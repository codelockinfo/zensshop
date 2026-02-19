<?php
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
$settingsObj = new Settings();
$baseUrl = getBaseUrl();

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: $baseUrl/blog.php");
    exit;
}

// Store ID Logic
$storeId = getCurrentStoreId();

// Fetch Blog
$blog = $db->fetchOne("SELECT * FROM blogs WHERE slug = ? AND status = 'published' AND (store_id = ? OR store_id IS NULL)", [$slug, $storeId]);

if (!$blog) {
    header("HTTP/1.0 404 Not Found");
    require_once __DIR__ . '/not-found.php'; // Or simple 404 logic
    exit;
}

// SEO
$pageTitle = $blog['title'];
$limit = 160;
$plainText = strip_tags($blog['content']);
$metaDescription = substr($plainText, 0, $limit) . (strlen($plainText) > $limit ? '...' : '');

require_once __DIR__ . '/includes/header.php';
?>

<?php
// Decode Blog Settings
$blogSettings = [
    'banner' => [
        'bg_color' => '#ffffff',
        'text_color' => '#ffffff',
        'heading_color' => '#ffffff',
        'subheading_color' => '#ffffff',
        'btn_text' => '',
        'btn_link' => '',
        'btn_bg_color' => '#ffffff',
        'btn_text_color' => '#000000',
        'btn_hover_bg_color' => '#f3f4f6',
        'btn_hover_text_color' => '#000000'
    ],
    'page_bg_color' => '#ffffff'
];

if (!empty($blog['settings'])) {
    $decodedSettings = json_decode($blog['settings'], true);
    if (is_array($decodedSettings)) {
        $blogSettings = array_merge($blogSettings, $decodedSettings);
    }
}
$banner = $blogSettings['banner'];
?>

