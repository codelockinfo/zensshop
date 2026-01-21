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

// Add more updates here as needed
// addColumnIfNotExists('some_other_table', 'new_col', "VARCHAR(255)...");

echo "Database updates completed.\n";
