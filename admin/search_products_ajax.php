<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

$db = Database::getInstance();
$params = ["%$term%", "%$term%", "%$term%"];
$query = "SELECT id, product_id, name, sku, featured_image FROM products 
          WHERE (name LIKE ? OR sku LIKE ? OR product_id LIKE ?) AND status = 'active' 
          LIMIT 20";

$products = $db->fetchAll($query, $params);

$results = [];
foreach ($products as $p) {
    $results[] = [
        'id' => $p['product_id'], // We use product_id for the homepage settings
        'system_id' => $p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'],
        'image' => getImageUrl($p['featured_image'])
    ];
}

echo json_encode($results);