<div class="min-h-screen" style="background-color: <?php echo $blogSettings['page_bg_color'] ?? '#ffffff'; ?>;">
    <?php if ($blog['image']): ?>
    <!-- Full Width Banner -->
    <!-- Skeleton Loader for Banner -->
    <div id="blog-banner-skeleton" class="relative w-full h-[400px] md:h-[500px] animate-pulse" style="background-color: <?php echo $banner['bg_color'] ?? '#e5e7eb'; ?>;">
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-50 animate-shimmer"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center space-y-4 px-4">
                <div class="h-3 bg-gray-400 rounded w-64 mx-auto animate-pulse"></div>
                <div class="h-12 bg-gray-400 rounded w-96 mx-auto animate-pulse"></div>
                <div class="h-4 bg-gray-400 rounded w-32 mx-auto animate-pulse"></div>
            </div>
        </div>
    </div>
    
    <div id="blog-banner" class="relative w-full h-[400px] md:h-[500px]" style="display: none; background-color: <?php echo $banner['bg_color'] ?? '#ffffff'; ?>;">
        <img src="<?php echo $baseUrl . '/' . $blog['image']; ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="w-full h-full object-cover" onload="hideBlogBannerSkeleton()">
        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="text-center px-4 max-w-4xl" style="color: <?php echo $banner['text_color'] ?? '#ffffff'; ?>;">
                <!-- Breadcrumb -->
                <nav class="breadcrumb-nav text-sm md:text-base mb-4 opacity-90 font-light">
                    <a href="<?php echo $baseUrl; ?>" class="hover:underline">Home</a>
                    <span class="mx-2">&gt;</span>
                    <a href="<?php echo $baseUrl; ?>/blog.php" class="hover:underline">Blog</a>
                    <span class="mx-2">&gt;</span>
                    <span><?php echo htmlspecialchars($blog['title']); ?></span>
                </nav>
                
                <!-- Title -->
                <h1 class="text-3xl md:text-5xl font-serif font-bold mb-4 leading-tight" style="color: <?php echo $banner['heading_color'] ?? '#ffffff'; ?>;">
                    <?php echo htmlspecialchars($blog['title']); ?>
                </h1>
                
                <!-- Date -->
                <div class="text-sm md:text-base font-medium uppercase tracking-widest opacity-80" style="color: <?php echo $banner['subheading_color'] ?? '#ffffff'; ?>;">
                    <?php echo date('F d, Y', strtotime($blog['created_at'])); ?>
                </div>

                <?php if (!empty($banner['btn_text'])): 
                    $btnId = 'blog-btn-' . uniqid();
                    $btnLink = !empty($banner['btn_link']) ? $banner['btn_link'] : '#';
                ?>
                <style>
                    #<?php echo $btnId; ?> {
                        background-color: <?php echo $banner['btn_bg_color'] ?? '#ffffff'; ?>;
                        color: <?php echo $banner['btn_text_color'] ?? '#000000'; ?>;
                    }
                    #<?php echo $btnId; ?>:hover {
                        background-color: <?php echo $banner['btn_hover_bg_color'] ?? '#f3f4f6'; ?>;
                        color: <?php echo $banner['btn_hover_text_color'] ?? '#000000'; ?>;
                        transform: translateY(-0.2rem);
                    }
                </style>
                <div class="mt-8">
                    <a id="<?php echo $btnId; ?>" href="<?php echo htmlspecialchars($btnLink); ?>" class="inline-block px-8 py-3 rounded font-bold transition transform shadow-lg">
                        <?php echo htmlspecialchars($banner['btn_text']); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Fallback if no image -->
    <div class="py-20 text-center" style="background-color: <?php echo $banner['bg_color'] ?? '#f9fafb'; ?>;">
         <div class="container mx-auto px-4">
            <nav class="breadcrumb-nav text-sm mb-4 opacity-70" style="color: <?php echo $banner['text_color'] ?? '#6b7280'; ?>;">
                <a href="<?php echo $baseUrl; ?>" class="hover:underline">Home</a>
                <span class="mx-2">&gt;</span>
                <a href="<?php echo $baseUrl; ?>/blog.php" class="hover:underline">Blog</a>
                <span class="mx-2">&gt;</span>
                <span><?php echo htmlspecialchars($blog['title']); ?></span>
            </nav>
            <h1 class="text-4xl font-serif font-bold mb-2" style="color: <?php echo $banner['heading_color'] ?? '#111827'; ?>;"><?php echo htmlspecialchars($blog['title']); ?></h1>
            <div class="text-sm font-bold uppercase tracking-widest opacity-60" style="color: <?php echo $banner['subheading_color'] ?? '#6b7280'; ?>;">
                <?php echo date('F d, Y', strtotime($blog['created_at'])); ?>
            </div>

            <?php if (!empty($banner['btn_text'])): 
                $btnId = 'blog-btn-' . uniqid();
                $btnLink = !empty($banner['btn_link']) ? $banner['btn_link'] : '#';
            ?>
            <style>
                #<?php echo $btnId; ?> {
                    background-color: <?php echo $banner['btn_bg_color'] ?? '#ffffff'; ?>;
                    color: <?php echo $banner['btn_text_color'] ?? '#000000'; ?>;
                    border: 1px solid <?php echo $banner['btn_text_color'] ?? '#000000'; ?>;
                }
                #<?php echo $btnId; ?>:hover {
                    background-color: <?php echo $banner['btn_hover_bg_color'] ?? '#f3f4f6'; ?>;
                    color: <?php echo $banner['btn_hover_text_color'] ?? '#000000'; ?>;
                }
            </style>
            <div class="mt-6">
                <a id="<?php echo $btnId; ?>" href="<?php echo htmlspecialchars($btnLink); ?>" class="inline-block px-6 py-2 rounded-full font-bold transition transform">
                    <?php echo htmlspecialchars($banner['btn_text']); ?>
                </a>
            </div>
            <?php endif; ?>
         </div>
    </div>
    <?php endif; ?>

    <!-- Content -->
    <?php
    $containerClass = 'max-w-3xl'; // Default Standard
    if (($blog['layout'] ?? '') === 'wide') {
        $containerClass = 'max-w-6xl';
    } elseif (($blog['layout'] ?? '') === 'full_width') {
        $containerClass = 'max-w-full';
    }
    ?>
    <div class="container mx-auto px-4 <?php echo $containerClass; ?> py-12 mb-16">
        <article class="prose prose-lg prose-blue mx-auto max-w-none text-gray-700 leading-relaxed font-sans ck-content">
            <?php echo $blog['content']; // Output raw HTML content ?>
        </article>
        
        <div class="border-t border-gray-200 mt-12 pt-8 text-center">
            <a href="<?php echo $baseUrl; ?>/blog.php" class="inline-flex items-center text-gray-600 hover:text-black font-semibold transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to All Articles
            </a>
        </div>
    </div>
</div>

