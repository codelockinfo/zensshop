<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Notification.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notification = new Notification();
$action = $_GET['action'] ?? 'list';
$storeId = $_SESSION['store_id'] ?? null;

try {
    switch ($action) {
        case 'count':
            echo json_encode(['count' => $notification->getUnreadCount($storeId)]);
            break;
            
        case 'list':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
            $notifications = $notification->getRecent($limit, $unreadOnly, $storeId);
            echo json_encode(['notifications' => $notifications]);
            break;
            
        case 'mark_read':
            if (isset($_POST['id'])) {
                $notification->markAsRead(intval($_POST['id']), $storeId);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('ID required');
            }
            break;
            
        case 'mark_all_read':
            $notification->markAllAsRead($storeId);
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
