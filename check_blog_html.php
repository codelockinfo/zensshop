<?php
require_once 'classes/Database.php';
$db = Database::getInstance();
$blog = $db->fetchOne("SELECT content FROM blogs WHERE slug = 'test'");
echo htmlspecialchars($blog['content']);
?>
