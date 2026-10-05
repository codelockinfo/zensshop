<?php
/**
 * Customer Order Tracking Redirect
 * Seamlessly forwards customers directly to Delhivery Official Tracking Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';

$awbParam = trim($_GET['awb'] ?? $_GET['waybill'] ?? $_GET['tracking'] ?? $_GET['order'] ?? '');

if (!empty($awbParam)) {
    $db = Database::getInstance();
    // Resolve tracking number if an order_number was passed
    $order = $db->fetchOne("SELECT tracking_number FROM orders WHERE tracking_number = ? OR order_number = ? LIMIT 1", [$awbParam, $awbParam]);
    $waybill = (!empty($order['tracking_number'])) ? $order['tracking_number'] : $awbParam;

    header("Location: https://www.delhivery.com/track/package/" . urlencode($waybill));
    exit;
}

// Fallback if no tracking number provided
$baseUrl = getBaseUrl();
header("Location: " . $baseUrl . "/account?section=orders");
exit;
