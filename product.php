    <?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Process redirects BEFORE any output
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Wishlist.php'; // Add Wishlist class
require_once __DIR__ . '/classes/Settings.php'; // Add Settings class
require_once __DIR__ . '/includes/functions.php';

$customerAuth = new CustomerAuth();
$customer = $customerAuth->getCurrentCustomer();
$product = new Product();
$db = Database::getInstance();
$settings = new Settings(); // Instantiate Settings
$wishlistObj = new Wishlist(); // Instantiate Wishlist
$wishlistItems = $wishlistObj->getWishlist(); // Get items
$wishlistIds = array_column($wishlistItems, 'product_id'); // Extract IDs

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

// Parse images
$images = json_decode($productData['images'] ?? '[]', true);
$mainImage = !empty($images[0]) ? getImageUrl($images[0]) : getProductImage($productData);
$schemaImages = [];
if (!empty($images)) {
    foreach($images as $img) {
        $schemaImages[] = getImageUrl($img);
    }
} else {
    $schemaImages = [$mainImage];
}

// --- RECENTLY VIEWED LOGIC (Must be before headers) ---
// Get recently viewed (from cookie)
$recentlyViewed = [];
$recentIds = isset($_COOKIE['recently_viewed']) ? json_decode($_COOKIE['recently_viewed'], true) : [];
if (!is_array($recentIds)) { $recentIds = []; }

// Add current product to top of list
// Use 10-digit product_id if available, otherwise fallback to id
$currentId = $productData['product_id'] ?? $productData['id'];
array_unshift($recentIds, $currentId);
$recentIds = array_unique($recentIds);
$recentIds = array_slice($recentIds, 0, 10); // Keep last 10

// Set cookie (valid for 30 days)
setcookie('recently_viewed', json_encode($recentIds), time() + (30 * 24 * 60 * 60), '/'); // Allow accross entire domain
$_COOKIE['recently_viewed'] = json_encode($recentIds); // Update current runtime global

// Fetch the actual product data for these IDs (excluding current)
$displayRecentIds = array_filter($recentIds, function($id) use ($currentId) {
    return $id != $currentId;
});
$displayRecentIds = array_slice($displayRecentIds, 0, 4);

if (!empty($displayRecentIds)) {
    $placeholders = implode(',', array_fill(0, count($displayRecentIds), '?'));
    // Query by product_id primarily, but fallback to id for legacy cookies could be added if needed.
    // Given user request, we stick to product_id column for these lookups if permissible, 
    // but typically `products` table primary key is `id`. 
    // If the cookie stores `product_id` (the 10-digit one), we must query `product_id` column.
    $recentlyViewed = $db->fetchAll(
        "SELECT * FROM products WHERE product_id IN ($placeholders) AND status = 'active'",
        $displayRecentIds
    );
}
// -------------------------------------------------------

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
    if (!empty($productData['category_id'])) {
        $oldCategory = $db->fetchOne("SELECT id, name, slug FROM categories WHERE id = ? AND status = 'active'", [$productData['category_id']]);
        if ($oldCategory) $productCategories = [$oldCategory];
    }
}

// Get primary category for breadcrumbs
$primaryCategory = !empty($productCategories) ? $productCategories[0] : null;

// Calculate price and discount
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;
$originalPrice = !empty($productData['sale_price']) ? $productData['price'] : null;

// Get product variants
$variantsData = $product->getVariants($productData['id']);
$productOptions = $variantsData['options'] ?? [];
$productVariants = $variantsData['variants'] ?? [];
$firstVariant = !empty($productVariants) ? $productVariants[0] : null;

if ($firstVariant) {
    if ($firstVariant['sale_price'] > 0) {
        $price = $firstVariant['sale_price'];
        if ($firstVariant['price'] > 0) {
            $originalPrice = $firstVariant['price'];
        }
    } else {
        $price = $firstVariant['price'] ?: $price;
        $originalPrice = null; // No sale on variant
    }
    
    // Override main image if variant has one
    if (!empty($firstVariant['image'])) {
        $mainImage = getImageUrl($firstVariant['image']);
    }
}

