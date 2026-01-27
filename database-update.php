<?php
/**
 * Database Update Script - FINAL VERSION
 * Run this file ONCE on your production server to apply all schema changes
 * 
 * IMPORTANT: Backup your database before running this script!
 * 
 * This script will:
 * 1. Add variant_attributes column to cart and order_items tables
 * 2. Update product_id columns to BIGINT to support 10-digit IDs
 * 3. Update user_id columns to BIGINT for future scalability
 * 4. FIX Foreign Key Constraints (Drop old incorrect ones, Add new correct ones)
 * 5. Handle all schema migrations safely
 */

require_once __DIR__ . '/classes/Database.php';

// Set to true to actually execute the changes, false for dry-run
$EXECUTE = true;

$db = Database::getInstance();
$errors = [];
$success = [];

echo "=== ZENSSHOP DATABASE UPDATE SCRIPT (FINAL) ===\n";
echo "Execution Mode: " . ($EXECUTE ? "LIVE" : "DRY-RUN") . "\n";
echo "================================================\n\n";

/**
 * Helper function to execute SQL with error handling
 */
function executeSql($db, $sql, $description, &$errors, &$success, $execute = false) {
    echo "[$description]\n";
    echo "SQL: $sql\n";
    
    if (!$execute) {
        echo "Status: SKIPPED (dry-run)\n\n";
        return true;
    }
    
    try {
        $db->execute($sql);
        $success[] = $description;
        echo "Status: ✅ SUCCESS\n\n";
        return true;
    } catch (Exception $e) {
        // Warning if duplicate column/key, but continue
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate foreign key') !== false) {
            echo "Status: ⚠️ EXISTS - " . $e->getMessage() . "\n\n";
            return true;
        }
        $errors[] = "$description: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n\n";
        return false;
    }
}

/**
 * Helper to drop Foreign Key if it exists
 */
function dropForeignKeyIfExists($db, $table, $constraintName, &$errors, &$success, $execute) {
    echo "[Drop FK $constraintName from $table]\n";
    if (!$execute) {
        echo "Status: SKIPPED (dry-run)\n\n";
        return;
    }
    try {
        // Check if exists
        $check = $db->fetchOne("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ? 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'", 
            [$table, $constraintName]
        );
        
        if ($check) {
            $db->execute("ALTER TABLE `$table` DROP FOREIGN KEY `$constraintName`");
            $success[] = "Dropped FK $constraintName from $table";
            echo "Status: ✅ DROPPED\n\n";
        } else {
            echo "Status: NOT FOUND (Skipped)\n\n";
        }
    } catch (Exception $e) {
        $errors[] = "Drop FK $constraintName: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n\n";
    }
}

/**
 * Helper function to check if column exists
 */
