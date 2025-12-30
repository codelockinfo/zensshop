<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Test Page</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

echo "<h2>Testing Includes:</h2>";

try {
    echo "<p>1. Testing Database class...</p>";
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    echo "<p style='color:green'>✓ Database connection OK</p>";
    
    echo "<p>2. Testing Cart class...</p>";
    require_once __DIR__ . '/classes/Cart.php';
    $cart = new Cart();
    echo "<p style='color:green'>✓ Cart class OK</p>";
    
    echo "<p>3. Testing header path...</p>";
    $headerPath = __DIR__ . '/includes/header.php';
    if (file_exists($headerPath)) {
        echo "<p style='color:green'>✓ Header file exists</p>";
    } else {
        echo "<p style='color:red'>✗ Header file NOT found at: $headerPath</p>";
    }
    
    echo "<h2>All tests passed!</h2>";
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}


