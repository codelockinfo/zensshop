<?php
/**
 * Checkout Page
 */

// Start output buffering to prevent headers already sent errors
ob_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Cart.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/CustomerAuth.php';

$baseUrl = getBaseUrl();
$cart = new Cart();
$order = new Order();
$auth = new CustomerAuth();

// Require login for checkout
if (!$auth->isLoggedIn()) {
    ob_end_clean();
    header('Location: ' . url('login?redirect=checkout'));
    exit;
}

// Get cart items
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();

$customer = null;
if ($auth->isLoggedIn()) {
    $customer = $auth->getCurrentCustomer();
    // Ensure store_id is in session if not already (for users logged in before the fix)
    if (!isset($_SESSION['store_id']) && $customer && isset($customer['store_id'])) {
        $_SESSION['store_id'] = $customer['store_id'];
    }
}

// Redirect if cart is empty
if (empty($cartItems)) {
    ob_end_clean();
    header('Location: ' . url('cart'));
    exit;
}

$error = '';
$success = false;
$orderId = null;
$shippingAmount = 5.00; // Default shipping
$discountAmount = 0;
$discountCode = '';

// Process discount code
$discountError = ''; // Specific error for discount field
if (isset($_POST['remove_discount'])) {
    unset($_SESSION['checkout_discount_code']);
    $discountCode = '';
    $discountAmount = 0;
} elseif (isset($_POST['apply_discount']) || isset($_POST['place_order']) || isset($_SESSION['checkout_discount_code'])) {
    // Prefer POST, then Session
    $codeToValidate = '';
    if (isset($_POST['apply_discount']) || isset($_POST['place_order'])) {
        $codeToValidate = trim($_POST['discount_code'] ?? '');
    } elseif (isset($_SESSION['checkout_discount_code'])) {
        $codeToValidate = $_SESSION['checkout_discount_code'];
    }

    if (!empty($codeToValidate)) {
        require_once __DIR__ . '/classes/Discount.php';
        $discountManager = new Discount();
        try {
            $currentTotal = $cart->getTotal();
            $userId = $customer['id'] ?? null;
            $discountAmount = $discountManager->calculateAmount($codeToValidate, $currentTotal, $userId);
            // If successful, save to session and variables
            $_SESSION['checkout_discount_code'] = $codeToValidate;
            $discountCode = $codeToValidate;
        } catch (Exception $e) {
            // If explicitly applying, show specific error
            if (isset($_POST['apply_discount'])) {
                $discountError = $e->getMessage();
            }
            // If just loading from session and it fails (e.g. cart invalid now), clear session
            if (isset($_SESSION['checkout_discount_code']) && !isset($_POST['apply_discount'])) {
                unset($_SESSION['checkout_discount_code']);
            }
            // Reset logic
            $discountCode = ''; 
            $discountAmount = 0;
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate Limiting (Prevent spam submissions)
if (isset($_SESSION['last_checkout_attempt']) && (time() - $_SESSION['last_checkout_attempt'] < 5)) {
    // If request is within 5 seconds of previous one
    $error = "Please wait a moment before trying again.";
}
$_SESSION['last_checkout_attempt'] = time();

// Process order if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && empty($error)) {
    try {
        // 1. Honeypot Check (Anti-Bot)
        // If this hidden field is filled, it's a bot
        if (!empty($_POST['hp_website_check'])) {
            // Silently fail or throw error (Silent is better to confuse bots, but for UX we just stop)
            throw new Exception("Security check failed.");
        }

        // 2. CSRF Check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Security check failed. Please refresh the page.");
        }

        // 3. Validate required fields
        $required = ['customer_name', 'customer_email', 'phone', 'country'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }

        // 4. Strict Server-Side Validation
        if (!filter_var($_POST['customer_email'], FILTER_VALIDATE_EMAIL)) {
             throw new Exception("Invalid email address format.");
        }
        
        // Basic phone validation (allow +, spaces, dashes, digits, min 7 chars)
        if (!preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/', trim($_POST['phone']))) {
             // Relaxed check to avoid blocking valid international formats too aggressively
             // Just checking if it has at least 7 digits
             if (strlen(preg_replace('/[^0-9]/', '', $_POST['phone'])) < 7) {
                 throw new Exception("Please enter a valid phone number.");
             }
        }
        
        // Block Direct POST for Online Payments (Must go through API/JS)
        $paymentMethod = $_POST['payment_method'] ?? 'cash_on_delivery';
        if ($paymentMethod === 'credit_card' || $paymentMethod === 'razorpay') {
             throw new Exception("Online payments must be processed via the secure payment window. Please click 'Pay Now'.");
        }

        // Get user ID if logged in
        $userId = null;
        if ($auth->isLoggedIn()) {
            $currentUser = $auth->getCurrentCustomer();
            $userId = $currentUser['customer_id'] ?? null;
            
            // Force re-match if ID is legacy/invalid
            if ($userId && $userId < 1000000000) {
                $userId = null;
            }
        }
        
        // Combine phone code and phone number
        $phoneCode = sanitize_input($_POST['phone_code'] ?? '+1');
        $phoneNumber = sanitize_input(trim($_POST['phone'] ?? ''));
        $fullPhone = $phoneCode . ' ' . $phoneNumber;
        
        // Prepare order data with sanitization
        $orderData = [
            'user_id' => $userId,
            'customer_name' => sanitize_input(trim($_POST['customer_name'])),
            'customer_email' => sanitize_input(trim($_POST['customer_email'])),
            'customer_phone' => $fullPhone,
            'billing_address' => [
                'street' => sanitize_input(trim($_POST['address'] ?? '')),
                'city' => sanitize_input(trim($_POST['city'] ?? '')),
                'state' => sanitize_input(trim($_POST['state'] ?? '')),
                'zip' => sanitize_input(trim($_POST['zip'] ?? '')),
                'country' => sanitize_input(trim($_POST['country_name'] ?? $_POST['country'] ?? 'India'))
            ],
            'shipping_address' => [
                'street' => sanitize_input(trim($_POST['address'] ?? '')),
                'city' => sanitize_input(trim($_POST['city'] ?? '')),
                'state' => sanitize_input(trim($_POST['state'] ?? '')),
                'zip' => sanitize_input(trim($_POST['zip'] ?? '')),
                'country' => sanitize_input(trim($_POST['country_name'] ?? $_POST['country'] ?? 'India'))
            ],
            'items' => [],
            'discount_amount' => $discountAmount,
            'coupon_code' => $discountCode,
            'shipping_amount' => ($_POST['delivery_type'] ?? '') === 'pickup' ? 0 : $shippingAmount,
            'tax_amount' => 0,
            'payment_method' => $paymentMethod
        ];
        
        // Prepare order items from cart
        foreach ($cartItems as $item) {
            $orderData['items'][] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'variant_attributes' => $item['variant_attributes'] ?? []
            ];
        }
        
        // Create order
        $orderResponse = $order->create($orderData);
        $orderId = $orderResponse['id'];
        $orderNumber = $orderResponse['order_number'];
        
        // Clear cart
        $cart->clear();
        
        // Clear output buffer before redirect
        ob_end_clean();
        
        // Redirect to thank you page
        header('Location: ' . url("order-success.php?order_number={$orderNumber}"));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Clear output buffer before including header
ob_end_clean();

$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/header.php';

// Calculate totals
$subtotal = $cartTotal;
$finalShipping = isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'pickup' ? 0 : $shippingAmount;
$tax = 0;
$total = $subtotal + $finalShipping - $discountAmount + $tax;
?>

<style>
/* Hide announcement bar and header on checkout page */
.bg-black.text-white.text-sm.py-2,
nav.bg-white.sticky.top-0 {
    display: none !important;
}
</style>

<section class="py-8 md:py-12 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <!-- Header: Logo & Progress -->
        <div class="max-w-6xl mx-auto mb-8 flex flex-col md:flex-row items-center justify-start gap-8 md:gap-12">
            <!-- Logo -->
            <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                <?php if ($siteLogoType == 'image'): ?>
                    <img src="<?php echo getImageUrl($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteLogoText); ?>" class="h-10 md:h-12 w-auto object-contain">
                <?php else: ?>
                    <span class="text-2xl md:text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($siteLogoText); ?></span>
                <?php endif; ?>
            </a>

            <!-- Progress Indicator -->
            <div class="flex items-center space-x-2 md:space-x-4 overflow-x-auto min-w-max">
                <!-- Cart Step -->
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-xs md:text-sm">
                        <i class="fas fa-check"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700 text-xs md:text-base">Cart</span>
                </div>
                <div class="w-6 md:w-12 h-0.5 bg-primary"></div>
                
                <!-- Review Step -->
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-xs md:text-sm">
                        <i class="fas fa-check"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700 text-xs md:text-base">Review</span>
                </div>
                <div class="w-6 md:w-12 h-0.5 bg-primary"></div>
                
                <!-- Checkout Step -->
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold text-xs md:text-sm">
                        3
                    </div>
                    <span class="ml-2 font-semibold text-blue-600 text-xs md:text-base">Checkout</span>
                </div>
            </div>
        </div>

        <!-- Error Message Container -->
        <div id="errorMessageContainer" class="hidden mb-6 max-w-6xl mx-auto">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <div class="flex items-center justify-between">
                    <span id="errorMessageText"></span>
                    <button onclick="hideErrorMessage()" class="text-red-700 hover:text-red-900 ml-4">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Success Message Container -->
        <div id="successMessageContainer" class="hidden mb-6 max-w-6xl mx-auto">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <div class="flex items-center justify-between">
                    <span id="successMessageText"></span>
                    <button onclick="hideSuccessMessage()" class="text-green-700 hover:text-green-900 ml-4">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <!-- <div id="php-error-msg" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 max-w-6xl mx-auto transition-opacity duration-500">
            <?php echo htmlspecialchars($error); ?>
        </div> -->
        <script>
            // Auto-hide error after 5 seconds
            setTimeout(function() {
                const errorMsg = document.getElementById('php-error-msg');
                if (errorMsg) {
                    errorMsg.style.opacity = '0';
                    setTimeout(() => errorMsg.style.display = 'none', 500); // Wait for fade out
                }
            }, 5000);

            // Prevent resubmission prompt on reload (PRG pattern via JS history)
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        </script>
        <?php endif; ?>

        <form method="POST" action="<?php echo url('checkout'); ?>" class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Section: Shipping Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg p-4 sm:p-6 md:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <a href="<?php echo url('cart'); ?>" class="text-gray-400 hover:text-black transition-colors" title="Back to Cart">
                                    <i class="fas fa-chevron-left text-xl"></i>
                                </a>
                                <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
                            </div>
                        </div>

                        <?php if ($customer): ?>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-8 flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-blue-800">Welcome back, <span class="font-bold"><?php echo htmlspecialchars($customer['name']); ?></span>! </p>
                                    <p class="text-xs text-blue-600 font-medium italic">Happy ordering! ✨</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-shipping-fast mr-3 text-gray-400"></i>
                            Shipping Information
                        </h2>
                        
                        <!-- Delivery Options -->
                        <div class="flex flex-col sm:flex-row gap-4 mb-8">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="delivery_type" value="delivery" checked class="hidden delivery-option" onchange="updateShipping()">
                                <div class="border-2 border-blue-600 rounded-lg p-4 flex items-center space-x-3 delivery-option-card">
                                    <i class="fas fa-truck text-blue-600 text-xl"></i>
                                    <span class="font-semibold text-blue-600">Delivery</span>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="delivery_type" value="pickup" class="hidden delivery-option" onchange="updateShipping()">
                                <div class="border-2 border-gray-300 rounded-lg p-4 flex items-center space-x-3 delivery-option-card">
                                    <i class="fas fa-box text-gray-400 text-xl"></i>
                                    <span class="font-semibold text-gray-400">Pick up</span>
                                </div>
                            </label>
                        </div>
                        
                        <!-- Form Fields -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Full name</label>
                                <input type="text" name="customer_name" required 
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($customer['name'] ?? '')); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Email address</label>
                                <input type="email" name="customer_email" required 
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ($customer['email'] ?? '')); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Phone number</label>
                                <div class="flex relative w-full">
                                    <select name="phone_code" id="phoneCodeSelect" class="px-3 py-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary bg-gray-50 appearance-none cursor-pointer" style="min-width: 90px;">
                                        <?php
                                        // Comprehensive phone country codes
                                        $phoneCodes = [
                                            '+91' => ['🇮🇳', '+91', 'IN'], // India - First
                                            '+1' => ['🇺🇸', '+1', 'US'], '+44' => ['🇬🇧', '+44', 'GB'], '+61' => ['🇦🇺', '+61', 'AU'], 
                                            '+49' => ['🇩🇪', '+49', 'DE'], '+33' => ['🇫🇷', '+33', 'FR'], '+39' => ['🇮🇹', '+39', 'IT'], 
                                            '+34' => ['🇪🇸', '+34', 'ES'], '+31' => ['🇳🇱', '+31', 'NL'], '+32' => ['🇧🇪', '+32', 'BE'], 
                                            '+41' => ['🇨🇭', '+41', 'CH'], '+43' => ['🇦🇹', '+43', 'AT'], '+46' => ['🇸🇪', '+46', 'SE'], 
                                            '+47' => ['🇳🇴', '+47', 'NO'], '+45' => ['🇩🇰', '+45', 'DK'], '+358' => ['🇫🇮', '+358', 'FI'],
                                            '+48' => ['🇵🇱', '+48', 'PL'], '+420' => ['🇨🇿', '+420', 'CZ'], '+353' => ['🇮🇪', '+353', 'IE'], 
                                            '+351' => ['🇵🇹', '+351', 'PT'], '+30' => ['🇬🇷', '+30', 'GR'], '+40' => ['🇷🇴', '+40', 'RO'], 
                                            '+36' => ['🇭🇺', '+36', 'HU'], '+359' => ['🇧🇬', '+359', 'BG'], '+385' => ['🇭🇷', '+385', 'HR'], 
                                            '+421' => ['🇸🇰', '+421', 'SK'], '+386' => ['🇸🇮', '+386', 'SI'], '+370' => ['🇱🇹', '+370', 'LT'],
                                            '+371' => ['🇱🇻', '+371', 'LV'], '+372' => ['🇪🇪', '+372', 'EE'], '+352' => ['🇱🇺', '+352', 'LU'], 
                                            '+356' => ['🇲🇹', '+356', 'MT'], '+357' => ['🇨🇾', '+357', 'CY'], '+354' => ['🇮🇸', '+354', 'IS'], 
                                            '+423' => ['🇱🇮', '+423', 'LI'], '+377' => ['🇲🇨', '+377', 'MC'], '+376' => ['🇦🇩', '+376', 'AD'], 
                                            '+378' => ['🇸🇲', '+378', 'SM'],
                                            '+81' => ['🇯🇵', '+81', 'JP'], '+86' => ['🇨🇳', '+86', 'CN'], '+82' => ['🇰🇷', '+82', 'KR'], 
                                           
                                            '+66' => ['🇹🇭', '+66', 'TH'], '+63' => ['🇵🇭', '+63', 'PH'], '+62' => ['🇮🇩', '+62', 'ID'], 
                                            '+84' => ['🇻🇳', '+84', 'VN'], '+852' => ['🇭🇰', '+852', 'HK'], '+886' => ['🇹🇼', '+886', 'TW'],
                                            '+64' => ['🇳🇿', '+64', 'NZ'], '+27' => ['🇿🇦', '+27', 'ZA'], '+20' => ['🇪🇬', '+20', 'EG'], 
                                            '+971' => ['🇦🇪', '+971', 'AE'], '+966' => ['🇸🇦', '+966', 'SA'], '+972' => ['🇮🇱', '+972', 'IL'], 
                                            '+90' => ['🇹🇷', '+90', 'TR'], '+7' => ['🇷🇺', '+7', 'RU'],
                                            '+55' => ['🇧🇷', '+55', 'BR'], '+52' => ['🇲🇽', '+52', 'MX'], '+54' => ['🇦🇷', '+54', 'AR'], 
                                            '+56' => ['🇨🇱', '+56', 'CL'], '+57' => ['🇨🇴', '+57', 'CO'], '+51' => ['🇵🇪', '+51', 'PE'], 
                                            '+58' => ['🇻🇪', '+58', 'VE'], '+593' => ['🇪🇨', '+593', 'EC'], '+598' => ['🇺🇾', '+598', 'UY'], 
                                            '+595' => ['🇵🇾', '+595', 'PY'], '+591' => ['🇧🇴', '+591', 'BO'], '+506' => ['🇨🇷', '+506', 'CR'],
                                            '+507' => ['🇵🇦', '+507', 'PA'], '+502' => ['🇬🇹', '+502', 'GT'], '+53' => ['🇨🇺', '+53', 'CU'],
                                            '+234' => ['🇳🇬', '+234', 'NG'], '+254' => ['🇰🇪', '+254', 'KE'], '+233' => ['🇬🇭', '+233', 'GH'], 
                                            '+255' => ['🇹🇿', '+255', 'TZ'], '+251' => ['🇪🇹', '+251', 'ET'], '+256' => ['🇺🇬', '+256', 'UG'], 
                                            '+212' => ['🇲🇦', '+212', 'MA'], '+216' => ['🇹🇳', '+216', 'TN'], '+213' => ['🇩🇿', '+213', 'DZ'], 
                                            '+218' => ['🇱🇾', '+218', 'LY'], '+249' => ['🇸🇩', '+249', 'SD'], '+244' => ['🇦🇴', '+244', 'AO'],
                                            '+258' => ['🇲🇿', '+258', 'MZ'], '+260' => ['🇿🇲', '+260', 'ZM'], '+263' => ['🇿🇼', '+263', 'ZW'], 
                                            '+267' => ['🇧🇼', '+267', 'BW'], '+264' => ['🇳🇦', '+264', 'NA'], '+230' => ['🇲🇺', '+230', 'MU'], 
                                            '+248' => ['🇸🇨', '+248', 'SC'], '+960' => ['🇲🇻', '+960', 'MV'],
                                            '+880' => ['🇧🇩', '+880', 'BD'], '+92' => ['🇵🇰', '+92', 'PK'], '+94' => ['🇱🇰', '+94', 'LK'], 
                                            '+977' => ['🇳🇵', '+977', 'NP'], '+975' => ['🇧🇹', '+975', 'BT'], '+95' => ['🇲🇲', '+95', 'MM'], 
                                            '+855' => ['🇰🇭', '+855', 'KH'], '+856' => ['🇱🇦', '+856', 'LA'], '+673' => ['🇧🇳', '+673', 'BN'], 
                                            '+679' => ['🇫🇯', '+679', 'FJ'], '+675' => ['🇵🇬', '+675', 'PG'], '+687' => ['🇳🇨', '+687', 'NC'], 
                                            '+689' => ['🇵🇫', '+689', 'PF'],
                                        ];
                                        
                                        $selectedCode = $_POST['phone_code'] ?? '+91'; // Default to India
                                        foreach ($phoneCodes as $code => $data) {
                                            $flag = $data[0];
                                            $display = $flag . ' ' . $code;
                                            $selected = ($selectedCode === $code) ? 'selected' : '';
                                            echo "<option value=\"{$code}\" {$selected}>{$display}</option>\n";
                                        }
                                        ?>
                                    </select>
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                    </div>
                                    <input type="tel" name="phone" required 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                           placeholder="425 151 2318"
                                           maxlength="10"
                                           class="flex-1 min-w-0 px-4 py-3 border border-gray-300 border-l-0 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Address</label>
                                <input type="text" name="address" 
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">City</label>
                                    <input type="text" name="city" 
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">State</label>
                                    <input type="text" name="state" 
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">ZIP Code</label>
                                    <input type="text" name="zip" 
                                           value="<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">Country</label>
                                    <input type="text" name="country" 
                                           value="<?php echo htmlspecialchars($_POST['country'] ?? 'India'); ?>"
                                           required
                                           placeholder="Enter country name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Section: Review Cart -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg p-6 sticky top-4">
                        <h2 class="text-xl font-bold mb-6">Review your cart</h2>
                        
                        <!-- Cart Items -->
                        <div class="space-y-4 mb-6">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="flex items-center space-x-3">
                                <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://placehold.co/80'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="w-16 h-16 object-cover rounded"
                                     onerror="this.src='https://placehold.co/150x150?text=Product+Image'">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-sm text-gray-800 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 2.5rem; line-height: 1.25rem;" title="<?php echo htmlspecialchars($item['name']); ?>"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-xs text-gray-500">Quantity: <?php echo $item['quantity']; ?>x</p>
                                    <?php if (!empty($item['variant_attributes']) && is_array($item['variant_attributes'])): ?>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <?php foreach ($item['variant_attributes'] as $key => $value): ?>
                                                <span class="text-[10px] text-gray-500 bg-gray-100 px-1 rounded">
                                                    <?php echo htmlspecialchars($key); ?>: <?php echo htmlspecialchars($value); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <p class="font-bold text-gray-800"><?php echo format_currency($item['price'] * $item['quantity']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Discount Code -->
                        <!-- Discount Code -->
                        <!-- Discount Code -->
                        <div class="mb-6" id="discountSection">
                            <!-- Applied State -->
                            <div id="appliedState" class="<?php echo $discountAmount > 0 ? '' : 'hidden'; ?>">
                                <input type="hidden" name="discount_code" id="hiddenDiscountCode" value="<?php echo htmlspecialchars($discountCode); ?>">
                                <div class="flex items-center justify-between py-1 px-5 bg-[#e5e7eb] border border-gray-300 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-tag text-gray-600"></i>
                                        <span class="font-medium text-gray-700" id="appliedCodeText"><?php echo htmlspecialchars($discountCode); ?></span>
                                    </div>
                                    <button type="button" id="btnRemoveDiscount" class="text-gray-500 hover:text-red-600 transition focus:outline-none p-1">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-600 mt-2 ml-1">Discount applied successfully!</p>
                            </div>

                            <!-- Input State -->
                            <div id="inputState" class="<?php echo $discountAmount > 0 ? 'hidden' : ''; ?>">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input type="text" id="discountInput" 
                                           placeholder="Discount code" 
                                           class="w-full sm:flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <button type="button" id="btnApplyDiscount" class="w-full sm:w-auto px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-semibold">
                                        Apply
                                    </button>
                                </div>
                                <p id="discountErrorMsg" class="text-sm text-red-500 mt-2 ml-1 hidden"></p>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="border-t pt-4 space-y-2 mb-6">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-semibold"><?php echo format_currency($subtotal); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Shipping</span>
                                <span id="summaryShipping" class="font-semibold"><?php echo format_currency($finalShipping); ?></span>
                            </div>
                            <div class="flex justify-between text-sm <?php echo $discountAmount > 0 ? '' : 'hidden'; ?>" id="summaryDiscountRow">
                                <span class="text-gray-600">Discount</span>
                                <span class="font-semibold text-gray-600" id="summaryDiscountAmount">-<?php echo format_currency($discountAmount); ?></span>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                <span>Total</span>
                                <span id="summaryTotal"><?php echo format_currency($total); ?></span>
                            </div>
                        </div>
                        
                        <!-- Payment Method Logos -->
                        <div class="flex justify-center items-center space-x-3 mb-6">
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Visa_Inc._logo.svg.png" alt="Visa" class="h-8 object-contain">
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Mastercard-logo.svg.png" alt="Mastercard" class="h-8 object-contain">
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/American_Express_logo.svg.png" alt="American Express" class="h-8 object-contain">
                        </div>
                        
                        <!-- Payment Method Selection -->
                        <input type="hidden" name="payment_method" value="credit_card">
                        
                        <!-- Pay Now Button -->
                        <button type="button" id="razorpayPayButton" class="w-full bg-blue-600 text-white py-4 px-6 rounded-lg hover:bg-blue-700 transition font-semibold mb-4">
                            Pay Now
                        </button>
                        <!-- Honeypot Field (Hidden from users, visible to bots) -->
                        <div style="display:none; opacity:0; visibility:hidden; position:absolute; left:-9999px;">
                            <input type="text" name="hp_website_check" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="place_order" value="1">
                        
                        <!-- Security Message -->
                        <div class="text-center text-xs text-gray-500">
                            <div class="flex items-center justify-center space-x-2 mb-2">
                                <i class="fas fa-lock text-gray-400"></i>
                                <span class="font-semibold">Secure Checkout - SSL Encrypted</span>
                            </div>
                            <p>Ensuring your financial and personal details are secure during every transaction.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
// Update delivery option styling
document.querySelectorAll('.delivery-option').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.delivery-option-card').forEach(card => {
            card.classList.remove('border-blue-600', 'text-blue-600');
            card.classList.add('border-gray-300', 'text-gray-400');
            card.querySelector('i').classList.remove('text-blue-600');
            card.querySelector('i').classList.add('text-gray-400');
            card.querySelector('span').classList.remove('text-blue-600');
            card.querySelector('span').classList.add('text-gray-400');
        });
        
        if (this.checked) {
            const card = this.closest('label').querySelector('.delivery-option-card');
            card.classList.remove('border-gray-300', 'text-gray-400');
            card.classList.add('border-blue-600', 'text-blue-600');
            card.querySelector('i').classList.remove('text-gray-400');
            card.querySelector('i').classList.add('text-blue-600');
            card.querySelector('span').classList.remove('text-gray-400');
            card.querySelector('span').classList.add('text-blue-600');
        }
    });
});

