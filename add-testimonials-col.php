<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
try {
    // Check if column exists
    $check = $db->fetchOne("SHOW COLUMNS FROM `landing_pages` LIKE 'testimonials_data'");

    if (!$check) {
        // Add testimonials_data column
        $db->execute("ALTER TABLE `landing_pages` ADD COLUMN `testimonials_data` json DEFAULT NULL AFTER `testimonials_title`");
        echo "Added testimonials_data column successfully.";
    } else {
        echo "Column testimonials_data already exists. Skipped adding.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
