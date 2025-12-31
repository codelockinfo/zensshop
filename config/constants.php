<?php
/**
 * Global Constants
 */

// Site Configuration
define('SITE_NAME', 'Milano');
// Always use actual file system directory name (zensshop)
// This ensures SITE_URL is always /zensshop/ regardless of access path
$projectDir = basename(dirname(__DIR__));
define('SITE_URL', 'http://localhost/' . $projectDir);
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

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 15);
define('OTP_LENGTH', 6);

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Admin Email (for error notifications)
define('ADMIN_EMAIL', 'admin@milano.com'); // Change this to your admin email

// Razorpay Configuration
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_RfbZw5apB4THcH');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'CEpjpNKALClK7tuKFf20D9VM');
define('RAZORPAY_MODE', getenv('RAZORPAY_MODE') ?: 'test'); // 'test' or 'live'