// Global state for calculations
window.cartTotal = <?php echo $cartTotal; ?>;
window.currentDiscountAmount = <?php echo $discountAmount; ?>;
window.currentDiscountCode = '<?php echo $discountCode; ?>';
window.defaultShipping = <?php echo $shippingAmount; ?>;

function updateShipping() {
    const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
    const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
    
    const shipping = deliveryType === 'pickup' ? 0 : window.defaultShipping;
    // Ensure total doesn't go negative
    const total = Math.max(0, window.cartTotal + shipping - window.currentDiscountAmount);
    
    // Update DOM
    const shippingEl = document.getElementById('summaryShipping');
    const totalEl = document.getElementById('summaryTotal');
    
    if (shippingEl) shippingEl.innerText = '₹' + shipping.toFixed(2);
    if (totalEl) totalEl.innerText = '₹' + total.toFixed(2);
}


    

    


// Error/Success Message Functions
let errorMessageTimeout = null;
let successMessageTimeout = null;

function setBtnLoading(btn, isLoading) {
    if (isLoading) {
        // Save original text if not already saved
        if (!btn.dataset.originalText) {
            btn.dataset.originalText = btn.innerHTML;
        }
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        // Restore original text
        if (btn.dataset.originalText) {
            btn.innerHTML = btn.dataset.originalText;
        }
        btn.disabled = false;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    }
}

