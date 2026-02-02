<?php
// Frontend Page Viewer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/classes/Database.php';

// Only require functions if not already available
if (!function_exists('getBaseUrl')) {
    require_once __DIR__ . '/includes/functions.php';
}

// Determine the slug from query string or URL rewrite
$slug = $_GET['slug'] ?? '';

// Basic sanitization
$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $slug);

if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php'; // Assuming you have a 404 page
    exit;
}

$db = Database::getInstance();

// We need to find the page. Since we don't know the store_id from session (guest), 
// we might need to deduce it from domain or just find the first match if domain mapping isn't set up.
// For now, let's assume single-domain or we pick the first partial match. 
// Ideally, the system should strictly know the store from the context.
// Assuming we are in a context where we might know the store (e.g. from session if we set it on visit)
// Or we just try to find ANY active page with this slug.
// If multiple stores use the same slug, this is ambiguous without domain parsing.
// Let's assume the session has store_id if visited before, or we pick the first one.
// Use dynamic store detection
$storeId = getCurrentStoreId();

$page = $db->fetchOne(
    "SELECT * FROM pages WHERE slug = ? AND (store_id = ? OR store_id IS NULL) AND status = 'active' ORDER BY store_id DESC LIMIT 1",
    [$slug, $storeId]
);

if (!$page) {
     http_response_code(404);
     // Fallback to check if it matches a Landing Page (special-page system)?
     // For now, just 404.
     echo "<h1>Page Not Found</h1>";
     exit;
}

// Decode content
$contentData = json_decode($page['content'], true) ?? [];
$htmlContent = $contentData['html'] ?? '';
$banner = $contentData['banner'] ?? [];
$seo = $contentData['seo'] ?? [];

$pageTitle = $seo['meta_title'] ?: $page['title'];
$metaDescription = $seo['meta_description'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Page Layout -->
<div class="custom-page-wrapper">
    
    <?php if (!empty($banner['image'])): ?>
    <div class="relative w-full h-[300px] md:h-[400px] bg-gray-200">
        <img src="<?php echo getBaseUrl() . '/' . $banner['image']; ?>" alt="Banner" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
            <div class="text-center text-white px-4">
                <?php if (!empty($banner['heading'])): ?>
                <h1 class="text-3xl md:text-5xl font-bold mb-4 drop-shadow-lg text-white"><?php echo htmlspecialchars($banner['heading']); ?></h1>
                <?php endif; ?>
                
                <?php if (!empty($banner['subheading'])): ?>
                <p class="text-lg md:text-xl mb-6 max-w-2xl mx-auto drop-shadow-md"><?php echo nl2br(htmlspecialchars($banner['subheading'])); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($banner['btn_text']) && !empty($banner['btn_link'])): ?>
                <a href="<?php echo htmlspecialchars($banner['btn_link']); ?>" class="inline-block bg-white text-black font-bold py-3 px-8 rounded hover:bg-gray-100 transition transform hover:-translate-y-1">
                    <?php echo htmlspecialchars($banner['btn_text']); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Fallback header if no banner image -->
    <div class="bg-gray-100 py-12">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($page['title']); ?></h1>
        </div>
    </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-12">
        <div class="prose max-w-none">
            <?php echo $htmlContent; // TRUSTED CONTENT from Admin ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
