<?php
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
$settingsObj = new Settings();
$baseUrl = getBaseUrl();

// Store ID Logic
$storeId = getCurrentStoreId();

// Handle direct access via ?slug=
if (!empty($_GET['slug'])) {
    require __DIR__ . '/blog-post.php';
    exit;
}

// Check if Blog is Enabled
if ($settingsObj->get('enable_blog', '1') != '1') {
    header("Location: " . $baseUrl);
    exit;
}

// Fetch Published Blogs
$blogs = $db->fetchAll("SELECT * FROM blogs WHERE status = 'published' AND (store_id = ? OR store_id IS NULL) ORDER BY created_at DESC", [$storeId]);

$pageTitle = 'Blog';
require_once __DIR__ . '/includes/header.php';
?>

<?php
// Load Consolidated Settings
$blogStylingJson = $settingsObj->get('blog_page_styling', '');
$blogStyling = !empty($blogStylingJson) ? json_decode($blogStylingJson, true) : [];

// Fallback to individual keys if JSON is empty
if (empty($blogStyling)) {
    $blogStyling = [
        'blog_heading' => $settingsObj->get('blog_heading', 'Our Blogs'),
        'blog_subheading' => $settingsObj->get('blog_subheading', 'Latest news, updates, and stories from our team.'),
        'blog_heading_color' => $settingsObj->get('blog_heading_color', '#111827'),
        'blog_subheading_color' => $settingsObj->get('blog_subheading_color', '#4b5563'),
        'blog_page_bg_color' => $settingsObj->get('blog_page_bg_color', '#f9fafb'),
        'blog_card_bg_color' => $settingsObj->get('blog_card_bg_color', '#ffffff'),
        'blog_date_color' => $settingsObj->get('blog_date_color', '#6b7280'),
        'blog_title_color' => $settingsObj->get('blog_title_color', '#111827'),
        'blog_desc_color' => $settingsObj->get('blog_desc_color', '#4b5563'),
        'blog_read_more_color' => $settingsObj->get('blog_read_more_color', '#2563eb')
    ];
}

$blogBg = $blogStyling['blog_page_bg_color'] ?? '#f9fafb';
$blogHeadingColor = $blogStyling['blog_heading_color'] ?? '#111827';
$blogSubheadingColor = $blogStyling['blog_subheading_color'] ?? '#4b5563';

// Card Colors
$cardBg = $blogStyling['blog_card_bg_color'] ?? '#ffffff';
$dateColor = $blogStyling['blog_date_color'] ?? '#6b7280';
$titleColor = $blogStyling['blog_title_color'] ?? '#111827';
$descColor = $blogStyling['blog_desc_color'] ?? '#4b5563';
$readMoreColor = $blogStyling['blog_read_more_color'] ?? '#2563eb';
?>
<div style="background-color: <?php echo $blogBg; ?>;" class="py-12 min-h-screen">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-serif text-center mb-4" style="color: <?php echo $blogHeadingColor; ?>;">
            <?php echo htmlspecialchars($settingsObj->get('blog_heading', 'Our Blog')); ?>
        </h1>
        <p class="text-center mb-12 max-w-2xl mx-auto" style="color: <?php echo $blogSubheadingColor; ?>;">
            <?php echo htmlspecialchars($settingsObj->get('blog_subheading', 'Latest news, updates, and stories from our team.')); ?>
        </p>

        <?php if (empty($blogs)): ?>
            <div class="text-center py-20 text-gray-500">
                <i class="far fa-newspaper text-6xl mb-4 text-gray-300"></i>
                <p class="text-xl">No blog posts available at the moment. Check back later!</p>
            </div>
        <?php else: ?>
            <!-- Skeleton Loaders for Blog Cards -->
            <div id="blog-skeleton" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php for ($i = 0; $i < min(6, count($blogs)); $i++): ?>
                <div class="rounded-xl shadow-sm overflow-hidden animate-pulse" style="background-color: <?php echo $cardBg; ?>;">
                    <div class="aspect-video bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-50 animate-shimmer"></div>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="h-3 bg-gray-300 rounded w-24"></div>
                        <div class="h-6 bg-gray-300 rounded w-full"></div>
                        <div class="h-4 bg-gray-300 rounded w-full"></div>
                        <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-300 rounded w-20 mt-4"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Actual Blog Cards -->
            <div id="blog-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" style="display: none;">
                <?php foreach ($blogs as $blog): ?>
                    <article class="rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden group flex flex-col h-full" style="background-color: <?php echo $cardBg; ?>;">
                        <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo htmlspecialchars($blog['slug']); ?>" class="block relative overflow-hidden aspect-video">
                            <?php if ($blog['image']): ?>
                                <img src="<?php echo $baseUrl . '/' . $blog['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($blog['title']); ?>" 
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-300">
                                    <i class="fas fa-image text-3xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition"></div>
                        </a>
                        
                        <div class="p-6 flex-1 flex flex-col">
                            <div class="text-xs mb-2 font-medium uppercase tracking-wider" style="color: <?php echo $dateColor; ?>;">
                                <?php echo date('F j, Y', strtotime($blog['created_at'])); ?>
                            </div>
                            
                            <h2 class="text-xl font-bold mb-3 line-clamp-2">
                                <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo htmlspecialchars($blog['slug']); ?>" class="transition hover:opacity-80" style="color: <?php echo $titleColor; ?>;">
                                    <?php echo htmlspecialchars($blog['title']); ?>
                                </a>
                            </h2>
                            
                            <div class="mb-4 line-clamp-3 text-sm flex-1" style="color: <?php echo $descColor; ?>;">
                                <?php 
                                    $plainText = strip_tags($blog['content']);
                                    echo substr($plainText, 0, 150) . (strlen($plainText) > 150 ? '...' : ''); 
                                ?>
                            </div>
                            
                            <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo htmlspecialchars($blog['slug']); ?>" class="inline-flex items-center font-semibold text-sm hover:underline mt-auto" style="color: <?php echo $readMoreColor; ?>;">
                                Read Article <i class="fas fa-arrow-right ml-2 text-xs transition-transform group-hover:translate-x-1"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

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
// Hide skeleton and show blog cards when page loads
window.addEventListener('load', function() {
    const skeleton = document.getElementById('blog-skeleton');
    const cards = document.getElementById('blog-cards');
    
    if (skeleton && cards) {
        skeleton.style.display = 'none';
        cards.style.display = 'grid';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
