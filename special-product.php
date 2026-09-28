<?php
// Main landing page loader

require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
$settingsObj = new Settings();
$productObj = new Product();

// Get base URL
$baseUrl = getBaseUrl();

// 1. Determine Page (by slug or ID)
$pageSlug = $_GET['page'] ?? '';

// 2. Fetch Landing Page Config (Store Specific)
$storeId = getCurrentStoreId();
$landingPage = $db->fetchOne("SELECT * FROM landing_pages WHERE slug = ? AND (store_id = ? OR store_id IS NULL)", [$pageSlug, $storeId]);

if (!$landingPage) {
    // We treat 'page' param as 'slug' for the custom page system (Store Specific)
    $customPage = $db->fetchOne("SELECT id FROM pages WHERE slug = ? AND status = 'active' AND (store_id = ? OR store_id IS NULL)", [$pageSlug, $storeId]);
    
    if ($customPage) {
        // It's a custom page, handover to page.php
        $_GET['slug'] = $pageSlug; // Map 'page' to 'slug'
        include __DIR__ . '/page.php';
        exit;
    }

    // If a specific slug was requested but not found -> 404
    if (!empty($pageSlug)) {
        header("HTTP/1.0 404 Not Found");
        header("Location: " . $baseUrl . "/not-found");
        exit;
    }

    $landingPage = $db->fetchOne("SELECT * FROM landing_pages WHERE (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC, id ASC LIMIT 1", [$storeId]);
    if (!$landingPage) die("Landing page not found.");
}

// Grouped Data Decoders (10 Distinct Sections)
$headerGrp = json_decode($landingPage['header_data'] ?? '{}', true) ?: [];
$heroGrp = json_decode($landingPage['hero_data'] ?? '{}', true) ?: [];
$bannerGrp = json_decode($landingPage['banner_data'] ?? '{}', true) ?: [];
$statsGrp = json_decode($landingPage['stats_data'] ?? '{}', true) ?: [];
$whyGrp = json_decode($landingPage['why_data'] ?? '{}', true) ?: [];
$aboutGrp = json_decode($landingPage['about_data'] ?? '{}', true) ?: [];
$testiGrp = json_decode($landingPage['testimonials_data'] ?? '{}', true) ?: [];
$newsGrp = json_decode($landingPage['newsletter_data'] ?? '{}', true) ?: [];
$platformsGrp = json_decode($landingPage['platforms_data'] ?? '{}', true) ?: [];
$footerGrp = json_decode($landingPage['footer_data'] ?? '{}', true) ?: [];
$configGrp = json_decode($landingPage['page_config'] ?? '{}', true) ?: [];
$seoGrp = json_decode($landingPage['seo_data'] ?? '{}', true) ?: [];

// 3. Fetch Product Data
$productData = $productObj->getByProductId($landingPage['product_id']);
if (!$productData || $productData['status'] !== 'active') die("The featured product is currently unavailable.");

// Fetch Platform Data early for Hero use
$platformItems = $platformsGrp['items'] ?? [];
$showPlat = $platformsGrp['show'] ?? 1;

// 3.1 Fetch Variants & Pick First if available
$variantsData = $productObj->getVariants($landingPage['product_id']);
$firstVariant = !empty($variantsData['variants']) ? $variantsData['variants'][0] : null;

// Style Defaults
$themeColor = $configGrp['theme_color'] ?? '#5F8D76';
$bodyBg = $configGrp['body_bg_color'] ?? '#ffffff';
$bodyText = $configGrp['body_text_color'] ?? '#000000';

// Hero Section Content
$heroTitle = !empty($heroGrp['title']) ? $heroGrp['title'] : ($productData['name'] ?? '');
$heroSubtitle = $heroGrp['subtitle'] ?? '';
$heroDescription = !empty($heroGrp['description']) ? $heroGrp['description'] : ($productData['description'] ?? '');
$mainImage = !empty($heroGrp['image']) ? getImageUrl($heroGrp['image']) : getProductImage($productData);

// Prepare Gallery
$images = json_decode($productData['images'] ?? '[]', true);

// Use Variant Price if available, otherwise Product Price
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;
$originalPrice = $productData['price'] ?? 0;
$currentSalePrice = $productData['sale_price'] ?? 0;

if ($firstVariant) {
    if ($firstVariant['price'] > 0) {
        $originalPrice = $firstVariant['price'];
    }
    if ($firstVariant['sale_price'] > 0) {
        $currentSalePrice = $firstVariant['sale_price'];
    } else {
        $currentSalePrice = 0; // No sale on variant
    }
    $price = $currentSalePrice > 0 ? $currentSalePrice : $originalPrice;
    
    // Override main image if variant has one
    if (!empty($firstVariant['image'])) {
        $mainImage = getImageUrl($firstVariant['image']);
    }
}

$hasSale = $currentSalePrice > 0 && $currentSalePrice < $originalPrice;
$isOutOfStock = ($productData['stock_status'] === 'out_of_stock' || (isset($productData['stock_quantity']) && $productData['stock_quantity'] <= 0));

$galleryPool = [];
if (!empty($mainImage)) $galleryPool[] = $mainImage;
if (is_array($images)) {
    foreach($images as $img) {
        $fullPath = getImageUrl($img);
        $filename = basename($img);
        $isDuplicate = false;
        foreach ($galleryPool as $existing) {
            if (strpos($existing, $filename) !== false) { $isDuplicate = true; break; }
        }
        if (!$isDuplicate) $galleryPool[] = $fullPath;
    }
}
$galleryPool = array_values(array_unique($galleryPool));
$galleryCount = count($galleryPool);
$currentImgIdx = 1; 

// Helper to determine contrast color (Black or White)
function getContrastColor($hexColor) {
    if (!$hexColor) return '#000000';
    $hexColor = str_replace('#', '', $hexColor);
    if(strlen($hexColor) == 3) {
        $r = hexdec(str_repeat(substr($hexColor, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hexColor, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hexColor, 2, 1), 2));
    } else if(strlen($hexColor) == 6) {
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
    } else { return '#000000'; }
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 150 ? '#000000' : '#ffffff';
}
// 4. Resolve Branding & Styles
$themeColor = ($configGrp['theme_color'] ?? '') ?: '#5F8D76';
$bodyBg = ($configGrp['body_bg_color'] ?? '') ?: '#ffffff';
$bodyText = ($configGrp['body_text_color'] ?? '') ?: '#000000';

$heroBg = $heroGrp['bg_color'] ?? '#ffffff';
$heroText = $heroGrp['text_color'] ?? getContrastColor($heroBg);

// 5. Header Links (Decoded from headerGrp)
$navLinks = $headerGrp['nav_links'] ?? [
    ['label' => 'Home', 'url' => '#'],
    ['label' => 'Shop', 'url' => '#'],
    ['label' => 'About', 'url' => '#'],
    ['label' => 'Contact', 'url' => '#']
];

