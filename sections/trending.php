<?php
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Wishlist.php';
require_once __DIR__ . '/../classes/Settings.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
$product = new Product();
$wishlistObj = new Wishlist();

// Get Wishlist IDs for checking status
$wishlistItems = $wishlistObj->getWishlist();
$wishlistIds = array_column($wishlistItems, 'product_id');

$products = $db->fetchAll(
    "SELECT p.*, h.heading, h.subheading
     FROM products p 
     JOIN section_trending_products h ON p.product_id = h.product_id 
     WHERE p.status = 'active' 
     AND (h.store_id = ? OR h.store_id IS NULL)
     AND p.store_id = ?
     ORDER BY h.sort_order ASC",
    [CURRENT_STORE_ID, CURRENT_STORE_ID]
);

// Fetch dynamic headers if available from any row
$sectionHeading = 'Trending Jewelry';
$sectionSubheading = 'Unmatched design—superior performance and customer satisfaction in one.';

$productsConfigPath = __DIR__ . '/../admin/homepage_products_config.json';
if (file_exists($productsConfigPath)) {
    $conf = json_decode(file_get_contents($productsConfigPath), true);
    $sectionHeading = $conf['tr_heading'] ?? $sectionHeading;
    $sectionSubheading = $conf['tr_subheading'] ?? $sectionSubheading;
} elseif (!empty($products)) {
    $sectionHeading = $products[0]['heading'] ?? $sectionHeading;
    $sectionSubheading = $products[0]['subheading'] ?? $sectionSubheading;
}

// Fallback logic removed per user request
?>

<?php if (!empty($products)): 
    // Fetch Global Styles
    $settingsObj = new Settings();
    $globalStylesJson = $settingsObj->get('global_card_styles', '{}');
    $globalStyles = json_decode($globalStylesJson, true);
    
    // Fetch Section Styles
    $stylesJson = $settingsObj->get('trending_styles', '{}'); 
    $styles = json_decode($stylesJson, true);
    
    // Defaults (Global -> specific override if not empty)
    // Helper to get style with fallback
    function getStyleTrending($key, $local, $global, $default) {
        return !empty($local[$key]) ? $local[$key] : (!empty($global[$key]) ? $global[$key] : $default);
    }
    
    // Define styles
    $bg_color = getStyleTrending('bg_color', $styles, $globalStyles, '#ffffff');
    $heading_color = getStyleTrending('heading_color', $styles, $globalStyles, '#1f2937');
    $subheading_color = getStyleTrending('subheading_color', $styles, $globalStyles, '#4b5563');
    
    $card_bg_color = getStyleTrending('card_bg_color', $styles, $globalStyles, '#ffffff');
    $card_title_color = getStyleTrending('card_title_color', $styles, $globalStyles, '#1F2937');
    $price_color = getStyleTrending('price_color', $styles, $globalStyles, '#1a3d32');
    $compare_price_color = getStyleTrending('compare_price_color', $styles, $globalStyles, '#9ca3af');
    
    $badge_bg_color = getStyleTrending('badge_bg_color', $styles, $globalStyles, '#ef4444');
    $badge_text_color = getStyleTrending('badge_text_color', $styles, $globalStyles, '#ffffff');
    
    $arrow_bg_color = getStyleTrending('arrow_bg_color', $styles, $globalStyles, '#ffffff');
    $arrow_icon_color = getStyleTrending('arrow_icon_color', $styles, $globalStyles, '#1f2937');
    
    $btn_bg_color = getStyleTrending('btn_bg_color', $styles, $globalStyles, '#ffffff');
    $btn_icon_color = getStyleTrending('btn_icon_color', $styles, $globalStyles, '#000000');
    $btn_hover_bg_color = getStyleTrending('btn_hover_bg_color', $styles, $globalStyles, '#000000');
    $btn_hover_icon_color = getStyleTrending('btn_hover_icon_color', $styles, $globalStyles, '#ffffff');
    $btn_active_bg_color = getStyleTrending('btn_active_bg_color', $styles, $globalStyles, '#000000');
    $btn_active_icon_color = getStyleTrending('btn_active_icon_color', $styles, $globalStyles, '#ffffff');
    
    $tooltip_bg_color = getStyleTrending('tooltip_bg_color', $styles, $globalStyles, '#000000');
    $tooltip_text_color = getStyleTrending('tooltip_text_color', $styles, $globalStyles, '#ffffff');

    $sectionId = 'trending-section-' . rand(1000, 9999);