function showErrorMessage(message) {
    const container = document.getElementById('errorMessageContainer');
    const text = document.getElementById('errorMessageText');
    if (container && text) {
        // Clear any existing timeout
        if (errorMessageTimeout) {
            clearTimeout(errorMessageTimeout);
            errorMessageTimeout = null;
        }
        
        text.textContent = message;
        container.classList.remove('hidden');
        // Scroll to top to show error
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Auto-hide after 5 seconds
        errorMessageTimeout = setTimeout(function() {
            hideErrorMessage();
        }, 5000);
    }
}

function hideErrorMessage() {
    const container = document.getElementById('errorMessageContainer');
    if (container) {
        container.classList.add('hidden');
        // Clear timeout if message is manually closed
        if (errorMessageTimeout) {
            clearTimeout(errorMessageTimeout);
            errorMessageTimeout = null;
        }
    }
}

function showSuccessMessage(message) {
    const container = document.getElementById('successMessageContainer');
    const text = document.getElementById('successMessageText');
    if (container && text) {
        // Clear any existing timeout
        if (successMessageTimeout) {
            clearTimeout(successMessageTimeout);
            successMessageTimeout = null;
        }
        
        text.textContent = message;
        container.classList.remove('hidden');
        // Scroll to top to show success
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Auto-hide after 5 seconds
        successMessageTimeout = setTimeout(function() {
            hideSuccessMessage();
        }, 5000);
    }
}

