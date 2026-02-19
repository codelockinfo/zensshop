<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
$columns = $db->fetchAll("DESCRIBE orders");
echo json_encode($columns, JSON_PRETTY_PRINT);
