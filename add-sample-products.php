<?php
/**
 * Add Sample Products to Database
 */

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Product.php';

$db = Database::getInstance();
$product = new Product();

echo "Adding sample products...\n\n";

// Get categories
$categories = $db->fetchAll("SELECT * FROM categories WHERE status = 'active' LIMIT 6");
if (empty($categories)) {
    echo "No categories found. Please run setup-database.php first.\n";
    exit;
}

// Sample products data
$sampleProducts = [
    [
        'name' => 'Rings Wrapped in 4 Rows',
        'description' => 'Elegant gold and silver intertwined ring design.',
        'category_id' => $categories[4]['id'] ?? null, // Rings
        'price' => 250.00,
        'sale_price' => null,
        'stock_quantity' => 50,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.5,
        'review_count' => 25,
        'images' => ['https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Ring in Platinum with Tanzanite',
        'description' => 'Stunning platinum ring featuring a large tanzanite center stone.',
        'category_id' => $categories[4]['id'] ?? null,
        'price' => 400.00,
        'sale_price' => null,
        'stock_quantity' => 30,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 5.0,
        'review_count' => 42,
        'images' => ['https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Gold Wire Bracelet in 18K',
        'description' => 'Beautiful 18K gold wire bracelet with elegant design.',
        'category_id' => $categories[3]['id'] ?? null, // Bracelets
        'price' => 280.00,
        'sale_price' => null,
        'stock_quantity' => 40,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.8,
        'review_count' => 18,
        'images' => ['https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Triple Drop Link Earrings',
        'description' => 'Elegant triple drop earrings with pearl accents.',
        'category_id' => $categories[1]['id'] ?? null, // Earrings
        'price' => 250.00,
        'sale_price' => 220.00,
        'stock_quantity' => 35,
        'stock_status' => 'in_stock',
        'gender' => 'female',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.7,
        'review_count' => 33,
        'images' => ['https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Olive Leaf Bypass Ring',
        'description' => 'Unique olive leaf design bypass ring in gold.',
        'category_id' => $categories[4]['id'] ?? null,
        'price' => 250.00,
        'sale_price' => 200.00,
        'stock_quantity' => 25,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.9,
        'review_count' => 28,
        'images' => ['https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Ring in Gold with Diamonds',
        'description' => 'Elegant gold ring with diamond accents and leaf design.',
        'category_id' => $categories[4]['id'] ?? null,
        'price' => 280.00,
        'sale_price' => null,
        'stock_quantity' => 45,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.6,
        'review_count' => 37,
        'images' => ['https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Double Pendant - 18K Gold',
        'description' => 'Delicate gold necklace with two small pendants.',
        'category_id' => $categories[2]['id'] ?? null, // Necklaces
        'price' => 320.00,
        'sale_price' => null,
        'stock_quantity' => 20,
        'stock_status' => 'in_stock',
        'gender' => 'female',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.8,
        'review_count' => 15,
        'images' => ['https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Gold Chain Bracelet',
        'description' => 'Classic gold chain bracelet with T-bar clasp.',
        'category_id' => $categories[3]['id'] ?? null,
        'price' => 180.00,
        'sale_price' => null,
        'stock_quantity' => 60,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.5,
        'review_count' => 22,
        'images' => ['https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Pearl Stud Earrings',
        'description' => 'Classic pearl stud earrings in gold setting.',
        'category_id' => $categories[1]['id'] ?? null,
        'price' => 150.00,
        'sale_price' => null,
        'stock_quantity' => 55,
        'stock_status' => 'in_stock',
        'gender' => 'female',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 0,
        'rating' => 4.4,
        'review_count' => 19,
        'images' => ['https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Diamond Tennis Bracelet',
        'description' => 'Elegant diamond tennis bracelet in white gold.',
        'category_id' => $categories[3]['id'] ?? null,
        'price' => 1200.00,
        'sale_price' => 1000.00,
        'stock_quantity' => 15,
        'stock_status' => 'in_stock',
        'gender' => 'unisex',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 5.0,
        'review_count' => 8,
        'images' => ['https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Rose Gold Hoop Earrings',
        'description' => 'Stylish rose gold hoop earrings with gemstone accents.',
        'category_id' => $categories[1]['id'] ?? null,
        'price' => 190.00,
        'sale_price' => null,
        'stock_quantity' => 38,
        'stock_status' => 'in_stock',
        'gender' => 'female',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.6,
        'review_count' => 26,
        'images' => ['https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=600&fit=crop']
    ],
    [
        'name' => 'Emerald Pendant Necklace',
        'description' => 'Beautiful emerald pendant on a delicate gold chain.',
        'category_id' => $categories[2]['id'] ?? null,
        'price' => 450.00,
        'sale_price' => 380.00,
        'stock_quantity' => 12,
        'stock_status' => 'in_stock',
        'gender' => 'female',
        'brand' => 'CookPro',
        'status' => 'active',
        'featured' => 1,
        'rating' => 4.9,
        'review_count' => 14,
        'images' => ['https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=600&h=600&fit=crop']
    ]
];

$added = 0;
$errors = 0;

foreach ($sampleProducts as $item) {
    try {
        // Generate slug
        $slug = strtolower(trim($item['name']));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while ($db->fetchOne("SELECT id FROM products WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        // Prepare images
        $imagesJson = json_encode($item['images']);
        $featuredImage = $item['images'][0] ?? null;
        
        // Insert product
        $productId = $db->insert(
            "INSERT INTO products 
            (name, slug, description, category_id, price, sale_price, stock_quantity, stock_status, 
             images, featured_image, gender, brand, status, featured, rating, review_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $item['name'],
                $slug,
                $item['description'],
                $item['category_id'],
                $item['price'],
                $item['sale_price'],
                $item['stock_quantity'],
                $item['stock_status'],
                $imagesJson,
                $featuredImage,
                $item['gender'],
                $item['brand'],
                $item['status'],
                $item['featured'],
                $item['rating'],
                $item['review_count']
            ]
        );
        
        echo "✓ Added: {$item['name']} (ID: $productId)\n";
        $added++;
        
    } catch (Exception $e) {
        echo "✗ Error adding {$item['name']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "========================================\n";
echo "Sample products added!\n";
echo "========================================\n";
echo "Added: $added products\n";
if ($errors > 0) {
    echo "Errors: $errors\n";
}
echo "\n";


