<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section (Loaded First) -->
<section id="hero-section" class="relative overflow-hidden">
    <div class="hero-slider relative">
        <!-- Slide 1 -->
        <div class="hero-slide active relative h-[600px] md:h-[700px] bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=1200');">
            <div class="absolute inset-0 bg-black bg-opacity-30"></div>
            <div class="container mx-auto px-4 h-full flex items-center relative z-10">
                <div class="max-w-md text-white">
                    <p class="text-sm uppercase tracking-wider mb-2">NEW ARRIVALS</p>
                    <h1 class="text-5xl md:text-6xl font-heading font-bold mb-6">Get Extra 15% Off</h1>
                    <a href="<?php echo $baseUrl; ?>/shop.php" class="inline-block bg-yellow-50 border border-white px-8 py-3 rounded hover:bg-white hover:text-black transition">
                        Shop Collection
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Slide 2 -->
        <div class="hero-slide relative h-[600px] md:h-[700px] bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=1200');">
            <div class="absolute inset-0 bg-black bg-opacity-30"></div>
            <div class="container mx-auto px-4 h-full flex items-center relative z-10">
                <div class="max-w-md text-white">
                    <p class="text-sm uppercase tracking-wider mb-2">TRENDING NOW</p>
                    <h1 class="text-5xl md:text-6xl font-heading font-bold mb-6">Elegant Jewelry Collection</h1>
                    <a href="<?php echo $baseUrl; ?>/shop.php" class="inline-block bg-yellow-50 border border-white px-8 py-3 rounded hover:bg-white hover:text-black transition">
                        Explore Now
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Slide 3 -->
        <div class="hero-slide relative h-[600px] md:h-[700px] bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=1200');">
            <div class="absolute inset-0 bg-black bg-opacity-30"></div>
            <div class="container mx-auto px-4 h-full flex items-center relative z-10">
                <div class="max-w-md text-white">
                    <p class="text-sm uppercase tracking-wider mb-2">LIMITED TIME</p>
                    <h1 class="text-5xl md:text-6xl font-heading font-bold mb-6">Premium Quality, Best Prices</h1>
                    <a href="<?php echo $baseUrl; ?>/shop.php" class="inline-block bg-yellow-50 border border-white px-8 py-3 rounded hover:bg-white hover:text-black transition">
                        Shop Now
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Navigation Arrows -->
        <button class="hero-prev absolute left-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full w-12 h-12 flex items-center justify-center text-black z-20 transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="hero-next absolute right-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-75 rounded-full w-12 h-12 flex items-center justify-center text-black z-20 transition">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <!-- Slide Indicators -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 flex space-x-2">
            <button class="hero-indicator w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-75 transition" data-slide="0"></button>
            <button class="hero-indicator w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-75 transition" data-slide="1"></button>
            <button class="hero-indicator w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-75 transition" data-slide="2"></button>
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
    
    if (slides.length === 0) return;
    
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(index) {
        // Hide all slides
        slides.forEach((slide, i) => {
            slide.classList.remove('active');
            slide.style.display = 'none';
            if (indicators[i]) {
                indicators[i].classList.remove('bg-white');
                indicators[i].classList.add('bg-opacity-50');
            }
        });
        
        // Show current slide
        if (slides[index]) {
            slides[index].classList.add('active');
            slides[index].style.display = 'block';
            if (indicators[index]) {
                indicators[index].classList.add('bg-white');
                indicators[index].classList.remove('bg-opacity-50');
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
        slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
    }
    
    function stopAutoSlide() {
        clearInterval(slideInterval);
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

<section id="newsletter-section" class="section-loading">
    <div class="loading-spinner"></div>
</section>

<script src="<?php echo $baseUrl; ?>/assets/js/lazy-load.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
