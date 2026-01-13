<?php
/**
 * Global Constants
 */

// Site Configuration - SITE_NAME is now managed in database settings (see admin/system-settings.php)
// Detect environment and set SITE_URL accordingly
$isProduction = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kartoai.com') !== false);

if ($isProduction) {
    // Production environment
    define('SITE_URL', 'https://zensshop.kartoai.com');
} else {
    // Local development environment
    $projectDir = basename(dirname(__DIR__));
    define('SITE_URL', 'http://localhost/' . $projectDir);
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
define('OTP_LENGTH', 6);

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Admin Email (for error notifications)
define('ADMIN_EMAIL', 'admin@milano.com'); // Change this to your admin email

// API Configuration - Now managed in database settings (see admin/system-settings.php)
// Load Razorpay and Google API keys from database
require_once __DIR__ . '/../classes/Settings.php';
Settings::loadApiConfig();

