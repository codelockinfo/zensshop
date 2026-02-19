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
require_once __DIR__ . '/classes/Database.php';

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

// Fetch checkout payment icons from database using Settings class
require_once __DIR__ . '/classes/Settings.php';
$settingsManager = new Settings();
$checkoutPaymentIconsJson = $settingsManager->get('checkout_payment_icons_json', '[]');
$checkoutPaymentIcons = json_decode($checkoutPaymentIconsJson, true) ?: [];


// Redirect if cart is empty
if (empty($cartItems)) {
    ob_end_clean();
    header('Location: ' . url('cart'));
    exit;
}

$error = '';
$success = false;
$orderId = null;
$shippingAmount = 0.00; // Default shipping
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
        
        // Dynamic Shipping Cost Calculation
        if (($_POST['delivery_type'] ?? '') !== 'pickup') {
            require_once __DIR__ . '/includes/shipping_helper.php';
            // Validate serviceability and get cost
            // If API fails or pincode invalid, it throws exception which is caught by main try-catch
            $shippingAmount = getDelhiveryShippingCost(trim($_POST['zip']), $paymentMethod);
        } else {
            $shippingAmount = 0;
        }

        // COD Charge
        $codCharge = 0;
        if ($paymentMethod === 'cash_on_delivery') {
            require_once __DIR__ . '/classes/Settings.php';
            $stManager = new Settings();
            $codCharge = (float)$stManager->get('cod_charge', 0);
        }
        
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
            'cod_charge' => $codCharge,
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
        
        // Auto-create Delhivery Shipment
        try {
            require_once __DIR__ . '/classes/Delhivery.php';
            $delhivery = new Delhivery();
            $delhivery->autoCreateShipment($orderId);
        } catch (Exception $e) {
            error_log("Failed to auto-create Delhivery shipment for order " . $orderNumber . ": " . $e->getMessage());
        }

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
$isCheckout = true;
require_once __DIR__ . '/includes/header.php';

// Re-fetch settings locally to ensure variables exist if header doesn't pass them
$settingsObj = new Settings();
$logoType = $settingsObj->get('footer_logo_type', 'image');
$logoText = $settingsObj->get('footer_logo_text', 'HomeproX');
$logo = $settingsObj->get('footer_logo_image', null);

// Calculate totals
$subtotal = $cartTotal;
$finalShipping = isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'pickup' ? 0 : $shippingAmount;
$tax = 0;
$isCodEnabled = (int)$settingsObj->get('enable_cod', 0);
$codChargeValue = (float)$settingsObj->get('cod_charge', 0);
$selectedPaymentMethod = $_POST['payment_method'] ?? 'credit_card'; // Default to online
$codAdjustment = ($selectedPaymentMethod === 'cash_on_delivery') ? $codChargeValue : 0;

$total = $subtotal + $finalShipping - $discountAmount + $tax + $codAdjustment;
// Load Checkout Page Styling (Consolidated)
$checkoutStylingJson = $settingsObj->get('checkout_page_styling', '');
$checkoutStyling = !empty($checkoutStylingJson) ? json_decode($checkoutStylingJson, true) : [];

// Helper function locally for checkout page
function getCheckoutStyle($key, $default, $settingsObj, $checkoutStyling) {
    if (isset($checkoutStyling[$key])) return $checkoutStyling[$key];
    return $settingsObj->get($key, $default);
}
?>

<style>
/* Hide announcement bar and header on checkout page */
.bg-black.text-white.text-sm.py-2,
nav.bg-white.sticky.top-0 {
    display: none !important;
}

