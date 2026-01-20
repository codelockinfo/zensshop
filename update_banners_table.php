<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Check if column exists
    $columns = $db->fetchAll("SHOW COLUMNS FROM banners LIKE 'link_mobile'");
    if (empty($columns)) {
        $db->execute("ALTER TABLE banners ADD COLUMN link_mobile VARCHAR(255) DEFAULT '' AFTER link");
        echo "Added link_mobile column to banners table.<br>";
    } else {
        echo "link_mobile column already exists.<br>";
    }
    
    echo "Database update completed successfully.";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
