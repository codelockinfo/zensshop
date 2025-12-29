<?php
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

$product = new Product();
$products = $product->getTrending(6);
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-heading font-bold mb-4">Trending Jewelry</h2>
            <p class="text-gray-600 text-lg max-w-2xl mx-auto">Unmatched design—superior performance and customer satisfaction in one.</p>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
            <?php foreach ($products as $item): 
                $mainImage = getProductImage($item);
                $price = $item['sale_price'] ?? $item['price'];
                $originalPrice = $item['sale_price'] ? $item['price'] : null;
                $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
                $hasTimer = ($discount > 0 && $item['id'] % 2 == 0); // Show timer on some discounted items
            ?>
            <div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">
                <div class="relative overflow-hidden">
                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                    
                    <!-- Discount Badge -->
                    <?php if ($discount > 0): ?>
                    <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                    
                    <!-- Wishlist Icon (Always Visible) -->
                    <button class="absolute top-2 right-2 w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition z-20 wishlist-btn" 
                            data-product-id="<?php echo $item['id']; ?>"
                            title="Add to Wishlist">
                        <i class="fas fa-heart"></i>
                    </button>
                    
                    <!-- Timer (for some discounted items) -->
                    <?php if ($hasTimer): ?>
                    <div class="absolute bottom-2 left-2 right-2 bg-red-500 text-white px-2 py-1 text-xs font-semibold rounded text-center countdown-timer" 
                         data-product-id="<?php echo $item['id']; ?>">
                        <span class="countdown-days">00</span> d : <span class="countdown-hours">00</span> h : <span class="countdown-minutes">00</span> m : <span class="countdown-seconds">00</span> s
                    </div>
                    <?php endif; ?>
                    
                    <!-- Hover Action Buttons -->
                    <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                        <button class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition shadow-lg quick-view-btn" 
                                data-product-id="<?php echo $item['id']; ?>"
                                title="Quick View">
                            <i class="fas fa-eye"></i>
                        </button>
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
                    <h3 class="font-semibold text-gray-800 mb-2 line-clamp-2 min-h-[48px]"><?php echo htmlspecialchars($item['name']); ?></h3>
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
                        <div>
                            <?php if ($originalPrice): ?>
                            <span class="text-gray-400 line-through text-sm block">$<?php echo number_format($originalPrice, 2); ?></span>
                            <?php endif; ?>
                            <p class="text-xl font-bold <?php echo $discount > 0 ? 'text-red-500' : 'text-primary'; ?>">$<?php echo number_format($price, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

