<?php
require_once 'includes/functions.php';
$db = Database::getInstance();
try {
    $rows = $db->fetchAll("SELECT * FROM section_videos");
    echo "Count: " . count($rows) . "\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
