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
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || 
            stripos($msg, 'already exists') !== false ||
            stripos($msg, 'Duplicate foreign key') !== false ||
            stripos($msg, '1826') !== false ||
            stripos($msg, 'Duplicate entry') !== false) {
            echo "Status: ⚠️ EXISTS - " . $msg . "\n\n";
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
            // Aggressive fallback: try to drop by name directly in case the schema check missed it (common with shared DB prefixes)
            try {
                $db->execute("ALTER TABLE `$table` DROP FOREIGN KEY `$constraintName`");
                echo "Status: ✅ DROPPED (via direct command)\n\n";
            } catch (Exception $e2) {
                echo "Status: NOT FOUND (Skipped)\n\n";
            }
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
// STEP 1: Setup Store ID Foundation (Users)
// ==========================================
echo "STEP 1: Setup Store ID Foundation\n";
echo "---------------------------------\n";

if (!columnExists($db, 'users', 'store_id')) {
    executeSql($db, "ALTER TABLE users ADD COLUMN store_id VARCHAR(50) DEFAULT NULL AFTER id", "Add store_id to users", $errors, $success, $EXECUTE);
}

$masterStoreId = null;

if ($EXECUTE) {
    try {
        // 1. Generate IDs for users who don't have one
        $usersWithoutStoreId = $db->fetchAll("SELECT id FROM users WHERE store_id IS NULL OR store_id = ''");
        if (!empty($usersWithoutStoreId)) {
            echo "Generating Store IDs for " . count($usersWithoutStoreId) . " users...\n";
            foreach ($usersWithoutStoreId as $user) {
                // Generate unique Store ID (10 chars upper)
                $genStoreId = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
                $db->execute("UPDATE users SET store_id = ? WHERE id = ?", [$genStoreId, $user['id']]);
                echo "  - Generated Store ID for User #{$user['id']}: $genStoreId\n";
            }
            $success[] = "Populated Store IDs for existing users";
        }

        // 1.5. Add store_url to users if not exists (for domain mapping)
        if (!columnExists($db, 'users', 'store_url')) {
             executeSql($db, "ALTER TABLE users ADD COLUMN store_url VARCHAR(255) DEFAULT NULL AFTER store_id", "Add store_url to users", $errors, $success, $EXECUTE);
             
             // Update main admin (ID 1)
             $db->execute("UPDATE users SET store_url = 'zensshop.kartoai.com' WHERE id = 1");
             // Update local dev users
             $db->execute("UPDATE users SET store_url = 'localhost' WHERE store_url IS NULL OR store_url = ''");
             echo "Initialized store_url for existing users.\n";
        }
        
        // 2. Get Master Store ID (use first valid one)
        $user = $db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL AND store_id != '' LIMIT 1");
        if ($user && !empty($user['store_id'])) {
            $masterStoreId = $user['store_id'];
            echo "✅ MASTER STORE ID: $masterStoreId\n\n";
        } else {
             // Fallback if no users exist?
             $masterStoreId = 'DEFAULT'; 
             echo "⚠️ No users found. Using fallback Master Store ID: $masterStoreId\n\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Store ID Setup Error: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n\n";
    }
}

// ==========================================
// STEP 2: Propagate Store ID to All Tables
// ==========================================
echo "STEP 2: Propagating Store ID to All Tables\n";
echo "------------------------------------------\n";

if ($EXECUTE && $masterStoreId) {
    try {
        $tablesResult = $db->fetchAll("SHOW TABLES");
        foreach ($tablesResult as $row) {
            $tableName = current($row);
            
            if ($tableName === 'users') continue; // Already handled
            
            // Add column if missing
            if (!columnExists($db, $tableName, 'store_id')) {
                // Try to add after 'id' if possible
                $afterSql = columnExists($db, $tableName, 'id') ? "AFTER id" : "";
                executeSql($db, "ALTER TABLE `$tableName` ADD COLUMN store_id VARCHAR(50) DEFAULT NULL $afterSql", "Add store_id to $tableName", $errors, $success, $EXECUTE);
            }
            
            // Add Index if missing
            try {
                $idxCheck = $db->fetchOne("SHOW INDEX FROM `$tableName` WHERE Key_name = 'idx_store_id'");
                if (!$idxCheck) {
                     executeSql($db, "CREATE INDEX idx_store_id ON `$tableName` (store_id)", "Add Index idx_store_id to $tableName", $errors, $success, $EXECUTE);
                }
            } catch(Exception $ex) {}
            
            // Backfill data
            // Only update if table has rows
            $count = $db->fetchOne("SELECT COUNT(*) as c FROM `$tableName`")['c'];
            if ($count > 0) {
                 $db->execute("UPDATE `$tableName` SET store_id = ? WHERE store_id IS NULL OR store_id = ''", [$masterStoreId]);
            }
        }
        $success[] = "Propagated Store ID to all tables";
    } catch (Exception $e) {
        $errors[] = "Store ID Propagation Error: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Skipped (dry-run or no master/store id)\n\n";
}

// ==========================================
// STEP 3: Add variant_attributes columns
// ==========================================
echo "STEP 3: Adding/Checking variant_attributes columns\n";
echo "------------------------------------------------\n";

if (!columnExists($db, 'cart', 'variant_attributes')) {
    executeSql($db, "ALTER TABLE cart ADD COLUMN variant_attributes JSON DEFAULT NULL COMMENT 'Selected product variant attributes'", "Add variant_attributes to cart", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'variant_attributes')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN variant_attributes JSON DEFAULT NULL COMMENT 'Selected product variant at time of order'", "Add variant_attributes to order_items", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 4: Drop Old Incorrect Foreign Keys
// ==========================================
echo "STEP 4: Dropping Old Foreign Keys (if they exist)\n";
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
// STEP 5: Update Column Types (BIGINT)
// ==========================================
echo "STEP 5: Updating IDs to BIGINT\n";
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

// Ensure customers has PRIMARY KEY on ID (required for FK reference)
executeSql($db, "ALTER TABLE customers MODIFY COLUMN id BIGINT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY IF NOT EXISTS (id)", "Modify customers.id to BIGINT and ensure PRIMARY KEY", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE cart MODIFY COLUMN product_id BIGINT NOT NULL", "Modify cart.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE cart MODIFY COLUMN user_id BIGINT NOT NULL", "Modify cart.user_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE wishlist MODIFY COLUMN product_id BIGINT NOT NULL", "Modify wishlist.product_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE wishlist MODIFY COLUMN user_id BIGINT NOT NULL", "Modify wishlist.user_id to BIGINT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE order_items MODIFY COLUMN product_id BIGINT NOT NULL", "Modify order_items.product_id to BIGINT", $errors, $success, $EXECUTE);

// Ensure products.product_id has a UNIQUE index (required for FK reference)
executeSql($db, "ALTER TABLE products ADD UNIQUE INDEX IF NOT EXISTS idx_unique_product_id (product_id)", "Ensure UNIQUE index on products.product_id", $errors, $success, $EXECUTE);

// Ensure child columns have indexes (prevents 1822 errors)
executeSql($db, "ALTER TABLE cart ADD INDEX IF NOT EXISTS idx_cart_user (user_id)", "Add index to cart.user_id", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE wishlist ADD INDEX IF NOT EXISTS idx_wishlist_user (user_id)", "Add index to wishlist.user_id", $errors, $success, $EXECUTE);

// ==========================================
// STEP 6: Re-Add Correct Foreign Keys
// ==========================================
echo "STEP 6: Adding Correct Foreign Keys\n";
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
// STEP 7: Data Migration
// ==========================================
echo "STEP 7: Data Migration (Old IDs -> New IDs)\n";
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
// STEP 8: Add Highlights and Policies
// ==========================================
echo "STEP 8: Adding Product Highlights and Policies\n";
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
// STEP 9: Add Razorpay & Delivery Columns to Orders
// ==========================================
echo "STEP 9: Adding Razorpay & Delivery Columns\n";
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
// STEP 10: Ensuring settings table correctness
// ==========================================
echo "STEP 10: Ensuring settings table constraints\n";
echo "------------------------------------------\n";

if (columnExists($db, 'settings', 'setting_key')) {
    // We need to fix the UNIQUE KEY `setting_key` because it prevents multiple stores from having the same key.
    // We want UNIQUE KEY (setting_key, store_id) instead.
    
    if ($EXECUTE) {
        try {
            // Check if indexes exist
            $indexes = $db->fetchAll("SHOW INDEX FROM settings WHERE Key_name = 'setting_key'");
            if (!empty($indexes)) {
                 $nonUnique = $indexes[0]['Non_unique'];
                 if ($nonUnique == 0) { // It is a UNIQUE index
                     // Drop it
                     executeSql($db, "ALTER TABLE settings DROP INDEX setting_key", "Drop old UNIQUE index on settings.setting_key", $errors, $success, $EXECUTE);
                 }
            }
            
            // Now add the correct composite unique index
            // We use 'IF NOT EXISTS' logic via try-catch or explicit check usually, but executeSql handles errors nicely
            executeSql(
                $db, 
                "ALTER TABLE settings ADD UNIQUE INDEX idx_unique_setting_store (setting_key, store_id)", 
                "Add composite UNIQUE index (setting_key, store_id) to settings", 
                $errors, 
                $success, 
                $EXECUTE
            );
            
        } catch (Exception $e) {
            $errors[] = "Settings constraint fix error: " . $e->getMessage();
        }
    }
}

// Ensure site_settings table exists

$sql_site_settings = "CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    store_id VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (setting_key, store_id),
    INDEX idx_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

executeSql($db, $sql_site_settings, "Create site_settings table", $errors, $success, $EXECUTE);

if (columnExists($db, 'site_settings', 'setting_key')) {
    // Check if store_id exists (it might be missing in older versions)
    if (!columnExists($db, 'site_settings', 'store_id')) {
        executeSql($db, "ALTER TABLE site_settings ADD COLUMN store_id VARCHAR(50) DEFAULT NULL", "Add store_id to site_settings", $errors, $success, $EXECUTE);
    }
    
    // Check if we need to fix the PRIMARY KEY (might have been just setting_key before)
    try {
        if ($EXECUTE) {
             // We use a safe way to check if PK needs update
             $pkCheck = $db->fetchAll("SHOW KEYS FROM site_settings WHERE Key_name = 'PRIMARY'");
             if (count($pkCheck) < 2) { // Should have 2 columns in PK: setting_key and store_id
                 $db->execute("ALTER TABLE site_settings DROP PRIMARY KEY, ADD PRIMARY KEY (setting_key, store_id)");
                 echo "Status: ✅ FIXED PRIMARY KEY\n\n";
             }
        }
    } catch(Exception $e) { /* Keys might already be correct */ }
}

// ==========================================
// STEP 11: Seed Brands into site_settings
// ==========================================
echo "STEP 11: Seeding Brands into site_settings\n";
echo "----------------------------------------\n";

if ($EXECUTE) {
    try {
        $brandsExist = $db->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = 'Brands'");
        if (!$brandsExist) {
            $initialBrands = json_encode(['CookPro', 'Luxury', 'Premium']);
            $db->execute(
                "INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('Brands', ?, ?)", 
                [$initialBrands, $masterStoreId ?: 'DEFAULT']
            );
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
// STEP 12: Add OtherPlatform column to Landing Pages
// ==========================================
echo "STEP 12: Adding Other_platform column to landing_pages\n";
echo "--------------------------------------------------\n";

if (!columnExists($db, 'landing_pages', 'Other_platform')) {
    executeSql($db, "ALTER TABLE landing_pages ADD COLUMN Other_platform JSON DEFAULT NULL", "Add Other_platform to landing_pages", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'landing_pages', 'show_other_platforms')) {
    executeSql($db, "ALTER TABLE landing_pages ADD COLUMN show_other_platforms TINYINT(1) DEFAULT 1", "Add show_other_platforms toggle to landing_pages", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 12: Consolidated Section Data (10 Groups)
// ==========================================
echo "STEP 12: Adding 10 Grouped JSON columns for sections\n";
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
// STEP 13: Drop Legacy Columns (Production Cleanup)
// ==========================================
echo "STEP 13: Dropping legacy columns from landing_pages\n";
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
// STEP 14: Modernize Product Links in Landing Pages
// ==========================================
echo "STEP 14: Modernizing product links in landing_pages\n";
echo "--------------------------------------------------\n";

if (columnExists($db, 'landing_pages', 'product_id')) {
    executeSql($db, "ALTER TABLE landing_pages MODIFY COLUMN product_id BIGINT", "Change product_id type to BIGINT", $errors, $success, $EXECUTE);
    
    $query = "UPDATE landing_pages lp 
              INNER JOIN products p ON lp.product_id = p.id 
              SET lp.product_id = p.product_id";
    executeSql($db, $query, "Migrate relative PK IDs to custom product_id values", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 15: Increase Column Sizes & Expand ENUMs
// ==========================================
echo "STEP 15: Increasing Column Sizes & Expanding ENUMs\n";
echo "--------------------------------------------------\n";

executeSql($db, "ALTER TABLE products MODIFY COLUMN images MEDIUMTEXT", "Upgrade products.images to MEDIUMTEXT", $errors, $success, $EXECUTE);
executeSql($db, "ALTER TABLE product_variants MODIFY COLUMN variant_attributes MEDIUMTEXT", "Upgrade product_variants.variant_attributes to MEDIUMTEXT", $errors, $success, $EXECUTE);

// Expand Order Status ENUM to include refund related statuses
executeSql($db, "ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending','processing','shipped','delivered','cancelled','returned','return_requested') DEFAULT 'pending'", "Expand orders.order_status ENUM", $errors, $success, $EXECUTE);


// ==========================================
// STEP 16: Customer ID Modernization (10-digit)
// ==========================================
echo "STEP 16: Modernizing Customer IDs to 10-digit format\n";
echo "---------------------------------------------------\n";

if (!columnExists($db, 'customers', 'customer_id')) {
    executeSql($db, "ALTER TABLE customers ADD COLUMN customer_id BIGINT AFTER id", "Add customer_id to customers", $errors, $success, $EXECUTE);
    executeSql($db, "ALTER TABLE customers ADD UNIQUE INDEX IF NOT EXISTS idx_unique_customer_id (customer_id)", "Add UNIQUE index to customers.customer_id", $errors, $success, $EXECUTE);
}

if ($EXECUTE) {
    // Populate existing customers with random 10-digit IDs if they don't have one
    $customers = $db->fetchAll("SELECT id FROM customers WHERE customer_id IS NULL");
    if (!empty($customers)) {
        echo "Generating IDs for " . count($customers) . " existing customers...\n";
        foreach ($customers as $c) {
            $randomId = mt_rand(1000000000, 9999999999);
            $db->execute("UPDATE customers SET customer_id = ? WHERE id = ?", [$randomId, $c['id']]);
        }
    }
    
    // Helper to dynamically drop FK on a column
    $dropFkDynamic = function($table, $column) use ($db) {
        try {
            $fks = $db->fetchAll("SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL", [$table, $column]);
                
            foreach ($fks as $fk) {
                $name = $fk['CONSTRAINT_NAME'];
                $db->execute("ALTER TABLE `$table` DROP FOREIGN KEY `$name`");
                echo "Dropped dynamic FK: $name on $table.$column\n";
            }
        } catch (Exception $e) {
             // Ignore errors if FK doesn't exist or table doesn't exist
        }
    };
    
    // Migrate dependent tables
    
    // 1. orders (user_id -> customer_id)
    $dropFkDynamic('orders', 'user_id');
    executeSql($db, "ALTER TABLE orders MODIFY COLUMN user_id BIGINT", "Ensure orders.user_id is BIGINT", $errors, $success, $EXECUTE);
    // Standard update: match old ID to new customer_id
    $db->execute("UPDATE orders o INNER JOIN customers c ON o.user_id = c.id SET o.user_id = c.customer_id WHERE o.user_id < 1000000000");
    // Recovery update: match via email (fixes mismatches for orders placed during transition)
    try {
        $db->execute("UPDATE orders o INNER JOIN customers c ON o.customer_email = c.email SET o.user_id = c.customer_id WHERE c.customer_id IS NOT NULL");
    } catch (Exception $e) { /* Ignore if columns missing */ }
    
    // 2. cart (user_id -> customer_id)
    $dropFkDynamic('cart', 'user_id');
    executeSql($db, "ALTER TABLE cart MODIFY COLUMN user_id BIGINT NOT NULL", "Ensure cart.user_id is BIGINT", $errors, $success, $EXECUTE);
    $db->execute("UPDATE cart cr INNER JOIN customers c ON cr.user_id = c.id SET cr.user_id = c.customer_id WHERE cr.user_id < 1000000000");

    // 3. wishlist (user_id -> customer_id)
    $dropFkDynamic('wishlist', 'user_id');
    executeSql($db, "ALTER TABLE wishlist MODIFY COLUMN user_id BIGINT NOT NULL", "Ensure wishlist.user_id is BIGINT", $errors, $success, $EXECUTE);
    $db->execute("UPDATE wishlist w INNER JOIN customers c ON w.user_id = c.id SET w.user_id = c.customer_id WHERE w.user_id < 1000000000");

    // 4. customer_sessions (customer_id -> customer_id)
    // First drop the old FK if it exists
    dropForeignKeyIfExists($db, 'customer_sessions', 'customer_sessions_ibfk_1', $errors, $success, $EXECUTE);
    $dropFkDynamic('customer_sessions', 'customer_id'); // Double check
    
    executeSql($db, "ALTER TABLE customer_sessions MODIFY COLUMN customer_id BIGINT NOT NULL", "Ensure customer_sessions.customer_id is BIGINT", $errors, $success, $EXECUTE);
    $db->execute("UPDATE customer_sessions cs INNER JOIN customers c ON cs.customer_id = c.id SET cs.customer_id = c.customer_id WHERE cs.customer_id < 1000000000");
    
    // Re-add FK pointing to new customer_id column
    executeSql($db, "ALTER TABLE customer_sessions ADD CONSTRAINT fk_sessions_customer_id FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE", "Re-add FK sessions -> customers.customer_id", $errors, $success, $EXECUTE);

    $success[] = "Customer ID Modernization Complete";
}

// ==========================================
// STEP 17: Migrate Order Items to use Order Number
// ==========================================
echo "STEP 17: Migrating order_items schema (order_id -> order_num)\n";
echo "---------------------------------------------------\n";

if ($EXECUTE) {
    // 1. Add new column order_num if not exists
    if (!columnExists($db, 'order_items', 'order_num')) {
        executeSql($db, "ALTER TABLE order_items ADD COLUMN order_num VARCHAR(50) AFTER id", "Add order_num column", $errors, $success, $EXECUTE);
        executeSql($db, "CREATE INDEX idx_order_items_order_num ON order_items(order_num)", "Index order_num", $errors, $success, $EXECUTE);
        
        // 2. Populate order_num from orders table
        echo "Populating order_num from orders table...\n";
        // Attempt to join on ID first (standard)
        $sql = "UPDATE order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                SET oi.order_num = o.order_number 
                WHERE oi.order_num IS NULL";
        
        // Check if order_id is numeric or string to decide how to join/copy
        // But the join above works if order_id is INT.
        // If order_id is ALREADY the string order_number (e.g. partial migration), we should copy it.
        // We can do both safely.
        
        $db->execute($sql);
        
        // Fallback: If order_id is already the order number string (legacy mixed state), copy it directly
        try {
            $db->execute("UPDATE order_items SET order_num = order_id WHERE order_num IS NULL AND order_id LIKE 'ORD-%'");
        } catch (Exception $e) {}
        
        // 3. Drop old column and keys
        // Drop FKs first
        $dropFkUsingSql = function($table, $column) use ($db) {
             try {
                $fks = $db->fetchAll("SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ? 
                    AND COLUMN_NAME = ? 
                    AND REFERENCED_TABLE_NAME IS NOT NULL", [$table, $column]);
                foreach ($fks as $fk) {
                    $db->execute("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }
            } catch (Exception $e) {}
        };
        $dropFkUsingSql('order_items', 'order_id');
        
        // Drop order_id column
        // Only drop if order_num is populated reasonably
        $count = $db->fetchOne("SELECT COUNT(*) FROM order_items WHERE order_num IS NOT NULL");
        if ($count > 0) {
             executeSql($db, "ALTER TABLE order_items DROP COLUMN order_id", "Drop old order_id column", $errors, $success, $EXECUTE);
        } else {
             $errors[] = "Aborted dropping order_id: order_num population seemed to fail (0 records).";
        }
    }
}

// ==========================================
// STEP 18: Stock Management Refinements
// ==========================================
echo "STEP 18: Adding total_sales and oversold_quantity\n";
echo "---------------------------------------------------\n";

if (!columnExists($db, 'products', 'total_sales')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN total_sales INT DEFAULT 0 AFTER stock_status", "Add total_sales to products", $errors, $success, $EXECUTE);
    
    if ($EXECUTE) {
        // Populate total_sales from order_items
        $updateSql = "
            UPDATE products p 
            SET total_sales = (
                SELECT COALESCE(SUM(quantity), 0) 
                FROM order_items oi 
                WHERE oi.product_id = p.product_id 
                   OR (oi.product_id < 1000000000 AND oi.product_id = p.id)
            )
        ";
        executeSql($db, $updateSql, "Populate initial total_sales from order_items", $errors, $success, $EXECUTE);
    }
}

if (!columnExists($db, 'order_items', 'oversold_quantity')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN oversold_quantity INT DEFAULT 0 AFTER quantity", "Add oversold_quantity to order_items", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'products', 'cost_per_item')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN cost_per_item DECIMAL(10,2) DEFAULT 0.00 AFTER sale_price", "Add cost_per_item to products", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'products', 'total_expense')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN total_expense DECIMAL(10,2) DEFAULT 0.00 AFTER cost_per_item", "Add total_expense to products", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 19: Notification Link Migration
// ==========================================
echo "STEP 19: Migrating notification links (list -> detail)\n";
echo "---------------------------------------------------\n";

if ($EXECUTE) {
    $db->execute("
        UPDATE admin_notifications 
        SET link = REPLACE(link, '/admin/orders/list.php?search=', '/admin/orders/detail.php?order_number=')
        WHERE type = 'order' AND link LIKE '/admin/orders/list.php?search=%'
    ");
    $success[] = "Migrated notification links";
    echo "Status: ✅ MIGRATED\n\n";
}

// ==========================================
// STEP 20: Remove Gender Column
// ==========================================
echo "STEP 20: Removing gender column from products\n";
echo "---------------------------------------------------\n";

if (columnExists($db, 'products', 'gender')) {
    executeSql($db, "ALTER TABLE products DROP COLUMN gender", "Drop gender column from products", $errors, $success, $EXECUTE);
} else {
    echo "Status: ⏭️  SKIPPED (column doesn't exist)\n\n";
}

// ==========================================
// STEP 21: Specific Section & Category Enhancements
// ==========================================
echo "STEP 21: Specific Section & Category Enhancements\n";
echo "---------------------------------------------------\n";

if (!columnExists($db, 'categories', 'icon')) {
    executeSql($db, "ALTER TABLE categories ADD COLUMN icon VARCHAR(50) DEFAULT NULL AFTER banner", "Add icon to categories", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'special_offers', 'heading')) {
    executeSql($db, "ALTER TABLE special_offers ADD COLUMN heading VARCHAR(255) DEFAULT NULL AFTER store_id", "Add heading to special_offers", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'special_offers', 'subheading')) {
    executeSql($db, "ALTER TABLE special_offers ADD COLUMN subheading TEXT DEFAULT NULL AFTER heading", "Add subheading to special_offers", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'section_videos', 'heading')) {
    executeSql($db, "ALTER TABLE section_videos ADD COLUMN heading VARCHAR(255) DEFAULT NULL AFTER store_id", "Add heading to section_videos", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'section_videos', 'subheading')) {
    executeSql($db, "ALTER TABLE section_videos ADD COLUMN subheading TEXT DEFAULT NULL AFTER heading", "Add subheading to section_videos", $errors, $success, $EXECUTE);
}

// Ensure section_newsletter has heading/subheading too if not present
if (!columnExists($db, 'section_newsletter', 'heading')) {
    executeSql($db, "ALTER TABLE section_newsletter ADD COLUMN heading VARCHAR(255) DEFAULT NULL AFTER store_id", "Add heading to section_newsletter", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'section_newsletter', 'subheading')) {
    executeSql($db, "ALTER TABLE section_newsletter ADD COLUMN subheading TEXT DEFAULT NULL AFTER heading", "Add subheading to section_newsletter", $errors, $success, $EXECUTE);
}


// ==========================================
// STEP 22: Create Pages Table
// ==========================================
echo "STEP 22: Creating Pages Table (JSON Content)\n";
echo "---------------------------------------------------\n";

// Check if table exists properly
$tableExists = false;
try {
    $db->fetchOne("SELECT 1 FROM pages LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    // Table likely doesn't exist
}

if (!$tableExists) {
    $sql = "CREATE TABLE IF NOT EXISTS pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id VARCHAR(50) NOT NULL,
        page_id BIGINT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        content JSON DEFAULT NULL COMMENT 'Stores HTML body, banner settings, SEO, etc. as JSON',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_store (store_id),
        UNIQUE KEY idx_slug_store (slug, store_id),
        UNIQUE KEY idx_page_id (page_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    executeSql($db, $sql, "Create pages table", $errors, $success, $EXECUTE);
} else {
    echo "Status: ⏭️  SKIPPED (table already exists)\n\n";
    // Migration logic if needed
    if (columnExists($db, 'pages', 'banner_image')) {
         echo "Detected old schema columns. Use a drop/recreate manually if needed.\n";
    }
}

// ==========================================
// STEP 23: Create Reset Password Table
// ==========================================
echo "STEP 23: Creating Reset Password Table\n";
echo "---------------------------------------------------\n";

$sql_reset_password = "CREATE TABLE IF NOT EXISTS `customer_reset_password` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";


executeSql($db, $sql_reset_password, "Create customer_reset_password table", $errors, $success, $EXECUTE);

// ==========================================
// STEP 24: Fix Multi-Store Unique Constraints
// ==========================================
echo "STEP 24: Fixing Multi-Store Unique Constraints\n";
echo "---------------------------------------------------\n";

// Fix menus table - location should be unique per store
if ($EXECUTE) {
    try {
        // Check if old unique constraint exists
        $indexes = $db->fetchAll("SHOW INDEX FROM menus WHERE Key_name = 'location' AND Non_unique = 0");
        if (!empty($indexes)) {
            executeSql($db, "ALTER TABLE menus DROP INDEX location", "Drop old UNIQUE constraint on menus.location", $errors, $success, $EXECUTE);
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "\n";
    }
    
    try {
        executeSql($db, "ALTER TABLE menus ADD UNIQUE KEY unique_location_store (location, store_id)", "Add composite UNIQUE constraint on menus(location, store_id)", $errors, $success, $EXECUTE);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            $errors[] = "Menus constraint error: " . $e->getMessage();
        }
    }
}

// Fix categories table - slug should be unique per store
if ($EXECUTE) {
    try {
        $indexes = $db->fetchAll("SHOW INDEX FROM categories WHERE Key_name = 'slug' AND Non_unique = 0");
        if (!empty($indexes)) {
            executeSql($db, "ALTER TABLE categories DROP INDEX slug", "Drop old UNIQUE constraint on categories.slug", $errors, $success, $EXECUTE);
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "\n";
    }
    
    try {
        executeSql($db, "ALTER TABLE categories ADD UNIQUE KEY unique_slug_store (slug, store_id)", "Add composite UNIQUE constraint on categories(slug, store_id)", $errors, $success, $EXECUTE);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            $errors[] = "Categories constraint error: " . $e->getMessage();
        }
    }
}

// Fix products table - slug and sku should be unique per store
if ($EXECUTE) {
    // Fix slug
    try {
        $indexes = $db->fetchAll("SHOW INDEX FROM products WHERE Key_name = 'slug' AND Non_unique = 0");
        if (!empty($indexes)) {
            executeSql($db, "ALTER TABLE products DROP INDEX slug", "Drop old UNIQUE constraint on products.slug", $errors, $success, $EXECUTE);
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "\n";
    }
    
    try {
        executeSql($db, "ALTER TABLE products ADD UNIQUE KEY unique_slug_store (slug, store_id)", "Add composite UNIQUE constraint on products(slug, store_id)", $errors, $success, $EXECUTE);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            $errors[] = "Products slug constraint error: " . $e->getMessage();
        }
    }
    
    // Fix SKU
    try {
        $indexes = $db->fetchAll("SHOW INDEX FROM products WHERE Key_name = 'sku' AND Non_unique = 0");
        if (!empty($indexes)) {
            executeSql($db, "ALTER TABLE products DROP INDEX sku", "Drop old UNIQUE constraint on products.sku", $errors, $success, $EXECUTE);
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "\n";
    }
    
    try {
        executeSql($db, "ALTER TABLE products ADD UNIQUE KEY unique_sku_store (sku, store_id)", "Add composite UNIQUE constraint on products(sku, store_id)", $errors, $success, $EXECUTE);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            $errors[] = "Products SKU constraint error: " . $e->getMessage();
        }
    }
    
    echo "Note: product_id remains globally unique as intended\n\n";
    $success[] = "Multi-store constraints fixed";
}

// ==========================================
// STEP 25: Clean STORE- Prefix from Existing Data
// ==========================================
echo "STEP 25: Cleaning STORE- Prefix from Existing Data\n";
echo "---------------------------------------------------\n";

if ($EXECUTE) {
    try {
        // Get all tables
        $tables = $db->fetchAll("SHOW TABLES");
        $totalCleaned = 0;
        
        foreach ($tables as $row) {
            $tableName = current($row);
            
            // Check if table has store_id column
            $columns = $db->fetchAll("SHOW COLUMNS FROM `$tableName` LIKE 'store_id'");
            
            if (empty($columns)) {
                continue; // Skip tables without store_id
            }
            
            // Count records with STORE- prefix
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$tableName` WHERE store_id LIKE 'STORE-%'");
            
            if ($count['count'] > 0) {
                echo "  Cleaning $tableName: {$count['count']} records\n";
                
                // Update: Remove STORE- prefix
                $sql = "UPDATE `$tableName` SET store_id = REPLACE(store_id, 'STORE-', '') WHERE store_id LIKE 'STORE-%'";
                $db->execute($sql);
                
                $totalCleaned += $count['count'];
            }
        }
        
        if ($totalCleaned > 0) {
            echo "✓ Cleaned $totalCleaned total records\n";
            $success[] = "Cleaned STORE- prefix from $totalCleaned records";
        } else {
            echo "✓ No records with STORE- prefix found\n";
            $success[] = "No STORE- prefix cleanup needed";
        }
        
    } catch (Exception $e) {
        $errors[] = "STORE- prefix cleanup error: " . $e->getMessage();
        echo "Status: ❌ ERROR - " . $e->getMessage() . "\n";
    }
} else {
    echo "Status: SKIPPED (dry-run)\n";
}

// ==========================================
// STEP 26: Add Discount Coupon Code to Orders
// ==========================================
echo "STEP 26: Adding coupon_code to orders\n";
echo "---------------------------------------------------\n";

if (!columnExists($db, 'orders', 'coupon_code')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL AFTER discount_amount", "Add coupon_code to orders", $errors, $success, $EXECUTE);
} else {
    echo "Status: ⏭️  SKIPPED (column already exists)\n\n";
}

// ==========================================
// STEP 27: Ensure used_count in Discounts Table
// ==========================================
echo "STEP 27: Ensuring used_count in discounts\n";
echo "---------------------------------------------------\n";

// First check if table exists
try {
    $db->execute("SELECT 1 FROM discounts LIMIT 1");
    if (!columnExists($db, 'discounts', 'used_count')) {
        executeSql($db, "ALTER TABLE discounts ADD COLUMN used_count INT DEFAULT 0 AFTER usage_limit", "Add used_count to discounts", $errors, $success, $EXECUTE);
    } else {
        echo "Status: ⏭️  SKIPPED (column already exists)\n\n";
    }
} catch (Exception $e) {
    echo "Status: ⏭️  SKIPPED (discounts table does not exist yet)\n\n";
}

// ==========================================
// STEP 28: Add Per-Customer Limit to Discounts
// ==========================================
echo "STEP 28: Adding usage_limit_per_customer to discounts\n";
echo "---------------------------------------------------\n";

try {
    $db->execute("SELECT 1 FROM discounts LIMIT 1");
    if (!columnExists($db, 'discounts', 'usage_limit_per_customer')) {
        executeSql($db, "ALTER TABLE discounts ADD COLUMN usage_limit_per_customer INT DEFAULT NULL AFTER usage_limit", "Add usage_limit_per_customer to discounts", $errors, $success, $EXECUTE);
    } else {
        echo "Status: ⏭️  SKIPPED (column already exists)\n\n";
    }
} catch (Exception $e) {
    echo "Status: ⏭️  SKIPPED (discounts table does not exist yet)\n\n";
}

// ==========================================
// STEP 29: Blogs Table Setup
// ==========================================
echo "STEP 29: Setting up Blogs Table\n";
echo "-------------------------------\n";

// 1. Create table if not exists (with store_id as VARCHAR)
$sql_blogs = "CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) DEFAULT NULL,
    blog_id BIGINT(15) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT,
    image VARCHAR(255),
    status ENUM('published', 'draft') DEFAULT 'draft',
    layout VARCHAR(20) DEFAULT 'standard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store_id (store_id),
    INDEX idx_blog_id (blog_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

executeSql($db, $sql_blogs, "Create blogs table", $errors, $success, $EXECUTE);

// 2. Add blog_id if missing (for existing tables)
if (!columnExists($db, 'blogs', 'blog_id')) {
    executeSql($db, "ALTER TABLE blogs ADD COLUMN blog_id BIGINT(15) DEFAULT NULL AFTER store_id", "Add blog_id to blogs", $errors, $success, $EXECUTE);
    executeSql($db, "CREATE INDEX idx_blog_id ON blogs (blog_id)", "Add index idx_blog_id", $errors, $success, $EXECUTE);
}

// 3. Fix store_id type if it's INT (legacy)
// Check column type
try {
    $colType = $db->fetchOne("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'blogs' 
        AND COLUMN_NAME = 'store_id'
    ");
    
    if ($colType && stripos($colType['COLUMN_TYPE'], 'int') !== false) {
        executeSql($db, "ALTER TABLE blogs MODIFY COLUMN store_id VARCHAR(50) DEFAULT NULL", "Change blogs.store_id to VARCHAR(50)", $errors, $success, $EXECUTE);
    }
} catch (Exception $e) {}


// 4. Add layout column if missing
if (!columnExists($db, 'blogs', 'layout')) {
    executeSql($db, "ALTER TABLE blogs ADD COLUMN layout VARCHAR(20) DEFAULT 'standard' AFTER status", "Add layout column to blogs", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 30: Category Banner
// ==========================================
echo "STEP 30: Adding banner to categories\n";
echo "---------------------------------\n";

if (!columnExists($db, 'categories', 'banner')) {
    executeSql($db, "ALTER TABLE categories ADD COLUMN banner VARCHAR(255) DEFAULT NULL AFTER image", "Add banner to categories", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 31: Landing Pages Slug Safety
// ==========================================
echo "STEP 31: Ensuring landing_pages slug\n";
echo "---------------------------------\n";

if (!columnExists($db, 'landing_pages', 'slug')) {
    // Determine position. If name exists, after name. Else after product_id.
    // We assume standard usage.
    executeSql($db, "ALTER TABLE landing_pages ADD COLUMN slug VARCHAR(255) NOT NULL AFTER name", "Add slug to landing_pages", $errors, $success, $EXECUTE);
    try {
        executeSql($db, "ALTER TABLE landing_pages ADD UNIQUE INDEX idx_slug_store (slug, store_id)", "Add unique index on landing_pages slug", $errors, $success, $EXECUTE);
    } catch (Exception $e) {}
}


// ==========================================
// STEP 32: Page ID Modernization
// ==========================================
echo "STEP 32: Adding page_id to pages table\n";
echo "---------------------------------\n";

if (!columnExists($db, 'pages', 'page_id')) {
    executeSql($db, "ALTER TABLE pages ADD COLUMN page_id BIGINT DEFAULT NULL AFTER id", "Add page_id to pages", $errors, $success, $EXECUTE);
    try {
        executeSql($db, "ALTER TABLE pages ADD UNIQUE INDEX idx_unique_page_id (page_id)", "Add unique index to page_id", $errors, $success, $EXECUTE);
    } catch(Exception $e) {}
}

if ($EXECUTE) {
    // Backfill random IDs
    if (columnExists($db, 'pages', 'page_id')) {
        $pagesWithoutId = $db->fetchAll("SELECT id FROM pages WHERE page_id IS NULL OR page_id = 0");
        if (!empty($pagesWithoutId)) {
            echo "Generating Page IDs for " . count($pagesWithoutId) . " pages...\n";
            foreach ($pagesWithoutId as $p) {
                $randomId = mt_rand(1000000000, 9999999999);
                $db->execute("UPDATE pages SET page_id = ? WHERE id = ?", [$randomId, $p['id']]);
            }
            $success[] = "Backfilled page_id for pages";
        }
    }
}

// ==========================================
// STEP 33: Product Category Modernization
// ==========================================
echo "STEP 33: Modernizing product category_id column\n";
echo "---------------------------------\n";

// 1. Remove redundant category_ids column if it exists
if (columnExists($db, 'products', 'category_ids')) {
    executeSql($db, "ALTER TABLE products DROP COLUMN category_ids", "Remove redundant category_ids column", $errors, $success, $EXECUTE);
}

// 2. Drop Foreign Key on category_id if it exists
// Standard constraint name products_ibfk_1
dropForeignKeyIfExists($db, 'products', 'products_ibfk_1', $errors, $success, $EXECUTE);

// 3. Drop Index on category_id if it exists
try {
    // If we're in DRY-RUN mode, executeSql won't actually run, so we just log the intent
    executeSql($db, "DROP INDEX category_id ON products", "Drop index 'category_id' on products", $errors, $success, $EXECUTE);
} catch (Exception $e) {
    // Index might not exist
}

// 4. Modify category_id to TEXT to support multiple JSON IDs
executeSql($db, "ALTER TABLE products MODIFY COLUMN category_id TEXT DEFAULT NULL", "Modify products.category_id to TEXT for multi-category support", $errors, $success, $EXECUTE);


// ==========================================
// STEP 34: GST and Tax Implementation
// ==========================================
echo "STEP 34: Implementing GST and Tax Columns\n";
echo "---------------------------------\n";

// 1. Products Table
if (!columnExists($db, 'products', 'is_taxable')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN is_taxable TINYINT(1) DEFAULT 0 AFTER price", "Add is_taxable to products", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'products', 'hsn_code')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN hsn_code VARCHAR(20) AFTER is_taxable", "Add hsn_code to products", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'products', 'gst_percent')) {
    executeSql($db, "ALTER TABLE products ADD COLUMN gst_percent DECIMAL(5,2) DEFAULT 0.00 AFTER hsn_code", "Add gst_percent to products", $errors, $success, $EXECUTE);
}

// 2. Orders Table
if (!columnExists($db, 'orders', 'tax_amount')) {
     // Ensure tax_amount exists (standard field, but good to check)
     executeSql($db, "ALTER TABLE orders ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_amount", "Add tax_amount to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'cgst_total')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN cgst_total DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount", "Add cgst_total to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'sgst_total')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN sgst_total DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_total", "Add sgst_total to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'igst_total')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN igst_total DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_total", "Add igst_total to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'grand_total')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN grand_total DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount", "Add grand_total to orders", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'orders', 'customer_state')) {
    executeSql($db, "ALTER TABLE orders ADD COLUMN customer_state VARCHAR(50) AFTER billing_address", "Add customer_state to orders", $errors, $success, $EXECUTE);
}

// 3. Order Items Table
if (!columnExists($db, 'order_items', 'hsn_code')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN hsn_code VARCHAR(20) AFTER variant_attributes", "Add hsn_code to order_items", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'gst_percent')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN gst_percent DECIMAL(5,2) DEFAULT 0.00 AFTER hsn_code", "Add gst_percent to order_items", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'cgst_amount')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN cgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER gst_percent", "Add cgst_amount to order_items", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'sgst_amount')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN sgst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_amount", "Add sgst_amount to order_items", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'igst_amount')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN igst_amount DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_amount", "Add igst_amount to order_items", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'order_items', 'line_total')) {
    executeSql($db, "ALTER TABLE order_items ADD COLUMN line_total DECIMAL(10,2) DEFAULT 0.00 AFTER igst_amount", "Add line_total to order_items", $errors, $success, $EXECUTE);
}

// 4. Site Settings (Seller State)
if ($EXECUTE) {
    echo "Checking for seller_state setting...\n";
    try {
        $res = $db->fetchOne("SELECT * FROM site_settings WHERE setting_key = 'seller_state'");
        if (!$res) {
            executeSql($db, "INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('seller_state', 'Maharashtra', 'DEFAULT')", "Insert default seller_state", $errors, $success, $EXECUTE);
        } else {
             echo "seller_state already exists: " . $res['setting_value'] . "\n";
        }
    } catch (Exception $e) {
        $errors[] = "Error checking seller_state: " . $e->getMessage();
    }
}



// ==========================================
// STEP 35: Order Cancellation Table
// ==========================================
echo "STEP 35: Creating/Updating ordercancel table\n";
echo "---------------------------------\n";

$sql_ordercancel = "CREATE TABLE IF NOT EXISTS ordercancel (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    customer_id BIGINT NOT NULL,
    store_id VARCHAR(50) DEFAULT NULL,
    
    -- Cancellation Details
    cancel_reason VARCHAR(255) NOT NULL,
    cancel_comment TEXT DEFAULT NULL,
    cancel_status VARCHAR(50) DEFAULT 'pending', -- requested, approved, rejected, pending
    
    -- Snapshot of Order Details
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    shipping_address JSON DEFAULT NULL,
    
    -- Payment & Shipping Snapshot
    payment_method VARCHAR(50),
    payment_status VARCHAR(50),
    total_amount DECIMAL(10,2),
    tracking_number VARCHAR(100) DEFAULT NULL,
    
    -- Items Snapshot
    items_snapshot JSON DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_cancel_customer (customer_id),
    INDEX idx_order_cancel_store (store_id),
    INDEX idx_order_cancel_number (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

executeSql($db, $sql_ordercancel, "Create ordercancel table", $errors, $success, $EXECUTE);

// Rename user_id to customer_id if it still exists
if (columnExists($db, 'ordercancel', 'user_id')) {
    executeSql($db, "ALTER TABLE ordercancel CHANGE COLUMN user_id customer_id BIGINT NOT NULL", "Rename user_id to customer_id in ordercancel", $errors, $success, $EXECUTE);
    executeSql($db, "ALTER TABLE ordercancel DROP INDEX idx_order_cancel_user", "Drop old index", $errors, $success, $EXECUTE);
    executeSql($db, "CREATE INDEX idx_cancel_customer ON ordercancel (customer_id)", "Index customer_id on ordercancel", $errors, $success, $EXECUTE);
}

// Update default status to pending
executeSql($db, "ALTER TABLE ordercancel MODIFY COLUMN cancel_status VARCHAR(50) DEFAULT 'pending'", "Update cancel_status default to pending", $errors, $success, $EXECUTE);

if (!columnExists($db, 'ordercancel', 'type')) {
    executeSql($db, "ALTER TABLE ordercancel ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'cancel' AFTER id", "Add 'type' to ordercancel", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'ordercancel', 'previous_status')) {
    executeSql($db, "ALTER TABLE ordercancel ADD COLUMN previous_status VARCHAR(50) DEFAULT NULL AFTER type", "Add 'previous_status' to ordercancel", $errors, $success, $EXECUTE);
}

try {
    executeSql($db, "CREATE INDEX idx_cancel_type ON ordercancel (type)", "Index 'type' on ordercancel", $errors, $success, $EXECUTE);
} catch (Exception $e) {}


if (!columnExists($db, 'ordercancel', 'refund_amount')) {
    executeSql($db, "ALTER TABLE ordercancel ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT NULL AFTER total_amount", "Add 'refund_amount' to ordercancel", $errors, $success, $EXECUTE);
}

// ==========================================
// STEP 36: Google Auth Columns (Customers)
// ==========================================
echo "STEP 36: Adding Google Auth columns to customers table\n";
echo "---------------------------------\n";

if (!columnExists($db, 'customers', 'google_id')) {
    executeSql($db, "ALTER TABLE customers ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER password", "Add google_id to customers", $errors, $success, $EXECUTE);
    try {
        executeSql($db, "ALTER TABLE customers ADD INDEX idx_google_id (google_id)", "Add index idx_google_id to customers", $errors, $success, $EXECUTE);
    } catch (Exception $e) {}
}

if (!columnExists($db, 'customers', 'avatar')) {
    executeSql($db, "ALTER TABLE customers ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER google_id", "Add avatar to customers", $errors, $success, $EXECUTE);
}

if (!columnExists($db, 'customers', 'auth_provider')) {
    executeSql($db, "ALTER TABLE customers ADD COLUMN auth_provider VARCHAR(20) DEFAULT 'local' AFTER avatar", "Add auth_provider to customers", $errors, $success, $EXECUTE);
}


// ==========================================
// STEP 20: Create/Update footer_features table
// ==========================================
echo "STEP 20: Creating/Updating footer_features table\n";
echo "---------------------------------------------------\n";

$sql_footer_features = "CREATE TABLE IF NOT EXISTS footer_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) DEFAULT NULL,
    icon TEXT,
    heading VARCHAR(255),
    content TEXT,
    bg_color VARCHAR(50) DEFAULT '#ffffff',
    text_color VARCHAR(50) DEFAULT '#000000',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

executeSql($db, $sql_footer_features, "Create footer_features table", $errors, $success, $EXECUTE);

// Add feature_id column if missing
if (!columnExists($db, 'footer_features', 'feature_id')) {
    executeSql($db, "ALTER TABLE footer_features ADD COLUMN feature_id BIGINT DEFAULT NULL AFTER id", "Add feature_id to footer_features", $errors, $success, $EXECUTE);
    executeSql($db, "ALTER TABLE footer_features ADD UNIQUE INDEX idx_feature_id (feature_id)", "Add unique index on feature_id", $errors, $success, $EXECUTE);
}

// Populate missing feature_ids
if ($EXECUTE) {
    try {
        $rows = $db->fetchAll("SELECT id FROM footer_features WHERE feature_id IS NULL");
        foreach ($rows as $row) {
            $randId = mt_rand(1000000000, 9999999999);
            $db->execute("UPDATE footer_features SET feature_id = ? WHERE id = ?", [$randId, $row['id']]);
        }
        if (!empty($rows)) {
            echo "Populated " . count($rows) . " footer features with 10-digit IDs\n";
        }
    } catch (Exception $e) {}
}

// ==========================================
// STEP 21: Latest UI Section Updates (Colors & Philosophy)
// ==========================================
echo "STEP 21: Updating Schema for UI Sections (Features & Philosophy)\n";
echo "------------------------------------------------------------\n";

// 1. Footer Features Updates
if (!columnExists($db, 'footer_features', 'heading_color')) {
    executeSql($db, "ALTER TABLE footer_features ADD COLUMN heading_color VARCHAR(20) DEFAULT NULL AFTER text_color", "Add heading_color to footer_features", $errors, $success, $EXECUTE);
}

// 2. Section Features (3-Column Layout)
$sql_section_features = "CREATE TABLE IF NOT EXISTS section_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) DEFAULT NULL,
    icon TEXT,
    heading VARCHAR(255),
    content TEXT,
    bg_color VARCHAR(20) DEFAULT '#ffffff',
    text_color VARCHAR(20) DEFAULT '#000000',
    heading_color VARCHAR(20) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeSql($db, $sql_section_features, "Create/Verify section_features table", $errors, $success, $EXECUTE);

// Ensure columns exist if table was already there
if (!columnExists($db, 'section_features', 'bg_color')) {
    executeSql($db, "ALTER TABLE section_features ADD COLUMN bg_color VARCHAR(20) DEFAULT '#ffffff'", "Add bg_color to section_features", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'section_features', 'text_color')) {
    executeSql($db, "ALTER TABLE section_features ADD COLUMN text_color VARCHAR(20) DEFAULT '#000000'", "Add text_color to section_features", $errors, $success, $EXECUTE);
}
if (!columnExists($db, 'section_features', 'heading_color')) {
    executeSql($db, "ALTER TABLE section_features ADD COLUMN heading_color VARCHAR(20) DEFAULT NULL", "Add heading_color to section_features", $errors, $success, $EXECUTE);
}

// 3. Philosophy Section
$sql_philosophy = "CREATE TABLE IF NOT EXISTS philosophy_section (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) DEFAULT NULL,
    heading VARCHAR(255),
    content TEXT,
    link_text VARCHAR(100),
    link_url VARCHAR(255),
    background_color VARCHAR(20) DEFAULT '#384135',
    text_color VARCHAR(20) DEFAULT '#eee4d3',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeSql($db, $sql_philosophy, "Create/Verify philosophy_section table", $errors, $success, $EXECUTE);

echo "\n========================================\n";
echo "SUMMARY:\n";
echo "========================================\n";
echo "Successful operations: " . count($success) . "\n";
echo "Errors encountered: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nError Details:\n";
    foreach ($errors as $err) {
        echo "- $err\n";
    }
} else {
    echo "\n✅ Update Complete. You may verify your tables now.\n";
    echo "We recommend deleting this file after successful execution.\n";
}
