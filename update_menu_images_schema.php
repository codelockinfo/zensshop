<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

try {
    echo "Updating menu_items table schema...\n";

    // Add image_path column
    try {
        $db->query("ALTER TABLE menu_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
        echo "Added 'image_path' column.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "'image_path' column already exists.\n";
        } else {
            echo "Error adding 'image_path': " . $e->getMessage() . "\n";
        }
    }

    // Add badge_text column
    try {
        $db->query("ALTER TABLE menu_items ADD COLUMN badge_text VARCHAR(50) DEFAULT NULL");
        echo "Added 'badge_text' column.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "'badge_text' column already exists.\n";
        } else {
            echo "Error adding 'badge_text': " . $e->getMessage() . "\n";
        }
    }
    
    // Add badge_color column (optional, for customization like Red/Blue)
    try {
        $db->query("ALTER TABLE menu_items ADD COLUMN badge_color VARCHAR(20) DEFAULT 'red'");
        echo "Added 'badge_color' column.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "'badge_color' column already exists.\n";
        } else {
            echo "Error adding 'badge_color': " . $e->getMessage() . "\n";
        }
    }

    echo "Schema update complete.\n";

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
