<?php
/**
 * Admin Search API
 * Searches across products, categories, orders, and customers
 */

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$baseUrl = getBaseUrl();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$query = trim($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    echo json_encode([
        'products' => [],
        'categories' => [],
        'orders' => [],
        'customers' => []
    ]);
    exit;
}

$searchTerm = "%{$query}%";
$results = [
    'products' => [],
    'categories' => [],
    'orders' => [],
    'customers' => []
];

try {
    // Search Products (by name, SKU, ID)
    $products = $db->fetchAll(
        "SELECT id, name, sku, price, status, featured_image 
         FROM products 
         WHERE name LIKE ? OR sku LIKE ? OR id = ?
         ORDER BY name ASC 
         LIMIT 5",
        [$searchTerm, $searchTerm, is_numeric($query) ? (int)$query : -1]
    );
    
    foreach ($products as $product) {
        $results['products'][] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'price' => $product['price'],
            'status' => $product['status'],
            'image' => $product['featured_image'] ?: $baseUrl . '/assets/images/default-product.svg',
            'url' => $baseUrl . "/admin/products/edit.php?id={$product['id']}"
        ];
    }
    
    // Search Categories/Collections (by name, ID)
    $categories = $db->fetchAll(
        "SELECT id, name, slug, image, status 
         FROM categories 
         WHERE name LIKE ? OR id = ?
         ORDER BY name ASC 
         LIMIT 5",
        [$searchTerm, is_numeric($query) ? (int)$query : -1]
    );
    
    foreach ($categories as $category) {
        $results['categories'][] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'image' => $category['image'] ?: $baseUrl . '/assets/images/default-category.svg',
            'status' => $category['status'],
            'url' => $baseUrl . "/admin/categories/manage.php?id={$category['id']}"
        ];
    }
    
    // Search Orders (by ID, order number, customer name, email)
    try {
        $orders = $db->fetchAll(
            "SELECT o.id, o.total_amount, o.status, o.created_at,
                    COALESCE(u.name, 'Guest') as customer_name,
                    COALESCE(u.email, '') as customer_email,
                    COALESCE(o.order_number, CONCAT('ORD-', o.id)) as order_number
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE CAST(o.id AS CHAR) LIKE ?
                OR COALESCE(o.order_number, '') LIKE ?
                OR u.name LIKE ?
                OR u.email LIKE ?
             ORDER BY o.created_at DESC 
             LIMIT 5",
            [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
        );
    } catch (Exception $e) {
        $orders = [];
    }
    
    foreach ($orders as $order) {
        $results['orders'][] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'] ?? '#' . $order['id'],
            'total_amount' => $order['total_amount'],
            'status' => $order['status'],
            'customer_name' => $order['customer_name'] ?: 'Guest',
            'customer_email' => $order['customer_email'] ?: '',
            'created_at' => $order['created_at'],
            'url' => $baseUrl . "/admin/orders/detail.php?id={$order['id']}"
        ];
    }
    
    // Search Customers (by name, email, ID)
    // First check if customers table exists
    try {
        $customers = $db->fetchAll(
            "SELECT id, name, email, phone, created_at 
             FROM customers 
             WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR id = ?
             ORDER BY name ASC 
             LIMIT 5",
            [$searchTerm, $searchTerm, $searchTerm, is_numeric($query) ? (int)$query : -1]
        );
        
        foreach ($customers as $customer) {
            $results['customers'][] = [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? '',
                'created_at' => $customer['created_at'],
                'url' => $baseUrl . "/admin/customers/view.php?id={$customer['id']}"
            ];
        }
    } catch (Exception $e) {
        // Customers table might not exist, skip
    }
    
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
}

echo json_encode($results);

