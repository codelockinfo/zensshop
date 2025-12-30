/**
 * Lazy Load Sections
 * Loads landing page sections progressively via AJAX
 */

const sections = [
    { id: 'categories-section', endpoint: 'categories' },
    { id: 'best-selling-section', endpoint: 'best-selling' },
    { id: 'special-offers-section', endpoint: 'special-offers' },
    { id: 'videos-section', endpoint: 'videos' },
    { id: 'trending-section', endpoint: 'trending' },
    { id: 'philosophy-section', endpoint: 'philosophy' },
    { id: 'newsletter-section', endpoint: 'newsletter' }
];

let currentSectionIndex = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Start loading sections after a short delay
    setTimeout(() => {
        loadNextSection();
    }, 500);
});

function loadNextSection() {
    if (currentSectionIndex >= sections.length) {
        return;
    }
    
    const section = sections[currentSectionIndex];
    loadSection(section.id, section.endpoint);
    currentSectionIndex++;
}

async function loadSection(sectionId, endpoint) {
    const sectionElement = document.getElementById(sectionId);
    if (!sectionElement) return;
    
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '/zensshop';
        const response = await fetch(`${baseUrl}/api/sections.php?section=${endpoint}`);
        const html = await response.text();
        
        if (html) {
            sectionElement.innerHTML = html;
            sectionElement.classList.remove('section-loading');
            sectionElement.classList.add('fade-in');
            
            // Initialize any interactive elements in the loaded section
            initializeSection(sectionElement);
            
            // Load next section after a delay
            setTimeout(() => {
                loadNextSection();
            }, 300);
        } else {
            sectionElement.innerHTML = '<div class="text-center py-8 text-gray-500">Section not available</div>';
            sectionElement.classList.remove('section-loading');
        }
    } catch (error) {
        console.error(`Error loading section ${endpoint}:`, error);
        sectionElement.innerHTML = '<div class="text-center py-8 text-red-500">Error loading section</div>';
        sectionElement.classList.remove('section-loading');
        
        // Continue loading next section even on error
        setTimeout(() => {
            loadNextSection();
        }, 300);
    }
}

function initializeSection(sectionElement) {
    // Initialize add to cart buttons
    const addToCartButtons = sectionElement.querySelectorAll('.add-to-cart-btn');
    addToCartButtons.forEach(button => {
        const productId = button.getAttribute('data-product-id');
        if (productId) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                addToCart(parseInt(productId), 1);
            });
        }
    });
    
    // Initialize Best Selling Slider if this is the best-selling section
    if (sectionElement.id === 'best-selling-section' || sectionElement.querySelector('#bestSellingSlider')) {
        setTimeout(function() {
            initializeBestSellingSlider();
        }, 200);
    }
    
    // Initialize Videos if this is the videos section
    if (sectionElement.id === 'videos-section' || sectionElement.querySelector('.video-card')) {
        setTimeout(function() {
            initializeVideos();
        }, 100);
    }
    
    // Initialize Product Cards (for trending and best-selling sections)
    if (sectionElement.querySelector('.product-card')) {
        setTimeout(function() {
            if (typeof initializeProductCards === 'function') {
                initializeProductCards();
            }
        }, 200);
    }
    
    // Initialize any other interactive elements
    // Add more initialization code as needed
}

function initializeVideos() {
    const videos = document.querySelectorAll('.video-card video');
    videos.forEach((video) => {
        // Ensure video autoplays
        video.setAttribute('autoplay', '');
        video.setAttribute('loop', '');
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        
        // Play video
        video.play().catch(function(error) {
            console.log('Video autoplay prevented:', error);
            // If autoplay fails, video will show poster image
        });
        
        // Ensure video keeps playing
        video.addEventListener('loadeddata', function() {
            video.play().catch(() => {});
        });
    });
}

function initializeBestSellingSlider() {
    // Wait a bit for DOM to be ready and images to load
    setTimeout(function() {
        const slider = document.getElementById('bestSellingSlider');
        const prevBtn = document.getElementById('bestSellingPrev');
        const nextBtn = document.getElementById('bestSellingNext');
        
        if (!slider) {
            console.log('Best selling slider not found');
            return;
        }
        
        if (!prevBtn || !nextBtn) {
            console.log('Best selling navigation buttons not found');
            return;
        }
        
        // Check if slider already initialized
        if (slider.dataset.initialized === 'true') {
            return;
        }
        slider.dataset.initialized = 'true';
        
        let currentIndex = 0;
        
        function getItemsPerView() {
            if (window.innerWidth >= 1280) return 6;
            if (window.innerWidth >= 1024) return 4;
            if (window.innerWidth >= 640) return 2;
            return 1;
        }
        
        function updateSlider() {
            if (!slider.children.length) return;
            
            const itemsPerView = getItemsPerView();
            const totalItems = slider.children.length;
            const maxIndex = Math.max(0, totalItems - itemsPerView);
            
            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }
            
            // Calculate scroll position
            const firstItem = slider.children[0];
            if (!firstItem) return;
            
            const itemWidth = firstItem.offsetWidth || 300;
            const gap = 24; // 1.5rem gap
            const translateX = -currentIndex * (itemWidth + gap);
            
            slider.style.transform = `translateX(${translateX}px)`;
            slider.style.transition = 'transform 0.5s ease-in-out';
            
            // Show/hide navigation buttons
            if (prevBtn) {
                prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
            }
            if (nextBtn) {
                nextBtn.style.display = currentIndex >= maxIndex ? 'none' : 'flex';
            }
        }
        
        // Remove existing event listeners by cloning buttons
        if (prevBtn && nextBtn) {
            const newPrevBtn = prevBtn.cloneNode(true);
            const newNextBtn = nextBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
            nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);
            
            newPrevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });
            
            newNextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemsPerView = getItemsPerView();
                const totalItems = slider.children.length;
                const maxIndex = Math.max(0, totalItems - itemsPerView);
                if (currentIndex < maxIndex) {
                    currentIndex++;
                    updateSlider();
                }
            });
        }
        
        // Handle window resize
        let resizeTimer;
        const resizeHandler = function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                updateSlider();
            }, 250);
        };
        window.addEventListener('resize', resizeHandler);
        
        // Initialize after images load
        const images = slider.querySelectorAll('img');
        let imagesLoaded = 0;
        
        if (images.length === 0) {
            updateSlider();
        } else {
            images.forEach(function(img) {
                if (img.complete) {
                    imagesLoaded++;
                    if (imagesLoaded === images.length) {
                        updateSlider();
                    }
                } else {
                    img.addEventListener('load', function() {
                        imagesLoaded++;
                        if (imagesLoaded === images.length) {
                            updateSlider();
                        }
                    });
                    img.addEventListener('error', function() {
                        imagesLoaded++;
                        if (imagesLoaded === images.length) {
                            updateSlider();
                        }
                    });
                }
            });
        }
        
        // Fallback initialization
        setTimeout(updateSlider, 500);
    }, 300);
}

