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
$mainImage = !empty($landingPage['hero_image']) ? $baseUrl . '/' . $landingPage['hero_image'] : getProductImage($productData);
$images = json_decode($productData['images'] ?? '[]', true);
$price = $productData['sale_price'] ?? $productData['price'] ?? 0;

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
$heroText = getContrastColor($heroBg);

$statsBg = $landingPage['stats_bg_color'] ?: '#F3F6F4';
$statsText = getContrastColor($statsBg);

$whyBg = $landingPage['why_bg_color'] ?: '#FFFFFF';
$whyText = getContrastColor($whyBg);

$aboutBg = $landingPage['about_bg_color'] ?: '#F9F9F9';
$aboutText = getContrastColor($aboutBg);

$testiBg = $landingPage['testimonials_bg_color'] ?: '#FFFFFF';
$testiText = getContrastColor($testiBg);

$newsBg = $landingPage['newsletter_bg_color'] ?: '#E8F0E9';
$newsText = getContrastColor($newsBg);


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

// --- 4. HEADER INJECTION ---
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styles for Landing Page (Injected after body start) -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<script>
    const LANDING_BASE_URL = '<?php echo $baseUrl; ?>';
    
    // Custom Add To Cart for Landing Page
    function spAddToCart(productId) {
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
                const cartBtn = document.getElementById('cartBtn');
                if(cartBtn) cartBtn.click();
                else alert('Product added to cart!');
            }
        })
        .catch(err => console.error(err));
    }
</script>

<style>
    /* Override font-family for this page if needed, or keep inherited */
    body { font-family: 'Outfit', sans-serif; }
    h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }

    /* Dynamic Theme Colors */
    :root {
        --theme-color: <?php echo htmlspecialchars($themeColor); ?>;
        
        --hero-bg: <?php echo htmlspecialchars($landingPage['hero_bg_color'] ?? '#f9fafb'); ?>;
        --hero-text: <?php echo htmlspecialchars($landingPage['hero_text_color'] ?? '#111827'); ?>;
        
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
    <section class="hero-section min-h-screen relative flex items-center pt-25 pb-25">
        <div class="container mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="z-10 order-2 lg:order-1 text-center lg:text-left">
                <h2 class="text-xl font-semibold tracking-widest uppercase mb-4 opacity-60"><?php echo htmlspecialchars($heroSubtitle); ?></h2>
                <h1 class="text-8xl lg:text-9xl font-bold mb-8 tracking-tighter lowercase leading-none">
                    <?php echo htmlspecialchars($heroTitle); ?>
                </h1>
                
                <div class="mb-10 max-w-xl mx-auto lg:mx-0 leading-relaxed text-lg opacity-80">
                    <?php echo nl2br(htmlspecialchars($heroDescription)); ?>
                </div>
                
                <button onclick="spAddToCart(<?php echo $productData['id']; ?>)" class="btn-accent px-10 py-4 rounded text-lg font-medium tracking-wide uppercase transition shadow-xl hover:shadow-2xl transform hover:-translate-y-0.5">
                    Add to your cart — <?php echo format_currency($price); ?>
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
        <section class="py-20 stats-section">
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
            $banHead = $banner['heading'] ?? '';
            $banText = $banner['text'] ?? '';
            $banBtn = $banner['btn_text'] ?? '';
            $banLink = $banner['btn_link'] ?? '';
            
            if($banImg && strpos($banImg, 'http') !== 0) $banImg = $baseUrl . '/' . $banImg;
            if($banMobImg && strpos($banMobImg, 'http') !== 0) $banMobImg = $baseUrl . '/' . $banMobImg;
            
            if (!$banImg) continue; 
            
            // Logic to wrap image in link if link exists but NO button
            $wrapLink = !empty($banLink) && empty($banBtn);
        ?>
        <section class="w-full relative my-10 px-20 md:px-40">
            <?php if ($wrapLink): ?><a href="<?php echo htmlspecialchars($banLink); ?>" class="block"><?php endif; ?>

            <?php if($banImg): ?>
            <img src="<?php echo htmlspecialchars($banImg); ?>" class="block w-full h-auto <?php echo $banMobImg ? 'hidden md:block' : ''; ?>" alt="Banner">
            <?php endif; ?>
            
            <?php if($banMobImg): ?>
            <img src="<?php echo htmlspecialchars($banMobImg); ?>" class="block w-full h-auto md:hidden" alt="Banner Mobile">
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
        <section class="py-32 why-section">
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
        $aboutImage = !empty($landingPage['about_image']) ? (strpos($landingPage['about_image'], 'http') === 0 ? $landingPage['about_image'] : $baseUrl . '/' . $landingPage['about_image']) : $mainImage;
        ob_start(); ?>
        <section class="py-32 about-section overflow-hidden">
            <div class="container mx-auto px-6">
                <div class="flex flex-col lg:flex-row items-center gap-20">
                    <div class="lg:w-1/2">
                        <h2 class="text-5xl font-heading uppercase tracking-widest mb-10"><?php echo htmlspecialchars($aboutTitle); ?></h2>
                        <div class="text-lg leading-8 space-y-6 opacity-80">
                            <p><?php echo nl2br(htmlspecialchars($aboutText)); ?></p>
                        </div>
                        <div class="mt-12">
                             <button onclick="spAddToCart(<?php echo $productData['id']; ?>)" class="btn-accent px-10 py-4 rounded text-lg font-medium tracking-wide uppercase transition shadow-lg hover:shadow-xl">
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
        <section class="py-25 testi-section border-t border-gray-100">
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
        ob_start(); ?>
        <section class="py-25 news-section">
            <div class="container mx-auto px-6">
                <div class="flex flex-col md:flex-row items-center justify-between max-w-6xl mx-auto bg-white p-16 rounded-2xl shadow-lg">
                    <div class="md:w-1/2 mb-10 md:mb-0 pr-10 border-r border-gray-100 flex justify-center">
                         <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="Newsletter" class="w-48 h-auto object-contain">
                    </div>
                    <div class="md:w-1/2 pl-0 md:pl-16 w-full">
                         <h2 class="text-4xl font-heading mb-4 text-gray-800"><?php echo htmlspecialchars($newsletterTitle); ?></h2>
                         <p class="text-gray-500 text-lg mb-8"><?php echo htmlspecialchars($newsletterText); ?></p>
                         <div class="flex w-full">
                             <input type="email" placeholder="Enter your email" class="flex-grow px-6 py-4 bg-gray-50 border border-gray-200 rounded-l focus:outline-none text-lg">
                             <button class="btn-accent px-8 py-4 rounded-r text-base font-bold uppercase transition whitespace-nowrap">Get Started</button>
                         </div>
                    </div>
                </div>
            </div>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
