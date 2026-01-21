<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY sort_order ASC");
foreach ($categories as $cat) {
    echo "ID: " . $p['id'] . "\n";
    echo "Status: " . $cat['status'] . "\n";
    echo "Span Class: " . ($cat['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') . "\n";
    echo "Ucfirst: " . ucfirst($cat['status']) . "\n";
    echo "------------------\n";
}