?>

<style>
    #<?php echo $sectionId; ?> {
        background-color: <?php echo $bg_color; ?>;
    }
    #<?php echo $sectionId; ?> .section-heading {
        color: <?php echo $heading_color; ?>;
    }
    #<?php echo $sectionId; ?> .section-subheading {
        color: <?php echo $subheading_color; ?>;
    }
    #<?php echo $sectionId; ?> .product-price {
        color: <?php echo $price_color; ?>;
    }
    #<?php echo $sectionId; ?> .product-compare-price {
        color: <?php echo $compare_price_color; ?>;
    }
    #<?php echo $sectionId; ?> .discount-badge {
        background-color: <?php echo $badge_bg_color; ?> !important;
        color: <?php echo $badge_text_color; ?> !important;
    }
    #<?php echo $sectionId; ?> .custom-arrow {
        background-color: <?php echo $arrow_bg_color; ?>;
        color: <?php echo $arrow_icon_color; ?>;
    }
    #<?php echo $sectionId; ?> .custom-arrow:hover {
        background-color: <?php echo $arrow_bg_color; ?>;
        color: <?php echo $arrow_icon_color; ?>;
        opacity: 0.8;
    }

    /* Action Buttons (Wishlist, Quick View, Add to Cart) */
    #<?php echo $sectionId; ?> .product-action-btn,
    #<?php echo $sectionId; ?> .wishlist-btn {
        background-color: <?php echo $btn_bg_color; ?> !important;
        color: <?php echo $btn_icon_color; ?> !important;
        border: 1px solid transparent; 
    }

    #<?php echo $sectionId; ?> .product-action-btn:hover,
    #<?php echo $sectionId; ?> .wishlist-btn:hover {
        background-color: <?php echo $btn_hover_bg_color; ?> !important;
        color: <?php echo $btn_hover_icon_color; ?> !important;
    }

    /* Active Wishlist Button */
    #<?php echo $sectionId; ?> .wishlist-btn.bg-black {
        background-color: <?php echo $btn_active_bg_color; ?> !important;
        color: <?php echo $btn_active_icon_color; ?> !important;
    }
    
    #<?php echo $sectionId; ?> .wishlist-btn.bg-black:hover {
        background-color: <?php echo $btn_active_bg_color; ?> !important;
        color: <?php echo $btn_active_icon_color; ?> !important;
        opacity: 0.9;
    }
    
    /* Tooltip Colors */
    #<?php echo $sectionId; ?> .product-tooltip {
        background-color: <?php echo $tooltip_bg_color; ?> !important;
        color: <?php echo $tooltip_text_color; ?> !important;
    }

    /* Tooltip Arrow */
    #<?php echo $sectionId; ?> .product-tooltip::after {
        border-top-color: <?php echo $tooltip_bg_color; ?> !important;
    }

    /* Product Card Background */
    #<?php echo $sectionId; ?> .product-card {
        background-color: <?php echo $card_bg_color; ?> !important;
    }
    
    /* Product Title Color */
    #<?php echo $sectionId; ?> .product-card h3 a,
    #<?php echo $sectionId; ?> .product-card h3 {
        color: <?php echo $card_title_color; ?> !important;
    }
</style>

