<?php
/**
 * Dynamic Router
 * Intelligently routes URLs to the correct handler:
 * 1. Custom Pages (from pages table)
 * 2. Landing Pages (from landing_pages table)
 * 3. 404 Not Found
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/classes/Database.php';

if (!function_exists('getBaseUrl')) {
    require_once __DIR__ . '/includes/functions.php';
}

// Get the slug from URL
$slug = $_GET['slug'] ?? '';

// Sanitize the slug
$slug = preg_replace('/[^a-zA-Z0-9-_]/', '', $slug);

if (empty($slug)) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
    exit;
}

$db = Database::getInstance();
$storeId = getCurrentStoreId();

// 1. Check if it's a Custom Page (highest priority)
$page = $db->fetchOne(
    "SELECT * FROM pages WHERE slug = ? AND (store_id = ? OR store_id IS NULL) AND status = 'active' ORDER BY store_id DESC LIMIT 1",
    [$slug, $storeId]
);

if ($page) {
    // Route to page.php
    $_GET['slug'] = $slug;
    require __DIR__ . '/page.php';
    exit;
}

// 2. Check if it's a Landing Page (special product page)
$landingPage = $db->fetchOne(
    "SELECT * FROM landing_pages WHERE slug = ? AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1",
    [$slug, $storeId]
);

if ($landingPage) {
    // Route to special-product.php
    $_GET['page'] = $slug;
    require __DIR__ . '/special-product.php';
    exit;
}

// 3. Check if it's a Blog Post
$blog = $db->fetchOne(
    "SELECT * FROM blogs WHERE slug = ? AND status = 'published' AND (store_id = ? OR store_id IS NULL) LIMIT 1",
    [$slug, $storeId]
);

if ($blog) {
    // Route to blog-post.php
    $_GET['slug'] = $slug;
    require __DIR__ . '/blog-post.php';
    exit;
}

// 4. Nothing found - 404
http_response_code(404);
require __DIR__ . '/not-found.php';
exit;
?>
