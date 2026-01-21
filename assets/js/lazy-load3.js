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
    { id: 'features-section', endpoint: 'features' },
    { id: 'newsletter-section', endpoint: 'newsletter' }
];

let currentSectionIndex = 0;

document.addEventListener('DOMContentLoaded', function () {
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
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
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
            // Section is empty/inactive - hide it completely
            sectionElement.style.display = 'none';
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
            button.addEventListener('click', function (e) {
                e.preventDefault();
                addToCart(parseInt(productId), 1);
            });
        }
    });

    // Initialize Best Selling Slider if this is the best-selling section
    if (sectionElement.id === 'best-selling-section' || sectionElement.querySelector('#bestSellingSlider')) {
        setTimeout(function () {
            initializeBestSellingSlider();
        }, 200);
    }

    // Initialize Trending Slider if this is the trending section
    if (sectionElement.id === 'trending-section' || sectionElement.querySelector('#trendingSlider')) {
        setTimeout(function () {
            initializeTrendingSlider();
        }, 200);
    }

    // Initialize Videos if this is the videos section
    if (sectionElement.id === 'videos-section' || sectionElement.querySelector('.video-card')) {
        setTimeout(function () {
            initializeVideos();
        }, 100);
    }

    // Initialize Product Cards (for trending and best-selling sections)
    if (sectionElement.querySelector('.product-card')) {
        setTimeout(function () {
            if (typeof initializeProductCards === 'function') {
                initializeProductCards();
            }
        }, 200);
    }

    // Initialize any other interactive elements
    // Add more initialization code as needed

    // Initialize Newsletter Form
    if (sectionElement.id === 'newsletter-section' || sectionElement.querySelector('#globalNewsletterForm')) {
        initializeNewsletterForm(sectionElement);
    }
}

function initializeNewsletterForm(container) {
    const form = container.querySelector('#globalNewsletterForm');
    if (!form) return;

    // Remove any existing listeners by cloning
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        console.log('Global Newsletter Form Submitted');

        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.innerText;
        const messageDiv = document.getElementById('globalNewsletterMessage');

        btn.innerText = 'Subscribing...';
        btn.disabled = true;

        // Hide any previous message
        if (messageDiv) {
            messageDiv.classList.add('hidden');
        }

        const email = this.querySelector('input[name="email"]').value;

        try {
            const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';

            const response = await fetch(baseUrl + '/api/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            });

            const data = await response.json();

            if (data.success) {
                this.reset();
                if (messageDiv) {
                    messageDiv.textContent = data.message || 'Successfully subscribed!';
                    messageDiv.className = 'text-center text-sm text-green-600 bg-green-50 py-2 px-4 rounded';
                    messageDiv.classList.remove('hidden');

                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        messageDiv.classList.add('hidden');
                    }, 5000);
                }
            } else {
                if (messageDiv) {
                    messageDiv.textContent = data.message || 'Something went wrong. Please try again.';
                    messageDiv.className = 'text-center text-sm text-red-600 bg-red-50 py-2 px-4 rounded';
                    messageDiv.classList.remove('hidden');

                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        messageDiv.classList.add('hidden');
                    }, 5000);
                }
            }
        } catch (error) {
            console.error(error);
            if (messageDiv) {
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.className = 'text-center text-sm text-red-600 bg-red-50 py-2 px-4 rounded';
                messageDiv.classList.remove('hidden');

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    messageDiv.classList.add('hidden');
                }, 5000);
            }
        } finally {
            btn.innerText = origText;
            btn.disabled = false;
        }
    });
}

