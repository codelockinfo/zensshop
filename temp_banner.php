<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$banners = $db->fetchAll('SELECT id, heading, subheading, button_text, image_desktop, store_id FROM banners');
print_r($banners);
