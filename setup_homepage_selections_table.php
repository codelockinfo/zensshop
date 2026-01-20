<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Table 1: Best Selling
    $sql1 = "CREATE TABLE IF NOT EXISTS home_best_selling_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_product (product_id)
    )";
    $db->execute($sql1);
    echo "Table 'home_best_selling_products' created successfully.<br>";

    // Table 2: Trending
    $sql2 = "CREATE TABLE IF NOT EXISTS home_trending_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_product (product_id)
    )";
    $db->execute($sql2);
    echo "Table 'home_trending_products' created successfully.<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
