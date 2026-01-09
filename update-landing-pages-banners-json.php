<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add banner_sections_json column for multiple banner support
    $query = "ADD COLUMN `banner_sections_json` json DEFAULT NULL AFTER `banner_btn_link`";
    
    try {
        $db->execute("ALTER TABLE `landing_pages` $query");
        echo "Added column: banner_sections_json<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "Column banner_sections_json already exists.<br>";
        } else {
             echo "Error: " . $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
