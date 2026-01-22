<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$search = $_GET['term'] ?? '';

header('Content-Type: application/json');

try {
    $sql = "SELECT p.* FROM products p WHERE p.status != 'archived'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.id LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY p.created_at DESC LIMIT 20";

    $products = $db->fetchAll($sql, $params);

    $results = [];
    foreach ($products as $item) {
        $results[] = [
            'id' => $item['product_id'], // Use public product_id
            'name' => $item['name'],
            'sku' => $item['sku'],
            'image' => getProductImage($item)
        ];
    }

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
