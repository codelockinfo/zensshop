<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    $menu = $db->fetchOne("SELECT id FROM menus WHERE location = 'header_main'");
    if (!$menu) {
        $menuId = $db->insert("INSERT INTO menus (name, location) VALUES ('Main Header Menu', 'header_main')");
    } else {
        $menuId = $menu['id'];
        // Clear existing items to re-seed
        $db->execute("DELETE FROM menu_items WHERE menu_id = ?", [$menuId]);
    }

    // 1. Home
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Home', '/', 1)", [$menuId]);

    // 2. Shop
    $shopId = $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Shop', 'collections.php', 2)", [$menuId]);
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id) VALUES (?, 'Collections', 'collections.php', 1, ?)", [$menuId, $shopId]);
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id) VALUES (?, 'All Products', 'shop.php', 2, ?)", [$menuId, $shopId]);

    // 3. Products (The Mega Menu Trigger)
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Products', 'products.php', 3)", [$menuId]);

    // 4. Pages
    $pagesId = $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Pages', 'pages.php', 4)", [$menuId]);
    $subPages = [
        ['About us', 'about.php'],
        ['Contact us', 'contact.php'],
        ['Sale', 'sale.php'],
        ['Our store', 'store.php'],
        ['FAQ', 'faq.php'],
        ['Wishlist', 'wishlist.php'],
        ['Compare', 'compare.php'],
        ['Store location', 'location.php'],
        ['Recently viewed products', 'recently-viewed.php']
    ];
    $i = 1;
    foreach ($subPages as $sp) {
        $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id) VALUES (?, ?, ?, ?, ?)", 
            [$menuId, $sp[0], $sp[1], $i++, $pagesId]);
    }

    // 5. Blog
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Blog', 'blog.php', 5)", [$menuId]);

    // 6. Product pages! (This one was dynamic in code, but we can add it as a parent item)
    // Actually, the PHP code loops `landing_pages` table.
    // We can add a "Product pages!" item, and the Code will detect it and append the dynamic sub-list.
    $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order) VALUES (?, 'Product pages!', '#', 6)", [$menuId]);

    echo "Header menu seeded successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