/* Dynamic Checkout Styles */
:root {
    --checkout-prog-active-bg: <?php echo getCheckoutStyle('checkout_progress_active_bg', '#2563eb', $settingsObj, $checkoutStyling); ?>;
    --checkout-prog-active-text: <?php echo getCheckoutStyle('checkout_progress_active_text', '#ffffff', $settingsObj, $checkoutStyling); ?>;
    --checkout-prog-inactive-bg: <?php echo getCheckoutStyle('checkout_progress_inactive_bg', '#e5e7eb', $settingsObj, $checkoutStyling); ?>;
    --checkout-prog-inactive-text: <?php echo getCheckoutStyle('checkout_progress_inactive_text', '#374151', $settingsObj, $checkoutStyling); ?>;
    
    --checkout-welcome-bg: <?php echo getCheckoutStyle('checkout_welcome_bg', '#eff6ff', $settingsObj, $checkoutStyling); ?>;
    --checkout-welcome-text: <?php echo getCheckoutStyle('checkout_welcome_text', '#1e40af', $settingsObj, $checkoutStyling); ?>;
    --checkout-welcome-border: <?php echo getCheckoutStyle('checkout_welcome_border', '#dbeafe', $settingsObj, $checkoutStyling); ?>;
    
    --checkout-heading-color: <?php echo getCheckoutStyle('checkout_heading_color', '#111827', $settingsObj, $checkoutStyling); ?>;
    --checkout-label-color: <?php echo getCheckoutStyle('checkout_label_color', '#374151', $settingsObj, $checkoutStyling); ?>;
    
    --checkout-input-border: <?php echo getCheckoutStyle('checkout_input_border', '#d1d5db', $settingsObj, $checkoutStyling); ?>;
    --checkout-input-focus: <?php echo getCheckoutStyle('checkout_input_focus', '#3b82f6', $settingsObj, $checkoutStyling); ?>;
    --checkout-input-text: <?php echo getCheckoutStyle('checkout_input_text_color', '#111827', $settingsObj, $checkoutStyling); ?>;
    
    --checkout-summary-bg: <?php echo getCheckoutStyle('checkout_summary_bg', '#ffffff', $settingsObj, $checkoutStyling); ?>;
    --checkout-summary-border: <?php echo getCheckoutStyle('checkout_summary_border', '#ffffff', $settingsObj, $checkoutStyling); ?>;
    --checkout-summary-text: <?php echo getCheckoutStyle('checkout_summary_text', '#111827', $settingsObj, $checkoutStyling); ?>;
    
    --checkout-pay-bg: <?php echo getCheckoutStyle('checkout_pay_btn_bg', '#2563eb', $settingsObj, $checkoutStyling); ?>;
    --checkout-pay-text: <?php echo getCheckoutStyle('checkout_pay_btn_text', '#ffffff', $settingsObj, $checkoutStyling); ?>;
    --checkout-pay-hover: <?php echo getCheckoutStyle('checkout_pay_btn_hover_bg', '#1d4ed8', $settingsObj, $checkoutStyling); ?>;
}

.checkout-step-active {
    background-color: var(--checkout-prog-active-bg) !important;
    color: var(--checkout-prog-active-text) !important;
}
/* For completed/inactive steps, we might want different styling */
/* Current design has 'completed' as primary color. Let's map inactive settings to 'other' steps if needed, 
   but for now let's just use it generic or for future steps */
.checkout-step-inactive {
    background-color: var(--checkout-prog-inactive-bg) !important;
    color: var(--checkout-prog-inactive-text) !important;
}

.checkout-welcome {
    background-color: var(--checkout-welcome-bg) !important;
    border-color: var(--checkout-welcome-border) !important;
}
.checkout-welcome-text {
    color: var(--checkout-welcome-text) !important;
}

.checkout-heading {
    color: var(--checkout-heading-color) !important;
}
.checkout-label {
    color: var(--checkout-label-color) !important;
}
.checkout-input {
    border-color: var(--checkout-input-border) !important;
    color: var(--checkout-input-text) !important;
}
.checkout-input:focus {
    border-color: var(--checkout-input-focus) !important;
    box-shadow: 0 0 0 2px var(--checkout-input-focus) !important;
}

.checkout-summary {
    background-color: var(--checkout-summary-bg) !important;
    border: 1px solid var(--checkout-summary-border) !important;
}
.checkout-summary-text {
    color: var(--checkout-summary-text) !important;
}

.checkout-pay-btn {
    background-color: var(--checkout-pay-bg) !important;
    color: var(--checkout-pay-text) !important;
}
.checkout-pay-btn:hover {
    background-color: var(--checkout-pay-hover) !important;
}
</style>

