<?php
/**
 * Orders API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';

$auth = new Auth();
$auth->requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$order = new Order();

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $item = $order->getById($id);
                echo json_encode(['success' => true, 'order' => $item]);
            } else {
                $filters = [
                    'order_status' => $_GET['status'] ?? null,
                    'payment_status' => $_GET['payment'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                $orders = $order->getAll($filters);
                echo json_encode(['success' => true, 'orders' => $orders]);
            }
            break;
            
        case 'PUT':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Order ID is required');
            }
            
            if (isset($input['order_status'])) {
                $order->updateStatus($id, $input['order_status']);
            }
            if (isset($input['payment_status'])) {
                $order->updatePaymentStatus($id, $input['payment_status']);
            }
            if (isset($input['tracking_number'])) {
                $order->updateTracking($id, $input['tracking_number']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
            break;
            
        case 'DELETE':
            // Orders should not be deleted, only cancelled
            throw new Exception('Orders cannot be deleted. Please cancel instead.');
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

