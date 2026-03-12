<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Delhivery.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pincode = $_GET['pincode'] ?? '';

if (empty($pincode)) {
    echo json_encode(['success' => false, 'message' => 'Pincode is required']);
    exit;
}

// Check if customer OR admin is logged in
$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$isAdminLoggedIn = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);

if (!$isCustomerLoggedIn && !$isAdminLoggedIn) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please login to check serviceability', 'error_type' => 'auth_required']);
    exit;
}

$delhivery = new Delhivery();
$result = $delhivery->checkPincode($pincode);
$result['debug_store_id'] = defined('CURRENT_STORE_ID') ? CURRENT_STORE_ID : 'NOT_DEFINED';
$result['debug_token_loaded'] = !empty($delhivery->getToken());
$result['debug_mode'] = $delhivery->isTest() ? 'test (staging)' : 'live';
$result['debug_base_url'] = $delhivery->getBaseUrl();
$result['debug_url'] = $delhivery->lastRequest['url'] ?? 'N/A';
$result['debug_token_len'] = strlen($delhivery->getToken());
$result['debug_headers'] = $delhivery->lastRequest['headers'] ?? [];
$result['debug_token_preview'] = substr($delhivery->getToken(), 0, 8) . '...';
$result['debug_user_session'] = [
    'customer_logged_in' => $isCustomerLoggedIn,
    'admin_logged_in' => $isAdminLoggedIn,
    'session_id_exists' => !empty(session_id())
];

$settings = new Settings();
$isCodEnabled = (int)$settings->get('enable_cod', 0);
$result['system_cod_enabled'] = $isCodEnabled;

if ((isset($result['success']) && $result['success']) && ($result['is_serviceable'] ?? false)) {
    // We keep the courier's COD status in $result['cod']
    // but the frontend will also check system_cod_enabled
    
    // Calculate Shipping Cost
    $sourcePincode = $settings->get('delhivery_source_pincode', '');
    
    if (!empty($sourcePincode)) {
        // Default weight 500g, Mode Surface
        // Using K-Web Params as they are more standard for new APIs
        $shippingParams = [
            'md' => 'S',
            'ss' => 'Delivered', 
            'd_pin' => $pincode,
            'o_pin' => $sourcePincode,
            'cgm' => '500', 
            'pt' => 'Prepaid'
        ];
        
        $costResult = $delhivery->calculateShippingCost($shippingParams);
        
        if (isset($costResult['total_amount']) && $costResult['total_amount'] > 0) {
            $result['shipping_cost'] = floatval($costResult['total_amount']);
        }
    }
}


echo json_encode($result);
