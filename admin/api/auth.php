<?php
/**
 * Admin Authentication API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'logout':
        $auth->logout();
        header('Location: ' . $baseUrl . '/admin/index.php');
        exit;
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}


