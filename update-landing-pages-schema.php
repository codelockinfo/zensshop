<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add new columns for detailed section customization if they don't exist
    $alterQueries = [
        "ADD COLUMN `stats_data` json DEFAULT NULL AFTER `show_stats`",
        "ADD COLUMN `why_title` varchar(255) DEFAULT NULL AFTER `show_why`",
        "ADD COLUMN `why_data` json DEFAULT NULL AFTER `why_title`",
        "ADD COLUMN `about_title` varchar(255) DEFAULT NULL AFTER `show_about`",
        "ADD COLUMN `about_text` text DEFAULT NULL AFTER `about_title`",
        "ADD COLUMN `about_image` varchar(255) DEFAULT NULL AFTER `about_text`",
        "ADD COLUMN `products_title` varchar(255) DEFAULT NULL AFTER `show_products`",
        "ADD COLUMN `testimonials_title` varchar(255) DEFAULT NULL AFTER `show_testimonials`", 
        "ADD COLUMN `newsletter_title` varchar(255) DEFAULT NULL AFTER `show_newsletter`",
        "ADD COLUMN `newsletter_text` varchar(255) DEFAULT NULL AFTER `newsletter_title`"
    ];

    foreach ($alterQueries as $query) {
        try {
            // Extract column name check
            if (preg_match('/ADD COLUMN `([^`]+)`/', $query, $matches)) {
                $colName = $matches[1];
                $check = $db->fetchOne("SHOW COLUMNS FROM `landing_pages` LIKE '$colName'");
                
                if (!$check) {
                    $db->execute("ALTER TABLE `landing_pages` $query");
                    echo "Added column: $colName<br>";
                } else {
                    echo "Column $colName already exists. Skipped.<br>";
                }
            }
        } catch (Exception $e) {
             echo "Error processing $query: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Table schema updated for detailed content customization!<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
