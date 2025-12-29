<?php
/**
 * Setup Wishlist Table
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/database/add_wishlist_table.sql');
    
    // Execute SQL
    $db->execute($sql);
    
    echo "Wishlist table created successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

