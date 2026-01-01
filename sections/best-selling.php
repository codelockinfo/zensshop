<?php
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$product = new Product();
$products = $product->getBestSelling(12); // Get more products for slider
?>

<section>
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4">Best Selling</h2>
            <p class="text-gray-600 text-sm max-w-2xl mx-auto">Unmatched design—superior performance and customer satisfaction in one.</p>
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
                    ?>
                    <div class="min-w-[280px] md:min-w-[300px] my-2">
                        <div class="product-card bg-white rounded-lg overflow-hidden shadow-md transition-all duration-300 group relative">
                            <div class="relative overflow-hidden">
                                <a href="<?php echo url('product.php?slug=' . urlencode($item['slug'] ?? '')); ?>">
                                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                                </a>
                                
                                <!-- Discount Badge -->
                                <?php if ($discount > 0): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-<?php echo $discount; ?>%</span>
                                <?php endif; ?>
                                
                                <!-- Wishlist Icon (Always Visible) -->
                                <button class="absolute top-2 right-2 w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition z-20 wishlist-btn" 
                                        data-product-id="<?php echo $item['id']; ?>"
                                        title="Add to Wishlist">
                                    <i class="far fa-heart"></i>
                                    <span class="product-tooltip">Add to Wishlist</span>
                                </button>
                                
                                <!-- Hover Action Buttons -->
                                <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                                    <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                                       class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                                       data-product-id="<?php echo $item['id']; ?>"
                                       data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                        <i class="fas fa-eye"></i>
                                        <span class="product-tooltip">Quick View</span>
                                    </a>
                                    <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg compare-btn relative group" 
                                            data-product-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-layer-group"></i>
                                        <span class="product-tooltip">Compare</span>
                                    </button>
                                    <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group" 
                                            data-product-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="product-tooltip">Add to Cart</span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-800 mb-2 line-clamp-2">
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
                                <div class="flex items-center gap-2">
                                    <p class="text-md font-bold <?php echo $discount > 0 ? 'text-red-500' : 'text-primary'; ?>"><?php echo format_currency($price); ?></p>
                                    <?php if ($originalPrice): ?>
                                    <span class="text-gray-400 line-through text-sm block"><?php echo format_currency($originalPrice); ?></span>
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
            <!-- Navigation Arrows 1111 -->
            <button class="absolute left-3 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 best-selling-prev" id="bestSellingPrev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="absolute right-3 top-1/2 -translate-y-1/2 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition z-10 best-selling-next" id="bestSellingNext">
                <i class="fas fa-chevron-right"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
</section>
