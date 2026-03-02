<?php
require_once __DIR__ . '/../classes/Product.php';
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
     JOIN section_best_selling_products h ON p.product_id = h.product_id 
     WHERE p.status = 'active' 
     AND (h.store_id = ? OR h.store_id IS NULL)
     AND p.store_id = ?
     ORDER BY h.sort_order ASC",
    [CURRENT_STORE_ID, CURRENT_STORE_ID]
);

// Fetch dynamic headers if available from any row
$sectionHeading = 'Best Selling';
$sectionSubheading = 'Unmatched design—superior performance and customer satisfaction in one.';

$productsConfigPath = __DIR__ . '/../admin/homepage_products_config.json';
if (file_exists($productsConfigPath)) {
    $conf = json_decode(file_get_contents($productsConfigPath), true);
    $sectionHeading = $conf['bs_heading'] ?? $sectionHeading;
    $sectionSubheading = $conf['bs_subheading'] ?? $sectionSubheading;
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
    $stylesJson = $settingsObj->get('best_selling_styles', '{}'); 
    $styles = json_decode($stylesJson, true);
    
    // Defaults (Global -> specific override if not empty)
    // Helper to get style with fallback
    function getStyle($key, $local, $global, $default) {
        return !empty($local[$key]) ? $local[$key] : (!empty($global[$key]) ? $global[$key] : $default);
    }
    
    // Define styles
    $bg_color = getStyle('bg_color', $styles, $globalStyles, '#ffffff');
    $heading_color = getStyle('heading_color', $styles, $globalStyles, '#1f2937');
    $subheading_color = getStyle('subheading_color', $styles, $globalStyles, '#4b5563');
    
    $card_bg_color = getStyle('card_bg_color', $styles, $globalStyles, '#ffffff');
    $card_title_color = getStyle('card_title_color', $styles, $globalStyles, '#1F2937');
    $price_color = getStyle('price_color', $styles, $globalStyles, '#1a3d32');
    $compare_price_color = getStyle('compare_price_color', $styles, $globalStyles, '#9ca3af');
    
    $badge_bg_color = getStyle('badge_bg_color', $styles, $globalStyles, '#ef4444');
    $badge_text_color = getStyle('badge_text_color', $styles, $globalStyles, '#ffffff');
    
    $arrow_bg_color = getStyle('arrow_bg_color', $styles, $globalStyles, '#ffffff');
    $arrow_icon_color = getStyle('arrow_icon_color', $styles, $globalStyles, '#1f2937');
    
    $btn_bg_color = getStyle('btn_bg_color', $styles, $globalStyles, '#ffffff');
    $btn_icon_color = getStyle('btn_icon_color', $styles, $globalStyles, '#000000');
    $btn_hover_bg_color = getStyle('btn_hover_bg_color', $styles, $globalStyles, '#000000');
    $btn_hover_icon_color = getStyle('btn_hover_icon_color', $styles, $globalStyles, '#ffffff');
    $btn_active_bg_color = getStyle('btn_active_bg_color', $styles, $globalStyles, '#000000');
    $btn_active_icon_color = getStyle('btn_active_icon_color', $styles, $globalStyles, '#ffffff');
    
    $tooltip_bg_color = getStyle('tooltip_bg_color', $styles, $globalStyles, '#000000');
    $tooltip_text_color = getStyle('tooltip_text_color', $styles, $globalStyles, '#ffffff');

    $sectionId = 'bs-section-' . rand(1000, 9999);
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
    #<?php echo $sectionId; ?> .swiper-button-prev,
    #<?php echo $sectionId; ?> .swiper-button-next {
        background-color: <?php echo $arrow_bg_color; ?> !important;
        color: <?php echo $arrow_icon_color; ?> !important;
    }
    #<?php echo $sectionId; ?> .swiper-button-prev:hover,
    #<?php echo $sectionId; ?> .swiper-button-next:hover {
        opacity: 0.8;
    }
</style>