<section class="py-8 md:py-12 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <!-- Header: Logo & Progress -->
        <div class="max-w-6xl mx-auto mb-8 flex flex-col md:flex-row items-center justify-start gap-8 md:gap-12">
            <!-- Logo -->
            <!-- Logo -->
            <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                <?php if ($logoType == 'image' && !empty($logo)): ?>
                    <img src="<?php echo getImageUrl($logo); ?>" alt="<?php echo htmlspecialchars($logoText); ?>" class="h-[60px] w-auto object-contain">
                <?php else: ?>
                    <span class="text-2xl md:text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($logoText); ?></span>
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
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold text-xs md:text-sm checkout-step-active">
                        3
                    </div>
                    <span class="ml-2 font-semibold text-blue-600 text-xs md:text-base">Checkout</span>
                </div>
            </div>
        </div>

        <!-- Error Message Container (Updated with transitions) -->
        <div id="errorMessageContainer" class="hidden mb-6 max-w-6xl mx-auto transition-all duration-500 ease-in-out opacity-0 transform -translate-y-4">
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

        <form id="checkoutForm" method="POST" action="<?php echo url('checkout'); ?>" class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Section: Shipping Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg p-4 sm:p-6 md:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <a href="<?php echo url('cart'); ?>" class="text-gray-400 hover:text-black transition-colors" title="Back to Cart">
                                    <i class="fas fa-chevron-left text-xl"></i>
                                </a>
                                <h1 class="text-3xl font-bold text-gray-900 checkout-heading">Checkout</h1>
                            </div>
                        </div>

                        <?php if ($customer): ?>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-8 flex items-center space-x-3 checkout-welcome">
                                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-blue-800 checkout-welcome-text">Welcome back, <span class="font-bold"><?php echo htmlspecialchars($customer['name']); ?></span>! </p>
                                    <p class="text-xs text-blue-600 font-medium italic checkout-welcome-text">Happy ordering! ✨</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center checkout-heading">
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
                                       pattern="[a-zA-Z\s\.-]{2,50}"
                                       title="Please enter a valid name (letters only)"
                                       onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32 || event.charCode === 46 || event.charCode === 45"
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($customer['name'] ?? '')); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Email address</label>
                                <input type="email" name="customer_email" required 
                                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                       title="Please enter a valid email address (e.g., user@example.com)"
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ($customer['email'] ?? '')); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Phone number</label>
                                <div class="flex relative w-full">
                                    <select name="phone_code" id="phoneCodeSelect" class="px-3 py-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary bg-gray-50 appearance-none cursor-pointer checkout-input" style="min-width: 90px;">
                                        <?php
                                        // Comprehensive phone country codes with lengths
                                        // Format: [Flag, Code, ISO, Length]
                                        $phoneCodes = [
                                            '+91' => ['🇮🇳', '+91', 'IN', 10], // India
                                            '+1' => ['🇺🇸', '+1', 'US', 10],   // USA/Canada
                                            '+44' => ['��', '+44', 'GB', 10], // UK (excluding 0)
                                            '+61' => ['��', '+61', 'AU', 9],  // Australia (mobile often 9)
                                            '+49' => ['��', '+49', 'DE', 11], // Germany
                                            '+33' => ['��', '+33', 'FR', 9],  // France
                                            '+86' => ['��', '+86', 'CN', 11], // China
                                            '+81' => ['��', '+81', 'JP', 10], // Japan
                                            '+971' => ['��', '+971', 'AE', 9], // UAE
                                            '+966' => ['🇸�', '+966', 'SA', 9], // Saudi Arabia
                                            '+7' => ['��', '+7', 'RU', 10],   // Russia
                                            '+55' => ['��', '+55', 'BR', 11], // Brazil
                                            '+20' => ['��', '+20', 'EG', 10], // Egypt
                                            '+27' => ['��', '+27', 'ZA', 9],  // South Africa
                                            '+90' => ['��', '+90', 'TR', 10], // Turkey
                                            '+39' => ['��', '+39', 'IT', 10], // Italy
                                            '+34' => ['🇪🇸', '+34', 'ES', 9],  // Spain
                                            '+65' => ['��', '+65', 'SG', 8],  // Singapore
                                            '+60' => ['��', '+60', 'MY', 9],  // Malaysia
                                            '+62' => ['��', '+62', 'ID', 11], // Indonesia (can vary 10-12)
                                            '+63' => ['��', '+63', 'PH', 10], // Philippines
                                            '+92' => ['��', '+92', 'PK', 10], // Pakistan
                                            '+880' => ['🇧�', '+880', 'BD', 10], // Bangladesh
                                            // Defaults/Others
                                            '+41' => ['��', '+41', 'CH', 9],
                                            '+31' => ['��', '+31', 'NL', 9],
                                            '+32' => ['🇧�', '+32', 'BE', 9],
                                            '+46' => ['��', '+46', 'SE', 9],
                                            '+47' => ['🇳�', '+47', 'NO', 8],
                                            '+45' => ['��', '+45', 'DK', 8],
                                            '+358' => ['��', '+358', 'FI', 10],
                                            '+48' => ['🇵🇱', '+48', 'PL', 9],
                                            '+351' => ['��', '+351', 'PT', 9],
                                            '+30' => ['��', '+30', 'GR', 10],
                                            '+972' => ['��', '+972', 'IL', 9],
                                            '+52' => ['��', '+52', 'MX', 10],
                                            '+54' => ['��', '+54', 'AR', 10],
                                        ];
                                        
                                        $selectedCode = $_POST['phone_code'] ?? '+91'; // Default to India
                                        
                                        // Fallback if specific country isn't in top list, add generic
                                        if (!array_key_exists($selectedCode, $phoneCodes)) {
                                             // If it was one of the many others not listed above explicitly with length
                                             // We will just treat it as flexible if not found, or default 10
                                        }

                                        foreach ($phoneCodes as $code => $data) {
                                            $flag = $data[2]; // Use ISO code (index 2) instead of Flag Emoji (index 0) to fix encoding/display issues
                                            $display = $flag . ' ' . $code;
                                            $length = $data[3] ?? 10;
                                            $selected = ($selectedCode === $code) ? 'selected' : '';
                                            echo "<option value=\"{$code}\" data-length=\"{$length}\" {$selected}>{$display}</option>\n";
                                        }
                                        ?>
                                    </select>
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                    </div>
                                    <input type="tel" name="phone" id="phoneInput" required 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                           placeholder="Mobile number"
                                           pattern="[0-9]{10}"
                                           title="Please enter a valid 10-digit mobile number"
                                           onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                           maxlength="10"
                                           class="flex-1 min-w-0 px-4 py-3 border border-gray-300 border-l-0 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const phoneSelect = document.getElementById('phoneCodeSelect');
                                        const phoneInput = document.getElementById('phoneInput');
                                        
                                        function updatePhoneValidation() {
                                            const selectedOption = phoneSelect.options[phoneSelect.selectedIndex];
                                            const length = selectedOption.getAttribute('data-length') || 10;
                                            
                                            phoneInput.setAttribute('maxlength', length);
                                            phoneInput.setAttribute('pattern', '[0-9]{' + length + '}');
                                            phoneInput.setAttribute('title', 'Please enter a valid ' + length + '-digit mobile number');
                                            phoneInput.setAttribute('placeholder', 'Enter your number');
                                            
                                            // Optional: truncate value if too long
                                            if (phoneInput.value.length > length) {
                                                phoneInput.value = phoneInput.value.slice(0, length);
                                            }
                                        }
                                        
                                        // Run on change and on load
                                        phoneSelect.addEventListener('change', updatePhoneValidation);
                                        updatePhoneValidation(); // Set initial state
                                    });
                                </script>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Address</label>
                                <input type="text" name="address" required minlength="5"
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">City</label>
                                    <input type="text" name="city" id="cityInput" required
                                           pattern="[a-zA-Z\s]+"
                                           title="Please enter a valid city name (letters only)"
                                           onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32"
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">State</label>
                                    <input type="text" name="state" id="customerStateInput" required
                                           pattern="[a-zA-Z\s]+"
                                           title="Please enter a valid state name (letters only)"
                                           onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32"
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? $customer['shipping_address']['state'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input"
                                           onblur="recalculateTaxes()">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">ZIP Code</label>
                                    <input type="text" name="zip" id="zipInput" required
                                           pattern="[0-9\s-]{6}"
                                           title="Please enter a valid 6-digit ZIP code"
                                           onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 45 || event.charCode === 32"
                                           maxlength="6"
                                           value="<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <div id="zipStatus" class="mt-2 text-xs"></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">Country</label>
                                    <input type="text" name="country" 
                                           value="<?php echo htmlspecialchars($_POST['country'] ?? 'India'); ?>"
                                           required
                                           pattern="[a-zA-Z\s]+"
                                           title="Please enter a valid country name (letters only)"
                                           onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32"
                                           placeholder="Enter country name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Section: Review Cart -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg p-6 sticky top-4 checkout-summary">
                        <h2 class="text-xl font-bold mb-6 checkout-heading">Review your cart</h2>
                        
                        <!-- Cart Items -->
                        <div class="space-y-4 mb-6">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="flex items-center space-x-3">
                                <img src="<?php echo getImageUrl($item['image'] ?? ''); ?>" 
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
                                    <button type="button" id="btnApplyDiscount" 
                                            data-order-amount="<?php echo $cartTotal; ?>"
                                            data-coupon-code=""
                                            class="w-full sm:w-auto px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-semibold">
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
                            
                            <!-- Tax Section -->
                            <div id="taxSummarySection" class="hidden space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax</span>
                                    <span class="font-semibold" id="taxValueTotal">₹0.00</span>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm hidden" id="genericTaxRow">
                                <span class="text-gray-600">Tax</span>
                                <span class="font-semibold" id="genericTaxValue">₹0.00</span>
                            </div>
                            <div class="flex justify-between text-sm <?php echo $discountAmount > 0 ? '' : 'hidden'; ?>" id="summaryDiscountRow">
                                <span class="text-gray-600">Discount</span>
                                <span class="font-semibold text-gray-600" id="summaryDiscountAmount">-<?php echo format_currency($discountAmount); ?></span>
                            </div>
                            <div class="flex justify-between text-sm <?php echo $codAdjustment > 0 ? '' : 'hidden'; ?>" id="codChargeRow">
                                <span class="text-gray-600">COD Service Charge</span>
                                <span class="font-semibold" id="summaryCodCharge"><?php echo format_currency($codAdjustment); ?></span>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                <span>Total</span>
                                <span id="summaryTotal"><?php echo format_currency($total); ?></span>
                            </div>
                        </div>
                        
                        <!-- Payment Method Logos -->
                        <?php if (!empty($checkoutPaymentIcons)): ?>
                        <div class="flex justify-center items-center flex-wrap gap-1 py-4 border-t">
                            <?php foreach ($checkoutPaymentIcons as $icon): ?>
                                <div class="h-8 flex items-center justify-center" title="<?php echo htmlspecialchars($icon['name'] ?? ''); ?>" style="max-width: 60px;">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <?php echo $icon['svg'] ?? ''; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                         
                        <!-- Payment Method Selection -->
                        <div class="mb-6">
                            <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wider">Payment Method</h3>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-all group <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>" id="payment_online_label">
                                    <input type="radio" name="payment_method" value="credit_card" class="hidden payment-method-radio" <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'checked' : ''; ?> onchange="updatePaymentMethodUI()">
                                    <div class="w-5 h-5 border-2 rounded-full mr-3 flex items-center justify-center <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'border-blue-600 bg-blue-600' : 'border-gray-300'; ?>">
                                        <div class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <div class="flex-1">
                                        <span class="font-semibold <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'text-blue-700' : 'text-gray-700'; ?>">Pay Online</span>
                                        <p class="text-xs text-gray-500">Razorpay, UPI, Cards</p>
                                    </div>
                                    <i class="fas fa-credit-card <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'text-blue-600' : 'text-gray-400'; ?>"></i>
                                </label>

                                <?php if ($isCodEnabled): ?>
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-all group <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>" id="payment_cod_label">
                                    <input type="radio" name="payment_method" value="cash_on_delivery" class="hidden payment-method-radio" <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'checked' : ''; ?> onchange="updatePaymentMethodUI()">
                                    <div class="w-5 h-5 border-2 rounded-full mr-3 flex items-center justify-center <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'border-blue-600 bg-blue-600' : 'border-gray-300'; ?>">
                                        <div class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <div class="flex-1">
                                        <span class="font-semibold <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'text-blue-700' : 'text-gray-700'; ?>">Cash on Delivery (COD)</span>
                                        <?php if ($codChargeValue > 0): ?>
                                            <p class="text-xs text-blue-600 font-medium">+ <?php echo format_currency($codChargeValue); ?> service charge</p>
                                        <?php endif; ?>
                                    </div>
                                    <i class="fas fa-money-bill-wave <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'text-blue-600' : 'text-gray-400'; ?>"></i>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                        

                        <!-- Pay Now Button -->


                        <!-- Pay Now Button -->
                        <?php 
                            $totalQuantity = 0;
                            foreach ($cartItems as $item) {
                                $totalQuantity += ($item['quantity'] ?? 1);
                            }
                        ?>
                        <button type="button" id="razorpayPayButton" 
                                data-order-amount="<?php echo $total; ?>"
                                data-discount-amount="<?php echo $discountAmount; ?>"
                                data-city="<?php echo htmlspecialchars($_POST['city'] ?? ($customer['shipping_address']['city'] ?? '')); ?>"
                                data-total-quantity="<?php echo $totalQuantity; ?>"
                                data-customer-id="<?php echo $customer['customer_id'] ?? ''; ?>"
                                class="w-full bg-blue-600 text-white py-4 px-6 rounded-lg hover:bg-blue-700 transition font-semibold mb-4 checkout-pay-btn <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'hidden' : ''; ?>">
                            Pay Now
                        </button>

                        <button type="submit" id="codPlaceOrderButton" 
                                name="place_order" value="1"
                                class="w-full bg-green-600 text-white py-4 px-6 rounded-lg hover:bg-green-700 transition font-semibold mb-4 checkout-pay-btn <?php echo $selectedPaymentMethod !== 'cash_on_delivery' ? 'hidden' : ''; ?>">
                            Place Order (COD)
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
window.currentTaxAmount = 0;
window.codChargeValue = <?php echo $codChargeValue; ?>;
window.systemCodEnabled = <?php echo $isCodEnabled ? 'true' : 'false'; ?>;

async function recalculateTaxes() {
    const state = document.getElementById('customerStateInput').value.trim();

    // Allow calculation even if state is empty (defaults to Intrastate/Store State)
    // if (!state) return;

    try {
        const response = await fetch('<?php echo $baseUrl; ?>/api/calculate-tax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ state: state })
        });
        const data = await response.json();
        
        if (data.success) {
            window.currentTaxAmount = data.total_tax;
            

            const taxSection = document.getElementById('taxSummarySection');
            const genericTaxRow = document.getElementById('genericTaxRow');
            if (data.total_tax > 0) {
                taxSection.classList.remove('hidden');
                document.getElementById('taxValueTotal').innerText = '₹' + data.total_tax.toFixed(2);
                genericTaxRow.classList.add('hidden');
            } else {
                taxSection.classList.add('hidden');
                genericTaxRow.classList.add('hidden');
            }
            
            updateShipping();
        }
    } catch (e) {
        console.error('Tax calc error:', e);
    }
}

