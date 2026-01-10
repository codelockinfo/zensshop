<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$columns = [
    'meta_title' => "VARCHAR(255) DEFAULT NULL",
    'meta_description' => "TEXT DEFAULT NULL",
    'custom_schema' => "MEDIUMTEXT DEFAULT NULL"
];

foreach ($columns as $name => $def) {
    try {
        // Use try-catch with direct ALTER to be robust
        // Or check correctly
        $check = $conn->query("SHOW COLUMNS FROM landing_pages LIKE '$name'");
        $exists = $check->rowCount() > 0; // Correct PDO method
        
        if (!$exists) {
            $conn->query("ALTER TABLE landing_pages ADD COLUMN $name $def");
            echo "Added column $name.\n";
        } else {
            echo "Column $name already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/adding $name: " . $e->getMessage() . "\n";
    }
}

echo "Schema update check complete.\n";
