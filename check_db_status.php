<?php
require_once 'classes/Database.php';
$db = Database::getInstance();

echo "Menus:\n";
$menus = $db->fetchAll("SELECT * FROM menus");
foreach ($menus as $m) {
    echo "ID: " . $m['id'] . " | Name: " . $m['name'] . " | Location: " . ($m['location'] ?? 'NULL') . "\n";
}

echo "\nSite Settings:\n";
$settings = $db->fetchAll("SELECT * FROM site_settings");
foreach ($settings as $s) {
    echo $s['setting_key'] . ": " . substr($s['setting_value'], 0, 50) . "...\n";
}
?>
