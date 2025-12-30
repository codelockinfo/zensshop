<?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Process redirects BEFORE any output
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$product = new Product();
$db = Database::getInstance();

// Get product slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    ob_end_clean(); // Clear any buffered output
    header('Location: <?php echo $baseUrl; ?>/');
    exit;
}

// Get product by slug
$productData = $product->getBySlug($slug);

if (!$productData || $productData['status'] !== 'active') {
    ob_end_clean(); // Clear any buffered output
    header('Location: <?php echo $baseUrl; ?>/');
    exit;
}

// Clear output buffer before including header
ob_end_clean();

// Now include header after all redirects are handled
$pageTitle = 'Product Details';
require_once __DIR__ . '/includes/header.php';

// Parse images
$images = json_decode($productData['images'] ?? '[]', true);
$mainImage = getProductImage($productData);
if (empty($images)) {
    $images = [$mainImage];
}

// Get product categories
$productCategories = [];
try {
    $productCategories = $db->fetchAll(
        "SELECT c.id, c.name, c.slug 
         FROM categories c 
         INNER JOIN product_categories pc ON c.id = pc.category_id 
         WHERE pc.product_id = ? AND c.status = 'active'",
        [$productData['id']]
    );
} catch (Exception $e) {
    // If product_categories table doesn't exist, try old category_id
    if (!empty($productData['category_id'])) {
        $oldCategory = $db->fetchOne(
            "SELECT id, name, slug FROM categories WHERE id = ? AND status = 'active'",
            [$productData['category_id']]
        );
        if ($oldCategory) {
            $productCategories = [$oldCategory];
        }
    }
}

// Get primary category for breadcrumbs
$primaryCategory = !empty($productCategories) ? $productCategories[0] : null;

// Calculate price and discount
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;
$originalPrice = !empty($productData['sale_price']) ? $productData['price'] : null;
$discount = $originalPrice && $originalPrice > 0 ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;

// Get related products (same category)
$relatedProducts = [];
if (!empty($productCategories)) {
    $categoryId = $productCategories[0]['id'] ?? null;
    if ($categoryId) {
        $relatedProducts = $product->getAll([
            'category_id' => $categoryId,
            'status' => 'active',
            'limit' => 5
        ]);
        // Remove current product from related
        $relatedProducts = array_filter($relatedProducts, function($p) use ($productData) {
            return isset($p['id']) && $p['id'] != $productData['id'];
        });
        $relatedProducts = array_slice($relatedProducts, 0, 4);
    }
}

// Get recently viewed (from cookie or session)
$recentlyViewed = [];
if (isset($_COOKIE['recently_viewed'])) {
    $recentIds = json_decode($_COOKIE['recently_viewed'], true);
    if (is_array($recentIds) && !empty($recentIds)) {
        $recentIds = array_filter($recentIds, function($id) use ($productData) {
            return $id != $productData['id'];
        });
        $recentIds = array_slice($recentIds, 0, 4);
        if (!empty($recentIds)) {
            $placeholders = implode(',', array_fill(0, count($recentIds), '?'));
            $recentlyViewed = $db->fetchAll(
                "SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'",
                $recentIds
            );
        }
    }
}

// Add current product to recently viewed
$recentIds = isset($_COOKIE['recently_viewed']) ? json_decode($_COOKIE['recently_viewed'], true) : [];
if (!is_array($recentIds)) {
    $recentIds = [];
}
array_unshift($recentIds, $productData['id']);
$recentIds = array_unique($recentIds);
$recentIds = array_slice($recentIds, 0, 10);
if (!headers_sent()) {
    setcookie('recently_viewed', json_encode($recentIds), time() + (30 * 24 * 60 * 60), '/');
}
$_COOKIE['recently_viewed'] = json_encode($recentIds);
?>

