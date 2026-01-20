<?php
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

echo "Starting Database Migration...<br>";

try {
    // 1. Rename Tables
    $tables = [
        'home_best_selling_products' => 'section_best_selling_products',
        'home_trending_products' => 'section_trending_products'
    ];

    foreach ($tables as $old => $new) {
        // Check if old table exists
        $check = $db->fetchOne("SHOW TABLES LIKE '$old'");
        if ($check) {
            $db->execute("RENAME TABLE $old TO $new");
            echo "Renamed table '$old' to '$new'.<br>";
        } else {
            echo "Table '$old' not found (maybe already renamed).<br>";
        }
    }

    // 2. Create section_videos table
    $sqlVideos = "CREATE TABLE IF NOT EXISTS section_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        subtitle VARCHAR(255) DEFAULT NULL,
        video_url TEXT DEFAULT NULL,
        poster_url TEXT DEFAULT NULL,
        link_url VARCHAR(255) DEFAULT NULL,
        embed_code TEXT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->execute($sqlVideos);
    echo "Table 'section_videos' ensured.<br>";

    // 3. Migrate JSON data to Table
    $existing = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'home_videos_data'");
    if ($existing) {
        $videos = json_decode($existing['setting_value'], true);
        if (is_array($videos) && !empty($videos)) {
            // Check if table is empty
            $count = $db->fetchOne("SELECT COUNT(*) as c FROM section_videos")['c'];
            if ($count == 0) {
                foreach ($videos as $i => $v) {
                    $db->execute("INSERT INTO section_videos (title, subtitle, video_url, poster_url, link_url, embed_code, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                        $v['title'] ?? '',
                        $v['subtitle'] ?? '',
                        $v['video_url'] ?? '',
                        $v['poster_url'] ?? '',
                        $v['link'] ?? '', // Note key change 'link' -> 'link_url'
                        $v['embed_code'] ?? '',
                        $i
                    ]);
                }
                echo "Migrated " . count($videos) . " videos from site_settings to section_videos table.<br>";
            }
        }
        // Remove old setting? Maybe keep as backup for now? No, user wants clean.
        // But if I fail, I lose data. I'll keep it for now.
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "Migration Complete.";
?>
