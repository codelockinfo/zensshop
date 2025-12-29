<?php
/**
 * Products API
 * Returns product data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Product.php';

$product = new Product();

$filters = [
    'status' => $_GET['status'] ?? 'active',
    'category_id' => $_GET['category_id'] ?? null,
    'featured' => $_GET['featured'] ?? null,
    'search' => $_GET['search'] ?? null,
    'limit' => $_GET['limit'] ?? null
];

try {
    $products = $product->getAll($filters);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

