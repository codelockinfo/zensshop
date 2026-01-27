<?php
/**
 * RECREATE LANDING PAGES TABLE
 * Use this only if you want to wipe and restart the landing_pages table schema.
 */
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

echo "=== RECREATING landing_pages TABLE ===\n";

try {
    // 1. Drop existing table
    $db->execute("DROP TABLE IF EXISTS landing_pages");
    echo "✅ Old table dropped.\n";

    // 2. Create new table with all correct columns
    $sql = "CREATE TABLE `landing_pages` (
      `id` int NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `slug` varchar(255) NOT NULL,
      `product_id` bigint DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `header_data` json DEFAULT NULL COMMENT 'Header section data',
      `hero_data` json DEFAULT NULL COMMENT 'Hero section data',
      `banner_data` json DEFAULT NULL COMMENT 'Banner section data',
      `stats_data` json DEFAULT NULL COMMENT 'Stats section data',
      `why_data` json DEFAULT NULL COMMENT 'Why section data',
      `about_data` json DEFAULT NULL COMMENT 'About section data',
      `testimonials_data` json DEFAULT NULL COMMENT 'Testimonials section data',
      `newsletter_data` json DEFAULT NULL COMMENT 'Newsletter section data',
      `platforms_data` json DEFAULT NULL COMMENT 'Platforms/Marketplaces section data',
      `footer_data` json DEFAULT NULL COMMENT 'Footer section data',
      `page_config` json DEFAULT NULL COMMENT 'Global page config',
      `seo_data` json DEFAULT NULL COMMENT 'SEO and Meta data',
      `Other_platform` json DEFAULT NULL,
      `show_other_platforms` tinyint(1) DEFAULT '1',
      PRIMARY KEY (`id`),
      UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->execute($sql);
    echo "✅ New table created successfully.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "====================================\n";
