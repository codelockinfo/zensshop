<?php
/**
 * Global Constants
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// 1. Detect environment and set CURRENT_URL (host + path without protocol)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cleanHost = explode(':', $rawHost)[0]; // Remove port (e.g. localhost:8080 -> localhost)
$projectDir = basename(dirname(__DIR__));

if (strpos($cleanHost, 'localhost') !== false || strpos($cleanHost, '127.0.0.1') !== false) {
    // Local dev: match localhost/project-folder
    define('SITE_URL', $protocol . $rawHost . '/' . $projectDir);
    $currentUrl = $cleanHost . '/' . $projectDir;
} else {
    // Production or Custom Host
    // Check if we are running from a subdirectory that matches projectDir
    if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/' . $projectDir . '/') === 0) {
        // We are in a subdirectory (e.g. homeprox.in/zensshop/...)
        define('SITE_URL', $protocol . $cleanHost . '/' . $projectDir);
        $currentUrl = $cleanHost . '/' . $projectDir;
    } else {
        // We are at root (e.g. homeprox.in/...)
        define('SITE_URL', $protocol . $cleanHost);
        $currentUrl = $cleanHost;
    }
}

// 2. Detect Store ID from database using the CURRENT_URL
require_once __DIR__ . '/../classes/Database.php';
try {
    $db = Database::getInstance();
    $storeResult = $db->fetchOne("SELECT store_id FROM users WHERE store_url = ? LIMIT 1", [$currentUrl]);
    
    // Fallback: if not found, try without path (backward compatibility)
    if (!$storeResult) {
        $storeResult = $db->fetchOne("SELECT store_id FROM users WHERE store_url = ? LIMIT 1", [$cleanHost]);
    }
    
    define('CURRENT_STORE_ID', $storeResult['store_id'] ?? 'DEFAULT');
    error_log("Store Detected: " . CURRENT_STORE_ID . " for URL: " . $currentUrl);
} catch (Exception $e) {
    define('CURRENT_STORE_ID', 'DEFAULT');
    error_log("Store detection ERROR: " . $e->getMessage());
}

define('BASE_PATH', dirname(__DIR__));

// Paths
define('UPLOAD_PATH', BASE_PATH . '/assets/images/products/');
define('UPLOAD_URL', SITE_URL . '/assets/images/products/');

// Cart Cookie
define('CART_COOKIE_NAME', 'cart_items');
define('CART_COOKIE_EXPIRY', 2592000); // 30 days

// Wishlist Cookie
define('WISHLIST_COOKIE_NAME', 'wishlist_items');
define('WISHLIST_COOKIE_EXPIRY', 2592000); // 30 days

// Retry Configuration
define('MAX_RETRY_ATTEMPTS', 2);
define('RETRY_DELAY_SECONDS', 2); // Base delay for exponential backoff

// OTP Configuration - Now managed in database settings (see admin/system-settings.php)
// OTP_EXPIRY_MINUTES and OTP_LENGTH are loaded from settings table
if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', 5);
}
if (!defined('OTP_LENGTH')) {
    define('OTP_LENGTH', 6);
}

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Admin Email (for error notifications)
define('ADMIN_EMAIL', 'admin@CookPro.com'); // Change this to your admin email

// API Configuration - Now managed in database settings (see admin/system-settings.php)
// Load Razorpay and Google API keys from database
require_once __DIR__ . '/../classes/Settings.php';
Settings::loadApiConfig();

