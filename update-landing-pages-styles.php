<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add columns for section-specific styling (Background & Text Colors)
    $styleQueries = [
        "ADD COLUMN `hero_bg_color` varchar(50) DEFAULT '#E8F0E9' AFTER `hero_image`",
        "ADD COLUMN `hero_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `hero_bg_color`",
        
        "ADD COLUMN `stats_bg_color` varchar(50) DEFAULT '#F3F6F4' AFTER `stats_data`",
        "ADD COLUMN `stats_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `stats_bg_color`",
        
        "ADD COLUMN `why_bg_color` varchar(50) DEFAULT '#FFFFFF' AFTER `why_data`",
        "ADD COLUMN `why_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `why_bg_color`",
        
        "ADD COLUMN `about_bg_color` varchar(50) DEFAULT '#F9F9F9' AFTER `about_image`",
        "ADD COLUMN `about_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `about_bg_color`",
        
        "ADD COLUMN `testimonials_bg_color` varchar(50) DEFAULT '#FFFFFF' AFTER `testimonials_title`",
        "ADD COLUMN `testimonials_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `testimonials_bg_color`",
        
        "ADD COLUMN `newsletter_bg_color` varchar(50) DEFAULT '#E8F0E9' AFTER `newsletter_text`",
        "ADD COLUMN `newsletter_text_color` varchar(50) DEFAULT '#4A4A4A' AFTER `newsletter_bg_color`"
    ];

    foreach ($styleQueries as $query) {
        try {
            if (preg_match('/ADD COLUMN `([^`]+)`/', $query, $matches)) {
                $colName = $matches[1];
                $check = $db->fetchOne("SHOW COLUMNS FROM `landing_pages` LIKE '$colName'");
                
                if (!$check) {
                    $db->execute("ALTER TABLE `landing_pages` $query");
                    echo "Added styling column: $colName<br>";
                } else {
                    echo "Column $colName already exists. Skipped.<br>";
                }
            }
        } catch (Exception $e) {
            echo "Error processing $query: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Schema updated with Section Styling options!<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
