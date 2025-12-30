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

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $item = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
                echo json_encode(['success' => true, 'category' => $item]);
            } else {
                $categories = $db->fetchAll("SELECT * FROM categories ORDER BY sort_order ASC");
                echo json_encode(['success' => true, 'categories' => $categories]);
            }
            break;
            
        case 'DELETE':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Category ID is required');
            }
            $db->execute("DELETE FROM categories WHERE id = ?", [$id]);
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


