<?php
require_once __DIR__ . '/classes/Database.php';
try {
    $db = Database::getInstance();
    $columns = $db->fetchAll("SHOW COLUMNS FROM products LIKE 'currency'");
    
    if (empty($columns)) {
        $db->execute("ALTER TABLE products ADD COLUMN currency VARCHAR(10) DEFAULT 'INR' AFTER price");
        echo "Added 'currency' column to products table.<br>";
    } else {
        echo "'currency' column already exists in products table.\n";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
