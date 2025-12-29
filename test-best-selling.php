<?php
require_once __DIR__ . '/classes/Product.php';

$product = new Product();
$products = $product->getBestSelling(12);

echo "<h1>Best Selling Products Test</h1>";
echo "<p>Found " . count($products) . " products</p>";

if (empty($products)) {
    echo "<p style='color:red'>No products found! Make sure products are added and have status='active'</p>";
} else {
    echo "<ul>";
    foreach ($products as $p) {
        echo "<li>{$p['name']} - Rating: {$p['rating']}, Reviews: {$p['review_count']}, Status: {$p['status']}</li>";
    }
    echo "</ul>";
}

