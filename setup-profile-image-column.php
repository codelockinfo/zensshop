<?php
/**
 * Setup Profile Image Column
 * Run this script to add profile_image column to users table
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
    
    echo "Adding profile_image column to users table...\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✓ Profile image column already exists\n";
    } else {
        // Add profile_image column
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `profile_image` varchar(255) DEFAULT NULL AFTER `email`");
        echo "✓ Profile image column added successfully!\n";
    }
    
    echo "\nSetup complete!\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✓ Column already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


