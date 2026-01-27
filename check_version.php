<?php
require 'classes/Database.php';
$db = Database::getInstance();
$version = $db->fetchOne("SELECT VERSION() as v");
echo $version['v'];
