<?php
/**
 * API Endpoint for Cart Discount Management
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Cart.php';
require_once __DIR__ . '/../classes/Discount.php';

header('Content-Type: application/json');

// Helper to send JSON response
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (empty($action)) {
    sendResponse(false, 'Missing action');
}

$cart = new Cart();
$cartTotal = $cart->getTotal();

// Handle Removal
if ($action === 'remove') {
    if (isset($_SESSION['checkout_discount_code'])) {
        unset($_SESSION['checkout_discount_code']);
        sendResponse(true, 'Discount removed successfully', [
            'discount_amount' => 0,
            'subtotal' => $cartTotal,
            'cart_total' => $cartTotal
        ]);
    } else {
        sendResponse(false, 'No discount to remove');
    }
}

// Handle Application
if ($action === 'apply') {
    $code = trim($input['code'] ?? '');
    
    if (empty($code)) {
        sendResponse(false, 'Please enter a discount code');
    }

    try {
        $discountManager = new Discount();
        $discountAmount = $discountManager->calculateAmount($code, $cartTotal);
        
        // Save to session
        $_SESSION['checkout_discount_code'] = $code;
        
        sendResponse(true, 'Discount applied successfully!', [
            'code' => $code,
            'discount_amount' => $discountAmount,
            'subtotal' => $cartTotal,
            'cart_total' => max(0, $cartTotal - $discountAmount)
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
}

sendResponse(false, 'Invalid action');
