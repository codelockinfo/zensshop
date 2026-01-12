<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

try {
    echo "Converting to Single Footer Menu Structure...\n";

    // 1. Delete all existing menus associated with footer (to clean up)
    // We will recreate them as a single structure.
    $db->execute("DELETE FROM menus WHERE location LIKE 'footer_%'");
    $db->execute("DELETE FROM menus WHERE name LIKE 'Footer%'");

    // 2. Create ONE Main Footer Menu
    $menuId = $db->insert("INSERT INTO menus (name, location) VALUES ('Footer Menu', 'footer_main')");
    echo "Created 'Footer Menu' (ID: $menuId)\n";

    // 3. Define the Columns (Top Level Items) and their Links (Children)
    $structure = [
        'Our Company' => [
            ['label' => 'Search', 'url' => 'search.php'],
            ['label' => 'Contact', 'url' => 'contact.php'],
            ['label' => 'About Us', 'url' => 'about.php'],
            ['label' => 'Terms & Conditions', 'url' => 'terms.php'],
            ['label' => 'Privacy Policy', 'url' => 'privacy.php'],
        ],
        'Quick Links' => [
            ['label' => 'Track Orders', 'url' => 'track-order.php'],
            ['label' => 'Returns', 'url' => 'returns.php'],
            ['label' => 'Shipping Info', 'url' => 'shipping.php'],
            ['label' => 'Help Center', 'url' => 'help.php'],
        ],
        'Shop Categories' => [
            ['label' => 'Men', 'url' => 'category.php?id=men'],
            ['label' => 'Women', 'url' => 'category.php?id=women'],
            ['label' => 'Accessories', 'url' => 'category.php?id=accessories'],
            ['label' => 'Sale', 'url' => 'sale.php'],
        ],
        'Follow Us' => [
            // Social links often need specific handling, but we'll add them as items for now
            // Or we key off the title "Follow Us" in the frontend to show icons.
            // Let's add them as placeholder links so the user sees them.
            ['label' => 'Facebook', 'url' => '#', 'image_path' => ''], 
            ['label' => 'Instagram', 'url' => '#', 'image_path' => ''],
        ]
    ];

    $sortOrder = 1;
    foreach ($structure as $columnTitle => $items) {
        // Create Column Header (Top Level)
        $parentId = $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, ?, '#', ?)", 
            [$menuId, $columnTitle, $sortOrder++]);
        
        // Create Links (Children)
        $childOrder = 1;
        foreach ($items as $item) {
            $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id) VALUES (?, ?, ?, ?, ?)", 
                [$menuId, $item['label'], $item['url'], $childOrder++, $parentId]);
        }
    }

    echo "Footer menu structure created successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