<section id="<?php echo $sectionId; ?>" class="py-5 md:py-14">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4 section-heading"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <p class="text-sm md:text-md max-w-2xl mx-auto section-subheading"><?php echo htmlspecialchars($sectionSubheading); ?></p>
        </div>
        
        <!-- Product Slider Container -->
        <div class="relative">
            <!-- Slider Wrapper -->
            <div class="trending-slider overflow-hidden">
                <div class="flex gap-6" id="trendingSlider" style="will-change: transform;">
                    <?php foreach ($products as $item): 
                $mainImage = getProductImage($item);
                $price = $item['sale_price'] ?? $item['price'];
                $originalPrice = $item['sale_price'] ? $item['price'] : null;
                $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
                
                $firstVariant = $db->fetchOne(
                    "SELECT variant_attributes FROM product_variants WHERE product_id = ? ORDER BY is_default DESC, id ASC LIMIT 1",
                    [$item['product_id']]
                );
                $defaultAttributes = $firstVariant ? json_decode($firstVariant['variant_attributes'], true) : [];
                $attributesJson = json_encode($defaultAttributes);
            ?>
                    <div class="min-w-full md:min-w-[300px] my-2">
                        <div class="product-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300 group relative">
                <div class="relative overflow-hidden">
                    <a class="product-card-view-link" href="<?php echo url('product?slug=' . urlencode($item['slug'] ?? '')); ?>">
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                    </a>
                    
                    <!-- Discount Badge -->
                    <?php if ($discount > 0): ?>
                    <span class="absolute top-2 left-2 px-2 py-1 text-xs font-bold rounded discount-badge">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                    
                    <!-- Wishlist Icon (Always Visible) -->
                    <?php 
                    $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                    $inWishlist = in_array($currentId, $wishlistIds);
                    ?>
                    <div class="absolute top-2 right-2 z-30 flex flex-col items-center gap-2">
                    <button id="product-card-wishlist-btn" class="w-10 h-10 rounded-full flex items-center justify-center relative group <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white transition wishlist-btn" 
                            data-product-id="<?php echo $currentId; ?>"
                            aria-label="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                            title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                        <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
                        <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                    </button>
                    
                    <div class="flex flex-col gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-300">
                        <a id="product-card-quick-view-btn" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                           class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                           data-product-id="<?php echo $item['product_id']; ?>"
                           data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                           data-product-price="<?php echo $finalPrice; ?>"
                           aria-label="Quick view product"
                           data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                            <span class="product-tooltip">Quick View</span>
                        </a>
                        <button id="product-card-add-to-cart-btn" class="productAddToCartBtn product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group <?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                aria-label="Add product to cart"
                                data-product-id="<?php echo $item['product_id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                data-product-price="<?php echo $finalPrice; ?>"
                                data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>"
                                data-attributes='<?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>'
                                <?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                            <span class="product-tooltip"><?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?></span>
                        </button>
                    </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <h3 class="font-semibold text-sm md:text-base text-gray-800 md:max-w-[250px] max-w-[250px] mb-2 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 3rem; line-height: 1.5rem;" title="<?php echo htmlspecialchars($item['name']); ?>">
                        <a class="product-card-view-link" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition block">
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
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                        <p class="text-md font-bold product-price"><?php echo format_price($price, $item['currency'] ?? 'USD'); ?></p>
                            <?php if ($originalPrice): ?>
                            <span class="text-gray-400 line-through text-sm block product-compare-price"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php 
            $productsConfigPath = __DIR__ . '/../admin/homepage_products_config.json';
            $showTRArrows = true;
            if (file_exists($productsConfigPath)) {
                $conf = json_decode(file_get_contents($productsConfigPath), true);
                $showTRArrows = isset($conf['show_trending_arrows']) ? $conf['show_trending_arrows'] : true;
            }
            
            if (count($products) > 1 && $showTRArrows): 
            ?>
            <!-- Navigation Arrows -->
            <button class="absolute left-3 top-1/2 -translate-y-1/2 bg-white/90 shadow-xl border border-white rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-white transition z-10 custom-arrow backdrop-blur-sm trending-prev" aria-label="Previous trending products" id="trendingPrev">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
            </button>
            <button class="absolute right-3 top-1/2 -translate-y-1/2 bg-white/90 shadow-xl border border-white rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-white transition z-10 custom-arrow backdrop-blur-sm trending-next" aria-label="Next trending products" id="trendingNext">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

