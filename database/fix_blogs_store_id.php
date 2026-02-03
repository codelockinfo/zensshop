<?php
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Change store_id to VARCHAR to support alphanumeric IDs
    $sql = "ALTER TABLE blogs MODIFY COLUMN store_id VARCHAR(50) DEFAULT NULL";
    
    $db->execute($sql);
    echo "Successfully updated store_id column to VARCHAR(50).";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
