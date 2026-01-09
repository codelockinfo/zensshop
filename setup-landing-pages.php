<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    $db->execute("CREATE TABLE IF NOT EXISTS `landing_pages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `slug` varchar(255) NOT NULL,
        `product_id` int(11) NOT NULL,
        
        -- Customization
        `hero_title` varchar(255) DEFAULT NULL,
        `hero_subtitle` varchar(255) DEFAULT NULL,
        `hero_description` text DEFAULT NULL,
        `hero_image` varchar(255) DEFAULT NULL,
        `theme_color` varchar(50) DEFAULT '#5F8D76',
        
        -- Section Toggles
        `show_stats` tinyint(1) DEFAULT 1,
        `show_why` tinyint(1) DEFAULT 1,
        `show_about` tinyint(1) DEFAULT 1,
        `show_products` tinyint(1) DEFAULT 1,
        `show_testimonials` tinyint(1) DEFAULT 1,
        `show_newsletter` tinyint(1) DEFAULT 1,
        `show_brands` tinyint(1) DEFAULT 1,
        
        -- Nav & Footer
        `nav_links` json DEFAULT NULL COMMENT 'Array of {label, url}',
        `footer_data` json DEFAULT NULL COMMENT 'Footer specific text/links',
        
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Table 'landing_pages' created successfully!<br>";
    
    // Insert a demo page if empty
    $count = $db->fetchOne("SELECT COUNT(*) as c FROM landing_pages");
    if ($count['c'] == 0) {
        $firstProduct = $db->fetchOne("SELECT id FROM products WHERE status = 'active' LIMIT 1");
        if ($firstProduct) {
            $db->insert("INSERT INTO landing_pages (name, slug, product_id, hero_title, hero_description, theme_color) VALUES (?, ?, ?, ?, ?, ?)", [
                'Default Campaign',
                'default',
                $firstProduct['id'],
                'Natural Inner Beauty',
                'Provide intense hydration for those with dry skin.',
                '#5F8D76'
            ]);
            echo "Default landing page inserted.<br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
