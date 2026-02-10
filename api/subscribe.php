<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    $db = Database::getInstance();
    
    // Check for customer login first
    $customerAuth = new CustomerAuth();
    $customer = $customerAuth->getCurrentCustomer();
    $userId = $customer ? $customer['id'] : null;
    
    if (!$userId) {
        $auth = new Auth();
        $admin = $auth->getCurrentUser();
        $userId = $admin ? $admin['id'] : null;
    }

    // Determine Store ID (Omni-store logic)
    $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? 'DEFAULT');

    // Auto-setup subscribers table if missing
    $db->execute("CREATE TABLE IF NOT EXISTS subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL,
        user_id BIGINT DEFAULT NULL,
        store_id VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY email_store (email, store_id)
    )");

    // Check if already exists
    $existing = $db->fetchOne("SELECT id FROM subscribers WHERE email = ? AND (store_id = ? OR (store_id IS NULL AND ? = 'DEFAULT'))", [$email, $storeId, $storeId]);

    if ($existing) {
        echo json_encode(['success' => true, 'message' => 'You are already subscribed!']);
        exit;
    }

    $db->insert("INSERT INTO subscribers (email, user_id, store_id) VALUES (?, ?, ?)", [$email, $userId, $storeId]);

    // Create notification for admin
    try {
        require_once __DIR__ . '/../classes/Notification.php';
        $notification = new Notification();
        $notification->notifyNewSubscriber($email);
    } catch (Throwable $tn) {
        error_log("Notification failed: " . $tn->getMessage());
    }
    
    // Send confirmation email
    try {
        require_once __DIR__ . '/../classes/Email.php';
        $emailService = new Email($storeId);
        $emailService->sendSubscriptionConfirmation($email);
    } catch (Throwable $te) {
        error_log("Email failed: " . $te->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Successfully subscribed!']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
