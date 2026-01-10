<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add banner_bg_color column
    $db->execute("ALTER TABLE landing_pages ADD COLUMN banner_bg_color VARCHAR(7) DEFAULT '#ffffff' AFTER hero_text_color");
    echo "Added banner_bg_color column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "banner_bg_color column already exists.\n";
    } else {
        echo "Error adding banner_bg_color: " . $e->getMessage() . "\n";
    }
}

try {
    // Add banner_text_color column
    $db->execute("ALTER TABLE landing_pages ADD COLUMN banner_text_color VARCHAR(7) DEFAULT '#000000' AFTER banner_bg_color");
    echo "Added banner_text_color column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "banner_text_color column already exists.\n";
    } else {
        echo "Error adding banner_text_color: " . $e->getMessage() . "\n";
    }
}
?>
