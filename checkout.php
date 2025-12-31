<?php
/**
 * Checkout Page
 */

// Start output buffering to prevent headers already sent errors
ob_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Cart.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Auth.php';

$baseUrl = getBaseUrl();
$cart = new Cart();
$order = new Order();
$auth = new Auth();

// Get cart items
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();

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
if (isset($_POST['apply_discount'])) {
    $discountCode = trim($_POST['discount_code'] ?? '');
    // Simple discount logic - you can enhance this
    if (strtoupper($discountCode) === 'SAVE10') {
        $discountAmount = 10.00;
    }
}

// Process order if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        // Validate required fields
        $required = ['customer_name', 'customer_email', 'phone', 'country'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }
        
        // Get user ID if logged in
        $userId = null;
        if ($auth->isLoggedIn()) {
            $currentUser = $auth->getCurrentUser();
            $userId = $currentUser['id'] ?? null;
        }
        
        // Combine phone code and phone number
        $phoneCode = $_POST['phone_code'] ?? '+1';
        $phoneNumber = trim($_POST['phone'] ?? '');
        $fullPhone = $phoneCode . ' ' . $phoneNumber;
        
        // Prepare order data
        $orderData = [
            'user_id' => $userId,
            'customer_name' => trim($_POST['customer_name']),
            'customer_email' => trim($_POST['customer_email']),
            'customer_phone' => $fullPhone,
            'billing_address' => [
                'street' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'zip' => trim($_POST['zip'] ?? ''),
                'country' => trim($_POST['country_name'] ?? $_POST['country'] ?? 'India')
            ],
            'shipping_address' => [
                'street' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'zip' => trim($_POST['zip'] ?? ''),
                'country' => trim($_POST['country_name'] ?? $_POST['country'] ?? 'India')
            ],
            'items' => [],
            'discount_amount' => $discountAmount,
            'shipping_amount' => $_POST['delivery_type'] === 'pickup' ? 0 : $shippingAmount,
            'tax_amount' => 0,
            'payment_method' => $_POST['payment_method'] ?? 'cash_on_delivery'
        ];
        
        // Prepare order items from cart
        foreach ($cartItems as $item) {
            $orderData['items'][] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ];
        }
        
        // Create order
        $orderId = $order->create($orderData);
        
        // Clear cart
        $cart->clear();
        
        // Clear output buffer before redirect
        ob_end_clean();
        
        // Redirect to thank you page
        header('Location: ' . url("order-success?id={$orderId}"));
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

