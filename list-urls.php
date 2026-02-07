<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
try {
    $rows = $db->fetchAll("SELECT DISTINCT store_url FROM users");
    foreach ($rows as $row) {
        echo $row['store_url'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
