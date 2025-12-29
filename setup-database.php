<?php
/**
 * Database Setup Script
 * Run this file once to create the database and tables
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'oecom_db';

echo "Starting database setup...\n\n";

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database first
    echo "Creating database '$db_name'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created/verified\n\n";
    
    // Select database
    $pdo->exec("USE `$db_name`");
    echo "Using database '$db_name'\n\n";
    
    // Set SQL mode and timezone
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    $pdo->exec("SET time_zone = '+00:00'");
    
    // Drop existing tables if they exist (in reverse order of dependencies)
    echo "Cleaning up existing tables...\n";
    $tables = ['order_items', 'cart', 'orders', 'products', 'password_resets', 'discounts', 'categories', 'users'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            // Ignore errors
        }
    }
    echo "✓ Cleanup complete\n\n";
    
    // Create tables
    echo "Creating tables...\n\n";
    
    // Users Table
    echo "Creating users table...\n";
    $pdo->exec("CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` enum('admin','manager') DEFAULT 'admin',
      `status` enum('active','inactive') DEFAULT 'active',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Users table created\n";
    
    // Categories Table
    echo "Creating categories table...\n";
    $pdo->exec("CREATE TABLE `categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `slug` varchar(100) NOT NULL,
      `description` text,
      `image` varchar(255),
      `parent_id` int(11) DEFAULT NULL,
      `sort_order` int(11) DEFAULT 0,
      `status` enum('active','inactive') DEFAULT 'active',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `slug` (`slug`),
      KEY `parent_id` (`parent_id`),
      FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Categories table created\n";
    
    // Products Table
    echo "Creating products table...\n";
    $pdo->exec("CREATE TABLE `products` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `slug` varchar(255) NOT NULL,
      `sku` varchar(100),
      `description` text,
      `short_description` text,
      `category_id` int(11) DEFAULT NULL,
      `price` decimal(10,2) NOT NULL DEFAULT 0.00,
      `sale_price` decimal(10,2) DEFAULT NULL,
      `stock_quantity` int(11) DEFAULT 0,
      `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
      `images` text COMMENT 'JSON array of image URLs',
      `featured_image` varchar(255),
      `gender` enum('male','female','unisex') DEFAULT 'unisex',
      `brand` varchar(100),
      `weight` decimal(8,2) DEFAULT NULL,
      `dimensions` varchar(100) DEFAULT NULL,
      `rating` decimal(3,2) DEFAULT 0.00,
      `review_count` int(11) DEFAULT 0,
      `status` enum('active','inactive','draft') DEFAULT 'draft',
      `featured` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `slug` (`slug`),
      UNIQUE KEY `sku` (`sku`),
      KEY `category_id` (`category_id`),
      KEY `status` (`status`),
      KEY `featured` (`featured`),
      FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Products table created\n";
    
    // Orders Table
    echo "Creating orders table...\n";
    $pdo->exec("CREATE TABLE `orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_number` varchar(50) NOT NULL,
      `user_id` int(11) DEFAULT NULL,
      `customer_name` varchar(100) NOT NULL,
      `customer_email` varchar(100) NOT NULL,
      `customer_phone` varchar(20),
      `billing_address` text NOT NULL,
      `shipping_address` text NOT NULL,
      `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
      `discount_amount` decimal(10,2) DEFAULT 0.00,
      `shipping_amount` decimal(10,2) DEFAULT 0.00,
      `tax_amount` decimal(10,2) DEFAULT 0.00,
      `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
      `payment_method` varchar(50),
      `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
      `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
      `tracking_number` varchar(100),
      `notes` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `order_number` (`order_number`),
      KEY `user_id` (`user_id`),
      KEY `order_status` (`order_status`),
      KEY `payment_status` (`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Orders table created\n";
    
    // Order Items Table
    echo "Creating order_items table...\n";
    $pdo->exec("CREATE TABLE `order_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `product_name` varchar(255) NOT NULL,
      `product_sku` varchar(100),
      `quantity` int(11) NOT NULL DEFAULT 1,
      `price` decimal(10,2) NOT NULL,
      `subtotal` decimal(10,2) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `order_id` (`order_id`),
      KEY `product_id` (`product_id`),
      FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Order items table created\n";
    
    // Discounts Table
    echo "Creating discounts table...\n";
    $pdo->exec("CREATE TABLE `discounts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `code` varchar(50) NOT NULL,
      `name` varchar(100) NOT NULL,
      `description` text,
      `type` enum('percentage','fixed') DEFAULT 'percentage',
      `value` decimal(10,2) NOT NULL,
      `min_purchase_amount` decimal(10,2) DEFAULT NULL,
      `max_discount_amount` decimal(10,2) DEFAULT NULL,
      `usage_limit` int(11) DEFAULT NULL,
      `used_count` int(11) DEFAULT 0,
      `start_date` datetime DEFAULT NULL,
      `end_date` datetime DEFAULT NULL,
      `status` enum('active','inactive','expired') DEFAULT 'active',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `code` (`code`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Discounts table created\n";
    
    // Cart Table
    echo "Creating cart table...\n";
    $pdo->exec("CREATE TABLE `cart` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `session_id` varchar(100),
      `product_id` int(11) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `session_id` (`session_id`),
      KEY `product_id` (`product_id`),
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Cart table created\n";
    
    // Password Resets Table
    echo "Creating password_resets table...\n";
    $pdo->exec("CREATE TABLE `password_resets` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `email` varchar(100) NOT NULL,
      `otp` varchar(10) NOT NULL,
      `expires_at` timestamp NOT NULL,
      `used` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `email` (`email`),
      KEY `otp` (`otp`),
      KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Password resets table created\n";
    
    // Insert default admin user (password: admin123)
    echo "\nInserting default admin user...\n";
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, 'admin', 'active')");
    $stmt->execute(['Admin User', 'admin@milano.com', $hashedPassword]);
    echo "✓ Default admin user created\n";
    
    // Insert sample categories
    echo "\nInserting sample categories...\n";
    $categories = [
        ['Jewelry Sets', 'jewelry-sets', 'Complete jewelry sets', 1],
        ['Earrings', 'earrings', 'Earrings collection', 2],
        ['Necklaces', 'necklaces', 'Necklaces collection', 3],
        ['Bracelets', 'bracelets', 'Bracelets collection', 4],
        ['Rings', 'rings', 'Rings collection', 5],
        ['Best Sellers', 'best-sellers', 'Best selling products', 6]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `sort_order`, `status`) VALUES (?, ?, ?, ?, 'active')");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }
    echo "✓ Sample categories inserted\n";
    
    echo "\n";
    echo "========================================\n";
    echo "Database setup completed successfully!\n";
    echo "========================================\n";
    echo "Database: $db_name\n";
    echo "Tables created: 8\n";
    echo "\n";
    echo "Default admin credentials:\n";
    echo "Email: admin@milano.com\n";
    echo "Password: admin123\n";
    echo "\n";
    echo "You can now access:\n";
    echo "- Frontend: http://localhost/oecom/\n";
    echo "- Admin: http://localhost/oecom/admin/\n";
    echo "\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. MySQL/MariaDB is running\n";
    echo "2. Database credentials in config/database.php\n";
    echo "3. User has CREATE DATABASE privileges\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
