<?php
require_once 'includes/functions.php';
$db = Database::getInstance();
try {
    $row = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'home_videos_data'");
    if ($row) {
        echo "Found JSON data in site_settings.\n";
        echo $row['setting_value'];
    } else {
        echo "No data in site_settings either.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
