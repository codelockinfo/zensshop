<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    $db = Database::getInstance();
    
    // Check for customer login first (most common case)
    $customerAuth = new CustomerAuth();
    $customer = $customerAuth->getCurrentCustomer();
    $userId = $customer ? $customer['id'] : null;
    
    // If not a customer, check for admin login
    if (!$userId) {
        $auth = new Auth();
        $admin = $auth->getCurrentUser();
        $userId = $admin ? $admin['id'] : null;
    }

    // Auto-setup: existence check
    $tables = $db->fetchAll("SHOW TABLES LIKE 'subscribers'");
    if (empty($tables)) {
        $db->execute("CREATE TABLE subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(191) NOT NULL,
            user_id BIGINT DEFAULT NULL,
            store_id VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY email_store (email, store_id)
        )");
    }

    // Determine Store ID (Omni-store logic)
    if (function_exists('getCurrentStoreId')) {
        $storeId = getCurrentStoreId();
    } else {
        $storeId = $_SESSION['store_id'] ?? null;
    }

    // Check if already exists in this store
    $existing = $db->fetchOne("SELECT id FROM subscribers WHERE email = ? AND (store_id = ? OR store_id IS NULL)", [$email, $storeId]);

    if ($existing) {
        echo json_encode(['success' => true, 'message' => 'You are already subscribed!']);
        exit;
    }

    $db->insert("INSERT INTO subscribers (email, user_id, store_id) VALUES (?, ?, ?)", [$email, $userId, $storeId]);

    // Create notification for admin
    require_once __DIR__ . '/../classes/Notification.php';
    $notification = new Notification();
    $notification->notifyNewSubscriber($email);
    
    // Send confirmation email to subscriber
    try {
        require_once __DIR__ . '/../classes/Email.php';
        $emailService = new Email();
        $emailService->sendSubscriptionConfirmation($email);
    } catch (Exception $e) {
        error_log("Failed to send subscription confirmation email: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Successfully subscribed!']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
