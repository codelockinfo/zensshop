<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

echo "Starting Product Category Fix...\n";

try {
    // 1. Find the database name
    $dbNameRes = $db->fetchOne("SELECT DATABASE() as dbname");
    $dbName = $dbNameRes['dbname'];
    echo "Database: $dbName\n";

    // 2. Find any foreign keys on products.category_id
    $fks = $db->fetchAll("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'products' 
        AND COLUMN_NAME = 'category_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [$dbName]);

    foreach ($fks as $fk) {
        $fkName = $fk['CONSTRAINT_NAME'];
        echo "Found Foreign Key: $fkName. Dropping it...\n";
        try {
            $db->execute("ALTER TABLE products DROP FOREIGN KEY $fkName");
            echo "Successfully dropped $fkName\n";
        } catch (Exception $e) {
            echo "Failed to drop $fkName: " . $e->getMessage() . "\n";
        }
    }

    // 3. Find and drop any indexes on category_id (since we're moving to TEXT)
    $indexes = $db->fetchAll("SHOW INDEX FROM products WHERE Column_name = 'category_id'");
    foreach ($indexes as $idx) {
        $idxName = $idx['Key_name'];
        if ($idxName !== 'PRIMARY') {
            echo "Found Index: $idxName. Dropping it...\n";
            try {
                $db->execute("DROP INDEX $idxName ON products");
                echo "Successfully dropped index $idxName\n";
            } catch (Exception $e) {
                echo "Note: Index drop might have failed if it was already dropped: " . $e->getMessage() . "\n";
            }
        }
    }

    // 4. Modify the column to TEXT
    echo "Modifying column 'category_id' to TEXT...\n";
    $db->execute("ALTER TABLE products MODIFY COLUMN category_id TEXT DEFAULT NULL");
    echo "Column 'category_id' is now TEXT.\n";

    // 5. Clean up product_categories table relationships
    // Make sure we use the 10-digit product_id wherever possible, but this script is just for the schema.
    
    echo "\nFix Complete! Please try saving the product categories again.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>
