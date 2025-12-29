<?php
/**
 * Setup Customers Table
 * Run this script to create the customers table
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
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'customers'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Customers table already exists\n";
        echo "Checking table structure...\n";
        
        // Check if it has the required columns
        $stmt = $pdo->query("DESCRIBE customers");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('email', $columns) && in_array('name', $columns)) {
            echo "✓ Table structure is correct\n";
        } else {
            echo "⚠ Table exists but structure may be different\n";
        }
    } else {
        echo "Creating customers table...\n";
        
        // Create customers table
        $sql = "CREATE TABLE `customers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `email` varchar(100) NOT NULL,
          `password` varchar(255),
          `phone` varchar(20),
          `billing_address` text,
          `shipping_address` text,
          `status` enum('active','inactive') DEFAULT 'active',
          `email_verified` tinyint(1) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`),
          KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✓ Customers table created successfully!\n";
    }
    
    echo "✓ Customers table created successfully!\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'customers'");
    $tableExists = $stmt->fetch();
    if ($tableExists) {
        echo "✓ Table verification successful!\n";
    }
    
    echo "\nSetup complete! You can now access the customer management pages.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

