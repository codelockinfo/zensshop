<?php
/**
 * Global Constants
 */

// Site Configuration
define('SITE_NAME', 'Milano');
define('SITE_URL', 'http://localhost/oecom');
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

