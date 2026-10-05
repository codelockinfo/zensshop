<?php
/**
 * Automated Delhivery Tracking Sync Cron
 * Can be run via cPanel / Hostinger Scheduled Cron Job (e.g. every 15-30 mins)
 * or triggered via HTTP: https://homeprox.in/cron/sync_delhivery.php?key=YOUR_CRON_KEY
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Delhivery.php';
require_once __DIR__ . '/../classes/Settings.php';

$isCli = (php_sapi_name() === 'cli');
$settings = new Settings();
$cronKey = $settings->get('cron_secret_key', 'zens_sync_secret_2026');

// Validate key if called via HTTP
if (!$isCli) {
    $providedKey = $_GET['key'] ?? $_POST['key'] ?? '';
    // Allow if valid secret key OR if admin user is logged in
    $isAdmin = false;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['admin_user'])) {
        $isAdmin = true;
    }

    if (!$isAdmin && $providedKey !== $cronKey) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized cron access. Provide valid ?key= parameter.']);
        exit;
    }
}

function mapStatusToOrder($status, $statusType = '') {
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

$db = Database::getInstance();
$delhivery = new Delhivery();

// Auto-fix any COD orders already marked delivered but payment_status still pending
$autoPaidCount = $db->execute(
    "UPDATE orders 
     SET payment_status = 'paid', updated_at = NOW() 
     WHERE order_status = 'delivered' 
       AND payment_method = 'cash_on_delivery' 
       AND payment_status != 'paid'"
);

// Find all active shipments not yet delivered or cancelled
$activeOrders = $db->fetchAll(
    "SELECT id, order_number, tracking_number, order_status, payment_method, payment_status, store_id 
     FROM orders 
     WHERE tracking_number IS NOT NULL 
       AND tracking_number != ''
       AND (order_status IS NULL OR order_status NOT IN ('delivered', 'cancelled')) 
     ORDER BY id DESC LIMIT 50"
);

$updated = 0;
$logs = [];

foreach ($activeOrders as $ord) {
    $wb = trim((string)$ord['tracking_number']);
    if (empty($wb)) continue;

    try {
        $storeDelhivery = new Delhivery(null, $ord['store_id'] ?? null);
        $trackResult = $storeDelhivery->track($wb);

        if (!empty($trackResult['ShipmentData'][0]['Shipment']['Status']['Status'])) {
            $shipment = $trackResult['ShipmentData'][0]['Shipment'];
            $dStatus = $shipment['Status']['Status'];
            $dStatusType = $shipment['Status']['StatusType'] ?? '';
            $mapped = mapStatusToOrder($dStatus, $dStatusType);

            if ($mapped && $mapped !== $ord['order_status']) {
                if ($mapped === 'delivered') {
                    $db->execute(
                        "UPDATE orders 
                         SET order_status = 'delivered', 
                             payment_status = CASE WHEN payment_method = 'cash_on_delivery' THEN 'paid' ELSE payment_status END, 
                             updated_at = NOW() 
                         WHERE id = ?", 
                        [$ord['id']]
                    );
                    $logs[] = "Order #{$ord['order_number']} (AWB: $wb): {$ord['order_status']} -> DELIVERED (COD Payment marked Paid)";
                } else {
                    $db->execute("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?", [$mapped, $ord['id']]);
                    $logs[] = "Order #{$ord['order_number']} (AWB: $wb): {$ord['order_status']} -> $mapped";
                }
                $updated++;
            }
        }
    } catch (Exception $e) {
        $logs[] = "Error syncing #{$ord['order_number']}: " . $e->getMessage();
    }

    usleep(100000); // 100ms pause to respect API rate limits
}

echo json_encode([
    'success' => true,
    'total_checked' => count($activeOrders),
    'total_updated' => $updated,
    'timestamp' => date('Y-m-d H:i:s'),
    'updates' => $logs
], JSON_PRETTY_PRINT);
