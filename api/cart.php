<?php
/**
 * Cart API
 * Handles cart operations via AJAX
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Cart.php';

$cart = new Cart();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // Get cart items
            $items = $cart->getCart();
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount()
            ]);
            break;
            
        case 'POST':
            // Add item to cart
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            
            $items = $cart->addItem($productId, $quantity);
            
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'message' => 'Product added to cart'
            ]);
            break;
            
        case 'PUT':
            // Update item quantity
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
            
            $items = $cart->updateItem($productId, $quantity);
            
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'message' => 'Cart updated'
            ]);
            break;
            
        case 'DELETE':
            // Remove item from cart
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $items = $cart->removeItem($productId);
            
            echo json_encode([
                'success' => true,
                'cart' => $items,
                'total' => $cart->getTotal(),
                'count' => $cart->getCount(),
                'message' => 'Product removed from cart'
            ]);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

