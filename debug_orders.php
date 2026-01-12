<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

echo "Latest 10 orders:\n";
$orders = $db->fetchAll("SELECT id, order_number, user_id, customer_email, created_at FROM orders ORDER BY id DESC LIMIT 10");
foreach ($orders as $o) {
    echo "ID: {$o['id']} | Number: {$o['order_number']} | User ID: " . ($o['user_id'] ?? 'NULL') . " | Email: {$o['customer_email']} | Date: {$o['created_at']}\n";
}

echo "\nCustomers:\n";
$customers = $db->fetchAll("SELECT id, name, email FROM customers LIMIT 10");
foreach ($customers as $c) {
    echo "ID: {$c['id']} | Name: {$c['name']} | Email: {$c['email']}\n";
}
