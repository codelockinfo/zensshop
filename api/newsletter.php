<?php
/**
 * Newsletter Subscription API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid email is required'
    ]);
    exit;
}

try {
    // In a real application, you would save to a newsletter table
    // For now, we'll just return success
    // You can add a newsletter_subscribers table later
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for subscribing!'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}

