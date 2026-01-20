<?php
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Create customer_sessions table
    $sql = "CREATE TABLE IF NOT EXISTS customer_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        selector VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (selector),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->execute($sql);
    echo "Table 'customer_sessions' created successfully or already exists.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
