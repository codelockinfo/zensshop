<?php
require_once __DIR__ . '/classes/Database.php';

/**
 * Zensshop Database Update Script
 * This script is designed to be safe to run multiple times.
 * It checks for the existence of columns before adding them.
 */

$db = Database::getInstance();

echo "Starting database updates...\n";

// Function to safely add a column if it doesn't exist
function addColumnIfNotExists($table, $column, $definition) {
    global $db;
    try {
        $cols = $db->fetchAll("DESCRIBE `$table` ");
        $exists = false;
        foreach ($cols as $col) {
            if ($col['Field'] === $column) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            echo "Adding column '$column' to table '$table'...\n";
            $db->execute("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "Column '$column' added successfully.\n";
        } else {
            echo "Column '$column' already exists in table '$table'. skipping.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/adding column '$column' to '$table': " . $e->getMessage() . "\n";
    }
}

// Function to safely create a table if it doesn't exist
function createTableIfNotExists($tableName, $schema) {
    global $db;
    try {
        // Simple check: Try select 1 limit 0. If fails, table likely doesn't exist.
        // Better: SHOW TABLES LIKE ...
        $check = $db->fetchAll("SHOW TABLES LIKE ?", [$tableName]);
        if (empty($check)) {
            echo "Creating table '$tableName'...\n";
            $db->execute("CREATE TABLE `$tableName` ($schema) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            echo "Table '$tableName' created successfully.\n";
            
            // customized initialization for settings tables
            if (strpos($tableName, '_settings') !== false) {
                 $db->execute("INSERT INTO `$tableName` (id, heading) VALUES (1, 'Default Heading')");
            }
        } else {
             echo "Table '$tableName' already exists. skipping creation.\n";
        }
    } catch (Exception $e) {
        echo "Error creating table '$tableName': " . $e->getMessage() . "\n";
    }
}

// 1. Update section_categories table
addColumnIfNotExists('section_categories', 'heading', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists('section_categories', 'subheading', "TEXT DEFAULT NULL");
addColumnIfNotExists('section_categories', 'active', "TINYINT(1) DEFAULT 1");

// 2. Update section_best_selling_products table
addColumnIfNotExists('section_best_selling_products', 'heading', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists('section_best_selling_products', 'subheading', "TEXT DEFAULT NULL");

// 3. Update section_trending_products table
addColumnIfNotExists('section_trending_products', 'heading', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists('section_trending_products', 'subheading', "TEXT DEFAULT NULL");

// 4. Update categories table
addColumnIfNotExists('categories', 'banner', "VARCHAR(255) DEFAULT NULL AFTER image");
addColumnIfNotExists('categories', 'sort_order', "INT DEFAULT 0 AFTER status");

// 5. Update special_offers table (User requested ALTER instead of new table)
addColumnIfNotExists('special_offers', 'heading', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists('special_offers', 'subheading', "TEXT DEFAULT NULL");

// 6. Update section_videos table (User requested ALTER instead of new table)
addColumnIfNotExists('section_videos', 'heading', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists('section_videos', 'subheading', "TEXT DEFAULT NULL");

echo "Database updates completed.\n";
