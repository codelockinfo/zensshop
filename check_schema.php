<?php
require 'classes/Database.php';
$db = Database::getInstance();
$columns = $db->fetchAll("SHOW COLUMNS FROM landing_pages");
foreach($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