// Initial tax calculation
document.addEventListener('DOMContentLoaded', () => {
    recalculateTaxes();
});



function updateShipping() {
    const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
    const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
    
    const paymentMethodInput = document.querySelector('input[name="payment_method"]:checked');
    const paymentMethod = paymentMethodInput ? paymentMethodInput.value : 'credit_card';
    
    const shipping = deliveryType === 'pickup' ? 0 : window.defaultShipping;
    const codCharge = (paymentMethod === 'cash_on_delivery') ? window.codChargeValue : 0;
    
    // Ensure total doesn't go negative
    const total = Math.max(0, window.cartTotal + shipping + window.currentTaxAmount - window.currentDiscountAmount + codCharge);
    
    // Update DOM
    const shippingEl = document.getElementById('summaryShipping');
    const totalEl = document.getElementById('summaryTotal');
    
    if (shippingEl) shippingEl.innerText = '₹' + shipping.toFixed(2);
    if (totalEl) totalEl.innerText = '₹' + total.toFixed(2);

    // Update COD Charge Row
    const codRow = document.getElementById('codChargeRow');
    const codSummaryVal = document.getElementById('summaryCodCharge');
    if (codCharge > 0) {
        codRow.classList.remove('hidden');
        if (codSummaryVal) codSummaryVal.innerText = '₹' + codCharge.toFixed(2);
    } else {
        codRow.classList.add('hidden');
    }

    // Update Pay Button Attributes for Tracking
    const payBtn = document.getElementById('razorpayPayButton');
    const cityInput = document.querySelector('input[name="city"]');
    if (payBtn) {
        payBtn.setAttribute('data-order-amount', total.toFixed(2));
        payBtn.setAttribute('data-discount-amount', window.currentDiscountAmount.toFixed(2));
        if (cityInput) {
             payBtn.setAttribute('data-city', cityInput.value);
        }
    }
}

