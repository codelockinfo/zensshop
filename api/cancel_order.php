<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../classes/Order.php';

header('Content-Type: application/json');

$auth = new CustomerAuth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentCustomer = $auth->getCurrentCustomer();
$userId = $currentCustomer['customer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderNumber = $input['order_number'] ?? '';
$reason = $input['reason'] ?? '';
$comments = $input['comments'] ?? '';

if (empty($orderNumber) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Order number and reason are required']);
    exit;
}

$db = Database::getInstance();
$orderModel = new Order();

// Verify order ownership and status
$order = $orderModel->getByOrderNumber($orderNumber);

if (!$order || $order['user_id'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
    exit;
}

// Determine request type: 'cancel' or 'refund'
$type = $input['type'] ?? 'cancel';

// Validation Logic
if ($type === 'refund') {
    // Refund Validation
    if ($order['order_status'] !== 'delivered') {
        echo json_encode(['success' => false, 'message' => 'Only delivered orders can be refunded']);
        exit;
    }
    
    // Check 7-day window
    $deliveryDate = !empty($order['delivery_date']) ? strtotime($order['delivery_date']) : null;
    if (!$deliveryDate) {
        // If delivery date not recorded, fallback to updated_at or prevent? 
        // Let's assume updated_at is close enough if delivery_date missing, or prevent.
        // Better: if delivery_date is missing, we can't verify 7 days.
        // But for user friendliness, let's allow if created_at is reasonable or just pass for now if strictly delivered.
        // User request: "after 7 days from the order deliver date complee 7 das then remove the button"
        // This implies front-end hides it, but back-end must enforce.
        if (!empty($order['updated_at'])) {
             $deliveryDate = strtotime($order['updated_at']); // Fallback
        }
    }
    
    if ($deliveryDate) {
        $daysSinceDelivery = (time() - $deliveryDate) / (60 * 60 * 24);
        if ($daysSinceDelivery > 7) {
            echo json_encode(['success' => false, 'message' => 'Refund period (7 days) has expired']);
            exit;
        }
    }
    
} else {
    // Cancel Validation
    $allowableStatuses = ['pending', 'processing', 'on_hold'];
    if (!in_array($order['order_status'], $allowableStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled in its current state']);
        exit;
    }
}

// 1. Insert into ordercancel table
try {
    $db->beginTransaction();
    
    // Prepare snapshot data
    $items = $orderModel->getOrderItems($orderNumber);
    
    // Check if already requested (block only if pending or approved)
    $existingRequest = $db->fetchOne("SELECT id FROM ordercancel WHERE order_id = ? AND type = ? AND (cancel_status = 'pending' OR cancel_status = 'approved')", [$order['id'], $type]);
    if ($existingRequest) {
        echo json_encode(['success' => false, 'message' => 'A ' . $type . ' request is already in progress or has been approved']);
        exit;
    }

    $insertSql = "INSERT INTO ordercancel (
        order_id, order_number, customer_id, store_id, type, previous_status,
        cancel_reason, cancel_comment, cancel_status,
        customer_name, customer_email, customer_phone, shipping_address,
        payment_method, payment_status, total_amount, tracking_number,
        items_snapshot
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, 'pending',
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?
    )";
    
    $storeId = $order['store_id'] ?? null;
    $trackingNum = $order['tracking_number'] ?? null;
    
    $db->execute($insertSql, [
        $order['id'],
        $order['order_number'],
        $userId,
        $storeId,
        $type,
        $order['order_status'], // previous_status
        $reason,
        $comments,
        $order['customer_name'],
        $order['customer_email'],
        $order['customer_phone'],
        $order['shipping_address'], 
        $order['payment_method'],
        $order['payment_status'],
        $order['total_amount'],
        $trackingNum,
        json_encode($items)
    ]);
    
    // 2. Update order status ONLY for cancellations
    if ($type === 'cancel') {
        $db->execute("UPDATE orders SET order_status = 'cancelled' WHERE id = ?", [$order['id']]);
        $successMsg = 'Order cancelled successfully';
    } else {
        // For refund, we might update status to 'return_requested' if you have that status, 
        // or just leave as delivered and let admin handle the request.
        // Let's notify success.
        $successMsg = 'Refund request submitted successfully';
    }
    
    $db->commit();
    
    // 3. Send Notifications and Emails
    try {
        require_once __DIR__ . '/../classes/Notification.php';
        require_once __DIR__ . '/../classes/Email.php';
        require_once __DIR__ . '/../classes/Settings.php';
        
        $notifier = new Notification();
        $mailer = new Email($storeId);
        
        // Load config for SITE_NAME, etc. (passing storeId)
        Settings::loadEmailConfig($storeId);
        
        // Admin Notification
        if ($type === 'refund') {
            $notifier->notifyRefundRequest($orderNumber, $order['customer_name']);
        } else {
            $notifier->notifyCancellationRequest($orderNumber, $order['customer_name']);
        }
        
        // Emails
        // 1. To Merchant (Admin)
        // Fetch the merchant's actual account email for this store
        $merchant = $db->fetchOne("SELECT email FROM users WHERE store_id = ? AND role = 'admin' LIMIT 1", [$storeId]);
        $adminEmail = $merchant['email'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : null);
        
        if ($adminEmail) {
            $mailer->sendOrderRequestNotification($adminEmail, $type, $orderNumber, $order['customer_name'], $reason, $comments, true);
        }
        
        // To Customer
        if (!empty($order['customer_email'])) {
            $mailer->sendOrderRequestNotification($order['customer_email'], $type, $orderNumber, $order['customer_name'], $reason, $comments, false);
        }
        
    } catch (Exception $ne) {
        error_log("Notification Error: " . $ne->getMessage());
        // Don't fail the request if notifications fail
    }
    
    echo json_encode(['success' => true, 'message' => $successMsg]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Cancellation/Refund Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process request: ' . $e->getMessage()]);
}
