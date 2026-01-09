<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
try {
    $columns = $db->fetchAll("DESCRIBE landing_pages");
    echo "Columns in landing_pages table:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
