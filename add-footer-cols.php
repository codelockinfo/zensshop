<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$columns = [
    'footer_extra_content' => "MEDIUMTEXT DEFAULT NULL",
    'show_footer_extra' => "TINYINT(1) DEFAULT 0"
];

foreach ($columns as $name => $def) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM landing_pages LIKE '$name'");
        if ($check->rowCount() == 0) {
            $conn->query("ALTER TABLE landing_pages ADD COLUMN $name $def");
            echo "Added column $name.\n";
        } else {
            echo "Column $name already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error checking/adding $name: " . $e->getMessage() . "\n";
    }
}

echo "Footer schema update complete.\n";
