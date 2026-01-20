<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Create new table for Newsletter Section (Homepage)
    $db->execute("CREATE TABLE IF NOT EXISTS `section_newsletter` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `background_image` varchar(255) DEFAULT NULL,
        `heading` varchar(255) DEFAULT 'Join our family',
        `subheading` text,
        `button_text` varchar(50) DEFAULT 'Subscribe',
        `footer_content` text,
        `active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Table 'section_newsletter' created/checked.<br>";

    // Insert default data if empty
    $check = $db->fetchOne("SELECT COUNT(*) as count FROM section_newsletter");
    if ($check['count'] == 0) {
        $defaultFooter = 'Your personal data will be used to support your experience throughout this website, and for other purposes described in our <a href="#" style="text-decoration:underline;">Privacy Policy</a>.';
        
        $db->execute("INSERT INTO section_newsletter (heading, subheading, button_text, footer_content) VALUES (?, ?, ?, ?)", [
            'Join our family',
            'Promotions, new products and sales. Directly to your inbox.',
            'Subscribe',
            $defaultFooter
        ]);
        echo "Default newsletter data inserted.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
