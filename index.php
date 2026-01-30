<?php
// Disable error reporting in production for better performance
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section (Loaded First) -->
<section id="hero-section" class="relative overflow-hidden">
<?php
// Fetch banners from database
$banners = $db->fetchAll("SELECT * FROM banners WHERE active = 1 AND store_id = ? ORDER BY display_order ASC", [$storeId]);

// Fallback to default banners if none exist
if (empty($banners)) {
    $banners = [
        [
            'image_desktop' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=1200',
            'image_mobile' => '',
            'subheading' => 'NEW ARRIVALS',
            'heading' => 'Get Extra 15% Off',
            'button_text' => 'Shop Collection',
            'link' => url('shop')
        ],
        [
            'image_desktop' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=1200',
            'image_mobile' => '',
            'subheading' => 'TRENDING NOW',
            'heading' => 'Elegant Jewelry Collection',
            'button_text' => 'Explore Now',
            'link' => url('shop')
        ],
        [
            'image_desktop' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=1200',
            'image_mobile' => '',
            'subheading' => 'LIMITED TIME',
            'heading' => 'Premium Quality, Best Prices',
            'button_text' => 'Shop Now',
            'link' => url('shop')
        ]
    ];
}
?>
    <div class="hero-slider relative">
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
            $bgImage = $banner['image_desktop'];
            if (!preg_match('/^https?:\/\//', $bgImage)) {
                $bgImage = $baseUrl . '/' . ltrim($bgImage, '/');
            }
            
            $bgImageMobile = $banner['image_mobile'] ?? '';
            if ($bgImageMobile && !preg_match('/^https?:\/\//', $bgImageMobile)) {
                $bgImageMobile = $baseUrl . '/' . ltrim($bgImageMobile, '/');
            }
            
            // Handle Links
            $desktopLink = resolveBannerLink($banner['link'] ?? '', $baseUrl);
            $mobileLink = resolveBannerLink(($banner['link_mobile'] ?? '') ?: ($banner['link'] ?? ''), $baseUrl);
            ?>
            <!-- Slide <?php echo $index + 1; ?> -->
            <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?> relative h-[600px] md:h-[700px]">
                <!-- Desktop Image -->
                <?php if($desktopLink): ?>
                <a href="<?php echo htmlspecialchars($desktopLink); ?>" class="hidden md:block absolute inset-0 z-0">
                    <div class="w-full h-full bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($bgImage); ?>');"></div>
                </a>
                <?php else: ?>
                <div class="absolute inset-0 bg-cover bg-center hidden md:block" style="background-image: url('<?php echo htmlspecialchars($bgImage); ?>');"></div>
                <?php endif; ?>
                
                <!-- Mobile Image (Fallback to desktop if empty) -->
                <?php if($mobileLink): ?>
                <a href="<?php echo htmlspecialchars($mobileLink); ?>" class="md:hidden absolute inset-0 z-0">
                    <div class="w-full h-full bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($bgImageMobile ?: $bgImage); ?>');"></div>
                </a>
                <?php else: ?>
                <div class="absolute inset-0 bg-cover bg-center md:hidden" style="background-image: url('<?php echo htmlspecialchars($bgImageMobile ?: $bgImage); ?>');"></div>
                <?php endif; ?>
                
                <div class="absolute inset-0 bg-black bg-opacity-30"></div>
                <div class="container mx-auto px-4 h-full flex items-center relative z-10">
                    <div class="max-w-md text-white">
                        <?php if (!empty($banner['subheading'])): ?>
                            <p class="text-md md:text-lg uppercase tracking-wider mb-2"><?php echo htmlspecialchars($banner['subheading']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($banner['heading'])): ?>
                            <h1 class="text-4xl md:text-5xl font-heading font-bold mb-6"><?php echo htmlspecialchars($banner['heading']); ?></h1>
                        <?php endif; ?>
                        
                        <?php if (!empty($banner['button_text'])): ?>
                            <!-- Desktop Button -->
                            <?php if($desktopLink): ?>
                            <a href="<?php echo htmlspecialchars($desktopLink); ?>" class="hidden md:inline-block border border-white px-8 py-3 hover:bg-white hover:text-black transition banner-btn relative z-10">
                                <?php echo htmlspecialchars($banner['button_text']); ?>
                            </a>
                            <?php endif; ?>

                            <!-- Mobile Button -->
                            <?php if($mobileLink): ?>
                            <a href="<?php echo htmlspecialchars($mobileLink); ?>" class="md:hidden inline-block border border-white px-8 py-3 hover:bg-white hover:text-black transition banner-btn relative z-10">
                                <?php echo htmlspecialchars($banner['button_text']); ?>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Navigation Arrows -->
        <button class="hero-prev absolute left-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full w-12 h-12 flex items-center justify-center text-black z-20 transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="hero-next absolute right-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full w-12 h-12 flex items-center justify-center text-black z-20 transition">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <!-- Slide Indicators -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 flex items-center space-x-2">
            <?php foreach ($banners as $index => $banner): ?>
                <button class="hero-indicator <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// Hero Banner Slider
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    const prevBtn = document.querySelector('.hero-prev');
    const nextBtn = document.querySelector('.hero-next');
    const indicators = document.querySelectorAll('.hero-indicator');
    const sliderContainer = document.querySelector('.hero-slider');
    
    if (slides.length === 0) return;
    
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(index) {
        // Hide all slides
        slides.forEach((slide, i) => {
            slide.classList.remove('active');
            slide.style.display = 'none';
            if (indicators[i]) {
                indicators[i].classList.remove('active');
            }
        });
        
        // Show current slide
        if (slides[index]) {
            slides[index].classList.add('active');
            slides[index].style.display = 'block';
            if (indicators[index]) {
                indicators[index].classList.add('active');
            }
        }
        
        currentSlide = index;
    }
    
    function nextSlide() {
        const next = (currentSlide + 1) % slides.length;
        showSlide(next);
    }
    
    function prevSlide() {
        const prev = (currentSlide - 1 + slides.length) % slides.length;
        showSlide(prev);
    }
    
    function startAutoSlide() {
        stopAutoSlide(); // Ensure no duplicates
        slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
    }
    
    function stopAutoSlide() {
        if(slideInterval) clearInterval(slideInterval);
    }
    
    // Event listeners
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            stopAutoSlide();
            nextSlide();
            startAutoSlide();
        });
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            stopAutoSlide();
            prevSlide();
            startAutoSlide();
        });
    }
    
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', function() {
            stopAutoSlide();
            showSlide(index);
            startAutoSlide();
        });
    });
    
    // Pause on hover
    const heroSection = document.getElementById('hero-section');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopAutoSlide);
        heroSection.addEventListener('mouseleave', startAutoSlide);
    }

    // Keyboard Navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            stopAutoSlide();
            prevSlide();
            startAutoSlide();
        }
        if (e.key === 'ArrowRight') {
            stopAutoSlide();
            nextSlide();
            startAutoSlide();
        }
    });

    // Swipe and Drag Logic
    if (sliderContainer) {
        let touchStartX = 0;
        let touchEndX = 0;
        let isDragging = false;

        // Touch Events (Mobile)
        sliderContainer.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
            stopAutoSlide();
        }, {passive: true});

        sliderContainer.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
            startAutoSlide();
        }, {passive: true});

        // Mouse Events (Desktop)
        sliderContainer.addEventListener('mousedown', e => {
            isDragging = true;
            touchStartX = e.clientX;
            stopAutoSlide();
            sliderContainer.style.cursor = 'grabbing';
            // Prevent text selection during drag
            e.preventDefault();
        });

        sliderContainer.addEventListener('mousemove', e => {
            if (!isDragging) return;
            // Optional: You could add logic here to visually drag the slide
        });

        sliderContainer.addEventListener('mouseup', e => {
            if (!isDragging) return;
            isDragging = false;
            touchEndX = e.clientX;
            handleSwipe();
            startAutoSlide();
            sliderContainer.style.cursor = 'default';
        });

        sliderContainer.addEventListener('mouseleave', e => {
            if (isDragging) {
                isDragging = false;
                startAutoSlide();
                sliderContainer.style.cursor = 'default';
            }
        });

        function handleSwipe() {
            const threshold = 50; // Minimum distance for swipe
            if (touchEndX < touchStartX - threshold) {
                nextSlide(); // Swiped Left -> Next
            }
            if (touchEndX > touchStartX + threshold) {
                prevSlide(); // Swiped Right -> Prev
            }
        }
    }
    
    // Initialize
    showSlide(0);
    startAutoSlide();
});
</script>


<!-- Sections will be loaded progressively via AJAX -->
<section id="categories-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="best-selling-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="special-offers-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="videos-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="trending-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="philosophy-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="features-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<section id="newsletter-section">
</section>

<script src="<?php echo $baseUrl; ?>/assets/js/lazy-load3.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