function enablePaymentButtons(enable) {
    const payBtn = document.getElementById('razorpayPayButton');
    const codBtn = document.getElementById('codPlaceOrderButton');
    [payBtn, codBtn].forEach(btn => {
        if (!btn) return;
        btn.disabled = !enable;
        if (enable) {
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    });
}

function updatePaymentMethodUI() {
    const radios = document.querySelectorAll('.payment-method-radio');
    radios.forEach(radio => {
        const labelId = radio.value === 'cash_on_delivery' ? 'payment_cod_label' : 'payment_online_label';
        const label = document.getElementById(labelId);
        const icon = label.querySelector('i');
        const text = label.querySelector('span');
        const circle = label.querySelector('.rounded-full');

        if (radio.checked) {
            label.classList.add('border-blue-500', 'bg-blue-50');
            label.classList.remove('border-gray-200');
            icon.classList.add('text-blue-600');
            icon.classList.remove('text-gray-400');
            text.classList.add('text-blue-700');
            text.classList.remove('text-gray-700');
            circle.classList.add('border-blue-600', 'bg-blue-600');
            circle.classList.remove('border-gray-300');
            
            // Toggle Buttons
            if (radio.value === 'cash_on_delivery') {
                document.getElementById('razorpayPayButton').classList.add('hidden');
                document.getElementById('codPlaceOrderButton').classList.remove('hidden');
            } else {
                document.getElementById('razorpayPayButton').classList.remove('hidden');
                document.getElementById('codPlaceOrderButton').classList.add('hidden');
            }
        } else {
            label.classList.remove('border-blue-500', 'bg-blue-50');
            label.classList.add('border-gray-200');
            icon.classList.remove('text-blue-600');
            icon.classList.add('text-gray-400');
            text.classList.remove('text-blue-700');
            text.classList.add('text-gray-700');
            circle.classList.remove('border-blue-600', 'bg-blue-600');
            circle.classList.add('border-gray-300');
        }
    });

    updateShipping();
}


// Listen for input changes to update tracking attributes immediately
document.addEventListener('DOMContentLoaded', function() {
    // Pincode Serviceability Check
    const zipInput = document.getElementById('zipInput');
    const cityInput = document.getElementById('cityInput');
    const stateInput = document.getElementById('customerStateInput');
    const zipStatus = document.getElementById('zipStatus');
    const payBtn = document.getElementById('razorpayPayButton');

    if (zipInput) {
        zipInput.addEventListener('input', async function() {
            const pincode = this.value.trim().replace(/\s/g, '');
            if (pincode.length === 6 && /^[0-9]+$/.test(pincode)) {
                zipStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking serviceability...';
                zipStatus.className = 'mt-2 text-xs text-blue-600';
                
                try {
                    const response = await fetch('<?php echo $baseUrl; ?>/api/pincode_serviceability.php?pincode=' + pincode);
                    const data = await response.json();
                    
                    if (data.success && data.is_serviceable) {
                        
                        // Update Shipping if calculated
                        if (data.shipping_cost !== undefined) {
                            window.defaultShipping = parseFloat(data.shipping_cost);
                            updateShipping();
                        }

                        // Handle COD Row Visibility
                        const codLabel = document.getElementById('payment_cod_label');
                        if (codLabel) {
                            // If system COD is enabled, we keep the option visible
                            // to avoid confusing the merchant/customer if the courier API 
                            // returns false due to test mode or specific account limits.
                            // Priority 1: System-wide COD toggle from API response
                            // Priority 2: Initial system toggle from page load (fallback)
                            const isCodAllowedBySystem = data.system_cod_enabled !== undefined ? !!data.system_cod_enabled : window.systemCodEnabled;
                            
                            if (isCodAllowedBySystem) {
                                codLabel.classList.remove('hidden');
                            } else {
                                codLabel.classList.add('hidden');
                                // If COD was selected but now disabled, switch to online
                                const codRadio = codLabel.querySelector('input');
                                if (codRadio && codRadio.checked) {
                                    const onlineRadio = document.querySelector('input[name="payment_method"][value="credit_card"]');
                                    if (onlineRadio) {
                                        onlineRadio.checked = true;
                                        updatePaymentMethodUI();
                                    }
                                }
                            }
                        }

                        zipStatus.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Delivery available to ' + data.city;
                        zipStatus.className = 'mt-2 text-xs text-green-600';
                        
                        // Auto-fill City and State
                        if (cityInput) {
                            cityInput.value = data.city;
                            // Trigger input event for tracking attributes
                            cityInput.dispatchEvent(new Event('input'));
                        }
                        if (stateInput) {
                            stateInput.value = data.state;
                            // Trigger recalculate taxes if state changed
                            recalculateTaxes();
                        }
                        
                        enablePaymentButtons(true);
                    } else {
                        const errorMsg = data.message || 'Sorry, we do not deliver to this pincode yet.';
                        zipStatus.innerHTML = '<i class="fas fa-times-circle mr-1"></i> ' + errorMsg;
                        zipStatus.className = 'mt-2 text-xs text-red-600';
                        
                        // Only disable if user is asking for delivery
                        const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
                        const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
                        if (deliveryType === 'delivery') {
                            enablePaymentButtons(false);
                        }
                    }
                } catch (error) {
                    zipStatus.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error checking serviceability';
                    zipStatus.className = 'mt-2 text-xs text-orange-600';
                }
            } else {
                zipStatus.innerHTML = '';
            }
        });
        
        // Ensure buttons are re-enabled if user switches to pickup after a bad pincode
        document.querySelectorAll('input[name="delivery_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'pickup') {
                    enablePaymentButtons(true);
                } else {
                    // Re-trigger zip check logic if switching back to delivery
                    zipInput.dispatchEvent(new Event('input'));
                }
            });
        });
    }

    // City Update logic already exists below, but we'll ensure it works with our new cityInput ID
    if (cityInput && payBtn) {
        cityInput.addEventListener('input', function() {
            payBtn.setAttribute('data-city', this.value);
        });
    }

    // Discount Code Update
    const discountInput = document.getElementById('discountInput');
    const applyBtn = document.getElementById('btnApplyDiscount');
    if (discountInput && applyBtn) {
        discountInput.addEventListener('input', function() {
            applyBtn.setAttribute('data-coupon-code', this.value);
        });
    }
});


    

    


