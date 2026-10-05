<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

try {
    echo "Adding delivery_date column to orders table...\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM orders LIKE 'delivery_date'");
    
    if (empty($columns)) {
        $db->execute("ALTER TABLE orders ADD COLUMN delivery_date DATE DEFAULT NULL AFTER tracking_number");
        echo "✓ Added delivery_date column\n";
    } else {
        echo "✓ delivery_date column already exists\n";
    }
    
    
    echo "Backfilling existing active orders...\n";
    $db->execute("UPDATE orders SET delivery_date = DATE_ADD(created_at, INTERVAL 3 DAY) WHERE delivery_date IS NULL");
    
    echo "\nSchema update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
