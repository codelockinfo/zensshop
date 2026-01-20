<?php
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Create homepage_categories table
    $sql = "CREATE TABLE IF NOT EXISTS homepage_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        image VARCHAR(255) NOT NULL,
        link VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->execute($sql);
    echo "Table 'homepage_categories' created successfully or already exists.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
