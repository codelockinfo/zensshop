<?php
// sitemap.php - Generates a dynamic XML sitemap and caches to sitemap.xml
ob_start();

// Ensure correct Content-Type is sent
header('Content-Type: application/xml; charset=utf-8');

// Load database and config
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    $db = null;
}

$baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : 'https://homeprox.in';
if (strpos($baseUrl, 'homeprox.in') !== false) {
    $baseUrl = str_replace('http://', 'https://', $baseUrl);
}
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Static Pages
$staticPages = [
    '' => ['priority' => '1.0', 'freq' => 'daily'],
    'shop' => ['priority' => '0.9', 'freq' => 'daily'],
    'special-product' => ['priority' => '0.9', 'freq' => 'weekly'],
    'about' => ['priority' => '0.8', 'freq' => 'monthly'],
    'support' => ['priority' => '0.8', 'freq' => 'monthly'],
    'collections' => ['priority' => '0.7', 'freq' => 'monthly'],
    'blog' => ['priority' => '0.7', 'freq' => 'weekly'],
    'privacy-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'terms-conditions' => ['priority' => '0.5', 'freq' => 'monthly'],
    'shipping-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'return-refund-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
    'return-policy' => ['priority' => '0.5', 'freq' => 'monthly'],
];

foreach ($staticPages as $path => $meta) {
    $loc = !empty($path) ? $baseUrl . '/' . $path : $baseUrl . '/';
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>" . $meta['freq'] . "</changefreq>\n";
    echo "    <priority>" . $meta['priority'] . "</priority>\n";
    echo "  </url>\n";
}

if ($db) {
    // 2. Products
    try {
        $products = $db->fetchAll("SELECT slug, updated_at FROM products WHERE status = 'active'");
        if (!empty($products)) {
            foreach ($products as $prod) {
                $lastMod = !empty($prod['updated_at']) ? date('Y-m-d', strtotime($prod['updated_at'])) : $today;
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($baseUrl . '/product?slug=' . $prod['slug']) . "</loc>\n";
                echo "    <lastmod>{$lastMod}</lastmod>\n";
                echo "    <changefreq>daily</changefreq>\n";
                echo "    <priority>0.8</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }

    // 3. Categories
    try {
        $categories = $db->fetchAll("SELECT slug FROM categories");
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($baseUrl . '/shop?category=' . $cat['slug']) . "</loc>\n";
                echo "    <lastmod>{$today}</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>0.8</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }

    // 4. Blogs
    try {
        $blogs = $db->fetchAll("SELECT slug, updated_at FROM blogs WHERE status = 'published'");
        if (!empty($blogs)) {
            foreach ($blogs as $blog) {
                $lastMod = !empty($blog['updated_at']) ? date('Y-m-d', strtotime($blog['updated_at'])) : $today;
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($baseUrl . '/blog?slug=' . $blog['slug']) . "</loc>\n";
                echo "    <lastmod>{$lastMod}</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>0.7</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }

    // 5. Landing Pages
    try {
        $landingPages = $db->fetchAll("SELECT slug, updated_at FROM landing_pages");
        if (!empty($landingPages)) {
            foreach ($landingPages as $lp) {
                $lastMod = !empty($lp['updated_at']) ? date('Y-m-d', strtotime($lp['updated_at'])) : $today;
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($baseUrl . '/' . $lp['slug']) . "</loc>\n";
                echo "    <lastmod>{$lastMod}</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>0.8</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }

    // 6. Custom CMS Pages
    try {
        $cmsPages = $db->fetchAll("SELECT slug, updated_at FROM pages WHERE status = 'active'");
        if (!empty($cmsPages)) {
            foreach ($cmsPages as $page) {
                $lastMod = !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : $today;
                echo "  <url>\n";
                echo "    <loc>" . htmlspecialchars($baseUrl . '/' . $page['slug']) . "</loc>\n";
                echo "    <lastmod>{$lastMod}</lastmod>\n";
                echo "    <changefreq>monthly</changefreq>\n";
                echo "    <priority>0.6</priority>\n";
                echo "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}

echo '</urlset>';

$xmlContent = ob_get_contents();
if (!empty($xmlContent)) {
    @file_put_contents(__DIR__ . '/sitemap.xml', $xmlContent);
}
ob_end_flush();
