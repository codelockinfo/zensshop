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
$slug = preg_replace('/[^a-zA-Z0-9-_]/', '', $slug);

if (empty($slug)) {
    http_response_code(404);
    require __DIR__ . '/not-found.php';
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
     require __DIR__ . '/not-found.php';
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
    <!-- Skeleton Loader for Banner -->
    <div id="page-banner-skeleton" class="relative w-full h-[300px] md:h-[400px] bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 animate-pulse">
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-50 animate-shimmer"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center space-y-4 px-4">
                <div class="h-12 bg-gray-400 rounded w-96 mx-auto animate-pulse"></div>
                <div class="h-6 bg-gray-400 rounded w-64 mx-auto animate-pulse"></div>
                <div class="h-10 bg-gray-400 rounded w-40 mx-auto animate-pulse"></div>
            </div>
        </div>
    </div>
    
    <div id="page-banner" class="relative w-full h-[300px] md:h-[400px] bg-gray-200" style="display: none;">
        <img src="<?php echo getBaseUrl() . '/' . $banner['image']; ?>" alt="Banner" class="w-full h-full object-cover" onload="hidePageBannerSkeleton()">
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

    <?php 
    $layout = $contentData['settings']['layout'] ?? 'standard';
    $containerClass = 'container mx-auto px-4 py-12'; // Default (Wide)
    
    if ($layout === 'standard') {
        $containerClass = 'container mx-auto px-4 py-12 max-w-4xl';
    } elseif ($layout === 'full_width') {
        $containerClass = 'w-full py-12 px-4 md:px-8';
    }
    ?>

    <div class="<?php echo $containerClass; ?>">
        <div class="prose max-w-none ck-content mx-auto">
            <?php echo $htmlContent; // TRUSTED CONTENT from Admin ?>
        </div>
    </div>

</div>

<style>
/* Rich Text Content Styling - Same as Blog Posts */
.ck-content {
    max-width: 100%;
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.ck-content h2 { 
    font-family: 'Playfair Display', serif; 
    margin-top: 2em; 
    margin-bottom: 0.8em; 
    font-size: 1.75em; 
    color: #111; 
    line-height: 1.3;
    word-wrap: break-word;
}

.ck-content h3 { 
    font-family: 'Playfair Display', serif; 
    margin-top: 1.5em; 
    margin-bottom: 0.8em; 
    font-size: 1.4em; 
    color: #333;
    word-wrap: break-word;
}

.ck-content p { 
    margin-bottom: 1.5em; 
    font-size: 1.125rem; 
    line-height: 1.8;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.ck-content ul { 
    list-style-type: disc; 
    padding-left: 1.5em; 
    margin-bottom: 1.5em; 
}

.ck-content ol { 
    list-style-type: decimal; 
    padding-left: 1.5em; 
    margin-bottom: 1.5em; 
}

/* CKEditor General Image Styling */
.ck-content figure {
    margin: 1.5em auto;
    display: table; /* Allows figure to shrink to image size */
    clear: both;
    max-width: 100%;
}

.ck-content img { 
    border-radius: 0.75rem; 
    display: block; 
    max-width: 100%; 
    height: auto;
    margin: 0 auto;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); 
    width: 100%; /* Make image fill the figure */
}

/* CKEditor Side-by-Side Image Layout */
.ck-content figure.image-style-side {
    float: right;
    max-width: 50%;
    margin-left: 2rem;
    margin-right: 0;
    margin-bottom: 1.5rem;
    margin-top: 0.5rem;
    clear: none; /* Allow text to wrap */
    display: block; /* Float needs block */
}

/* Clearfix for container to prevent collapse */
.ck-content::after {
    content: "";
    display: table;
    clear: both;
}

.ck-content figure.image-style-side img {
    width: 100%; 
    max-width: 100%;
}

/* Specific handling for resized images (CKEditor adds style width to figure) */
.ck-content figure.image_resized {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

/* Image Captions */
.ck-content figcaption {
    text-align: center;
    font-size: 0.875em;
    color: #6b7280;
    margin-top: 0.5em;
}

.ck-content blockquote { 
    padding-left: 1.5rem; 
    border-left: 4px solid #3b82f6; 
    font-style: italic; 
    background: #f9fafb; 
    padding: 1.5rem; 
    border-radius: 0.5rem; 
    color: #4b5563;
    word-wrap: break-word;
}

.ck-content a { 
    color: #2563eb; 
    text-decoration: underline; 
    text-underline-offset: 4px; 
    transition: color 0.2s;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.ck-content a:hover { 
    color: #1e40af; 
}

/* Support for CKEditor Tables used as two-column layouts */
.ck-content table {
    width: 100% !important;
    border-collapse: collapse;
    margin: 2rem 0;
    border: none !important;
    table-layout: fixed;
}

.ck-content table td {
    vertical-align: top;
    padding: 1rem;
    border: none !important;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.ck-content table tr td:first-child {
    width: 50%;
}

.ck-content table tr td:last-child {
    width: 50%;
}

.ck-content table td figure,
.ck-content table td img {
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    display: block;
    border-radius: 0.5rem;
}

/* Prose container */
.prose {
    max-width: 100% !important;
}

/* Mobile: Stack images and tables */
@media (max-width: 768px) {
    .ck-content figure.image-style-side {
        float: none !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        margin-bottom: 1.5rem !important;
    }
    
    .ck-content table,
    .ck-content table tbody,
    .ck-content table tr,
    .ck-content table td {
        display: block !important;
        width: 100% !important;
    }
    
    .ck-content table td {
        padding: 0.5rem 0;
    }
}

/* Skeleton Loader Animation */
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}
</style>

<script>
function hidePageBannerSkeleton() {
    const skeleton = document.getElementById('page-banner-skeleton');
    const banner = document.getElementById('page-banner');
    
    if (skeleton && banner) {
        skeleton.style.display = 'none';
        banner.style.display = 'block';
    }
}

// Hide skeleton when DOM is ready with timeout fallback
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        hidePageBannerSkeleton();
    }, 300);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
