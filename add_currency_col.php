<?php
require_once __DIR__ . '/classes/Database.php';
try {
    $db = Database::getInstance();
    
    // Check if column exists
    $columns = $db->fetchAll("SHOW COLUMNS FROM products LIKE 'currency'");
    
    if (empty($columns)) {
        // Add currency column
        $db->execute("ALTER TABLE products ADD COLUMN currency VARCHAR(10) DEFAULT 'INR' AFTER price");
        echo "Added 'currency' column to products table.<br>";
    } else {
        echo "'currency' column already exists in products table.\n";
    }
    
    // Also check if we need to update product_variants table?
    // Variants usually inherit currency from product, but sometimes might differ?
    // For now, let's keep it simple on the main product.
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
