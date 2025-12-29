<?php
/**
 * Error Check - Enable error display for web access
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set error log path
ini_set('error_log', __DIR__ . '/error.log');

echo "Error reporting enabled. Access your page now and check for errors.\n";
echo "Error log location: " . __DIR__ . "/error.log\n";

