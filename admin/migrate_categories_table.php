<?php
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();
try {
    $check = $db->fetchOne("SHOW TABLES LIKE 'homepage_categories'");
    if ($check) {
        $db->execute("RENAME TABLE homepage_categories TO section_categories");
        echo "Table renamed successfully.";
    } else {
        echo "Table homepage_categories not found (maybe already renamed).";
    }
} catch (Exception $e) { 
    echo "Error: " . $e->getMessage(); 
}
?>
