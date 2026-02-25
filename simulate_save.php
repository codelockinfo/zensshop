<?php
require_once 'classes/Database.php';
$db = Database::getInstance();

$_POST = [
    'section_heading' => 'Test Heading',
    'section_subheading' => 'Test Subheading',
    'title' => ['New Category Test'],
    'link' => ['test-link'],
    'sort_order' => [0],
    'id' => [''],
    'layout_type' => 'grid',
    'mobile_size' => '2'
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['store_id'] = 'D2EA2917';

$storeId = $_SESSION['store_id'];
$baseUrl = 'http://localhost/zensshop';
$error = '';
$section_heading = $_POST['section_heading'];
$section_subheading = $_POST['section_subheading'];
$titles = $_POST['title'];
$links = $_POST['link'];
$orders = $_POST['sort_order'];
$ids = $_POST['id'];
$layout_type = $_POST['layout_type'];
$mobile_size = $_POST['mobile_size'];

try {
    for ($i = 0; $i < count($titles); $i++) {
        $id = $ids[$i] ?? null;
        $title = trim((string)$titles[$i]);
        $link = trim((string)($links[$i] ?? ''));
        $order = (int)($orders[$i] ?? 0);
        
        if (empty($title)) continue;
        
        $imagePath = '';
        $exists = false;
        if (!empty($id)) {
            $exists = $db->fetchOne("SELECT id FROM section_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
        }

        if ($exists) {
            echo "Updating $id\n";
        } else {
            echo "Inserting new\n";
            $db->execute(
                "INSERT INTO section_categories (title, link, sort_order, image, heading, subheading, layout_type, mobile_size, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $link, $order, $imagePath, $section_heading, $section_subheading, $layout_type, $mobile_size, $storeId]
            );
        }
    }
    echo "Done simulate\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
