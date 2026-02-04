<?php
/**
 * Database Configuration
 * Automatically detects environment (local vs production)
 */

// Detect environment based on hostname
$isProduction = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kartoai.com') !== false);

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
