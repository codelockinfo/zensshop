<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

$sql = "CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255),
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    google_id VARCHAR(255),
    billing_address TEXT,
    shipping_address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $db->execute($sql);
    echo "Customers table created or already exists.\n";
} catch (Exception $e) {
    echo "Error creating customers table: " . $e->getMessage() . "\n";
}
