<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add section_order column to store the ID order of sections
    $query = "ADD COLUMN `section_order` json DEFAULT NULL AFTER `banner_sections_json`";
    
    // Check if exists
    $check = $db->fetchOne("SHOW COLUMNS FROM `landing_pages` LIKE 'section_order'");
    
    if (!$check) {
        $db->execute("ALTER TABLE `landing_pages` $query");
        echo "Added column: section_order<br>";
    } else {
        echo "Column section_order already exists.<br>";
    }
    
    // Set default order for existing pages if needed
    // The default IDs we will use are: secHeaders, secBanner, secStats, secWhy, secAbout, secTesti, secNews, secFooter
    // But for frontend we just need the keys like 'headers', 'banner', 'stats', etc.
    // Let's stick to simple keys: 'header', 'banner', 'stats', 'why', 'about', 'testimonials', 'newsletter', 'footer'
    
} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