function initializeVideos() {
    // 1. Initialize Video Autoplay behavior
    const videos = document.querySelectorAll('.video-card video');
    videos.forEach((video) => {
        video.setAttribute('autoplay', '');
        video.setAttribute('loop', '');
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');

        video.play().catch(function (error) {
            // console.log('Video autoplay prevented:', error);
        });

        video.addEventListener('loadeddata', function () {
            video.play().catch(() => { });
        });
    });

    // 2. Initialize Video Section Slider (Inbuilt Logic Pattern)
    setTimeout(function () {
        const slider = document.getElementById('videoSectionSlider');
        const sliderWrapper = slider ? slider.parentElement : null;
        const prevBtn = document.getElementById('videoSectionPrev');
        const nextBtn = document.getElementById('videoSectionNext');

        if (!slider) return;
        if (!prevBtn || !nextBtn) return;

        // Prevent double init
        if (slider.dataset.initialized === 'true') return;
        slider.dataset.initialized = 'true';

        let currentIndex = 0;
        let isDragging = false;
        let startX = 0;
        let currentX = 0;
        let initialTranslate = 0;
        let currentTranslate = 0;
        let prevBtnRef = null;
        let nextBtnRef = null;

        function getItemsPerView() {
            if (window.innerWidth >= 1024) return 4;
            if (window.innerWidth >= 640) return 2;
            return 1;
        }

        function getMaxIndex() {
            if (!slider.children.length) return 0;

            const firstItem = slider.children[0];
            if (!firstItem) return 0;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;

            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;

            if (scrollWidth <= containerWidth + 5) return 0;

            const itemsInView = Math.floor((containerWidth + gap) / (itemWidth + gap));
            return Math.max(0, slider.children.length - itemsInView);
        }

        function updateSlider(disableTransition = false) {
            if (!slider.children.length) return;

            const firstItem = slider.children[0];
            if (!firstItem) return;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;
            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;
            const maxIndex = getMaxIndex();

            if (currentIndex > maxIndex) currentIndex = maxIndex;
            if (currentIndex < 0) currentIndex = 0;

            let translateX;
            if (currentIndex === maxIndex && maxIndex > 0) {
                translateX = containerWidth - scrollWidth;
            } else {
                translateX = -currentIndex * (itemWidth + gap);
            }

            translateX += (isDragging ? currentTranslate : 0);
            slider.style.transform = `translateX(${translateX}px)`;
            
            if (!disableTransition) {
                slider.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            } else {
                 slider.style.transition = 'none';
            }

            // Update Buttons
            const btnPrev = prevBtnRef || document.getElementById('videoSectionPrev');
            const btnNext = nextBtnRef || document.getElementById('videoSectionNext');
            if (btnPrev) btnPrev.style.display = currentIndex > 0 ? 'flex' : 'none';
            if (btnNext) btnNext.style.display = currentIndex < maxIndex ? 'flex' : 'none';
        }

        // Drag Logic Wrapper
        function getPositionX(e) { return e.type.includes('mouse') ? e.clientX : e.touches[0].clientX; }
        
        function startDrag(e) {
            if (e.target.closest('a, button')) return; 
            isDragging = true;
            startX = getPositionX(e);
            
            const firstItem = slider.children[0];
            const itemWidth = firstItem ? (firstItem.offsetWidth || 300) : 300;
            const gap = 24;
            
            initialTranslate = -currentIndex * (itemWidth + gap);
            currentTranslate = 0;
            
            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grabbing';
                sliderWrapper.style.userSelect = 'none';
            }
            if (e.type === 'touchstart') {
                document.addEventListener('touchmove', drag, { passive: false });
                document.addEventListener('touchend', endDrag);
            } else {
                document.addEventListener('mousemove', drag);
                document.addEventListener('mouseup', endDrag);
            }
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault(); 
            currentX = getPositionX(e);
            const dragDistance = currentX - startX;
            currentTranslate = dragDistance;
            slider.style.transition = 'none';
            updateSlider(true);
        }

        function endDrag(e) {
            if (!isDragging) return;
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('touchend', endDrag);
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', endDrag);
            isDragging = false;
            
            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grab';
                sliderWrapper.style.userSelect = '';
            }

            // Threshold logic
            const firstItem = slider.children[0];
            const width = firstItem ? firstItem.offsetWidth : 300;
            if (Math.abs(currentTranslate) > (width * 0.25)) {
                if (currentTranslate < 0 && currentIndex < getMaxIndex()) currentIndex++;
                else if (currentTranslate > 0 && currentIndex > 0) currentIndex--;
            }
            currentTranslate = 0;
            updateSlider();
        }

        // Attach Button Listeners (Clone to remove old)
        if (prevBtn && nextBtn) {
            const newPrev = prevBtn.cloneNode(true);
            const newNext = nextBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrev, prevBtn);
            nextBtn.parentNode.replaceChild(newNext, nextBtn);
            
            prevBtnRef = newPrev;
            nextBtnRef = newNext;

            newPrev.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentIndex > 0) { currentIndex--; updateSlider(); }
            });
            newNext.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentIndex < getMaxIndex()) { currentIndex++; updateSlider(); }
            });
        }
        
        // Drag listeners
        if (sliderWrapper) {
            sliderWrapper.style.cursor = 'grab';
            sliderWrapper.addEventListener('touchstart', startDrag, {passive: false});
            sliderWrapper.addEventListener('mousedown', startDrag);
        }

        window.addEventListener('resize', () => { setTimeout(updateSlider, 250); });
        
        // Initial Update
        updateSlider();
        
    }, 200);
}

