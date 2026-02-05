<?php
$pageTitle = 'Collections List';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
// Get all active categories (Store Specific)
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE status = 'active' AND (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC, name ASC",
    [CURRENT_STORE_ID]
);
?>

<section class="py-16 md:py-24 bg-white min-h-screen">
    <div class="container mx-auto px-4">
        
        <!-- Collections Skeleton -->
        <div id="collectionsSkeleton" class="animate-pulse">
            <!-- Header Skeleton -->
            <div class="text-center mb-12 space-y-4">
                <div class="h-10 bg-gray-200 rounded w-64 mx-auto"></div>
                <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6 mx-auto"></div>
            </div>

            <!-- Grid Skeleton -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php for($i=0; $i<8; $i++): ?>
                <div class="relative overflow-hidden rounded-xl bg-gray-100 aspect-[3/4] p-4 flex flex-col justify-end">
                    <div class="bg-white/80 h-12 w-full rounded-full animate-pulse"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div id="mainCollectionsContent" class="hidden">
            <!-- Page Header -->
            <div class="text-center mb-12">
                <h1 class="text-3xl md:text-4xl font-heading font-bold mb-4">Collections List</h1>
                <p class="text-gray-600 text-md max-w-3xl mx-auto">
                    Explore our thoughtfully curated collections: Sweaters, Handbags, Denim, and more—each perfect for enhancing every style on every special occasion and daily wear.
                </p>
            </div>
            
            <!-- Collections Grid -->
            <?php if (empty($categories)): ?>
            <div class="text-center py-12">
                <p class="text-gray-500 text-lg">No collections available at the moment.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php foreach ($categories as $category): 
                    // Get category image or use placeholder
                    if (!empty($category['image'])) {
                        // Check if it's a full URL or relative path
                        if (strpos($category['image'], 'http://') === 0 || strpos($category['image'], 'https://') === 0) {
                            $categoryImage = $category['image'];
                        } else {
                            // Database stores path like 'assets/images/categories/filename.jpg'
                            $categoryImage = $baseUrl . '/' . ltrim($category['image'], '/');
                        }
                    } else {
                        $categoryImage = 'data:image/svg+xml;base64,' . base64_encode('<svg width="400" height="500" viewBox="0 0 400 500" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="500" fill="#F3F4F6"/><circle cx="200" cy="200" r="50" fill="#9B7A8A"/><path d="M100 350C100 300 150 250 200 250C250 250 300 300 300 350" fill="#9B7A8A"/></svg>');
                    }
                    $categoryUrl = $baseUrl . '/shop?category=' . urlencode($category['slug']);
                ?>
                <a href="<?php echo htmlspecialchars($categoryUrl); ?>" class="group">
                    <div class="relative overflow-hidden rounded-xl bg-gray-100 aspect-[3/4]">
                        <img src="<?php echo htmlspecialchars($categoryImage); ?>" 
                             alt="<?php echo htmlspecialchars($category['name']); ?>"
                             class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500"
                             onerror="this.src='https://placehold.co/600x600?text=Category+Image'">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300"></div>
                        <!-- Collection Name Overlay Button -->
                        <div class="absolute bottom-0 left-0 right-0 p-4 flex items-center justify-center">
                            <div class="bg-white px-6 py-3 w-full max-w-[85%]" style="border-radius: 50px;">
                                <h3 class="text-center text-md font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($categories) > 8): ?>
            <div class="text-center mt-8">
                <button onclick="loadMoreCollections()" 
                        class="bg-black text-white px-8 py-3 rounded-lg hover:bg-gray-800 transition font-semibold">
                    Load More
                </button>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Skeleton Loader Handling
document.addEventListener('DOMContentLoaded', function() {
    const skeleton = document.getElementById('collectionsSkeleton');
    const content = document.getElementById('mainCollectionsContent');
    if (skeleton && content) {
        skeleton.classList.add('hidden');
        content.classList.remove('hidden');
    }
});

function loadMoreCollections() {
    console.log('Load more collections');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

