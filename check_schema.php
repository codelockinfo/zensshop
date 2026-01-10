<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
$rows = $db->fetchAll('DESCRIBE landing_pages');
foreach($rows as $r) {
    echo str_pad($r['Field'], 25) . ": " . $r['Type'] . "\n";
}
