<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    $sql = "CREATE TABLE IF NOT EXISTS special_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        image VARCHAR(255) NOT NULL,
        link VARCHAR(255) DEFAULT '',
        button_text VARCHAR(255) DEFAULT 'Shop Now',
        display_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->execute($sql);
    echo "Table 'special_offers' created or already exists.<br>";
    
    // Check if table is empty, if so, seed it
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM special_offers");
    if ($count['count'] == 0) {
        $offers = [
            [
                'title' => 'Limited Time Deals',
                'image' => 'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=600&h=400&fit=crop',
                'link' => 'shop.php?filter=deals',
                'button_text' => 'Shop Now',
                'order' => 1
            ],
            [
                'title' => 'Glamorous Essence',
                'image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=400&fit=crop',
                'link' => 'shop.php?filter=glamorous',
                'button_text' => 'Shop Now',
                'order' => 2
            ],
            [
                'title' => 'Ethereal Beauty',
                'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&h=400&fit=crop',
                'link' => 'shop.php?filter=ethereal',
                'button_text' => 'Shop Now',
                'order' => 3
            ],
            [
                'title' => 'Delicate Sparkle',
                'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=400&fit=crop',
                'link' => 'shop.php?filter=sparkle',
                'button_text' => 'Shop Now',
                'order' => 4
            ]
        ];
        
        foreach ($offers as $offer) {
            $db->execute(
                "INSERT INTO special_offers (title, image, link, button_text, display_order) VALUES (?, ?, ?, ?, ?)",
                [$offer['title'], $offer['image'], $offer['link'], $offer['button_text'], $offer['order']]
            );
        }
        echo "Seeded special_offers with default data.<br>";
    }
    
    echo "Setup completed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
