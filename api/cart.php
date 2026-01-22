<?php
/**
 * Cart API
 * Handles cart operations via AJAX
 */

require_once __DIR__ . '/../classes/Cart.php';

$cart = new Cart();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
$rawInput = file_get_contents('php://input');
error_log("=== Cart API Request ===");
error_log("Method: " . $method);
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET'));
error_log("Raw php://input: " . $rawInput);
error_log("Parsed input: " . json_encode($input));
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));

try {
    switch ($method) {
        case 'GET':
            // Get cart items
            $items = $cart->getCart();
            // Include cookie_data in GET response too for consistency
            $cookieValue = json_encode($items);
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'cookie_data' => $cookieValue
            ]);
            break;
            
        case 'POST':
            // Add item to cart
            error_log("Cart API POST - Input received: " . json_encode($input));
            
            if (empty($input['product_id'])) {
                error_log("Cart API POST - Product ID is empty!");
                throw new Exception('Product ID is required');
            }
            
            $productId = $input['product_id'];
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            $attributes = isset($input['variant_attributes']) ? $input['variant_attributes'] : (isset($input['attributes']) ? $input['attributes'] : []);
            
            error_log("Cart API POST - Adding product ID: $productId, quantity: $quantity, attrs: " . json_encode($attributes));
            
            $items = $cart->addItem($productId, $quantity, $attributes);
            
            error_log("Cart API POST - Items after add: " . json_encode($items));
            error_log("Cart API POST - Cart count: " . count($items));
            
            // Include cookie_data for JavaScript to set cookie
            $cookieValue = json_encode($items);
            
            // Get product name and cart totals for success message
            $productName = !empty($items) && isset($items[count($items) - 1]['name']) ? $items[count($items) - 1]['name'] : 'Product';
            $cartTotal = $cart->getTotal();
            $cartCount = $cart->getCount();
            
            $response = [
                'success' => true,
                'cart' => $items,
                'total' => $cartTotal,
                'count' => $cartCount,
                'message' => 'Product added to cart',
                'cookie_data' => $cookieValue
            ];
            
            // Terminal success message
            error_log("✅ SUCCESS: $productName added to cart!");
            error_log("   Product ID: $productId");
            error_log("   Quantity: $quantity");
            error_log("   Cart Total: $" . number_format($cartTotal, 2));
            error_log("   Cart Count: $cartCount item(s)");
            error_log("   Cookie set: YES");
            
            echo json_encode($response);
            break;
            
        case 'PUT':
            // Update item quantity
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = $input['product_id'];
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            $attributes = isset($input['variant_attributes']) ? $input['variant_attributes'] : (isset($input['attributes']) ? $input['attributes'] : []);
            
            $items = $cart->updateItem($productId, $quantity, $attributes);
            
            // Include cookie_data for JavaScript to set cookie
            $cookieValue = json_encode($items);
            
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'message' => 'Cart updated',
                'cookie_data' => $cookieValue
            ]);
            break;
            
        case 'DELETE':
            // Remove item from cart
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = $input['product_id'];
            $attributes = isset($input['variant_attributes']) ? $input['variant_attributes'] : (isset($input['attributes']) ? $input['attributes'] : []);
            $items = $cart->removeItem($productId, $attributes);
            
            // Include cookie_data for JavaScript to set cookie
            $cookieValue = json_encode($items);
            
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'message' => 'Product removed from cart',
                'cookie_data' => $cookieValue
            ]);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    
    // Log the error with full details
    error_log("❌ Cart API ERROR: " . $e->getMessage());
    error_log("   File: " . $e->getFile());
    error_log("   Line: " . $e->getLine());
    error_log("   Method: " . ($method ?? 'UNKNOWN'));
    error_log("   Input: " . json_encode($input ?? []));
    
    // Even on error, include empty cart and cookie_data
    $cartItems = $cart->getCart();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'cart' => $cartItems,
        'cookie_data' => json_encode($cartItems),
        'error' => $e->getMessage()
    ]);
}


