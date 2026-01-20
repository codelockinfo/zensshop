<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Create new table for Feature Section Cards
    $db->execute("CREATE TABLE IF NOT EXISTS `section_features` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `icon` text,
        `heading` varchar(255) DEFAULT NULL,
        `content` text,
        `sort_order` int(11) DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Table 'section_features' created/checked.<br>";

    // Check if empty, maybe add default dummy data?
    // User wants "max 3 card", let's not auto-fill to let them start fresh or maybe fill 1 example.
    $check = $db->fetchOne("SELECT COUNT(*) as count FROM section_features");
    if ($check['count'] == 0) {
        $defaultSvg = '<svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        
        $db->execute("INSERT INTO section_features (icon, heading, content, sort_order) VALUES (?, ?, ?, ?)", [
            $defaultSvg,
            'Premium Quality',
            'We use only the finest responsibly sourced materials.',
            0
        ]);
        echo "Default feature inserted.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
