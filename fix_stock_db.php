<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // 1. Set 'out_of_stock' for products with quantity <= 0
    $result1 = $db->execute("UPDATE products SET stock_status = 'out_of_stock' WHERE stock_quantity <= 0");
    echo "Updated products to 'out_of_stock' where quantity <= 0. Rows affected: unknown (execute returns boolean or similar)\n";

    // 2. Set 'in_stock' for products with quantity > 0 (Optional, but good for consistency, unless 'on_backorder' is used)
    // We strictly want to fix the "0 quantity but showing in stock" issue.
    // So if it is 'in_stock' but quantity <= 0, it gets fixed by step 1.
    // If it is 'out_of_stock' but quantity > 0, should we fix it? Maybe not, maybe manually set.
    // But usually quantity > 0 implies in stock.
    $result2 = $db->execute("UPDATE products SET stock_status = 'in_stock' WHERE stock_quantity > 0 AND stock_status = 'out_of_stock'");
    echo "Updated products to 'in_stock' where quantity > 0 and status was 'out_of_stock'.\n";
    
    echo "Database stock consistency check complete.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
