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
if (!is_array($images)) $images = [];
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

// -------------------------------------------------------

// Get product categories (Source of Truth: products table category_id)
$productCategories = [];
$rawCatId = trim($productData['category_id'] ?? '');
$catIds = [];

if (!empty($rawCatId)) {
    $decoded = json_decode($rawCatId, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $catIds = array_map('strval', $decoded);
    } else {
        // Regex fallback: extract all numbers
        if (preg_match_all('/\d+/', $rawCatId, $matches)) {
            $catIds = $matches[0];
        } else {
            $catIds = [(string)$rawCatId];
        }
    }
}

if (!empty($catIds)) {
    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
    $productCategories = $db->fetchAll(
        "SELECT id, name, slug FROM categories WHERE id IN ($placeholders) AND status = 'active'",
        $catIds
    );
}

// Fallback to mapping table if products table was empty
if (empty($productCategories)) {
    try {
        $productCategories = $db->fetchAll(
            "SELECT c.id, c.name, c.slug 
             FROM categories c 
             INNER JOIN product_categories pc ON c.id = pc.category_id 
             WHERE pc.product_id = ? AND c.status = 'active'",
            [$productData['id']]
        );
    } catch (Exception $e) {}
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
// Prepare Offer Data
$offerSchema = [
    "@type" => "Offer",
    "url" => $baseUrl . '/product?slug=' . $productData['slug'],
    "priceCurrency" => "INR",
    "price" => $price,
    "availability" => ($productData['stock_status'] === 'instock' || ($productData['stock'] ?? 0) > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
    "itemCondition" => "https://schema.org/NewCondition"
];

// Add Price Specification if On Sale
if (!empty($originalPrice) && $originalPrice > $price) {
    $offerSchema['priceSpecification'] = [
        [
            "@type" => "UnitPriceSpecification",
            "price" => $price,
            "priceCurrency" => "INR",
            "priceType" => "https://schema.org/SalePrice"
        ],
        [
            "@type" => "UnitPriceSpecification",
            "price" => $originalPrice,
            "priceCurrency" => "INR",
            "priceType" => "https://schema.org/ListPrice"
        ]
    ];
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
    "offers" => $offerSchema
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

// Product Page Styles
$productStylesJson = $settings->get('product_page_styles', '[]');
$productStyles = json_decode($productStylesJson, true);

$p_page_bg = $productStyles['page_bg_color'] ?? '#ffffff';
$p_sale_color = $productStyles['sale_price_color'] ?? '#1a3d32';
$p_reg_color = $productStyles['reg_price_color'] ?? '#9ca3af';
$p_variant_bg = $productStyles['variant_bg_color'] ?? '#1a3d32';
$p_variant_text = $productStyles['variant_text_color'] ?? '#ffffff';
$p_atc_bg = $productStyles['atc_btn_color'] ?? '#000000';
$p_atc_text = $productStyles['atc_btn_text_color'] ?? '#ffffff';
$p_buy_bg = $productStyles['buy_now_btn_color'] ?? '#b91c1c';
$p_buy_text = $productStyles['buy_now_btn_text_color'] ?? '#ffffff';
$p_action_color = $productStyles['action_links_color'] ?? '#4b5563';
$p_info_bg = $productStyles['info_box_bg_color'] ?? '#f9fafb';
$p_info_text = $productStyles['info_box_text_color'] ?? '#374151';
$p_border_color = $productStyles['border_color'] ?? '#e5e7eb';
$p_divider_color = $productStyles['divider_color'] ?? '#e5e7eb';
$p_in_stock_color = $productStyles['in_stock_color'] ?? '#1a3d32';
$p_out_stock_color = $productStyles['out_stock_color'] ?? '#b91c1c';

$p_atc_hover_bg = $productStyles['atc_hover_bg_color'] ?? '#000000';
$p_atc_hover_text = $productStyles['atc_hover_text_color'] ?? '#ffffff';
$p_buy_hover_bg = $productStyles['buy_now_hover_bg_color'] ?? '#991b1b';
$p_buy_hover_text = $productStyles['buy_now_hover_text_color'] ?? '#ffffff';
?>

<style>
    #product-main-section {
        background-color: <?php echo $p_page_bg; ?> !important;
    }
    
    /* Price Styles */
    .product-sale-price, #sticky-price { color: <?php echo $p_sale_color; ?> !important; }
    .product-reg-price, #sticky-original-price { color: <?php echo $p_reg_color; ?> !important; }
    
    /* Button Styles */
    #productCartAddToCartBtn, #sticky-atc-btn {
        background-color: <?php echo $p_atc_bg; ?> !important;
        color: <?php echo $p_atc_text; ?> !important;
    }
    #productCartBuyNowBtn {
        background-color: <?php echo $p_buy_bg; ?> !important;
        color: <?php echo $p_buy_text; ?> !important;
    }
    
    /* Hover Overrides */
    #productCartAddToCartBtn:hover, #sticky-atc-btn:hover {
        background-color: <?php echo $p_atc_hover_bg; ?> !important;
        color: <?php echo $p_atc_hover_text; ?> !important;
    }
    #productCartBuyNowBtn:hover {
        background-color: <?php echo $p_buy_hover_bg; ?> !important;
        color: <?php echo $p_buy_hover_text; ?> !important;
    }
    
    /* Variant Styles */
    .variant-option-btn.active {
        background-color: <?php echo $p_variant_bg; ?> !important;
        color: <?php echo $p_variant_text; ?> !important;
        border-color: <?php echo $p_variant_bg; ?> !important;
    }
    
    /* Action Links */
    .action-link-item, .action-link-item button, .action-link-item a {
        color: <?php echo $p_action_color; ?> !important;
    }
    
    /* Style for wishlist and other action buttons hover */
    #product-main-section .product-action-btn:hover {
        border-color: <?php echo $p_hover_bg; ?> !important;
        color: <?php echo $p_hover_bg; ?> !important;
    }
    
    /* Info Box */
    .product-info-box {
        background-color: <?php echo $p_info_bg; ?> !important;
        color: <?php echo $p_info_text; ?> !important;
    }

    /* Dividers & Borders */
    #product-main-section .border-t, 
    #product-main-section .border-b,
    #product-main-section .divide-y > * {
        border-color: <?php echo $p_divider_color; ?> !important;
    }
    
    .product-border-item,
    #product-main-section .variant-btn,
    #product-main-section .quantity-selector,
    #product-main-section select,
    #product-main-section input:not([type="hidden"]),
    #product-main-section textarea,
    #product-main-section .border {
        border-color: <?php echo $p_border_color; ?> !important;
    }

    #product-main-section .border-2 {
        border-color: <?php echo $p_border_color; ?> !important;
    }

    /* Stock Colors */
    .product-in-stock { color: <?php echo $p_in_stock_color; ?> !important; }
    .product-out-stock { color: <?php echo $p_out_stock_color; ?> !important; }

    /* Sticky Bar Background */
    #sticky-bar {
        background-color: <?php echo $p_page_bg; ?> !important;
    }
