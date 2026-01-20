<?php
require_once __DIR__ . '/includes/functions.php';
$db = Database::getInstance();
$rows = $db->fetchAll("SELECT * FROM section_videos");
echo "Total Rows: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " | Order: " . $r['sort_order'] . " | Title: " . $r['title'] . "\n";
}
