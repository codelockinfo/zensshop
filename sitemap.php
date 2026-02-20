<?php
// sitemap.php - Generates a dynamic XML sitemap
// Ensures correct Content-Type is sent
header('Content-Type: application/xml; charset=utf-8');

// Load database and config
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$baseUrl = getBaseUrl();

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// 1. Static Pages
// Define priority and change frequency for main pages
$staticPages = [
    '' => ['priority' => '1.0', 'freq' => 'weekly'],
    'shop' => ['priority' => '0.9', 'freq' => 'daily'],
    'about' => ['priority' => '0.8', 'freq' => 'monthly'],
    'support' => ['priority' => '0.8', 'freq' => 'monthly'],
    'login' => ['priority' => '0.6', 'freq' => 'monthly'],
    'register' => ['priority' => '0.6', 'freq' => 'monthly'],
    'wishlist' => ['priority' => '0.5', 'freq' => 'weekly'],
    'cart' => ['priority' => '0.5', 'freq' => 'weekly'],
    'special-product' => ['priority' => '0.5', 'freq' => 'weekly'],
    'account' => ['priority' => '0.5', 'freq' => 'weekly'],
    'blog' => ['priority' => '0.5', 'freq' => 'weekly'],
    'privacy-policy' => ['priority' => '0.6', 'freq' => 'monthly'],
    'terms-conditions' => ['priority' => '0.6', 'freq' => 'monthly'],
    'shipping-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'return-refund-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'return-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'category' => ['priority' => '0.5', 'freq' => 'monthly'],
    'collections' => ['priority' => '0.5', 'freq' => 'monthly'],
];

foreach ($staticPages as $path => $meta) {
    echo '<url>';
    echo '<loc>' . htmlspecialchars($baseUrl . '/' . $path) . '</loc>';
    echo '<changefreq>' . $meta['freq'] . '</changefreq>';
    echo '<priority>' . $meta['priority'] . '</priority>';
    echo '</url>';
}

// 2. Products
// Get all active products
try {
    $products = $db->fetchAll("SELECT slug, updated_at FROM products WHERE status = 'active'");
    foreach ($products as $prod) {
        echo '<url>';
        echo '<loc>' . htmlspecialchars($baseUrl . '/product?slug=' . $prod['slug']) . '</loc>';
        $lastMod = !empty($prod['updated_at']) ? date('Y-m-d', strtotime($prod['updated_at'])) : date('Y-m-d');
        echo '<lastmod>' . $lastMod . '</lastmod>';
        echo '<changefreq>daily</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
} catch (Exception $e) {
    // Ignore errors to ensure partial sitemap generation
}

// 3. Categories
// Get all categories
try {
    $categories = $db->fetchAll("SELECT slug FROM categories");
    foreach ($categories as $cat) {
        echo '<url>';
        echo '<loc>' . htmlspecialchars($baseUrl . '/shop?category=' . $cat['slug']) . '</loc>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
} catch (Exception $e) {
    // Ignore errors
}

// 4. Blogs (if enabled)
// Get all published blog posts
try {
    // Check if table exists first by simple query or just try
    $blogs = $db->fetchAll("SELECT slug, updated_at FROM blogs WHERE status = 'published'");
    foreach ($blogs as $blog) {
        echo '<url>';
        echo '<loc>' . htmlspecialchars($baseUrl . '/blog?slug=' . $blog['slug']) . '</loc>';
        $lastMod = !empty($blog['updated_at']) ? date('Y-m-d', strtotime($blog['updated_at'])) : date('Y-m-d');
        echo '<lastmod>' . $lastMod . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.7</priority>';
        echo '</url>';
    }
} catch (Exception $e) {
    // Table might not exist or other error
}

// 5. CMS Pages (if enabled)
// Get published pages
try {
    // Assuming 'pages' table exists (admin/pages.php exists)
    $cmsPages = $db->fetchAll("SELECT slug, updated_at FROM pages WHERE status = 'active'");
    foreach ($cmsPages as $page) {
        echo '<url>';
        echo '<loc>' . htmlspecialchars($baseUrl . '/' . $page['slug']) . '</loc>';
        $lastMod = !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : date('Y-m-d');
        echo '<lastmod>' . $lastMod . '</lastmod>';
        echo '<changefreq>monthly</changefreq>';
        echo '<priority>0.6</priority>';
        echo '</url>';
    }
} catch (Exception $e) {
    // Ignore errors
}

echo '</urlset>';
?>