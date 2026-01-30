<?php
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Wishlist.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
$product = new Product();
$wishlistObj = new Wishlist();

// Get Wishlist IDs for checking status
$wishlistItems = $wishlistObj->getWishlist();
$wishlistIds = array_column($wishlistItems, 'product_id');

// Check for manual selection from dedicated table
$products = $db->fetchAll(
    "SELECT p.*, h.heading, h.subheading
     FROM products p 
     JOIN section_best_selling_products h ON p.product_id = h.product_id 
     WHERE p.status != 'archived' AND h.store_id = ?
     ORDER BY h.sort_order ASC",
    [$storeId]
);

// Fetch dynamic headers if available from any row
$sectionHeading = 'Best Selling';
$sectionSubheading = 'Unmatched design—superior performance and customer satisfaction in one.';
if (!empty($products)) {
    $sectionHeading = !empty($products[0]['heading']) ? $products[0]['heading'] : $sectionHeading;
    $sectionSubheading = !empty($products[0]['subheading']) ? $products[0]['subheading'] : $sectionSubheading;
} else {
    // Fallback headers if table is empty but we want to check if headers exist anyway
    $headers = $db->fetchOne("SELECT heading, subheading FROM section_best_selling_products WHERE store_id = ? LIMIT 1", [$storeId]);
    if ($headers) {
        $sectionHeading = !empty($headers['heading']) ? $headers['heading'] : $sectionHeading;
        $sectionSubheading = !empty($headers['subheading']) ? $headers['subheading'] : $sectionSubheading;
    }
}

// Fallback if no specific products selected
if (empty($products)) {
    $products = $product->getBestSelling(12, $storeId);
}
?>

<section>
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <p class="text-gray-600 text-sm max-w-2xl mx-auto"><?php echo htmlspecialchars($sectionSubheading); ?></p>
        </div>
        
        <!-- Product Slider Container -->
        <div class="relative">
            <!-- Slider Wrapper -->
            <div class="best-selling-slider overflow-hidden">
                <div class="flex" id="bestSellingSlider" style="will-change: transform;">
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
                    <div class="min-w-[280px] md:min-w-[300px] my-2">
                        <div class="product-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300 group relative">
                            <div class="relative overflow-hidden">
                                <a href="<?php echo url('product?slug=' . urlencode($item['slug'] ?? '')); ?>">
                                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="w-full h-64 object-contain group-hover:scale-110 transition-transform duration-500">
                                </a>
                                
                                <!-- Discount Badge -->
                                <?php if ($discount > 0): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-<?php echo $discount; ?>%</span>
                                <?php endif; ?>
                                
                                <!-- Wishlist Icon (Always Visible) -->
                                <?php 
                                $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                                $inWishlist = in_array($currentId, $wishlistIds);
                                ?>
                                <button class="absolute top-2 right-2 w-10 h-10 rounded-full flex items-center justify-center <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white transition z-20 wishlist-btn" 
                                        data-product-id="<?php echo $currentId; ?>"
                                        title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                    <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                                </button>
                                
                                <!-- Hover Action Buttons -->
                                <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                                    <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                                       class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                                       data-product-id="<?php echo $item['product_id']; ?>"
                                       data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                        <i class="fas fa-eye"></i>
                                        <span class="product-tooltip">Quick View</span>
                                    </a>
                                    <!-- <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg compare-btn relative group" 
                                            data-product-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-layer-group"></i>
                                        <span class="product-tooltip">Compare</span>
                                    </button> -->
                                    <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group <?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                            data-product-id="<?php echo $item['product_id']; ?>"
                                            data-attributes='<?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>'
                                            <?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="product-tooltip"><?php echo ($item['stock_status'] === 'out_of_stock' || $item['stock_quantity'] <= 0) ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?></span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-sm md:text-base text-gray-800 md:max-w-[250px]  max-w-[250px] mb-2 overflow-hidden h-10 md:h-12 leading-tight" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="<?php echo htmlspecialchars($item['name']); ?>">
                                    <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition block">
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
                                <div class="flex items-center gap-2">
                                    <p class="text-md font-bold <?php echo $discount > 0 ? 'text-red-500' : 'text-primary'; ?>"><?php echo format_price($price, $item['currency'] ?? 'USD'); ?></p>
                                    <?php if ($originalPrice): ?>
                                    <span class="text-gray-400 line-through text-sm block"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($products)): ?>
                    <div class="min-w-full text-center py-12">
                        <p class="text-gray-500">No products available at the moment.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($products)): ?>
            <!-- Navigation Arrows -->
            <button class="absolute left-3 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 best-selling-prev" id="bestSellingPrev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="absolute right-3 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 best-selling-next" id="bestSellingNext">
                <i class="fas fa-chevron-right"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    </div>
</section>
<!-- <script>
    const bestSellerCartItem = document.getElementById('addToCartByProductIcon');
    bestSellerCartItem.addEventListener('click', function(btn){
        btn.preventDefault();
        const productId = this.getAttribute('data-product-id');
        addToCart(productId, 1);
    })

</script> -->