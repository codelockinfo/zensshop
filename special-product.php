<?php
// Start output buffering
ob_start();

require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
$productObj = new Product();

// Get base URL
$baseUrl = getBaseUrl();

// 1. Determine Page (by slug or ID)
$pageSlug = $_GET['page'] ?? 'default';

// 2. Fetch Landing Page Config
$landingPage = $db->fetchOne("SELECT * FROM landing_pages WHERE slug = ?", [$pageSlug]);

if (!$landingPage) {
    // If not found, try to fallback to the first available one or 404
    $landingPage = $db->fetchOne("SELECT * FROM landing_pages ORDER BY id ASC LIMIT 1");
    if (!$landingPage) {
        die("Landing page not found. Please create one in Admin > Landing Pages.");
    }
}

// 3. Fetch Product Data
$productData = $productObj->getById($landingPage['product_id']);

if (!$productData || $productData['status'] !== 'active') {
    die("The featured product is currently unavailable.");
}

// Prepare Data
$mainImage = !empty($landingPage['hero_image']) ? getImageUrl($landingPage['hero_image']) : getProductImage($productData);
$images = json_decode($productData['images'] ?? '[]', true);
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;
$originalPrice = $productData['price'] ?? 0;
$currentSalePrice = $productData['sale_price'] ?? 0;
$hasSale = $currentSalePrice > 0 && $currentSalePrice < $originalPrice;

// Setup Gallery Pool for Rotation
$galleryPool = [];
if (!empty($mainImage)) $galleryPool[] = $mainImage;

if (is_array($images)) {
    foreach($images as $img) {
        // Construct Full Path
        $fullPath = getImageUrl($img);

        // Deduplicate based on filename to avoid path mismatches (e.g. relative vs absolute)
        $filename = basename($img);
        $isDuplicate = false;
        foreach ($galleryPool as $existing) {
            if (strpos($existing, $filename) !== false) {
                $isDuplicate = true;
                break;
            }
        }
        
        if (!$isDuplicate) $galleryPool[] = $fullPath;
    }
}
$galleryPool = array_values(array_unique($galleryPool));
$galleryCount = count($galleryPool);
$currentImgIdx = 1; // Start from 1 since 0 is used by Hero


// Hero Section Content
$heroTitle = $landingPage['hero_title'] ?: $productData['name'];
$heroSubtitle = $landingPage['hero_subtitle'] ?: 'Natural Inner Beauty';
$heroDescription = $landingPage['hero_description'] ?: ($productData['description'] ?? '');

$themeColor = $landingPage['theme_color'] ?: '#5F8D76';

// Helper to determine contrast color (Black or White)
function getContrastColor($hexColor) {
    // defaults
    if (!$hexColor) return '#000000';
    $hexColor = str_replace('#', '', $hexColor);
    
    // Check if valid hex
    if(strlen($hexColor) == 3) {
        $r = hexdec(str_repeat(substr($hexColor, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hexColor, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hexColor, 2, 1), 2));
    } else if(strlen($hexColor) == 6) {
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
    } else {
        return '#000000';
    }

    // Calculate luminance
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    // Return black for bright colors, white for dark colors
    return ($luminance > 0.5) ? '#000000' : '#FFFFFF';
}

// Style Overrides (Auto-Contrast Text)
$heroBg = $landingPage['hero_bg_color'] ?: '#E8F0E9';
$heroText = $landingPage['hero_text_color'] ?: getContrastColor($heroBg);

$bodyBg = $landingPage['body_bg_color'] ?: '#FFFFFF';
$bodyText = $landingPage['body_text_color'] ?: getContrastColor($bodyBg); 

$bannerBg = $landingPage['banner_bg_color'] ?: '#FFFFFF';
$bannerText = $landingPage['banner_text_color'] ?: getContrastColor($bannerBg);

$statsBg = $landingPage['stats_bg_color'] ?: '#F3F6F4';
$statsText = $landingPage['stats_text_color'] ?: getContrastColor($statsBg);

$whyBg = $landingPage['why_bg_color'] ?: '#FFFFFF';
$whyText = $landingPage['why_text_color'] ?: getContrastColor($whyBg);

$aboutBg = $landingPage['about_bg_color'] ?: '#F9F9F9';
$aboutText = $landingPage['about_text_color'] ?: getContrastColor($aboutBg);

$testiBg = $landingPage['testimonials_bg_color'] ?: '#FFFFFF';
$testiText = $landingPage['testimonials_text_color'] ?: getContrastColor($testiBg);

$newsBg = $landingPage['newsletter_bg_color'] ?: '#E8F0E9';
$newsText = $landingPage['newsletter_text_color'] ?: getContrastColor($newsBg);