<section class="py-8 md:py-12 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <!-- Progress Indicator -->
        <div class="flex justify-center items-center mb-8">
            <div class="flex items-center space-x-4">
                <!-- Cart Step -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold">
                        <i class="fas fa-check text-sm"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700">Cart</span>
                </div>
                <div class="w-12 h-0.5 bg-primary"></div>
                
                <!-- Review Step -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold">
                        <i class="fas fa-check text-sm"></i>
                    </div>
                    <span class="ml-2 font-semibold text-gray-700">Review</span>
                </div>
                <div class="w-12 h-0.5 bg-primary"></div>
                
                <!-- Checkout Step -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                        3
                    </div>
                    <span class="ml-2 font-semibold text-blue-600">Checkout</span>
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
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 max-w-6xl mx-auto">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo url('checkout'); ?>" class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Section: Shipping Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg p-6 md:p-8">
                        <h1 class="text-3xl font-bold mb-2">Checkout</h1>
                        <h2 class="text-xl font-semibold text-gray-700 mb-6">Shipping Information</h2>
                        
                        <!-- Delivery Options -->
                        <div class="flex gap-4 mb-8">
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
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Email address</label>
                                <input type="email" name="customer_email" required 
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-700">Phone number</label>
                                <div class="flex relative">
                                    <select name="phone_code" id="phoneCodeSelect" class="px-3 py-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary bg-gray-50 appearance-none cursor-pointer" style="min-width: 120px;">
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
                                           class="flex-1 px-4 py-3 border border-gray-300 border-l-0 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
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
                                    <div class="relative country-dropdown-wrapper">
                                        <!-- Hidden inputs for form submission -->
                                        <input type="hidden" name="country" id="countryInput" value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>" required>
                                        <?php
                                        // Get country name from POST or lookup from code
                                        $selectedCountryCode = $_POST['country'] ?? '';
                                        $selectedCountryName = $_POST['country_name'] ?? '';
                                        if ($selectedCountryCode && !$selectedCountryName) {
                                            $countryLookup = [
                                                'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
                                                'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
                                                'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
                                                'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
                                                'PL' => 'Poland', 'CZ' => 'Czech Republic', 'IE' => 'Ireland', 'PT' => 'Portugal',
                                                'GR' => 'Greece', 'RO' => 'Romania', 'HU' => 'Hungary', 'BG' => 'Bulgaria',
                                                'HR' => 'Croatia', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'LT' => 'Lithuania',
                                                'LV' => 'Latvia', 'EE' => 'Estonia', 'LU' => 'Luxembourg', 'MT' => 'Malta',
                                                'CY' => 'Cyprus', 'IS' => 'Iceland', 'LI' => 'Liechtenstein', 'MC' => 'Monaco',
                                                'AD' => 'Andorra', 'SM' => 'San Marino', 'VA' => 'Vatican City',
                                                'JP' => 'Japan', 'CN' => 'China', 'KR' => 'South Korea', 'IN' => 'India',
                                                'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'PH' => 'Philippines',
                                                'ID' => 'Indonesia', 'VN' => 'Vietnam', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
                                                'NZ' => 'New Zealand', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'AE' => 'United Arab Emirates',
                                                'SA' => 'Saudi Arabia', 'IL' => 'Israel', 'TR' => 'Turkey', 'RU' => 'Russia',
                                                'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile',
                                                'CO' => 'Colombia', 'PE' => 'Peru', 'VE' => 'Venezuela', 'EC' => 'Ecuador',
                                                'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BO' => 'Bolivia', 'CR' => 'Costa Rica',
                                                'PA' => 'Panama', 'GT' => 'Guatemala', 'DO' => 'Dominican Republic', 'CU' => 'Cuba',
                                                'JM' => 'Jamaica', 'TT' => 'Trinidad and Tobago', 'BB' => 'Barbados', 'BS' => 'Bahamas',
                                                'NG' => 'Nigeria', 'KE' => 'Kenya', 'GH' => 'Ghana', 'TZ' => 'Tanzania',
                                                'ET' => 'Ethiopia', 'UG' => 'Uganda', 'MA' => 'Morocco', 'TN' => 'Tunisia',
                                                'DZ' => 'Algeria', 'LY' => 'Libya', 'SD' => 'Sudan', 'AO' => 'Angola',
                                                'MZ' => 'Mozambique', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe', 'BW' => 'Botswana',
                                                'NA' => 'Namibia', 'MU' => 'Mauritius', 'SC' => 'Seychelles', 'MV' => 'Maldives',
                                                'BD' => 'Bangladesh', 'PK' => 'Pakistan', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
                                                'BT' => 'Bhutan', 'MM' => 'Myanmar', 'KH' => 'Cambodia', 'LA' => 'Laos',
                                                'BN' => 'Brunei', 'FJ' => 'Fiji', 'PG' => 'Papua New Guinea', 'NC' => 'New Caledonia',
                                                'PF' => 'French Polynesia', 'GU' => 'Guam', 'AS' => 'American Samoa',
                                            ];
                                            $selectedCountryName = $countryLookup[$selectedCountryCode] ?? $selectedCountryCode;
                                        }
                                        ?>
                                        <input type="hidden" name="country_name" id="countryNameInput" value="<?php echo htmlspecialchars($selectedCountryName); ?>">
                                        
                                        <!-- Custom dropdown button -->
                                        <button type="button" id="countryDropdownBtn" 
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white text-left flex items-center justify-between">
                                            <span id="countryDisplayText" class="text-gray-700">
                                                <?php
                                                $selectedCountry = $_POST['country'] ?? '';
                                                if ($selectedCountry) {
                                                    $countries = [
                                                        'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
                                                        'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
                                                        'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
                                                        'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
                                                        'PL' => 'Poland', 'CZ' => 'Czech Republic', 'IE' => 'Ireland', 'PT' => 'Portugal',
                                                        'GR' => 'Greece', 'RO' => 'Romania', 'HU' => 'Hungary', 'BG' => 'Bulgaria',
                                                        'HR' => 'Croatia', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'LT' => 'Lithuania',
                                                        'LV' => 'Latvia', 'EE' => 'Estonia', 'LU' => 'Luxembourg', 'MT' => 'Malta',
                                                        'CY' => 'Cyprus', 'IS' => 'Iceland', 'LI' => 'Liechtenstein', 'MC' => 'Monaco',
                                                        'AD' => 'Andorra', 'SM' => 'San Marino', 'VA' => 'Vatican City',
                                                        'JP' => 'Japan', 'CN' => 'China', 'KR' => 'South Korea', 'IN' => 'India',
                                                        'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'PH' => 'Philippines',
                                                        'ID' => 'Indonesia', 'VN' => 'Vietnam', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
                                                        'NZ' => 'New Zealand', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'AE' => 'United Arab Emirates',
                                                        'SA' => 'Saudi Arabia', 'IL' => 'Israel', 'TR' => 'Turkey', 'RU' => 'Russia',
                                                        'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile',
                                                        'CO' => 'Colombia', 'PE' => 'Peru', 'VE' => 'Venezuela', 'EC' => 'Ecuador',
                                                        'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BO' => 'Bolivia', 'CR' => 'Costa Rica',
                                                        'PA' => 'Panama', 'GT' => 'Guatemala', 'DO' => 'Dominican Republic', 'CU' => 'Cuba',
                                                        'JM' => 'Jamaica', 'TT' => 'Trinidad and Tobago', 'BB' => 'Barbados', 'BS' => 'Bahamas',
                                                        'NG' => 'Nigeria', 'KE' => 'Kenya', 'GH' => 'Ghana', 'TZ' => 'Tanzania',
                                                        'ET' => 'Ethiopia', 'UG' => 'Uganda', 'MA' => 'Morocco', 'TN' => 'Tunisia',
                                                        'DZ' => 'Algeria', 'LY' => 'Libya', 'SD' => 'Sudan', 'AO' => 'Angola',
                                                        'MZ' => 'Mozambique', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe', 'BW' => 'Botswana',
                                                        'NA' => 'Namibia', 'MU' => 'Mauritius', 'SC' => 'Seychelles', 'MV' => 'Maldives',
                                                        'BD' => 'Bangladesh', 'PK' => 'Pakistan', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
                                                        'BT' => 'Bhutan', 'MM' => 'Myanmar', 'KH' => 'Cambodia', 'LA' => 'Laos',
                                                        'BN' => 'Brunei', 'FJ' => 'Fiji', 'PG' => 'Papua New Guinea', 'NC' => 'New Caledonia',
                                                        'PF' => 'French Polynesia', 'GU' => 'Guam', 'AS' => 'American Samoa',
                                                    ];
                                                    $flags = [
                                                        'US' => '🇺🇸', 'GB' => '🇬🇧', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
                                                        'DE' => '🇩🇪', 'FR' => '🇫🇷', 'IT' => '🇮🇹', 'ES' => '🇪🇸',
                                                        'NL' => '🇳🇱', 'BE' => '🇧🇪', 'CH' => '🇨🇭', 'AT' => '🇦🇹',
                                                        'SE' => '🇸🇪', 'NO' => '🇳🇴', 'DK' => '🇩🇰', 'FI' => '🇫🇮',
                                                        'PL' => '🇵🇱', 'CZ' => '🇨🇿', 'IE' => '🇮🇪', 'PT' => '🇵🇹',
                                                        'GR' => '🇬🇷', 'RO' => '🇷🇴', 'HU' => '🇭🇺', 'BG' => '🇧🇬',
                                                        'HR' => '🇭🇷', 'SK' => '🇸🇰', 'SI' => '🇸🇮', 'LT' => '🇱🇹',
                                                        'LV' => '🇱🇻', 'EE' => '🇪🇪', 'LU' => '🇱🇺', 'MT' => '🇲🇹',
                                                        'CY' => '🇨🇾', 'IS' => '🇮🇸', 'LI' => '🇱🇮', 'MC' => '🇲🇨',
                                                        'AD' => '🇦🇩', 'SM' => '🇸🇲', 'VA' => '🇻🇦',
                                                        'JP' => '🇯🇵', 'CN' => '🇨🇳', 'KR' => '🇰🇷', 'IN' => '🇮🇳',
                                                        'SG' => '🇸🇬', 'MY' => '🇲🇾', 'TH' => '🇹🇭', 'PH' => '🇵🇭',
                                                        'ID' => '🇮🇩', 'VN' => '🇻🇳', 'HK' => '🇭🇰', 'TW' => '🇹🇼',
                                                        'NZ' => '🇳🇿', 'ZA' => '🇿🇦', 'EG' => '🇪🇬', 'AE' => '🇦🇪',
                                                        'SA' => '🇸🇦', 'IL' => '🇮🇱', 'TR' => '🇹🇷', 'RU' => '🇷🇺',
                                                        'BR' => '🇧🇷', 'MX' => '🇲🇽', 'AR' => '🇦🇷', 'CL' => '🇨🇱',
                                                        'CO' => '🇨🇴', 'PE' => '🇵🇪', 'VE' => '🇻🇪', 'EC' => '🇪🇨',
                                                        'UY' => '🇺🇾', 'PY' => '🇵🇾', 'BO' => '🇧🇴', 'CR' => '🇨🇷',
                                                        'PA' => '🇵🇦', 'GT' => '🇬🇹', 'DO' => '🇩🇴', 'CU' => '🇨🇺',
                                                        'JM' => '🇯🇲', 'TT' => '🇹🇹', 'BB' => '🇧🇧', 'BS' => '🇧🇸',
                                                        'NG' => '🇳🇬', 'KE' => '🇰🇪', 'GH' => '🇬🇭', 'TZ' => '🇹🇿',
                                                        'ET' => '🇪🇹', 'UG' => '🇺🇬', 'MA' => '🇲🇦', 'TN' => '🇹🇳',
                                                        'DZ' => '🇩🇿', 'LY' => '🇱🇾', 'SD' => '🇸🇩', 'AO' => '🇦🇴',
                                                        'MZ' => '🇲🇿', 'ZM' => '🇿🇲', 'ZW' => '🇿🇼', 'BW' => '🇧🇼',
                                                        'NA' => '🇳🇦', 'MU' => '🇲🇺', 'SC' => '🇸🇨', 'MV' => '🇲🇻',
                                                        'BD' => '🇧🇩', 'PK' => '🇵🇰', 'LK' => '🇱🇰', 'NP' => '🇳🇵',
                                                        'BT' => '🇧🇹', 'MM' => '🇲🇲', 'KH' => '🇰🇭', 'LA' => '🇱🇦',
                                                        'BN' => '🇧🇳', 'FJ' => '🇫🇯', 'PG' => '🇵🇬', 'NC' => '🇳🇨',
                                                        'PF' => '🇵🇫', 'GU' => '🇬🇺', 'AS' => '🇦🇸',
                                                    ];
                                                    $flag = $flags[$selectedCountry] ?? '🌍';
                                                    $name = $countries[$selectedCountry] ?? '';
                                                    echo $flag . ' ' . $name;
                                                } else {
                                                    echo 'Choose country';
                                                }
                                                ?>
                                            </span>
                                            <i class="fas fa-chevron-down text-gray-400"></i>
                                        </button>
                                        
                                        <!-- Dropdown menu -->
                                        <div id="countryDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-80 overflow-hidden">
                                            <!-- Search input -->
                                            <div class="p-3 border-b border-gray-200">
                                                <input type="text" id="countrySearch" placeholder="Search country..." 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                                            </div>
                                            
                                            <!-- Country list -->
                                            <div id="countryList" class="overflow-y-auto max-h-64">
                                                <?php
                                                $countries = [
                                                    'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
                                                    'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
                                                    'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
                                                    'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
                                                    'PL' => 'Poland', 'CZ' => 'Czech Republic', 'IE' => 'Ireland', 'PT' => 'Portugal',
                                                    'GR' => 'Greece', 'RO' => 'Romania', 'HU' => 'Hungary', 'BG' => 'Bulgaria',
                                                    'HR' => 'Croatia', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'LT' => 'Lithuania',
                                                    'LV' => 'Latvia', 'EE' => 'Estonia', 'LU' => 'Luxembourg', 'MT' => 'Malta',
                                                    'CY' => 'Cyprus', 'IS' => 'Iceland', 'LI' => 'Liechtenstein', 'MC' => 'Monaco',
                                                    'AD' => 'Andorra', 'SM' => 'San Marino', 'VA' => 'Vatican City',
                                                    'JP' => 'Japan', 'CN' => 'China', 'KR' => 'South Korea', 'IN' => 'India',
                                                    'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'PH' => 'Philippines',
                                                    'ID' => 'Indonesia', 'VN' => 'Vietnam', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
                                                    'NZ' => 'New Zealand', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'AE' => 'United Arab Emirates',
                                                    'SA' => 'Saudi Arabia', 'IL' => 'Israel', 'TR' => 'Turkey', 'RU' => 'Russia',
                                                    'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile',
                                                    'CO' => 'Colombia', 'PE' => 'Peru', 'VE' => 'Venezuela', 'EC' => 'Ecuador',
                                                    'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BO' => 'Bolivia', 'CR' => 'Costa Rica',
                                                    'PA' => 'Panama', 'GT' => 'Guatemala', 'DO' => 'Dominican Republic', 'CU' => 'Cuba',
                                                    'JM' => 'Jamaica', 'TT' => 'Trinidad and Tobago', 'BB' => 'Barbados', 'BS' => 'Bahamas',
                                                    'NG' => 'Nigeria', 'KE' => 'Kenya', 'GH' => 'Ghana', 'TZ' => 'Tanzania',
                                                    'ET' => 'Ethiopia', 'UG' => 'Uganda', 'MA' => 'Morocco', 'TN' => 'Tunisia',
                                                    'DZ' => 'Algeria', 'LY' => 'Libya', 'SD' => 'Sudan', 'AO' => 'Angola',
                                                    'MZ' => 'Mozambique', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe', 'BW' => 'Botswana',
                                                    'NA' => 'Namibia', 'MU' => 'Mauritius', 'SC' => 'Seychelles', 'MV' => 'Maldives',
                                                    'BD' => 'Bangladesh', 'PK' => 'Pakistan', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
                                                    'BT' => 'Bhutan', 'MM' => 'Myanmar', 'KH' => 'Cambodia', 'LA' => 'Laos',
                                                    'BN' => 'Brunei', 'FJ' => 'Fiji', 'PG' => 'Papua New Guinea', 'NC' => 'New Caledonia',
                                                    'PF' => 'French Polynesia', 'GU' => 'Guam', 'AS' => 'American Samoa',
                                                ];
                                                
                                                $flags = [
                                                    'US' => '🇺🇸', 'GB' => '🇬🇧', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
                                                    'DE' => '🇩🇪', 'FR' => '🇫🇷', 'IT' => '🇮🇹', 'ES' => '🇪🇸',
                                                    'NL' => '🇳🇱', 'BE' => '🇧🇪', 'CH' => '🇨🇭', 'AT' => '🇦🇹',
                                                    'SE' => '🇸🇪', 'NO' => '🇳🇴', 'DK' => '🇩🇰', 'FI' => '🇫🇮',
                                                    'PL' => '🇵🇱', 'CZ' => '🇨🇿', 'IE' => '🇮🇪', 'PT' => '🇵🇹',
                                                    'GR' => '🇬🇷', 'RO' => '🇷🇴', 'HU' => '🇭🇺', 'BG' => '🇧🇬',
                                                    'HR' => '🇭🇷', 'SK' => '🇸🇰', 'SI' => '🇸🇮', 'LT' => '🇱🇹',
                                                    'LV' => '🇱🇻', 'EE' => '🇪🇪', 'LU' => '🇱🇺', 'MT' => '🇲🇹',
                                                    'CY' => '🇨🇾', 'IS' => '🇮🇸', 'LI' => '🇱🇮', 'MC' => '🇲🇨',
                                                    'AD' => '🇦🇩', 'SM' => '🇸🇲', 'VA' => '🇻🇦',
                                                    'JP' => '🇯🇵', 'CN' => '🇨🇳', 'KR' => '🇰🇷', 'IN' => '🇮🇳',
                                                    'SG' => '🇸🇬', 'MY' => '🇲🇾', 'TH' => '🇹🇭', 'PH' => '🇵🇭',
                                                    'ID' => '🇮🇩', 'VN' => '🇻🇳', 'HK' => '🇭🇰', 'TW' => '🇹🇼',
                                                    'NZ' => '🇳🇿', 'ZA' => '🇿🇦', 'EG' => '🇪🇬', 'AE' => '🇦🇪',
                                                    'SA' => '🇸🇦', 'IL' => '🇮🇱', 'TR' => '🇹🇷', 'RU' => '🇷🇺',
                                                    'BR' => '🇧🇷', 'MX' => '🇲🇽', 'AR' => '🇦🇷', 'CL' => '🇨🇱',
                                                    'CO' => '🇨🇴', 'PE' => '🇵🇪', 'VE' => '🇻🇪', 'EC' => '🇪🇨',
                                                    'UY' => '🇺🇾', 'PY' => '🇵🇾', 'BO' => '🇧🇴', 'CR' => '🇨🇷',
                                                    'PA' => '🇵🇦', 'GT' => '🇬🇹', 'DO' => '🇩🇴', 'CU' => '🇨🇺',
                                                    'JM' => '🇯🇲', 'TT' => '🇹🇹', 'BB' => '🇧🇧', 'BS' => '🇧🇸',
                                                    'NG' => '🇳🇬', 'KE' => '🇰🇪', 'GH' => '🇬🇭', 'TZ' => '🇹🇿',
                                                    'ET' => '🇪🇹', 'UG' => '🇺🇬', 'MA' => '🇲🇦', 'TN' => '🇹🇳',
                                                    'DZ' => '🇩🇿', 'LY' => '🇱🇾', 'SD' => '🇸🇩', 'AO' => '🇦🇴',
                                                    'MZ' => '🇲🇿', 'ZM' => '🇿🇲', 'ZW' => '🇿🇼', 'BW' => '🇧🇼',
                                                    'NA' => '🇳🇦', 'MU' => '🇲🇺', 'SC' => '🇸🇨', 'MV' => '🇲🇻',
                                                    'BD' => '🇧🇩', 'PK' => '🇵🇰', 'LK' => '🇱🇰', 'NP' => '🇳🇵',
                                                    'BT' => '🇧🇹', 'MM' => '🇲🇲', 'KH' => '🇰🇭', 'LA' => '🇱🇦',
                                                    'BN' => '🇧🇳', 'FJ' => '🇫🇯', 'PG' => '🇵🇬', 'NC' => '🇳🇨',
                                                    'PF' => '🇵🇫', 'GU' => '🇬🇺', 'AS' => '🇦🇸',
                                                ];
                                                
                                                foreach ($countries as $code => $name) {
                                                    $flag = $flags[$code] ?? '🌍';
                                                    echo "<div class=\"country-option px-4 py-2 hover:bg-gray-100 cursor-pointer flex items-center space-x-2\" data-code=\"{$code}\" data-name=\"{$name}\" data-flag=\"{$flag}\">";
                                                    echo "<span>{$flag}</span>";
                                                    echo "<span>{$name}</span>";
                                                    echo "</div>\n";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
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
                                <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/80'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="w-16 h-16 object-cover rounded">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-xs text-gray-500"><?php echo $item['quantity']; ?>x</p>
                                </div>
                                <p class="font-bold text-gray-800"><?php echo format_currency($item['price'] * $item['quantity']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Discount Code -->
                        <div class="mb-6">
                            <form method="POST" class="flex gap-2">
                                <input type="text" name="discount_code" 
                                       value="<?php echo htmlspecialchars($discountCode); ?>"
                                       placeholder="Discount code" 
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="submit" name="apply_discount" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-semibold">
                                    Apply
                                </button>
                            </form>
                            <?php if ($discountAmount > 0): ?>
                            <p class="text-sm text-green-600 mt-2">Discount applied!</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="border-t pt-4 space-y-2 mb-6">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-semibold"><?php echo format_currency($subtotal); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Shipping</span>
                                <span class="font-semibold"><?php echo format_currency($finalShipping); ?></span>
                            </div>
                            <?php if ($discountAmount > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Discount</span>
                                <span class="font-semibold text-green-600">-<?php echo format_currency($discountAmount); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                <span>Total</span>
                                <span><?php echo format_currency($total); ?></span>
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

function updateShipping() {
    // This function can be used to update shipping costs dynamically
    // For now, it's handled server-side
}

// Country dropdown functionality
(function() {
    const dropdownBtn = document.getElementById('countryDropdownBtn');
    const dropdown = document.getElementById('countryDropdown');
    const countryInput = document.getElementById('countryInput');
    const countryDisplayText = document.getElementById('countryDisplayText');
    const countrySearch = document.getElementById('countrySearch');
    const countryList = document.getElementById('countryList');
    const countryOptions = countryList.querySelectorAll('.country-option');
    
    // Toggle dropdown
    if (dropdownBtn && dropdown) {
        dropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) {
                countrySearch.focus();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }
    
    // Search functionality
    if (countrySearch && countryOptions.length > 0) {
        countrySearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            
            countryOptions.forEach(option => {
                const name = option.getAttribute('data-name').toLowerCase();
                const code = option.getAttribute('data-code').toLowerCase();
                
                if (name.includes(searchTerm) || code.includes(searchTerm)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
    
    // Select country
    const countryNameInput = document.getElementById('countryNameInput');
    countryOptions.forEach(option => {
        option.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            const name = this.getAttribute('data-name');
            const flag = this.getAttribute('data-flag');
            
            // Update hidden inputs
            countryInput.value = code;
            if (countryNameInput) {
                countryNameInput.value = name;
            }
            
            // Update display text
            countryDisplayText.textContent = flag + ' ' + name;
            
            // Close dropdown
            dropdown.classList.add('hidden');
            
            // Clear search
            if (countrySearch) {
                countrySearch.value = '';
                // Show all options again
                countryOptions.forEach(opt => {
                    opt.style.display = 'flex';
                });
            }
        });
    });
    
    // Keyboard navigation
    if (countrySearch) {
        let selectedIndex = -1;
        
        countrySearch.addEventListener('keydown', function(e) {
            const visibleOptions = Array.from(countryOptions).filter(opt => 
                opt.style.display !== 'none'
            );
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, visibleOptions.length - 1);
                visibleOptions[selectedIndex]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                if (selectedIndex >= 0) {
                    visibleOptions[selectedIndex]?.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                visibleOptions[selectedIndex]?.click();
            } else {
                selectedIndex = -1;
            }
        });
    }
})();

// Error/Success Message Functions
let errorMessageTimeout = null;
let successMessageTimeout = null;

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
const razorpayCartTotal = <?php echo $cartTotal; ?>;
const razorpayShippingAmount = <?php echo $shippingAmount; ?>;
const razorpayDiscountAmount = <?php echo $discountAmount; ?>;
const razorpayTotal = razorpayCartTotal + razorpayShippingAmount - razorpayDiscountAmount;

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
    const countryCode = document.querySelector('input[name="country"]').value.trim();
    const countryName = document.querySelector('input[name="country_name"]').value.trim();
    const country = countryName || countryCode; // Use full name if available, otherwise use code
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    
    // Hide any previous error messages
    hideErrorMessage();
    
    if (!customerName || !customerEmail || !customerPhone || !address || !city || !state || !zip || !countryCode) {
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
    
    // Calculate final shipping and total based on delivery type
    const finalShipping = deliveryType === 'pickup' ? 0 : razorpayShippingAmount;
    const finalTotal = razorpayCartTotal + finalShipping - razorpayDiscountAmount;
    
    console.log('[RAZORPAY] Starting payment process');
    console.log('[RAZORPAY] Cart total:', razorpayCartTotal);
    console.log('[RAZORPAY] Shipping:', finalShipping);
    console.log('[RAZORPAY] Discount:', razorpayDiscountAmount);
    console.log('[RAZORPAY] Final total:', finalTotal);
    console.log('[RAZORPAY] Delivery type:', deliveryType);
    
    // Disable button to prevent double clicks
    const button = this;
    button.disabled = true;
    button.textContent = 'Processing...';
    
    try {
        // Create Razorpay order
        const requestData = {
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: phoneCode + ' ' + customerPhone,
            amount: finalTotal,
            shipping_amount: finalShipping,
            discount_amount: razorpayDiscountAmount
        };
        
        console.log('[RAZORPAY] Sending order request:', requestData);
        
        const orderResponse = await fetch(razorpayBaseUrl + '/api/razorpay/create-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });
        
        const orderData = await orderResponse.json();
        
        if (!orderData.success) {
            console.error('[RAZORPAY] Failed to create order:', orderData);
            console.error('[RAZORPAY] Error message:', orderData.message);
            if (orderData.debug) {
                console.error('[RAZORPAY] Debug info:', orderData.debug);
            }
            const errorMsg = orderData.message || 'Failed to create payment order';
            showErrorMessage(errorMsg);
            button.disabled = false;
            button.textContent = 'Pay Now';
            return;
        }
        
        console.log('[RAZORPAY] Order created successfully:', orderData);
        
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
            discount_amount: razorpayDiscountAmount,
            shipping_amount: deliveryType === 'pickup' ? 0 : razorpayShippingAmount,
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
                        console.log('[RAZORPAY] Payment verified successfully:', verifyData);
                        console.log('[RAZORPAY] Order ID:', verifyData.order_id);
                        
                        // Ensure order_id exists
                        if (!verifyData.order_id) {
                            console.error('[RAZORPAY] No order_id in response:', verifyData);
                            throw new Error('Order ID not received from server');
                        }
                        
                        // Redirect to success page with order ID
                        // Use clean URL format (without .php) to avoid .htaccess redirect issues
                        const successUrl = razorpayBaseUrl + '/order-success?id=' + encodeURIComponent(verifyData.order_id);
                        console.log('[RAZORPAY] Redirecting to:', successUrl);
                        console.log('[RAZORPAY] Order ID being passed:', verifyData.order_id);
                        console.log('[RAZORPAY] Full verify response:', verifyData);
                        // Use window.location.replace to prevent back button issues
                        window.location.replace(successUrl);
                    } else {
                        console.error('[RAZORPAY] Payment verification failed:', verifyData);
                        console.error('[RAZORPAY] Error message:', verifyData.message);
                        showErrorMessage('Payment verification failed: ' + (verifyData.message || 'Unknown error'));
                        button.disabled = false;
                        button.textContent = 'Pay Now';
                    }
                } catch (error) {
                    console.error('[RAZORPAY] Payment verification error:', error);
                    showErrorMessage('An error occurred while verifying payment. Please contact support.');
                    button.disabled = false;
                    button.textContent = 'Pay Now';
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
                    button.disabled = false;
                    button.textContent = 'Pay Now';
                }
            }
        };
        
        const razorpay = new Razorpay(options);
        razorpay.open();
        
    } catch (error) {
        console.error('[RAZORPAY] Payment error:', error);
        console.error('[RAZORPAY] Error message:', error.message);
        console.error('[RAZORPAY] Error stack:', error.stack);
        showErrorMessage('An error occurred: ' + (error.message || 'Unknown error. Please try again.'));
        button.disabled = false;
        button.textContent = 'Pay Now';
    }
});
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


