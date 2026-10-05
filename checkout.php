<?php
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
if (!$auth->isLoggedIn()) {
    ob_end_clean();
    header('Location: ' . url('login?redirect=checkout'));
    exit;
}
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();

$customer = null;
if ($auth->isLoggedIn()) {
    $customer = $auth->getCurrentCustomer();
    if (!isset($_SESSION['store_id']) && $customer && isset($customer['store_id'])) {
        $_SESSION['store_id'] = $customer['store_id'];
    }
}

require_once __DIR__ . '/classes/Settings.php';
$settingsManager = new Settings();
$checkoutPaymentIconsJson = $settingsManager->get('checkout_payment_icons_json', '[]');
$checkoutPaymentIcons = json_decode($checkoutPaymentIconsJson, true) ?: [];

if (empty($cartItems)) {
    ob_end_clean();
    header('Location: ' . url('cart'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['checkout_tracked_' . md5(json_encode($cartItems))])) {
    require_once __DIR__ . '/classes/Notification.php';
    $cartCurrency = !empty($cartItems) ? ($cartItems[0]['currency'] ?? 'INR') : 'INR';
    $notification = new Notification();
    $notification->create('cart', '💳 Checkout Started', "User has proceeded to checkout.\nCart Total: " . format_price($cartTotal, $cartCurrency) . "\nItems: " . count($cartItems));
    $_SESSION['checkout_tracked_' . md5(json_encode($cartItems))] = true;
}

$error = '';
$success = false;
$orderId = null;
$shippingAmount = 0.00; 
$discountAmount = 0;
$discountCode = '';
$discountError = ''; 
if (isset($_POST['remove_discount'])) {
    unset($_SESSION['checkout_discount_code']);
    $discountCode = '';
    $discountAmount = 0;
} elseif (isset($_POST['apply_discount']) || isset($_POST['place_order']) || isset($_SESSION['checkout_discount_code'])) {
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
            $_SESSION['checkout_discount_code'] = $codeToValidate;
            $discountCode = $codeToValidate;
        } catch (Exception $e) {
            if (isset($_POST['apply_discount'])) {
                $discountError = $e->getMessage();
            }
            if (isset($_SESSION['checkout_discount_code']) && !isset($_POST['apply_discount'])) {
                unset($_SESSION['checkout_discount_code']);
            }
            $discountCode = ''; 
            $discountAmount = 0;
        }
    }
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (isset($_SESSION['last_checkout_attempt']) && (time() - $_SESSION['last_checkout_attempt'] < 5)) {
    $error = "Please wait a moment before trying again.";
}
$_SESSION['last_checkout_attempt'] = time();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && empty($error)) {
    try {
       
        if (!empty($_POST['hp_website_check'])) {
            throw new Exception("Security check failed.");
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Security check failed. Please refresh the page.");
        }
        $required = ['customer_name', 'customer_email', 'phone', 'country'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }
        if (!filter_var($_POST['customer_email'], FILTER_VALIDATE_EMAIL)) {
             throw new Exception("Invalid email address format.");
        }
        if (!preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/', trim($_POST['phone']))) {
             
             if (strlen(preg_replace('/[^0-9]/', '', $_POST['phone'])) < 7) {
                 throw new Exception("Please enter a valid phone number.");
             }
        }
                $paymentMethod = $_POST['payment_method'] ?? 'cash_on_delivery';
        if ($paymentMethod === 'credit_card' || $paymentMethod === 'razorpay') {
             throw new Exception("Online payments must be processed via the secure payment window. Please click 'Pay Now'.");
        }
        $userId = null;
        if ($auth->isLoggedIn()) {
            $currentUser = $auth->getCurrentCustomer();
            $userId = $currentUser['customer_id'] ?? null;
            if ($userId && $userId < 1000000000) {
                $userId = null;
            }
        }
        $phoneCode = sanitize_input($_POST['phone_code'] ?? '+1');
        $phoneNumber = sanitize_input(trim($_POST['phone'] ?? ''));
        $fullPhone = $phoneCode . ' ' . $phoneNumber;
        if (($_POST['delivery_type'] ?? '') !== 'pickup') {
            require_once __DIR__ . '/includes/shipping_helper.php';
            $shippingAmount = getDelhiveryShippingCost(trim($_POST['zip']), $paymentMethod);
        } else {
            $shippingAmount = 0;
        }
        $codCharge = 0;
        if ($paymentMethod === 'cash_on_delivery') {
            require_once __DIR__ . '/classes/Settings.php';
            $stManager = new Settings();
            $codCharge = (float)$stManager->get('cod_charge', 0);
        }
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
        $orderResponse = $order->create($orderData);
        $orderId = $orderResponse['id'];
        $orderNumber = $orderResponse['order_number'];
        
        try {
            require_once __DIR__ . '/classes/Delhivery.php';
            $delhivery = new Delhivery();
            $res = $delhivery->autoCreateShipment($orderId);
            if (!$res['success']) {
                error_log("Delhivery autoCreateShipment failed for order $orderNumber: " . ($res['message'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            error_log("Failed to auto-create Delhivery shipment for order " . $orderNumber . ": " . $e->getMessage());
        }
        $cart->clear();
        ob_end_clean();
        header('Location: ' . url("order-success.php?order_number={$orderNumber}"));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
ob_end_clean();
session_write_close();

$pageTitle = 'Checkout';
$isCheckout = true;
require_once __DIR__ . '/includes/header.php';
$settingsObj = new Settings();
$logoType = $settingsObj->get('footer_logo_type', 'image');
$logoText = $settingsObj->get('footer_logo_text', 'HomeproX');
$logo = $settingsObj->get('footer_logo_image', null);
$subtotal = $cartTotal;
$finalShipping = isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'pickup' ? 0 : $shippingAmount;
$tax = 0;
$isCodEnabled = (int)$settingsObj->get('enable_cod', 0);
$codChargeValue = (float)$settingsObj->get('cod_charge', 0);
$selectedPaymentMethod = $_POST['payment_method'] ?? 'credit_card'; 
$codAdjustment = ($selectedPaymentMethod === 'cash_on_delivery') ? $codChargeValue : 0;

$total = $subtotal + $finalShipping - $discountAmount + $tax + $codAdjustment;
$checkoutStylingJson = $settingsObj->get('checkout_page_styling', '');
$checkoutStyling = !empty($checkoutStylingJson) ? json_decode($checkoutStylingJson, true) : [];

function getCheckoutStyle($key, $default, $settingsObj, $checkoutStyling) {
    if (isset($checkoutStyling[$key])) return $checkoutStyling[$key];
    return $settingsObj->get($key, $default);
}
?>

<style>
.bg-black.text-white.text-sm.py-2,
nav.bg-white.sticky.top-0 {
    display: none !important;
}
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

<section class="pt-2 pb-6 md:pt-4 md:pb-10 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto mb-4 md:mb-4 flex flex-col md:flex-row items-center justify-start gap-3 md:gap-12">
        
            <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                <?php if ($logoType == 'image' && !empty($logo)): ?>
                    <img src="<?php echo getImageUrl($logo); ?>" alt="<?php echo htmlspecialchars($logoText); ?>" class="h-[60px] w-auto object-contain">
                <?php else: ?>
                    <span class="text-2xl md:text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($logoText); ?></span>
                <?php endif; ?>
            </a>
            <div class="flex items-center space-x-2 md:space-x-4 overflow-x-auto min-w-max">
                
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-xs md:text-sm">
                        <i class="fas fa-check"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700 text-xs md:text-base">Cart</span>
                </div>
                <div class="w-6 md:w-12 h-0.5 bg-primary"></div>
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-xs md:text-sm">
                        <i class="fas fa-check"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700 text-xs md:text-base">Review</span>
                </div>
                <div class="w-6 md:w-12 h-0.5 bg-primary"></div>
                <div class="flex items-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold text-xs md:text-sm checkout-step-active">
                        3
                    </div>
                    <span class="ml-2 font-semibold text-blue-600 text-xs md:text-base">Checkout</span>
                </div>
            </div>
        </div>
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
            setTimeout(function() {
                const errorMsg = document.getElementById('php-error-msg');
                if (errorMsg) {
                    errorMsg.style.opacity = '0';
                    setTimeout(() => errorMsg.style.display = 'none', 500);
                }
            }, 5000);

            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        </script>
        <?php endif; ?>

        <form id="checkoutForm" method="POST" action="<?php echo url('checkout'); ?>" class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg p-4 sm:p-6 md:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <a href="<?php echo url('cart'); ?>" class="text-gray-400 hover:text-black transition-colors" title="Back to Cart">
                                    <i class="fas fa-chevron-left text-xl"></i>
                                </a>
                                <h1 class="text-xl md:text-2xl font-bold text-gray-900 checkout-heading">Checkout</h1>
                            </div>
                        </div>

                        <?php if ($customer): ?>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-4 md:mb-8 flex items-center space-x-3 checkout-welcome">
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
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Full name</label>
                                <input type="text" name="customer_name" required 
                                       pattern="[a-zA-Z\s\.\-]{2,50}"
                                       title="Please enter a valid name (letters only)"
                                       onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32 || event.charCode === 46 || event.charCode === 45"
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($customer['name'] ?? '')); ?>"
                                       placeholder="Enter full name"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Email address</label>
                                <input type="email" name="customer_email" required 
                                       pattern="[a-z0-9\._%+\-]+@[a-z0-9\.\-]+\.[a-z]{2,}$"
                                       title="Please enter a valid email address (e.g., user@example.com)"
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ($customer['email'] ?? '')); ?>"
                                       placeholder="Enter email address"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Phone number</label>
                                <div class="flex relative w-full">
                                    <select name="phone_code" id="phoneCodeSelect" class="px-3 py-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary bg-gray-50 appearance-none cursor-pointer checkout-input" style="min-width: 90px;">
                                        <?php
                                      
                                        $phoneCodes = [
                                            '+91' => ['🇮🇳', '+91', 'IN', 10], 
                                            '+1' => ['🇺🇸', '+1', 'US', 10],   
                                            '+44' => ['��', '+44', 'GB', 10],
                                            '+61' => ['��', '+61', 'AU', 9], 
                                            '+49' => ['��', '+49', 'DE', 11], 
                                            '+33' => ['��', '+33', 'FR', 9],  
                                            '+86' => ['��', '+86', 'CN', 11], 
                                            '+81' => ['��', '+81', 'JP', 10], 
                                            '+971' => ['��', '+971', 'AE', 9], 
                                            '+966' => ['🇸�', '+966', 'SA', 9], 
                                            '+7' => ['��', '+7', 'RU', 10],   
                                            '+55' => ['��', '+55', 'BR', 11], 
                                            '+20' => ['��', '+20', 'EG', 10],
                                            '+27' => ['��', '+27', 'ZA', 9],  
                                            '+90' => ['��', '+90', 'TR', 10], 
                                            '+39' => ['��', '+39', 'IT', 10], 
                                            '+34' => ['🇪🇸', '+34', 'ES', 9], 
                                            '+65' => ['��', '+65', 'SG', 8], 
                                            '+60' => ['��', '+60', 'MY', 9], 
                                            '+62' => ['��', '+62', 'ID', 11], 
                                            '+63' => ['��', '+63', 'PH', 10], 
                                            '+92' => ['��', '+92', 'PK', 10], 
                                            '+880' => ['🇧�', '+880', 'BD', 10],
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
                                        
                                        $selectedCode = $_POST['phone_code'] ?? '+91'; 
                                        if (!array_key_exists($selectedCode, $phoneCodes)) {
                                             
                                        }

                                        foreach ($phoneCodes as $code => $data) {
                                            $display = $code;
                                            $length = $data[3] ?? 10;
                                            $selected = ($selectedCode === $code) ? 'selected' : '';
                                            echo "<option value=\"{$code}\" data-length=\"{$length}\" {$selected}>{$display}</option>\n";
                                        }
                                        ?>
                                    </select>
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
                                            if (phoneInput.value.length > length) {
                                                phoneInput.value = phoneInput.value.slice(0, length);
                                            }
                                        }
                                        phoneSelect.addEventListener('change', updatePhoneValidation);
                                        updatePhoneValidation(); 
                                    });
                                </script>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Address</label>
                                <input type="text" name="address" required minlength="5"
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                       placeholder="Enter address"
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
                                           placeholder="Enter city"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">State</label>
                                    <input type="text" name="state" id="customerStateInput" required
                                           pattern="[a-zA-Z\s]+"
                                           title="Please enter a valid state name (letters only)"
                                           onkeypress="return (event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode <= 122) || event.charCode === 32"
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? $customer['shipping_address']['state'] ?? ''); ?>"
                                           placeholder="Enter state"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent checkout-input"
                                           onblur="recalculateTaxes()">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">ZIP Code</label>
                                    <input type="text" name="zip" id="zipInput" required
                                           placeholder="Enter ZIP code"
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
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg p-6 sticky top-4 checkout-summary">
                        <h2 class="text-xl font-bold mb-6 checkout-heading">Review your cart</h2>
                        
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
                        <div class="mb-6" id="discountSection">
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
                        <div class="border-t pt-4 space-y-2 mb-6">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-semibold"><?php echo format_currency($subtotal); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Shipping</span>
                                <span id="summaryShipping" class="font-semibold"><?php echo format_currency($finalShipping); ?></span>
                            </div>
                            <div id="taxSummarySection" class="hidden space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax</span>
                                    <span class="font-semibold" id="taxValueTotal">₹0</span>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm hidden" id="genericTaxRow">
                                <span class="text-gray-600">Tax</span>
                                <span class="font-semibold" id="genericTaxValue">₹0</span>
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
window.cartTotal = <?php echo $cartTotal; ?>;
window.currentDiscountAmount = <?php echo $discountAmount; ?>;
window.currentDiscountCode = '<?php echo $discountCode; ?>';
window.defaultShipping = <?php echo $shippingAmount; ?>;
window.currentTaxAmount = 0;
window.codChargeValue = <?php echo $codChargeValue; ?>;
window.systemCodEnabled = <?php echo $isCodEnabled ? 'true' : 'false'; ?>;

async function recalculateTaxes() {
    const state = document.getElementById('customerStateInput').value.trim();

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
                document.getElementById('taxValueTotal').innerText = '₹' + (Number.isInteger(data.total_tax) ? data.total_tax.toFixed(0) : data.total_tax.toFixed(2));
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
    
    const total = Math.max(0, window.cartTotal + shipping + window.currentTaxAmount - window.currentDiscountAmount + codCharge);
    
    const shippingEl = document.getElementById('summaryShipping');
    const totalEl = document.getElementById('summaryTotal');
    
    if (shippingEl) shippingEl.innerText = '₹' + (Number.isInteger(shipping) ? shipping.toFixed(0) : shipping.toFixed(2));
    if (totalEl) totalEl.innerText = '₹' + (Number.isInteger(total) ? total.toFixed(0) : total.toFixed(2));

    const codRow = document.getElementById('codChargeRow');
    const codSummaryVal = document.getElementById('summaryCodCharge');
    if (codCharge > 0) {
        codRow.classList.remove('hidden');
        if (codSummaryVal) codSummaryVal.innerText = '₹' + (Number.isInteger(codCharge) ? codCharge.toFixed(0) : codCharge.toFixed(2));
    } else {
        codRow.classList.add('hidden');
    }
    const payBtn = document.getElementById('razorpayPayButton');
    const cityInput = document.querySelector('input[name="city"]');
    if (payBtn) {
        payBtn.setAttribute('data-order-amount', Number.isInteger(total) ? total.toFixed(0) : total.toFixed(2));
        payBtn.setAttribute('data-discount-amount', Number.isInteger(window.currentDiscountAmount) ? window.currentDiscountAmount.toFixed(0) : window.currentDiscountAmount.toFixed(2));
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

document.addEventListener('DOMContentLoaded', function() {
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
                    if (!response.ok) {
                        if (response.status === 403) {
                            zipStatus.innerHTML = '<i class="fas fa-lock mr-1"></i> <a href="<?php echo $baseUrl; ?>/login" class="underline font-bold">Please login</a> to check delivery availability';
                            zipStatus.className = 'mt-2 text-xs text-orange-600';
                            return;
                        }
                        throw new Error('Server error: ' + response.status);
                    }
                    const data = await response.json();
                    
                    if (data.success && data.is_serviceable) {
                        
                        if (data.shipping_cost !== undefined) {
                            window.defaultShipping = parseFloat(data.shipping_cost);
                            updateShipping();
                        }
                        const codLabel = document.getElementById('payment_cod_label');
                        if (codLabel) {
                            const isCodAllowedBySystem = data.system_cod_enabled !== undefined ? !!data.system_cod_enabled : window.systemCodEnabled;
                            
                            if (isCodAllowedBySystem) {
                                codLabel.classList.remove('hidden');
                            } else {
                                codLabel.classList.add('hidden');
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
                        
                        if (cityInput) {
                            cityInput.value = data.city;
                            cityInput.dispatchEvent(new Event('input'));
                        }
                        if (stateInput) {
                            stateInput.value = data.state;
                            recalculateTaxes();
                        }
                        
                        enablePaymentButtons(true);
                    } else {
                        const errorMsg = data.message || 'Sorry, we do not deliver to this pincode yet.';
                        zipStatus.innerHTML = '<i class="fas fa-times-circle mr-1"></i> ' + errorMsg;
                        zipStatus.className = 'mt-2 text-xs text-red-600';
                        
                        const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
                        const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
                        if (deliveryType === 'delivery') {
                            enablePaymentButtons(false);
                        }
                    }
                } catch (error) {
                    console.error('Serviceability check error:', error);
                    zipStatus.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error checking serviceability';
                    zipStatus.className = 'mt-2 text-xs text-orange-600';
                }
            } else {
                zipStatus.innerHTML = '';
            }
        });
        document.querySelectorAll('input[name="delivery_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'pickup') {
                    enablePaymentButtons(true);
                } else {
                    zipInput.dispatchEvent(new Event('input'));
                }
            });
        });
    }

    if (cityInput && payBtn) {
        cityInput.addEventListener('input', function() {
            payBtn.setAttribute('data-city', this.value);
        });
    }
    const discountInput = document.getElementById('discountInput');
    const applyBtn = document.getElementById('btnApplyDiscount');
    if (discountInput && applyBtn) {
        discountInput.addEventListener('input', function() {
            applyBtn.setAttribute('data-coupon-code', this.value);
        });
    }
});
let errorMessageTimeout = null;
let successMessageTimeout = null;