function initializeBestSellingSlider() {
    // Wait a bit for DOM to be ready and images to load
    setTimeout(function () {
        const slider = document.getElementById('bestSellingSlider');
        const sliderWrapper = slider ? slider.parentElement : null;
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

        // Drag/swipe variables
        let isDragging = false;
        let startX = 0;
        let currentX = 0;
        let initialTranslate = 0;
        let currentTranslate = 0;
        let prevBtnRef = null;
        let nextBtnRef = null;

        function getItemsPerView() {
            if (window.innerWidth >= 1280) return 6;
            if (window.innerWidth >= 1024) return 4;
            if (window.innerWidth >= 640) return 2;
            return 1;
        }

        function getMaxIndex() {
            if (!slider.children.length) return 0;

            const firstItem = slider.children[0];
            if (!firstItem) return 0;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;

            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;

            // If everything fits, no need to scroll
            if (scrollWidth <= containerWidth + 5) return 0;

            // Calculate how many items are fully or mostly visible
            const itemsInView = Math.floor((containerWidth + gap) / (itemWidth + gap));

            return Math.max(0, slider.children.length - itemsInView);
        }

        function updateSlider(disableTransition = false) {
            if (!slider.children.length) return;

            const firstItem = slider.children[0];
            if (!firstItem) return;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;
            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;
            const maxIndex = getMaxIndex();

            // Clamp currentIndex to valid range
            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }
            if (currentIndex < 0) {
                currentIndex = 0;
            }

            let translateX;
            if (currentIndex === maxIndex && maxIndex > 0) {
                // At max position, perfectly align last item with right edge
                translateX = containerWidth - scrollWidth;
            } else {
                translateX = -currentIndex * (itemWidth + gap);
            }

            // Apply dragging offset
            const finalTranslate = translateX + (isDragging ? currentTranslate : 0);

            slider.style.transform = `translateX(${finalTranslate}px)`;
            if (!disableTransition) {
                slider.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            } else {
                slider.style.transition = 'none';
            }

            // Show/hide navigation buttons
            const btnPrev = prevBtnRef || document.getElementById('bestSellingPrev');
            const btnNext = nextBtnRef || document.getElementById('bestSellingNext');

            if (btnPrev) {
                btnPrev.style.display = currentIndex > 0 ? 'flex' : 'none';
            }
            if (btnNext) {
                btnNext.style.display = currentIndex < maxIndex ? 'flex' : 'none';
            }
        }

        // Drag/Swipe functions
        function getPositionX(e) {
            return e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
        }

        function startDrag(e) {
            // Don't start drag if clicking on a link, button, or interactive element
            const target = e.target.closest('a, button, .wishlist-btn, .product-action-btn');
            if (target) return;

            isDragging = true;
            startX = getPositionX(e);
            const firstItem = slider.children[0];
            if (!firstItem) {
                isDragging = false;
                return;
            }
            const itemWidth = firstItem.offsetWidth || 300;
            const gap = 24;
            initialTranslate = -currentIndex * (itemWidth + gap);
            currentTranslate = 0;

            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grabbing';
                sliderWrapper.style.userSelect = 'none';
            }

            // Add event listeners to document for better drag handling
            if (e.type === 'touchstart') {
                document.addEventListener('touchmove', drag, { passive: false });
                document.addEventListener('touchend', endDrag);
            } else {
                document.addEventListener('mousemove', drag);
                document.addEventListener('mouseup', endDrag);
            }
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();
            e.stopPropagation();

            currentX = getPositionX(e);
            const dragDistance = currentX - startX;

            const firstItem = slider.children[0];
            if (!firstItem) return;
            const itemWidth = firstItem.offsetWidth || 300;
            const gap = 24;
            const maxIndex = getMaxIndex();

            // Calculate constraints - account for CSS gap
            const maxTranslate = 0; // Can't drag past start
            const minTranslate = -maxIndex * (itemWidth + gap); // Can't drag past end

            // Apply drag distance with constraints
            currentTranslate = dragDistance;
            const newTranslate = initialTranslate + currentTranslate;

            if (newTranslate > maxTranslate) {
                currentTranslate = maxTranslate - initialTranslate;
            } else if (newTranslate < minTranslate) {
                currentTranslate = minTranslate - initialTranslate;
            }

            slider.style.transition = 'none';
            updateSlider(true);
        }

        function endDrag(e) {
            if (!isDragging) return;

            // Remove event listeners
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('touchend', endDrag);
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', endDrag);

            isDragging = false;

            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grab';
                sliderWrapper.style.userSelect = '';
            }

            // Determine if we should snap to next/prev slide
            const firstItem = slider.children[0];
            if (!firstItem) {
                currentTranslate = 0;
                updateSlider();
                return;
            }

            const itemWidth = firstItem.offsetWidth || 300;
            const threshold = itemWidth * 0.25; // 25% of item width to trigger slide

            if (Math.abs(currentTranslate) > threshold) {
                if (currentTranslate < 0 && currentIndex < getMaxIndex()) {
                    // Swipe left - go to next
                    currentIndex++;
                } else if (currentTranslate > 0 && currentIndex > 0) {
                    // Swipe right - go to prev
                    currentIndex--;
                }
            }

            currentTranslate = 0;
            updateSlider();
        }

        // Remove existing event listeners by cloning buttons and storing references
        if (prevBtn && nextBtn) {
            const newPrevBtn = prevBtn.cloneNode(true);
            const newNextBtn = nextBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
            nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

            prevBtnRef = newPrevBtn;
            nextBtnRef = newNextBtn;

            newPrevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });

            newNextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex < getMaxIndex()) {
                    currentIndex++;
                    updateSlider();
                }
            });
        }

        // Add drag/swipe event listeners
        if (sliderWrapper) {
            sliderWrapper.style.cursor = 'grab';
            sliderWrapper.addEventListener('touchstart', startDrag, { passive: false });
            sliderWrapper.addEventListener('mousedown', startDrag);
        }

        // Handle window resize
        let resizeTimer;
        const resizeHandler = function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
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
            images.forEach(function (img) {
                if (img.complete) {
                    imagesLoaded++;
                    if (imagesLoaded === images.length) {
                        updateSlider();
                    }
                } else {
                    img.addEventListener('load', function () {
                        imagesLoaded++;
                        if (imagesLoaded === images.length) {
                            updateSlider();
                        }
                    });
                    img.addEventListener('error', function () {
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

function initializeTrendingSlider() {
    // Wait a bit for DOM to be ready and images to load
    setTimeout(function () {
        const slider = document.getElementById('trendingSlider');
        const sliderWrapper = slider ? slider.parentElement : null;
        const prevBtn = document.getElementById('trendingPrev');
        const nextBtn = document.getElementById('trendingNext');

        if (!slider) {
            console.log('Trending slider not found');
            return;
        }

        if (!prevBtn || !nextBtn) {
            console.log('Trending navigation buttons not found');
            return;
        }

        // Check if slider already initialized
        if (slider.dataset.initialized === 'true') {
            return;
        }
        slider.dataset.initialized = 'true';

        let currentIndex = 0;

        // Drag/swipe variables
        let isDragging = false;
        let startX = 0;
        let currentX = 0;
        let initialTranslate = 0;
        let currentTranslate = 0;
        let prevBtnRef = null;
        let nextBtnRef = null;

        function getItemsPerView() {
            if (window.innerWidth >= 1280) return 6;
            if (window.innerWidth >= 1024) return 4;
            if (window.innerWidth >= 640) return 2;
            return 1;
        }

        function getMaxIndex() {
            if (!slider.children.length) return 0;

            const firstItem = slider.children[0];
            if (!firstItem) return 0;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;

            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;

            // If everything fits, no need to scroll
            if (scrollWidth <= containerWidth + 5) return 0;

            const itemsInView = Math.floor((containerWidth + gap) / (itemWidth + gap));
            return Math.max(0, slider.children.length - itemsInView);
        }

        function updateSlider(disableTransition = false) {
            if (!slider.children.length) return;

            const firstItem = slider.children[0];
            if (!firstItem) return;

            const itemWidth = firstItem.offsetWidth;
            const style = window.getComputedStyle(slider);
            const gap = parseFloat(style.gap) || 24;
            const containerWidth = slider.parentElement.offsetWidth;
            const scrollWidth = slider.scrollWidth;
            const maxIndex = getMaxIndex();

            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }
            if (currentIndex < 0) {
                currentIndex = 0;
            }

            let translateX;
            if (currentIndex === maxIndex && maxIndex > 0) {
                translateX = containerWidth - scrollWidth;
            } else {
                translateX = -currentIndex * (itemWidth + gap);
            }

            const finalTranslate = translateX + (isDragging ? currentTranslate : 0);

            slider.style.transform = `translateX(${finalTranslate}px)`;
            if (!disableTransition) {
                slider.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            } else {
                slider.style.transition = 'none';
            }

            const btnPrev = prevBtnRef || document.getElementById('trendingPrev');
            const btnNext = nextBtnRef || document.getElementById('trendingNext');

            if (btnPrev) {
                btnPrev.style.display = currentIndex > 0 ? 'flex' : 'none';
            }
            if (btnNext) {
                btnNext.style.display = currentIndex < maxIndex ? 'flex' : 'none';
            }
        }

        // Drag/Swipe functions
        function getPositionX(e) {
            return e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
        }

        function startDrag(e) {
            // Don't start drag if clicking on a link, button, or interactive element
            const target = e.target.closest('a, button, .wishlist-btn, .product-action-btn');
            if (target) return;

            isDragging = true;
            startX = getPositionX(e);
            const firstItem = slider.children[0];
            if (!firstItem) {
                isDragging = false;
                return;
            }
            const itemWidth = firstItem.offsetWidth || 300;
            const gap = 24;
            initialTranslate = -currentIndex * (itemWidth + gap);
            currentTranslate = 0;

            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grabbing';
                sliderWrapper.style.userSelect = 'none';
            }

            // Add event listeners to document for better drag handling
            if (e.type === 'touchstart') {
                document.addEventListener('touchmove', drag, { passive: false });
                document.addEventListener('touchend', endDrag);
            } else {
                document.addEventListener('mousemove', drag);
                document.addEventListener('mouseup', endDrag);
            }
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();
            e.stopPropagation();

            currentX = getPositionX(e);
            const dragDistance = currentX - startX;

            const firstItem = slider.children[0];
            if (!firstItem) return;
            const itemWidth = firstItem.offsetWidth || 300;
            const gap = 24;
            const maxIndex = getMaxIndex();

            // Calculate constraints - account for CSS gap
            const maxTranslate = 0; // Can't drag past start
            const minTranslate = -maxIndex * (itemWidth + gap); // Can't drag past end

            // Apply drag distance with constraints
            currentTranslate = dragDistance;
            const newTranslate = initialTranslate + currentTranslate;

            if (newTranslate > maxTranslate) {
                currentTranslate = maxTranslate - initialTranslate;
            } else if (newTranslate < minTranslate) {
                currentTranslate = minTranslate - initialTranslate;
            }

            slider.style.transition = 'none';
            updateSlider(true);
        }

        function endDrag(e) {
            if (!isDragging) return;

            // Remove event listeners
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('touchend', endDrag);
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', endDrag);

            isDragging = false;

            if (sliderWrapper) {
                sliderWrapper.style.cursor = 'grab';
                sliderWrapper.style.userSelect = '';
            }

            // Determine if we should snap to next/prev slide
            const firstItem = slider.children[0];
            if (!firstItem) {
                currentTranslate = 0;
                updateSlider();
                return;
            }

            const itemWidth = firstItem.offsetWidth || 300;
            const threshold = itemWidth * 0.25; // 25% of item width to trigger slide

            if (Math.abs(currentTranslate) > threshold) {
                if (currentTranslate < 0 && currentIndex < getMaxIndex()) {
                    // Swipe left - go to next
                    currentIndex++;
                } else if (currentTranslate > 0 && currentIndex > 0) {
                    // Swipe right - go to prev
                    currentIndex--;
                }
            }

            currentTranslate = 0;
            updateSlider();
        }

        // Remove existing event listeners by cloning buttons and storing references
        if (prevBtn && nextBtn) {
            const newPrevBtn = prevBtn.cloneNode(true);
            const newNextBtn = nextBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
            nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

            prevBtnRef = newPrevBtn;
            nextBtnRef = newNextBtn;

            newPrevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });

            newNextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex < getMaxIndex()) {
                    currentIndex++;
                    updateSlider();
                }
            });
        }

        // Add drag/swipe event listeners
        if (sliderWrapper) {
            sliderWrapper.style.cursor = 'grab';
            sliderWrapper.addEventListener('touchstart', startDrag, { passive: false });
            sliderWrapper.addEventListener('mousedown', startDrag);
        }

        // Handle window resize
        let resizeTimer;
        const resizeHandler = function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
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
            images.forEach(function (img) {
                if (img.complete) {
                    imagesLoaded++;
                    if (imagesLoaded === images.length) {
                        updateSlider();
                    }
                } else {
                    img.addEventListener('load', function () {
                        imagesLoaded++;
                        if (imagesLoaded === images.length) {
                            updateSlider();
                        }
                    });
                    img.addEventListener('error', function () {
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

