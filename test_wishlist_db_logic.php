<?php
// Simple test script for Wishlist logic without browser interaction
require_once __DIR__ . '/classes/Wishlist.php';
require_once __DIR__ . '/classes/Database.php';

// Mock session
$_SESSION['customer_id'] = 1; // Assuming user ID 1 exists
$_SESSION['store_id'] = 'DEFAULT';

$wishlist = new Wishlist();
$db = Database::getInstance();

echo "=== Wishlist Logic Test ===\n";

// 1. Pick a product to test
// Get a real product from DB
$product = $db->fetchOne("SELECT product_id, name FROM products LIMIT 1");
if (!$product) {
    die("No products found in DB to test with.\n");
}
$testProductId = $product['product_id'];
echo "Testing with Product ID via DB: " . $testProductId . " (" . $product['name'] . ")\n";

// 2. Add to Wishlist
echo "\n--- ADDING ITEM ---\n";
try {
    $items = $wishlist->addItem($testProductId);
    echo "Added. Current count: " . count($items) . "\n";
    
    // Verify in DB
    $dbCheck = $db->fetchOne("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?", [1, $testProductId]);
    if ($dbCheck) {
        echo "✅ Verified in DB: Found entry for Product $testProductId\n";
    } else {
        echo "❌ FAILED: Not found in DB after add.\n";
    }
} catch (Exception $e) {
    echo "❌ Error adding: " . $e->getMessage() . "\n";
}

// 3. Remove from Wishlist
echo "\n--- REMOVING ITEM ---\n";
try {
    $items = $wishlist->removeItem($testProductId);
    echo "Removed. Current count: " . count($items) . "\n";
    
    // Verify in DB
    $dbCheck = $db->fetchOne("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?", [1, $testProductId]);
    if (!$dbCheck) {
        echo "✅ Verified in DB: Entry is GONE for Product $testProductId\n";
    } else {
        echo "❌ FAILED: Still found in DB after remove!\n";
    }
} catch (Exception $e) {
    echo "❌ Error removing: " . $e->getMessage() . "\n";
}
