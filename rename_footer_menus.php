<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

try {
    $updates = [
        'footer_company' => 'Our Company',
        'footer_quick_links' => 'Quick Links',
        'footer_categories' => 'Shop Categories',
        'footer_social' => 'Follow Us'
    ];

    foreach ($updates as $loc => $name) {
        $db->execute("UPDATE menus SET name = ? WHERE location = ?", [$name, $loc]);
    }
    echo "Menus renamed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
