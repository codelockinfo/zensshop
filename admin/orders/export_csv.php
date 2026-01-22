<?php
ob_start();
session_start();
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Initialize Order class
$orderModel = new Order();

// Fetch ALL orders
$orders = $orderModel->getAll([]); 

// Clean buffer
ob_end_clean();

// Set Headers for CSV Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, [
    'Order ID', 
    'Order Number', 
    'Date', 
    'Customer Name', 
    'Email', 
    'Phone', 
    'Status', 
    'Payment Status', 
    'Payment Method',
    'Total Amount', 
    'Currency',
    'Items Summary',
    'Billing Address',
    'Shipping Address'
]);

// Loop over the rows, outputting them
foreach ($orders as $o) {
    // Format Items
    $items = $orderModel->getOrderItems($o['id']);
    $itemStrings = [];
    foreach ($items as $item) {
        $itemStrings[] = $item['product_name'] . ' (Qty: ' . $item['quantity'] . ', Price: ' . $item['price'] . ')';
    }
    $itemsSummary = implode(' | ', $itemStrings);
    
    // Format Addresses
    $billing = json_decode($o['billing_address'], true);
    $billingStr = '';
    if ($billing) {
        $parts = [
            $billing['address'] ?? '',
            $billing['city'] ?? '',
            $billing['zip'] ?? '',
            $billing['country'] ?? ''
        ];
        $billingStr = implode(', ', array_filter($parts));
    }

    $shipping = json_decode($o['shipping_address'], true);
    $shippingStr = '';
    if ($shipping) {
        $parts = [
            $shipping['address'] ?? '',
            $shipping['city'] ?? '',
            $shipping['zip'] ?? '',
            $shipping['country'] ?? ''
        ];
        $shippingStr = implode(', ', array_filter($parts));
    }

    $row = [
        $o['id'],
        $o['order_number'],
        $o['created_at'],
        $o['customer_name'],
        $o['customer_email'],
        $o['customer_phone'],
        $o['order_status'],
        $o['payment_status'],
        $o['payment_method'] ?? '',
        $o['total_amount'],
        'INR', // Assuming INR based on user context
        $itemsSummary,
        $billingStr,
        $shippingStr
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;
