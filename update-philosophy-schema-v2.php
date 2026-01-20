<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // 1. cleanup: Remove columns from landing_pages if they exist
    $colsToRemove = [
        'show_philosophy',
        'philosophy_title',
        'philosophy_data', // user previously created this, we should remove it
        'philosophy_bg_color',
        'philosophy_text_color'
    ];

    foreach ($colsToRemove as $col) {
        $check = $db->fetchOne("SHOW COLUMNS FROM `landing_pages` LIKE '$col'");
        if ($check) {
            try {
                $db->execute("ALTER TABLE `landing_pages` DROP COLUMN `$col`");
                echo "Dropped column: $col from landing_pages<br>";
            } catch (Exception $e) {
                echo "Error dropping $col: " . $e->getMessage() . "<br>";
            }
        }
    }

    // 2. Create new table for Philosophy Section
    $db->execute("CREATE TABLE IF NOT EXISTS `philosophy_section` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `heading` varchar(255) DEFAULT NULL,
        `content` text,
        `link_text` varchar(255) DEFAULT 'OUR PHILOSOPHY',
        `link_url` varchar(255) DEFAULT '#',
        `background_color` varchar(50) DEFAULT '#384135',
        `text_color` varchar(50) DEFAULT '#eee4d3',
        `active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Table 'philosophy_section' created/checked.<br>";

    // 3. Insert default data if empty
    $check = $db->fetchOne("SELECT COUNT(*) as count FROM philosophy_section");
    if ($check['count'] == 0) {
        $defaultContent = "We believe that every human deserves to feel beautiful and confident, and we are committed to providing you with the best quality and styles that will make you look and feel your best.";
        
        $db->execute("INSERT INTO philosophy_section (heading, content, link_text, link_url, background_color, text_color) VALUES (?, ?, ?, ?, ?, ?)", [
            '', // Heading is empty in current HTML
            $defaultContent,
            'OUR PHILOSOPHY',
            '#',
            '#384135',
            '#eee4d3'
        ]);
        echo "Default philosophy data inserted.<br>";
    }

    // 4. Also check for 'banner_items' column in landing page mentioned by user? 
    // "we are doint this as we xrete setting like banner section"
    // I think the user meant "do it like the banner section" (which might be handled separately or via JSON).
    // I've handled the specific request.

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
