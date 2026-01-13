<?php
/**
 * Fix Database Paths Script
 * Run this once on your live server to remove hardcoded "/zensshop/" paths from database content.
 */

// Load Database
require_once __DIR__ . '/classes/Database.php';

// Disable error reporting for cleaner output
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h1>Starting Database Path Fix...</h1>";
    
    // 1. Fix site_settings (JSON fields often contain links)
    echo "<h3>1. Checking Site Settings...</h3>";
    $stmt = $conn->prepare("UPDATE site_settings SET setting_value = REPLACE(setting_value, '/zensshop/', '/') WHERE setting_value LIKE '%/zensshop/%'");
    $stmt->execute();
    echo "Updated " . $stmt->rowCount() . " rows in site_settings.<br>";
    
    // 2. Fix menu_items (Navigation links)
    echo "<h3>2. Checking Menu Items...</h3>";
    try {
        $stmt = $conn->prepare("UPDATE menu_items SET url = REPLACE(url, '/zensshop/', '/') WHERE url LIKE '%/zensshop/%'");
        $stmt->execute();
        echo "Updated " . $stmt->rowCount() . " rows in menu_items.<br>";
    } catch (Exception $e) {
        echo "Skipped menu_items (table might not exist or verify name).<br>";
    }
    
    // 3. Fix landing_pages (HTML content)
    echo "<h3>3. Checking Landing Pages...</h3>";
    try {
        $stmt = $conn->prepare("UPDATE landing_pages SET content = REPLACE(content, '/zensshop/', '/') WHERE content LIKE '%/zensshop/%'");
        $stmt->execute();
        echo "Updated " . $stmt->rowCount() . " rows in landing_pages content.<br>";
        
        // Also check banner_link or similar columns if they exist
        // Doing a generic column check might be complex, sticking to main content
    } catch (Exception $e) {
        echo "Skipped landing_pages usage.<br>";
    }

    echo "<h2>✅ Fix Complete!</h2>";
    echo "<p>Please delete this file (fix_db_paths.php) from your server now.</p>";
    echo "<p><a href='/'>Go to Homepage</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
