<?php
// API Endpoint for Quick View Data
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$slug = $_GET['slug'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($slug) && empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Product identifier missing']);
    exit;
}

try {
    $productObj = new Product();
    $db = Database::getInstance();
    
    $product = null;
    if ($slug) {
        $product = $productObj->getBySlug($slug);
    } elseif ($id) {
        $product = $productObj->getById($id);
    }
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Enrich with images and options
    $images = json_decode($product['images'] ?? '[]', true);
    if (empty($images)) {
        // Fallback to main image
        $images = [$product['image'] ?? 'assets/images/placeholder.jpg'];
    }
    
    // Get Variants/Options
    $variantsData = $productObj->getVariants($product['id']);
    
    // Check Wishlist status
    try {
        require_once __DIR__ . '/../classes/Wishlist.php';
        $wishlistObj = new Wishlist();
        $inWishlist = $wishlistObj->isInWishlist($product['product_id']);
    } catch (Exception $e) {
        $inWishlist = false;
    }

    $responseData = [
        'id' => $product['id'],
        'product_id' => $product['product_id'],
        'name' => $product['name'],
        'slug' => $product['slug'],
        'price' => $product['price'],
        'sale_price' => $product['sale_price'],
        'currency' => $product['currency'] ?? 'USD',
        'short_description' => $product['short_description'] ?? '',
        'description' => $product['description'] ?? '', // Limit length?
        'rating' => $product['rating'] ?? 0,
        'review_count' => $product['review_count'] ?? 0,
        'image' => getProductImage($product),
        'images' => array_map('getImageUrl', $images),
        'options' => array_map(function($opt) {
            return [
                'name' => $opt['option_name'],
                'values' => $opt['option_values']
            ];
        }, $variantsData['options'] ?? []),
        'sku' => $product['sku'] ?? 'N/A',
        'in_wishlist' => $inWishlist,
        'stock_status' => $product['stock_status'],
        'stock_quantity' => $product['stock_quantity'],
        'highlights' => json_decode($product['highlights'] ?? '[]', true),
        'shipping_policy' => $product['shipping_policy'] ?? '',
        'return_policy' => $product['return_policy'] ?? '',
        'variants' => array_map(function($v) {
            return [
                'id' => $v['id'],
                'sku' => $v['sku'],
                'price' => $v['price'],
                'sale_price' => $v['sale_price'],
                'stock_quantity' => $v['stock_quantity'],
                'stock_status' => $v['stock_status'],
                'image' => !empty($v['image']) ? getImageUrl($v['image']) : null,
                'attributes' => $v['variant_attributes']
            ];
        }, $variantsData['variants'] ?? [])
    ];
    
    echo json_encode(['success' => true, 'product' => $responseData]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