</style>

<section id="product-main-section" class=" md:py-12">
    <div class="container mx-auto px-4">
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav text-sm mb-6 pt-3 mt-5">
            <a href="<?php echo $baseUrl; ?>/">Home</a>
            <span>></span>
            <?php if ($primaryCategory): ?>
            <a href="<?php echo $baseUrl; ?>/shop?category=<?php echo urlencode($primaryCategory['slug']); ?>">
                <?php echo htmlspecialchars($primaryCategory['name']); ?>
            </a>
            <span>></span>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></span>
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
                <h1 class="text-2xl md:text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($productData['name'] ?? 'Product'); ?></h1>
                
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
                    <span id="original-price" class="product-reg-price compare-price text-2xl line-through mr-2 <?php echo !$originalPrice ? 'hidden' : ''; ?>"><?php echo format_price($originalPrice ?: 0, $productData['currency'] ?? 'USD'); ?></span>
                    <span id="product-price" class="product-sale-price product-price text-2xl font-bold"><?php echo format_price($price, $productData['currency'] ?? 'USD'); ?></span>
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
                        <span><?php echo trim(strip_tags(html_entity_decode($h['text'] ?? '', ENT_QUOTES | ENT_HTML5))); ?></span>
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
                                        class="variant-btn variant-option-btn px-4 py-2 border-2 rounded transition border-gray-300 hover:border-primary">
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
                                    class="variant-option-btn active px-6 py-2 border-2 rounded border-primary bg-primary text-white">
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
                    <div class="flex items-center border border-gray-300 rounded-md w-28 h-10 overflow-hidden bg-white quantity-selector">
                        <button onclick="updateProductQuantity(-1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">-</button>
                        <div class="flex-1 h-full grid place-items-center">
                            <span id="productQuantityDisplay" class="text-gray-900 font-bold text-base select-none"><?php echo $isOutOfStock ? '0' : '1'; ?></span>
                        </div>
                        <input type="hidden" id="productQuantity" value="<?php echo $isOutOfStock ? '0' : '1'; ?>">
                        <button onclick="updateProductQuantity(1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">+</button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 mb-2">
                    <button onclick="addToCartFromDetail(<?php echo $productData['product_id']; ?>, this)" 
                            id="productCartAddToCartBtn"
                            class="flex-1 bg-black text-white py-4 px-6 hover:bg-gray-800 transition font-semibold flex items-center justify-center add-to-cart-btn <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            data-loading-text="Adding..."
                            data-product-id="<?php echo $productData['product_id']; ?>"
                            data-product-name="<?php echo htmlspecialchars($productData['name'] ?? ''); ?>"
                            data-product-price="<?php echo $price; ?>"
                            data-product-slug="<?php echo htmlspecialchars($productData['slug'] ?? ''); ?>"
                            <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        <?php if ($isOutOfStock): ?>
                            <?php echo $stockLabel; ?>
                        <?php else: ?>
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Add To Cart
                        <?php endif; ?>
                    </button>
                    <button onclick="buyNow(<?php echo $productData['product_id']; ?>, this)" 
                            id="productCartBuyNowBtn"
                            class="flex-1 bg-red-700 text-white py-4 px-6 hover:bg-red-600 transition font-semibold buy-now-btn <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            data-loading-text="Processing..."
                            data-product-id="<?php echo $productData['product_id']; ?>"
                            data-product-name="<?php echo htmlspecialchars($productData['name'] ?? ''); ?>"
                            data-product-price="<?php echo $price; ?>"
                            data-product-slug="<?php echo htmlspecialchars($productData['slug'] ?? ''); ?>"
                            <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        Buy It Now
                    </button>
                </div>
                
                <!-- Availability Status (Dynamic) -->
                <div class="mb-6 flex items-center space-x-2">
                    <span id="stock-count-display" class="text-sm font-bold <?php echo ($currentStatus === 'in_stock' && $currentStock > 0) ? 'product-in-stock' : (($currentStatus === 'in_stock' && $currentStock < 0) ? 'text-orange-600' : 'product-out-stock'); ?>">
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
                    <div class="action-link-item">
                        <button onclick="toggleProductWishlist(<?php echo $productData['product_id'] ?? $productData['id']; ?>, this)" class="hover:text-primary transition flex items-center wishlist-btn" data-product-id="<?php echo $productData['product_id'] ?? $productData['id']; ?>">
                            <?php if (in_array($productData['product_id'] ?? $productData['id'], $wishlistIds)): ?>
                                <i class="fas fa-heart mr-1"></i> Remove from Wishlist
                            <?php else: ?>
                                <i class="far fa-heart mr-1"></i> Add to Wishlist
                            <?php endif; ?>
                        </button>
                    </div>
                    <div class="action-link-item">
                        <a href="javascript:void(0)" onclick="toggleAskQuestionModal(true, '<?php echo addslashes($productData['name']); ?>')" class="hover:text-primary transition flex items-center font-medium"><i class="fas fa-question-circle mr-1 text-primary"></i>Ask a question</a>
                    </div>
                    <div class="action-link-item">
                        <button onclick="sharePage('<?php echo addslashes($productData['name']); ?>', 'Check out this product!', window.location.href)" class="hover:text-primary transition flex items-center">
                            <i class="fas fa-share-alt mr-1"></i>Share
                        </button>
                    </div>
                </div>
                

                <!-- Pickup Information -->
                <?php if ($settings->get('pickup_enable', '1') == '1'): ?>
                <div class="product-info-box p-4 rounded-lg flex">
                    <div class="text-sm flex items-start">
                        <i class="<?php echo htmlspecialchars($settings->get('pickup_icon', 'fas fa-store')); ?> mr-2 text-primary mt-1 flex-shrink-0"></i>
                        <div class="prose prose-sm max-w-none" style="color: inherit;">
                            <?php echo $settings->get('pickup_message', 'Pickup available at Shop location. Usually ready in 24 hours'); ?>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($settings->get('pickup_link_url', '#')); ?>" class="text-sm text-primary hover:underline mt-1 inline-block" style="color: inherit; opacity: 0.8;">
                        <?php echo htmlspecialchars($settings->get('pickup_link_text', 'View store information')); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Product Details -->
                <div class="border-t pt-4 space-y-2 text-sm">
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-20">Sku:</span>
                        <span id="variant-sku" class="text-gray-600"><?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-20">Available:</span>
                        <span id="variant-stock-status" class="text-gray-600 capitalize"><?php echo $stockLabel; ?></span>
                    </div>
                    <!-- <div class="flex">
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
                    </div> -->
                    <div class="flex">
                        <span class="font-semibold text-gray-700 w-20">Brand:</span>
                        <span class="text-gray-600"><?php echo htmlspecialchars($productData['brand'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <!-- Guarantee -->
                <div class="mt-6 pt-4 border-t">
                    <p class="text-sm text-gray-600 text-right">Guarantee Safe Checkout</p>
                    <div class="flex justify-end items-center flex-wrap gap-1 mt-2">
                        <?php 
                        $checkoutPaymentIconsJson = $settings->get('checkout_payment_icons_json', '[]');
                        $checkoutPaymentIcons = json_decode($checkoutPaymentIconsJson, true) ?: [];
                        
                        if (!empty($checkoutPaymentIcons)): 
                            foreach ($checkoutPaymentIcons as $icon): ?>
                                <div class="h-8 flex items-center justify-center" title="<?php echo htmlspecialchars($icon['name'] ?? ''); ?>" style="max-width: 60px;">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <?php echo $icon['svg'] ?? ''; ?>
                                    </div>
                                </div>
                            <?php endforeach; 
                        else: ?>
                            <!-- Fallback if no icons configured -->
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Visa_Inc._logo.svg.png" alt="Visa" class="h-8 w-8 object-contain">
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/Mastercard-logo.svg.png" alt="Mastercard" class="h-8 w-8 object-contain">
                            <img src="<?php echo $baseUrl; ?>/assets/images/checkout-image/American_Express_logo.svg.png" alt="American Express" class="h-8 w-8 object-contain">
                        <?php endif; ?>
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
                        <i class="fas fa-plus text-gray-400 transition-transform duration-300" id="description-icon"></i>
                    </button>
                    <div id="description-content" class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out text-gray-700 text-sm">
                        <div class="pb-4">
                            <div class="prose prose-sm max-w-none">
                                <?php echo htmlspecialchars_decode($productData['description'] ?? 'No description available.'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Policy -->
                <div class="border-b">
                    <button onclick="toggleSection('shipping')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Shipping and Returns</span>
                        <i class="fas fa-plus text-gray-400 transition-transform duration-300" id="shipping-icon"></i>
                    </button>
                    <div id="shipping-content" class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out text-gray-700 text-sm">
                        <div class="pb-4">
                            <div class="prose prose-sm max-w-none text-[15px]">
                                <?php 
                                $shippingPolicy = $productData['shipping_policy'] ?? '';
                                if (empty($shippingPolicy)) {
                                    $shippingPolicy = $settings->get('default_shipping_policy', '');
                                }
                                if (empty($shippingPolicy)) {
                                    $shippingPolicy = '<p>We offer free shipping on all orders over ' . format_price(150, $productData['currency'] ?? 'USD') . '. Standard shipping takes 3-5 business days. International shipping may take 7-14 business days.</p><p class="mt-2">Returns are accepted within 30 days of purchase. Items must be unworn and in original packaging.</p>';
                                }
                                echo $shippingPolicy;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Return Policies -->
                <div class="border-b">
                    <button onclick="toggleSection('returns')" class="w-full flex items-center justify-between py-4 text-left">
                        <span class="font-semibold text-lg">Return Policies</span>
                        <i class="fas fa-plus text-gray-400 transition-transform duration-300" id="returns-icon"></i>
                    </button>
                    <div id="returns-content" class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out text-gray-700 text-sm">
                        <div class="pb-4">
                            <div class="prose prose-sm max-w-none text-[15px]">
                                <?php 
                                $returnPolicy = $productData['return_policy'] ?? '';
                                if (empty($returnPolicy)) {
                                    $returnPolicy = $settings->get('default_return_policy', '');
                                }
                                if (empty($returnPolicy)) {
                                    $returnPolicy = '<p>We accept returns within 30 days of purchase. Items must be in original condition with tags attached.</p><p class="mt-2">To initiate a return, please contact our customer service team or visit your account page.</p>';
                                }
                                echo $returnPolicy;
                                ?>
                            </div>
                        </div>
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
            
            <button onclick="openReviewModal()" class="product-border-item bg-white border-2 px-6 py-2 rounded-lg hover:border-primary transition mb-6 text-sm">
                Write A Review
            </button>
            
            <!-- Reviews List -->
            <div class="space-y-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Most Recent</h3>
                    <select class="product-border-item border rounded px-3 py-2 text-sm" id="reviewSort" onchange="loadReviews()">
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
        
        <!-- People Also Bought Skeleton -->
        <div id="related-products-section" class="section-loading mb-5" data-product-id="<?php echo $productData['id']; ?>">
            <div class="text-center mb-8">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-2 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                <div class="h-4 bg-gray-200 rounded max-w-xl mx-auto relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 px-4 md:px-12">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="bg-white rounded-lg overflow-hidden shadow-md p-4 space-y-4">
                    <div class="w-full h-64 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-3 bg-gray-200 rounded w-1/4 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-10 bg-gray-200 rounded w-full relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Recently Viewed Skeleton -->
        <div id="recently-viewed-section" class="section-loading mb-16 mt-[25px] md:mt-[60px]" data-product-id="<?php echo $productData['id']; ?>">
            <div class="text-center mb-8">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-2 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                <div class="h-4 bg-gray-200 rounded max-w-2xl mx-auto relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 px-4 md:px-12">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="bg-white rounded-lg overflow-hidden shadow-md p-4 space-y-4">
                    <div class="w-full h-64 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-3 bg-gray-200 rounded w-1/4 relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="h-10 bg-gray-200 rounded w-full relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
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
    
    if (currentMaxStock > 0) {
        if (val > currentMaxStock) {
            if (typeof showNotification === 'function') {
                showNotification(`Only ${currentMaxStock} items available in stock`, 'info');
            }
            val = currentMaxStock;
        }
        if (val < 1) val = 1;
    } else {
        val = 0;
    }
    
    // Update main
    if (input) input.value = val;
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
                stockElement.className = 'product-in-stock capitalize';
                if (stockCountDisplay) {
                    stockCountDisplay.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${stock} items available`;
                    stockCountDisplay.className = 'text-sm font-bold product-in-stock';
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
                        stockCountDisplay.className = 'text-sm font-bold product-out-stock';
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
                        stockCountDisplay.className = 'text-sm font-bold product-out-stock';
                    }
                }
                stockElement.className = 'product-out-stock capitalize';
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
                    stockCountDisplay.className = 'text-sm font-bold product-in-stock';
                } else if (defaultStock < 0) {
                    stockCountDisplay.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i> Backorder (${Math.abs(defaultStock)} pending)`;
                    stockCountDisplay.className = 'text-sm font-bold text-orange-600';
                } else {
                    stockCountDisplay.innerHTML = `<i class="fas fa-times-circle mr-1"></i> Out of Stock`;
                    stockCountDisplay.className = 'text-sm font-bold product-out-stock';
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
    
    // Check if the max-height is set (meaning it's open) and not '0px'
    if (content.style.maxHeight && content.style.maxHeight !== '0px') {
        content.style.maxHeight = '0px';
        icon.classList.remove('fa-minus', 'rotate-180');
        icon.classList.add('fa-plus');
    } else {
        // Set max-height to scrollHeight to open it
        content.style.maxHeight = content.scrollHeight + "px";
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-minus', 'rotate-180');
    }
}

function addToCartFromDetail(productId, btn) {
    const quantity = parseInt(document.getElementById('productQuantity').value) || 1;
    
    if (btn) setBtnLoading(btn, true);
    
    // Use global addToCart function if available
    if (typeof addToCart === 'function') {
        return addToCart(productId, quantity, btn, selectedOptions);
    } else {
        // Fallback to direct API call
        return fetch('<?php echo $baseUrl; ?>/api/cart.php', {
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
                return { success: true };
            } else {
                if (typeof showNotificationModal === 'function') {
                    showNotificationModal(data.message || 'Failed to add product to cart', 'error');
                } else if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Failed to add product to cart', 'error');
                } else {
                    // console.log(data.message || 'Failed to add product to cart');
                }
                return { success: false, message: data.message };
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('An error occurred while adding the product to cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification('An error occurred while adding the product to cart', 'error');
            } else {
                // console.log('An error occurred while adding the product to cart');
            }
            return { success: false, error: error };
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
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to add product to cart', 'error');
            } else {
                alert(data.message || 'Failed to add product to cart');
            }
            if (btn) setBtnLoading(btn, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        } else {
            alert('An error occurred. Please try again.');
        }
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
            <div class="hidden md:flex items-center border border-gray-300 rounded-md w-28 h-10 overflow-hidden bg-white quantity-selector">
                <button onclick="updateStickyQty(-1)" class="w-10 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:text-black hover:bg-gray-100 transition select-none">-</button>
                <div class="flex-1 h-full grid place-items-center">
                    <span id="sticky-qty-display" class="text-gray-900 font-semibold text-base select-none"><?php echo $isOutOfStock ? '0' : '1'; ?></span>
                </div>
                <input type="hidden" id="sticky-qty" value="<?php echo $isOutOfStock ? '0' : '1'; ?>">
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
    
    if (currentMaxStock > 0) {
        if (val > currentMaxStock) {
            if (typeof showNotification === 'function') {
                showNotification(`Only ${currentMaxStock} items available in stock`, 'info');
            }
            val = currentMaxStock;
        }
        if (val < 1) val = 1;
    } else {
        val = 0;
    }
    
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
            const result = addToCartFromDetail(productId, null); 
            
            // Handle promise if returned
            if (result && typeof result.then === 'function') {
                result.finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            } else {
                // Fallback for sync or no-return
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 1000);
            }
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

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<?php if (count($galleryItems ?? []) > 1): ?>
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

<script src="<?php echo $baseUrl; ?>/assets/js/lazy-load17.js?v=4" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

