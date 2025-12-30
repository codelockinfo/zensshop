<?php
/**
 * Category Page - Display products by category
 */

// Start output buffering to prevent headers already sent errors
ob_start();

// Process redirects BEFORE any output
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = getBaseUrl();
$product = new Product();
$db = Database::getInstance();

// Get category slug from URL
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . $baseUrl . '/collections');
    exit;
}

// Get category info
$category = $db->fetchOne(
    "SELECT * FROM categories WHERE slug = ? AND status = 'active'",
    [$categorySlug]
);

if (!$category) {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . $baseUrl . '/collections');
    exit;
}

// Clear output buffer before including header
ob_end_clean();

$pageTitle = 'Category';
require_once __DIR__ . '/includes/header.php';

// Get products for this category
$products = $product->getByCategory($categorySlug);

// Get category image
$categoryImage = null;
if (!empty($category['image'])) {
    if (strpos($category['image'], 'http://') === 0 || strpos($category['image'], 'https://') === 0) {
        $categoryImage = $category['image'];
    } elseif (strpos($category['image'], '/') === 0) {
        $categoryImage = $category['image'];
    } else {
        $categoryImage = $baseUrl . '/assets/images/uploads/' . $category['image'];
    }
} else {
    // Use placeholder
    $categoryImage = 'data:image/svg+xml;base64,' . base64_encode('<svg width="1200" height="400" viewBox="0 0 1200 400" xmlns="http://www.w3.org/2000/svg"><rect width="1200" height="400" fill="#F3F4F6"/><circle cx="600" cy="200" r="80" fill="#9B7A8A"/><path d="M400 350C400 300 500 250 600 250C700 250 800 300 800 350" fill="#9B7A8A"/></svg>');
}
?>

<section class="py-16 md:py-24 bg-white min-h-screen">
    <div class="container mx-auto px-4">
        <!-- Breadcrumbs -->
        <nav class="text-sm text-gray-600 mb-8">
            <a href="<?php echo url(''); ?>" class="hover:text-primary">Home</a> > 
            <a href="<?php echo url('collections.php'); ?>" class="hover:text-primary">Collections</a> > 
            <span class="text-gray-900"><?php echo htmlspecialchars($category['name']); ?></span>
        </nav>

        <!-- Category Hero Section -->
        <div class="mb-12">
            <div class="relative overflow-hidden rounded-lg" style="height: 300px;">
                <img src="<?php echo htmlspecialchars($categoryImage); ?>" 
                     alt="<?php echo htmlspecialchars($category['name']); ?>"
                     class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                    <div class="text-center text-white">
                        <h1 class="text-4xl md:text-5xl font-heading font-bold mb-4">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </h1>
                        <?php if (!empty($category['description'])): ?>
                        <p class="text-lg max-w-2xl mx-auto">
                            <?php echo htmlspecialchars($category['description']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($products as $item): 
                $mainImage = getProductImage($item);
                $price = $item['sale_price'] ?? $item['price'];
                $originalPrice = $item['sale_price'] ? $item['price'] : null;
                $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
            ?>
            <div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">
                <div class="relative overflow-hidden">
                    <a href="<?php echo url('product.php?slug=' . urlencode($item['slug'] ?? '')); ?>">
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                    </a>
                    
                    <!-- Discount Badge -->
                    <?php if ($discount > 0): ?>
                    <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                    
                    <!-- Wishlist Icon -->
                    <button class="absolute top-2 right-2 w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition z-20 wishlist-btn" 
                            data-product-id="<?php echo $item['id']; ?>"
                            title="Add to Wishlist">
                        <i class="far fa-heart"></i>
                    </button>
                    
                    <!-- Hover Action Buttons -->
                    <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                        <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                           class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition shadow-lg quick-view-btn" 
                           data-product-id="<?php echo $item['id']; ?>"
                           data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>"
                           title="Quick View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition shadow-lg compare-btn" 
                                data-product-id="<?php echo $item['id']; ?>"
                                title="Compare">
                            <i class="fas fa-layer-group"></i>
                        </button>
                        <button class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition shadow-lg add-to-cart-hover-btn" 
                                data-product-id="<?php echo $item['id']; ?>"
                                title="Add to Cart">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-4">
                    <h3 class="font-semibold text-gray-800 mb-2 line-clamp-2 min-h-[48px]">
                        <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </a>
                    </h3>
                    <div class="flex items-center mb-3">
                        <div class="flex text-yellow-400">
                            <?php 
                            $rating = floor($item['rating'] ?? 5);
                            for ($i = 0; $i < 5; $i++): 
                            ?>
                            <i class="fas fa-star text-sm <?php echo $i < $rating ? '' : 'text-gray-300'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($originalPrice): ?>
                        <span class="text-gray-400 line-through text-sm block">$<?php echo number_format($originalPrice, 2); ?></span>
                        <?php endif; ?>
                        <p class="text-xl font-bold <?php echo $discount > 0 ? 'text-red-500' : 'text-primary'; ?>">$<?php echo number_format($price, 2); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-bold mb-2">No products found</h2>
            <p class="text-gray-600 mb-6">This category doesn't have any products yet.</p>
            <a href="<?php echo url('shop.php'); ?>" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-dark transition">
                Browse All Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

