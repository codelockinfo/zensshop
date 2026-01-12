<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

$orderNumber = 'ORD-20260112-F6CF94';
echo "Searching for order $orderNumber...\n";
$o = $db->fetchOne("SELECT * FROM orders WHERE order_number = ?", [$orderNumber]);
if ($o) {
    print_r($o);
} else {
    echo "Order not found.\n";
}
