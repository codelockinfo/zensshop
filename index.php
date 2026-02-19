<?php
// Disable error reporting in production for better performance
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<?php
// --- VISIBILITY CONFIGURATION ---
function getSectionConfig($file) {
    $path = __DIR__ . '/admin/' . $file;
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

// 1. Banner
$bannerConfigPath = __DIR__ . '/admin/banner_config.json';
$bannerConf = file_exists($bannerConfigPath) ? json_decode(file_get_contents($bannerConfigPath), true) : [];
$showBanner = $bannerConf['show_section'] ?? true;
$bannerHideTextMobile = $bannerConf['hide_text_mobile'] ?? false;

// Fetch Banner Styles
$bannerStylesJson = $settingsObj->get('banner_styles', '{"heading_color":"#ffffff","subheading_color":"#f3f4f6","button_bg_color":"#ffffff","button_text_color":"#000000","arrow_bg_color":"#ffffff","arrow_icon_color":"#1f2937"}');
$bannerStyles = json_decode($bannerStylesJson, true);

// Fetch Best Selling Styles for Skeleton
$bsStylesJson = $settingsObj->get('best_selling_styles', '{"bg_color":"#ffffff"}');
$bsStyles = json_decode($bsStylesJson, true);
$bsBgColor = $bsStyles['bg_color'] ?? '#ffffff';

// Fetch Trending Styles for Skeleton
$trStylesJson = $settingsObj->get('trending_styles', '{"bg_color":"#ffffff"}');
$trStyles = json_decode($trStylesJson, true);
$trBgColor = $trStyles['bg_color'] ?? '#ffffff';

// Fetch Special Offers Styles for Skeleton
$soStylesJson = $settingsObj->get('special_offers_styles', '{"bg_color":"#f3f4f6"}'); // Default gray-100 is f3f4f6
$soStyles = json_decode($soStylesJson, true);
$soBgColor = $soStyles['bg_color'] ?? '#f3f4f6';

// Fetch Newsletter Styles for Skeleton
$nlStylesJson = $settingsObj->get('newsletter_styles', '{"bg_color":"#f3f4f6"}');
$nlStyles = json_decode($nlStylesJson, true);
$nlBgColor = $nlStyles['bg_color'] ?? '#f3f4f6';

// Fetch Videos Styles for Skeleton
$vidStylesJson = $settingsObj->get('video_section_styles', '{"bg_color":"#ffffff"}');
$vidStyles = json_decode($vidStylesJson, true);
$vidBgColor = $vidStyles['bg_color'] ?? '#ffffff';

// Fetch Philosophy Styles for Skeleton (From DB) - Now moved query up
$philRow = $db->fetchOne("SELECT active, background_color FROM philosophy_section WHERE store_id = ? LIMIT 1", [CURRENT_STORE_ID]);
$showPhilosophy = $philRow ? ($philRow['active'] == 1) : true;
$philBgColor = $philRow['background_color'] ?? '#384135';

// Fetch Features Styles for Skeleton
// Features uses straightforward settings, not JSON
$featBgColor = $settingsObj->get('features_section_bg', '#ffffff');

// Fetch Footer Features Styles for Skeleton
// Footer Features uses straightforward settings, not JSON
$ffBgColor = $settingsObj->get('footer_features_section_bg', '#ffffff');

// 2. Categories
$catConf = getSectionConfig('category_config.json');
$showCategories = $catConf['show_section'] ?? true;

// 3. Products
$prodConf = getSectionConfig('homepage_products_config.json');
$showBest = $prodConf['show_best_selling_section'] ?? true;
$showTrend = $prodConf['show_trending_section'] ?? true;

// 4. Special Offers
$offerConf = getSectionConfig('special_offers_config.json');
$showOffers = $offerConf['show_section'] ?? true;

// 5. Videos
$vidConf = getSectionConfig('video_config.json');
$showVideos = $vidConf['show_section'] ?? true;

// Fetch Categories Styles for Skeleton
$catStylesJson = $settingsObj->get('homepage_categories_styles', '{"bg_color":"#ffffff"}');
$catStyles = json_decode($catStylesJson, true);
$catBgColor = $catStyles['bg_color'] ?? '#ffffff';

// 6. Newsletter
$newsConf = getSectionConfig('newsletter_config.json');
$showNewsletter = $newsConf['show_section'] ?? true;

// 7. Philosophy (DB) - Fetched above
// $philRow fetching moved up
// $showPhilosophy calculated above

// 8. Features (Settings)
$showFeatures = $settingsObj->get('features_section_visibility', '1') == '1';

// 9. Footer Features (Settings)
$showFooterFeatures = $settingsObj->get('footer_features_section_visibility', '1') == '1';
?>

<?php if ($showBanner): ?>
<!-- Hero Section (Loaded First) -->
<!-- Skeleton Loader for Banner -->
<div id="hero-skeleton" class="relative h-[600px] md:h-[700px] bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 animate-pulse">
    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-50 animate-shimmer"></div>
    <div class="container mx-auto px-4 h-full flex items-center">
        <div class="max-w-md space-y-4">
            <div class="h-4 bg-gray-400 rounded w-32 animate-pulse"></div>
            <div class="h-12 bg-gray-400 rounded w-64 animate-pulse"></div>
            <div class="h-12 bg-gray-400 rounded w-48 animate-pulse"></div>
            <div class="h-10 bg-gray-400 rounded w-40 animate-pulse mt-6"></div>
        </div>
    </div>
</div>

</div>

<style>
    /* Banner Dynamic Styles */
    #hero-section .banner-heading {
        color: <?php echo $bannerStyles['heading_color']; ?> !important;
    }
    #hero-section .banner-subheading {
        color: <?php echo $bannerStyles['subheading_color']; ?> !important;
    }
    #hero-section .banner-btn {
        background-color: <?php echo $bannerStyles['button_bg_color']; ?> !important;
        color: <?php echo $bannerStyles['button_text_color']; ?> !important;
        border-color: <?php echo $bannerStyles['button_bg_color']; ?> !important;
    }
    #hero-section .banner-btn:hover {
        opacity: 0.9;
    }
    #hero-section .hero-prev,
    #hero-section .hero-next {
        background-color: <?php echo $bannerStyles['arrow_bg_color']; ?> !important;
        color: <?php echo $bannerStyles['arrow_icon_color']; ?> !important;
        border-color: <?php echo $bannerStyles['arrow_bg_color']; ?> !important;
    }
     #hero-section .hero-prev:hover,
    #hero-section .hero-next:hover {
        opacity: 0.9 !important;
    }
    
    <?php if($bannerHideTextMobile): ?>
    @media (max-width: 768px) {
        #hero-section .banner-heading,
        #hero-section .banner-subheading {
            display: none !important;
        }
    }
    <?php endif; ?>
