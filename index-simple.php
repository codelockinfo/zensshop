<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Step by Step</h1>";

echo "<p>Step 1: PHP is working</p>";

try {
    echo "<p>Step 2: Loading Database class...</p>";
    require_once __DIR__ . '/classes/Database.php';
    echo "<p style='color:green'>✓ Database class loaded</p>";
    
    echo "<p>Step 3: Testing database connection...</p>";
    $db = Database::getInstance();
    echo "<p style='color:green'>✓ Database connected</p>";
    
    echo "<p>Step 4: Loading Cart class...</p>";
    require_once __DIR__ . '/classes/Cart.php';
    echo "<p style='color:green'>✓ Cart class loaded</p>";
    
    echo "<p>Step 5: Creating Cart instance...</p>";
    $cart = new Cart();
    echo "<p style='color:green'>✓ Cart created</p>";
    
    echo "<p>Step 6: Getting cart count...</p>";
    $cartCount = $cart->getCount();
    echo "<p style='color:green'>✓ Cart count: $cartCount</p>";
    
    echo "<p>Step 7: Testing header file...</p>";
    if (file_exists(__DIR__ . '/includes/header.php')) {
        echo "<p style='color:green'>✓ Header file exists</p>";
    } else {
        echo "<p style='color:red'>✗ Header file NOT found</p>";
    }
    
    echo "<h2 style='color:green'>All steps passed! The issue might be in header.php</h2>";
    
} catch (Throwable $e) {
    echo "<h2 style='color:red'>ERROR FOUND:</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

