<?php
/**
 * Setup Product Categories Junction Table
 * Run this script to create the product_categories table for many-to-many relationship
 */

require_once __DIR__ . '/config/database.php';

try {
    // Connect directly using PDO
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "Creating product_categories junction table...\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_categories'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Product categories table already exists\n";
    } else {
        // Create product_categories table
        $sql = "CREATE TABLE `product_categories` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) NOT NULL,
          `category_id` int(11) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_product_category` (`product_id`, `category_id`),
          KEY `product_id` (`product_id`),
          KEY `category_id` (`category_id`),
          FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
          FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✓ Product categories table created successfully!\n";
        
        // Migrate existing category_id data
        echo "Migrating existing category relationships...\n";
        $migrateSql = "INSERT INTO `product_categories` (`product_id`, `category_id`)
                       SELECT `id`, `category_id` 
                       FROM `products` 
                       WHERE `category_id` IS NOT NULL
                       ON DUPLICATE KEY UPDATE `product_id` = `product_id`";
        $pdo->exec($migrateSql);
        echo "✓ Migration complete!\n";
    }
    
    echo "\nSetup complete!\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "✓ Table already exists or migration already completed\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


