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
    header('Location: /oecom/');
    exit;
}

// Get product by slug
$productData = $product->getBySlug($slug);

if (!$productData || $productData['status'] !== 'active') {
    ob_end_clean(); // Clear any buffered output
    header('Location: /oecom/');
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
            <a href="/oecom/" class="hover:text-primary">Home</a> > 
            <?php if ($primaryCategory): ?>
            <a href="/oecom/shop.php?category=<?php echo urlencode($primaryCategory['slug']); ?>" class="hover:text-primary">
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
            
            <button class="bg-white border-2 border-gray-300 px-6 py-2 rounded-lg hover:border-primary transition mb-6">
                Write A Review
            </button>
            
            <!-- Reviews List -->
            <div class="space-y-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Most Recent</h3>
                    <select class="border rounded px-3 py-1">
                        <option>Most Recent</option>
                        <option>Oldest First</option>
                        <option>Highest Rating</option>
                        <option>Lowest Rating</option>
                    </select>
                </div>
                
                <!-- Sample Review -->
                <div class="border-b pb-6">
                    <div class="flex items-center mb-2">
                        <div class="flex items-center mr-4">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                            <i class="fas fa-star text-yellow-400 text-sm"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="font-semibold">john</span>
                        <span class="text-gray-500 text-sm ml-4"><?php echo date('m/d/Y'); ?></span>
                    </div>
                    <h4 class="font-semibold mb-2">Effortless Maxi Dress.</h4>
                    <p class="text-gray-700">This maxi dress is perfect for both lounging and going out. The flowy design is so flattering, and the fabric feels cool on the skin. Perfect for summer days!</p>
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
                <a href="/oecom/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="group">
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
                <a href="/oecom/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="group">
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
    const size = selectedSize || 'M';
    const color = selectedColor || 'Indigo';
    
    fetch('/oecom/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            product_id: productId, 
            quantity: quantity,
            size: size,
            color: color
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Open side cart
            const sideCart = document.getElementById('sideCart');
            const cartOverlay = document.getElementById('cartOverlay');
            if (sideCart && cartOverlay) {
                sideCart.classList.remove('translate-x-full');
                cartOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            // Update cart UI
            if (typeof updateCartUI === 'function') {
                updateCartUI();
            }
            if (typeof updateCartCount === 'function') {
                updateCartCount();
            }
            // Show success notification
            if (typeof showNotification === 'function') {
                showNotification('Product added to cart!', 'success');
            }
        } else {
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to add product to cart', 'error');
            } else {
                alert(data.message || 'Failed to add product to cart');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred while adding the product to cart', 'error');
        } else {
            alert('An error occurred while adding the product to cart');
        }
    });
}

function buyNow(productId) {
    // Add to cart and redirect to checkout
    fetch('/oecom/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/oecom/checkout.php';
        } else {
            alert(data.message || 'Failed to add product to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

