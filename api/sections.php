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
require_once __DIR__ . '/../includes/functions.php';
$db = Database::getInstance();

// Store ID is only used for admin side logic. 
// On the front side we show everything regardless of store_id as it's filtered by domain/installation.
$storeId = null;

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
    'newsletter',
    'footer_features',
    'related-products',
    'recently-viewed'
];

// Capture product ID if provided
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

// Debug: Log the request
error_log("Sections API called with section: " . $section . ($productId ? " and product_id: " . $productId : ""));

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

