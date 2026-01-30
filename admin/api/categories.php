<?php
/**
 * Categories API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();
$auth->requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$db = Database::getInstance();

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $item = $db->fetchOne("SELECT * FROM categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
                echo json_encode(['success' => true, 'category' => $item]);
            } else {
                $categories = $db->fetchAll("SELECT * FROM categories WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);
                echo json_encode(['success' => true, 'categories' => $categories]);
            }
            break;
            
        case 'DELETE':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Category ID is required');
            }
            $db->execute("DELETE FROM categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
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