// 6. SEO & Meta (Passed to header.php)
$pageTitle = $seoGrp['meta_title'] ?? $heroTitle;
$metaDescription = $seoGrp['meta_description'] ?? '';
$customSchema = $seoGrp['custom_schema'] ?? '';

if (!empty($customSchema)) {
    $replacements = [
        '{{description}}' => preg_replace('/\s+/', ' ', strip_tags($heroDescription)),
        '{{url}}' => $baseUrl . '/' . $pageSlug,
        '{{image}}' => $mainImage,
        '{{currency}}' => $productData['currency'] ?? 'USD',
        '{{brand}}' => $settingsObj->get('site_name', 'CookPro'),
        '{{price_valid_until}}' => date('Y-m-d', strtotime('+1 year'))
    ];
    foreach ($productData as $key => $value) {
        if (is_scalar($value)) $replacements['{{' . $key . '}}'] = $value;
    }
    foreach ($replacements as $key => $val) {
        $customSchema = str_replace($key, $val, $customSchema);
    }
} elseif ($productData) {
    // Basic Auto Schema
    $reviewStats = $db->fetchOne("SELECT COUNT(*) as count, AVG(rating) as avg_rating FROM reviews WHERE product_id = ? AND status = 'approved'", [$productData['id']]);
    $reviewCount = $reviewStats['count'] ?? 0;
    $avgRating = $reviewStats['avg_rating'] ?? 0;

    $productSchema = [
        "@context" => "https://schema.org",
        "@type" => "Product",
        "name" => $productData['name'],
        "description" => $metaDescription ?: strip_tags($heroDescription),
        "url" => $baseUrl . '/' . $pageSlug,
        "image" => [$mainImage],
        "brand" => ["@type" => "Brand", "name" => "CookPro"],
        "offers" => [
            "@type" => "Offer",
            "price" => (float)$price,
            "priceCurrency" => $productData['currency'] ?? 'USD',
            "availability" => (isset($productData['quantity']) && $productData['quantity'] <= 0) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            "url" => $baseUrl . '/' . $pageSlug,
            "priceValidUntil" => date('Y-m-d', strtotime('+1 year'))
        ]
    ];
    if ($reviewCount > 0) {
        $productSchema['aggregateRating'] = ["@type" => "AggregateRating", "ratingValue" => $avgRating, "reviewCount" => $reviewCount, "bestRating" => "5", "worstRating" => "1"];
    }
    $customSchema = json_encode($productSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// 7. Header Injection
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styles for Landing Page (Injected after body start) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap">
</noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<script>
    const LANDING_BASE_URL = '<?php echo $baseUrl; ?>';
    let currentMaxStock = <?php echo (int)($productData['stock_quantity'] ?? 0); ?>;
    
    // Custom Add To Cart for Landing Page
    function spAddToCart(productId, btn, attributes = {}, qty = 1) {
        if (btn) setBtnLoading(btn, true);
        fetch(`${LANDING_BASE_URL}/api/cart.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty, variant_attributes: attributes })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Update cart count if element exists (handled by header usually, but we force update)
                const countEls = document.querySelectorAll('.cart-count');
                countEls.forEach(el => el.textContent = data.count || data.cartCount);
                
                // Show site's cart drawer if available
                if (typeof openSideCart === 'function') {
                    openSideCart(data.cart);
                } else {
                    const cartBtn = document.getElementById('cartBtn');
                    if(cartBtn) cartBtn.click();
                    // Fallback to notification if cart button missing
                    else if(typeof showNotification === 'function') showNotification('Product added to cart!', 'success');
                }
            }
        })
        .catch(err => console.error(err))
        .finally(() => {
            if (btn) setBtnLoading(btn, false);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Read More Toggle for Hero Description (Line Based)
        const setupReadMore = (containerId, btnId, fadeId) => {
            const container = document.getElementById(containerId);
            const btn = document.getElementById(btnId);
            const fade = document.getElementById(fadeId);
            
            if (container && btn) {
                // Check if content overflows (clamped)
                // We assume the container starts with the line-clamp class applied.
                const isClamped = container.scrollHeight > container.clientHeight;
                
                if (!isClamped) {
                    btn.style.display = 'none';
                    if(fade) fade.style.display = 'none';
                }
                
                btn.addEventListener('click', function() {
                    const isExpanded = !container.classList.contains('line-clamp-5');
                    
                    if (isExpanded) {
                        // Collapse
                        container.classList.add('line-clamp-5');
                        if(fade) fade.style.opacity = '1';
                        btn.querySelector('span').textContent = 'Read More';
                        btn.querySelector('i').style.transform = 'rotate(0deg)';
                    } else {
                        // Expand
                        container.classList.remove('line-clamp-5');
                        if(fade) fade.style.opacity = '0';
                        btn.querySelector('span').textContent = 'Show Less';
                        btn.querySelector('i').style.transform = 'rotate(180deg)';
                    }
                });
            }
        };

        // Initialize (timeout ensures styles are applied for height calc)
        setTimeout(() => {
            setupReadMore('hero-description-container', 'hero-read-more-btn', 'hero-description-fade');
        }, 100);

        // Skeleton Loader for all images
        document.querySelectorAll('img').forEach(img => {
            if (!img.complete) {
                img.classList.add('skeleton-loading');
                img.onload = function() { this.classList.remove('skeleton-loading'); };
                img.onerror = function() { this.classList.remove('skeleton-loading'); };
            }
        });
    });
</script>

<style>
    /* Override font-family for this page if needed, or keep inherited */
    body { font-family: 'Outfit', sans-serif; background-color: var(--body-bg); color: var(--body-text); }
    footer, footer.bg-white { background-color: var(--body-bg) !important; color: var(--body-text) !important; }
    h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }

    /* Dynamic Theme Colors */
    :root {
        --theme-color: <?php echo htmlspecialchars($themeColor); ?>;
        --body-bg: <?php echo htmlspecialchars($bodyBg); ?>;
        --body-text: <?php echo htmlspecialchars($bodyText); ?>;

        --hero-bg: <?php echo htmlspecialchars(($heroGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --hero-text: <?php echo htmlspecialchars(($heroGrp['text_color'] ?? '') ?: '#111827'); ?>;

        --banner-bg: <?php echo htmlspecialchars(($bannerGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --banner-text: <?php echo htmlspecialchars(($bannerGrp['text_color'] ?? '') ?: '#000000'); ?>;
        
        --stats-bg: <?php echo htmlspecialchars(($statsGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --stats-text: <?php echo htmlspecialchars(($statsGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --why-bg: <?php echo htmlspecialchars(($whyGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --why-text: <?php echo htmlspecialchars(($whyGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --about-bg: <?php echo htmlspecialchars(($aboutGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --about-text: <?php echo htmlspecialchars(($aboutGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --testi-bg: <?php echo htmlspecialchars(($testiGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --testi-text: <?php echo htmlspecialchars(($testiGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --news-bg: <?php echo htmlspecialchars(($newsGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --news-text: <?php echo htmlspecialchars(($newsGrp['text_color'] ?? '') ?: '#111827'); ?>;
    }

    /* Section Colors */
    .hero-section { background-color: var(--hero-bg); color: var(--hero-text); }
    .banner-section { background-color: var(--banner-bg); color: var(--banner-text); }
    .stats-section { background-color: var(--stats-bg); color: var(--stats-text); }
    .why-section { background-color: var(--why-bg); color: var(--why-text); }
    .about-section { background-color: var(--about-bg); color: var(--about-text); }
    .testi-section { background-color: var(--testi-bg); color: var(--testi-text); }
    .news-section { background-color: var(--news-bg); color: var(--news-text); }

    .text-theme { color: var(--theme-color); }
    .bg-theme { background-color: var(--theme-color); }
    .btn-accent {
        background-color: var(--theme-color);
        color: white;
    }
    .btn-accent:hover {
        transform: translateY(-2px);
    }

    /* Hero Animations */
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
        100% { transform: translateY(0px); }
    }
    .product-image-animate {
        animation: float 6s ease-in-out infinite;
    }

    /* Skeleton Loader */
    .skeleton-loading {
        background-color: #f3f4f6;
        background-image: linear-gradient(90deg, #f3f4f6 0px, #e5e7eb 40px, #f3f4f6 80px);
        background-size: 300px 100%; 
        animation: skeleton-shimmer 1.5s infinite linear;
    }
    .skeleton-loading.w-full {
        min-height: 250px;
    }
    @media (max-width: 768px) {
        .skeleton-loading.w-full { min-height: 200px; }
    }
    @keyframes skeleton-shimmer {
        0% { background-position: -300px 0; }
        100% { background-position: 300px 0; }
    }
    .line-clamp-5 {
        display: -webkit-box;
        -webkit-line-clamp: 5;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .why-section h2, .why-section h2::after, .why-section h2::before,
    .about-section h2, .about-section h2::after, .about-section h2::before {
        text-decoration: none !important;
        border-bottom: none !important;
        content: none !important;
    }
    .testimonialSwiper {
        padding-bottom: 3rem !important;
    }
    .testimonialSwiper .swiper-pagination {
        bottom: 0 !important;
    }
    .testimonialSwiper .swiper-pagination-bullet {
        background: #045d36 !important;
        opacity: 0.25 !important;
        width: 8px !important;
        height: 8px !important;
        transition: all 0.3s ease !important;
    }
    .testimonialSwiper .swiper-pagination-bullet-active {
        opacity: 1 !important;
        width: 24px !important;
        border-radius: 4px !important;
        background: #045d36 !important;
    }
</style>

<!-- Main Wrapper -->
<div class="landing-page-wrapper">

    <!-- Hero Section -->
    <section class="hero-section relative flex items-center pt-4 pb-6 md:pt-6 md:pb-8">
        <div class="container mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="z-10 order-2 lg:order-1 text-left">
                <!-- Title -->
                <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-[40px] font-semibold mb-3 tracking-tight leading-tight text-gray-900 text-left">
                    <?php echo htmlspecialchars($heroTitle); ?>
                </h1>

                <!-- Rating & Sales Social Proof Row -->
                <div class="flex items-center gap-2 mb-2.5 text-xs text-left justify-start flex-wrap">
                    <div class="flex text-amber-400 text-xs gap-0.5">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <span class="text-gray-600 font-semibold underline cursor-pointer">2 reviews</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-gray-600 font-medium">10 sold in last 18 hours</span>
                </div>

                <!-- Price Row -->
                <div class="product-price-container mb-1 sm:mb-1.5 flex items-center gap-3 justify-start text-left max-w-xl">
                    <?php if ($hasSale): ?>
                        <span class="text-3xl sm:text-4xl font-extrabold text-[#045d36] tracking-tight"><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="text-lg font-bold text-gray-400 line-through opacity-80"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="bg-red-600 text-white text-xs font-extrabold px-2.5 py-1 rounded shadow-xs uppercase tracking-wider whitespace-nowrap">SAVE <?php echo round((($originalPrice - $currentSalePrice) / $originalPrice) * 100); ?>%</span>
                    <?php else: ?>
                        <span class="text-3xl sm:text-4xl font-extrabold text-[#045d36] tracking-tight"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="mb-5 max-w-xl text-left">
                    <div id="hero-description-container" class="relative overflow-hidden transition-all duration-300 prose max-w-none line-clamp-4 text-left" style="color: inherit;">
                        <div class="text-gray-600 font-normal leading-relaxed text-xs sm:text-sm md:text-base opacity-90 [&>p]:mt-0 [&>p]:mb-2 mt-0 pt-0">
                            <?php echo $heroDescription; ?>
                        </div>
                        <div id="hero-description-fade" class="absolute bottom-0 left-0 w-full h-12 pointer-events-none transition-opacity duration-300" style="background: linear-gradient(to top, var(--hero-bg), transparent);"></div>
                    </div>
                    <button id="hero-read-more-btn" class="mt-2 text-xs font-bold uppercase tracking-widest text-[#045d36] hover:text-[#034729] transition-all flex items-center gap-1.5 group text-left">
                        <span class="border-b border-[#045d36]/30 group-hover:border-[#045d36]">Read More</span>
                        <i class="fas fa-chevron-down text-[10px] transition-transform duration-300"></i>
                    </button>
                </div>

                <!-- Quantity Control Row -->
                <div class="flex items-center gap-3 mb-5 justify-start text-left">
                    <span class="text-sm font-bold text-gray-900">Quantity:</span>
                    <div class="flex items-center border border-gray-300 rounded-lg bg-white shadow-xs overflow-hidden h-[42px] px-2 transition hover:border-[#045d36]">
                        <button type="button" onclick="let q = document.getElementById('sp-qty'); if(parseInt(q.value) > 1) q.value = parseInt(q.value) - 1;" class="w-8 h-full text-gray-500 hover:text-black font-bold text-base flex items-center justify-center cursor-pointer select-none">-</button>
                        <input type="number" id="sp-qty" value="1" min="1" class="w-10 text-center font-bold text-gray-900 bg-transparent focus:outline-none text-sm border-none" style="-moz-appearance: textfield;">
                        <button type="button" onclick="let q = document.getElementById('sp-qty'); q.value = parseInt(q.value || 1) + 1;" class="w-8 h-full text-gray-500 hover:text-black font-bold text-base flex items-center justify-center cursor-pointer select-none">+</button>
                    </div>
                </div>

                <!-- Add To Cart Button Row -->
                <div class="flex flex-col sm:flex-row items-center gap-3 justify-start mb-4 sm:mb-8 max-w-xl">
                    <button id="special-add-to-cart-btn" onclick="spAddToCart(<?php echo $productData['product_id']; ?>, this, <?php echo htmlspecialchars(json_encode($firstVariant['variant_attributes'] ?? (object)[])); ?>, parseInt(document.getElementById('sp-qty').value || 1))" 
                            data-product-id="<?php echo $productData['product_id']; ?>"
                            data-product-name="<?php echo htmlspecialchars($productData['name'] ?? ''); ?>"
                            data-product-price="<?php echo $price; ?>"
                            data-product-slug="<?php echo htmlspecialchars($productData['slug'] ?? ''); ?>"
                            class="bg-[#045d36] hover:bg-[#034729] text-white w-full sm:w-auto px-10 h-[50px] rounded-xl text-xs sm:text-sm font-extrabold tracking-wider uppercase transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2.5 <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>" data-loading-text="Adding..." <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        <i class="fas fa-shopping-cart text-xs sm:text-sm"></i>
                        <span><?php echo $isOutOfStock ? 'Out of Stock' : 'ADD TO CART'; ?></span>
                    </button>

                    <?php foreach ($platformItems as $plat): 
                        $pLink = $plat['link'] ?? '#';
                        $pImg = !empty($plat['image']) ? getImageUrl($plat['image']) : '';
                        $pName = $plat['name'] ?? '';
                        $pBg = $plat['bg'] ?? '#ffffff';
                        $pText = $plat['text'] ?? '#111827';
                    ?>
                    <a href="<?php echo htmlspecialchars($pLink); ?>" target="_blank" class="w-full sm:w-auto h-[50px] px-4 rounded-xl flex items-center justify-center gap-2 shadow-xs hover:shadow transition-all group border border-gray-200" style="background-color: <?php echo $pBg; ?>; color: <?php echo $pText; ?>;" title="<?php echo htmlspecialchars($pName); ?>">
                        <?php if ($pImg): ?>
                            <img src="<?php echo htmlspecialchars($pImg); ?>" alt="" class="h-5 w-auto object-contain rounded-full">
                        <?php endif; ?>
                        <?php if (!empty($pName)): ?>
                            <span class="font-bold uppercase text-[10px] tracking-widest truncate" style="color: <?php echo $pText; ?>;"><?php echo htmlspecialchars($pName); ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Trust Badges Row -->
                <div class="grid grid-cols-3 gap-2 sm:gap-4 pt-2 sm:pt-5 max-w-xl mx-auto lg:mx-0 text-left">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-1.5 sm:gap-3 text-center sm:text-left">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 text-[#045d36] flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div>
                            <p class="text-[11px] sm:text-xs font-bold text-gray-900 leading-tight">Free Shipping</p>
                            <p class="text-[9px] sm:text-[11px] text-gray-500 leading-tight mt-0.5 hidden sm:block">Orders over ₹999</p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-1.5 sm:gap-3 text-center sm:text-left">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 text-[#045d36] flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <p class="text-[11px] sm:text-xs font-bold text-gray-900 leading-tight">Secure Payment</p>
                            <p class="text-[9px] sm:text-[11px] text-gray-500 leading-tight mt-0.5 hidden sm:block">100% Encrypted</p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-1.5 sm:gap-3 text-center sm:text-left">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 text-[#045d36] flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div>
                            <p class="text-[11px] sm:text-xs font-bold text-gray-900 leading-tight">Easy Returns</p>
                            <p class="text-[9px] sm:text-[11px] text-gray-500 leading-tight mt-0.5 hidden sm:block">7 Days Policy</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="z-10 order-1 lg:order-2 flex justify-center relative">
                <div class="absolute inset-0 bg-white opacity-20 rounded-full blur-3xl transform scale-75"></div>
                <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                     alt="<?php echo htmlspecialchars($heroTitle); ?>" 
                     class="relative w-full max-w-lg lg:max-w-2xl object-contain drop-shadow-2xl product-image-animate"
                     fetchpriority="high"
                     loading="eager"
                     onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
            </div>
        </div>
    </section>

    <?php
    // --- CAPTURE SECTIONS INTO ARRAY ---
    $sections = [];

    // 1. Stats Section
    $showStats = $statsGrp['show'] ?? ($landingPage['show_stats'] ?? 1);
    $statsItems = $statsGrp['items'] ?? [];
    if ($showStats && !empty($statsItems)) {
        ob_start(); ?>
        <section class="py-8 stats-section">
            <div class="container mx-auto px-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-12 text-center divide-x divide-gray-200">
                    <?php foreach($statsItems as $stat): ?>
                    <div>
                        <div class="text-5xl font-bold mb-2 stat-val"><?php echo htmlspecialchars($stat['value'] ?? ''); ?></div>
                        <div class="text-sm uppercase tracking-wider stat-label"><?php echo htmlspecialchars($stat['label'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php $sections['secStats'] = ob_get_clean();
    }

    // 2. Banner Section(s)
    $showBanner = $bannerGrp['show'] ?? ($landingPage['show_banner'] ?? 1);
    if ($showBanner) {
        $bannerSections = $bannerGrp['items'] ?? [];
        ob_start();
        
        foreach ($bannerSections as $banner): 
            $banImg = $banner['image'] ?? '';
            $banMobImg = $banner['mobile_image'] ?? '';
            $banVideo = $banner['video_url'] ?? '';
            $banMobVideo = $banner['mobile_video_url'] ?? '';
            
            $banHead = $banner['heading'] ?? '';
            $banText = $banner['text'] ?? '';
            $banBtn = $banner['btn_text'] ?? '';
            $banLink = $banner['btn_link'] ?? '';
            
            $desktopAsset = $banVideo ?: $banImg;
            $isDesktopVideo = !empty($banVideo) || preg_match('/\.(mp4|webm|ogg)$/i', $banImg);
            if($desktopAsset) $desktopAsset = getImageUrl($desktopAsset);
            
            $mobileAsset = $banMobVideo ?: $banMobImg;
            $isMobileVideo = !empty($banMobVideo) || ($mobileAsset && preg_match('/\.(mp4|webm|ogg)$/i', $mobileAsset));
            if($mobileAsset) $mobileAsset = getImageUrl($mobileAsset);
            
            if (!$desktopAsset && !$mobileAsset) continue; 
            $wrapLink = !empty($banLink) && empty($banBtn);
        ?>
        <section class="banner-section w-full relative my-4 px-4 md:px-20 py-4">
            <?php if ($wrapLink): ?><a href="<?php echo htmlspecialchars($banLink); ?>" class="block"><?php endif; ?>
            <?php if($isDesktopVideo): ?>
                <video src="<?php echo htmlspecialchars($desktopAsset); ?>" autoplay muted loop playsinline class="block w-full h-auto object-cover <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>"></video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($desktopAsset); ?>" class="block w-full h-auto <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>" alt="Banner" onerror="this.src='https://placehold.co/1200x600?text=Banner+Image'">
            <?php endif; ?>
            <?php if($mobileAsset): ?>
                <?php if($isMobileVideo): ?>
                    <video src="<?php echo htmlspecialchars($mobileAsset); ?>" autoplay muted loop playsinline class="block w-full h-auto object-cover md:hidden"></video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($mobileAsset); ?>" class="block w-full h-auto md:hidden" alt="Banner Mobile" onerror="this.src='https://placehold.co/600x600?text=Banner+Image'">
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($wrapLink): ?></a><?php endif; ?>
            <?php if($banHead || $banText || $banBtn): ?>
            <div class="absolute inset-0 flex items-center justify-center text-center p-6 bg-black bg-opacity-20 hover:bg-opacity-30 transition pointer-events-none">
                <div class="text-white max-w-2xl px-4 pointer-events-auto">
                    <?php if($banHead): ?><h2 class="text-4xl md:text-6xl font-bold mb-4 drop-shadow-md"><?php echo htmlspecialchars($banHead); ?></h2><?php endif; ?>
                    <?php if($banText): ?><p class="text-lg md:text-xl mb-8 drop-shadow leading-relaxed"><?php echo nl2br(htmlspecialchars($banText)); ?></p><?php endif; ?>
                    <?php if($banBtn): ?><a href="<?php echo $banLink ? htmlspecialchars($banLink) : '#'; ?>" class="inline-block bg-white text-gray-900 border-2 border-white px-8 py-3 rounded-full font-bold uppercase tracking-wider hover:bg-transparent hover:text-white transition transform hover:-translate-y-1 shadow-lg"><?php echo htmlspecialchars($banBtn); ?></a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endforeach;
        $sections['secBanner'] = ob_get_clean();
    }

    // 3. Why Section
    $showWhy = $whyGrp['show'] ?? ($landingPage['show_why'] ?? 1);
    if ($showWhy) {
        $whyTitle = $whyGrp['title'] ?? 'WHY US?';
        $whyItems = $whyGrp['items'] ?? [];
        if (empty($whyItems)) {
            $whyItems = [
                [
                    'title' => 'Effective Protection', 
                    'desc' => 'Fast and powerful action to eliminate cockroaches and keep your home protected for longer.',
                    'bg' => 'bg-[#eaf5ed]',
                    'border' => 'border-[#d6ebd9]',
                    'shadow' => 'shadow-[#eaf5ed]',
                    'icon_color' => 'text-[#045d36]',
                    'svg' => '<svg class="w-10 h-10 text-[#045d36]" fill="currentColor" viewBox="0 0 24 24"><path d="M17 8C8 10 5.9 16.5 4.2 21.8c-.4.4-1.1.1-1.1-.5 0-3.5 1.2-8.6 4.9-12.3C11.5 5.5 17 5 17 5s-.5 5.5-4.5 9.5c.3-.3.6-.6.9-.9C16.5 10.5 17 8 17 8z"/><path d="M12 14c-4 1-7 4.5-8.5 7.5 3-1.5 6.5-4.5 7.5-8.5z"/></svg>'
                ],
                [
                    'title' => 'Safe for Family', 
                    'desc' => 'Formulated to be safe for your family and pets when used as directed.',
                    'bg' => 'bg-[#e8f2fc]',
                    'border' => 'border-[#d2e4f7]',
                    'shadow' => 'shadow-[#e8f2fc]',
                    'icon_color' => 'text-[#0f4c81]',
                    'svg' => '<svg class="w-10 h-10 text-[#0f4c81]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'
                ],
                [
                    'title' => 'Long-Lasting Results', 
                    'desc' => 'Provides continuous protection to keep cockroaches away and maintain a cleaner, healthier home.',
                    'bg' => 'bg-[#fef7e7]',
                    'border' => 'border-[#faecd0]',
                    'shadow' => 'shadow-[#fef7e7]',
                    'icon_color' => 'text-[#045d36]',
                    'svg' => '<svg class="w-10 h-10 text-[#045d36]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'
                ]
            ];
        } else {
            // Map preset visual metadata to items from DB
            $meta = [
                ['bg' => 'bg-[#eaf5ed]', 'border' => 'border-[#d6ebd9]', 'shadow' => 'shadow-[#eaf5ed]', 'svg' => '<svg class="w-10 h-10 text-[#045d36]" fill="currentColor" viewBox="0 0 24 24"><path d="M17 8C8 10 5.9 16.5 4.2 21.8c-.4.4-1.1.1-1.1-.5 0-3.5 1.2-8.6 4.9-12.3C11.5 5.5 17 5 17 5s-.5 5.5-4.5 9.5c.3-.3.6-.6.9-.9C16.5 10.5 17 8 17 8z"/><path d="M12 14c-4 1-7 4.5-8.5 7.5 3-1.5 6.5-4.5 7.5-8.5z"/></svg>'],
                ['bg' => 'bg-[#e8f2fc]', 'border' => 'border-[#d2e4f7]', 'shadow' => 'shadow-[#e8f2fc]', 'svg' => '<svg class="w-10 h-10 text-[#0f4c81]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'],
                ['bg' => 'bg-[#fef7e7]', 'border' => 'border-[#faecd0]', 'shadow' => 'shadow-[#fef7e7]', 'svg' => '<svg class="w-10 h-10 text-[#045d36]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>']
            ];
            foreach ($whyItems as $idx => &$item) {
                $m = $meta[$idx % 3];
                $item['bg'] = $m['bg'];
                $item['border'] = $m['border'];
                $item['shadow'] = $m['shadow'];
                $item['svg'] = $m['svg'];
            }
        }
        ob_start(); ?>
        <section class="pt-3 pb-6 md:pt-10 md:pb-16 why-section">
            <div class="container mx-auto px-4 sm:px-6 text-center">
                <!-- Header without lines -->
                <div class="text-center mb-5 md:mb-12">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold uppercase tracking-wider text-[#111827] no-underline" style="text-decoration: none !important; border-bottom: none !important;"><?php echo htmlspecialchars($whyTitle); ?></h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                    <?php foreach ($whyItems as $idx => $why): 
                        $cardStyles = [
                            ['badge' => 'bg-emerald-50 text-[#045d36] border-emerald-200/80 shadow-emerald-100', 'icon' => 'fas fa-shield-virus'],
                            ['badge' => 'bg-sky-50 text-[#0f4c81] border-sky-200/80 shadow-sky-100', 'icon' => 'fas fa-user-shield'],
                            ['badge' => 'bg-amber-50 text-[#b45309] border-amber-200/80 shadow-amber-100', 'icon' => 'fas fa-clock-rotate-left']
                        ];
                        $st = $cardStyles[$idx % 3];
                    ?>
                    <div class="flex flex-col items-center text-center p-6 sm:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 group relative">
                        <!-- Modern Dual Ring Icon Badge -->
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl <?php echo $st['badge']; ?> border flex items-center justify-center mb-5 shadow-xs transform group-hover:scale-110 transition-transform duration-300">
                            <?php if (!empty($why['icon'])): ?>
                                <i class="<?php echo htmlspecialchars($why['icon']); ?> text-2xl sm:text-3xl"></i>
                            <?php else: ?>
                                <i class="<?php echo $st['icon']; ?> text-2xl sm:text-3xl"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Title -->
                        <h3 class="font-semibold text-lg sm:text-xl text-gray-900 mb-2 group-hover:text-[#045d36] transition-colors"><?php echo htmlspecialchars($why['title'] ?? ''); ?></h3>

                        <!-- Description -->
                        <p class="text-xs sm:text-sm leading-relaxed text-gray-500 max-w-xs opacity-90"><?php echo htmlspecialchars($why['desc'] ?? ''); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php $sections['secWhy'] = ob_get_clean();
    }

    // 4. About Section
    $showAbout = $aboutGrp['show'] ?? 1;
    if ($showAbout) {
        $aboutTitle = !empty($aboutGrp['title']) ? $aboutGrp['title'] : 'ABOUT OUR PRODUCT';
        $aboutText = !empty($aboutGrp['text']) ? $aboutGrp['text'] : ($productData['description'] ?? '');
        
        $aboutImage = '';
        if (!empty($aboutGrp['image'])) {
            $aboutImage = getImageUrl($aboutGrp['image']);
        } else {
            $aboutImage = getImageUrl('assets/uploads/newsletter/1790255189_16890dad-1f8b-4948-b97e-511b06933cc1.png');
        }
        ob_start(); ?>
        <section class="py-6 md:py-10 about-section relative overflow-hidden bg-white">
            <div class="container mx-auto px-6 relative z-10">
                <div class="flex flex-col-reverse lg:flex-row items-center gap-8 lg:gap-16">
                    <!-- Column: Content (Below Image on Mobile, Left on Desktop) -->
                    <div class="lg:w-1/2 text-center lg:text-left">
                        <!-- Heading -->
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold uppercase tracking-wider text-[#111827] mb-3 sm:mb-6 no-underline" style="text-decoration: none !important; border-bottom: none !important;"><?php echo htmlspecialchars($aboutTitle); ?></h2>
                        
                        <!-- Description -->
                        <div class="text-gray-600 text-sm md:text-base leading-relaxed mb-4 sm:mb-8 max-w-xl mx-auto lg:mx-0 opacity-90 prose">
                            <?php echo $aboutText; ?>
                        </div>

                        <!-- Action Button: SHOP NOW -->
                        <div class="flex justify-center lg:justify-start">
                            <button onclick="spAddToCart(<?php echo $productData['product_id']; ?>, this)" 
                                    class="bg-[#045d36] hover:bg-[#034729] text-white px-8 py-3.5 rounded-xl text-xs sm:text-sm font-extrabold tracking-wider uppercase transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer">
                                <span>SHOP NOW</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Padded White Outer Card with Cream Inner Frame -->
                    <div class="lg:w-1/2 relative flex justify-center items-center w-full">
                        <div class="relative w-full max-w-lg lg:max-w-xl bg-white p-4 sm:p-6 rounded-xl shadow-2xl border border-gray-100/80 group">
                            <div class="w-full aspect-[4/3] bg-[#f7f3e9] rounded-lg p-6 flex items-center justify-center">
                                <img src="<?php echo htmlspecialchars($aboutImage); ?>" 
                                     alt="<?php echo htmlspecialchars($aboutTitle); ?>" 
                                     class="w-full h-full max-h-[320px] object-contain transform group-hover:scale-105 transition duration-500 ease-out" 
                                     onerror="this.src='<?php echo getImageUrl('assets/uploads/newsletter/1790255189_16890dad-1f8b-4948-b97e-511b06933cc1.png'); ?>'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php $sections['secAbout'] = ob_get_clean();
    }

    // 5. Testimonials Section
    $showTesti = $testiGrp['show'] ?? 1;
    if ($showTesti) {
        $testiTitle = $testiGrp['title'] ?? 'Testimonials';
        ob_start(); ?>
        <section class="py-6 md:py-10 testi-section border-t border-gray-100">
             <div class="container mx-auto px-6 text-center">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold uppercase tracking-wider text-[#111827] mb-8 sm:mb-12 no-underline" style="text-decoration: none !important; border-bottom: none !important;"><?php echo htmlspecialchars($testiTitle); ?></h2>
                <div id="testimonialsList" class="mx-auto">
                    <div class="text-center text-gray-500 py-12 text-xl">Loading reviews...</div>
                </div>
             </div>
        </section>
        <?php $sections['secTesti'] = ob_get_clean();
    }

    // 6. Newsletter Section
    $showNews = $newsGrp['show'] ?? 1;
    if ($showNews) {
        $newsTitle = !empty($newsGrp['title']) ? $newsGrp['title'] : 'SUBSCRIBE TO OUR NEWSLETTER';
        $newsText = !empty($newsGrp['text']) ? $newsGrp['text'] : 'Get exclusive deals, tips, and fast updates directly to your inbox.';
        
        $newsImage = '';
        if (!empty($newsGrp['image'])) {
            $newsImage = getImageUrl($newsGrp['image']);
        } elseif (!empty($mainImage)) {
            $newsImage = $mainImage;
        } else {
            $newsImage = getImageUrl('assets/uploads/newsletter/1790255189_16890dad-1f8b-4948-b97e-511b06933cc1.png');
        }
        ob_start(); ?>
        <section class="py-6 md:py-10 news-section bg-white">
            <div class="container mx-auto px-4 md:px-6">
                <div class="flex flex-col md:flex-row items-center justify-between max-w-5xl mx-auto bg-white p-6 sm:p-10 md:p-12 rounded-3xl shadow-xl border border-gray-100 relative overflow-hidden">
                    <!-- Image Side -->
                    <div class="w-full md:w-5/12 mb-6 md:mb-0 flex justify-center items-center relative z-10">
                        <div class="w-48 sm:w-60 md:w-72 aspect-square rounded-2xl bg-emerald-50/60 p-4 flex items-center justify-center border border-emerald-100/60 shadow-xs">
                             <img src="<?php echo htmlspecialchars($newsImage); ?>" 
                                  alt="Newsletter" 
                                  class="w-full h-full object-contain drop-shadow-xl transform hover:scale-105 transition duration-500" 
                                  onerror="this.src='<?php echo getImageUrl('assets/uploads/newsletter/1790255189_16890dad-1f8b-4948-b97e-511b06933cc1.png'); ?>'">
                        </div>
                    </div>

                    <!-- Content Side -->
                    <div class="w-full md:w-7/12 md:pl-8 lg:pl-12 text-center md:text-left relative z-10">
                         <span class="inline-block px-3 py-1 rounded-full text-[10px] sm:text-[11px] font-semibold uppercase tracking-widest bg-emerald-50 text-[#045d36] mb-3">
                             STAY UPDATED
                         </span>
                         <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold uppercase tracking-wider text-[#111827] mb-3 leading-tight no-underline" style="text-decoration: none !important; border-bottom: none !important;">
                             <?php echo htmlspecialchars($newsTitle); ?>
                         </h2>
                         <p class="text-gray-500 text-xs sm:text-sm md:text-base mb-6 leading-relaxed max-w-lg mx-auto md:mx-0">
                             <?php echo htmlspecialchars($newsText); ?>
                         </p>
                         
                         <form id="landingNewsletterForm" class="flex flex-col sm:flex-row w-full max-w-md mx-auto md:mx-0 gap-2.5 sm:gap-0">
                             <input type="email" name="email" placeholder="Enter your email address" required class="w-full flex-grow px-4 py-3 sm:px-5 sm:py-3.5 bg-gray-50 border border-gray-300 rounded-xl sm:rounded-r-none focus:outline-none text-xs sm:text-sm focus:border-[#045d36] transition">
                             <button type="submit" id="special-sub-btn" class="w-full sm:w-auto bg-[#045d36] hover:bg-[#034729] text-white px-6 py-3 sm:py-3.5 rounded-xl sm:rounded-l-none text-xs sm:text-sm font-extrabold uppercase tracking-wider transition whitespace-nowrap shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer" data-loading-text="Subscribing...">
                                 <span>SUBSCRIBE</span>
                                 <i class="fas fa-paper-plane text-xs"></i>
                             </button>
                         </form>
                         <div id="landingNewsletterMessage" class="hidden text-center md:text-left text-xs sm:text-sm mt-3 font-semibold"></div>
                         <p class="text-[11px] text-gray-400 mt-4 flex items-center justify-center md:justify-start gap-1">
                             <i class="fas fa-lock text-[10px]"></i> 
                             <span>Your privacy is 100% protected.</span>
                         </p>
                    </div>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                const newsForm = document.getElementById('landingNewsletterForm');
                const messageDiv = document.getElementById('landingNewsletterMessage');
                if (newsForm) {
                    newsForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const btn = this.querySelector('button');
                        if (btn) setBtnLoading(btn, true);
                        if (messageDiv) messageDiv.classList.add('hidden');
                        
                        try {
                            const email = this.querySelector('input[name="email"]').value;
                            const res = await fetch('<?php echo $baseUrl; ?>/api/subscribe.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ email })
                            });
                            const data = await res.json();
                            if (messageDiv) {
                                messageDiv.textContent = data.message || 'Subscribed!';
                                messageDiv.className = `text-center md:text-left text-xs sm:text-sm mt-3 font-semibold ${data.success ? 'text-emerald-600' : 'text-red-600'}`;
                                messageDiv.classList.remove('hidden');
                            }
                        } catch (err) { console.error(err); }
                        finally { if (btn) setBtnLoading(btn, false); }
                    });
                }
            });
            </script>
        </section>
        <?php $sections['secNews'] = ob_get_clean();
    }

    // --- RENDER IN ORDER ---
    $savedOrder = $configGrp['section_order'] ?? [];
    $defaultOrder = ['secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews'];
    
    $finalOrder = is_array($savedOrder) && !empty($savedOrder) ? $savedOrder : $defaultOrder;
    foreach ($defaultOrder as $k) {
        if (!in_array($k, $finalOrder)) $finalOrder[] = $k;
    }

    foreach ($finalOrder as $secId) {
        if (isset($sections[$secId])) {
            echo $sections[$secId];
        }
    }
    ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
    <?php if ($showTesti): ?>
    (function() {
        const manualReviews = <?php echo json_encode($testiGrp['items'] ?? []); ?>;
        const container = document.getElementById('testimonialsList');
        const productId = '<?php echo $productData['product_id'] ?? ($productData['id'] ?? ''); ?>';
        const baseUrl = '<?php echo $baseUrl; ?>';

        const renderReviews = (reviews) => {
            if (!reviews || reviews.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-400 text-lg w-full">No reviews yet.</p>';
                return;
            }

            const createCard = (review, isSlide = false) => {
                const name = review.name || review.user_name || 'Anonymous';
                const comment = review.comment || '';
                const rating = parseInt(review.rating || 5);
                const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                
                // Determine Avatar
                let imgSrc = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
                if (review.image) {
                     imgSrc = review.image.startsWith('http') ? review.image : `${baseUrl}/${review.image}`;
                }

                return `
                    <div class="${isSlide ? 'swiper-slide h-auto' : 'h-full'}">
                        <div class="flex flex-col items-start bg-gray-50 p-6 sm:p-8 rounded-2xl relative text-left h-full border border-gray-100 shadow-xs">
                            <div class="flex items-center justify-between w-full mb-4">
                                <div class="text-amber-400 text-sm font-bold tracking-widest">${stars}</div>
                                <i class="fas fa-quote-right text-gray-200 text-3xl"></i>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-6 leading-relaxed flex-grow">"${escapeHtml(comment)}"</p>
                            <div class="flex items-center mt-auto w-full pt-4 border-t border-gray-200/60">
                                <img src="${imgSrc}" class="w-10 h-10 rounded-full mr-3 shadow-xs object-cover" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random'">
                                <div>
                                    <span class="font-bold text-sm text-gray-900 block leading-tight">${escapeHtml(name)}</span>
                                    <span class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1"><i class="fas fa-check-circle text-[10px]"></i> Verified Buyer</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            };

            const isSlider = reviews.length >= 1;

            if (isSlider) {
                container.innerHTML = `
                    <div class="swiper testimonialSwiper pb-12 w-full">
                        <div class="swiper-wrapper items-stretch">
                            ${reviews.map(r => createCard(r, true)).join('')}
                        </div>
                        <div class="swiper-pagination"></div>
                    </div>
                `;
                new Swiper(".testimonialSwiper", {
                    slidesPerView: 1,
                    spaceBetween: 30,
                    pagination: { el: ".swiper-pagination", clickable: true },
                    breakpoints: {
                        640: { slidesPerView: 2, spaceBetween: 20 },
                        1024: { slidesPerView: 3, spaceBetween: 30 },
                    },
                });
            } else {
                 container.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-${Math.min(reviews.length, 3)} gap-8 max-w-6xl mx-auto">
                        ${reviews.map(r => createCard(r)).join('')}
                    </div>
                `;
            }
        };

        // Execution Logic
        if (manualReviews && Array.isArray(manualReviews) && manualReviews.length > 0) {
            renderReviews(manualReviews);
        } else {
            fetch(`${baseUrl}/api/reviews.php?product_id=${productId}&sort=highest`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.reviews) renderReviews(data.reviews);
                    else renderReviews([]);
                })
                .catch(e => {
                    console.error(e);
                    container.innerHTML = '<p class="text-center text-red-400">Error loading reviews.</p>';
                });
        }
    })();
    <?php endif; ?>

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize Cart (if needed or if headers script missed it)
    document.addEventListener('DOMContentLoaded', () => {
        // if(typeof loadCart === 'function') loadCart();
    });
    </script>
</div>

<!-- Sticky Add To Cart Bar for Landing Page -->
<div id="sticky-bar" class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] transform translate-y-full transition-transform duration-300 z-40 px-3 py-3 md:px-4">
    <div class="container mx-auto flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 overflow-hidden">
            <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                 alt="Product" 
                 class="w-12 h-12 object-contain rounded border border-gray-100"
                 onerror="this.src='https://placehold.co/150x150?text=Product'"> 

            <div class="min-w-0">
                <h3 class="font-bold text-gray-900 leading-tight text-sm md:text-base overflow-hidden text-ellipsis" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; max-width: 450px;"><?php echo htmlspecialchars($heroTitle); ?></h3>
                <div class="hidden md:flex text-xs items-center mt-1">
                    <?php 
                    $reviewCount = intval($productData['review_count'] ?? 0);
                    if ($reviewCount === 0) {
                        echo '<div class="flex flex-col">';
                        echo '<span class="text-[10px] text-gray-500 font-medium">No reviews yet</span>';
                        echo '</div>';
                    } else {
                        echo '<div class="flex items-center text-yellow-500">';
                        $rating = floatval($avgRating ?? $productData['rating'] ?? 5);
                        for ($i = 0; $i < 5; $i++) {
                            echo '<i class="fas fa-star ' . ($i < $rating ? '' : 'text-gray-300') . '"></i>';
                        }
                        echo '<span class="text-[10px] text-gray-500 ml-1">(' . $reviewCount . ')</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="text-right mr-2 hidden md:block">
                 <div class="text-xs text-gray-500">Total Price:</div>
                 <div class="font-bold text-lg text-[#1a3d32]">
                     <?php if ($hasSale): ?>
                        <span class="text-gray-400 line-through text-xs mr-1"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="text-[#1a3d32]"><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                     <?php else: ?>
                        <span><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                     <?php endif; ?>
                 </div>
            </div>
            
             <div class="hidden md:flex items-center border border-gray-300 rounded-md w-24 h-10 overflow-hidden bg-white">
                <button onclick="updateStickyQty(-1)" class="w-8 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition select-none">-</button>
                <div class="flex-1 h-full grid place-items-center">
                    <span id="sticky-qty-display" class="text-gray-900 font-semibold text-sm select-none"><?php echo $isOutOfStock ? '0' : '1'; ?></span>
                </div>
                <input type="hidden" id="sticky-qty" value="<?php echo $isOutOfStock ? '0' : '1'; ?>">
                <button onclick="updateStickyQty(1)" class="w-8 h-full flex-shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition select-none">+</button>
            </div>

            <button onclick="stickyAddToCart()" 
                    data-product-id="<?php echo $productData['product_id']; ?>"
                    data-product-name="<?php echo htmlspecialchars($productData['name'] ?? ''); ?>"
                    data-product-price="<?php echo $price; ?>"
                    data-product-slug="<?php echo htmlspecialchars($productData['slug'] ?? ''); ?>"
                    class="bg-[#1a3d32] text-white px-4 py-2.5 md:px-8 rounded-full font-bold hover:bg-black transition flex items-center justify-center gap-2 text-sm md:text-base whitespace-nowrap <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>" id="sticky-atc-btn" <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                <i class="fas fa-shopping-cart text-xs md:text-sm"></i>
                <span><?php echo $isOutOfStock ? 'Out of Stock' : 'Add To Cart'; ?></span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('scroll', function() {
    const stickyBar = document.getElementById('sticky-bar');
    const heroSection = document.querySelector('.hero-section'); 
    const footer = document.querySelector('footer');
    const backToTopBtn = document.getElementById('backToTop');
    
    if (!stickyBar || !heroSection) return;
    
    const scrollY = window.scrollY;
    // Calculate trigger point: roughly passed the hero section
    const heroHeight = heroSection.offsetHeight;
    const heroBottom = heroSection.offsetTop + heroHeight;
    
    // Check if we hit footer
    let footerTop = document.documentElement.scrollHeight; 
    if(footer) footerTop = footer.offsetTop;
    
    // Show if passed hero AND not yet at footer (with buffer)
    if (scrollY > (heroBottom - 100) && (scrollY + window.innerHeight) < (footerTop + 50)) {
        stickyBar.classList.remove('translate-y-full');
        if (backToTopBtn && window.innerWidth < 768) {
            backToTopBtn.style.bottom = '80px';
        }
    } else {
        stickyBar.classList.add('translate-y-full');
        if (backToTopBtn && window.innerWidth < 768) {
            backToTopBtn.style.bottom = '20px';
        }
    }
});

function updateStickyQty(change) {
    const input = document.getElementById('sticky-qty');
    const display = document.getElementById('sticky-qty-display');
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

    input.value = val;
    if (display) display.textContent = val;
}

function stickyAddToCart() {
    const btn = document.getElementById('sticky-atc-btn');
    const qty = parseInt(document.getElementById('sticky-qty').value) || 1;
    const productId = <?php echo $productData['product_id']; ?>;
    
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;

        if (typeof spAddToCart === 'function') {
           // Direct fetch to support quantity not supported by default spAddToCart
            const attributes = <?php echo json_encode($firstVariant['variant_attributes'] ?? (object)[]); ?>;
            fetch(`${LANDING_BASE_URL}/api/cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty, variant_attributes: attributes })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const countEls = document.querySelectorAll('.cart-count');
                    countEls.forEach(el => el.textContent = data.count || data.cartCount);
                    if (typeof openSideCart === 'function') {
                        openSideCart(data.cart);
                    } else {
                        const cartBtn = document.getElementById('cartBtn');
                        if(cartBtn) cartBtn.click();
                        // Fallback
                        else if(typeof showNotification === 'function') showNotification('Product added to cart!', 'success');
                    }
                }
            })
            .catch(err => console.error(err))
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<!--  Footer Extra (Consolidated) -->
<?php 
if (!empty($footerGrp['show_extra']) && !empty($footerGrp['extra_content'])): ?>
<section class="pt-10 pb-20 md:py-24 border-t border-gray-100 relative z-10" style="background-color: <?php echo $footerGrp['extra_bg'] ?? '#f8f9fa'; ?>; color: <?php echo $footerGrp['extra_text'] ?? '#333333'; ?>;">
    <div class="container mx-auto px-4">
        <div class="prose max-w-none leading-relaxed" style="color: <?php echo $footerGrp['extra_text'] ?? '#333333'; ?>;">
            <?php echo $footerGrp['extra_content']; ?>
        </div>
    </div>
</section>
<?php endif; ?>