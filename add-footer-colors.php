<?php
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->query("SHOW COLUMNS FROM landing_pages LIKE 'footer_extra_bg'");
    if ($stmt->rowCount() == 0) {
        $conn->query("ALTER TABLE landing_pages ADD COLUMN footer_extra_bg VARCHAR(7) DEFAULT '#f8f9fa' AFTER footer_extra_content");
        echo "Added footer_extra_bg column.\n";
    } else {
        echo "footer_extra_bg column already exists.\n";
    }
    $stmt = $conn->query("SHOW COLUMNS FROM landing_pages LIKE 'footer_extra_text'");
    if ($stmt->rowCount() == 0) {
        $conn->query("ALTER TABLE landing_pages ADD COLUMN footer_extra_text VARCHAR(7) DEFAULT '#333333' AFTER footer_extra_bg");
        echo "Added footer_extra_text column.\n";
    } else {
        echo "footer_extra_text column already exists.\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
