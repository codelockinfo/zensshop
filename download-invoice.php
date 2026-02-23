<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Settings.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/InvoiceGenerator.php';

use CookPro\InvoiceGenerator;

// Auth check (Optional - we prioritize the unique order number for access)
$auth = new CustomerAuth();
$currentCustomer = $auth->isLoggedIn() ? $auth->getCurrentCustomer() : null;

// Get Order
$orderNumber = $_GET['order_number'] ?? null;
if (!$orderNumber) {
    die("Invalid Order");
}

$orderModel = new Order();
$order = $orderModel->getByOrderNumber($orderNumber);

if (!$order) {
    die("Order not found");
}

$generator = new InvoiceGenerator($order);
$pdf = $generator->generate();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice_' . $orderNumber . '.pdf"');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
