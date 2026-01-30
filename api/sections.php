<?php
/**
 * Sections API
 * Returns HTML for lazy-loaded sections
 */

// Disable error display for API responses (but log them)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId) {
    try {
        if (isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }
        if (!$storeId) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
             $storeId = $storeUser['store_id'] ?? null;
        }
        if ($storeId) $_SESSION['store_id'] = $storeId;
    } catch(Exception $ex) {}
}

// Get section parameter
$section = isset($_GET['section']) ? trim($_GET['section']) : '';

$allowedSections = [
    'categories',
    'best-selling',
    'special-offers',
    'videos',
    'trending',
    'philosophy',
    'features',
    'newsletter'
];

// Debug: Log the request
error_log("Sections API called with section: " . $section);

if (empty($section)) {
    http_response_code(400);
    echo '<div class="text-center py-8 text-gray-500">No section specified</div>';
    exit;
}

if (!in_array($section, $allowedSections)) {
    http_response_code(404);
    echo '<div class="text-center py-8 text-gray-500">Section not found: ' . htmlspecialchars($section) . '</div>';
    error_log("Invalid section requested: " . $section);
    exit;
}

$sectionFile = __DIR__ . '/../sections/' . $section . '.php';

if (!file_exists($sectionFile)) {
    http_response_code(404);
    echo '<div class="text-center py-8 text-gray-500">Section file not found: ' . htmlspecialchars($section) . '</div>';
    error_log("Section file not found: " . $sectionFile);
    exit;
}

try {
    // Ensure baseUrl is available for all sections
    if (!function_exists('getBaseUrl')) {
        require_once __DIR__ . '/../includes/functions.php';
    }
    if (!isset($baseUrl)) {
        $baseUrl = getBaseUrl();
    }
    
    // Ensure url() function is available
    if (!function_exists('url')) {
        function url($path = '') {
            $baseUrl = getBaseUrl();
            $path = ltrim($path, '/');
            $queryString = '';
            if (strpos($path, '?') !== false) {
                $parts = explode('?', $path, 2);
                $path = $parts[0];
                $queryString = '?' . $parts[1];
            }
            $path = preg_replace('/\.php$/', '', $path);
            if (empty($path)) {
                return $baseUrl . '/' . $queryString;
            }
            return $baseUrl . '/' . $path . $queryString;
        }
    }
    
    // Start output buffering
    ob_start();
    
    // Include the section file
    include $sectionFile;
    
    // Get the output
    $html = ob_get_clean();
    
    // Output the content (even if empty)
    echo $html;
} catch (Throwable $e) {
    // Clean any output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo '<div class="text-center py-8 text-red-500">Error loading section</div>';
    error_log("Error in sections API for section '{$section}': " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

