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

// Load Settings
require_once __DIR__ . '/classes/Settings.php';
$settingsObj = new Settings();

// Fetch Content Settings
$pageHeading = $settingsObj->get('wishlist_heading', 'Wishlist');
$pageSubheading = $settingsObj->get('wishlist_subheading', 'Explore your saved items, blending quality and style for a refined living experience.');

// Fetch Global Styles
$globalStylesJson = $settingsObj->get('global_card_styles', '{}');
$globalStyles = json_decode($globalStylesJson, true);

// Fetch Wishlist Styles
$stylesJson = $settingsObj->get('wishlist_styles', '[]');
$styles = json_decode($stylesJson, true);

// Extract Styles with Defaults (Global -> Wishlist Override)
$w_page_bg_color = $styles['page_bg_color'] ?? '#ffffff';
$w_card_bg_color = $styles['card_bg_color'] ?? $globalStyles['card_bg_color'] ?? '#ffffff';
$w_card_title_color = $styles['card_title_color'] ?? $globalStyles['card_title_color'] ?? '#1f2937';
$w_price_color = $styles['price_color'] ?? $globalStyles['price_color'] ?? '#1a3d32';
$w_compare_price_color = $styles['compare_price_color'] ?? $globalStyles['compare_price_color'] ?? '#9ca3af';
$w_stock_status_color = $styles['stock_status_color'] ?? '#ef4444';

// Use Global Action Button styles as base for specific buttons if not set
$g_btn_bg = $globalStyles['btn_bg_color'] ?? '#ffffff';
$g_btn_icon = $globalStyles['btn_icon_color'] ?? '#000000';
$g_btn_hover_bg = $globalStyles['btn_hover_bg_color'] ?? '#000000';
$g_btn_hover_icon = $globalStyles['btn_hover_icon_color'] ?? '#ffffff';

// Global ATC Button
$g_atc_bg = $globalStyles['atc_btn_bg_color'] ?? '#1a3d32';
$g_atc_text = $globalStyles['atc_btn_text_color'] ?? '#ffffff';
$g_atc_hover_bg = $globalStyles['atc_btn_hover_bg_color'] ?? '#000000';
$g_atc_hover_text = $globalStyles['atc_btn_hover_text_color'] ?? '#ffffff';

// Add to Cart Button (Wishlist) - Default to Global ATC if set, else fallback
$w_btn_bg_color = $styles['btn_bg_color'] ?? $g_atc_bg;
$w_btn_text_color = $styles['btn_text_color'] ?? $g_atc_text; 
$w_btn_hover_bg_color = $styles['btn_hover_bg_color'] ?? $g_atc_hover_bg;
$w_btn_hover_text_color = $styles['btn_hover_text_color'] ?? $g_atc_hover_text;

$w_remove_bg = $styles['remove_btn_bg_color'] ?? $g_btn_bg;
$w_remove_icon = $styles['remove_btn_icon_color'] ?? $g_btn_icon;
$w_remove_hover_bg = $styles['remove_btn_hover_bg_color'] ?? $g_btn_hover_bg;
$w_remove_hover_icon = $styles['remove_btn_hover_icon_color'] ?? $g_btn_hover_icon;

$w_qv_bg = $styles['quick_view_bg_color'] ?? $g_btn_bg;
$w_qv_icon = $styles['quick_view_icon_color'] ?? $g_btn_icon;
$w_qv_hover_bg = $styles['quick_view_hover_bg_color'] ?? $g_btn_hover_bg;
$w_qv_hover_icon = $styles['quick_view_hover_icon_color'] ?? $g_btn_hover_icon;

$w_tooltip_bg = $styles['tooltip_bg_color'] ?? $globalStyles['tooltip_bg_color'] ?? '#000000';
$w_tooltip_text = $styles['tooltip_text_color'] ?? $globalStyles['tooltip_text_color'] ?? '#ffffff';



// Get wishlist items
$wishlistItems = $wishlist->getWishlist();



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