function hideSuccessMessage() {
    const container = document.getElementById('successMessageContainer');
    if (container) {
        container.classList.add('hidden');
        // Clear timeout if message is manually closed
        if (successMessageTimeout) {
            clearTimeout(successMessageTimeout);
            successMessageTimeout = null;
        }
    }
}

// Razorpay Integration
const razorpayBaseUrl = '<?php echo $baseUrl; ?>';

// NOTE: We do not define const for amounts here as they are dynamic (window.cartTotal etc)

document.getElementById('razorpayPayButton').addEventListener('click', async function(e) {
    e.preventDefault();
    
    // Validate form fields
    const customerName = document.querySelector('input[name="customer_name"]').value.trim();
    const customerEmail = document.querySelector('input[name="customer_email"]').value.trim();
    const customerPhone = document.querySelector('input[name="phone"]').value.trim();
    const phoneCode = document.querySelector('select[name="phone_code"]').value;
    const address = document.querySelector('input[name="address"]').value.trim();
    const city = document.querySelector('input[name="city"]').value.trim();
    const state = document.querySelector('input[name="state"]').value.trim();
    const zip = document.querySelector('input[name="zip"]').value.trim();
    const country = document.querySelector('input[name="country"]').value.trim();
    const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
    const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
    
    // Hide any previous error messages
    hideErrorMessage();
    
    // Check validation - specifically checking 'country' now instead of countryCode
    if (!customerName || !customerEmail || !customerPhone || !address || !city || !state || !zip || !country) {
        console.error('[RAZORPAY] Validation failed: Missing required fields');
        showErrorMessage('Please fill in all required fields');
        return;
    }
    
    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(customerEmail)) {
        console.error('[RAZORPAY] Validation failed: Invalid email address');
        showErrorMessage('Please enter a valid email address');
        return;
    }
    
    // Calculate final shipping and total based on delivery type USING GLOBAL VARIABLES
    const finalShipping = deliveryType === 'pickup' ? 0 : window.defaultShipping;
    const finalTotal = Math.max(0, window.cartTotal + finalShipping - window.currentDiscountAmount);
    
    // Disable button to prevent double clicks
    const button = this;
    setBtnLoading(button, true);
    
    try {
        // Create Razorpay order
        const requestData = {
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: phoneCode + ' ' + customerPhone,
            amount: finalTotal,
            shipping_amount: finalShipping,
            discount_amount: window.currentDiscountAmount
        };
        
        const orderResponse = await fetch(razorpayBaseUrl + '/api/razorpay/create-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });
        
        const orderData = await orderResponse.json();
        
        if (!orderData.success) {
            showErrorMessage('Something went wrong');
            setBtnLoading(button, false);
            return;
        }
        
        // Prepare order data for verification
        const orderInfo = {
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: phoneCode + ' ' + customerPhone,
            billing_address: {
                street: address,
                city: city,
                state: state,
                zip: zip,
                country: country
            },
            shipping_address: {
                street: address,
                city: city,
                state: state,
                zip: zip,
                country: country
            },
            discount_amount: window.currentDiscountAmount,
            coupon_code: window.currentDiscountCode,
            shipping_amount: finalShipping,
            tax_amount: 0
        };
        
        // Razorpay options
        const options = {
            key: orderData.razorpay_key,
            amount: orderData.amount,
            currency: orderData.currency,
            name: '<?php echo SITE_NAME; ?>',
            description: 'Order Payment',
            order_id: orderData.order_id,
            handler: async function(response) {
                try {
                    // Verify payment
                    const verifyResponse = await fetch(razorpayBaseUrl + '/api/razorpay/verify-payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                            order_data: orderInfo
                        })
                    });
                    
                    const verifyData = await verifyResponse.json();
                    
                    if (verifyData.success) {
                        // Ensure order_id exists
                        if (!verifyData.order_id) {
                            throw new Error('Order ID not received from server');
                        }
                        
                        // Redirect to success page with order number
                        const successUrl = razorpayBaseUrl + '/order-success?order_number=' + encodeURIComponent(verifyData.order_number);
                        // Use window.location.replace to prevent back button issues
                        window.location.replace(successUrl);
                    } else {
                        showErrorMessage(verifyData.message || 'Payment verification failed');
                        setBtnLoading(button, false);
                    }
                } catch (error) {
                    console.error(error);
                    showErrorMessage('Something went wrong processing payment');
                    setBtnLoading(button, false);
                }
            },
            prefill: {
                name: customerName,
                email: customerEmail,
                contact: phoneCode + customerPhone
            },
            theme: {
                color: '#2563eb'
            },
            modal: {
                ondismiss: function() {
                    setBtnLoading(button, false);
                }
            }
        };
        
        const razorpay = new Razorpay(options);
        razorpay.open();
        
    } catch (error) {
        console.error(error);
        showErrorMessage('Something went wrong initiating payment');
        setBtnLoading(button, false);
    }
});

