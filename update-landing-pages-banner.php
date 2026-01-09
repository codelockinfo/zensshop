<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Add new columns for Banner Section
    $alterQueries = [
        "ADD COLUMN `show_banner` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_newsletter`",
        "ADD COLUMN `banner_image` varchar(255) DEFAULT NULL AFTER `show_banner`",
        "ADD COLUMN `banner_mobile_image` varchar(255) DEFAULT NULL AFTER `banner_image`",
        "ADD COLUMN `banner_heading` varchar(255) DEFAULT NULL AFTER `banner_mobile_image`",
        "ADD COLUMN `banner_text` text DEFAULT NULL AFTER `banner_heading`",
        "ADD COLUMN `banner_btn_text` varchar(255) DEFAULT NULL AFTER `banner_text`",
        "ADD COLUMN `banner_btn_link` varchar(255) DEFAULT NULL AFTER `banner_btn_text`"
    ];

    foreach ($alterQueries as $query) {
        try {
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
            echo "Error executing $query: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Table schema updated for Banner Section!<br>";

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
