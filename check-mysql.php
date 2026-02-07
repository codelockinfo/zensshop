<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();
try {
    $res = $db->fetchOne("SELECT VERSION() as v");
    echo "MySQL Version: " . $res['v'] . "\n";
    
    $jsonValidRes = $db->fetchOne("SELECT JSON_VALID('{}') as jv");
    echo "JSON_VALID exists: " . ($jsonValidRes !== false ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