function setBtnLoading(btn, isLoading) {
    if (isLoading) {
        if (!btn.dataset.originalText) {
            btn.dataset.originalText = btn.innerHTML;
        }
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i>Processing Order...';
        btn.disabled = true;
        btn.classList.add('opacity-90', 'cursor-not-allowed', 'animate-pulse');
        
    } else {
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
        if (errorMessageTimeout) {
            clearTimeout(errorMessageTimeout);
            errorMessageTimeout = null;
        }
        if (container._hideTimeout) {
            clearTimeout(container._hideTimeout);
            container._hideTimeout = null;
        }
        
        text.textContent = message;
        container.classList.remove('hidden');
        void container.offsetWidth;
        container.classList.remove('opacity-0', '-translate-y-4');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        errorMessageTimeout = setTimeout(function() {
            hideErrorMessage();
        }, 5000);
    }
}

function hideErrorMessage() {
    const container = document.getElementById('errorMessageContainer');
    if (container) {
        container.classList.add('opacity-0', '-translate-y-4');
        if (container._hideTimeout) clearTimeout(container._hideTimeout);
        container._hideTimeout = setTimeout(() => {
            container.classList.add('hidden');
        }, 500);
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
        if (successMessageTimeout) {
            clearTimeout(successMessageTimeout);
            successMessageTimeout = null;
        }
        
        text.textContent = message;
        container.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        successMessageTimeout = setTimeout(function() {
            hideSuccessMessage();
        }, 5000);
    }
}

