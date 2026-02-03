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

<!-- Breadcrumb and Wishlist Section -->
<div class="container mx-auto px-4 py-4 md:py-12">
    <!-- Wishlist Skeleton -->
    <div id="wishlistSkeleton" class="animate-pulse">
        <!-- Breadcrumb Skeleton -->
        <div class="h-4 bg-gray-200 rounded w-32 mb-8"></div>
        
        <!-- Title Skeleton -->
        <div class="h-10 bg-gray-200 rounded w-48 mx-auto mb-12"></div>
        
        <!-- Grid Skeleton -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            <?php for($i=0; $i<4; $i++): ?>
            <div class="bg-white border border-gray-100 rounded-lg p-4 space-y-4">
                <div class="w-full h-64 bg-gray-200 rounded-lg"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="h-10 bg-gray-200 rounded w-full mt-4"></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Recently Viewed Title Skeleton -->
        <div class="h-10 bg-gray-200 rounded w-64 mx-auto mb-4 mt-20"></div>
        <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto mb-12"></div>

        <!-- Grid Skeleton -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            <?php for($i=0; $i<4; $i++): ?>
            <div class="bg-white border border-gray-100 rounded-lg p-4 space-y-4">
                <div class="w-full h-64 bg-gray-200 rounded-lg"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div id="mainWishlistContent" class="hidden">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-600 mb-8 mt-4 md:mt-0">
            <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a>
            <span class="mx-2">></span>
            <span class="text-gray-900">Wishlist</span>
        </nav>

        <h1 class="text-2xl md:text-4xl font-heading font-bold text-center mb-5 md:mb-12">Wishlist</h1>
        
        <?php if (empty($wishlistItems)): ?>
            <!-- Empty Wishlist -->
            <div class="text-center py-16">
                <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-heading font-bold mb-2">Your wishlist is empty</h2>
                <p class="text-gray-600 mb-6">Start adding products you love to your wishlist!</p>
                <a href="<?php echo $baseUrl; ?>/shop" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-primary-light hover:text-white transition">
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Wishlist Items -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
                <?php foreach ($wishlistItems as $item): ?>
                    <div class="group relative bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow flex flex-col h-full">
                        <!-- Remove Button -->
                        <button onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)" 
                                class="absolute top-2 md:top-4 right-2 md:right-4 z-10 bg-white rounded-full h-9 w-9 shadow-md hover:bg-black hover:text-white transition">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <!-- Product Image -->
                        <a href="<?php echo url('product?slug=' . urlencode($item['slug'] ?? '')); ?>">
                            <div class="relative overflow-hidden bg-gray-50 h-64">
                                <?php 
                                $imageUrl = getImageUrl($item['image'] ?? '');
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                     class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                            </div>
                        </a>
                        
                        <!-- Product Info -->
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="text-sm font-semibold text-gray-800 mb-2 h-10 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                                    <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo htmlspecialchars($item['slug'] ?? ''); ?>"  
                                   class="hover:text-primary transition">
                                        <?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>
                                    </a>
                            </h3>
                            
                            <!-- Rating -->
                            <div class="flex items-center mb-3">
                                <div class="flex text-yellow-400">
                                    <?php 
                                    $rating = floor($item['rating'] ?? 5);
                                    for ($i = 0; $i < 5; $i++): 
                                    ?>
                                    <i class="fas fa-star text-xs <?php echo $i < $rating ? '' : 'text-gray-300'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-4 mt-auto">
                                <?php 
                                $displayPrice = !empty($item['sale_price']) ? $item['sale_price'] : $item['price'];
                                $originalPrice = (!empty($item['sale_price']) && $item['sale_price'] < $item['price']) ? $item['price'] : null;
                                ?>
                                <span class="text-base font-bold <?php echo $originalPrice ? 'text-[#1a3d32]' : 'text-primary'; ?>">
                                    <?php echo format_price($displayPrice, $item['currency'] ?? 'USD'); ?>
                                </span>
                                <?php if ($originalPrice): ?>
                                    <span class="text-gray-400 font-bold line-through text-xs"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                <?php endif; ?>
                            </div>
                                
                            <!-- Add to Cart Button -->
                            <?php
                            // Get default variant attributes
                            $variantsData = $product->getVariants($item['product_id']);
                            $defaultAttributes = [];
                            if (!empty($variantsData['variants'])) {
                                $defaultVariant = $variantsData['variants'][0];
                                foreach ($variantsData['variants'] as $v) {
                                    if (!empty($v['is_default'])) {
                                        $defaultVariant = $v;
                                        break;
                                    }
                                }
                                $defaultAttributes = $defaultVariant['variant_attributes'];
                            }
                            $attributesJson = json_encode($defaultAttributes);
                            $isOutOfStock = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                            ?>
                            <button onclick='addToCart(<?php echo $item['product_id']; ?>, 1, this, <?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="w-full bg-[#1a3d32] text-white px-4 py-2.5 rounded hover:bg-black transition text-xs font-bold flex items-center justify-center gap-2 <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                <?php if ($isOutOfStock): ?>
                                    <span><?php echo strtoupper(get_stock_status_text($item['stock_status'] ?? 'in_stock', $item['stock_quantity'] ?? 0)); ?></span>
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart text-[10px]"></i>
                                    <span>ADD TO CART</span>
                                <?php endif; ?>
                            </button>
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
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
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
                        <div class="group relative bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow flex flex-col h-full">
                            <!-- Wishlist Button -->
                            <button onclick="toggleWishlist(<?php echo $recentProduct['id']; ?>, this)" 
                                    class="absolute top-4 right-4 z-10 bg-white rounded-full w-9 h-9 shadow-md hover:bg-black hover:text-white transition <?php echo $isInWishlist ? 'bg-red-500 text-white' : ''; ?>"
                                    data-product-id="<?php echo $recentProduct['id']; ?>"
                                    title="<?php echo $isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                <i class="<?php echo $isInWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                            
                            <!-- Product Image -->
                            <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>">
                                <div class="relative overflow-hidden bg-gray-50 h-64">
                                    <img src="<?php echo htmlspecialchars($recentImage); ?>" 
                                         alt="<?php echo htmlspecialchars($recentProduct['name'] ?? 'Product'); ?>"
                                         class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                                </div>
                            </a>
                            
                            <!-- Product Info -->
                            <div class="p-4 flex flex-col flex-1">
                                <h3 class="text-sm font-semibold text-gray-800 mb-2 h-10 overflow-hidden line-clamp-2">
                                    <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>" 
                                       class="hover:text-primary transition">
                                        <?php echo htmlspecialchars($recentProduct['name'] ?? 'Product'); ?>
                                    </a>
                                </h3>
                                
                                <div class="flex items-center mb-3">
                                    <div class="flex text-yellow-400">
                                        <?php 
                                        $ratingValue = floor($recentProduct['rating'] ?? 5);
                                        for ($i = 0; $i < 5; $i++): 
                                        ?>
                                        <i class="fas fa-star text-xs <?php echo $i < $ratingValue ? '' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 mt-auto">
                                    <?php 
                                    $rPrice = !empty($recentProduct['sale_price']) ? $recentProduct['sale_price'] : $recentProduct['price'];
                                    $rOrgPrice = (!empty($recentProduct['sale_price']) && $recentProduct['sale_price'] < $recentProduct['price']) ? $recentProduct['price'] : null;
                                    ?>
                                    <span class="text-base font-bold <?php echo $rOrgPrice ? 'text-[#1a3d32]' : 'text-primary'; ?>">
                                        <?php echo format_price($rPrice, $recentProduct['currency'] ?? 'USD'); ?>
                                    </span>
                                    <?php if ($rOrgPrice): ?>
                                    <span class="text-gray-400 line-through text-xs"><?php echo format_price($rOrgPrice, $recentProduct['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Skeleton Loader Handling
document.addEventListener('DOMContentLoaded', function() {
    const skeleton = document.getElementById('wishlistSkeleton');
    const content = document.getElementById('mainWishlistContent');
    if (skeleton && content) {
        skeleton.classList.add('hidden');
        content.classList.remove('hidden');
    }
});
</script>

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

    // Custom tooltip for remove buttons
    document.querySelectorAll('button[onclick^="removeFromWishlist"]').forEach(btn => {
        btn.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-remove-tooltip';
            tooltip.textContent = this.getAttribute('title') || 'Remove from Wishlist';
            tooltip.style.cssText = `
                position: fixed;
                background: #000;
                color: #fff;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 10000;
                pointer-events: none;
                font-weight: 500;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2) + 'px';
            tooltip.style.left = (rect.left - tooltip.offsetWidth - 8) + 'px';
            
            this._tooltip = tooltip;
        });
        
        btn.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


