<?php
/**
 * Database Configuration
 * Automatically detects environment (local vs production)
 */

// Detect environment based on hostname
// If it's not localhost, it's production (handles temporary domains automatically)
$isProduction = (isset($_SERVER['HTTP_HOST']) && 
    $_SERVER['HTTP_HOST'] !== 'localhost' && 
    $_SERVER['HTTP_HOST'] !== '127.0.0.1');

if ($isProduction) {
    // Production environment (zensshop.kartoai.com)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u402017191_cookpro');
    define('DB_USER', 'u402017191_cookpro');
    define('DB_PASS', 'Codelock@63');
} else {
    // Local development environment
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'oecom_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

define('DB_CHARSET', 'utf8mb4');