// Decode JSON Configs
$navLinks = json_decode($landingPage['nav_links'] ?? '[]', true);
if (empty($navLinks)) {
    $navLinks = [
        ['label' => 'Home', 'url' => '#'],
        ['label' => 'Shop', 'url' => '#'],
        ['label' => 'About', 'url' => '#'],
        ['label' => 'Contact', 'url' => '#']
    ];
}

// --- STATS DATA ---
$statsData = json_decode($landingPage['stats_data'] ?? '[]', true);
if (empty($statsData)) {
    // Fallback Mock Stats if nothing configured
    $reviewStats = $db->fetchOne("SELECT COUNT(*) as count, AVG(rating) as avg_rating FROM reviews WHERE product_id = ? AND status = 'approved'", [$productData['id']]);
    $reviewCount = $reviewStats['count'] ?? 0;
    $avgRating = $reviewStats['avg_rating'] ?? 0;
    $satisfactionPercent = $reviewCount > 0 ? round(($avgRating / 5) * 100) : 100;
    
    try {
        $orderStats = $db->fetchOne("SELECT COUNT(DISTINCT order_id) as count FROM order_items WHERE product_id = ?", [$productData['id']]);
        $soldCount = $orderStats['count'] ?? 0;
    } catch (Exception $e) { $soldCount = 0; }
    $usersCount = 100 + $soldCount * 2; 

    $statsData = [
        ['value' => number_format($soldCount), 'label' => 'Happy Customers'],
        ['value' => number_format($usersCount), 'label' => 'Followers'],
        ['value' => number_format($reviewCount), 'label' => 'Reviews'],
        ['value' => $satisfactionPercent . '%', 'label' => 'Satisfaction']
    ];
}

// SEO Meta Data Override
if (!empty($landingPage['meta_title'])) {
    $pageTitle = $landingPage['meta_title'];
} else {
    $pageTitle = $heroTitle; // Default fallback
}

$metaDescription = $landingPage['meta_description'] ?? '';
$customSchema = $landingPage['custom_schema'] ?? '';