<style>
    body {
        background-color: <?php echo $w_page_bg_color; ?> !important;
    }
    .wishlist-card, .recently-viewed-card {
        background-color: <?php echo $w_card_bg_color; ?> !important;
    }
    .wishlist-card h3, .recently-viewed-card h3 {
        color: <?php echo $w_card_title_color; ?> !important;
    }
    .wishlist-card .product-price, .recently-viewed-card .product-price {
        color: <?php echo $w_price_color; ?> !important;
    }
    .wishlist-card .compare-price, .recently-viewed-card .compare-price {
        color: <?php echo $w_compare_price_color; ?> !important;
    }
    .wishlist-atc-btn {
        background-color: <?php echo $w_btn_bg_color; ?> !important;
        color: <?php echo $w_btn_text_color; ?> !important;
    }
    .wishlist-atc-btn:hover {
        background-color: <?php echo $w_btn_hover_bg_color; ?> !important;
        color: <?php echo $w_btn_hover_text_color; ?> !important;
    }
    /* Specific Action Buttons */
    .wishlist-remove-btn, .wishlist-qv-btn, .wishlist-btn.product-action-btn:not(.wishlist-active) {
        background-color: <?php echo $g_btn_bg; ?> !important;
        color: <?php echo $g_btn_icon; ?> !important;
    }
    .wishlist-remove-btn:hover, .wishlist-qv-btn:hover, .wishlist-btn.product-action-btn:not(.wishlist-active):hover {
        background-color: <?php echo $g_btn_hover_bg; ?> !important;
        color: <?php echo $g_btn_hover_icon; ?> !important;
    }
    /* Active State */
    .wishlist-active {
        background-color: var(--btn-active-bg) !important;
        color: var(--btn-active-icon) !important;
    }
