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

<div class="bg-white min-h-screen">
    <?php if ($blog['image']): ?>
    <!-- Full Width Banner -->
    <div class="relative w-full h-[400px] md:h-[500px]">
        <img src="<?php echo $baseUrl . '/' . $blog['image']; ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="text-center text-white px-4 max-w-4xl">
                <!-- Breadcrumb -->
                <nav class="text-sm md:text-base mb-4 opacity-90 font-light">
                    <a href="<?php echo $baseUrl; ?>" class="hover:underline">Home</a>
                    <span class="mx-2">&gt;</span>
                    <a href="<?php echo $baseUrl; ?>/blog.php" class="hover:underline">Blog</a>
                    <span class="mx-2">&gt;</span>
                    <span><?php echo htmlspecialchars($blog['title']); ?></span>
                </nav>
                
                <!-- Title -->
                <h1 class="text-3xl md:text-5xl font-serif font-bold mb-4 leading-tight text-white">
                    <?php echo htmlspecialchars($blog['title']); ?>
                </h1>
                
                <!-- Date -->
                <div class="text-sm md:text-base font-medium uppercase tracking-widest opacity-80">
                    <?php echo date('F d, Y', strtotime($blog['created_at'])); ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Fallback if no image -->
    <div class="bg-gray-100 py-20 text-center">
         <div class="container mx-auto px-4">
            <nav class="text-sm text-gray-500 mb-4">
                <a href="<?php echo $baseUrl; ?>" class="hover:text-black">Home</a>
                <span class="mx-2">&gt;</span>
                <a href="<?php echo $baseUrl; ?>/blog.php" class="hover:text-black">Blog</a>
                <span class="mx-2">&gt;</span>
                <span><?php echo htmlspecialchars($blog['title']); ?></span>
            </nav>
            <h1 class="text-4xl font-serif font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($blog['title']); ?></h1>
            <div class="text-sm font-bold text-gray-400 uppercase tracking-widest">
                <?php echo date('F d, Y', strtotime($blog['created_at'])); ?>
            </div>
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
    <div class="container mx-auto px-4 <?php echo $containerClass; ?> mb-16">
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
.ck-content img { border-radius: 0.75rem; margin: 2em auto; display: block; max-width: 100%; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
.ck-content blockquote { padding-left: 1.5rem; border-left: 4px solid #3b82f6; font-style: italic; background: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; color: #4b5563; }
.ck-content a { color: #2563eb; text-decoration: underline; text-underline-offset: 4px; transition: color 0.2s; }
.ck-content a:hover { color: #1e40af; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