function hideSuccessMessage() {
    const container = document.getElementById('successMessageContainer');
    if (container) {
        container.classList.add('hidden');
        if (successMessageTimeout) {
            clearTimeout(successMessageTimeout);
            successMessageTimeout = null;
        }
    }
}
const razorpayBaseUrl = '<?php echo $baseUrl; ?>';
document.getElementById('razorpayPayButton').addEventListener('click', async function(e) {
    e.preventDefault();
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
    hideErrorMessage();
    if (!customerName || !customerEmail || !customerPhone || !address || !city || !state || !zip || !country) {
        console.error('[RAZORPAY] Validation failed: Missing required fields');
        showErrorMessage('Please fill in all required fields');
        return;
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(customerEmail)) {
        console.error('[RAZORPAY] Validation failed: Invalid email address');
        showErrorMessage('Please enter a valid email address');
        return;
    }
    const finalShipping = deliveryType === 'pickup' ? 0 : window.defaultShipping;
    const finalTotal = Math.max(0, window.cartTotal + finalShipping + window.currentTaxAmount - window.currentDiscountAmount);
    
    const button = this;
    setBtnLoading(button, true);
    
    try {
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
        const options = {
            key: orderData.razorpay_key,
            amount: orderData.amount,
            currency: orderData.currency,
            description: 'Order Payment',
            order_id: orderData.order_id,
            handler: async function(response) {
                try {
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
                        if (!verifyData.order_id) {
                            throw new Error('Order ID not received from server');
                        }
                        
                        const successUrl = razorpayBaseUrl + '/order-success?order_number=' + encodeURIComponent(verifyData.order_number);
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
    
    if (action === 'apply' && !input.value.trim()) {
        if(errorMsg) {
            errorMsg.textContent = 'Please enter a discount code';
            errorMsg.classList.remove('hidden');
            discountTimeout = setTimeout(() => {
                errorMsg.classList.add('hidden');
            }, 5000);
        } else {
            showErrorMessage('Please enter a discount code');
        }
        return;
    }
    if (errorMsg) errorMsg.classList.add('hidden');
    
    setBtnLoading(btn, true);
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
            if (action === 'apply') {
                document.getElementById('appliedState').classList.remove('hidden');
                document.getElementById('inputState').classList.add('hidden');
                document.getElementById('appliedCodeText').textContent = res.code;
                document.getElementById('hiddenDiscountCode').value = res.code;
                window.currentDiscountCode = res.code; 
                showSuccessMessage(res.message);
            } else {
                document.getElementById('appliedState').classList.add('hidden');
                document.getElementById('inputState').classList.remove('hidden');
                input.value = '';
                document.getElementById('hiddenDiscountCode').value = '';
                window.currentDiscountCode = '';
                showSuccessMessage('Discount removed');
            }
            const discountRow = document.getElementById('summaryDiscountRow');
            const discountAmountEl = document.getElementById('summaryDiscountAmount');
            
            if (res.discount_amount > 0) {
                if (discountRow) discountRow.classList.remove('hidden');
                if (discountAmountEl) discountAmountEl.textContent = '-₹' + (Number.isInteger(parseFloat(res.discount_amount)) ? parseFloat(res.discount_amount).toFixed(0) : parseFloat(res.discount_amount).toFixed(2));
            } else {
                if (discountRow) discountRow.classList.add('hidden');
            }
            window.currentDiscountAmount = parseFloat(res.discount_amount);
            updateShipping();
            
        } else {
            if (action === 'apply') {
                 if (errorMsg) {
                     errorMsg.textContent = res.message;
                     errorMsg.classList.remove('hidden');
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
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const codBtn = document.getElementById('codPlaceOrderButton');
    if (codBtn && !codBtn.classList.contains('hidden')) {
        setBtnLoading(codBtn, true);
    }
});
</script>



