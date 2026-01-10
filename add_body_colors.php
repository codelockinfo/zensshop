<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add body_bg_color column
    $db->execute("ALTER TABLE landing_pages ADD COLUMN body_bg_color VARCHAR(7) DEFAULT '#ffffff' AFTER theme_color");
    echo "Added body_bg_color column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "body_bg_color column already exists.\n";
    } else {
        echo "Error adding body_bg_color: " . $e->getMessage() . "\n";
    }
}

try {
    // Add body_text_color column
    $db->execute("ALTER TABLE landing_pages ADD COLUMN body_text_color VARCHAR(7) DEFAULT '#000000' AFTER body_bg_color");
    echo "Added body_text_color column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "body_text_color column already exists.\n";
    } else {
        echo "Error adding body_text_color: " . $e->getMessage() . "\n";
    }
}
?>
