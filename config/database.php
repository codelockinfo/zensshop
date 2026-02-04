<?php
/**
 * Database Configuration
 * Automatically detects environment (local vs production)
 */

// Detect environment based on hostname
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = ($host === 'localhost' || $host === '127.0.0.1');

if ($isLocal) {
    // Local development environment
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'oecom_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Production settings (zensshop.kartoai.com & homeprox.in)
    define('DB_HOST', 'localhost');
    define('DB_PASS', 'Codelock@63');

    if (strpos($host, 'kartoai.com') !== false) {
        // KartoAI Subdomain (zensshop)
        define('DB_NAME', 'u402017191_zensshop');
        define('DB_USER', 'u402017191_zensshop');
    } else {
        // Main domain (homeprox.in / production)
        define('DB_NAME', 'u402017191_cookpro');
        define('DB_USER', 'u402017191_cookpro');
    }
}

define('DB_CHARSET', 'utf8mb4');