<section class="py-8 md:py-12 bg-white">
    <div class="container mx-auto px-4">
        <!-- Breadcrumbs -->
        <nav class="text-sm text-gray-600 mb-6">
            <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a> > 
            <?php if ($primaryCategory): ?>
            <a href="<?php echo $baseUrl; ?>/shop.php?category=<?php echo urlencode($primaryCategory['slug']); ?>" class="hover:text-primary">
                <?php echo htmlspecialchars($primaryCategory['name']); ?>
            </a> > 
            <?php endif; ?>
            <span class="text-gray-900"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></span>
        </nav>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
            <!-- Product Images -->
            <div>
                <!-- Main Image -->
                <div class="mb-4">
                    <img id="mainProductImage" 
                         src="<?php echo htmlspecialchars($mainImage); ?>" 
                         alt="<?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?>" 
                         class="w-full h-auto rounded-lg">
                </div>
                
                <!-- Thumbnail Images -->
                <?php if (count($images) > 1): ?>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach ($images as $index => $image): 
                        $imageUrl = getImageUrl($image);
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="Thumbnail <?php echo $index + 1; ?>"
                         class="w-full h-20 object-cover rounded cursor-pointer border-2 transition hover:border-primary <?php echo $index === 0 ? 'border-primary' : 'border-transparent'; ?>"
                         onclick="changeMainImage('<?php echo htmlspecialchars($imageUrl); ?>', this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Information -->
            <div>
                <h1 class="text-3xl md:text-4xl font-heading font-bold mb-4"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></h1>
                
                <!-- Rating and Reviews -->
                <div class="flex items-center space-x-4 mb-4">
                    <div class="flex items-center">
                        <?php 
                        $rating = floatval($productData['rating'] ?? 5);
                        $reviewCount = intval($productData['review_count'] ?? 1);
                        $fullStars = floor($rating);
                        $hasHalfStar = ($rating - $fullStars) >= 0.5;
                        for ($i = 0; $i < 5; $i++): 
                        ?>
                        <i class="fas fa-star <?php echo $i < $fullStars ? 'text-yellow-400' : ($i === $fullStars && $hasHalfStar ? 'text-yellow-400' : 'text-gray-300'); ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-gray-600"><?php echo $reviewCount; ?> review<?php echo $reviewCount != 1 ? 's' : ''; ?></span>
                    <span class="text-gray-600">10 sold in last 18 hours</span>
                </div>
                
                <!-- Price -->
                <div class="mb-6">
                    <?php if ($originalPrice): ?>
                    <span class="text-2xl text-gray-400 line-through mr-2">$<?php echo number_format($originalPrice, 2); ?></span>
                    <?php endif; ?>
                    <span class="text-4xl font-bold text-gray-900">$<?php echo number_format($price, 2); ?></span>
                </div>
                
                <!-- Description -->
                <div class="mb-6">
                    <p class="text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($productData['description'] ?? $productData['short_description'] ?? 'No description available.')); ?>
                    </p>
                </div>
                
                <!-- Key Information -->
                <div class="space-y-3 mb-6 text-sm">
                    <div class="flex items-center text-gray-700">
                        <i class="fas fa-truck mr-2 text-primary"></i>
                        <span>Estimate delivery times: 3-5 days International</span>
                    </div>
                    <div class="flex items-center text-gray-700">
                        <i class="fas fa-tag mr-2 text-primary"></i>
                        <span>Use code <strong>'WELCOMES'</strong> for discount 15% on your first order.</span>
                    </div>
                    <div class="flex items-center text-gray-700">
                        <i class="fas fa-shipping-fast mr-2 text-primary"></i>
                        <span>Free shipping & returns: On all orders over $150.</span>
                    </div>
                    <div class="flex items-center text-gray-700">
                        <i class="fas fa-eye mr-2 text-primary"></i>
                        <span><?php echo rand(10, 20); ?> people are viewing this right now</span>
                    </div>
                </div>
                
                <!-- Size Selector -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <label class="font-semibold text-gray-900">Size:</label>
                        <a href="#" class="text-sm text-primary hover:underline">Size guide</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $sizes = ['S', 'M', 'L', 'XL'];
                        $selectedSize = $_GET['size'] ?? 'M';
                        foreach ($sizes as $size): 
                        ?>
                        <button type="button" 
                                onclick="selectSize('<?php echo $size; ?>', this)"
                                class="size-btn px-6 py-2 border-2 rounded transition <?php echo $selectedSize === $size ? 'border-primary bg-primary text-white' : 'border-gray-300 hover:border-primary'; ?>">
                            <?php echo $size; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Color Selector -->
                <div class="mb-6">
                    <label class="font-semibold text-gray-900 mb-3 block">Color: <span id="selectedColor">Indigo</span></label>
                    <div class="flex gap-3">
                        <button type="button" 
                                onclick="selectColor('Indigo', this, '#4F46E5')"
                                class="color-btn w-12 h-12 rounded-full border-2 border-primary bg-blue-600 focus:outline-none focus:ring-2 focus:ring-primary">
                        </button>
                        <button type="button" 
                                onclick="selectColor('Black', this, '#000000')"
                                class="color-btn w-12 h-12 rounded-full border-2 border-gray-300 bg-black hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary">
                        </button>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <button onclick="addToCartFromDetail(<?php echo $productData['id']; ?>)" 
                            class="flex-1 bg-black text-white py-4 px-6 rounded-lg hover:bg-gray-800 transition font-semibold flex items-center justify-center">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        Add To Cart
                    </button>
                    <button onclick="buyNow(<?php echo $productData['id']; ?>)" 
                            class="flex-1 bg-pink-500 text-white py-4 px-6 rounded-lg hover:bg-pink-600 transition font-semibold">
                        Buy It Now
                    </button>
                </div>
                
                <!-- Additional Links -->
                <div class="flex flex-wrap gap-4 text-sm mb-6">
                    <a href="#" class="text-gray-600 hover:text-primary transition">Compare colors</a>
                    <a href="#" class="text-gray-600 hover:text-primary transition">Ask a question</a>
                    <a href="#" class="text-gray-600 hover:text-primary transition">Share</a>
                </div>
                
                <!-- Pickup Information -->
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <p class="text-sm text-gray-700">
                        <i class="fas fa-store mr-2 text-primary"></i>
                        Pickup available at Shop location. Usually ready in 24 hours
                    </p>
                    <a href="#" class="text-sm text-primary hover:underline mt-1 inline-block">View store information</a>
                </div>
                
                <!-- Product Details -->
                <div class="border-t pt-6 space-y-2 text-sm">
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Sku:</span>
                        <span class="text-gray-600"><?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Available:</span>
                        <span class="text-gray-600 capitalize"><?php echo str_replace('_', ' ', $productData['stock_status'] ?? 'in_stock'); ?></span>
                    </div>
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Collections:</span>
                        <span class="text-gray-600">
                            <?php 
                            if (!empty($productCategories)) {
                                echo htmlspecialchars(implode(', ', array_column($productCategories, 'name')));
                            } else {
                                echo 'Uncategorized';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Guarantee -->
                <div class="mt-6 pt-6 border-t">
                    <p class="text-sm text-gray-600 text-center">Guarantee Safe Checkout</p>
                    <div class="flex justify-center space-x-4 mt-4">
                        <img src="https://via.placeholder.com/60x40/1a5d3a/ffffff?text=VISA" alt="Visa" class="h-8">
                        <img src="https://via.placeholder.com/60x40/1a5d3a/ffffff?text=MC" alt="Mastercard" class="h-8">
                        <img src="https://via.placeholder.com/60x40/1a5d3a/ffffff?text=AMEX" alt="American Express" class="h-8">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Collapsible Sections -->
        <div class="max-w-4xl mx-auto mb-16">
            <div class="space-y-4">
                <!-- Description -->
                <div class="border-b">
                    <button onclick="toggleSection('description')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Description</span>
                        <i class="fas fa-plus text-gray-400" id="description-icon"></i>
                    </button>
                    <div id="description-content" class="hidden pb-4 text-gray-700">
                        <?php echo nl2br(htmlspecialchars($productData['description'] ?? 'No description available.')); ?>
                    </div>
                </div>
                
                <!-- Shipping and Returns -->
                <div class="border-b">
                    <button onclick="toggleSection('shipping')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Shipping and Returns</span>
                        <i class="fas fa-plus text-gray-400" id="shipping-icon"></i>
                    </button>
                    <div id="shipping-content" class="hidden pb-4 text-gray-700">
                        <p>We offer free shipping on all orders over $150. Standard shipping takes 3-5 business days. International shipping may take 7-14 business days.</p>
                        <p class="mt-2">Returns are accepted within 30 days of purchase. Items must be unworn and in original packaging.</p>
                    </div>
                </div>
                
                <!-- Return Policies -->
                <div class="border-b">
                    <button onclick="toggleSection('returns')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Return Policies</span>
                        <i class="fas fa-plus text-gray-400" id="returns-icon"></i>
                    </button>
                    <div id="returns-content" class="hidden pb-4 text-gray-700">
                        <p>We accept returns within 30 days of purchase. Items must be in original condition with tags attached.</p>
                        <p class="mt-2">To initiate a return, please contact our customer service team or visit your account page.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Reviews -->
        <div class="max-w-4xl mx-auto mb-16">
            <h2 class="text-2xl font-heading font-bold mb-6">Customer Reviews</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Overall Rating -->
                <div class="text-center">
                    <div class="text-5xl font-bold mb-2"><?php echo number_format($rating, 2); ?></div>
                    <div class="flex justify-center mb-2">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <i class="fas fa-star text-yellow-400"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-gray-600">Based on <?php echo $reviewCount; ?> review<?php echo $reviewCount != 1 ? 's' : ''; ?></p>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="md:col-span-2">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                    <div class="flex items-center mb-2">
                        <span class="text-sm text-gray-600 w-12"><?php echo $star; ?> star</span>
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mx-2">
                            <div class="bg-yellow-400 h-2 rounded-full" style="width: <?php echo $star === 5 ? 100 : 0; ?>%"></div>
                        </div>
                        <span class="text-sm text-gray-600 w-8"><?php echo $star === 5 ? $reviewCount : 0; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <button onclick="openReviewModal()" class="bg-white border-2 border-gray-300 px-6 py-2 rounded-lg hover:border-primary transition mb-6">
                Write A Review
            </button>
            
            <!-- Reviews List -->
            <div class="space-y-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Most Recent</h3>
                    <select class="border rounded px-3 py-1" id="reviewSort" onchange="loadReviews()">
                        <option value="recent">Most Recent</option>
                        <option value="oldest">Oldest First</option>
                        <option value="highest">Highest Rating</option>
                        <option value="lowest">Lowest Rating</option>
                    </select>
                </div>
                
                <!-- Reviews List Container -->
                <div id="reviewsList">
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p>Loading reviews...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- People Also Bought -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="mb-16">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-heading font-bold mb-2">People Also Bought</h2>
                <p class="text-gray-600">Here's some of our most similar products people are buying. Click to discover trending style.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($relatedProducts as $item): 
                    $itemImage = getProductImage($item);
                    $itemPrice = $item['sale_price'] ?? $item['price'] ?? 0;
                    $itemOriginalPrice = !empty($item['sale_price']) ? $item['price'] : null;
                    $itemDiscount = $itemOriginalPrice && $itemOriginalPrice > 0 ? round((($itemOriginalPrice - $itemPrice) / $itemOriginalPrice) * 100) : 0;
                ?>
                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="group">
                    <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative">
                        <div class="relative overflow-hidden">
                            <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                 class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                            <?php if ($itemDiscount > 0): ?>
                            <span class="absolute top-3 right-3 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                -<?php echo $itemDiscount; ?>%
                            </span>
                            <?php endif; ?>
                            <button class="absolute top-3 left-3 bg-white rounded-full p-2 hover:bg-red-500 hover:text-white transition opacity-0 group-hover:opacity-100">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold mb-2"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></h3>
                            <div class="flex items-center mb-2">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="flex items-center justify-between">
                                <?php if ($itemOriginalPrice): ?>
                                <span class="text-gray-400 line-through text-sm mr-2">$<?php echo number_format($itemOriginalPrice, 2); ?></span>
                                <?php endif; ?>
                                <span class="font-bold text-primary">$<?php echo number_format($itemPrice, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recently Viewed -->
        <?php if (!empty($recentlyViewed)): ?>
        <div class="mb-16">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-heading font-bold mb-2">Recently Viewed</h2>
                <p class="text-gray-600">Explore your recently viewed items, blending quality and style for a refined living experience.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($recentlyViewed as $item): 
                    $itemImage = getProductImage($item);
                    $itemPrice = $item['sale_price'] ?? $item['price'] ?? 0;
                ?>
                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="group">
                    <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative">
                        <div class="relative overflow-hidden">
                            <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                 class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                            <?php if ($item['id'] == $productData['id']): ?>
                            <span class="absolute top-3 right-3 bg-primary text-white px-2 py-1 rounded text-xs">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold mb-2"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></h3>
                            <div class="flex items-center mb-2">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="font-bold text-primary">$<?php echo number_format($itemPrice, 2); ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
let selectedSize = '<?php echo $selectedSize; ?>';
let selectedColor = 'Indigo';

function changeMainImage(imageUrl, thumbnail) {
    document.getElementById('mainProductImage').src = imageUrl;
    // Update thumbnail borders
    document.querySelectorAll('.border-primary').forEach(el => {
        if (el.classList.contains('border-primary') && el !== thumbnail) {
            el.classList.remove('border-primary');
            el.classList.add('border-transparent');
        }
    });
    thumbnail.classList.remove('border-transparent');
    thumbnail.classList.add('border-primary');
}

function selectSize(size, button) {
    selectedSize = size;
    document.querySelectorAll('.size-btn').forEach(btn => {
        btn.classList.remove('border-primary', 'bg-primary', 'text-white');
        btn.classList.add('border-gray-300');
    });
    button.classList.remove('border-gray-300');
    button.classList.add('border-primary', 'bg-primary', 'text-white');
}

function selectColor(color, button, colorCode) {
    selectedColor = color;
    document.getElementById('selectedColor').textContent = color;
    document.querySelectorAll('.color-btn').forEach(btn => {
        btn.classList.remove('border-primary');
        btn.classList.add('border-gray-300');
    });
    button.classList.remove('border-gray-300');
    button.classList.add('border-primary');
}

function toggleSection(sectionId) {
    const content = document.getElementById(sectionId + '-content');
    const icon = document.getElementById(sectionId + '-icon');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-minus');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('fa-minus');
        icon.classList.add('fa-plus');
    }
}

function addToCartFromDetail(productId) {
    const quantity = 1;
    
    // Use global addToCart function if available
    if (typeof addToCart === 'function') {
        addToCart(productId, quantity);
    } else {
        // Fallback to direct API call
        fetch('<?php echo $baseUrl; ?>/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                product_id: productId, 
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh cart data
                if (typeof refreshCart === 'function') {
                    refreshCart();
                } else if (typeof loadCart === 'function') {
                    loadCart();
                }
                
                // Open side cart
                const sideCart = document.getElementById('sideCart');
                const cartOverlay = document.getElementById('cartOverlay');
                if (sideCart && cartOverlay) {
                    sideCart.classList.remove('translate-x-full');
                    cartOverlay.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
                
                // Show success notification
                if (typeof showNotificationModal === 'function') {
                    showNotificationModal('Product added to cart!', 'success');
                } else if (typeof showNotification === 'function') {
                    showNotification('Product added to cart!', 'success');
                }
            } else {
                if (typeof showNotificationModal === 'function') {
                    showNotificationModal(data.message || 'Failed to add product to cart', 'error');
                } else if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Failed to add product to cart', 'error');
                } else {
                    alert(data.message || 'Failed to add product to cart');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('An error occurred while adding the product to cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification('An error occurred while adding the product to cart', 'error');
            } else {
                alert('An error occurred while adding the product to cart');
            }
        });
    }
}

function buyNow(productId) {
    // Add to cart and redirect to checkout
    fetch('<?php echo $baseUrl; ?>/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo $baseUrl; ?>/checkout.php';
        } else {
            alert(data.message || 'Failed to add product to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

// Review Modal Functions
let selectedRating = 0;

function setRating(rating) {
    selectedRating = rating;
    document.getElementById('reviewRating').value = rating;
    
    // Update star display
    const stars = document.querySelectorAll('.star-rating-btn');
    stars.forEach((star, index) => {
        const starIcon = star.querySelector('i');
        if (index < rating) {
            starIcon.classList.remove('far', 'text-gray-300');
            starIcon.classList.add('fas', 'text-yellow-400');
        } else {
            starIcon.classList.remove('fas', 'text-yellow-400');
            starIcon.classList.add('far', 'text-gray-300');
        }
    });
    
    // Update rating text
    const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    document.getElementById('ratingText').textContent = ratingTexts[rating] || 'Click to rate';
}

function openReviewModal() {
    document.getElementById('reviewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.body.style.overflow = '';
    // Reset form
    document.getElementById('reviewForm').reset();
    selectedRating = 0;
    document.getElementById('reviewRating').value = '';
    // Reset stars
    document.querySelectorAll('.star-rating-btn i').forEach(icon => {
        icon.classList.remove('fas', 'text-yellow-400');
        icon.classList.add('far', 'text-gray-300');
    });
    document.getElementById('ratingText').textContent = 'Click to rate';
}

function submitReview(event) {
    event.preventDefault();
    
    if (selectedRating === 0) {
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('Please select a rating before submitting your review.', 'info', 'Rating Required');
        } else {
            alert('Please select a rating');
        }
        return;
    }
    
    const formData = {
        product_id: document.getElementById('reviewProductId').value,
        user_name: document.getElementById('reviewName').value,
        user_email: document.getElementById('reviewEmail').value,
        rating: selectedRating,
        title: document.getElementById('reviewTitle').value,
        comment: document.getElementById('reviewComment').value
    };
    
    // Disable submit button
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    fetch('<?php echo $baseUrl; ?>/api/reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeReviewModal();
            // Show success notification
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Thank you for your review! It has been submitted successfully.', 'success', 'Review Submitted');
            } else {
                alert('Thank you for your review! It has been submitted successfully.');
            }
            // Reload reviews
            loadReviews();
            // Reload page to update rating after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to submit review. Please try again.', 'error', 'Error');
            } else {
                alert(data.message || 'Failed to submit review. Please try again.');
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Review';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('An error occurred while submitting your review. Please try again.', 'error', 'Error');
        } else {
            alert('An error occurred while submitting your review. Please try again.');
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Review';
    });
}

function loadReviews() {
    const productId = <?php echo $productData['id']; ?>;
    const sortBy = document.getElementById('reviewSort')?.value || 'recent';
    
    let sortOrder = 'ORDER BY created_at DESC';
    if (sortBy === 'oldest') {
        sortOrder = 'ORDER BY created_at ASC';
    } else if (sortBy === 'highest') {
        sortOrder = 'ORDER BY rating DESC, created_at DESC';
    } else if (sortBy === 'lowest') {
        sortOrder = 'ORDER BY rating ASC, created_at DESC';
    }
    
    fetch(`<?php echo $baseUrl; ?>/api/reviews.php?product_id=${productId}&sort=${sortBy}`)
        .then(response => response.json())
        .then(data => {
            const reviewsList = document.getElementById('reviewsList');
            
            if (data.success && data.reviews && data.reviews.length > 0) {
                reviewsList.innerHTML = data.reviews.map(review => {
                    const date = new Date(review.created_at);
                    const formattedDate = date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
                    
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        if (i <= review.rating) {
                            stars += '<i class="fas fa-star text-yellow-400 text-sm"></i>';
                        } else {
                            stars += '<i class="fas fa-star text-gray-300 text-sm"></i>';
                        }
                    }
                    
                    return `
                        <div class="border-b pb-6 mb-6">
                            <div class="flex items-center mb-2">
                                <div class="flex items-center mr-4">
                                    ${stars}
                                </div>
                                <span class="font-semibold">${escapeHtml(review.user_name)}</span>
                                <span class="text-gray-500 text-sm ml-4">${formattedDate}</span>
                            </div>
                            ${review.title ? `<h4 class="font-semibold mb-2">${escapeHtml(review.title)}</h4>` : ''}
                            <p class="text-gray-700">${escapeHtml(review.comment)}</p>
                        </div>
                    `;
                }).join('');
            } else {
                reviewsList.innerHTML = '<div class="text-center text-gray-500 py-8"><p>No reviews yet. Be the first to review this product!</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading reviews:', error);
            document.getElementById('reviewsList').innerHTML = '<div class="text-center text-gray-500 py-8"><p>Unable to load reviews.</p></div>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load reviews on page load
document.addEventListener('DOMContentLoaded', function() {
    loadReviews();
    
    // Close modal on overlay click
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        reviewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });
    }
});
</script>

<!-- Review Modal -->
<div id="reviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-heading font-bold">Write A Review</h2>
                <button onclick="closeReviewModal()" class="text-gray-500 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="reviewForm" onsubmit="submitReview(event)">
                <input type="hidden" id="reviewProductId" value="<?php echo $productData['id']; ?>">
                
                <!-- Rating -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">Your Rating *</label>
                    <div class="flex items-center space-x-2" id="starRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" 
                                onclick="setRating(<?php echo $i; ?>)" 
                                class="star-rating-btn text-3xl text-gray-300 hover:text-yellow-400 transition"
                                data-rating="<?php echo $i; ?>">
                            <i class="far fa-star"></i>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="reviewRating" name="rating" required>
                    <p class="text-sm text-gray-500 mt-2" id="ratingText">Click to rate</p>
                </div>
                
                <!-- Name -->
                <div class="mb-4">
                    <label for="reviewName" class="block text-gray-700 font-semibold mb-2">Your Name *</label>
                    <input type="text" 
                           id="reviewName" 
                           name="user_name" 
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="reviewEmail" class="block text-gray-700 font-semibold mb-2">Your Email *</label>
                    <input type="email" 
                           id="reviewEmail" 
                           name="user_email" 
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <!-- Review Title -->
                <div class="mb-4">
                    <label for="reviewTitle" class="block text-gray-700 font-semibold mb-2">Review Title</label>
                    <input type="text" 
                           id="reviewTitle" 
                           name="title" 
                           placeholder="Summarize your review"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <!-- Review Comment -->
                <div class="mb-6">
                    <label for="reviewComment" class="block text-gray-700 font-semibold mb-2">Your Review *</label>
                    <textarea id="reviewComment" 
                              name="comment" 
                              rows="5" 
                              required
                              placeholder="Share your experience with this product..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-center justify-end space-x-4">
                    <button type="button" 
                            onclick="closeReviewModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

