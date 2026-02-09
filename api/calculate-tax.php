<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Cart.php';
require_once __DIR__ . '/../classes/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
$customerState = $input['state'] ?? '';

$cart = new Cart();
$items = $cart->getCart();
$sellerState = getSetting('seller_state', 'Maharashtra');

$db = Database::getInstance();

$totalTax = 0;
$cgstTotal = 0;
$sgstTotal = 0;
$igstTotal = 0;
$subtotal = 0;

$processedItems = [];

foreach ($items as $item) {
    $pId = $item['product_id'];
    
    // Fetch product GST details
    $productInfo = $db->fetchOne(
        "SELECT is_taxable, hsn_code, gst_percent FROM products WHERE (product_id = ? OR id = ?)", 
        [$pId, $pId]
    );
    
    $gstPercent = 0;
    if ($productInfo && $productInfo['is_taxable']) {
        $gstPercent = $productInfo['gst_percent'];
    }
    
    // Calculate tax on the effective price (Sale Price if available, otherwise Price)
    // The item['price'] already holds the effective price as per Cart.php logic
    $gstResult = calculateGST($item['price'], $gstPercent, $sellerState, $customerState, $item['quantity']);
    
    $itemLineSubtotal = $item['price'] * $item['quantity'];
    
    $totalTax += ($gstResult['cgst'] + $gstResult['sgst'] + $gstResult['igst']);
    $cgstTotal += $gstResult['cgst'];
    $sgstTotal += $gstResult['sgst'];
    $igstTotal += $gstResult['igst'];
    $subtotal += $itemLineSubtotal;
    
    $processedItems[] = array_merge($item, [
        'cgst' => $gstResult['cgst'],
        'sgst' => $gstResult['sgst'],
        'igst' => $gstResult['igst'],
        'tax_total' => ($gstResult['cgst'] + $gstResult['sgst'] + $gstResult['igst']),
        'line_total' => $itemLineSubtotal + ($gstResult['cgst'] + $gstResult['sgst'] + $gstResult['igst'])
    ]);
}

echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'total_tax' => $totalTax,
    'cgst_total' => $cgstTotal,
    'sgst_total' => $sgstTotal,
    'igst_total' => $igstTotal,
    'grand_total' => ($subtotal + $totalTax),
    'items' => $processedItems
]);
