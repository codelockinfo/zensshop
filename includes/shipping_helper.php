<?php
// Function to calculate shipping cost via Delhivery
function getDelhiveryShippingCost($pincode, $paymentMode = 'Prepaid') {
    $settings = new Settings();
    $token = $settings->get('delhivery_api_token', '');
    $sourcePincode = $settings->get('delhivery_source_pincode', '');
    
    // Only proceed if configured
    if (empty($token) || empty($sourcePincode)) {
        return 0.00; // Default fallback
    }

    require_once __DIR__ . '/../classes/Delhivery.php';
    $delhivery = new Delhivery();
    
    // Check serviceability first
    $serviceCheck = $delhivery->checkPincode($pincode);
    if (!$serviceCheck['success'] || !($serviceCheck['is_serviceable'] ?? false)) {
        throw new Exception("Delivery not available to pincode: " . htmlspecialchars($pincode));
    }
    
    // Calculate cost
    $params = [
        'ss' => $sourcePincode,
        'ds' => $pincode,
        'wt' => '500', // Default 500g
        'md' => 'S',   // Surface
        'pt' => ($paymentMode === 'cash_on_delivery' || $paymentMode === 'cod') ? 'COD' : 'Prepaid'
    ];
    
    $costResult = $delhivery->calculateShippingCost($params);
    
    if (isset($costResult['total_amount']) && $costResult['total_amount'] > 0) {
        return floatval($costResult['total_amount']);
    }
    
    return 0.00; // Fallback if API fails to return cost
}
?>
