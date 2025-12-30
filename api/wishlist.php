<?php
/**
 * Wishlist API
 * Handles wishlist operations via AJAX
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Wishlist.php';

$wishlist = new Wishlist();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // Get wishlist items
            $items = $wishlist->getWishlist();
            echo json_encode([
                'success' => true,
                'wishlist' => $items,
                'count' => $wishlist->getCount()
            ]);
            break;
            
        case 'POST':
            // Add item to wishlist
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $items = $wishlist->addItem($productId);
            
            echo json_encode([
                'success' => true,
                'wishlist' => $items,
                'count' => $wishlist->getCount(),
                'message' => 'Product added to wishlist'
            ]);
            break;
            
        case 'DELETE':
            // Remove item from wishlist
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $items = $wishlist->removeItem($productId);
            
            echo json_encode([
                'success' => true,
                'wishlist' => $items,
                'count' => $wishlist->getCount(),
                'message' => 'Product removed from wishlist'
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


