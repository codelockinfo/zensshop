<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'count';

try {
    switch ($action) {
        case 'count':
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM support_messages WHERE status = 'open'");
            echo json_encode(['count' => $result['count'] ?? 0]);
            break;
            
        case 'list':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $messages = $db->fetchAll(
                "SELECT * FROM support_messages ORDER BY created_at DESC LIMIT ?",
                [$limit]
            );
            echo json_encode(['messages' => $messages]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
