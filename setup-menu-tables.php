<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Create menus table
    $db->query("CREATE TABLE IF NOT EXISTS menus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(50) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create menu_items table
    $db->query("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        label VARCHAR(255) NOT NULL,
        url VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
    )");

    // Create site_settings table for footer content etc
    $db->query("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT
    )");

    // Seed default menus if they don't exist
    $menus = [
        ['name' => 'Main Header Menu', 'location' => 'header_main'],
        ['name' => 'Footer - About Us', 'location' => 'footer_about'],
        ['name' => 'Footer - Our Company', 'location' => 'footer_company'],
        ['name' => 'Footer - Quick Links', 'location' => 'footer_quick_links'],
        ['name' => 'Footer - Shop Categories', 'location' => 'footer_categories'],
        ['name' => 'Footer - Follow Us', 'location' => 'footer_social'] // For social links if treated as menu, or we can use settings
    ];

    foreach ($menus as $menu) {
        $exists = $db->fetchOne("SELECT id FROM menus WHERE location = ?", [$menu['location']]);
        if (!$exists) {
            $db->query("INSERT INTO menus (name, location) VALUES (?, ?)", [$menu['name'], $menu['location']]);
        }
    }

    // Seed default site settings (Footer content)
    $defaults = [
        'footer_logo_text' => 'About us',
        'footer_description' => 'We only carry designs we believe in ethically and aesthetically – original, authentic pieces that are made to last.',
        'footer_address' => 'Street Address 2571 Oakridge',
        'footer_phone' => '+1 (973) 435-3638',
        'footer_email' => 'info@fashionwomen.com',
        'footer_facebook' => '#',
        'footer_instagram' => '#',
        'footer_tiktok' => '#',
        'footer_youtube' => '#',
        'footer_pinterest' => '#'
    ];

    foreach ($defaults as $key => $val) {
        $exists = $db->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = ?", [$key]);
        if (!$exists) {
            $db->query("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $val]);
        }
    }

    echo "Tables menus, menu_items, and site_settings created and seeded successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
