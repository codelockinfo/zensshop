<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

echo "Linking orders with missing user_id to customers...\n";

$orders = $db->fetchAll("SELECT id, customer_email FROM orders WHERE user_id IS NULL OR user_id = 0");

foreach ($orders as $o) {
    $email = $o['customer_email'];
    $customer = $db->fetchOne("SELECT id FROM customers WHERE email = ?", [$email]);
    
    if ($customer) {
        echo "Found customer for order {$o['id']} (Email: $email) -> User ID: {$customer['id']}\n";
        $db->execute("UPDATE orders SET user_id = ? WHERE id = ?", [$customer['id'], $o['id']]);
        echo "Updated order {$o['id']}.\n";
    } else {
        echo "No customer found for email $email.\n";
    }
}

echo "Done.\n";
