<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    $customerName = trim($input['name'] ?? '');
    $customerEmail = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    // Validation
    if (empty($customerName)) {
        throw new Exception('Name is required');
    }
    
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    if (empty($subject)) {
        throw new Exception('Subject is required');
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }

    $db = Database::getInstance();
    
    // Check if customer is logged in
    $customerAuth = new CustomerAuth();
    $customer = $customerAuth->getCurrentCustomer();
    $customerId = $customer ? $customer['id'] : null;

    // Determine Store ID
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }
    if (!$storeId) {
         try {
            $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
            $storeId = $storeUser['store_id'] ?? null;
         } catch(Exception $ex) {}
    }

    // Insert support message
    $messageId = $db->insert(
        "INSERT INTO support_messages (customer_id, customer_name, customer_email, subject, message, store_id) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$customerId, $customerName, $customerEmail, $subject, $message, $storeId]
    );

    if (!$messageId) {
        throw new Exception('Failed to save message. Please try again.');
    }

    // Try to send email notification to admin (optional - won't fail if email not configured)
    try {
        require_once __DIR__ . '/../classes/Email.php';
        require_once __DIR__ . '/../classes/Auth.php';
        
        // Get admin email from users table (first admin)
        $admin = $db->fetchOne("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
        if ($admin && !empty($admin['email'])) {
            $email = new Email();
            $email->sendSupportNotificationToAdmin(
                $admin['email'],
                $customerName,
                $customerEmail,
                $subject,
                $message
            );
        }
    } catch (Exception $e) {
        // Log email error but don't fail the request
        error_log("Failed to send support notification email: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Your message has been sent successfully. We will get back to you soon!'
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Support API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

