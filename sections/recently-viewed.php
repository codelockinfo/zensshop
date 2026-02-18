<?php
if (!isset($db)) {
    require_once __DIR__ . '/../classes/Database.php';
    $db = Database::getInstance();
}
if (!isset($product)) {
    require_once __DIR__ . '/../classes/Product.php';
    $product = new Product();
}
if (!isset($wishlistIds)) {
    require_once __DIR__ . '/../classes/Wishlist.php';
    $wishlistObj = new Wishlist();
    $wishlistItems = $wishlistObj->getWishlist();
    $wishlistIds = array_column($wishlistItems, 'product_id');
}

// Get recently viewed (from cookie)
$recentIds = isset($_COOKIE['recently_viewed']) ? json_decode($_COOKIE['recently_viewed'], true) : [];
if (!is_array($recentIds)) { $recentIds = []; }

// Exclude current product if provided
if ($productId) {
    $recentIds = array_filter($recentIds, function($id) use ($productId) {
        return $id != $productId;
    });
}
$recentIds = array_slice($recentIds, 0, 10);

if (empty($recentIds)) {
    echo ''; exit;
}

$placeholders = implode(',', array_fill(0, count($recentIds), '?'));
$recentlyViewed = $db->fetchAll(
    "SELECT * FROM products WHERE (product_id IN ($placeholders) OR id IN ($placeholders)) AND status = 'active' LIMIT 10",
    array_merge($recentIds, $recentIds)
);

// Final filter to be absolutely sure the current product is not shown
if ($productId) {
    $recentlyViewed = array_filter($recentlyViewed, function($item) use ($productId) {
        return $item['id'] != $productId && $item['product_id'] != $productId;
    });
}

if (empty($recentlyViewed)) {
    echo ''; exit;
}

$baseUrl = getBaseUrl();

$settingsObj = new Settings();
$productStylesJson = $settingsObj->get('product_page_styles', '[]');
$productStyles = json_decode($productStylesJson, true);

$showRecent = $productStyles['show_recent'] ?? '1';
$recentTitle = $productStyles['recent_title'] ?? 'Recently Viewed';
$recentSubtitle = $productStyles['recent_subtitle'] ?? "Explore your recently viewed items, blending quality and style for a refined living experience.";

if ($showRecent !== '1') {
    echo ''; exit;
}
?>

<div class="text-center mb-8 px-4">
    <h2 class="text-2xl font-heading font-bold mb-2"><?php echo htmlspecialchars($recentTitle); ?></h2>
    <p class="text-gray-600 text-sm md:text-base max-w-2xl mx-auto"><?php echo htmlspecialchars($recentSubtitle); ?></p>
</div>

<div class="swiper recently-viewed-slider pb-12 px-4 md:px-12 relative overflow-hidden">
    <div class="swiper-wrapper">
        <?php foreach ($recentlyViewed as $item): 
            $itemImage = getProductImage($item);
            $itemPrice = $item['sale_price'] ?? $item['price'] ?? 0;
            $itemOriginalPrice = !empty($item['sale_price']) ? $item['price'] : null;
            
            $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
            $inWishlist = in_array($currentId, $wishlistIds);
        ?>
        <div class="swiper-slide h-auto">
            <div class="group product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative flex flex-col h-full w-full max-w-sm">
                <div class="relative overflow-hidden">
                    <a class="product-card-view-link" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="block">
                        <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                                onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                    </a>
                    <div class="absolute top-2 right-2 z-30 flex flex-col items-center gap-2">
                        <button id="product-card-wishlist-btn" type="button" class="wishlist-btn product-action-btn w-10 h-10 rounded-full flex items-center justify-center relative group transition <?php echo $inWishlist ? 'wishlist-active text-white' : ''; ?>"
                                data-product-id="<?php echo $currentId; ?>"
                                aria-label="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                                title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
                            <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                        </button>
                        
                        <a id="product-card-quick-view-btn" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                           class="product-action-btn w-10 h-10 rounded-full flex items-center justify-center transition shadow-lg quick-view-btn relative group opacity-100 md:opacity-0 md:group-hover:opacity-100" 
                           data-product-id="<?php echo $currentId; ?>"
                           data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                           data-product-price="<?php echo $itemPrice; ?>"
                           aria-label="Quick view product"
                           data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                            <span class="product-tooltip">Quick View</span>
                        </a>
                    </div>
                </div>
                <div class="p-4 flex flex-col flex-1">
                    <h3 class="font-semibold text-gray-800 mb-2 h-10 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                        <a class="product-card-view-link" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition">
                            <?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>
                        </a>
                    </h3>
                    <div class="flex items-center mb-3">
                        <div class="flex text-yellow-400">
                            <?php 
                            $itemRatingValue = floor($item['rating'] ?? 5);
                            for ($i = 0; $i < 5; $i++): 
                            ?>
                            <i class="fas fa-star text-[10px] <?php echo $i < $itemRatingValue ? '' : 'text-gray-300'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-auto">
                        <span class="product-price text-base font-bold">
                            <?php echo format_price($itemPrice, $item['currency'] ?? 'USD'); ?>
                        </span>
                        <?php if ($itemOriginalPrice): ?>
                            <span class="compare-price font-bold line-through text-xs"><?php echo format_price($itemOriginalPrice, $item['currency'] ?? 'USD'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    $vLoaderData = $product->getVariants($item['id']);
                    $dAttrs = [];
                    if (!empty($vLoaderData['variants'])) {
                        $dV = $vLoaderData['variants'][0];
                        foreach ($vLoaderData['variants'] as $v) {
                            if (!empty($v['is_default'])) { $dV = $v; break; }
                        }
                        $dAttrs = $dV['variant_attributes'];
                    }
                    $attrsJ = json_encode($dAttrs);
                    $oos = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                    ?>
                    <div class="mt-4">
                        <button id="product-card-add-to-cart-btn" type="button" onclick="addToCart('<?php echo $currentId; ?>', 1, this, <?php echo htmlspecialchars($attrsJ, ENT_QUOTES, 'UTF-8'); ?>)" 
                                data-product-id="<?php echo $currentId; ?>"
                                data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                data-product-price="<?php echo $finalPrice; ?>"
                                data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>" 
                                class="productAddToCartBtn w-full bg-[#1a3d32] text-white px-4 py-2.5 rounded hover:bg-black transition text-xs font-bold flex items-center justify-center gap-2 <?php echo $oos ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo $oos ? 'disabled' : ''; ?>>
                            <?php if ($oos): ?>
                                <span><?php echo strtoupper(get_stock_status_text($item['stock_status'] ?? 'in_stock', $item['stock_quantity'] ?? 0)); ?></span>
                            <?php else: ?>
                                <i class="fas fa-shopping-cart text-[10px]"></i>
                                <span>ADD TO CART</span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (count($recentlyViewed) > 1): ?>
    <button class="absolute left-2 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 recently-viewed-prev border border-white <?php echo count($recentlyViewed) <= 4 ? 'lg:hidden' : ''; ?>" aria-label="Previous">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>
    <button class="absolute right-2 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 recently-viewed-next border border-white <?php echo count($recentlyViewed) <= 4 ? 'lg:hidden' : ''; ?>" aria-label="Next">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
</div>