<section id="<?php echo $sectionId; ?>" class="pt-8 md:pt-14">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4 section-heading"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <p class="text-lg max-w-2xl mx-auto section-subheading"><?php echo htmlspecialchars($sectionSubheading); ?></p>
        </div>
        
        <!-- Swiper Slider -->
        <?php 
        $productsConfigPath = __DIR__ . '/../admin/homepage_products_config.json';
        $showBSArrows = true;
        if (file_exists($productsConfigPath)) {
            $conf = json_decode(file_get_contents($productsConfigPath), true);
            $showBSArrows = isset($conf['show_best_selling_arrows']) ? $conf['show_best_selling_arrows'] : true;
        }
        ?>
        <div class="relative">
            <div class="swiper bestSellingSwiper" id="bestSellingSlider">
                <div class="swiper-wrapper">

                    <?php foreach ($products as $item): 
                        $mainImage = getProductImage($item);
                        $price = $item['sale_price'] ?? $item['price'];
                        $originalPrice = $item['sale_price'] ? $item['price'] : null;
                        $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
                        
                        // Get first variant for default attributes
                        $firstVariant = $db->fetchOne(
                            "SELECT variant_attributes FROM product_variants WHERE product_id = ? ORDER BY is_default DESC, id ASC LIMIT 1",
                            [$item['product_id']]
                        );
                        $defaultAttributes = $firstVariant ? json_decode($firstVariant['variant_attributes'], true) : [];
                        $attributesJson = json_encode($defaultAttributes);
                    ?>
                    <div class="swiper-slide h-auto !w-[280px] md:!w-[300px]">
                        <div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative flex flex-col h-full w-full max-w-sm">
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

                                 <?php
                                 $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                                 $inWishlist = in_array($currentId, $wishlistIds);
                                 $oos = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                                 ?>
                                 
                                 <!-- Out Of Stock Badge -->
                                 <?php if ($oos): ?>
                                 <span class="absolute top-10 left-2 bg-gray-800 text-white px-2 py-1 text-[10px] font-bold rounded-sm uppercase tracking-tighter z-10 opacity-90">OUT OF STOCK</span>
                                 <?php endif; ?>
                                 
                                 <!-- Action Icons Column -->
                                 <div class="absolute top-2 right-2 z-30 flex flex-col items-center gap-2">
                                     <button id="product-card-wishlist-btn" class="w-10 h-10 rounded-full flex items-center justify-center relative group transition wishlist-btn product-action-btn <?php echo $inWishlist ? 'wishlist-active text-white' : ''; ?>" 
                                             data-product-id="<?php echo $currentId; ?>"
                                             aria-label="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                                             title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                         <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
                                         <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                                     </button>
                                    
                                    <a id="product-card-quick-view-btn" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                                       class="product-action-btn w-10 h-10 rounded-full flex items-center justify-center transition shadow-lg quick-view-btn relative group opacity-100 md:opacity-0 md:group-hover:opacity-100" 
                                       aria-label="Quick view product"
                                       data-product-id="<?php echo $item['product_id']; ?>"
                                       data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                       data-product-price="<?php echo $price; ?>"
                                       data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span class="product-tooltip">Quick View</span>
                                    </a>

                                    <?php
                                    // Set up attributes for first variant
                                    $vData = $product->getVariants($currentId);
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
                                    $oos = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                                    ?>
                                    <button id="product-card-add-to-cart-btn" onclick='addToCart(<?php echo $currentId; ?>, 1, this, <?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>)' 
                                            class="productAddToCartBtn product-action-btn w-10 h-10 rounded-full flex items-center justify-center transition shadow-lg add-to-cart-hover-btn relative group opacity-100 md:opacity-0 md:group-hover:opacity-100 <?php echo $oos ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                            data-product-id="<?php echo $currentId; ?>"
                                            data-product-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                            data-product-price="<?php echo $price; ?>"
                                            data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>"
                                            <?php echo $oos ? 'disabled' : ''; ?>>
                                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                        <span class="product-tooltip"><?php echo $oos ? strtoupper(get_stock_status_text($item['stock_status'] ?? 'in_stock', $item['stock_quantity'] ?? 0)) : 'Add to Cart'; ?></span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 flex flex-col flex-1">
                                <h3 class="font-semibold text-gray-800 mb-2 h-10 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; color: <?php echo $card_title_color; ?> !important;" title="<?php echo htmlspecialchars($item['name']); ?>">
                                    <a class="product-card-view-link" href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" style="color: inherit;" class="hover:text-primary transition">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h3>
                                <div class="flex items-center mb-3">
                                    <div class="flex text-yellow-400">
                                        <?php 
                                        $ratingValue = floor($item['rating'] ?? 5);
                                        for ($i = 0; $i < 5; $i++): 
                                        ?>
                                        <i class="fas fa-star text-[10px] <?php echo $i < $ratingValue ? '' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 mb-4 mt-auto">
                                    <span class="product-price text-base font-bold" style="color: <?php echo $price_color; ?> !important;">
                                        <?php echo format_price($price, $item['currency'] ?? 'USD'); ?>
                                    </span>
                                    <?php if ($originalPrice): ?>
                                        <span class="compare-price font-bold line-through text-xs" style="color: <?php echo $compare_price_color; ?> !important;"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div><!-- /.swiper-wrapper -->

                <?php if (count($products) > 1 && $showBSArrows): ?>
                <button class="absolute left-2 md:-left-4 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:text-[#1a3d32] hover:bg-gray-50 transition z-30 best-selling-swiper-prev border border-gray-100" aria-label="Previous">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <button class="absolute right-2 md:-right-4 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:text-[#1a3d32] hover:bg-gray-50 transition z-30 best-selling-swiper-next border border-gray-100" aria-label="Next">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
            </div><!-- /.swiper -->
        </div>
    </div>
</section>
<?php endif; ?>