/* Discount Code Handler */
document.getElementById('btnApplyDiscount').addEventListener('click', function() {
    handleDiscount('apply');
});

document.getElementById('btnRemoveDiscount').addEventListener('click', function() {
    handleDiscount('remove');
});

let discountTimeout = null;
function handleDiscount(action) {
    const btn = action === 'apply' ? document.getElementById('btnApplyDiscount') : document.getElementById('btnRemoveDiscount');
    const input = document.getElementById('discountInput');
    const errorMsg = document.getElementById('discountErrorMsg');

    if (discountTimeout) clearTimeout(discountTimeout);
    
    // Validate apply
    if (action === 'apply' && !input.value.trim()) {
        if(errorMsg) {
            errorMsg.textContent = 'Please enter a discount code';
            errorMsg.classList.remove('hidden');
            // Auto hide after 5 seconds
            discountTimeout = setTimeout(() => {
                errorMsg.classList.add('hidden');
            }, 5000);
        } else {
            showErrorMessage('Please enter a discount code');
        }
        return;
    }
    
    // Clear errors
    if (errorMsg) errorMsg.classList.add('hidden');
    
    setBtnLoading(btn, true);
    
    // Prepare Data
    const data = {
        action: action,
        code: action === 'apply' ? input.value.trim() : ''
    };
    
    fetch('<?php echo $baseUrl; ?>/api/cart-discount.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        setBtnLoading(btn, false);
        
        if (res.success) {
            // Update UI State
            if (action === 'apply') {
                document.getElementById('appliedState').classList.remove('hidden');
                document.getElementById('inputState').classList.add('hidden');
                document.getElementById('appliedCodeText').textContent = res.code;
                document.getElementById('hiddenDiscountCode').value = res.code;
                window.currentDiscountCode = res.code; // Update JS global
                showSuccessMessage(res.message);
            } else {
                document.getElementById('appliedState').classList.add('hidden');
                document.getElementById('inputState').classList.remove('hidden');
                input.value = ''; // Clear input
                document.getElementById('hiddenDiscountCode').value = '';
                window.currentDiscountCode = ''; // Update JS global
                showSuccessMessage('Discount removed');
            }
            
            // Update Discount Row DOM
            const discountRow = document.getElementById('summaryDiscountRow');
            const discountAmountEl = document.getElementById('summaryDiscountAmount');
            
            if (res.discount_amount > 0) {
                if (discountRow) discountRow.classList.remove('hidden');
                if (discountAmountEl) discountAmountEl.textContent = '-₹' + parseFloat(res.discount_amount).toFixed(2);
            } else {
                if (discountRow) discountRow.classList.add('hidden');
            }
            
            // Update Global discount variable for `updateShipping` and Razorpay
            window.currentDiscountAmount = parseFloat(res.discount_amount);
             
            // Trigger recalculation (which handles shipping + new discount)
            updateShipping();
            
        } else {
            // Error handling
            if (action === 'apply') {
                 if (errorMsg) {
                     errorMsg.textContent = res.message;
                     errorMsg.classList.remove('hidden');
                     // Auto hide after 5 seconds
                     discountTimeout = setTimeout(() => {
                         errorMsg.classList.add('hidden');
                     }, 5000);
                 } else {
                     showErrorMessage(res.message);
                 }
            } else {
                showErrorMessage(res.message);
            }
        }
    })
    .catch(err => {
        console.error(err);
        setBtnLoading(btn, false);
        showErrorMessage('An error occurred. Please try again.');
    });
}
</script>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<style>
.delivery-option-card {
    transition: all 0.3s ease;
}

.delivery-option-card:hover {
    border-color: #2563eb;
}

/* Country dropdown styling */
.country-dropdown-wrapper {
    position: relative;
}

#countryDropdownBtn {
    transition: all 0.2s ease;
}

#countryDropdownBtn:hover {
    border-color: #2563eb;
}

#countryDropdown {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.country-option {
    transition: background-color 0.15s ease;
}

.country-option:hover {
    background-color: #f3f4f6;
}

.country-option:active {
    background-color: #e5e7eb;
}

#phoneCodeSelect {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2rem;
}
</style>


