<?php
/**
 * Create Razorpay Order
 * E-commerce System
 */

// Start output buffering to prevent any output before JSON
ob_start();

// Disable error display, log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Cart.php';
require_once __DIR__ . '/../../classes/CustomerAuth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Clear any output that might have been generated
ob_clean();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Something went wrong']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong']);
    exit;
}

// Validate required fields
$required = ['customer_name', 'customer_email', 'customer_phone', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => "Something went wrong"]);
        exit;
    }
}

// Sanitize input
$customerName = sanitize_input($input['customer_name']);
$customerEmail = sanitize_input($input['customer_email']);
$customerPhone = sanitize_input($input['customer_phone']);
$amount = floatval($input['amount']);

// Optional: Get shipping and discount from input if provided
$shippingAmount = isset($input['shipping_amount']) ? floatval($input['shipping_amount']) : 0;
$discountAmount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0;

// Validate amount
if ($amount <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Something went wrong']);
    exit;
}

// Get cart to verify amount
$cart = new Cart();
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();

// Calculate expected total (cart + shipping - discount)
$expectedTotal = $cartTotal + $shippingAmount - $discountAmount;

// Log amounts for debugging
error_log("Razorpay Create Order - Received amount: $amount");
error_log("Razorpay Create Order - Cart total: $cartTotal");
error_log("Razorpay Create Order - Shipping: $shippingAmount");
error_log("Razorpay Create Order - Discount: $discountAmount");
error_log("Razorpay Create Order - Expected total: $expectedTotal");
error_log("Razorpay Create Order - Difference: " . abs($amount - $expectedTotal));

// Verify amount matches expected total (allow small difference for rounding, max 0.01)
$difference = abs($amount - $expectedTotal);
if ($difference > 0.01) {
    error_log("Razorpay Create Order - Amount mismatch detected");
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Cart verification failed: Amount mismatch. Please refresh cart.',
        'debug' => [
            'received_amount' => $amount,
            'expected_total' => $expectedTotal,
            'cart_total' => $cartTotal
        ]
    ]);
    exit;
}

// Get user ID if logged in
$auth = new CustomerAuth();
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentCustomer();
    $userId = $currentUser['customer_id'] ?? null;
}

// Convert amount to paise (Razorpay uses smallest currency unit)
$amountInPaise = intval($amount * 100);

try {
    // Initialize Razorpay
    $razorpayKeyId = RAZORPAY_KEY_ID;
    $razorpayKeySecret = RAZORPAY_KEY_SECRET;
    
    // Validate Razorpay keys
    if (empty($razorpayKeyId) || strpos($razorpayKeyId, 'YOUR_KEY') !== false) {
        $msg = "Razorpay Key ID is not configured.";
        error_log($msg);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    
    if (empty($razorpayKeySecret) || strpos($razorpayKeySecret, 'YOUR_KEY') !== false) {
        $msg = "Razorpay Key Secret is not configured.";
        error_log($msg);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    
    // Create order via Razorpay API
    $orderData = [
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'receipt' => 'order_' . ($userId ?? 'guest') . '_' . time(),
        'notes' => [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'user_id' => $userId ?? 'guest',
            'cart_items' => json_encode($cartItems)
        ]
    ];
    
    // Make API call to Razorpay
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($razorpayKeyId . ':' . $razorpayKeySecret)
    ]);
    
    // SSL verification based on environment
    $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) || 
                   strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    
    if ($isLocalhost && RAZORPAY_MODE === 'test') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for cURL errors
    if ($response === false || !empty($curlError)) {
        error_log("Razorpay cURL Error: " . $curlError);
        echo json_encode([
            'success' => false, 
            'message' => 'Connection Error: ' . $curlError
        ]);
        exit;
    }
    
    // Check HTTP status code
    if ($httpCode !== 200) {
        error_log("Razorpay API Error (HTTP $httpCode): " . $response);
        $respData = json_decode($response, true);
        $msg = $respData['error']['description'] ?? 'Razorpay API Error';
        echo json_encode([
            'success' => false, 
            'message' => 'Payment Gateway Error: ' . $msg,
            'http_code' => $httpCode,
            'response' => $respData
        ]);
        exit;
    }
    
    $orderResponse = json_decode($response, true);
    
    if (!isset($orderResponse['id'])) {
        error_log("Invalid Razorpay response: " . $response);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid Gateway Response',
            'debug_response' => $orderResponse
        ]);
        exit;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'order_id' => $orderResponse['id'],
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'razorpay_key' => $razorpayKeyId
    ]);
    
} catch (Exception $e) {
    error_log("Razorpay order creation error: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Something went wrong: ' . $e->getMessage(),
        'debug_error' => $e->getMessage()
    ]);
    exit;
}

// End output buffering
ob_end_flush();

