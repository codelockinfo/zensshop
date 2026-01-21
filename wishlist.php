<?php
/**
 * Wishlist Page
 */

require_once __DIR__ . '/classes/Wishlist.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = getBaseUrl();
$wishlist = new Wishlist();
$product = new Product();
$db = Database::getInstance();

// Clear old cookies with wrong paths (if headers not sent)
if (!headers_sent()) {
    // Clear existing cookies if they exist to prevent conflicts
if (isset($_COOKIE['wishlist_items'])) {
    $cookiePath = function_exists('getBaseUrl') ? getBaseUrl() : '/';
    if (empty($cookiePath)) $cookiePath = '/';
    setcookie('wishlist_items', '', time() - 3600, $cookiePath);
}
    setcookie('wishlist_items', '', time() - 3600, '/oecom');
}

// Get wishlist items
$wishlistItems = $wishlist->getWishlist();

// If wishlist is empty but cookie exists, try to read it directly and resave with correct path
if (empty($wishlistItems) && isset($_COOKIE['wishlist_items'])) {
    $cookieValue = $_COOKIE['wishlist_items'];
    $decoded = json_decode($cookieValue, true);
    if (is_array($decoded) && !empty($decoded)) {
        // Cookie has data but wasn't read properly - resave with correct path
        $wishlistItems = $decoded;
        // Use reflection to access private method or create a public method
        // For now, just update $_COOKIE directly
        $_COOKIE['wishlist_items'] = json_encode($wishlistItems);
        if (!headers_sent()) {
            setcookie('wishlist_items', json_encode($wishlistItems), time() + 2592000, '/');
        }
    }
}

// Get recently viewed products from cookie
$recentlyViewed = [];
if (isset($_COOKIE['recently_viewed'])) {
    $recentlyViewedIds = json_decode($_COOKIE['recently_viewed'], true);
    if (is_array($recentlyViewedIds) && !empty($recentlyViewedIds)) {
        // Get last 4 products
        $recentlyViewedIds = array_slice(array_reverse($recentlyViewedIds), 0, 4);
        $placeholders = implode(',', array_fill(0, count($recentlyViewedIds), '?'));
        $recentlyViewed = $db->fetchAll(
            "SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'",
            $recentlyViewedIds
        );
    }
}

$pageTitle = 'Wishlist';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="py-4">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-gray-600">
            <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a>
            <span class="mx-2">></span>
            <span class="text-gray-900">Wishlist</span>
        </nav>
    </div>
</div>

