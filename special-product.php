<?php
// Main landing page loader

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
    $landingPage = $db->fetchOne("SELECT * FROM landing_pages ORDER BY id ASC LIMIT 1");
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
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;
$originalPrice = $productData['price'] ?? 0;
$currentSalePrice = $productData['sale_price'] ?? 0;
$hasSale = $currentSalePrice > 0 && $currentSalePrice < $originalPrice;

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

$heroBg = $heroGrp['bg_color'] ?? '#E8F0E9';
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
        '{{url}}' => $baseUrl . '/special-product.php?page=' . $pageSlug,
        '{{image}}' => $mainImage,
        '{{currency}}' => $productData['currency'] ?? 'USD',
        '{{brand}}' => "ZensShop",
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
        "url" => $baseUrl . '/special-product.php?page=' . $pageSlug,
        "image" => [$mainImage],
        "brand" => ["@type" => "Brand", "name" => "ZensShop"],
        "offers" => [
            "@type" => "Offer",
            "price" => (float)$price,
            "priceCurrency" => $productData['currency'] ?? 'USD',
            "availability" => (isset($productData['quantity']) && $productData['quantity'] <= 0) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            "url" => $baseUrl . '/special-product.php?page=' . $pageSlug,
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

    document.addEventListener('DOMContentLoaded', () => {
        // Read More Toggle for Hero Description
        const setupReadMore = (containerId, btnId, fadeId, collapsedHeight) => {
            const container = document.getElementById(containerId);
            const btn = document.getElementById(btnId);
            const fade = document.getElementById(fadeId);
            
            if (container && btn) {
                const isTall = container.scrollHeight > collapsedHeight;
                
                if (!isTall) {
                    btn.style.display = 'none';
                    if(fade) fade.style.display = 'none';
                    container.style.maxHeight = 'none';
                }
                
                btn.addEventListener('click', function() {
                    const isCollapsed = !container.style.maxHeight || container.style.maxHeight !== 'none';
                    
                    if (isCollapsed) {
                        container.style.maxHeight = 'none';
                        if(fade) fade.style.opacity = '0';
                        btn.querySelector('span').textContent = 'Show Less';
                        btn.querySelector('i').style.transform = 'rotate(180deg)';
                    } else {
                        container.style.maxHeight = collapsedHeight + 'px';
                        if(fade) fade.style.opacity = '1';
                        btn.querySelector('span').textContent = 'Read More';
                        btn.querySelector('i').style.transform = 'rotate(0deg)';
                        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        };

        setupReadMore('hero-description-container', 'hero-read-more-btn', 'hero-description-fade', 200);
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
        --body-bg: <?php echo htmlspecialchars($bodyBg); ?>;
        --body-text: <?php echo htmlspecialchars($bodyText); ?>;

        --hero-bg: <?php echo htmlspecialchars(($heroGrp['bg_color'] ?? '') ?: '#f9fafb'); ?>;
        --hero-text: <?php echo htmlspecialchars(($heroGrp['text_color'] ?? '') ?: '#111827'); ?>;

        --banner-bg: <?php echo htmlspecialchars(($bannerGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --banner-text: <?php echo htmlspecialchars(($bannerGrp['text_color'] ?? '') ?: '#000000'); ?>;
        
        --stats-bg: <?php echo htmlspecialchars(($statsGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --stats-text: <?php echo htmlspecialchars(($statsGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --why-bg: <?php echo htmlspecialchars(($whyGrp['bg_color'] ?? '') ?: '#f9fafb'); ?>;
        --why-text: <?php echo htmlspecialchars(($whyGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --about-bg: <?php echo htmlspecialchars(($aboutGrp['bg_color'] ?? '') ?: '#ffffff'); ?>;
        --about-text: <?php echo htmlspecialchars(($aboutGrp['text_color'] ?? '') ?: '#111827'); ?>;
        
        --testi-bg: <?php echo htmlspecialchars(($testiGrp['bg_color'] ?? '') ?: '#f9fafb'); ?>;
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
                    <div id="hero-description-container" class="relative overflow-hidden transition-all duration-500 prose prose-lg max-w-none" style="max-height: 200px; color: inherit;">
                        <div class="opacity-80 leading-relaxed">
                            <?php echo $heroDescription; ?>
                        </div>
                        <div id="hero-description-fade" class="absolute bottom-0 left-0 w-full h-16 pointer-events-none transition-opacity duration-300" style="background: linear-gradient(to top, var(--hero-bg), transparent);"></div>
                    </div>
                    <button id="hero-read-more-btn" class="mt-4 text-sm font-bold uppercase tracking-widest hover:opacity-70 transition-all flex items-center gap-2" style="color: var(--theme-color);">
                        <span>Read More</span>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                </div>

                <div class="product-price-container mb-8 flex items-center gap-4 justify-center lg:justify-start flex-wrap">
                    <span class="text-2xl font-extrabold text-[#707b8e] uppercase tracking-tight">Price:</span>
                    <?php if ($hasSale): ?>
                        <span class="text-2xl font-bold text-[#e15a5a] line-through opacity-80"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="text-3xl font-black text-[#000000]"><?php echo format_price($currentSalePrice, $productData['currency'] ?? 'INR'); ?></span>
                        <span class="bg-[#ef4444] text-white text-[12px] font-black px-3 py-1 rounded-md shadow-sm">-<?php echo round((($originalPrice - $currentSalePrice) / $originalPrice) * 100); ?>% OFF</span>
                    <?php else: ?>
                        <span class="text-3xl font-black text-[#000000]"><?php echo format_price($originalPrice, $productData['currency'] ?? 'INR'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center gap-4 justify-center lg:justify-start flex-wrap">
                    <button onclick="spAddToCart(<?php echo $productData['product_id']; ?>, this)" class="btn-accent px-10 py-[17px] rounded text-lg font-medium tracking-wide uppercase transition shadow-xl hover:shadow-2xl transform hover:-translate-y-1" data-loading-text="Adding...">
                        Add to your cart
                    </button>

                        <?php foreach ($platformItems as $plat): 
                            $pLink = $plat['link'] ?? '#';
                            $pImg = !empty($plat['image']) ? getImageUrl($plat['image']) : '';
                            $pName = $plat['name'] ?? 'Buy Now';
                            $pBg = $plat['bg'] ?? '#ffffff';
                            $pText = $plat['text'] ?? '#111827';
                        ?>
                        <a href="<?php echo htmlspecialchars($pLink); ?>" target="_blank" class="h-[58px] px-6 rounded flex items-center gap-3 shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1 group border border-gray-100" style="background-color: <?php echo $pBg; ?>; color: <?php echo $pText; ?>;" title="Buy on <?php echo htmlspecialchars($pName); ?>">
                            <?php if ($pImg): ?>
                                <img src="<?php echo htmlspecialchars($pImg); ?>" alt="<?php echo htmlspecialchars($pName); ?>" class="h-7 md:h-8 w-auto object-contain">
                            <?php endif; ?>
                            <span class="font-bold uppercase text-[11px] tracking-widest" style="color: <?php echo $pText; ?>;"><?php echo htmlspecialchars($pName); ?></span>
                        </a>
                        <?php endforeach; ?>
                </div>
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
    $showStats = $statsGrp['show'] ?? ($landingPage['show_stats'] ?? 1);
    if ($showStats) {
        $statsItems = $statsGrp['items'] ?? [];
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 stats-section">
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
            if($desktopAsset && strpos($desktopAsset, 'http') !== 0) $desktopAsset = $baseUrl . '/' . $desktopAsset;
            
            $mobileAsset = $banMobVideo ?: $banMobImg;
            $isMobileVideo = !empty($banMobVideo) || ($mobileAsset && preg_match('/\.(mp4|webm|ogg)$/i', $mobileAsset));
            if($mobileAsset && strpos($mobileAsset, 'http') !== 0) $mobileAsset = $baseUrl . '/' . $mobileAsset;
            
            if (!$desktopAsset && !$mobileAsset) continue; 
            $wrapLink = !empty($banLink) && empty($banBtn);
        ?>
        <section class="banner-section w-full relative my-10 px-20 md:px-40 py-10">
            <?php if ($wrapLink): ?><a href="<?php echo htmlspecialchars($banLink); ?>" class="block"><?php endif; ?>
            <?php if($isDesktopVideo): ?>
                <video src="<?php echo htmlspecialchars($desktopAsset); ?>" autoplay muted loop playsinline class="block w-full h-auto object-cover <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>"></video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($desktopAsset); ?>" class="block w-full h-auto <?php echo $mobileAsset ? 'hidden md:block' : ''; ?>" alt="Banner">
            <?php endif; ?>
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
        $whyTitle = $whyGrp['title'] ?? 'Why Us?';
        $whyItems = $whyGrp['items'] ?? [];
        if (empty($whyItems)) {
            $whyItems = [
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
                    <?php foreach ($whyItems as $why): ?>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 mb-8 text-5xl accent-color">
                            <i class="<?php echo htmlspecialchars($why['icon'] ?? 'fas fa-check'); ?>"></i>
                        </div>
                        <h3 class="font-bold text-2xl mb-4"><?php echo htmlspecialchars($why['title'] ?? ''); ?></h3>
                        <p class="text-base leading-relaxed max-w-sm opacity-70"><?php echo htmlspecialchars($why['desc'] ?? ''); ?></p>
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
        $aboutTitle = !empty($aboutGrp['title']) ? $aboutGrp['title'] : 'About Our Product';
        $aboutText = !empty($aboutGrp['text']) ? $aboutGrp['text'] : ($productData['description'] ?? '');
        if (!empty($aboutGrp['image'])) {
            $aboutImage = (strpos($aboutGrp['image'], 'http') === 0 ? $aboutGrp['image'] : $baseUrl . '/' . $aboutGrp['image']);
        } else {
            $aboutImage = $galleryPool[$currentImgIdx % $galleryCount];
            $currentImgIdx++;
        }
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 about-section overflow-hidden">
            <div class="container mx-auto px-6">
                <div class="flex flex-col lg:flex-row items-center gap-20">
                    <div class="lg:w-1/2">
                        <h2 class="text-4xl md:text-5xl font-heading mb-8 uppercase tracking-widest"><?php echo htmlspecialchars($aboutTitle); ?></h2>
                        <div class="prose prose-lg opacity-80 leading-relaxed mb-8" style="color: inherit;">
                            <?php echo $aboutText; ?>
                        </div>
                        <div class="mt-6">
                             <button onclick="spAddToCart(<?php echo $productData['product_id']; ?>, this)" class="btn-accent px-10 py-4 rounded text-lg font-medium tracking-wide uppercase transition shadow-lg hover:shadow-xl" data-loading-text="Adding...">Shop Now</button>
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
    $showTesti = $testiGrp['show'] ?? 1;
    if ($showTesti) {
        $testiTitle = $testiGrp['title'] ?? 'Testimonials';
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 testi-section border-t border-gray-100">
             <div class="container mx-auto px-6 text-center">
                <h2 class="text-5xl font-heading font-thin mb-20 uppercase tracking-widest"><?php echo htmlspecialchars($testiTitle); ?></h2>
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
        $newsTitle = $newsGrp['title'] ?? 'Subscribe News';
        $newsText = $newsGrp['text'] ?? 'Enter your email below.';
        $newsImage = $galleryPool[$currentImgIdx % $galleryCount];
        $currentImgIdx++;
        ob_start(); ?>
        <section class="pt-10 pb-20 md:py-24 news-section">
            <div class="container mx-auto px-4 md:px-6">
                <div class="flex flex-col md:flex-row items-center justify-between max-w-6xl mx-auto bg-white p-6 md:p-12 lg:p-16 rounded-2xl shadow-lg border border-gray-100/50">
                    <div class="w-full md:w-5/12 mb-8 md:mb-0 md:pr-10 border-b md:border-b-0 md:border-r border-gray-100 flex justify-center pb-8 md:pb-0">
                         <img src="<?php echo htmlspecialchars($newsImage); ?>" alt="Newsletter" class="w-100 md:w-100 h-auto object-contain drop-shadow-md hover:scale-105 transition duration-300">
                    </div>
                    <div class="w-full md:w-7/12 md:pl-10 lg:pl-16 text-center md:text-left">
                         <h2 class="text-2xl md:text-3xl lg:text-4xl font-heading mb-4 text-gray-800 font-bold tracking-tight"><?php echo htmlspecialchars($newsTitle); ?></h2>
                         <p class="text-gray-500 text-base md:text-lg mb-8 leading-relaxed max-w-lg mx-auto md:mx-0"><?php echo htmlspecialchars($newsText); ?></p>
                         <form id="landingNewsletterForm" class="flex flex-col sm:flex-row w-full gap-3 sm:gap-0">
                             <input type="email" name="email" placeholder="Enter your email" required class="w-full flex-grow px-5 py-3 md:px-6 md:py-4 bg-gray-50 border border-gray-200 rounded-lg sm:rounded-r-none focus:outline-none text-base md:text-lg focus:ring-2 focus:ring-gray-200 transition">
                              <button type="submit" class="w-full sm:w-auto btn-accent px-8 py-3 md:py-4 rounded-lg sm:rounded-l-none text-sm md:text-base font-bold uppercase transition whitespace-nowrap shadow-md hover:shadow-lg transform active:scale-95" data-loading-text="Subscribing...">Get Started</button>
                         </form>
                         <div id="landingNewsletterMessage" class="hidden text-center text-sm mt-3"></div>
                         <p class="text-xs text-gray-400 mt-4"><i class="fas fa-lock mr-1"></i> Your privacy is our priority.</p>
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
                                messageDiv.className = `text-center text-sm mt-3 ${data.success ? 'text-green-600' : 'text-red-600'}`;
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
    const productId = <?php echo $productData['product_id']; ?>;
    
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