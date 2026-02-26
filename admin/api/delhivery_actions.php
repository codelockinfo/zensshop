<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Delhivery.php';
require_once __DIR__ . '/../../classes/Settings.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$orderId = $data['order_id'] ?? '';
$orderNumber = $data['order_number'] ?? '';

if (!$action || (!$orderId && !$orderNumber)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$orderObj = new Order();
$settings = new Settings();
$delhivery = new Delhivery();

$storeId = $_SESSION['store_id'] ?? null;

if ($orderNumber) {
    $orderData = $orderObj->getByOrderNumber($orderNumber, $storeId);
} else {
    $orderData = $orderObj->getById($orderId, $storeId);
}

if (!$orderData) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$numericOrderId = $orderData['id'];

switch ($action) {
    case 'create_shipment':
        $result = $delhivery->autoCreateShipment($numericOrderId);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true, 
                'message' => 'Shipment created successfully', 
                'waybill' => $result['waybill'],
                'debug' => $delhivery->lastRequest // Show request in network tab
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => $result['message'],
                'debug' => $delhivery->lastRequest
            ]);
        }
        break;

    case 'cancel_shipment':
        $waybill = $orderData['tracking_number'];
        if (!$waybill) {
            echo json_encode(['success' => false, 'message' => 'No tracking number found for this order']);
            exit;
        }

        $result = $delhivery->cancel($waybill);
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Shipment cancelled successfully', 'debug' => $delhivery->lastRequest]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to cancel shipment', 'debug' => $delhivery->lastRequest]);
        }
        break;

    case 'track_shipment':
        $waybill = $orderData['tracking_number'];
        if (!$waybill) {
            echo json_encode(['success' => false, 'message' => 'No tracking number found']);
            exit;
        }

        $result = $delhivery->track($waybill);
        if ($result['success']) {
            echo json_encode(['success' => true, 'data' => $result, 'debug' => $delhivery->lastRequest]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Tracking failed', 'debug' => $delhivery->lastRequest]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
