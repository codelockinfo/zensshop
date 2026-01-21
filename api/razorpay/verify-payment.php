<?php
/**
 * Verify Razorpay Payment
 * E-commerce System
 */

// Start output buffering to prevent any output before JSON
ob_start();

// Disable error display, log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Cart.php';
require_once __DIR__ . '/../../classes/CustomerAuth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Clear any output that might have been generated
ob_clean();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Validate required fields
$required = ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature', 'order_data'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize input
$paymentId = sanitize_input($input['razorpay_payment_id']);
$orderId = sanitize_input($input['razorpay_order_id']);
$signature = sanitize_input($input['razorpay_signature']);
$orderData = $input['order_data']; // Contains customer info, address, etc.

// Get user ID if logged in
$userId = null;
$auth = new CustomerAuth();
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentCustomer();
    $userId = $currentUser['id'] ?? null;
}

try {
    $razorpayKeySecret = RAZORPAY_KEY_SECRET;
    
    // Verify signature
    $generatedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $razorpayKeySecret);
    
    if ($generatedSignature !== $signature) {
        echo json_encode(['success' => false, 'message' => 'Payment verification failed: Invalid signature']);
        exit;
    }
    
    // Verify payment with Razorpay API
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . $paymentId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(RAZORPAY_KEY_ID . ':' . $razorpayKeySecret)
    ]);
    
    $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) || 
                   strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    
    if ($isLocalhost && RAZORPAY_MODE === 'test') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to verify payment with Razorpay']);
        exit;
    }
    
    $paymentData = json_decode($response, true);
    
    // Check if payment is successful
    if ($paymentData['status'] !== 'captured' && $paymentData['status'] !== 'authorized') {
        echo json_encode(['success' => false, 'message' => 'Payment not successful. Status: ' . $paymentData['status']]);
        exit;
    }
    
    // Get cart items
    $cart = new Cart();
    $cartItems = $cart->getCart();
    
    // Prepare order data
    $orderData['user_id'] = $userId;
    $orderData['items'] = [];
    $orderData['payment_method'] = 'razorpay';
    $orderData['payment_status'] = 'paid';
    $orderData['razorpay_payment_id'] = $paymentId;
    $orderData['razorpay_order_id'] = $orderId;
    
    // Prepare order items from cart
    foreach ($cartItems as $item) {
        $orderData['items'][] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'product_sku' => $item['sku'] ?? null,
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ];
    }
    
    // Create order
    $order = new Order();
    $createdOrderId = $order->create($orderData);
    
    // Clear cart
    $cart->clear();
    
    // Clear discount session
    if (isset($_SESSION['checkout_discount_code'])) {
        unset($_SESSION['checkout_discount_code']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified and order created successfully',
        'order_id' => $createdOrderId['id'],
        'order_number' => $createdOrderId['order_number']
    ]);
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    error_log("Payment verification error trace: " . $e->getTraceAsString());
    ob_clean();
    
    // Provide more detailed error message for debugging
    $errorMessage = 'Payment verification failed. Please contact support.';
    if (strpos($e->getMessage(), 'razorpay_payment_id') !== false || strpos($e->getMessage(), 'Column not found') !== false) {
        $errorMessage = 'Database error: Razorpay columns missing. Please run database migration.';
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $errorMessage,
        'error' => RAZORPAY_MODE === 'test' ? $e->getMessage() : null
    ]);
    exit;
}

// End output buffering
ob_end_flush();

