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

// Clear ANY previous output (like database connection messages or warnings)
if (ob_get_level() > 0) ob_clean();

try {
    switch ($action) {
        case 'create_shipment':
            $result = $delhivery->autoCreateShipment($numericOrderId);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true, 
                    'status' => 'success',
                    'message' => 'Shipment created successfully', 
                    'waybill' => $result['waybill'],
                    'debug' => $delhivery->lastRequest
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'status' => 'error',
                    'message' => $result['message'],
                    'debug' => $delhivery->lastRequest
                ]);
            }
            break;

        case 'cancel_shipment':
            $waybill = $orderData['tracking_number'];
            if (!$waybill) {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'No tracking number found for this order']);
                exit;
            }

            $result = $delhivery->cancel($waybill);
            if ($result['success']) {
                // Update order: clear tracking number and move status back to pending
                $db = Database::getInstance();
                $db->execute("UPDATE orders SET tracking_number = NULL, order_status = 'pending' WHERE id = ?", [$numericOrderId]);

                echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Shipment cancelled successfully and order status updated.', 'debug' => $delhivery->lastRequest]);
            } else {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => $result['message'] ?? 'Failed to cancel shipment', 'debug' => $delhivery->lastRequest]);
            }
            break;

        case 'track_shipment':
            $waybill = $orderData['tracking_number'];
            if (!$waybill) {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'No tracking number found']);
                exit;
            }

            $result = $delhivery->track($waybill);
            if ($result['success']) {
                echo json_encode([
                    'success' => true, 
                    'status' => 'success',
                    'data' => $result, 
                    'debug' => $delhivery->lastRequest
                ]);
            } else {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => $result['message'] ?? 'Tracking failed', 'debug' => $delhivery->lastRequest]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'System Error: ' . $e->getMessage()
    ]);
}