<style>
/* Additional Typography Styles for Blog Content */
.ck-content h2 { font-family: 'Playfair Display', serif; margin-top: 2em; margin-bottom: 0.8em; font-size: 1.75em; color: #111; line-height: 1.3; }
.ck-content h3 { font-family: 'Playfair Display', serif; margin-top: 1.5em; margin-bottom: 0.8em; font-size: 1.4em; color: #333; }
.ck-content p { margin-bottom: 1.5em; font-size: 1.125rem; line-height: 1.8; }
.ck-content ul { list-style-type: disc; padding-left: 1.5em; margin-bottom: 1.5em; }
.ck-content ol { list-style-type: decimal; padding-left: 1.5em; margin-bottom: 1.5em; }
/* CKEditor General Image Styling */
.ck-content figure {
    margin: 2em auto;
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

.ck-content blockquote { padding-left: 1.5rem; border-left: 4px solid #3b82f6; font-style: italic; background: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; color: #4b5563; }
.ck-content a { color: #2563eb; text-decoration: underline; text-underline-offset: 4px; transition: color 0.2s; }
.ck-content a:hover { color: #1e40af; }

/* CKEditor Side-by-Side Image Layout */
.ck-content figure.image-style-side {
    float: right;
    max-width: 50%;
    margin-left: 2rem;
    margin-right: 0;
    margin-bottom: 1.5rem;
    margin-top: 0.5rem;
    clear: none; /* Allow text to wrap */
    display: block;
}

.ck-content figure.image-style-side img {
    width: 100%;
    height: auto;
}

/* Clearfix for container */
.ck-content::after {
    content: "";
    display: table;
    clear: both;
}

/* Specific handling for resized images */
.ck-content figure.image_resized {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

/* Clear floats after side images */
.ck-content figure.image-style-side + * {
    clear: none;
}

/* Mobile: Stack images */
@media (max-width: 768px) {
    .ck-content figure.image-style-side {
        float: none;
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
        margin-bottom: 1.5rem;
        width: 100%;
    }
}


/* Image-Text Block Styles - For side-by-side image and text layout */
.ck-content .image-text-block {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 2rem !important;
    margin: 3rem 0 !important;
    align-items: center !important;
}

.ck-content .image-text-block .image-side {
    width: 100%;
}

.ck-content .image-text-block .image-side img {
    width: 100% !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    margin: 0 !important; /* Override default img margin */
    display: block !important;
}

.ck-content .image-text-block .text-side {
    padding: 1rem;
}

.ck-content .image-text-block .text-side p {
    margin-bottom: 1rem;
    font-size: 1rem;
    line-height: 1.7;
    color: #374151;
}

.ck-content .image-text-block .text-side h2,
.ck-content .image-text-block .text-side h3 {
    margin-top: 0;
}

.ck-content .image-text-block .text-side ul,
.ck-content .image-text-block .text-side ol {
    margin-bottom: 1rem;
}

/* Support for CKEditor Tables used as two-column layouts */
.ck-content table {
    width: 100% !important;
    border-collapse: collapse;
    margin: 2rem 0;
    border: none !important;
}

.ck-content table td {
    vertical-align: top;
    padding: 1rem;
    border: none !important;
}

/* When table has 2 columns (typical for image-text layout) */
.ck-content table tr td:first-child {
    width: 50%;
}

.ck-content table tr td:last-child {
    width: 50%;
}

/* Images inside table cells */
.ck-content table td figure,
.ck-content table td img {
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    display: block;
    border-radius: 0.5rem;
}

/* Text content in table cells */
.ck-content table td p {
    margin-bottom: 1rem;
    font-size: 1rem;
    line-height: 1.7;
}

.ck-content table td ol,
.ck-content table td ul {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

/* Responsive: Stack on mobile */
@media (max-width: 768px) {
    .ck-content .image-text-block {
        grid-template-columns: 1fr !important;
        gap: 1.5rem !important;
    }
    
    .ck-content .image-text-block.image-right .image-side {
        order: -1; /* Move image to top on mobile */
    }
    
    /* Stack table columns on mobile */
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
</style>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}
</style>

<script>
function hideBlogBannerSkeleton() {
    const skeleton = document.getElementById('blog-banner-skeleton');
    const banner = document.getElementById('blog-banner');
    
    if (skeleton && banner) {
        skeleton.style.display = 'none';
        banner.style.display = 'block';
    }
}

// Hide skeleton when DOM is ready with timeout fallback
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        hideBlogBannerSkeleton();
    }, 300);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
