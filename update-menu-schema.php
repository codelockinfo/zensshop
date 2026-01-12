<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

echo "Updating database schema for Menu Images and Dynamic Menus...\n";

try {
    // 1. Add image_path to menu_items
    $checkImg = $db->fetchOne("SHOW COLUMNS FROM menu_items LIKE 'image_path'");
    if (!$checkImg) {
        $db->execute("ALTER TABLE menu_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
        echo "Added 'image_path' to menu_items.\n";
    } else {
        echo "'image_path' already exists in menu_items.\n";
    }

    // 2. Allow NULL in location for menus table (to support custom menus not tied to theme locations)
    $db->execute("ALTER TABLE menus MODIFY location VARCHAR(50) DEFAULT NULL");
    echo "Updated 'menus' table to allow NULL locations.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Schema update complete.\n";