</style>

<section id="hero-section" class="relative overflow-hidden" style="display: none;">
<?php
// Fetch banners from database (Store Specific)
$banners = $db->fetchAll("SELECT * FROM banners WHERE active = 1 AND (store_id = ? OR store_id IS NULL) ORDER BY display_order ASC", [CURRENT_STORE_ID]);

// Fallback to default banners if none exist
// Fallback banners removed per user request
if (empty($banners)) {
    // If no banners, we can either return or show nothing. 
    // Since this is the first section, let's just ensure $banners is empty so the foreach doesn't run.
    $banners = [];
}
?>
    <div class="hero-slider relative">
        <div class="hero-view-port">
            <div class="hero-track">
                <?php foreach ($banners as $index => $banner): ?>
                    <?php 
                    // Helper to resolve link
                    if (!function_exists('resolveBannerLink')) {
                        function resolveBannerLink($url, $base) {
                            if (empty($url)) return '';
                            if (preg_match('/^https?:\/\//', $url)) return $url;
                            if (strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) return $url;
                            return $base . '/' . preg_replace('/\.php($|\?)/', '$1', ltrim($url, '/'));
                        }
                    }
        
                    // Handle image URL
                    $bgImage = getImageUrl($banner['image_desktop'] ?? '');
                    // Only process mobile image if it exists, otherwise leave null to trigger fallback
                    $bgImageMobile = !empty($banner['image_mobile']) ? getImageUrl($banner['image_mobile']) : null;
                    
                    // Handle Links
                    $desktopLink = resolveBannerLink($banner['link'] ?? '', $baseUrl);
                    $mobileLink = resolveBannerLink(($banner['link_mobile'] ?? '') ?: ($banner['link'] ?? ''), $baseUrl);
                    ?>
                    <!-- Slide <?php echo $index + 1; ?> -->
                    <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?> relative h-[600px] md:h-[700px]">
                        <!-- Desktop Image -->
                        <?php if($desktopLink): ?>
                        <a href="<?php echo htmlspecialchars($desktopLink); ?>" class="hidden md:block absolute inset-0 z-0">
                            <img src="<?php echo htmlspecialchars($bgImage); ?>" 
                                 class="w-full h-full object-cover" 
                                 <?php echo $index === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?> 
                                 alt="<?php echo htmlspecialchars($banner['heading'] ?? 'Banner Image'); ?>"
                                 onerror="this.src='https://placehold.co/1200x600?text=Banner+Image'">
                        </a>
                        <?php else: ?>
                        <div class="absolute inset-0 hidden md:block z-0 h-full w-full">
                            <img src="<?php echo htmlspecialchars($bgImage); ?>" 
                                 class="w-full h-full object-cover" 
                                 <?php echo $index === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?> 
                                 alt="<?php echo htmlspecialchars($banner['heading'] ?? 'Banner Image'); ?>"
                                 onerror="this.src='https://placehold.co/1200x600?text=Banner+Image'">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Mobile Image (Fallback to desktop if empty) -->
                        <?php if($mobileLink): ?>
                        <a href="<?php echo htmlspecialchars($mobileLink); ?>" class="md:hidden absolute inset-0 z-0">
                            <img src="<?php echo htmlspecialchars($bgImageMobile ?: $bgImage); ?>" 
                                 class="w-full h-full object-cover" 
                                 <?php echo $index === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?> 
                                 alt="<?php echo htmlspecialchars($banner['heading'] ?? 'Banner Image'); ?>"
                                 onerror="this.src='https://placehold.co/1200x600?text=Banner+Image'">
                        </a>
                        <?php else: ?>
                        <div class="absolute inset-0 md:hidden z-0 h-full w-full">
                            <img src="<?php echo htmlspecialchars($bgImageMobile ?: $bgImage); ?>" 
                                 class="w-full h-full object-cover" 
                                 <?php echo $index === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?> 
                                 alt="<?php echo htmlspecialchars($banner['heading'] ?? 'Banner Image'); ?>"
                                 onerror="this.src='https://placehold.co/1200x600?text=Banner+Image'">
                        </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-black bg-opacity-30"></div>
                        <div class="container mx-auto px-4 h-full flex items-center relative z-10">
                            <div class="max-w-md text-white banner-text-content">
                                <?php if (!empty($banner['subheading'])): ?>
                                    <p class="text-md md:text-lg uppercase tracking-wider mb-2 banner-subheading"><?php echo htmlspecialchars($banner['subheading']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($banner['heading'])): ?>
                                    <h1 class="text-4xl md:text-5xl font-heading font-bold mb-6 banner-heading"><?php echo htmlspecialchars($banner['heading']); ?></h1>
                                <?php endif; ?>
                                
                                <?php if (!empty($banner['button_text'])): ?>
                                    <!-- Desktop Button -->
                                    <?php if($desktopLink): ?>
                                    <a href="<?php echo htmlspecialchars($desktopLink); ?>" class="hidden md:inline-block border border-white px-8 py-3 hover:bg-white hover:text-black transition relative z-10 banner-btn">
                                        <?php echo htmlspecialchars($banner['button_text']); ?>
                                    </a>
                                    <?php endif; ?>
        
                                    <!-- Mobile Button -->
                                    <?php if($mobileLink): ?>
                                    <a href="<?php echo htmlspecialchars($mobileLink); ?>" class="md:hidden inline-block border border-white px-8 py-3 hover:bg-white hover:text-black transition relative z-10 banner-btn">
                                        <?php echo htmlspecialchars($banner['button_text']); ?>
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Navigation Arrows -->
        <?php 
        $showArrows = $bannerConf['show_arrows'] ?? true;
        if (count($banners) > 1 && $showArrows): 
        ?>
        <button class="hero-prev absolute left-2 md:left-4 top-1/2 transform -translate-y-1/2 bg-white/75 hover:bg-white rounded-full w-10 h-10 md:w-12 md:h-12 flex items-center justify-center text-black z-10 transition shadow-lg border border-white backdrop-blur-sm" aria-label="Previous slide" style="display: flex !important; opacity: 1 !important;">
            <i class="fas fa-chevron-left text-sm md:text-base" aria-hidden="true"></i>
        </button>
        <button class="hero-next absolute right-2 md:right-4 top-1/2 transform -translate-y-1/2 bg-white/75 hover:bg-white rounded-full w-10 h-10 md:w-12 md:h-12 flex items-center justify-center text-black z-10 transition shadow-lg border border-white backdrop-blur-sm" aria-label="Next slide" style="display: flex !important; opacity: 1 !important;">
            <i class="fas fa-chevron-right text-sm md:text-base" aria-hidden="true"></i>
        </button>
        
        <!-- Slide Indicators -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 flex items-center space-x-2">
            <?php foreach ($banners as $index => $banner): ?>
                <button class="hero-indicator <?php echo $index === 0 ? 'active' : ''; ?>" aria-label="Go to slide <?php echo $index + 1; ?>" data-slide="<?php echo $index; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<style>
    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
    }

    /* Banner Slider Styles */
    .hero-view-port {
        overflow: hidden;
        position: relative;
        width: 100%;
        height: 600px;
    }
    @media (min-width: 768px) {
        .hero-view-port {
            height: 700px;
        }
    }
    .hero-track {
        display: flex;
        height: 100%;
        transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
        width: 100%;
    }
    .hero-slide {
        min-width: 100%;
        flex-shrink: 0;
        position: relative;
        display: block !important; /* Override current display:none */
        opacity: 1 !important;
    }
    
    /* Section Loading Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translate3d(0, 30px, 0);
        }
        to {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }
    }

    .section-announce {
        animation: fadeInUp 0.8s ease-out forwards;
    }
</style>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}
</style>

<script>
// Hide skeleton and show hero when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const heroSkeleton = document.getElementById('hero-skeleton');
    const heroSection = document.getElementById('hero-section');
    
    if (heroSkeleton && heroSection) {
        // Show hero after a short delay to allow initial render, but only if it has slides
        const hasSlides = heroSection.querySelector('.hero-slide');
        setTimeout(function() {
            heroSkeleton.style.display = 'none';
            if (hasSlides) {
                heroSection.style.display = 'block';
            } else {
                heroSection.style.display = 'none';
            }
        }, 300);
    }
});

// Hero Banner Slider (Infinite + Drag)
document.addEventListener('DOMContentLoaded', function() {
    const track = document.querySelector('.hero-track');
    const originalSlides = document.querySelectorAll('.hero-slide');
    const prevBtn = document.querySelector('.hero-prev');
    const nextBtn = document.querySelector('.hero-next');
    const indicators = document.querySelectorAll('.hero-indicator');
    const sliderContainer = document.querySelector('.hero-slider');
    
    if (originalSlides.length < 2) return; // Don't initialize slider if only 1 or 0 slides

    // Clone first and last slides for infinite loop effect
    const firstClone = originalSlides[0].cloneNode(true);
    const lastClone = originalSlides[originalSlides.length - 1].cloneNode(true);
    
    firstClone.id = 'first-clone';
    lastClone.id = 'last-clone';
    
    track.appendChild(firstClone);
    track.insertBefore(lastClone, originalSlides[0]);
    
    const allSlides = document.querySelectorAll('.hero-slide'); // Re-query including clones
    let currentIndex = 1; // Start at 1 (because of first clone)
    let isDragging = false;
    let startPos = 0;
    let currentTranslate = 0;
    let prevTranslate = 0;
    let slideInterval;
    const totalSlides = originalSlides.length;
    let isTransitioning = false;

    // Set initial position
    track.style.transform = `translateX(-${currentIndex * 100}%)`;

    function getPositionX(event) {
        return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
    }

    // Touch/Mouse Events
    sliderContainer.addEventListener('mousedown', touchStart);
    sliderContainer.addEventListener('touchstart', touchStart, {passive: true});

    sliderContainer.addEventListener('mouseup', touchEnd);
    sliderContainer.addEventListener('mouseleave', () => {
         if(isDragging) touchEnd();
    });
    sliderContainer.addEventListener('touchend', touchEnd);

    sliderContainer.addEventListener('mousemove', touchMove);
    sliderContainer.addEventListener('touchmove', touchMove, {passive: true}); 

    function touchStart(event) {
        // Ignore if clicking on controls
        if (event.target.closest('.hero-prev') || event.target.closest('.hero-next') || event.target.closest('.hero-indicator')) return;
        
        if (isTransitioning) return; // Prevent drag during transition
        stopAutoSlide();
        isDragging = true;
        startPos = getPositionX(event);
        sliderContainer.style.cursor = 'grabbing';
        track.style.transition = 'none';
    }

    function touchMove(event) {
        if (!isDragging) return;
        const currentPosition = getPositionX(event);
        const diff = currentPosition - startPos;
        const movePercent = (diff / sliderContainer.offsetWidth) * 100;
        track.style.transform = `translateX(calc(-${currentIndex * 100}% + ${movePercent}%))`;
    }

    function touchEnd(event) {
        if (!isDragging) return;
        isDragging = false;
        sliderContainer.style.cursor = 'default';
        
        let currentPosition = startPos;
        if (event) {
            if (event.changedTouches && event.changedTouches.length > 0) {
                currentPosition = event.changedTouches[0].clientX;
            } else {
                currentPosition = event.clientX || event.pageX || startPos;
            }
        }
        
        const diff = currentPosition - startPos;
        
        track.style.transition = 'transform 0.5s ease-out';

        // Boundary checks before incrementing
        if (diff < -50) {
            if (currentIndex < allSlides.length - 1) {
                currentIndex++;
            }
        } else if (diff > 50) {
            if (currentIndex > 0) {
                currentIndex--;
            }
        }
        
        updateSlidePosition();
        startAutoSlide();
    }
    
    function updateSlidePosition() {
        if (!track) return;
        isTransitioning = true;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Use a timeout backup in case transitionend failes (e.g. tab inactive)
        const transitionTimeout = setTimeout(() => {
            checkIndex();
        }, 550); // Slightly longer than 0.5s transition

        const onTransitionEnd = () => {
             clearTimeout(transitionTimeout);
             checkIndex();
             track.removeEventListener('transitionend', onTransitionEnd);
        };

        track.addEventListener('transitionend', onTransitionEnd);
        updateIndicators();
    }
    
    function checkIndex() {
        isTransitioning = false;
        track.style.transition = 'none'; // Disable transition for jump
        
        if (currentIndex === 0) {
            currentIndex = allSlides.length - 2;
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
        }
        if (currentIndex === allSlides.length - 1) {
            currentIndex = 1;
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
        }
        
        // Restore transition after small delay for next move
        // We don't necessarily need to restore it immediately, only on next action
        // But safe to restore in a requestAnimationFrame
        requestAnimationFrame(() => {
             // track.style.transition = 'transform 0.5s ease-out'; 
             // Don't restore here per se, individual actions will set it to ease-out or none
        });
    }

    function moveToNextSlide() {
        if (isTransitioning) return;
        if (currentIndex >= allSlides.length - 1) return;
        currentIndex++;
        track.style.transition = 'transform 0.5s ease-out';
        updateSlidePosition();
    }

    function moveToPrevSlide() {
        if (isTransitioning) return;
        if (currentIndex <= 0) return;
        currentIndex--;
        track.style.transition = 'transform 0.5s ease-out';
        updateSlidePosition();
    }
    
    function updateIndicators() {
        let realIndex = currentIndex - 1;
        if (realIndex < 0) realIndex = totalSlides - 1;
        if (realIndex >= totalSlides) realIndex = 0;
        
        indicators.forEach((ind, index) => {
            if (index === realIndex) {
                ind.classList.add('active');
            } else {
                ind.classList.remove('active');
            }
        });
    }

    // Auto Play
    function startAutoSlide() {
        stopAutoSlide();
        slideInterval = setInterval(() => {
            moveToNextSlide();
        }, 5000);
    }

    function stopAutoSlide() {
        clearInterval(slideInterval);
    }
    
    // Controls
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Stop propagation to container
            stopAutoSlide();
            moveToNextSlide();
            startAutoSlide();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
             e.stopPropagation(); // Stop propagation to container
            stopAutoSlide();
            moveToPrevSlide();
            startAutoSlide();
        });
    }
    
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', (e) => {
            e.stopPropagation(); // Stop propagation to container
            stopAutoSlide();
            currentIndex = index + 1; // +1 because of first clone
            track.style.transition = 'transform 0.5s ease-out';
            updateSlidePosition();
            startAutoSlide();
        });
    });

    // Pause on hover
    sliderContainer.addEventListener('mouseenter', stopAutoSlide);
    sliderContainer.addEventListener('mouseleave', startAutoSlide);

    startAutoSlide();
});

</script>


<!-- Sections will be loaded progressively via AJAX -->
<!-- Categories Skeleton -->
<?php if ($showCategories): ?>
<div id="categories-section" class="section-loading">
    <section class="py-16" style="background-color: <?php echo htmlspecialchars($catBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-4 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-4 bg-gray-200 rounded max-w-2xl mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
            <div class="flex flex-wrap justify-center gap-6">
                <?php for($i=0; $i<6; $i++): ?>
                <div class="w-[45%] md:w-[30%] lg:w-[14%] flex flex-col items-center">
                    <div class="w-full aspect-square bg-gray-200 rounded-full mb-4 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-24 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Best Selling Skeleton -->
<?php if ($showBest): ?>
<div id="best-selling-section" class="section-loading">
    <section class="py-14" style="background-color: <?php echo htmlspecialchars($bsBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-4 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-4 bg-gray-200 rounded max-w-2xl mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="bg-white rounded-lg border border-gray-100 p-4 space-y-4 shadow-sm">
                    <div class="w-full h-64 bg-gray-200 rounded relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-1/2 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-6 bg-gray-200 rounded w-1/4 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Special Offers Skeleton -->
<?php if ($showOffers): ?>
<div id="special-offers-section" class="section-loading">
    <section class="py-14" style="background-color: <?php echo htmlspecialchars($soBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="text-center mb-10">
                <div class="h-10 bg-gray-200 rounded w-72 mx-auto mb-3 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-5 bg-gray-200 rounded w-96 mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="h-64 bg-gray-200 rounded-lg relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Videos Skeleton -->
<?php if ($showVideos): ?>
<div id="videos-section" class="section-loading">
    <section class="py-10" style="background-color: <?php echo htmlspecialchars($vidBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="text-center mb-10">
                <div class="h-10 bg-gray-200 rounded w-64 mx-auto mb-3 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-5 bg-gray-200 rounded w-80 mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
            <div class="flex gap-6 overflow-hidden">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="flex-shrink-0 w-full md:w-[calc(33.333%-16px)] lg:w-[calc(25%-18px)] h-[400px] md:h-[600px] bg-gray-200 rounded-lg relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Trending Skeleton -->
<?php if ($showTrend): ?>
<div id="trending-section" class="section-loading">
    <section class="py-14" style="background-color: <?php echo htmlspecialchars($trBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="text-center mb-10">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-4 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-4 bg-gray-200 rounded max-w-2xl mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="bg-white rounded-lg border border-gray-100 p-4 space-y-4 shadow-sm">
                    <div class="w-full h-64 bg-gray-200 rounded relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-3/4 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-1/2 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-6 bg-gray-200 rounded w-1/4 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Philosophy Skeleton -->
<?php if ($showPhilosophy): ?>
<div id="philosophy-section" class="section-loading">
    <section class="py-20" style="background-color: <?php echo htmlspecialchars($philBgColor); ?>;">
        <div class="container mx-auto px-4 text-center">
            <div class="h-10 bg-gray-200 rounded w-3/4 md:w-1/2 mx-auto mb-10 relative overflow-hidden">
                <div class="absolute inset-0 animate-shimmer"></div>
            </div>
            <div class="space-y-4 max-w-5xl mx-auto mb-12">
                <div class="h-5 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                <div class="h-5 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                <div class="h-5 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                <div class="h-5 bg-gray-200 rounded w-4/5 mx-auto relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
            </div>
            <div class="h-4 bg-gray-200 rounded w-40 mx-auto relative overflow-hidden">
                <div class="absolute inset-0 animate-shimmer"></div>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Features Skeleton -->
<?php if ($showFeatures): ?>
<div id="features-section" class="section-loading">
    <section class="py-16" style="background-color: <?php echo htmlspecialchars($featBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <?php for($i=0; $i<3; $i++): ?>
                <div class="flex flex-col items-center p-8 space-y-6 bg-gray-50 rounded shadow-sm">
                    <div class="w-16 h-16 bg-gray-200 rounded-full relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-6 bg-gray-200 rounded w-40 relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="space-y-2 w-full">
                        <div class="h-4 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                        <div class="h-4 bg-gray-200 rounded w-5/6 mx-auto relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<!-- Newsletter Skeleton -->
<?php if ($showNewsletter): ?>
<div id="newsletter-section" class="section-loading">
    <section class="py-20" style="background-color: <?php echo htmlspecialchars($nlBgColor); ?>;">
        <div class="container mx-auto px-4 flex justify-center">
            <div class="bg-white rounded-xl shadow-lg p-10 max-w-2xl w-full text-center">
                <div class="h-8 bg-gray-200 rounded w-64 mx-auto mb-4 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="h-4 bg-gray-200 rounded w-80 mx-auto mb-10 relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
                <div class="flex flex-col md:flex-row gap-3 mb-8">
                    <div class="flex-grow h-12 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    <div class="w-32 h-12 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                </div>
                <div class="h-3 bg-gray-200 rounded w-48 mx-auto relative overflow-hidden">
                    <div class="absolute inset-0 animate-shimmer"></div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>
<!-- Footer Features Skeleton -->
<?php if ($showFooterFeatures): ?>
<div id="footer-features-section" class="section-loading">
    <section class="py-12" style="background-color: <?php echo htmlspecialchars($ffBgColor); ?>;">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 text-center">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="p-8 bg-gray-50 rounded space-y-4">
                    <div class="w-12 h-12 bg-gray-200 rounded-full mx-auto relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="h-6 bg-gray-200 rounded w-32 mx-auto relative overflow-hidden">
                        <div class="absolute inset-0 animate-shimmer"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-4 bg-gray-200 rounded relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3 mx-auto relative overflow-hidden"><div class="absolute inset-0 animate-shimmer"></div></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="<?php echo $baseUrl; ?>/assets/js/lazy-load17.js?v=1" defer></script>