if (!empty($customSchema)) {
    // 1. Create Base Placeholders (Calculated values)
    $replacements = [
        '{{description}}' => preg_replace('/\s+/', ' ', strip_tags($heroDescription)),
        '{{url}}' => $baseUrl . '/special-product.php?page=' . $pageSlug,
        '{{image}}' => $mainImage,
        '{{currency}}' => $productData['currency'] ?? 'USD',
        '{{brand}}' => "ZensShop", // Default Brand
        '{{availability}}' => (isset($productData['quantity']) && $productData['quantity'] <= 0) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        '{{price_valid_until}}' => date('Y-m-d', strtotime('+1 year'))
    ];

    // 2. Auto-generate placeholders for ALL product fields
    // This allows you to use {{weight}}, {{sku}}, {{material}}, etc. if they exist in DB
    if (is_array($productData)) {
        foreach ($productData as $key => $value) {
            if (is_scalar($value)) { // Only string/int/float/bool
                 $replacements['{{' . $key . '}}'] = $value;
            }
        }
    }
    
    // 3. Perform Replacement
    foreach ($replacements as $key => $val) {
        $customSchema = str_replace($key, $val, $customSchema);
    }
} 
elseif ($productData) {
    // AUTO-GENERATE standard Product Schema if nothing provided
    $schemaUrl = $baseUrl . '/special-product.php?page=' . $pageSlug; 
    $schemaImg = $mainImage; 
    
    $availability = 'https://schema.org/InStock'; 
    if (isset($productData['quantity']) && $productData['quantity'] <= 0) {
        $availability = 'https://schema.org/OutOfStock';
    }

    $productSchema = [
        "@context" => "https://schema.org",
        "@type" => "Product",
        "name" => $productData['name'],
        "description" => strip_tags($heroDescription),
        "url" => $schemaUrl,
        "image" => [$schemaImg],
        "brand" => [
            "@type" => "Brand",
            "name" => "ZensShop" 
        ],
        "offers" => [
            "@type" => "Offer",
            "price" => (float)$price,
            "priceCurrency" => $productData['currency'] ?? 'USD',
            "availability" => $availability,
            "url" => $schemaUrl,
            "priceValidUntil" => date('Y-m-d', strtotime('+1 year'))
        ]
    ];
    
    if (!empty($reviewCount) && $reviewCount > 0) {
        $productSchema['aggregateRating'] = [
            "@type" => "AggregateRating",
            "ratingValue" => $avgRating,
            "reviewCount" => $reviewCount,
            "bestRating" => "5",
            "worstRating" => "1"
        ];
    }
    
    $customSchema = json_encode($productSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// --- 4. HEADER INJECTION ---
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styles for Landing Page (Injected after body start) -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<script>
    const LANDING_BASE_URL = '<?php echo $baseUrl; ?>';
    
    // Custom Add To Cart for Landing Page
    function spAddToCart(productId, btn) {
        if (btn) setBtnLoading(btn, true);
        fetch(`${LANDING_BASE_URL}/api/cart.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Update cart count if element exists (handled by header usually, but we force update)
                const countEls = document.querySelectorAll('.cart-count');
                countEls.forEach(el => el.textContent = data.cartCount);
                
                // Show site's cart drawer if available
                if (typeof openSideCart === 'function') {
                    openSideCart(data.cart);
                } else {
                    const cartBtn = document.getElementById('cartBtn');
                    if(cartBtn) cartBtn.click();
                    else console.log('Product added to cart!');
                }
            }
        })
        .catch(err => console.error(err))
        .finally(() => {
            if (btn) setBtnLoading(btn, false);
        });
    }

    // Read More Toggle for Hero Description
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('hero-description-container');
        const btn = document.getElementById('hero-read-more-btn');
        const fade = document.getElementById('hero-description-fade');
        
        if (container && btn) {
            // Check if content is actually taller than max-height
            const isTall = container.scrollHeight > 180;
            
            if (!isTall) {
                btn.style.display = 'none';
                fade.style.display = 'none';
                container.style.maxHeight = 'none';
            }
            
            btn.addEventListener('click', function() {
                const isExpanded = container.style.maxHeight === 'none';
                
                if (isExpanded) {
                    container.style.maxHeight = '180px';
                    fade.style.opacity = '1';
                    btn.querySelector('span').textContent = 'Read More';
                    btn.querySelector('i').style.transform = 'rotate(0deg)';
                    // Scroll back to top of container if it's out of view
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    container.style.maxHeight = 'none';
                    fade.style.opacity = '0';
                    btn.querySelector('span').textContent = 'Show Less';
                    btn.querySelector('i').style.transform = 'rotate(180deg)';
                }
            });
        }
    });
</script>

<style>
    /* Override font-family for this page if needed, or keep inherited */
    body { font-family: 'Outfit', sans-serif; background-color: var(--body-bg); color: var(--body-text); }
    footer, footer.bg-white { background-color: var(--body-bg) !important; color: var(--body-text) !important; }
    h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }

    /* Dynamic Theme Colors */
    :root {
        --theme-color: <?php echo htmlspecialchars($themeColor); ?>;
        
        --body-bg: <?php echo htmlspecialchars($landingPage['body_bg_color'] ?? '#ffffff'); ?>;
        --body-text: <?php echo htmlspecialchars($landingPage['body_text_color'] ?? '#000000'); ?>;

        --hero-bg: <?php echo htmlspecialchars($landingPage['hero_bg_color'] ?? '#f9fafb'); ?>;
        --hero-text: <?php echo htmlspecialchars($landingPage['hero_text_color'] ?? '#111827'); ?>;

        --banner-bg: <?php echo htmlspecialchars($landingPage['banner_bg_color'] ?? '#ffffff'); ?>;
        --banner-text: <?php echo htmlspecialchars($landingPage['banner_text_color'] ?? '#000000'); ?>;
        
        --stats-bg: <?php echo htmlspecialchars($landingPage['stats_bg_color'] ?? '#ffffff'); ?>;
        --stats-text: <?php echo htmlspecialchars($landingPage['stats_text_color'] ?? '#111827'); ?>;
        
        --why-bg: <?php echo htmlspecialchars($landingPage['why_bg_color'] ?? '#f9fafb'); ?>;
        --why-text: <?php echo htmlspecialchars($landingPage['why_text_color'] ?? '#111827'); ?>;
        
        --about-bg: <?php echo htmlspecialchars($landingPage['about_bg_color'] ?? '#ffffff'); ?>;
        --about-text: <?php echo htmlspecialchars($landingPage['about_text_color'] ?? '#111827'); ?>;
        
        --testi-bg: <?php echo htmlspecialchars($landingPage['testimonials_bg_color'] ?? '#f9fafb'); ?>;
        --testi-text: <?php echo htmlspecialchars($landingPage['testimonials_text_color'] ?? '#111827'); ?>;
        
        --news-bg: <?php echo htmlspecialchars($landingPage['newsletter_bg_color'] ?? '#ffffff'); ?>;
        --news-text: <?php echo htmlspecialchars($landingPage['newsletter_text_color'] ?? '#111827'); ?>;
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
        opacity: 0.9;
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
</style>

<!-- Main Wrapper -->
<div class="landing-page-wrapper">

    <!-- Hero Section -->
    <section class="hero-section min-h-screen relative flex items-center pt-10 pb-20 md:py-24">
        <div class="container mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="z-10 order-2 lg:order-1 text-center lg:text-left">
                <h2 class="text-xl font-semibold tracking-widest uppercase mb-4 opacity-60"><?php echo htmlspecialchars($heroSubtitle); ?></h2>
                <h1 class="text-8xl lg:text-9xl font-bold mb-8 tracking-tighter lowercase leading-none">
                    <?php echo htmlspecialchars($heroTitle); ?>
                </h1>
                
                <?php // Price variables already defined at top ?>

                <div class="mb-6 max-w-xl mx-auto lg:mx-0">
                    <div id="hero-description-container" class="relative overflow-hidden transition-all duration-500 prose prose-lg max-w-none" style="max-height: 180px; color: inherit;">
                        <div class="opacity-80 leading-relaxed">
                            <?php echo $heroDescription; ?>
                        </div>
                        <div id="hero-description-fade" class="absolute bottom-0 left-0 w-full h-16 pointer-events-none transition-opacity duration-300" style="background: linear-gradient(to top, var(--hero-bg), transparent);"></div>
                    </div>
                    <button id="hero-read-more-btn" class="mb-4 text-sm font-bold uppercase tracking-widest hover:opacity-70 transition-all flex items-center gap-2" style="color: var(--theme-color);">
                        <span>Read More</span>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                </div>

                <div class="product-price-container mb-6 flex items-center gap-3 justify-center lg:justify-start">
                    <span class="text-xl font-bold text-gray-500 uppercase">Price:</span>
                    <?php if ($hasSale): ?>
                        <span class="text-xl font-bold text-red-600 line-through opacity-70"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="text-xl font-bold text-gray-900"><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded ml-2">-<?php echo round((($originalPrice - $currentSalePrice) / $originalPrice) * 100); ?>% OFF</span>
                    <?php else: ?>
                        <span class="text-xl font-bold text-gray-900"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                    <?php endif; ?>
                </div>
                
                <button onclick="spAddToCart(<?php echo $productData['id']; ?>, this)" class="btn-accent px-10 py-4 rounded text-lg font-medium tracking-wide uppercase transition shadow-xl hover:shadow-2xl transform hover:-translate-y-0.5" data-loading-text="Adding...">
                    Add to your cart
                </button>
            </div>
            
            <div class="z-10 order-1 lg:order-2 flex justify-center relative">
                <div class="absolute inset-0 bg-white opacity-20 rounded-full blur-3xl transform scale-75"></div>
                <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($heroTitle); ?>" class="relative w-full max-w-lg lg:max-w-2xl object-contain drop-shadow-2xl product-image-animate">
            </div>
        </div>
    </section>

    <?php
    // --- CAPTURE SECTIONS INTO ARRAY ---
    $sections = [];

    // 1. Stats Section
    if ($landingPage['show_stats']) {
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 stats-section">
            <div class="container mx-auto px-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-12 text-center divide-x divide-gray-200">
                    <?php foreach($statsData as $stat): ?>
                    <div>
                        <div class="text-5xl font-bold mb-2 stat-val"><?php echo htmlspecialchars($stat['value']); ?></div>
                        <div class="text-sm uppercase tracking-wider stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php $sections['secStats'] = ob_get_clean();
    }

    // 2. Banner Section(s)
    if (!empty($landingPage['show_banner'])) {
        ob_start();
        
        $bannerSections = [];
        if (!empty($landingPage['banner_sections_json'])) {
            $bannerSections = json_decode($landingPage['banner_sections_json'], true);
        }
        if (empty($bannerSections) && !empty($landingPage['banner_image'])) {
            $bannerSections = [[
                'image' => $landingPage['banner_image'],
                'mobile_image' => $landingPage['banner_mobile_image'],
                'heading' => $landingPage['banner_heading'],
                'text' => $landingPage['banner_text'],
                'btn_text' => $landingPage['banner_btn_text'],
                'btn_link' => $landingPage['banner_btn_link']
            ]];
        }
        
        foreach ($bannerSections as $banner): 
            $banImg = $banner['image'] ?? '';
            $banMobImg = $banner['mobile_image'] ?? '';
            $banVideo = $banner['video_url'] ?? '';
            $banMobVideo = $banner['mobile_video_url'] ?? '';
            
            $banHead = $banner['heading'] ?? '';
            $banText = $banner['text'] ?? '';
            $banBtn = $banner['btn_text'] ?? '';
            $banLink = $banner['btn_link'] ?? '';
            
            // 1. Resolve Desktop Media
            $desktopAsset = $banVideo ?: $banImg;
            $isDesktopVideo = !empty($banVideo) || preg_match('/\.(mp4|webm|ogg)$/i', $banImg);
            if($desktopAsset && strpos($desktopAsset, 'http') !== 0) $desktopAsset = $baseUrl . '/' . $desktopAsset;
            
            // 2. Resolve Mobile Media
            $mobileAsset = $banMobVideo ?: $banMobImg;
            $isMobileVideo = !empty($banMobVideo) || ($mobileAsset && preg_match('/\.(mp4|webm|ogg)$/i', $mobileAsset));
            if($mobileAsset && strpos($mobileAsset, 'http') !== 0) $mobileAsset = $baseUrl . '/' . $mobileAsset;
            
            if (!$desktopAsset && !$mobileAsset) continue; 
            
            // Logic to wrap media in link if link exists but NO button
            $wrapLink = !empty($banLink) && empty($banBtn);
        ?>
        <section class="banner-section w-full relative my-10 px-20 md:px-40 py-10">
            <?php if ($wrapLink): ?><a href="<?php echo htmlspecialchars($banLink); ?>" class="block"><?php endif; ?>

            <!-- Desktop Media -->
            <?php if($isDesktopVideo): ?>
                <video src="<?php echo htmlspecialchars($desktopAsset); ?>" autoplay muted loop playsinline class="block w-full h-auto object-cover <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>"></video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($desktopAsset); ?>" class="block w-full h-auto <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>" alt="Banner">
            <?php endif; ?>
            
            <!-- Mobile Media -->
            <?php if($mobileAsset): ?>
                <?php if($isMobileVideo): ?>
                    <video src="<?php echo htmlspecialchars($mobileAsset); ?>" autoplay muted loop playsinline class="block w-full h-auto object-cover md:hidden"></video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($mobileAsset); ?>" class="block w-full h-auto md:hidden" alt="Banner Mobile">
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($wrapLink): ?></a><?php endif; ?>
    
            <?php if($banHead || $banText || $banBtn): ?>
            <div class="absolute inset-0 flex items-center justify-center text-center p-6 bg-black bg-opacity-20 hover:bg-opacity-30 transition pointer-events-none">
                <div class="text-white max-w-2xl px-4 pointer-events-auto">
                    <?php if($banHead): ?>
                    <h2 class="text-4xl md:text-6xl font-bold mb-4 drop-shadow-md"><?php echo htmlspecialchars($banHead); ?></h2>
                    <?php endif; ?>
                    
                    <?php if($banText): ?>
                    <p class="text-lg md:text-xl mb-8 drop-shadow leading-relaxed"><?php echo nl2br(htmlspecialchars($banText)); ?></p>
                    <?php endif; ?>
                    
                    <?php if($banBtn): ?>
                    <a href="<?php echo $banLink ? htmlspecialchars($banLink) : '#'; ?>" class="inline-block bg-white text-gray-900 border-2 border-white px-8 py-3 rounded-full font-bold uppercase tracking-wider hover:bg-transparent hover:text-white transition transform hover:-translate-y-1 shadow-lg">
                        <?php echo htmlspecialchars($banBtn); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endforeach;
        $sections['secBanner'] = ob_get_clean();
    }

    // 3. Why Section
    if ($landingPage['show_why']) {
        $whyTitle = $landingPage['why_title'] ?: 'Why Us?';
        $whyData = json_decode($landingPage['why_data'] ?? '[]', true);
        if (empty($whyData)) {
            $whyData = [
                ['icon' => 'fas fa-leaf', 'title' => 'Natural', 'desc' => '100% natural ingredients sourced responsibly.'],
                ['icon' => 'fas fa-ban', 'title' => 'No Chemicals', 'desc' => 'Free from harmful chemicals and parabens.'],
                ['icon' => 'fas fa-seedling', 'title' => 'Organic', 'desc' => 'Certified organic compounds for your skin.']
            ];
        }
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 why-section">
            <div class="container mx-auto px-6 text-center">
                <h2 class="text-5xl font-heading uppercase tracking-widest mb-6"><?php echo htmlspecialchars($whyTitle); ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-16 max-w-6xl mx-auto mt-20">
                    <?php foreach ($whyData as $why): ?>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 mb-8 text-5xl accent-color">
                            <i class="<?php echo htmlspecialchars($why['icon']); ?>"></i>
                        </div>
                        <h3 class="font-bold text-2xl mb-4"><?php echo htmlspecialchars($why['title']); ?></h3>
                        <p class="text-base leading-relaxed max-w-sm opacity-70"><?php echo htmlspecialchars($why['desc']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php $sections['secWhy'] = ob_get_clean();
    }

    // 4. About Section
    if ($landingPage['show_about']) {
        $aboutTitle = $landingPage['about_title'] ?: 'About Us';
        $aboutText = $landingPage['about_text'] ?: ($landingPage['hero_description'] ?: $productData['description']);
        
        // Image Logic: Manual override OR next in gallery
        if (!empty($landingPage['about_image'])) {
            $aboutImage = (strpos($landingPage['about_image'], 'http') === 0 ? $landingPage['about_image'] : $baseUrl . '/' . $landingPage['about_image']);
        } else {
            $aboutImage = $galleryPool[$currentImgIdx % $galleryCount];
            $currentImgIdx++;
        }
        
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 about-section overflow-hidden">
            <div class="container mx-auto px-6">
                <div class="flex flex-col lg:flex-row items-center gap-20">
                    <div class="lg:w-1/2">
                        <h2 class="text-5xl font-heading uppercase tracking-widest mb-10"><?php echo htmlspecialchars($aboutTitle); ?></h2>
                        <div class="text-lg leading-8 space-y-6 opacity-80 prose prose-lg max-w-none" style="color: inherit;">
                            <?php echo $aboutText; ?>
                        </div>

                        <div class="product-price-container mb-6 flex items-center gap-3">
                            <span class="text-xl font-bold text-gray-500 uppercase">Price:</span>
                            <?php if ($hasSale): ?>
                                <span class="text-xl font-bold text-red-600 line-through opacity-70"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                                <span class="text-xl font-bold text-gray-900"><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded ml-2">-<?php echo round((($originalPrice - $currentSalePrice) / $originalPrice) * 100); ?>% OFF</span>
                            <?php else: ?>
                                <span class="text-xl font-bold text-gray-900"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-6">
                             <button onclick="spAddToCart(<?php echo $productData['id']; ?>, this)" class="btn-accent px-10 py-4 rounded text-lg font-medium tracking-wide uppercase transition shadow-lg hover:shadow-xl" data-loading-text="Adding...">
                                Shop Now
                            </button>
                        </div>
                    </div>
                    <div class="lg:w-1/2 relative">
                         <img src="<?php echo htmlspecialchars($aboutImage); ?>" alt="About" class="w-full max-w-xl mx-auto object-cover rounded shadow-2xl bg-white p-6">
                    </div>
                </div>
            </div>
        </section>
        <?php $sections['secAbout'] = ob_get_clean();
    }

    // 5. Testimonials Section
    if ($landingPage['show_testimonials']) {
        $testimonialsTitle = $landingPage['testimonials_title'] ?: 'Testimonials';
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 testi-section border-t border-gray-100">
             <div class="container mx-auto px-6 text-center">
                <h2 class="text-5xl font-heading font-thin mb-20 uppercase tracking-widest"><?php echo htmlspecialchars($testimonialsTitle); ?></h2>
                <div id="testimonialsList" class="mx-auto">
                    <div class="text-center text-gray-500 py-12 text-xl">Loading reviews...</div>
                </div>
             </div>
        </section>
        <?php $sections['secTesti'] = ob_get_clean();
    }

    // 6. Newsletter Section
    if ($landingPage['show_newsletter']) {
        $newsletterTitle = $landingPage['newsletter_title'] ?: 'Subscribe News';
        $newsletterText = $landingPage['newsletter_text'] ?: 'Enter your email below.';
        
        // Image Logic: Next in gallery
        $newsImage = $galleryPool[$currentImgIdx % $galleryCount];
        $currentImgIdx++;

        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 news-section">
            <div class="container mx-auto px-4 md:px-6">
                <div class="flex flex-col md:flex-row items-center justify-between max-w-6xl mx-auto bg-white p-6 md:p-12 lg:p-16 rounded-2xl shadow-lg border border-gray-100/50">
                    <!-- Image / Icon -->
                    <div class="w-full md:w-5/12 mb-8 md:mb-0 md:pr-10 border-b md:border-b-0 md:border-r border-gray-100 flex justify-center pb-8 md:pb-0">
                         <img src="<?php echo htmlspecialchars($newsImage); ?>" alt="Newsletter" class="w-100 md:w-100 h-auto object-contain drop-shadow-md hover:scale-105 transition duration-300">
                    </div>
                    
                    <!-- Content -->
                    <div class="w-full md:w-7/12 md:pl-10 lg:pl-16 text-center md:text-left">
                         <h2 class="text-2xl md:text-3xl lg:text-4xl font-heading mb-4 text-gray-800 font-bold tracking-tight"><?php echo htmlspecialchars($newsletterTitle); ?></h2>
                         <p class="text-gray-500 text-base md:text-lg mb-8 leading-relaxed max-w-lg mx-auto md:mx-0"><?php echo htmlspecialchars($newsletterText); ?></p>
                         
                         <form id="landingNewsletterForm" class="flex flex-col sm:flex-row w-full gap-3 sm:gap-0">
                             <input type="email" name="email" placeholder="Enter your email" required class="w-full flex-grow px-5 py-3 md:px-6 md:py-4 bg-gray-50 border border-gray-200 rounded-lg sm:rounded-r-none focus:outline-none text-base md:text-lg focus:ring-2 focus:ring-gray-200 transition">
                              <button type="submit" class="w-full sm:w-auto btn-accent px-8 py-3 md:py-4 rounded-lg sm:rounded-l-none text-sm md:text-base font-bold uppercase transition whitespace-nowrap shadow-md hover:shadow-lg transform active:scale-95" data-loading-text="Subscribing...">
                                Get Started
                              </button>
                         </form>
                         
                         <!-- Message Container -->
                         <div id="landingNewsletterMessage" class="hidden text-center text-sm mt-3"></div>
                         
                         <p class="text-xs text-gray-400 mt-4"><i class="fas fa-lock mr-1"></i> Your privacy is our priority.</p>
                    </div>
                </div>
            </div>
            <script>
            document.getElementById('landingNewsletterForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button');
                if (btn) setBtnLoading(btn, true);
                
                // Hide any previous message
                if (messageDiv) {
                    messageDiv.classList.add('hidden');
                }
                
                const email = this.querySelector('input[name="email"]').value;
                const baseUrl = '<?php echo $baseUrl; ?>';
                
                try {
                    const response = await fetch(`${baseUrl}/api/subscribe.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        this.reset();
                        if (messageDiv) {
                            messageDiv.textContent = data.message || 'Successfully subscribed!';
                            messageDiv.className = 'text-center text-sm mt-3 text-green-600 bg-green-50 py-2 px-4 rounded';
                            messageDiv.classList.remove('hidden');
                            
                            // Auto-hide after 5 seconds
                            setTimeout(() => {
                                messageDiv.classList.add('hidden');
                            }, 5000);
                        }
                    } else {
                        if (messageDiv) {
                            messageDiv.textContent = data.message || 'Error subscribing.';
                            messageDiv.className = 'text-center text-sm mt-3 text-red-600 bg-red-50 py-2 px-4 rounded';
                            messageDiv.classList.remove('hidden');
                            
                            // Auto-hide after 5 seconds
                            setTimeout(() => {
                                messageDiv.classList.add('hidden');
                            }, 5000);
                        }
                    }
                } catch (err) {
                    console.error(err);
                    if (messageDiv) {
                        messageDiv.textContent = 'An error occurred.';
                        messageDiv.className = 'text-center text-sm mt-3 text-red-600 bg-red-50 py-2 px-4 rounded';
                        messageDiv.classList.remove('hidden');
                        
                        // Auto-hide after 5 seconds
                        setTimeout(() => {
                            messageDiv.classList.add('hidden');
                        }, 5000);
                    }
                } finally {
                    if (btn) setBtnLoading(btn, false);
                }
            });
            </script>
        </section>
        <?php $sections['secNews'] = ob_get_clean();
    }

    // --- RENDER IN ORDER ---
    $savedOrder = [];
    if (!empty($landingPage['section_order'])) {
        $savedOrder = json_decode($landingPage['section_order'], true);
    }
    
    // Default fallback order if empty (same as previous sequence)
    $defaultOrder = ['secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews'];
    
    // Ensure all enabled sections are present in final order
    $finalOrder = is_array($savedOrder) ? $savedOrder : [];
    foreach ($defaultOrder as $k) {
        if (!in_array($k, $finalOrder)) $finalOrder[] = $k;
    }

    // Output Sections
    foreach ($finalOrder as $secId) {
        if (isset($sections[$secId])) {
            echo $sections[$secId];
        }
    }
    ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
    <?php if ($landingPage['show_testimonials']): ?>
    (function() {
        // Data Source: Manual or API
        const manualReviews = <?php echo !empty($landingPage['testimonials_data']) ? $landingPage['testimonials_data'] : 'null'; ?>;
        const container = document.getElementById('testimonialsList');
        const productId = '<?php echo $productData['id']; ?>';
        const baseUrl = '<?php echo $baseUrl; ?>';

        const renderReviews = (reviews) => {
            if (!reviews || reviews.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-400 text-lg w-full">No reviews yet.</p>';
                return;
            }

            const createCard = (review, isSlide = false) => {
                const name = review.name || review.user_name || 'Anonymous';
                const comment = review.comment || '';
                
                // Determine Avatar
                let imgSrc = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
                if (review.image) {
                     imgSrc = review.image.startsWith('http') ? review.image : `${baseUrl}/${review.image}`;
                }

                return `
                    <div class="${isSlide ? 'swiper-slide h-auto' : 'h-full'}">
                        <div class="flex flex-col items-start bg-gray-50 p-10 rounded-xl relative text-left h-full">
                            <i class="fas fa-quote-left text-gray-200 text-5xl mb-6"></i>
                            <p class="text-base text-gray-500 mb-6 italic leading-loose flex-grow">"${escapeHtml(comment)}"</p>
                            <div class="flex items-center mt-auto w-full pt-4 border-t border-gray-100">
                                <img src="${imgSrc}" class="w-12 h-12 rounded-full mr-4 shadow-sm object-cover">
                                <span class="font-bold text-lg text-gray-800">${escapeHtml(name)}</span>
                            </div>
                        </div>
                    </div>
                `;
            };

            const isSlider = reviews.length > 3;

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
                 alt="Sticky Bar Product" 
                 class="w-10 h-10 md:w-12 md:h-12 object-contain rounded border border-gray-100 flex-shrink-0"
                 onerror="this.src='https://via.placeholder.com/100x100?text=Product'">
            <div class="min-w-0">
                <h3 class="font-bold text-gray-900 leading-tight text-sm md:text-base truncate"><?php echo htmlspecialchars($heroTitle); ?></h3>
                <div class="hidden md:flex text-xs text-yellow-500 items-center mt-1">
                    <?php 
                    $rating = floatval($avgRating ?? 5);
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
                 <div class="font-bold text-lg text-gray-900">
                     <?php if ($hasSale): ?>
                        <span class="text-red-500 line-through text-xs mr-1"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                     <?php else: ?>
                        <span><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                     <?php endif; ?>
                 </div>
            </div>
            
             <div class="hidden md:flex items-center border border-gray-300 rounded-md w-24 h-10 overflow-hidden bg-white">
                <button onclick="updateStickyQty(-1)" class="w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 transition">-</button>
                <input type="number" id="sticky-qty" value="1" min="1" class="flex-1 w-full text-center border-none focus:ring-0 p-0 text-gray-900 font-semibold appearance-none bg-transparent h-full text-sm" readonly>
                <button onclick="updateStickyQty(1)" class="w-8 h-full flex items-center justify-center text-gray-600 hover:bg-gray-100 transition">+</button>
            </div>

            <button onclick="stickyAddToCart()" class="bg-black text-white px-4 py-2.5 md:px-8 rounded-full font-bold hover:bg-gray-800 transition shadow-lg transform hover:-translate-y-0.5 text-sm md:text-base whitespace-nowrap" id="sticky-atc-btn">
                Add To Cart
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('scroll', function() {
    const stickyBar = document.getElementById('sticky-bar');
    const heroSection = document.querySelector('.hero-section'); 
    const footer = document.querySelector('footer');
    
    if (!stickyBar || !heroSection) return;
    
    const scrollY = window.scrollY;
    // Calculate trigger point: roughly passed the hero section
    const heroHeight = heroSection.offsetHeight;
    const heroBottom = heroSection.offsetTop + heroHeight;
    const triggerPoint = heroBottom - 200; 
    
    // Check if we hit footer
    let footerTop = document.documentElement.scrollHeight; 
    if(footer) footerTop = footer.offsetTop;
    
    // Show if passed hero AND not yet at footer (with buffer)
    if (scrollY > (heroBottom - 100) && (scrollY + window.innerHeight) < (footerTop + 50)) {
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
}

function stickyAddToCart() {
    const btn = document.getElementById('sticky-atc-btn');
    const qty = parseInt(document.getElementById('sticky-qty').value) || 1;
    const productId = <?php echo $productData['id']; ?>;
    
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;

        if (typeof spAddToCart === 'function') {
           // Direct fetch to support quantity not supported by default spAddToCart
            fetch(`${LANDING_BASE_URL}/api/cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const countEls = document.querySelectorAll('.cart-count');
                    countEls.forEach(el => el.textContent = data.cartCount);
                    if (typeof openSideCart === 'function') {
                        openSideCart(data.cart);
                    } else {
                        const cartBtn = document.getElementById('cartBtn');
                        if(cartBtn) cartBtn.click();
                        else console.log('Product added to cart!');
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

<!-- SEO Content / Footer Extra -->
<?php if (!empty($landingPage['show_footer_extra']) && !empty($landingPage['footer_extra_content'])): 
    $footerBg = $landingPage['footer_extra_bg'] ?? '#f8f9fa';
    $footerText = $landingPage['footer_extra_text'] ?? '#333333';
?>
<section class="pt-10 pb-20 md:py-24 border-t border-gray-100 relative z-10" style="background-color: <?php echo $footerBg; ?>; color: <?php echo $footerText; ?>;">
    <div class="container mx-auto px-4">
        <div class="prose max-w-none leading-relaxed" style="--tw-prose-body: <?php echo $footerText; ?>; --tw-prose-headings: <?php echo $footerText; ?>; --tw-prose-links: <?php echo $footerText; ?>; color: <?php echo $footerText; ?>;">
            <?php echo $landingPage['footer_extra_content']; ?>
        </div>
    </div>
</section>
<?php endif; ?>