function columnExists($db, $table, $column) {
    try {
        $result = $db->fetchOne("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

// Disable foreign key checks temporarily
executeSql($db, "SET FOREIGN_KEY_CHECKS = 0", "Disable foreign key checks", $errors, $success, $EXECUTE);

// ==========================================
// STEP 1: Add variant_attributes columns
// ==========================================
echo "STEP 1: Adding/Checking variant_attributes columns\n";
echo "------------------------------------------------\n";

if (!columnExists($db, 'cart', 'variant_attributes')) {
    executeSql($db, "ALTER TABLE cart ADD COLUMN variant_attributes JSON DEFAULT NULL COMMENT 'Selected product variant attributes'", "Add variant_attributes to cart", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'variant_attributes')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN variant_attributes JSON DEFAULT NULL COMMENT 'Selected product variant at time of order'", "Add variant_attributes to order_items", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 2: Drop Old Incorrect Foreign Keys
// ==========================================
echo "STEP 2: Dropping Old Foreign Keys (if they exist)\n";
echo "-------------------------------------------------\n";

// Cart Table - Drop known old keys (cart_ibfk_2 usually points to products.id)
dropForeignKeyIfExists($db, 'cart', 'cart_ibfk_2', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'cart', 'fk_cart_customer', $errors, $success, $EXECUTE); // Drop to recreate properly

// Wishlist Table
dropForeignKeyIfExists($db, 'wishlist', 'wishlist_ibfk_2', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'wishlist', 'wishlist_customer_fk', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'wishlist', 'fk_wishlist_product_id', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'wishlist', 'fk_wishlist_customer', $errors, $success, $EXECUTE);

// Explicitly drop the new ones too for a clean retry if needed
dropForeignKeyIfExists($db, 'cart', 'fk_cart_product_id', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'cart', 'fk_cart_customer', $errors, $success, $EXECUTE);

// Order Items Table
dropForeignKeyIfExists($db, 'order_items', 'order_items_ibfk_1', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'order_items', 'order_items_ibfk_2', $errors, $success, $EXECUTE);
dropForeignKeyIfExists($db, 'order_items', 'fk_order_items_product_id', $errors, $success, $EXECUTE); // For idempotency

// ==========================================
// STEP 3: Update Column Types (BIGINT)
// ==========================================
echo "STEP 3: Updating IDs to BIGINT\n";
echo "------------------------------\n";

$tablesToUpdate = [
    'products' => ['product_id'],
    'product_variants' => ['product_id'],
    'cart' => ['product_id', 'user_id'],
    'wishlist' => ['product_id', 'user_id'],
    'order_items' => ['product_id'],
    'customers' => ['id'] // Ensure customer ID is big enough if referenced
];

executeSql($db, "ALTER TABLE products MODIFY COLUMN product_id BIGINT NOT NULL", "Modify products.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE product_variants MODIFY COLUMN product_id BIGINT NOT NULL", "Modify product_variants.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE customers MODIFY COLUMN id BIGINT NOT NULL AUTO_INCREMENT", "Modify customers.id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE cart MODIFY COLUMN product_id BIGINT NOT NULL", "Modify cart.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE cart MODIFY COLUMN user_id BIGINT NOT NULL", "Modify cart.user_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE wishlist MODIFY COLUMN product_id BIGINT NOT NULL", "Modify wishlist.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE wishlist MODIFY COLUMN user_id BIGINT NOT NULL", "Modify wishlist.user_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE order_items MODIFY COLUMN product_id BIGINT NOT NULL", "Modify order_items.product_id to BIGINT", $errors, $success, $EXECUTE);

// Ensure products.product_id has a UNIQUE index (required for FK reference)
executeSql($db, "ALTER TABLE products ADD UNIQUE INDEX IF NOT EXISTS idx_unique_product_id (product_id)", "Ensure UNIQUE index on products.product_id", $errors, $success, $EXECUTE);

// ==========================================
// STEP 4: Re-Add Correct Foreign Keys
// ==========================================
echo "STEP 4: Adding Correct Foreign Keys\n";
echo "-----------------------------------\n";

// Cart: Product FK -> products(product_id)
executeSql(
    $db,
    "ALTER TABLE cart ADD CONSTRAINT fk_cart_product_id FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE",
    "Add FK cart.product_id -> products.product_id",
    $errors,
    $success,
    $EXECUTE
);

// Cart: Customer FK -> customers(id)
executeSql(
    $db,
    "ALTER TABLE cart ADD CONSTRAINT fk_cart_customer FOREIGN KEY (user_id) REFERENCES customers(id) ON DELETE CASCADE",
    "Add FK cart.user_id -> customers.id",
    $errors,
    $success,
    $EXECUTE
);

// Wishlist: Product FK -> products(product_id)
executeSql(
    $db,
    "ALTER TABLE wishlist ADD CONSTRAINT fk_wishlist_product_id FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE",
    "Add FK wishlist.product_id -> products.product_id",
    $errors,
    $success,
    $EXECUTE
);

// Wishlist: Customer FK -> customers(id)
executeSql(
    $db,
    "ALTER TABLE wishlist ADD CONSTRAINT fk_wishlist_customer FOREIGN KEY (user_id) REFERENCES customers(id) ON DELETE CASCADE",
    "Add FK wishlist.user_id -> customers.id",
    $errors,
    $success,
    $EXECUTE
);

// Order Items: Product FK -> products(product_id)
executeSql(
    $db,
    "ALTER TABLE order_items ADD CONSTRAINT fk_order_items_product_id FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE",
    "Add FK order_items.product_id -> products.product_id",
    $errors,
    $success,
    $EXECUTE
);

// Re-enable foreign key checks
executeSql($db, "SET FOREIGN_KEY_CHECKS = 1", "Re-enable foreign key checks", $errors, $success, $EXECUTE);

// ==========================================
// STEP 5: Data Migration
// ==========================================
echo "STEP 5: Data Migration (Old IDs -> New IDs)\n";
echo "-------------------------------------------\n";

if ($EXECUTE) {
    try {
        // Cart Migration
        $oldCartItems = $db->fetchAll("SELECT id, product_id FROM cart WHERE product_id < 1000000000");
        if (!empty($oldCartItems)) {
            echo "Migrating " . count($oldCartItems) . " cart items...\n";
            foreach ($oldCartItems as $item) {
                // Find matching product by old ID
                $product = $db->fetchOne("SELECT product_id FROM products WHERE id = ?", [$item['product_id']]);
                if ($product && !empty($product['product_id'])) {
                    $db->execute("UPDATE cart SET product_id = ? WHERE id = ?", [$product['product_id'], $item['id']]);
                    echo "  - Fixed Cart Item #{$item['id']}: {$item['product_id']} -> {$product['product_id']}\n";
                }
            }
            $success[] = "Migrated Cart Items";
        }
        
        // Wishlist Migration
        $oldWishlistItems = $db->fetchAll("SELECT id, product_id FROM wishlist WHERE product_id < 1000000000");
        if (!empty($oldWishlistItems)) {
            echo "Migrating " . count($oldWishlistItems) . " wishlist items...\n";
            foreach ($oldWishlistItems as $item) {
                $product = $db->fetchOne("SELECT product_id FROM products WHERE id = ?", [$item['product_id']]);
                if ($product && !empty($product['product_id'])) {
                    $db->execute("UPDATE wishlist SET product_id = ? WHERE id = ?", [$product['product_id'], $item['id']]);
                    echo "  - Fixed Wishlist Item #{$item['id']}: {$item['product_id']} -> {$product['product_id']}\n";
                }
            }
            $success[] = "Migrated Wishlist Items";
        }
        
    } catch (Exception $e) {
        $errors[] = "Migration Error: " . $e->getMessage();
        echo "Migration Failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Migration skipped (dry-run)\n";
}

// ==========================================
// STEP 6: Add Highlights and Policies
// ==========================================
echo "STEP 6: Adding Product Highlights and Policies\n";
echo "----------------------------------------------\n";

if (!columnExists($db, 'products', 'highlights')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN highlights TEXT AFTER featured_image", "Add highlights to products", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'products', 'shipping_policy')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN shipping_policy TEXT AFTER highlights", "Add shipping_policy to products", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'products', 'return_policy')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN return_policy TEXT AFTER shipping_policy", "Add return_policy to products", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 7: Add Razorpay & Delivery Columns to Orders
// ==========================================
echo "STEP 7: Adding Razorpay & Delivery Columns\n";
echo "------------------------------------------\n";

if (!columnExists($db, 'orders', 'razorpay_payment_id')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN razorpay_payment_id VARCHAR(255) DEFAULT NULL AFTER order_status", "Add razorpay_payment_id to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'razorpay_order_id')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN razorpay_order_id VARCHAR(255) DEFAULT NULL AFTER razorpay_payment_id", "Add razorpay_order_id to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'delivery_date')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN delivery_date DATE DEFAULT NULL AFTER razorpay_order_id", "Add delivery_date to orders", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 8: Seed Brands into site_settings
// ==========================================
echo "STEP 8: Seeding Brands into site_settings\n";
echo "----------------------------------------\n";

if ($EXECUTE) {
    try {
        $brandsExist = $db->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = 'Brands'");
        if (!$brandsExist) {
            $initialBrands = json_encode(['Milano', 'Luxury', 'Premium']);
            $db->execute("INSERT INTO site_settings (setting_key, setting_value) VALUES ('Brands', ?)", [$initialBrands]);
            $success[] = "Seeded Brands into site_settings";
            echo "Status: ✅ SEEDED\n\n";
        } else {
            echo "Status: ⚠️ EXISTS - Brands already seeded\n\n";
        }
    } catch (Exception $e) {
        $errors[] = "Seed Brands error: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Status: SKIPPED (dry-run)\n\n";
}

// ==========================================
// STEP 9: Add OtherPlatform column to Landing Pages
// ==========================================
echo "STEP 9: Adding Other_platform column to landing_pages\n";
echo "--------------------------------------------------\n";

if (!columnExists($db, 'landing_pages', 'Other_platform')) {
    executeSql($db, "ALTER TABLE landing_pages ADD COLUMN Other_platform JSON DEFAULT NULL", "Add Other_platform to landing_pages", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'landing_pages', 'show_other_platforms')) {
    executeSql($db, "ALTER TABLE landing_pages ADD COLUMN show_other_platforms TINYINT(1) DEFAULT 1", "Add show_other_platforms toggle to landing_pages", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 10: Consolidated Section Data (10 Groups)
// ==========================================
echo "STEP 10: Adding 10 Grouped JSON columns for sections\n";
echo "------------------------------------------------\n";

$jsonColumns = [
    'header_data' => 'JSON DEFAULT NULL COMMENT "Header section data"',
    'hero_data' => 'JSON DEFAULT NULL COMMENT "Hero section data"',
    'stats_data' => 'JSON DEFAULT NULL COMMENT "Stats section data"',
    'why_data' => 'JSON DEFAULT NULL COMMENT "Why section data"',
    'about_data' => 'JSON DEFAULT NULL COMMENT "About section data"',
    'banner_data' => 'JSON DEFAULT NULL COMMENT "Banner section data"',
    'testimonials_data' => 'JSON DEFAULT NULL COMMENT "Testimonials section data"',
    'newsletter_data' => 'JSON DEFAULT NULL COMMENT "Newsletter section data"',
    'platforms_data' => 'JSON DEFAULT NULL COMMENT "Platforms/Marketplaces section data"',
    'footer_data' => 'JSON DEFAULT NULL COMMENT "Footer section data"',
    'page_config' => 'JSON DEFAULT NULL COMMENT "Global page config"',
    'seo_data' => 'JSON DEFAULT NULL COMMENT "SEO and Meta data"'
];

foreach ($jsonColumns as $col => $def) {
    if (!columnExists($db, 'landing_pages', $col)) {
        executeSql($db, "ALTER TABLE landing_pages ADD COLUMN $col $def", "Add $col to landing_pages", $errors, $success, $EXECUTE);
    }
}

// ==========================================
// STEP 11: Drop Legacy Columns (Production Cleanup)
// ==========================================
echo "STEP 11: Dropping legacy columns from landing_pages\n";
echo "--------------------------------------------------\n";

$legacyColumns = [
    'hero_title', 'hero_subtitle', 'hero_description', 'hero_bg_color', 'hero_text_color',
    'show_stats', 'stats_bg_color', 'stats_text_color',
    'show_why', 'why_title', 'why_bg_color', 'why_text_color',
    'show_about', 'about_title', 'about_text', 'about_image', 'about_bg_color', 'about_text_color',
    'show_products', 'products_title',
    'show_testimonials', 'testimonials_title', 'testimonials_bg_color', 'testimonials_text_color',
    'show_newsletter', 'newsletter_title', 'newsletter_text', 'newsletter_bg_color', 'newsletter_text_color',
    'show_banner', 'banner_image', 'banner_mobile_image', 'banner_heading', 'banner_text', 'banner_btn_text', 'banner_btn_link', 'banner_sections_json',
    'Other_platform', 'show_other_platforms',
    'meta_title', 'meta_description', 'custom_schema',
    'footer_extra_content', 'footer_extra_bg', 'footer_extra_text', 'show_footer_extra',
    'theme_color', 'body_bg_color', 'body_text_color', 'nav_links',
    'hero_image', 'banner_bg_color', 'banner_text_color', 'show_brands', 'section_order'
];

foreach ($legacyColumns as $col) {
    if (columnExists($db, 'landing_pages', $col)) {
        executeSql($db, "ALTER TABLE landing_pages DROP COLUMN $col", "Drop legacy column $col", $errors, $success, $EXECUTE);
    }
}
// ==========================================
// STEP 12: Modernize Product Links in Landing Pages
// ==========================================
echo "STEP 12: Modernizing product links in landing_pages\n";
echo "--------------------------------------------------\n";

if (columnExists($db, 'landing_pages', 'product_id')) {
    executeSql($db, "ALTER TABLE landing_pages MODIFY COLUMN product_id BIGINT", "Change product_id type to BIGINT", $errors, $success, $EXECUTE);
    
    $query = "UPDATE landing_pages lp 
              INNER JOIN products p ON lp.product_id = p.id 
              SET lp.product_id = p.product_id";
    executeSql($db, $query, "Migrate relative PK IDs to custom product_id values", $errors, $success, $EXECUTE);
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Successful operations: " . count($success) . "\n";
echo "Errors encountered: " . count($errors) . "\n\n";

if (!$EXECUTE) {
    echo "⚠️  NOTE: This was a DRY RUN. No changes were applied.\n";
    echo "To execute, edit this file and set \$EXECUTE = true;\n";
    echo "Then run: php database-update.php\n";
} else {
    echo "✅ Update Complete. You may verify your tables now.\n";
    echo "We recommend deleting this file after successful execution.\n";
}
