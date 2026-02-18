<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Delhivery.php';

$pincode = $_GET['pincode'] ?? '';

if (empty($pincode)) {
    echo json_encode(['success' => false, 'message' => 'Pincode is required']);
    exit;
}

$delhivery = new Delhivery();
$result = $delhivery->checkPincode($pincode);

if ($result['success'] && ($result['is_serviceable'] ?? false)) {
    // Calculate Shipping Cost
    $settings = new Settings();
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
