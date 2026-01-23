    <?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Process redirects BEFORE any output
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$product = new Product();
$db = Database::getInstance();

// Get base URL for redirects
$baseUrl = getBaseUrl();

// Get product slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . $baseUrl . '/');
    exit;
}

// Get product by slug
$productData = $product->getBySlug($slug);

if (!$productData || $productData['status'] !== 'active') {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . $baseUrl . '/');
    exit;
}

// Clear output buffer before including header
ob_end_clean();

// Now include header after all redirects are handled
$pageTitle = 'Product Details';
require_once __DIR__ . '/includes/header.php';

// Parse images
$images = json_decode($productData['images'] ?? '[]', true);
$mainImage = !empty($images[0]) ? getImageUrl($images[0]) : getProductImage($productData);
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

// Get product variants
$variantsData = $product->getVariants($productData['id']);
$productOptions = $variantsData['options'] ?? [];
$productVariants = $variantsData['variants'] ?? [];
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
                         class="w-full h-auto rounded-lg"
                         onerror="this.src='https://via.placeholder.com/600x600?text=Product+Image'">
                </div>
                
                <!-- Thumbnail Images (Extended with Variants) -->
                <?php 
                $galleryItems = [];
                $seenUrls = [];
                
                // Add main images (the Ring first)
                foreach ($images as $img) {
                    $url = getImageUrl($img);
                    if (!in_array($url, $seenUrls)) {
                        $galleryItems[] = ['url' => $url, 'variant' => null];
                        $seenUrls[] = $url;
                    }
                }
                
                // Add variant images
                foreach ($productVariants as $v) {
                    if (!empty($v['image'])) {
                        $url = getImageUrl($v['image']);
                        if (!in_array($url, $seenUrls)) {
                            $galleryItems[] = ['url' => $url, 'variant' => $v['variant_attributes']];
                            $seenUrls[] = $url;
                        }
                    }
                }
                ?>
                
                <?php if (count($galleryItems) > 1): ?>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach ($galleryItems as $index => $item): ?>
                    <img src="<?php echo htmlspecialchars($item['url']); ?>" 
                         alt="Thumbnail <?php echo $index + 1; ?>"
                         class="thumbnail-img w-full h-20 object-cover rounded cursor-pointer border-2 transition hover:border-primary <?php echo $index === 0 ? 'border-primary' : 'border-transparent'; ?>"
                         onclick="changeMainImage('<?php echo htmlspecialchars($item['url']); ?>', this, <?php echo $item['variant'] ? htmlspecialchars(json_encode($item['variant'])) : 'null'; ?>)"
                         onerror="this.src='https://via.placeholder.com/150x150?text=No+Image'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Information -->
            <div>
                <h1 class="text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></h1>
                
                <!-- Rating and Reviews -->
                <div class="flex items-center space-x-4 mb-4 text-sm">
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
                    <span id="original-price" class="text-2xl text-gray-400 line-through mr-2 <?php echo !$originalPrice ? 'hidden' : ''; ?>"><?php echo format_price($originalPrice ?: 0, $productData['currency'] ?? 'USD'); ?></span>
                    <span id="product-price" class="text-2xl font-bold text-gray-900"><?php echo format_price($price, $productData['currency'] ?? 'USD'); ?></span>
                </div>
                
                <!-- Description -->
                <div class="mb-6">
                    <p class="text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($productData['description'] ?? $productData['short_description'] ?? 'No description available.')); ?>
                    </p>
                </div>
                
                <!-- Key Information (Highlights) -->
                <div class="space-y-3 mb-6 text-sm">
                    <?php 
                    $highlights = json_decode($productData['highlights'] ?? '[]', true);
                    if (!empty($highlights)):
                        foreach ($highlights as $h): ?>
                    <div class="flex items-center text-gray-700">
                        <i class="<?php echo htmlspecialchars($h['icon'] ?: 'fas fa-check'); ?> mr-2 text-primary"></i>
                        <span><?php echo $h['text']; ?></span>
                    </div>
                    <?php endforeach; 
                    else: ?>
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
                        <span>Free shipping & returns: On all orders over <?php echo format_price(150, $productData['currency'] ?? 'USD'); ?>.</span>
                    </div>
                    <div class="flex items-center text-gray-700">
                        <i class="fas fa-eye mr-2 text-primary"></i>
                        <span><?php echo rand(10, 20); ?> people are viewing this right now</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Dynamic Variant Selectors -->
                <?php if (!empty($productOptions)): ?>
                    <?php foreach ($productOptions as $option): 
                        $optionName = $option['option_name'];
                        $optionValues = $option['option_values'];
                    ?>
                    <div class="mb-6 variant-option-group" data-option-name="<?php echo htmlspecialchars($optionName); ?>">
                        <div class="flex items-center justify-between mb-3">
                            <label class="font-semibold text-gray-900"><?php echo htmlspecialchars($optionName); ?>: <span class="selected-value text-primary font-normal"></span></label>
                            <?php if (strtolower($optionName) === 'size'): ?>
                                <a href="#" class="text-sm text-primary hover:underline">Size guide</a>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($optionValues as $value): ?>
                                <button type="button" 
                                        onclick="selectVariantOption('<?php echo htmlspecialchars($optionName); ?>', '<?php echo htmlspecialchars($value); ?>', this)"
                                        class="variant-btn px-4 py-2 border-2 rounded transition border-gray-300 hover:border-primary">
                                    <?php echo htmlspecialchars($value); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback or simple size selector if no variants in DB -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="font-semibold text-gray-900">Standard Size:</label>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (['Standard'] as $size): ?>
                            <button type="button" 
                                    class="px-6 py-2 border-2 rounded border-primary bg-primary text-white">
                                <?php echo $size; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Variant Images Section -->
                <?php if (!empty($productVariants)): ?>
                <!-- <div class="mb-6">
                    <label class="font-semibold text-gray-900 mb-3 block">Available Styles:</label>
                    <div class="flex flex-wrap gap-3">
                        <?php 
                        $seenImages = [];
                        foreach ($productVariants as $v): 
                            $vImg = !empty($v['image']) ? getImageUrl($v['image']) : $mainImage;
                            if (in_array($vImg, $seenImages)) continue;
                            $seenImages[] = $vImg;
                        ?>
                            <button type="button" 
                                    onclick="selectVariantByImage(<?php echo htmlspecialchars(json_encode($v['variant_attributes'])); ?>, '<?php echo htmlspecialchars($vImg); ?>')"
                                    class="v-style-btn w-16 h-16 rounded border-2 border-gray-200 hover:border-primary overflow-hidden transition">
                                <img src="<?php echo htmlspecialchars($vImg); ?>" alt="Style" class="w-full h-full object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div> -->
                <?php endif; ?>
                
                <!-- Quantity Selector -->
                <div class="flex items-center gap-4 mb-6">
                    <label class="font-semibold text-gray-900">Quantity:</label>
                    <div class="flex items-center border border-gray-300 rounded-md w-32 h-10 overflow-hidden">
                        <button onclick="updateProductQuantity(-1)" class="w-10 h-full flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition leading-none">-</button>
                        <input type="number" id="productQuantity" value="1" min="1" class="flex-1 pl-4 w-full text-center border-none focus:ring-0 p-0 text-gray-900 font-semibold appearance-none bg-transparent h-full" style="text-align: center; border: 0 !important; outline: none !important; box-shadow: none !important;" readonly>
                        <button onclick="updateProductQuantity(1)" class="w-10 h-full flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition leading-none">+</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <button onclick="addToCartFromDetail(<?php echo $productData['product_id']; ?>, this)" 
                            class="flex-1 bg-black text-white py-4 px-6 hover:bg-gray-800 transition font-semibold flex items-center justify-center add-to-cart-btn"
                            data-loading-text="Adding...">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        Add To Cart
                    </button>
                    <button onclick="buyNow(<?php echo $productData['product_id']; ?>, this)" 
                            class="flex-1 bg-red-700 text-white py-4 px-6 hover:bg-red-600 transition font-semibold buy-now-btn"
                            data-loading-text="Processing...">
                        Buy It Now
                    </button>
                </div>
                
                <!-- Additional Links -->
                <div class="flex flex-wrap gap-4 text-sm mb-6">
                    <button onclick="toggleProductWishlist(<?php echo $productData['product_id'] ?? $productData['id']; ?>, this)" class="text-gray-600 hover:text-primary transition flex items-center wishlist-btn" data-product-id="<?php echo $productData['product_id'] ?? $productData['id']; ?>">
                        <i class="far fa-heart mr-1"></i> Add to Wishlist
                    </button>
                    <!-- <a href="#" class="text-gray-600 hover:text-primary transition"><i class="fas fa-exchange-alt mr-1"></i>Compare colors</a> -->
                    <a href="#" class="text-gray-600 hover:text-primary transition"><i class="fas fa-question-circle mr-1"></i>Ask a question</a>
                    <button onclick="sharePage('<?php echo addslashes($productData['name']); ?>', 'Check out this product!', window.location.href)" class="text-gray-600 hover:text-primary transition flex items-center">
                        <i class="fas fa-share-alt mr-1"></i>Share
                    </button>
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
                        <span id="variant-sku" class="text-gray-600"><?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Available:</span>
                        <span id="variant-stock-status" class="text-gray-600 capitalize"><?php echo str_replace('_', ' ', $productData['stock_status'] ?? 'in_stock'); ?></span>
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
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Brand:</span>
                        <span class="text-gray-600"><?php echo htmlspecialchars($productData['brand'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <!-- Guarantee -->
                <div class="mt-6 pt-6 border-t">
                    <p class="text-sm text-gray-600 text-center">Guarantee Safe Checkout</p>
                    <div class="flex justify-center items-center space-x-4 mt-4">
                        <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Visa_Inc._logo.svg.png" alt="Visa" class="h-8 w-8 object-contain">
                        <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Mastercard-logo.svg.png" alt="Mastercard" class="h-8 w-8 object-contain">
                        <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/American_Express_logo.svg.png" alt="American Express" class="h-8 w-8 object-contain">
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
                    <div id="description-content" class="hidden pb-4 text-gray-700 text-sm">
                        <?php echo nl2br(htmlspecialchars($productData['description'] ?? 'No description available.')); ?>
                    </div>
                </div>
                
                <!-- Shipping Policy -->
                <div class="border-b">
                    <button onclick="toggleSection('shipping')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Shipping and Returns</span>
                        <i class="fas fa-plus text-gray-400" id="shipping-icon"></i>
                    </button>
                    <div id="shipping-content" class="hidden pb-4 text-gray-700 text-sm">
                        <?php if (!empty($productData['shipping_policy'])): ?>
                            <div class="prose prose-sm max-w-none text-[15px]">
                                <?php echo $productData['shipping_policy']; ?>
                            </div>
                        <?php else: ?>
                            <p>We offer free shipping on all orders over <?php echo format_price(150, $productData['currency'] ?? 'USD'); ?>. Standard shipping takes 3-5 business days. International shipping may take 7-14 business days.</p>
                            <p class="mt-2">Returns are accepted within 30 days of purchase. Items must be unworn and in original packaging.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Return Policies -->
                <div class="border-b">
                    <button onclick="toggleSection('returns')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Return Policies</span>
                        <i class="fas fa-plus text-gray-400" id="returns-icon"></i>
                    </button>
                    <div id="returns-content" class="hidden pb-4 text-gray-700 text-sm">
                        <?php if (!empty($productData['return_policy'])): ?>
                            <div class="prose prose-sm max-w-none text-[15px]">
                                <?php echo $productData['return_policy']; ?>
                            </div>
                        <?php else: ?>
                            <p>We accept returns within 30 days of purchase. Items must be in original condition with tags attached.</p>
                            <p class="mt-2">To initiate a return, please contact our customer service team or visit your account page.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Reviews -->
        <div class="max-w-4xl mx-auto mb-16">
            <h2 class="text-xl font-heading font-bold mb-6">Customer Reviews</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Overall Rating -->
                <div class="text-center">
                    <div class="text-4xl font-bold mb-2"><?php echo number_format($rating, 2); ?></div>
                    <div class="flex justify-center mb-2 text-sm">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <i class="fas fa-star text-yellow-400"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-gray-600 text-sm">Based on <?php echo $reviewCount; ?> review<?php echo $reviewCount != 1 ? 's' : ''; ?></p>
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
            
            <button onclick="openReviewModal()" class="bg-white border-2 border-gray-300 px-6 py-2 rounded-lg hover:border-primary transition mb-6 text-sm">
                Write A Review
            </button>
            
            <!-- Reviews List -->
            <div class="space-y-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Most Recent</h3>
                    <select class="border rounded px-3 py-2 text-sm" id="reviewSort" onchange="loadReviews()">
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
                    <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative 1">
                        <div class="relative overflow-hidden">
                            <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                 class="w-full h-64 object-contain group-hover:scale-110 transition-transform duration-500">
                            <?php if ($itemDiscount > 0): ?>
                            <span class="absolute top-3 right-3 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                -<?php echo $itemDiscount; ?>%
                            </span>
                            <?php endif; ?>
                            <button class="wishlist-btn absolute top-3 left-3 bg-white rounded-full w-9 h-9 hover:bg-black hover:text-white transition opacity-0 group-hover:opacity-100 z-10"
                                    data-product-id="<?php echo $item['id']; ?>"
                                    onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist('<?php echo $item['id']; ?>', this)">
                                <i class="far fa-heart"></i>
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
                                <span class="text-gray-400 line-through text-sm mr-2"><?php echo format_price($itemOriginalPrice, $item['currency'] ?? 'USD'); ?></span>
                                <?php endif; ?>
                                <span class="font-bold text-primary"><?php echo format_price($itemPrice, $item['currency'] ?? 'USD'); ?></span>
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
                            <span class="font-bold text-primary"><?php echo format_price($itemPrice, $item['currency'] ?? 'USD'); ?></span>
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
const productVariants = <?php echo json_encode($productVariants); ?>;
const productMainSku = "<?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?>";
const productMainPrice = <?php echo $price; ?>;
const productCurrency = "<?php echo $productData['currency'] ?? 'USD'; ?>";
const currencySymbols = <?php 
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹']; 
    echo json_encode($symbols); 
?>;
const defaultMainImage = "<?php echo htmlspecialchars($mainImage); ?>";
let selectedOptions = {};

// Quantity Helper Functions
function updateProductQuantity(change) {
    const input = document.getElementById('productQuantity');
    let val = parseInt(input.value) + change;
    if (val < 1) val = 1;
    input.value = val;
}

function formatPriceJS(amount, currency) {
    const symbol = currencySymbols[currency] || currency;
    return symbol + parseFloat(amount).toFixed(2);
}

function getImageUrlJS(path) {
    if (!path || path === '' || path === 'null' || path === 'undefined') return defaultMainImage;
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path;
    
    const baseUrl = '<?php echo $baseUrl; ?>';
    const basePath = '<?php echo parse_url($baseUrl, PHP_URL_PATH); ?>';
    
    let cleanPath = path;
    // Remove base path if it exists at the start (de-duplicate)
    if (basePath !== '/' && cleanPath.startsWith(basePath)) {
        cleanPath = cleanPath.substring(basePath.length);
    }
    
    // Remove leading slash
    cleanPath = cleanPath.replace(/^\/+/, '');
    
    // If path doesn't contain assets/images/, prepend the uploads path
    if (!cleanPath.includes('assets/images/')) {
        cleanPath = 'assets/images/uploads/' + cleanPath;
    }
    
    return baseUrl + '/' + cleanPath;
}

function selectVariantOption(optionName, value, button) {
    selectedOptions[optionName] = value;
    
    // Update button UI
    const group = button.closest('.variant-option-group');
    group.querySelectorAll('.variant-btn').forEach(btn => {
        btn.classList.remove('border-primary', 'bg-primary', 'text-white');
        btn.classList.add('border-gray-300');
    });
    button.classList.remove('border-gray-300');
    button.classList.add('border-primary', 'bg-primary', 'text-white');
    
    // Update selected value text
    group.querySelector('.selected-value').textContent = value;
    
    updateVariantDisplay();
}

function selectVariantByImage(attributes, imageUrl) {
    selectedOptions = {...attributes};
    
    // Update buttons UI
    Object.keys(attributes).forEach(name => {
        const val = attributes[name];
        const group = document.querySelector(`.variant-option-group[data-option-name="${name}"]`);
        if (group) {
            group.querySelectorAll('.variant-btn').forEach(btn => {
                if (btn.textContent.trim() === val) {
                    btn.classList.remove('border-gray-300');
                    btn.classList.add('border-primary', 'bg-primary', 'text-white');
                    group.querySelector('.selected-value').textContent = val;
                } else {
                    btn.classList.remove('border-primary', 'bg-primary', 'text-white');
                    btn.classList.add('border-gray-300');
                }
            });
        }
    });
    
    const mainImg = document.getElementById('mainProductImage');
    const stickyImg = document.querySelector('#sticky-bar img');
    
    const finalImageUrl = getImageUrlJS(imageUrl);
    
    if (mainImg) mainImg.src = finalImageUrl;
    if (stickyImg) stickyImg.src = finalImageUrl;
    
    updateVariantDisplay();
}

function updateVariantDisplay() {
    // Find matching variant
    const matchingVariant = productVariants.find(v => {
        return Object.keys(selectedOptions).every(key => v.variant_attributes[key] === selectedOptions[key]);
    });
    
    const skuElement = document.getElementById('variant-sku');
    const priceElement = document.getElementById('product-price');
    const originalPriceElement = document.getElementById('original-price');
    const stickyOriginalPriceElement = document.getElementById('sticky-original-price');
    const stockElement = document.getElementById('variant-stock-status');
    const stickyPriceElement = document.getElementById('sticky-price');
    const mainImg = document.getElementById('mainProductImage');
    const stickyImg = document.querySelector('#sticky-bar img');
    
    if (matchingVariant) {
        // Update SKU
        if (skuElement) {
            skuElement.textContent = matchingVariant.sku || productMainSku;
        }

        // Update Price
        const displayPrice = matchingVariant.sale_price || matchingVariant.price || productMainPrice;
        const orgPrice = matchingVariant.sale_price ? matchingVariant.price : null;

        if (priceElement) {
            priceElement.textContent = formatPriceJS(displayPrice, productCurrency);
        }
        if (stickyPriceElement) {
            stickyPriceElement.textContent = formatPriceJS(displayPrice, productCurrency);
        }

        if (originalPriceElement) {
            if (orgPrice) {
                originalPriceElement.textContent = formatPriceJS(orgPrice, productCurrency);
                originalPriceElement.classList.remove('hidden');
            } else {
                originalPriceElement.classList.add('hidden');
            }
        }

        if (stickyOriginalPriceElement) {
            if (orgPrice) {
                stickyOriginalPriceElement.textContent = formatPriceJS(orgPrice, productCurrency);
                stickyOriginalPriceElement.classList.remove('hidden');
            } else {
                stickyOriginalPriceElement.classList.add('hidden');
            }
        }
        
        // Update Stock Status
        if (stockElement) {
            const stock = parseInt(matchingVariant.stock_quantity);
            if (stock > 0) {
                stockElement.textContent = 'In Stock';
                stockElement.className = 'text-green-600 capitalize';
            } else {
                stockElement.textContent = 'Out of Stock';
                stockElement.className = 'text-red-600 capitalize';
            }
        }
        
        // Update image if variant has one, otherwise revert to default
        const variantImg = getImageUrlJS(matchingVariant.image);
        if (mainImg) mainImg.src = variantImg;
        if (stickyImg) stickyImg.src = variantImg;
        
    } else {
        // Reset to default
        if (skuElement) skuElement.textContent = productMainSku;
        if (priceElement) priceElement.textContent = formatPriceJS(productMainPrice, productCurrency);
        if (stickyPriceElement) stickyPriceElement.textContent = formatPriceJS(productMainPrice, productCurrency);
        if (originalPriceElement) originalPriceElement.classList.add('hidden'); // Or reset to initial if applicable
        if (stickyOriginalPriceElement) stickyOriginalPriceElement.classList.add('hidden');
        
        if (stockElement) {
            stockElement.textContent = "<?php echo str_replace('_', ' ', $productData['stock_status'] ?? 'in_stock'); ?>";
            stockElement.className = 'text-gray-600 capitalize';
        }
        
        if (mainImg) mainImg.src = defaultMainImage;
        if (stickyImg) stickyImg.src = defaultMainImage;
    }

    // --- Sync Thumbnail Borders ---
    const currentSrc = mainImg ? mainImg.src : null;
    if (currentSrc) {
        let matchedAny = false;
        document.querySelectorAll('.thumbnail-img').forEach(el => {
            // Compare normalized URLs
            if (el.src === currentSrc) {
                el.classList.remove('border-transparent');
                el.classList.add('border-primary');
                matchedAny = true;
            } else {
                el.classList.remove('border-primary');
                el.classList.add('border-transparent');
            }
        });
        
        // If the variant image doesn't match any thumbnail (unlikely but possible), 
        // fallback to highlighting the first one if we're on default settings
        if (!matchedAny) {
             const firstThumb = document.querySelector('.thumbnail-img');
             if (firstThumb) {
                 firstThumb.classList.remove('border-transparent');
                 firstThumb.classList.add('border-primary');
             }
        }
    }
}

function changeMainImage(imageUrl, button, variantData = null) {
    const mainImg = document.getElementById('mainProductImage');
    const stickyImg = document.querySelector('#sticky-bar img');
    
    if (mainImg) mainImg.src = imageUrl;
    if (stickyImg) stickyImg.src = imageUrl;
    
    // Update thumbnail borders
    document.querySelectorAll('.thumbnail-img').forEach(el => {
        el.classList.remove('border-primary');
        el.classList.add('border-transparent');
    });
    button.classList.remove('border-transparent');
    button.classList.add('border-primary');

    // If it comes with variant data (image belongs to a variant), select that variant
    if (variantData && typeof selectVariantByImage === 'function') {
        selectVariantByImage(variantData, imageUrl);
    }
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

function addToCartFromDetail(productId, btn) {
    const quantity = parseInt(document.getElementById('productQuantity').value) || 1;
    
    if (btn) setBtnLoading(btn, true);
    
    // Use global addToCart function if available
    if (typeof addToCart === 'function') {
        addToCart(productId, quantity, btn, selectedOptions);
    } else {
        // Fallback to direct API call
        fetch('<?php echo $baseUrl; ?>/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                product_id: productId, 
                quantity: quantity,
                variant_attributes: selectedOptions
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
                    console.log(data.message || 'Failed to add product to cart');
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
                console.log('An error occurred while adding the product to cart');
            }
        })
        .finally(() => {
            if (btn) setBtnLoading(btn, false);
        });
    }
}

function buyNow(productId, btn) {
    const quantity = parseInt(document.getElementById('productQuantity').value) || 1;
    if (btn) setBtnLoading(btn, true);
    // Add to cart and redirect to checkout
    fetch('<?php echo $baseUrl; ?>/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            product_id: productId, 
            quantity: quantity,
            variant_attributes: selectedOptions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo url('checkout'); ?>';
        } else {
            console.log(data.message || 'Failed to add product to cart');
            if (btn) setBtnLoading(btn, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        console.log('An error occurred. Please try again.');
        if (btn) setBtnLoading(btn, false);
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
            console.log('Please select a rating');
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
                console.log('Thank you for your review! It has been submitted successfully.');
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
                console.log(data.message || 'Failed to submit review. Please try again.');
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
            console.log('An error occurred while submitting your review. Please try again.');
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

// Load reviews and initialize variants on page load
document.addEventListener('DOMContentLoaded', function() {
    loadReviews();
    
    // Auto-select first variant if available
    if (typeof productVariants !== 'undefined' && productVariants.length > 0) {
        const firstVariant = productVariants[0];
        if (firstVariant.variant_attributes) {
            selectVariantByImage(firstVariant.variant_attributes, firstVariant.image);
        }
    }
    
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
                <h2 class="text-xl font-heading font-bold">Write A Review</h2>
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
                                class="star-rating-btn text-2xl text-gray-300 hover:text-yellow-400 transition"
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
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="reviewEmail" class="block text-gray-700 font-semibold mb-2">Your Email *</label>
                    <input type="email" 
                           id="reviewEmail" 
                           name="user_email" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                </div>
                
                <!-- Review Title -->
                <div class="mb-4">
                    <label for="reviewTitle" class="block text-gray-700 font-semibold mb-2">Review Title</label>
                    <input type="text" 
                           id="reviewTitle" 
                           name="title" 
                           placeholder="Summarize your review"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                </div>
                
                <!-- Review Comment -->
                <div class="mb-6">
                    <label for="reviewComment" class="block text-gray-700 font-semibold mb-2">Your Review *</label>
                    <textarea id="reviewComment" 
                              name="comment" 
                              rows="5" 
                              required
                              placeholder="Share your experience with this product..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm"></textarea>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-center justify-end space-x-4">
                    <button type="button" 
                            onclick="closeReviewModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-light hover:text-white transition">
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function toggleProductWishlist(productId, btn) {
    if (!btn) return;

    // Determine action based on current state (text)
    // If text contains "Add", we want to ADD.
    // If text contains "Remove", we want to REMOVE.
    const isAdding = btn.textContent.toLowerCase().includes('add');
    const method = isAdding ? 'POST' : 'DELETE';
    
    // Save original content
    const originalContent = btn.innerHTML;
    
    // Show Loading
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
    btn.style.opacity = '0.7';
    btn.disabled = true;

    try {
        const response = await fetch('<?php echo $baseUrl; ?>/api/wishlist.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId
            })
        });

        const data = await response.json();

        if (data.success) {
            // Update UI based on the action we just performed
            if (isAdding) {
                // We added it
                btn.innerHTML = '<i class="fas fa-heart mr-1 text-red-500"></i> Remove from Wishlist';
                // btn.classList.add('text-red-500'); 
            } else {
                // We removed it
                btn.innerHTML = '<i class="far fa-heart mr-1"></i> Add to Wishlist';
                // btn.classList.remove('text-red-500');
            }
            
            // Try to update global counts if the main script is loaded
            if (typeof refreshWishlist === 'function') {
                refreshWishlist();
            }
        } else {
            console.error('Wishlist action failed:', data.message);
            // Revert on error
            btn.innerHTML = originalContent;
        }
    } catch (error) {
        console.error('Error toggling wishlist:', error);
        btn.innerHTML = originalContent;
    } finally {
        btn.style.opacity = '1';
        btn.disabled = false;
    }
}
</script>

<!-- Sticky Add To Cart Bar -->
<div id="sticky-bar" class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] transform translate-y-full transition-transform duration-300 z-40 px-3 py-3 md:px-4">
    <div class="container mx-auto flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 overflow-hidden">
            <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                 alt="Sticky Bar Product" 
                 class="w-10 h-10 md:w-12 md:h-12 object-contain rounded border border-gray-100 flex-shrink-0"
                 onerror="this.src='https://via.placeholder.com/100x100?text=Product'">
            <div class="min-w-0">
                <h3 class="font-bold text-gray-900 leading-tight text-sm md:text-base truncate"><?php echo htmlspecialchars($productData['name']); ?></h3>
                <div class="hidden md:flex text-xs text-yellow-500 items-center mt-1">
                    <?php 
                    $rating = floatval($productData['rating'] ?? 5);
                    for ($i = 0; $i < 5; $i++) {
                        echo '<i class="fas fa-star ' . ($i < $rating ? '' : 'text-gray-300') . '"></i>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="text-right mr-2 hidden md:block">
                 <div class="text-xs text-gray-500">Total Price:</div>
                 <div class="flex items-center justify-end gap-2">
                     <span id="sticky-original-price" class="text-sm text-gray-400 line-through <?php echo !$originalPrice ? 'hidden' : ''; ?>"><?php echo format_price($originalPrice ?: 0, $productData['currency'] ?? 'USD'); ?></span>
                     <div class="font-bold text-lg text-gray-900" id="sticky-price"><?php echo format_price($price, $productData['currency'] ?? 'USD'); ?></div>
                 </div>
            </div>
            
            <!-- Quantity - simplified -->
             <div class="hidden md:flex items-center border border-gray-300 rounded-md w-24 h-10 overflow-hidden bg-white">
                <button onclick="updateStickyQty(-1)" class="w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 transition">-</button>
                <input type="number" id="sticky-qty" value="1" min="1" class="flex-1 w-full text-center border-none focus:ring-0 p-0 text-gray-900 pl-2 font-semibold appearance-none bg-transparent h-full text-sm" readonly>
                <button onclick="updateStickyQty(1)" class="w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 transition">+</button>
            </div>

            <button onclick="stickyAddToCart()" class="bg-black text-white px-4 py-2.5 md:px-8 rounded-full font-bold hover:bg-gray-800 transition shadow-lg transform hover:-translate-y-0.5 text-sm md:text-base whitespace-nowrap" id="sticky-atc-btn">
                Add To Cart
            </button>
            
            <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="hidden md:flex w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 items-center justify-center transition ml-2">
                <i class="fas fa-arrow-up text-gray-600"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Sticky Bar Logic
document.addEventListener('scroll', function() {
    const stickyBar = document.getElementById('sticky-bar');
    const footer = document.querySelector('footer');
    const mainAtcBtn = document.querySelector('.add-to-cart-btn');
    
    if (!stickyBar) return;
    
    const scrollY = window.scrollY;
    
    // Calculate trigger point based on Main Add To Cart Button
    let triggerPoint = 600; // default fallback (approx height of mobile viewport/hero)
    
    if (mainAtcBtn) {
        // Get absolute position of the button's bottom edge
        const rect = mainAtcBtn.getBoundingClientRect();
        const absoluteBottom = rect.bottom + window.scrollY;
        
        // Trigger just after button scrolls out of view
        triggerPoint = absoluteBottom; 
    } else {
        // Fallback: try to find the first image or grid
        const mainImage = document.getElementById('mainProductImage');
        if (mainImage) {
             const rect = mainImage.getBoundingClientRect();
             triggerPoint = rect.bottom + window.scrollY;
        }
    }
    
    // Check if we hit footer
    let footerTop = document.documentElement.scrollHeight; 
    if(footer) footerTop = footer.offsetTop;
    
    // Show if passed the trigger point AND not yet at footer (with buffer)
    if (scrollY > triggerPoint && (scrollY + window.innerHeight) < (footerTop + 50)) {
        stickyBar.classList.remove('translate-y-full');
    } else {
        stickyBar.classList.add('translate-y-full');
    }
});

function updateStickyQty(change) {
    const input = document.getElementById('sticky-qty');
    let val = parseInt(input.value) + change;
    if (val < 1) val = 1;
    input.value = val;
    
    // Sync with main quantity if exists
    const mainQty = document.getElementById('productQuantity');
    if(mainQty) mainQty.value = val;
}

function stickyAddToCart() {
    const btn = document.getElementById('sticky-atc-btn');
    const qty = parseInt(document.getElementById('sticky-qty').value) || 1;
    const productId = <?php echo $productData['id']; ?>;
    
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
        
        // Use main add to cart function if available (it handles variants)
        if (typeof addToCartFromDetail === 'function') {
            // We need to make sure main input is synced first
            const mainQty = document.getElementById('productQuantity');
            if(mainQty) mainQty.value = qty;
            
            // Call main function
            addToCartFromDetail(productId, null); 
            
            // Reset sticky button after short delay
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1000);
        } else {
            // Fallback
             fetch('<?php echo $baseUrl; ?>/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: qty })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    if(typeof refreshCart === 'function') refreshCart();
                    // trigger side cart
                     const sideCart = document.getElementById('sideCart');
                    const cartOverlay = document.getElementById('cartOverlay');
                    if (sideCart && cartOverlay) {
                        sideCart.classList.remove('translate-x-full');
                        cartOverlay.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                } else {
                    console.log('Error: ' + data.message);
                }
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

