<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

// Check authentication (basic check)
// In a real app, you would verify admin session here
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) { die(json_encode([])); }

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();
$products = $db->fetchAll(
    "SELECT id, name,featured_image FROM products 
     WHERE (name LIKE ? OR sku LIKE ?) AND status = 'active' 
     LIMIT 10",
    ["%$term%", "%$term%"]
);

$results = [];
foreach ($products as $p) {
    $results[] = [
        'id' => $p['id'],
        'label' => $p['name'],
        'value' => $p['id'],
        'image' => getImageUrl($p['featured_image'])
    ];
}

echo json_encode($results);
