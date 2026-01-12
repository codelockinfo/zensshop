<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
try {
    $columns = $db->fetchAll("DESCRIBE orders");
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
