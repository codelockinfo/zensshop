<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
$columns = $db->fetchAll("DESCRIBE orders");
foreach ($columns as $c) echo $c['Field'] . "\n";
