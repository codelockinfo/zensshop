<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

function checkTable($tableName, $db) {
    try {
        echo "\nChecking $tableName table:\n";
        $columns = $db->fetchAll("DESCRIBE $tableName");
        foreach ($columns as $column) {
            echo $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Error checking $tableName: " . $e->getMessage() . "\n";
    }
}

checkTable('support_messages', $db);
checkTable('admin_notifications', $db);
checkTable('section_best_selling_products', $db);