</style>

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
        <nav class="breadcrumb-nav text-sm text-gray-600 mb-8 mt-4 md:mt-0">
            <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a>
            <span class="mx-2">></span>
            <span class="text-gray-900">Wishlist</span>
        </nav>

        <h1 class="text-2xl md:text-4xl font-heading font-bold text-center mb-2" style="color: <?php echo $styles['heading_color'] ?? '#1f2937'; ?>;"><?php echo htmlspecialchars($pageHeading); ?></h1>
        <?php if (!empty($pageSubheading)): ?>
        <p class="text-center mb-8 md:mb-12 max-w-2xl mx-auto" style="color: <?php echo $styles['subheading_color'] ?? '#4b5563'; ?>;"><?php echo htmlspecialchars($pageSubheading); ?></p>
        <?php else: ?>
        <div class="mb-5 md:mb-12"></div>
        <?php endif; ?>
        
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
                    <div class="product-card wishlist-card group relative rounded-lg overflow-hidden hover:shadow-lg transition-shadow flex flex-col h-full" style="background-color: <?php echo $w_card_bg_color; ?>;">
                        <div class="absolute top-2 right-2 z-30 flex flex-col items-center gap-2">
                            <!-- Remove Button -->
                            <?php 
                            $finalPrice = !empty($item['sale_price']) ? $item['sale_price'] : $item['price'];
                            ?>
                            <button onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)" 
                                    class="wishlist-remove-btn w-10 h-10 rounded-full shadow-md transition flex items-center justify-center relative group product-action-btn">
                                <i class="fas fa-times"></i>
                                <span class="product-tooltip">Remove</span>
                            </button>
                            
                            <button type="button" 
                                    class="wishlist-qv-btn w-10 h-10 rounded-full shadow-md transition flex items-center justify-center quick-view-btn relative group product-action-btn opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                    data-product-id="<?php echo $item['product_id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                    data-product-price="<?php echo $finalPrice; ?>"
                                    data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                <i class="fas fa-eye"></i>
                                <span class="product-tooltip">Quick View</span>
                            </button>
                        </div>
                        
                        <!-- Product Image -->
                        <a href="<?php echo url('product?slug=' . urlencode($item['slug'] ?? '')); ?>">
                            <div class="relative overflow-hidden bg-gray-50 h-64">
                                <?php 
                                $imageUrl = getImageUrl($item['image'] ?? '');
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                            </div>
                        </a>
                        
                        <!-- Product Info -->
                        <div class="p-4 flex flex-col flex-grow">
                            <h3 class="font-semibold text-gray-800 mb-2 card-title" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 3rem; line-height: 1.5rem;">
                                <a href="<?php echo url('product?slug=' . urlencode($item['slug'] ?? '')); ?>" class="hover:text-primary transition">
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
                            
                            <div class="flex items-center gap-2 mb-4">
                                <span class="text-lg font-bold current-price"><?php echo format_price($finalPrice, $item['currency'] ?? 'USD'); ?></span>
                                <?php if (!empty($item['sale_price'])): ?>
                                    <span class="text-gray-400 text-sm line-through compare-price"><?php echo format_price($item['price'], $item['currency'] ?? 'USD'); ?></span>
                                <?php endif; ?>
                            </div>
                                
                            <!-- Add to Cart Button -->
                            <?php 
                            $isOutOfStock = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                            
                            // Get first variant for default attributes
                            $vData = $product->getVariants($item['product_id']);
                            $defaultAttributes = [];
                            if (!empty($vData['variants'])) {
                                $defaultVariant = $vData['variants'][0];
                                foreach ($vData['variants'] as $v) {
                                    if (!empty($v['is_default'])) {
                                        $defaultVariant = $v;
                                        break;
                                    }
                                }
                                $defaultAttributes = $defaultVariant['variant_attributes'];
                            }
                            $attributesJson = json_encode($defaultAttributes);
                            ?>
                            <button onclick='addToCart(<?php echo $item['product_id']; ?>, 1, this, <?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>)'
                                    class="w-full py-3 text-[12px] rounded-lg transition flex items-center justify-center gap-2 mt-auto wishlist-atc-btn productAddToCartBtn <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <span><?php echo $isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?></span>
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
                        <div class="product-card recently-viewed-card group relative border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow flex flex-col h-full" style="background-color: <?php echo $w_card_bg_color; ?>;">
                            <div class="absolute top-2 right-2 z-30 flex flex-col items-center gap-2">
                                <!-- Wishlist Button -->
                                <button onclick="toggleWishlist(<?php echo $recentProduct['id']; ?>, this)" 
                                        class="wishlist-btn product-action-btn w-10 h-10 rounded-full shadow-md transition flex items-center justify-center relative group <?php echo $isInWishlist ? 'wishlist-active text-white' : ''; ?>"
                                        data-product-id="<?php echo $recentProduct['id']; ?>"
                                        aria-label="<?php echo $isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>"
                                        title="<?php echo $isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                    <i class="<?php echo $isInWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                    <span class="product-tooltip"><?php echo $isInWishlist ? 'Remove' : 'Add to wishlist'; ?></span>
                                </button>

                                <button type="button" 
                                        class="quick-view-btn product-action-btn w-10 h-10 rounded-full shadow-md transition flex items-center justify-center relative group opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                        data-product-id="<?php echo $recentProduct['id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($recentProduct['name'] ?? ''); ?>"
                                        data-product-price="<?php echo $displayPrice; ?>"
                                        data-product-slug="<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>">
                                    <i class="fas fa-eye"></i>
                                    <span class="product-tooltip">Quick View</span>
                                </button>
                            </div>
                            
                            <!-- Product Image -->
                            <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo htmlspecialchars($recentProduct['slug'] ?? ''); ?>">
                                <div class="relative overflow-hidden bg-gray-50 h-64">
                                    <img src="<?php echo htmlspecialchars($recentImage); ?>" 
                                         alt="<?php echo htmlspecialchars($recentProduct['name'] ?? 'Product'); ?>"
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI0YzRjRGNiIvPjxjaXJjbGUgY3g9IjIwMCIgY3k9IjIwMCIgcj0iNjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                                </div>
                            </a>
                            
                            <!-- Product Info -->
                            <div class="p-4 flex flex-col flex-1">
                                <h3 class="font-semibold text-gray-800 mb-2 card-title" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 3rem; line-height: 1.5rem;">
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
                                $displayPrice = !empty($recentProduct['sale_price']) ? $recentProduct['sale_price'] : $recentProduct['price'];
                                $originalPrice = (!empty($recentProduct['sale_price']) && $recentProduct['sale_price'] < $recentProduct['price']) ? $recentProduct['price'] : null;
                                ?>
                                <span class="text-lg font-bold product-price">
                                    <?php echo format_price($displayPrice, $recentProduct['currency'] ?? 'USD'); ?>
                                </span>
                                <?php if ($originalPrice): ?>
                                    <span class="text-gray-400 text-sm line-through compare-price"><?php echo format_price($originalPrice, $recentProduct['currency'] ?? 'USD'); ?></span>
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
                        btn<?php echo $recentProduct['id']; ?>.classList.add('wishlist-active', 'text-white');
                    <?php else: ?>
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        btn<?php echo $recentProduct['id']; ?>.classList.remove('wishlist-active', 'text-white');
                    <?php endif; ?>
                }
            }
        <?php endforeach; ?>
    <?php endif; ?>

    // Standard tooltips are handled by CSS
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
