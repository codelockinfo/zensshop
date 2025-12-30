<?php
/**
 * Debug Script - Check for errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "\n";
echo "Error Reporting: ON\n\n";

try {
    echo "1. Testing database connection...\n";
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    echo "   ✓ Database connection OK\n\n";
    
    echo "2. Testing Cart class...\n";
    require_once __DIR__ . '/classes/Cart.php';
    $cart = new Cart();
    echo "   ✓ Cart class loaded\n\n";
    
    echo "3. Testing header include...\n";
    $pageTitle = 'Test';
    ob_start();
    require_once __DIR__ . '/includes/header.php';
    $header = ob_get_clean();
    echo "   ✓ Header loaded\n\n";
    
    echo "4. Testing index.php...\n";
    ob_start();
    require_once __DIR__ . '/index.php';
    $index = ob_get_clean();
    echo "   ✓ Index page loaded\n\n";
    
    echo "========================================\n";
    echo "All tests passed! No errors found.\n";
    echo "========================================\n";
    
} catch (Throwable $e) {
    echo "\n❌ ERROR FOUND:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}


