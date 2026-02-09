<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Email.php';
require_once __DIR__ . '/../../classes/Settings.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestId = $input['requestId'] ?? null;
$status = $input['status'] ?? null; // approved, rejected
$type = $input['type'] ?? ''; // cancel, refund

if (!$requestId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // 1. Fetch request details
    $request = $db->fetchOne("SELECT * FROM ordercancel WHERE id = ?", [$requestId]);
    if (!$request) {
        throw new Exception("Request not found");
    }

    // 2. Update request status
    $db->execute("UPDATE ordercancel SET cancel_status = ? WHERE id = ?", [$status, $requestId]);

    // 3. If approved, update order status. If rejected, revert status if it was a cancellation.
    if ($status === 'approved') {
        $newOrderStatus = ($request['type'] === 'refund' || $type === 'refund') ? 'returned' : 'cancelled';
        $db->execute("UPDATE orders SET order_status = ? WHERE id = ?", [$newOrderStatus, $request['order_id']]);
    } elseif ($status === 'rejected') {
        // If cancellation was rejected, revert to previous status
        if ($request['type'] === 'cancel' && !empty($request['previous_status'])) {
            $db->execute("UPDATE orders SET order_status = ? WHERE id = ?", [$request['previous_status'], $request['order_id']]);
        }
    }

    $db->commit();

    // 4. Send email notification to customer
    try {
        Settings::loadEmailConfig($request['store_id']);
        $mailer = new Email($request['store_id']);
        $mailer->sendOrderRequestStatusUpdate(
            $request['customer_email'],
            $request['type'],
            $request['order_number'],
            $request['customer_name'],
            $status
        );
    } catch (Exception $ee) {
        error_log("Email Error in status update: " . $ee->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