// Generate Product Schema
$productSchema = [
    "@context" => "https://schema.org/",
    "@type" => "Product",
    "name" => $productData['name'],
    "image" => $schemaImages,
    "description" => trim(strip_tags($productData['description'])),
    "sku" => $productData['sku'] ?? '',
    "brand" => [
        "@type" => "Brand",
        "name" => $productData['brand'] ?? $settings->get('site_name', 'Zensshop')
    ],
    "offers" => [
        "@type" => "Offer",
        "url" => $baseUrl . '/product/' . $productData['slug'],
        "priceCurrency" => "INR",
        "price" => $price,
        "availability" => ($productData['stock_status'] === 'instock' || ($productData['stock'] ?? 0) > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
        "itemCondition" => "https://schema.org/NewCondition"
    ]
];
$customSchema = json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

// Now include header
$pageTitle = $productData['name'];
require_once __DIR__ . '/includes/header.php';

$discount = $originalPrice && $originalPrice > 0 ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;

// Determine initial stock status
$currentStock = $productData['stock_quantity'] ?? 0;
$currentStatus = $productData['stock_status'] ?? 'in_stock';

if ($firstVariant) {
    if (isset($firstVariant['stock_quantity'])) $currentStock = $firstVariant['stock_quantity'];
    if (isset($firstVariant['stock_status'])) $currentStatus = $firstVariant['stock_status'];
}

$totalSold = (int)($productData['total_sales'] ?? 0);
$isOutOfStock = ($currentStatus === 'out_of_stock' || $currentStock <= 0);
$stockLabel = get_stock_status_text($currentStatus, $currentStock, $totalSold);

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

// Product Options and Variants (already fetched above)
?>

<section class=" md:py-12 bg-white">
    <div class="container mx-auto px-4">
        <!-- Breadcrumbs -->
        <nav class="text-sm text-gray-600 mb-6 pt-3 mt-5">
            <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a> > 
            <?php if ($primaryCategory): ?>
            <a href="<?php echo $baseUrl; ?>/shop?category=<?php echo urlencode($primaryCategory['slug']); ?>" class="hover:text-primary">
                <?php echo htmlspecialchars($primaryCategory['name']); ?>
            </a> > 
            <?php endif; ?>
            <span class="text-gray-900"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></span>
        </nav>
        
        <!-- Product Skeleton -->
        <div id="productSkeleton" class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-16 animate-pulse">
            <!-- Image Skeleton -->
            <div class="rounded-lg overflow-hidden">
                <div class="w-full h-[500px] bg-gray-200 mb-4 rounded-lg"></div>
                <div class="flex gap-2 justify-center">
                    <div class="w-20 h-20 bg-gray-200 rounded"></div>
                    <div class="w-20 h-20 bg-gray-200 rounded"></div>
                    <div class="w-20 h-20 bg-gray-200 rounded"></div>
                    <div class="w-20 h-20 bg-gray-200 rounded"></div>
                </div>
            </div>
            
            <!-- Info Skeleton -->
            <div class="space-y-6">
                <div class="space-y-2">
                    <div class="h-10 bg-gray-200 rounded w-3/4"></div> <!-- Title -->
                    <div class="flex items-center space-x-4">
                         <div class="h-5 bg-gray-200 rounded w-32"></div> <!-- Rating -->
                         <div class="h-5 bg-gray-200 rounded w-24"></div> <!-- Sold count -->
                    </div>
                </div>
                
                <div class="h-10 bg-gray-200 rounded w-1/3"></div> <!-- Price -->
                
                <div class="space-y-3">
                    <div class="h-4 bg-gray-200 rounded w-full"></div>
                    <div class="h-4 bg-gray-200 rounded w-full"></div>
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                </div>
                
                <!-- Options Skeleton -->
                <div class="space-y-4 pt-4">
                    <div>
                        <div class="h-6 bg-gray-200 rounded w-24 mb-2"></div>
                        <div class="flex gap-2">
                            <div class="w-16 h-10 bg-gray-200 rounded"></div>
                            <div class="w-16 h-10 bg-gray-200 rounded"></div>
                            <div class="w-16 h-10 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>

                <!-- Quantity Skeleton -->
                 <div class="flex items-center gap-4">
                    <div class="h-6 bg-gray-200 rounded w-24"></div>
                    <div class="w-28 h-10 bg-gray-200 rounded"></div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <div class="h-14 bg-gray-200 rounded flex-1"></div> <!-- Add to Cart -->
                    <div class="h-14 bg-gray-200 rounded flex-1"></div> <!-- Buy Now -->
                </div>
                
                <div class="flex gap-4 pt-2">
                     <div class="h-6 bg-gray-200 rounded w-32"></div>
                     <div class="h-6 bg-gray-200 rounded w-32"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-16 hidden" id="mainProductContainer">
            <!-- Product Images -->
            <div>

                <!-- Main Image -->
                <div class="mb-4 w-full max-w-[730px] mx-auto flex items-center justify-center bg-gray-50 rounded-lg overflow-hidden border border-gray-100 relative group" id="mainImageContainer" style="aspect-ratio: 1 / 1;">
                    <?php
                    $mainExt = strtolower(pathinfo($mainImage, PATHINFO_EXTENSION));
                    $isMainVideo = in_array($mainExt, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v']);
                    ?>

                    <img id="mainProductImage" 
                         src="<?php echo htmlspecialchars($mainImage); ?>" 
                         alt="<?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?>" 
                         class="w-full h-full object-contain transition-transform duration-200 ease-out cursor-zoom-in <?php echo $isMainVideo ? 'hidden' : ''; ?>"
                         fetchpriority="high"
                         loading="eager"
                         onmousemove="zoomImage(event, this)"
                         onmouseleave="resetZoom(this)"
                         onerror="this.src='https://placehold.co/730x730?text=Product+Image'">
                         
                    <video id="mainProductVideo"
                           src="<?php echo $isMainVideo ? htmlspecialchars($mainImage) : ''; ?>"
                           controls
                           class="w-full h-full object-contain bg-black <?php echo $isMainVideo ? '' : 'hidden'; ?>">
                    </video>
                </div>

                <script>
                function zoomImage(e, img) {
                    const container = img.parentElement;
                    const { left, top, width, height } = container.getBoundingClientRect();
                    const x = (e.clientX - left) / width;
                    const y = (e.clientY - top) / height;

                    img.style.transformOrigin = `${x * 100}% ${y * 100}%`;
                    img.style.transform = "scale(2)"; // Adjustable zoom level
                }

                function resetZoom(img) {
                    img.style.transform = "scale(1)";
                    img.style.transformOrigin = "center center";
                }
                </script>

                
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
                
                <!-- Swiper CSS -->
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
                <style>
                    .thumbnail-slider {
                        padding: 0 0px; /* Space for arrows */
                        position: relative;
                    }
                    .thumbnail-slider .swiper-button-next,
                    .thumbnail-slider .swiper-button-prev {
                        color: #000;
                        width: 30px;
                        height: 30px;
                        background: #fff;
                        border: 1px solid #e5e7eb;
                        border-radius: 50%;
                        top: 50%;
                        transform: translateY(-50%);
                        margin-top: 0; /* Reset default swiper margin */
                        position: absolute;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    }
                    .thumbnail-slider .swiper-button-next { right: 0; }
                    .thumbnail-slider .swiper-button-prev { left: 0; }
                    .thumbnail-slider .swiper-button-next:hover,
                    .thumbnail-slider .swiper-button-prev:hover {
                        background: #f9fafb;
                    }
                    .thumbnail-slider .swiper-button-next::after,
                    .thumbnail-slider .swiper-button-prev::after {
                        font-size: 14px;
                        font-weight: bold;
                    }
                    .thumbnail-slider .swiper-slide {
                        height: auto;
                        display: flex;
                        justify-content: center;
                        width: auto;
                    }
                    .thumbnail-slider .swiper-slide .thumbnail-img {
                        width: auto; 
                        height: 80px;
                        object-fit: contain;
                        background-color: #f9fafb;
                        margin: 5px;
                    }
                    </style>
                <?php if (count($galleryItems) > 1): ?>
                <div class="relative">
                    <div class="swiper thumbnail-slider mt-4">
                        <div class="swiper-wrapper">
                            <?php foreach ($galleryItems as $index => $item): 
                                $ext = strtolower(pathinfo($item['url'], PATHINFO_EXTENSION));
                                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v']);
                            ?>
                            <div class="swiper-slide">
                                <?php if ($isVideo): ?>
                                    <div class="thumbnail-img w-auto h-[80px] object-cover rounded cursor-pointer border-2 transition bg-black flex items-center justify-center overflow-hidden aspect-square <?php echo ($index === 0 && $isMainVideo) ? 'border-primary' : 'border-transparent'; ?>"
                                         style="min-width: 80px;"
                                         onclick="changeMainImage('<?php echo htmlspecialchars($item['url']); ?>', this, <?php echo $item['variant'] ? htmlspecialchars(json_encode($item['variant'])) : 'null'; ?>, true)">
                                        <video src="<?php echo htmlspecialchars($item['url']); ?>" class="w-full h-full object-cover opacity-60 pointer-events-none" preload="metadata"></video>
                                        <i class="fas fa-play-circle text-white text-xl absolute pointer-events-none"></i>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($item['url']); ?>" 
                                         alt="Thumbnail <?php echo $index + 1; ?>"
                                         class="thumbnail-img object-contain rounded cursor-pointer border-2 transition hover:border-primary <?php echo ($index === 0 && !$isMainVideo) ? 'border-primary' : 'border-transparent'; ?>"
                                         onclick="changeMainImage('<?php echo htmlspecialchars($item['url']); ?>', this, <?php echo $item['variant'] ? htmlspecialchars(json_encode($item['variant'])) : 'null'; ?>, false)"
                                         onerror="this.src='https://placehold.co/150x150?text=Product+Image'"
                                         loading="lazy">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Navigation arrows -->
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Information -->
            <div>
                <h1 class="text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></h1>
                
                <!-- Rating and Reviews -->
                <div class="flex items-center space-x-4 mb-4 text-sm cursor-pointer hover:opacity-80 transition" onclick="document.getElementById('customer-reviews').scrollIntoView({ behavior: 'smooth' })">
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
                    <span class="text-gray-600 underline decoration-dotted"><?php echo $reviewCount; ?> review<?php echo $reviewCount != 1 ? 's' : ''; ?></span>
                    <span class="text-gray-600">10 sold in last 18 hours</span>
                </div>
                
                <!-- Price -->
                <div class="mb-6">
                    <span id="original-price" class="text-2xl text-gray-400 line-through mr-2 <?php echo !$originalPrice ? 'hidden' : ''; ?>"><?php echo format_price($originalPrice ?: 0, $productData['currency'] ?? 'USD'); ?></span>
                    <span id="product-price" class="text-2xl font-bold text-[#1a3d32]"><?php echo format_price($price, $productData['currency'] ?? 'USD'); ?></span>
                </div>
                
                <!-- Description with Read More -->
                <div class="mb-6">
                    <style>
                        .line-clamp-5 {
                            display: -webkit-box;
                            -webkit-line-clamp: 5;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                        }
                    </style>
                    <div id="product-description-text" class="text-gray-700 leading-relaxed transition-all duration-300 prose prose-sm max-w-none line-clamp-5">
                        <?php echo htmlspecialchars_decode($productData['description'] ?? $productData['short_description'] ?? 'No description available.'); ?>
                    </div>
                    <button id="toggle-description-btn" 
                            onclick="toggleProductDescription()" 
                            class="text-primary hover:text-green-800 font-medium text-sm mt-2 hidden focus:outline-none">
                        Read More
                    </button>
                    
                    <script>
                    function toggleProductDescription() {
                        const container = document.getElementById('product-description-text');
                        const btn = document.getElementById('toggle-description-btn');
                        
                        if (container.classList.contains('line-clamp-5')) {
                            // Expand
                            container.classList.remove('line-clamp-5');
                            btn.textContent = 'Read Less';
                        } else {
                            // Collapse
                            container.classList.add('line-clamp-5');
                            btn.textContent = 'Read More';
                        }
                    }

                    // Check if content exceeds height to show/hide button
                    document.addEventListener('DOMContentLoaded', function() {
                        const container = document.getElementById('product-description-text');
                        const btn = document.getElementById('toggle-description-btn');
                        
                        // Check if content exceeds the clamp limit
                        // When clamped, scrollHeight (full content) will be greater than clientHeight (visible area)
                        if (container.scrollHeight > container.clientHeight) {
                            btn.classList.remove('hidden');
                        }
                    });
                    </script>
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
                    endif; ?>
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
                            <!-- <?php if (strtolower($optionName) === 'size'): ?>
                                <a href="#" class="text-sm text-primary hover:underline">Size guide</a>
                            <?php endif; ?> -->
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
                
                <!-- Quantity Selector - Premium Non-clickable Style -->
                <div class="flex items-center gap-4 mb-6">
                    <label class="font-semibold text-gray-900">Quantity:</label>
                    <div class="flex items-center border border-gray-300 rounded-md w-28 h-10 overflow-hidden bg-white">
                        <button onclick="updateProductQuantity(-1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">-</button>
                        <div class="flex-1 h-full grid place-items-center">
                            <span id="productQuantityDisplay" class="text-gray-900 font-bold text-base select-none">1</span>
                        </div>
                        <input type="hidden" id="productQuantity" value="1">
                        <button onclick="updateProductQuantity(1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">+</button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 mb-2">
                    <button onclick="addToCartFromDetail(<?php echo $productData['product_id']; ?>, this)" 
                            class="flex-1 bg-black text-white py-4 px-6 hover:bg-gray-800 transition font-semibold flex items-center justify-center add-to-cart-btn <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            data-loading-text="Adding..."
                            <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        <?php if ($isOutOfStock): ?>
                            <?php echo $stockLabel; ?>
                        <?php else: ?>
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Add To Cart
                        <?php endif; ?>
                    </button>
                    <button onclick="buyNow(<?php echo $productData['product_id']; ?>, this)" 
                            class="flex-1 bg-red-700 text-white py-4 px-6 hover:bg-red-600 transition font-semibold buy-now-btn <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            data-loading-text="Processing..."
                            <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        Buy It Now
                    </button>
                </div>
                
                <!-- Availability Status (Dynamic) -->
                <div class="mb-6 flex items-center space-x-2">
                    <span id="stock-count-display" class="text-sm font-bold <?php echo ($currentStatus === 'in_stock' && $currentStock > 0) ? 'text-primary' : (($currentStatus === 'in_stock' && $currentStock < 0) ? 'text-orange-600' : 'text-red-600'); ?>">
                        <?php 
                        $currentLabel = get_stock_status_text($currentStatus, $currentStock, $totalSold);
                        if ($currentLabel === 'Out of Stock') {
                            echo '<i class="fas fa-times-circle mr-1"></i> Out of Stock';
                        } elseif ($currentLabel === 'Sold Out') {
                            echo '<i class="fas fa-times-circle mr-1"></i> Sold Out';
                        } elseif ($currentStock > 0) {
                            echo '<i class="fas fa-check-circle mr-1"></i> ' . $currentStock . ' items available';
                        } elseif ($currentStock < 0) {
                            echo '<i class="fas fa-exclamation-circle mr-1"></i> Backorder (' . abs($currentStock) . ' pending)';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- Additional Links -->
                <div class="flex flex-wrap gap-4 text-sm mb-6">
                    <button onclick="toggleProductWishlist(<?php echo $productData['product_id'] ?? $productData['id']; ?>, this)" class="text-gray-600 hover:text-primary transition flex items-center wishlist-btn" data-product-id="<?php echo $productData['product_id'] ?? $productData['id']; ?>">
                        <?php if (in_array($productData['product_id'] ?? $productData['id'], $wishlistIds)): ?>
                            <i class="fas fa-heart mr-1"></i> Remove from Wishlist
                        <?php else: ?>
                            <i class="far fa-heart mr-1"></i> Add to Wishlist
                        <?php endif; ?>
                    </button>
                    <!-- <a href="#" class="text-gray-600 hover:text-primary transition"><i class="fas fa-exchange-alt mr-1"></i>Compare colors</a> -->
                    <a href="javascript:void(0)" onclick="toggleAskQuestionModal(true, '<?php echo addslashes($productData['name']); ?>')" class="text-gray-600 hover:text-primary transition flex items-center font-medium"><i class="fas fa-question-circle mr-1 text-primary"></i>Ask a question</a>
                    <button onclick="sharePage('<?php echo addslashes($productData['name']); ?>', 'Check out this product!', window.location.href)" class="text-gray-600 hover:text-primary transition flex items-center">
                        <i class="fas fa-share-alt mr-1"></i>Share
                    </button>
                </div>
                
                <!-- Pickup Information -->
                <?php if ($settings->get('pickup_enable', '1') == '1'): ?>
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="text-sm text-gray-700 flex items-start">
                        <i class="<?php echo htmlspecialchars($settings->get('pickup_icon', 'fas fa-store')); ?> mr-2 text-primary mt-1 flex-shrink-0"></i>
                        <div class="prose prose-sm max-w-none">
                            <?php echo $settings->get('pickup_message', 'Pickup available at Shop location. Usually ready in 24 hours'); ?>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($settings->get('pickup_link_url', '#')); ?>" class="text-sm text-primary hover:underline mt-1 inline-block">
                        <?php echo htmlspecialchars($settings->get('pickup_link_text', 'View store information')); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Product Details -->
                <div class="border-t pt-6 space-y-2 text-sm">
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Sku:</span>
                        <span id="variant-sku" class="text-gray-600"><?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-24">Available:</span>
                        <span id="variant-stock-status" class="text-gray-600 capitalize"><?php echo $stockLabel; ?></span>
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
        
        <script>
            // Immediate Skeleton Removal
            (function() {
                const skeleton = document.getElementById('productSkeleton');
                const content = document.getElementById('mainProductContainer');
                if (skeleton && content) {
                    skeleton.classList.add('hidden');
                    content.classList.remove('hidden');
                }
            })();
        </script>
        
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
                        <div class="prose prose-sm max-w-none">
                            <?php echo htmlspecialchars_decode($productData['description'] ?? 'No description available.'); ?>
                        </div>
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
        <div class="max-w-4xl mx-auto mb-16" id="customer-reviews">
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
        <div class="mb-5">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-heading font-bold mb-2">People Also Bought</h2>
                <p class="text-gray-600">Here's some of our most similar products people are buying. Click to discover trending style.</p>
            </div>
            <div class="swiper people-bought-slider pb-12 px-4 md:px-12 relative">
                <div class="swiper-wrapper">
                    <?php foreach ($relatedProducts as $item): 
                        $itemImage = getProductImage($item);
                        $itemPrice = $item['sale_price'] ?? $item['price'] ?? 0;
                        $itemOriginalPrice = !empty($item['sale_price']) ? $item['price'] : null;
                        $itemDiscount = $itemOriginalPrice && $itemOriginalPrice > 0 ? round((($itemOriginalPrice - $itemPrice) / $itemOriginalPrice) * 100) : 0;
                        
                        // Use product_id (10-digit) if available, matching best-selling logic
                        $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                        $inWishlist = in_array($currentId, $wishlistIds);
                    ?>
                    <div class="swiper-slide h-auto">
                        <div class="group product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative flex flex-col h-full">
                            <div class="relative overflow-hidden">
                                <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="block">
                                    <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                            alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                            class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                                            loading="lazy"
                                            onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                                </a>
                                <?php if ($itemDiscount > 0): ?>
                                <span class="absolute top-3 left-3 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold z-10">
                                    -<?php echo $itemDiscount; ?>%
                                </span>
                                <?php endif; ?>
                                <button type="button" class="absolute top-2 right-2 w-10 h-10 rounded-full flex items-center justify-center <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white transition z-20 wishlist-btn"
                                        data-product-id="<?php echo $currentId; ?>"
                                        aria-label="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                                        title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
                                    <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                                </button>
                                
                                <!-- Hover Action Buttons -->
                                <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                                    <button type="button" class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                                            data-product-id="<?php echo $currentId; ?>"
                                            aria-label="Quick view product"
                                            data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span class="product-tooltip">Quick View</span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 flex flex-col flex-1">
                                <h3 class="font-semibold text-gray-800 mb-2 h-12 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                                    <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition">
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
                                <div class="flex items-center gap-2 mb-4 mt-auto">
                                    <span class="text-base font-bold <?php echo $itemOriginalPrice ? 'text-[#1a3d32]' : 'text-primary'; ?>">
                                        <?php echo format_price($itemPrice, $item['currency'] ?? 'USD'); ?>
                                    </span>
                                    <?php if ($itemOriginalPrice): ?>
                                        <span class="text-red-500 font-bold line-through text-xs"><?php echo format_price($itemOriginalPrice, $item['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Add to Cart Button -->
                                <?php
                                // Get default variant attributes
                                $variantsData = $product->getVariants($item['id']);
                                $defaultAttributes = [];
                                if (!empty($variantsData['variants'])) {
                                    $defaultVariant = $variantsData['variants'][0];
                                    foreach ($variantsData['variants'] as $v) {
                                        if (!empty($v['is_default'])) {
                                            $defaultVariant = $v;
                                            break;
                                        }
                                    }
                                    $defaultAttributes = $defaultVariant['variant_attributes'];
                                }
                                $attributesJson = json_encode($defaultAttributes);
                                $isOutOfStock = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                                ?>
                                <button onclick='addToCart(<?php echo $item['product_id']; ?>, 1, this, <?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>)' 
                                        class="w-full bg-[#1a3d32] text-white px-4 py-2.5 rounded hover:bg-black transition text-xs font-bold flex items-center justify-center gap-2 <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                        <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                    <?php if ($isOutOfStock): ?>
                                        <span><?php echo strtoupper(get_stock_status_text($item['stock_status'] ?? 'in_stock', $item['stock_quantity'] ?? 0)); ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-shopping-cart text-[10px]"></i>
                                        <span>ADD TO CART</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Pagination -->
                <div class="swiper-pagination"></div>
                <!-- Navigation -->
                <div class="swiper-button-next !text-black !w-10 !h-10 !bg-white !shadow-md !rounded-full after:!text-sm !right-2 md:!right-4"></div>
                <div class="swiper-button-prev !text-black !w-10 !h-10 !bg-white !shadow-md !rounded-full after:!text-sm !left-2 md:!left-4"></div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Swiper('.people-bought-slider', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    loop: true,
                    centerInsufficientSlides: true,
                    centeredSlides: false,
                    autoplay: {
                        delay: 5000,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    breakpoints: {
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 20,
                        },
                        1024: {
                            slidesPerView: 3,
                            spaceBetween: 30,
                        },
                        1280: {
                            slidesPerView: 4,
                            spaceBetween: 30,
                        },
                    }
                });
            });
            </script>
        </div>
        <?php endif; ?>
        
        <!-- Recently Viewed -->
        <?php if (!empty($recentlyViewed)): ?>
        <div class="mb-16">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-heading font-bold mb-2">Recently Viewed</h2>
                <p class="text-gray-600">Explore your recently viewed items, blending quality and style for a refined living experience.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
                <?php foreach ($recentlyViewed as $item): 
                    $itemImage = getProductImage($item);
                    $itemPrice = $item['sale_price'] ?? $item['price'] ?? 0;
                    $itemOriginalPrice = !empty($item['sale_price']) ? $item['price'] : null;
                    
                    // Use product_id (10-digit) if available, matching best-selling logic
                    $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                    $inWishlist = in_array($currentId, $wishlistIds);
                ?>
                <div class="group bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 relative flex flex-col h-full">
                    <div class="relative overflow-hidden">
                        <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="block">
                            <img src="<?php echo htmlspecialchars($itemImage); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>"
                                 class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                                 onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                        </a>
                        <?php if ($item['id'] == $productData['id']): ?>
                        <span class="absolute top-3 left-3 bg-[#1a3d32] text-white px-2 py-1 rounded text-[10px] font-bold z-10 uppercase tracking-tight">
                            Current Item
                        </span>
                        <?php endif; ?>
                        <button class="wishlist-btn absolute top-3 right-3 rounded-full w-9 h-9 hover:bg-black hover:text-white transition opacity-0 group-hover:opacity-100 z-20 flex items-center justify-center <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?>"
                                data-product-id="<?php echo $currentId; ?>">
                            <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <h3 class="font-semibold text-gray-800 mb-2 h-10 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                            <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition">
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
                            <span class="text-base font-bold <?php echo $itemOriginalPrice ? 'text-[#1a3d32]' : 'text-primary'; ?>">
                                <?php echo format_price($itemPrice, $item['currency'] ?? 'USD'); ?>
                            </span>
                            <?php if ($itemOriginalPrice): ?>
                                <span class="text-red-500 font-bold line-through text-xs"><?php echo format_price($itemOriginalPrice, $item['currency'] ?? 'USD'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mini Add to Cart Button for Recently Viewed -->
                        <?php
                        // Get default variant attributes
                        $vData = $product->getVariants($item['id']);
                        $defAttrs = [];
                        if (!empty($vData['variants'])) {
                            $defVar = $vData['variants'][0];
                            foreach ($vData['variants'] as $v) {
                                if (!empty($v['is_default'])) {
                                    $defVar = $v;
                                    break;
                                }
                            }
                            $defAttrs = $defVar['variant_attributes'];
                        }
                        $attrsJson = json_encode($defAttrs);
                        $outOfStock = (($item['stock_status'] ?? 'in_stock') === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                        ?>
                        <div class="mt-4">
                            <button onclick='addToCart(<?php echo $item['product_id']; ?>, 1, this, <?php echo htmlspecialchars($attrsJson, ENT_QUOTES, 'UTF-8'); ?>)' 
                                    class="w-full bg-[#1a3d32] text-white py-2 rounded hover:bg-black transition text-[10px] font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 <?php echo $outOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $outOfStock ? 'disabled' : ''; ?>>
                                <?php if ($outOfStock): ?>
                                    <span><?php echo strtoupper(get_stock_status_text($item['stock_status'] ?? 'in_stock', $item['stock_quantity'] ?? 0)); ?></span>
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart text-[9px]"></i>
                                    <span>ADD TO CART</span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>
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
const productTotalSales = <?php echo (int)($productData['total_sales'] ?? 0); ?>;
const currencySymbols = <?php 
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹']; 
    echo json_encode($symbols); 
?>;
const defaultMainImage = "<?php echo htmlspecialchars($mainImage); ?>";
let selectedOptions = <?php echo !empty($firstVariant['variant_attributes']) ? json_encode($firstVariant['variant_attributes']) : '{}'; ?>;
let currentMaxStock = <?php echo (int)($productData['stock_quantity'] ?? 0); ?>;

// Quantity Helper Functions
function updateProductQuantity(change) {
    const input = document.getElementById('productQuantity');
    const display = document.getElementById('productQuantityDisplay');
    const stickyQtyInput = document.getElementById('sticky-qty');
    const stickyQtyDisplay = document.getElementById('sticky-qty-display');
    
    let val = parseInt(input.value) + change;
    
    if (val > currentMaxStock) {
        if (typeof showNotification === 'function') {
            showNotification(`Only ${currentMaxStock} items available in stock`, 'info');
        }
        val = currentMaxStock;
    }
    
    if (val < 1) val = 1;
    
    // Update main
    input.value = val;
    if (display) display.textContent = val;
    
    // Sync with sticky bar
    if (stickyQtyInput) stickyQtyInput.value = val;
    if (stickyQtyDisplay) stickyQtyDisplay.textContent = val;
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

function selectVariantByImage(attributes, imageUrl, isVideo = false) {
    selectedOptions = {...attributes};
    
    // Check if video if boolean not passed strictly
    if (typeof isVideo !== 'boolean') {
         const ext = imageUrl.split('.').pop().toLowerCase();
         isVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(ext);
    }
    
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
    const mainVideo = document.getElementById('mainProductVideo');
    const stickyImg = document.querySelector('#sticky-bar img');
    
    const finalImageUrl = getImageUrlJS(imageUrl);
    
    if (isVideo) {
        if (mainImg) mainImg.classList.add('hidden');
        if (mainVideo) {
            mainVideo.src = finalImageUrl;
            mainVideo.classList.remove('hidden');
        }
    } else {
        if (mainVideo) {
            mainVideo.pause();
            mainVideo.classList.add('hidden');
        }
        if (mainImg) {
            mainImg.src = finalImageUrl;
            mainImg.classList.remove('hidden');
        }
        if (stickyImg) stickyImg.src = finalImageUrl;
    }
    
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
    const mainVideo = document.getElementById('mainProductVideo');
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
        
        if (stockElement) {
            const stock = parseInt(matchingVariant.stock_quantity);
            currentMaxStock = stock; // Update global stock limit
            const stockCountDisplay = document.getElementById('stock-count-display');
            
            if (matchingVariant.stock_status !== 'out_of_stock' && stock > 0) {
                stockElement.textContent = 'In Stock';
                stockElement.className = 'text-primary capitalize';
                if (stockCountDisplay) {
                    stockCountDisplay.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${stock} items available`;
                    stockCountDisplay.className = 'text-sm font-bold text-primary';
                }
                updateButtons(false);
                
                // Cap current quantity if it exceeds variant stock
                const qtyInput = document.getElementById('productQuantity');
                if (qtyInput && parseInt(qtyInput.value) > stock) {
                    updateProductQuantity(stock - parseInt(qtyInput.value));
                }
            } else {
                if (matchingVariant.stock_status === 'out_of_stock') {
                    stockElement.textContent = 'Out of Stock';
                    if (stockCountDisplay) {
                        stockCountDisplay.innerHTML = `<i class="fas fa-times-circle mr-1"></i> Out of Stock`;
                        stockCountDisplay.className = 'text-sm font-bold text-red-600';
                    }
                } else if (stock < 0) {
                    stockElement.textContent = 'In Stock'; // Or Backorder? 
                    if (stockCountDisplay) {
                        stockCountDisplay.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i> Backorder (${Math.abs(stock)} pending)`;
                        stockCountDisplay.className = 'text-sm font-bold text-orange-600';
                    }
                } else {
                    const label = (productTotalSales > 0) ? 'Sold Out' : 'Out of Stock';
                    stockElement.textContent = label;
                    if (stockCountDisplay) {
                        stockCountDisplay.innerHTML = `<i class="fas fa-times-circle mr-1"></i> ${label}`;
                        stockCountDisplay.className = 'text-sm font-bold text-red-600';
                    }
                }
                stockElement.className = 'text-red-600 capitalize';
                updateButtons(true, stockElement.textContent);
            }
        }
        
        // Update image/video if variant has one, otherwise revert to default
        const variantImg = matchingVariant.image ? getImageUrlJS(matchingVariant.image) : defaultMainImage;
        const ext = variantImg.split('.').pop().toLowerCase();
        const isVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(ext);

        if (isVideo) {
            if (mainImg) mainImg.classList.add('hidden');
            if (mainVideo) {
                mainVideo.src = variantImg;
                mainVideo.classList.remove('hidden');
            }
        } else {
            if (mainVideo) {
                mainVideo.pause();
                mainVideo.classList.add('hidden');
            }
            if (mainImg) {
                mainImg.src = variantImg;
                mainImg.classList.remove('hidden');
            }
            if (stickyImg) stickyImg.src = variantImg;
        }
        
    } else {
        // Reset to default
        if (skuElement) skuElement.textContent = productMainSku;
        if (priceElement) priceElement.textContent = formatPriceJS(productMainPrice, productCurrency);
        if (stickyPriceElement) stickyPriceElement.textContent = formatPriceJS(productMainPrice, productCurrency);
        if (originalPriceElement) originalPriceElement.classList.add('hidden'); 
        if (stickyOriginalPriceElement) stickyOriginalPriceElement.classList.add('hidden');
        
        if (stockElement) {
            stockElement.textContent = "<?php echo str_replace('_', ' ', $productData['stock_status'] ?? 'in_stock'); ?>";
            stockElement.className = 'text-gray-600 capitalize';
             
            const defaultStock = <?php echo $productData['stock_quantity'] ?? 0; ?>;
            const defaultStatus = "<?php echo $productData['stock_status'] ?? 'in_stock'; ?>";
            
            const stockCountDisplay = document.getElementById('stock-count-display');
            if (stockCountDisplay) {
                if (defaultStock > 0 && defaultStatus !== 'out_of_stock') {
                    stockCountDisplay.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${defaultStock} items available`;
                    stockCountDisplay.className = 'text-sm font-bold text-primary';
                } else if (defaultStock < 0) {
                    stockCountDisplay.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i> Backorder (${Math.abs(defaultStock)} pending)`;
                    stockCountDisplay.className = 'text-sm font-bold text-orange-600';
                } else {
                    stockCountDisplay.innerHTML = `<i class="fas fa-times-circle mr-1"></i> Out of Stock`;
                    stockCountDisplay.className = 'text-sm font-bold text-primary';
                }
            }

            updateButtons(defaultStatus === 'out_of_stock' || defaultStock <= 0);
        }
        
        const ext = defaultMainImage.split('.').pop().toLowerCase();
        const isVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(ext);

        if (isVideo) {
            if (mainImg) mainImg.classList.add('hidden');
            if (mainVideo) {
                mainVideo.src = defaultMainImage;
                mainVideo.classList.remove('hidden');
            }
        } else {
            if (mainVideo) {
                mainVideo.pause();
                mainVideo.classList.add('hidden');
            }
            if (mainImg) {
                mainImg.src = defaultMainImage;
                mainImg.classList.remove('hidden');
            }
            if (stickyImg) stickyImg.src = defaultMainImage;
        }
    }

    // --- Sync Thumbnail Borders ---
    const currentSrc = (mainImg && !mainImg.classList.contains('hidden')) ? mainImg.src : (mainVideo ? mainVideo.src : null);
    if (currentSrc) {
        let matchedAny = false;
        // Normalize for comparison (remove domain/protocol if needed or just use JS endsWith logic)
        // Simple exact match check first
        document.querySelectorAll('.thumbnail-img').forEach(el => {
             // For video thumbnails, el is the container div, but we can check if it has a video child or just rely on click setting active.
             // But here we are syncing state when variant changes.
             // Thumbnails in updated code are Divs for video, Imgs for image.
             
             let thumbSrc = "";
             const vid = el.querySelector('video');
             if (vid) thumbSrc = vid.src;
             else if (el.tagName === 'IMG') thumbSrc = el.src;
             
             // Compare
             if (thumbSrc === currentSrc) {
                 el.classList.remove('border-transparent');
                 el.classList.add('border-primary');
                 matchedAny = true;
             } else {
                 el.classList.remove('border-primary');
                 el.classList.add('border-transparent');
             }
        });
        
        if (!matchedAny) {
             const firstThumb = document.querySelector('.thumbnail-img');
             if (firstThumb) {
                 firstThumb.classList.remove('border-transparent');
                 firstThumb.classList.add('border-primary');
             }
        }
    }
}

function updateButtons(isOutOfStock, label) {
    const atcBtn = document.querySelector('.add-to-cart-btn');
    const buyNowBtn = document.querySelector('.buy-now-btn');
    const stickyAtcBtn = document.getElementById('sticky-atc-btn');

    if (isOutOfStock) {
        if(atcBtn) { 
            atcBtn.disabled = true; 
            atcBtn.classList.add('opacity-50', 'cursor-not-allowed'); 
            atcBtn.innerHTML = label || 'Out of Stock'; 
        }
        if(buyNowBtn) { 
            buyNowBtn.disabled = true; 
            buyNowBtn.classList.add('opacity-50', 'cursor-not-allowed'); 
        }
        if(stickyAtcBtn) { 
            stickyAtcBtn.disabled = true; 
            stickyAtcBtn.classList.add('opacity-50', 'cursor-not-allowed'); 
            stickyAtcBtn.innerHTML = `<span>${label || 'Out of Stock'}</span>`; 
        }
    } else {
        if(atcBtn) { 
            atcBtn.disabled = false; 
            atcBtn.classList.remove('opacity-50', 'cursor-not-allowed'); 
            atcBtn.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Add To Cart'; 
        }
        if(buyNowBtn) { 
            buyNowBtn.disabled = false; 
            buyNowBtn.classList.remove('opacity-50', 'cursor-not-allowed'); 
        }
        if(stickyAtcBtn) { 
            stickyAtcBtn.disabled = false; 
            stickyAtcBtn.classList.remove('opacity-50', 'cursor-not-allowed'); 
            stickyAtcBtn.innerHTML = '<i class="fas fa-shopping-cart text-xs md:text-sm"></i><span>Add To Cart</span>'; 
        }
    }
} 


function changeMainImage(imageUrl, button, variantData = null, isVideo = false) {
    const mainImg = document.getElementById('mainProductImage');
    const mainVideo = document.getElementById('mainProductVideo');
    const stickyImg = document.querySelector('#sticky-bar img');
    
    if (isVideo) {
        if (mainImg) mainImg.classList.add('hidden');
        if (mainVideo) {
            mainVideo.src = imageUrl;
            mainVideo.classList.remove('hidden');
            mainVideo.play().catch(e => console.log('Autoplay prevented:', e));
        }
    } else {
        if (mainVideo) {
            mainVideo.pause();
            mainVideo.classList.add('hidden');
        }
        if (mainImg) {
            mainImg.src = imageUrl;
            mainImg.classList.remove('hidden');
        }
        if (stickyImg) stickyImg.src = imageUrl;
    }
    
    // Update thumbnail borders
    document.querySelectorAll('.thumbnail-img').forEach(el => {
        el.classList.remove('border-primary');
        el.classList.add('border-transparent');
    });
    
    if (button) {
        button.classList.remove('border-transparent');
        button.classList.add('border-primary');
    }

    // If it comes with variant data (image belongs to a variant), select that variant
    if (variantData && typeof selectVariantByImage === 'function') {
        selectVariantByImage(variantData, imageUrl, isVideo);
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
                <button onclick="closeReviewModal()" class="text-gray-500 hover:text-gray-800" data-aria-label="Close review modal">
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
                btn.innerHTML = '<i class="fas fa-heart mr-1"></i>  Remove from Wishlist';
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
                 onerror="this.src='https://placehold.co/100x100?text=Product'">
            <div class="min-w-0">
                <h3 class="font-bold text-gray-900 leading-tight text-sm md:text-base overflow-hidden text-ellipsis" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; max-width: 450px;"><?php echo htmlspecialchars($productData['name']); ?></h3>
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
                     <div class="font-bold text-lg text-[#1a3d32]" id="sticky-price"><?php echo format_price($price, $productData['currency'] ?? 'USD'); ?></div>
                 </div>
            </div>
            
            <!-- Quantity - Balanced grid centering -->
            <div class="hidden md:flex items-center border border-gray-300 rounded-md w-28 h-10 overflow-hidden bg-white">
                <button onclick="updateStickyQty(-1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">-</button>
                <div class="flex-1 h-full grid place-items-center">
                    <span id="sticky-qty-display" class="text-gray-900 font-semibold text-base select-none">1</span>
                </div>
                <input type="hidden" id="sticky-qty" value="1">
                <button onclick="updateStickyQty(1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">+</button>
            </div>

            <button onclick="stickyAddToCart()" 
                    class="bg-[#1a3d32] text-white px-4 py-2.5 md:px-8 rounded-full font-bold hover:bg-black transition flex items-center justify-center gap-2 text-sm md:text-base whitespace-nowrap <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                    id="sticky-atc-btn"
                    <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                <?php if ($isOutOfStock): ?>
                    <span><?php echo $stockLabel; ?></span>
                <?php else: ?>
                    <i class="fas fa-shopping-cart text-xs md:text-sm"></i>
                    <span>Add To Cart</span>
                <?php endif; ?>
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
    const display = document.getElementById('sticky-qty-display');
    const mainQtyInput = document.getElementById('productQuantity');
    const mainQtyDisplay = document.getElementById('productQuantityDisplay');
    
    let val = parseInt(input.value) + change;
    
    if (val > currentMaxStock) {
        if (typeof showNotification === 'function') {
            showNotification(`Only ${currentMaxStock} items available in stock`, 'info');
        }
        val = currentMaxStock;
    }
    
    if (val < 1) val = 1;
    
    // Update sticky
    input.value = val;
    if (display) display.textContent = val;
    
    // Sync with main quantity if exists
    if (mainQtyInput) mainQtyInput.value = val;
    if (mainQtyDisplay) mainQtyDisplay.textContent = val;
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

<?php if (count($galleryItems ?? []) > 1): ?>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('.thumbnail-slider', {
        slidesPerView: 'auto',
        spaceBetween: 0,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        breakpoints: {
            640: {
                slidesPerView: 'auto',
            },
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

