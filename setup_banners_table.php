<?php
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Create banners table
    $sql = "CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        heading VARCHAR(255) NULL,
        subheading VARCHAR(255) NULL,
        link VARCHAR(255) NULL,
        button_text VARCHAR(255) DEFAULT 'Shop Now',
        image_desktop VARCHAR(255) NOT NULL,
        image_mobile VARCHAR(255) NULL,
        display_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->execute($sql);
    echo "Table 'banners' created successfully or already exists.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
