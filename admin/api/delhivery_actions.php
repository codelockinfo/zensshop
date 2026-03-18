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

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_REQUEST['action'] ?? $data['action'] ?? '';
$orderId = $_REQUEST['order_id'] ?? $data['order_id'] ?? '';
$orderNumber = $_REQUEST['order_number'] ?? $data['order_number'] ?? '';

if (!$action || (!$orderId && !$orderNumber)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') die("Missing parameters");
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

        case 'download_label':
            $waybill = $orderData['tracking_number'];
            if (!$waybill) {
                if ($_SERVER['REQUEST_METHOD'] === 'GET') die("No tracking number");
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'No tracking number found']);
                exit;
            }

            // Proxy the PDF through our server to handle headers correctly
            $result = $delhivery->generateLabel($waybill);
            
            // Check if we got a valid PDF response (starts with %PDF)
            $isPdf = isset($result['raw_response']) && strpos($result['raw_response'], '%PDF') !== false;

            if ($result['http_code'] === 200 && $isPdf) {
                // Completely clear ALL output buffers to prevent corruption
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="Label_'.$waybill.'.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                echo $result['raw_response'];
                exit;
            } else {
                // LOG RAW RESPONSE FOR DEBUGGING
                error_log("Delhivery Label Download Failed for $waybill");
                error_log("HTTP Code: " . $result['http_code']);
                error_log("Raw Response: " . ($result['raw_response'] ?? 'EMPTY'));
                
                // If we are in the browser (GET), show a clean error message
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    while (ob_get_level()) ob_end_clean();
                    header('Content-Type: text/html');
                    
                    // Fallback to searching the whole result for a body if raw_response is missing
                    $bodyContent = $result['raw_response'] ?? $result['message'] ?? $result['remarks'][0] ?? "No response body";
                    $rawDump = htmlspecialchars(substr($bodyContent, 0, 1000));
                    $errorDetails = $isPdf ? "" : " (Response was not a valid PDF)";
                    
                    die("<div style='font-family:sans-serif;padding:20px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;'>
                        <h3 style='margin-top:0'>Label Download Failed</h3>
                        <p>Could not fetch the label for <b>$waybill</b> from Delhivery.</p>
                        <p style='font-size:13px'>HTTP Status: {$result['http_code']}$errorDetails</p>
                        <div style='background:#fff;padding:10px;border:1px solid #ccc;font-family:monospace;font-size:12px;overflow:auto;max-height:200px;margin-top:10px;'>
                            <b>API Response:</b><br><br>
                            $rawDump
                        </div>
                        <br>
                        <button onclick='window.close()' style='padding:8px 16px;cursor:pointer'>Close Window</button>
                    </div>");
                }
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Failed to fetch valid label']);
            }
            break;
            
        case 'request_pickup':
            // Can handle single or bulk
            $ids = $data['bulk_ids'] ?? [$numericOrderId];
            $result = $delhivery->autoRequestPickup($ids);
            
            if ($result['success']) {
                echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Pickup requested successfully', 'debug' => $delhivery->lastRequest]);
            } else {
                echo json_encode(['success' => false, 'status' => 'error', 'message' => $result['message'] ?? 'Pickup request failed', 'debug' => $delhivery->lastRequest]);
            }
            break;

        case 'get_shipping_charge':
            $shippingAddr = json_decode($orderData['shipping_address'] ?? '[]', true);
            $destPin = $shippingAddr['pincode'] ?? $shippingAddr['zip'] ?? $shippingAddr['postal_code'] ?? '';
            
            // Get Warehouse Pincode
            $sellerJson = $settings->get('seller_address_data', '{}', $storeId);
            $sellerData = json_decode($sellerJson, true) ?: [];
            $sourcePin = $sellerData['pincode'] ?? '';

            if (!$sourcePin || !$destPin) {
                echo json_encode(['success' => false, 'message' => 'Pincodes missing (Source: '.$sourcePin.', Dest: '.$destPin.')']);
                exit;
            }

            $params = [
                'ss' => $sourcePin,
                'ds' => $destPin,
                'wt' => ($orderData['total_weight'] > 0) ? ($orderData['total_weight'] * 1000) : 500, // Convert KG to Grams
                'md' => 'Surface',
                'pt' => (strpos(strtolower($orderData['payment_method'] ?? ''), 'cod') !== false) ? 'COD' : 'Prepaid'
            ];

            $result = $delhivery->calculateShippingCost($params);
            if (isset($result[0]['total_amount']) || isset($result['total_amount'])) {
                 echo json_encode(['success' => true, 'data' => $result]);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Calculation failed', 'raw' => $result]);
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

