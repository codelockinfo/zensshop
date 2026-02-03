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
// Get Colors
$blogBg = $settingsObj->get('blog_bg_color', '#f9fafb');
$blogText = $settingsObj->get('blog_text_color', '#1f2937');
$blogHeadingColor = $settingsObj->get('blog_heading_color', '#111827');
?>
<div style="background-color: <?php echo $blogBg; ?>;" class="py-12 min-h-screen">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-serif text-center mb-4" style="color: <?php echo $blogHeadingColor; ?>;">
            <?php echo htmlspecialchars($settingsObj->get('blog_heading', 'Our Blog')); ?>
        </h1>
        <p class="text-center mb-12 max-w-2xl mx-auto" style="color: <?php echo $blogText; ?>;">
            <?php echo htmlspecialchars($settingsObj->get('blog_description', 'Latest news, updates, and stories from our team.')); ?>
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
                <div class="bg-white rounded-xl shadow-sm overflow-hidden animate-pulse">
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
                    <article class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden group flex flex-col h-full">
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
                            <div class="text-xs text-gray-500 mb-2 font-medium uppercase tracking-wider">
                                <?php echo date('F j, Y', strtotime($blog['created_at'])); ?>
                            </div>
                            
                            <h2 class="text-xl font-bold mb-3 line-clamp-2">
                                <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo htmlspecialchars($blog['slug']); ?>" class="text-gray-900 group-hover:text-blue-600 transition">
                                    <?php echo htmlspecialchars($blog['title']); ?>
                                </a>
                            </h2>
                            
                            <div class="text-gray-600 mb-4 line-clamp-3 text-sm flex-1">
                                <?php 
                                    $plainText = strip_tags($blog['content']);
                                    echo substr($plainText, 0, 150) . (strlen($plainText) > 150 ? '...' : ''); 
                                ?>
                            </div>
                            
                            <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo htmlspecialchars($blog['slug']); ?>" class="inline-flex items-center text-blue-600 font-semibold text-sm hover:underline mt-auto">
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