<!-- Wishlist Section -->
<div class="container mx-auto pt-2 px-4 py-12">
    <h1 class="text-2xl md:text-4xl font-heading font-bold text-center mb-5 md:mb-12">Wishlist</h1>
    
    <?php if (empty($wishlistItems)): ?>
        <!-- Empty Wishlist -->
        <div class="text-center py-16">
            <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-heading font-bold mb-2">Your wishlist is empty</h2>
            <p class="text-gray-600 mb-6">Start adding products you love to your wishlist!</p>
            <a href="<?php echo $baseUrl; ?>/shop.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-primary-light hover:text-white transition">
                Continue Shopping
            </a>
        </div>
    <?php else: ?>
        <!-- Wishlist Items -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
            <?php foreach ($wishlistItems as $item): ?>
                <div class="group relative bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                    <!-- Remove Button -->
                    <button onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)" 
                            class="absolute top-2 md:top-4 right-2 md:right-4 z-10 bg-white rounded-full h-9 w-9 shadow-md hover:bg-black hover:text-white transition"
                            title="Remove from wishlist">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <!-- Product Image -->
                    <a href="<?php echo url('product.php?slug=' . urlencode($item['slug'] ?? '')); ?>">
                        <div class="relative overflow-hidden bg-gray-100" style="padding-top: 100%;">
                            <?php 
                            $imageUrl = $item['image'] ?? '';
                            if (empty($imageUrl) || $imageUrl === 'null' || $imageUrl === 'undefined') {
                                $imageUrl = 'data:image/svg+xml;base64,' . base64_encode('<svg width="400" height="400" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="400" fill="#F3F4F6"/><circle cx="200" cy="200" r="60" fill="#9B7A8A"/></svg>');
                            } elseif (strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '/') !== 0 && strpos($imageUrl, 'data:') !== 0) {
                                $imageUrl = $baseUrl . '/assets/images/uploads/' . $imageUrl;
                            } elseif (strpos($imageUrl, '/') !== 0 && strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, 'data:') !== 0) {
                                $imageUrl = $baseUrl . $imageUrl;
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                 class="absolute top-0 left-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                        </div>
                    </a>
                    
                    <!-- Product Info -->
                    <div class="py-6 px-4">
                        <h3 class="text-md font-heading font-semibold mb-2">
                                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" 
                               class="hover:text-primary transition">
                                    <?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>
                                </a>
                        </h3>
                        
                        <!-- Rating -->
                        <div class="flex items-center mb-3 text-sm">
                            <?php 
                            $rating = floatval($item['rating'] ?? 0);
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            for ($i = 0; $i < 5; $i++): 
                                if ($i < $fullStars): ?>
                                    <i class="fas fa-star text-yellow-400"></i>
                                <?php elseif ($i == $fullStars && $hasHalfStar): ?>
                                    <i class="fas fa-star-half-alt text-yellow-400"></i>
                                <?php else: ?>
                                    <i class="far fa-star text-yellow-400"></i>
                                <?php endif;
                            endfor; ?>
                        </div>
                        
                        <!-- Price -->
                        <div class="flex items-center justify-between">
                            <span class="text-md font-bold text-primary">
                                $<?php echo number_format(floatval($item['price'] ?? 0), 2); ?>
                            </span>
                            
                            <!-- Add to Cart Button -->
                            <button onclick="addToCart(<?php echo $item['product_id']; ?>, 1)" 
                                    class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-light hover:text-white transition text-sm">
                                <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Recently Viewed Section -->
    <?php if (!empty($recentlyViewed)): ?>
        <div class="mt-20">
            <h2 class="text-4xl md:text-5xl font-heading font-bold text-center mb-4">Recently Viewed</h2>
            <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">
                Explore your recently viewed items, blending quality and style for a refined living experience.
            </p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($recentlyViewed as $recentProduct): 
                    $recentImages = json_decode($recentProduct['images'] ?? '[]', true);
                    $recentImage = getProductImage($recentProduct);
                    $recentRating = floatval($recentProduct['rating'] ?? 0);
                    $recentFullStars = floor($recentRating);
                    $recentHasHalfStar = ($recentRating - $recentFullStars) >= 0.5;
                    $isInWishlist = false;
                    foreach ($wishlistItems as $wishItem) {
                        if ($wishItem['product_id'] == $recentProduct['id']) {
                            $isInWishlist = true;
                            break;
                        }
                    }
                ?>
                    <div class="group relative bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <!-- Wishlist Button -->
                        <button onclick="toggleWishlist(<?php echo $recentProduct['id']; ?>, this)" 
                                class="absolute top-4 right-4 z-10 bg-white rounded-full w-9 h-9 shadow-md hover:bg-black hover:text-white transition <?php echo $isInWishlist ? 'bg-red-500 text-white' : ''; ?>"
                                data-product-id="<?php echo $recentProduct['id']; ?>"
                                title="<?php echo $isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                            <i class="<?php echo $isInWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        
                        <!-- Product Image -->
                        <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>">
                            <div class="relative overflow-hidden bg-gray-100" style="padding-top: 100%;">
                                <img src="<?php echo htmlspecialchars($recentImage); ?>" 
                                     alt="<?php echo htmlspecialchars($recentProduct['name'] ?? 'Product'); ?>"
                                     class="absolute top-0 left-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                            </div>
                        </a>
                        
                        <!-- Product Info -->
                        <div class="p-4">
                            <h3 class="text-base font-heading font-semibold mb-2 line-clamp-2">
                                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>" 
                                   class="hover:text-primary transition">
                                    <?php echo htmlspecialchars($recentProduct['name'] ?? 'Product'); ?>
                                </a>
                            </h3>
                            
                            <!-- Rating -->
                            <div class="flex items-center mb-2">
                                <?php for ($i = 0; $i < 5; $i++): 
                                    if ($i < $recentFullStars): ?>
                                        <i class="fas fa-star text-yellow-400 text-sm"></i>
                                    <?php elseif ($i == $recentFullStars && $recentHasHalfStar): ?>
                                        <i class="fas fa-star-half-alt text-yellow-400 text-sm"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-yellow-400 text-sm"></i>
                                    <?php endif;
                                endfor; ?>
                            </div>
                            
                            <!-- Price -->
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-bold text-primary">
                                    $<?php echo number_format(floatval($recentProduct['sale_price'] ?? $recentProduct['price'] ?? 0), 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Clear old cookies with wrong paths on page load
(function() {
    const oldPaths = ['/zensshop', '/oecom'];
    oldPaths.forEach(path => {
        document.cookie = `wishlist_items=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path}; SameSite=Lax`;
    });
})();

// Initialize wishlist buttons state on page load
document.addEventListener('DOMContentLoaded', function() {
    // Reload wishlist from cookie to ensure it's up to date
    if (typeof loadWishlist === 'function') {
        loadWishlist();
    }
    
    // Update wishlist button states for recently viewed products
    <?php if (!empty($recentlyViewed)): ?>
        <?php foreach ($recentlyViewed as $recentProduct): 
            $isInWishlist = false;
            foreach ($wishlistItems as $wishItem) {
                if ($wishItem['product_id'] == $recentProduct['id']) {
                    $isInWishlist = true;
                    break;
                }
            }
        ?>
            const btn<?php echo $recentProduct['id']; ?> = document.querySelector('[data-product-id="<?php echo $recentProduct['id']; ?>"]');
            if (btn<?php echo $recentProduct['id']; ?>) {
                const icon = btn<?php echo $recentProduct['id']; ?>.querySelector('i');
                if (icon) {
                    <?php if ($isInWishlist): ?>
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        btn<?php echo $recentProduct['id']; ?>.classList.add('bg-red-500', 'text-white');
                    <?php else: ?>
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        btn<?php echo $recentProduct['id']; ?>.classList.remove('bg-red-500', 'text-white');
                    <?php endif; ?>
                }
            }
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


