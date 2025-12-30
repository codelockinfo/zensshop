<?php
/**
 * Admin Authentication API
 */

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent headers already sent errors
if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Handle logout action
if ($action === 'logout') {
    // Clear all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Logout the user
    $auth->logout();
    
    // Redirect to login page
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

// For other actions or invalid requests, return JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');
http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Invalid action'
]);
exit;


