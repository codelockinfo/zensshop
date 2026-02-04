<?php
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
try {
    $count = $db->fetchOne("SELECT COUNT(*) as c FROM section_videos")['c'];

    if ($count == 0) {
        echo "Restoring demo videos...\n";
        $videos = [
            [
                'title' => 'Limited Time Deals',
                'subtitle' => 'SPECIAL 50% OFF',
                'video_url' => '',
                'poster_url' => 'https://demo-CookPro.myshopify.com/cdn/shop/files/jew2_1.webp?v=1739185331&width=480',
                'link_url' => 'shop.php?filter=deals',
                'embed_code' => ''
            ],
            [
                'title' => 'Glamorous Essence',
                'subtitle' => 'EXCLUSIVE DESIGNS',
                'video_url' => 'https://demo-CookPro.myshopify.com/cdn/shop/videos/c/vp/6cae339d4a154d37a6ff2daf5d28d3a4/6cae339d4a154d37a6ff2daf5d28d3a4.HD-720p-2.1Mbps-42378036.mp4?v=0',
                'poster_url' => 'https://demo-CookPro.myshopify.com/cdn/shop/files/preview_images/6cae339d4a154d37a6ff2daf5d28d3a4.thumbnail.0000000000_small.jpg?v=1739186184',
                'link_url' => 'shop.php?filter=glamorous',
                'embed_code' => ''
            ],
            [
                'title' => 'Ethereal Beauty',
                'subtitle' => 'HANDCRAFTED PERFECTION',
                'video_url' => '',
                'poster_url' => 'https://demo-CookPro.myshopify.com/cdn/shop/files/jew2_2.webp?v=1739185348&width=480',
                'link_url' => 'shop.php?filter=ethereal',
                'embed_code' => ''
            ],
            [
                'title' => 'Delicate Sparkle',
                'subtitle' => 'GRACEFUL BEAUTY',
                'video_url' => '',
                'poster_url' => 'https://demo-CookPro.myshopify.com/cdn/shop/files/jew2_3.webp?v=1739185348&width=480',
                'link_url' => 'shop.php?filter=sparkle',
                'embed_code' => ''
            ]
        ];
        
        $sort = 0;
        foreach ($videos as $v) {
            $db->execute("INSERT INTO section_videos (title, subtitle, video_url, poster_url, link_url, embed_code, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                $v['title'], $v['subtitle'], $v['video_url'], $v['poster_url'], $v['link_url'], $v['embed_code'], $sort++
            ]);
        }
        echo "Restoration complete.";
    } else {
        echo "Table already has " . $count . " videos.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
