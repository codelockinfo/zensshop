<?php
/**
 * Delhivery Real-time Webhook Receiver
 * Configure this URL in Delhivery One -> Settings -> API -> Webhooks:
 * https://homeprox.in/api/delhivery_webhook.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

$rawPayload = file_get_contents('php://input');
if (empty($rawPayload)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

$data = json_decode($rawPayload, true);
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Log incoming webhook for debugging
@file_put_contents(__DIR__ . '/../tmp/delhivery_webhook.log', date('Y-m-d H:i:s') . " - " . $rawPayload . "\n\n", FILE_APPEND);

function mapWebhookStatus($status, $statusType = '') {
    $s = strtolower(trim((string)$status));
    $st = strtoupper(trim((string)$statusType));

    if (strpos($s, 'delivered') !== false || $st === 'DL') {
        return 'delivered';
    }
    if (strpos($s, 'in transit') !== false || strpos($s, 'dispatched') !== false || strpos($s, 'out for delivery') !== false || strpos($s, 'reached') !== false || strpos($s, 'arrived') !== false) {
        return 'shipped';
    }
    if (strpos($s, 'rto') !== false || strpos($s, 'returned') !== false || $st === 'RT') {
        return 'returned';
    }
    if (strpos($s, 'cancel') !== false || strpos($s, 'cnl') !== false || $st === 'CN') {
        return 'cancelled';
    }
    return null;
}

// Support multiple events or single event
$events = isset($data[0]) ? $data : [$data];
$db = Database::getInstance();
$updatedCount = 0;

foreach ($events as $event) {
    $awb = $event['AWB'] ?? $event['waybill'] ?? $event['Shipment']['AWB'] ?? null;
    $status = $event['Status'] ?? $event['status'] ?? $event['Shipment']['Status']['Status'] ?? null;
    $statusType = $event['StatusType'] ?? $event['status_type'] ?? $event['Shipment']['Status']['StatusType'] ?? '';

    if (!$awb || !$status) continue;

    $order = $db->fetchOne("SELECT id, order_number, order_status, payment_method, payment_status FROM orders WHERE tracking_number = ? LIMIT 1", [$awb]);
    if (!$order) continue;

    $newStatus = mapWebhookStatus($status, $statusType);
    if ($newStatus && $newStatus !== $order['order_status']) {
        if ($newStatus === 'delivered') {
            $db->execute(
                "UPDATE orders 
                 SET order_status = 'delivered', 
                     payment_status = CASE WHEN payment_method = 'cash_on_delivery' THEN 'paid' ELSE payment_status END, 
                     updated_at = NOW() 
                 WHERE id = ?", 
                [$order['id']]
            );
        } else {
            $db->execute("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $order['id']]);
        }
        $updatedCount++;
    } elseif (($order['order_status'] === 'delivered' || $newStatus === 'delivered') && ($order['payment_method'] ?? '') === 'cash_on_delivery' && strtolower((string)($order['payment_status'] ?? '')) !== 'paid') {
        $db->execute("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id = ?", [$order['id']]);
        $updatedCount++;
    }
}

// Return 200 OK fast (<500ms requirement by Delhivery)
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'updated' => $updatedCount,
    'message' => 'Webhook processed successfully'
]);
