<?php
/**
 * Add custom_classes column to menu_items table
 * This allows storing custom CSS classes and configurations for menu items
 */

require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    echo "Adding custom_classes column to menu_items table...\n";
    
    // Check if column already exists
    $columns = $db->fetchAll("SHOW COLUMNS FROM menu_items LIKE 'custom_classes'");
    
    if (empty($columns)) {
        // Add custom_classes column
        $db->execute("ALTER TABLE menu_items ADD COLUMN custom_classes TEXT DEFAULT NULL AFTER badge_text");
        echo "✓ Added custom_classes column\n";
    } else {
        echo "✓ custom_classes column already exists\n";
    }
    
    echo "\nSchema update completed successfully!\n";
    echo "\nYou can now store custom CSS classes for menu items in the database.\n";
    echo "Example: 'text-black hover:text-red-700 transition font-sans text-md nav-link flex items-center'\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