// Error/Success Message Functions
let errorMessageTimeout = null;
let successMessageTimeout = null;

function setBtnLoading(btn, isLoading) {
    if (isLoading) {
        // Save original text if not already saved
        if (!btn.dataset.originalText) {
            btn.dataset.originalText = btn.innerHTML;
        }
        // "Louder" button state: Pulsing, distinct text, spinner
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i>Processing Order...';
        btn.disabled = true;
        btn.classList.add('opacity-90', 'cursor-not-allowed', 'animate-pulse');
        
    } else {
        // Restore original text
        if (btn.dataset.originalText) {
            btn.innerHTML = btn.dataset.originalText;
        }
        btn.disabled = false;
        btn.classList.remove('opacity-90', 'cursor-not-allowed', 'animate-pulse');
    }
}

function showErrorMessage(message) {
    const container = document.getElementById('errorMessageContainer');
    const text = document.getElementById('errorMessageText');
    if (container && text) {
        // Clear any existing timeout for auto-hide
        if (errorMessageTimeout) {
            clearTimeout(errorMessageTimeout);
            errorMessageTimeout = null;
        }
        
        // Clear any pending hide animation
        if (container._hideTimeout) {
            clearTimeout(container._hideTimeout);
            container._hideTimeout = null;
        }
        
        text.textContent = message;
        container.classList.remove('hidden');
        
        // Force reflow to enable transition
        void container.offsetWidth;
        
        // Animate in
        container.classList.remove('opacity-0', '-translate-y-4');
        
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
        // Animate out
        container.classList.add('opacity-0', '-translate-y-4');
        
        // Clear any pending hide animation
        if (container._hideTimeout) clearTimeout(container._hideTimeout);

        // Wait for transition before setting display:none
        container._hideTimeout = setTimeout(() => {
            container.classList.add('hidden');
        }, 500); // 500ms matches duration-500

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
    const finalTotal = Math.max(0, window.cartTotal + finalShipping + window.currentTaxAmount - window.currentDiscountAmount);
    
    // Disable button to prevent double clicks
    const button = this;
    setBtnLoading(button, true);
    
    try {
        // Create Razorpay order
        const requestData = {
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: phoneCode + ' ' + customerPhone,
            state: state,
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

<script>
// Handle form submission for COD button loading
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const codBtn = document.getElementById('codPlaceOrderButton');
    // Only show loading if COD button is visible
    if (codBtn && !codBtn.classList.contains('hidden')) {
        setBtnLoading(codBtn, true);
    }
});
</script>



