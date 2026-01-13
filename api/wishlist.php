<?php
/**
 * Wishlist API
 * Handles wishlist operations via AJAX
 */

require_once __DIR__ . '/../classes/Wishlist.php';

$wishlist = new Wishlist();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
$rawInput = file_get_contents('php://input');
error_log("=== Wishlist API Request ===");
error_log("Method: " . $method);
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET'));
error_log("Raw php://input: " . $rawInput);
error_log("Parsed input: " . json_encode($input));
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));

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
            
            // Include cookie_data for JavaScript to set cookie
            $cookieValue = json_encode($items);
            
            echo json_encode([
                'success' => true,
                'wishlist' => $items,
                'count' => $wishlist->getCount(),
                'message' => 'Product added to wishlist',
                'cookie_data' => $cookieValue
            ]);
            break;
            
        case 'DELETE':
            // Remove item from wishlist
            if (empty($input['product_id'])) {
                throw new Exception('Product ID is required');
            }
            
            $productId = (int)$input['product_id'];
            $items = $wishlist->removeItem($productId);
            
            // Include cookie_data for JavaScript to set cookie
            $cookieValue = json_encode($items);
            
            echo json_encode([
                'success' => true,
                'wishlist' => $items,
                'count' => $wishlist->getCount(),
                'message' => 'Product removed from wishlist',
                'cookie_data' => $cookieValue
            ]);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    
    // Log the error with full details
    error_log("❌ Wishlist API ERROR: " . $e->getMessage());
    error_log("   File: " . $e->getFile());
    error_log("   Line: " . $e->getLine());
    error_log("   Method: " . ($method ?? 'UNKNOWN'));
    error_log("   Input: " . json_encode($input ?? []));
    
    // Even on error, include empty wishlist and cookie_data
    $wishlistItems = $wishlist->getWishlist();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'wishlist' => $wishlistItems,
        'cookie_data' => json_encode($wishlistItems),
        'error' => $e->getMessage()
    ]);
}


