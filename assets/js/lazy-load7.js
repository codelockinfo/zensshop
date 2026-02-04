/**
 * Optimized Lazy Loading for Sections
 * Version 5.1 - IntersectionObserver + Idle Initialization
 */
const sections = [
    { id: "categories-section", endpoint: "categories" },
    { id: "best-selling-section", endpoint: "best-selling" },
    { id: "special-offers-section", endpoint: "special-offers" },
    { id: "videos-section", endpoint: "videos" },
    { id: "trending-section", endpoint: "trending" },
    { id: "philosophy-section", endpoint: "philosophy" },
    { id: "features-section", endpoint: "features" },
    { id: "newsletter-section", endpoint: "newsletter" }
];

function initLazyLoading() {
    if (!('IntersectionObserver' in window)) {
        // Fallback for very old browsers: load all immediately
        sections.forEach(s => loadSection(s.id, s.endpoint));
        return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const section = sections.find(s => s.id === entry.target.id);
                if (section) {
                    loadSection(section.id, section.endpoint);
                    obs.unobserve(entry.target);
                }
            }
        });
    }, { rootMargin: '400px' });

    sections.forEach(s => {
        const el = document.getElementById(s.id);
        if (el) {
            // Load the first section immediately to avoid empty space
            if (s.id === "categories-section") {
                loadSection(s.id, s.endpoint);
            } else {
                observer.observe(el);
            }
        }
    });
}

async function loadSection(id, endpoint) {
    const container = document.getElementById(id);
    if (!container || container.dataset.loaded === "true") return;
    
    // Check if caching is allowed
    const isCachingAllowed = () => localStorage.getItem('cookieConsent') === 'allowed';

    // 1. Try to load from Cache first (Instant Load)
    if (isCachingAllowed()) {
        const cachedHtml = localStorage.getItem('cache_' + endpoint);
        if (cachedHtml) {
            container.innerHTML = cachedHtml;
            container.classList.remove("section-loading");
            // Background initialization for cached content
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => initializeSectionContent(container));
            } else {
                setTimeout(() => initializeSectionContent(container), 0);
            }
        }
    }

    container.dataset.loaded = "true";

    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        const response = await fetch(`${baseUrl}/api/sections.php?section=${endpoint}`);
        const html = await response.text();

        if (html) {
            // Only update DOM if it's different from cache to prevent flicker
            if (container.innerHTML !== html) {
                container.innerHTML = html;
                container.classList.remove("section-loading");
                
                // Background initialization to keep main thread free
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(() => initializeSectionContent(container));
                } else {
                    setTimeout(() => initializeSectionContent(container), 50);
                }
            }

            // 2. Save to Cache for next time
            if (isCachingAllowed()) {
                localStorage.setItem('cache_' + endpoint, html);
            }
        }
    } catch (error) {
        console.error(`Error loading section ${endpoint}:`, error);
        container.dataset.loaded = "false";
    }
}

function initializeSectionContent(container) {
    // 1. Sliders - only initialize if they exist in this container
    if (container.querySelector("#bestSellingSlider")) {
        setupCustomSlider("bestSellingSlider", "bestSellingPrev", "bestSellingNext");
    }
    if (container.querySelector("#trendingSlider")) {
        setupCustomSlider("trendingSlider", "trendingPrev", "trendingNext");
    }
    if (container.querySelector("#videoSectionSlider")) {
        const videos = container.querySelectorAll("video");
        videos.forEach(v => { v.muted = true; v.playsInline = true; v.play().catch(() => {}); });
        setupCustomSlider("videoSectionSlider", "videoSectionPrev", "videoSectionNext");
    }

    // 2. Product Cards (Wishlist/Quickview wiring)
    if (typeof initializeProductCards === 'function') {
        initializeProductCards();
    }
}

function setupCustomSlider(sliderId, prevBtnId, nextBtnId) {
    const slider = document.getElementById(sliderId);
    if (!slider) return;
    const wrapper = slider.parentElement;
    const prevBtn = document.getElementById(prevBtnId);
    const nextBtn = document.getElementById(nextBtnId);
    
    let isDragging = false;
    let startX;
    let scrollLeft;
    let currentIndex = 0;

    const getMetrics = () => {
        if (!slider.children.length) return { itemWidth: 0, gap: 0, visibleCount: 1, maxIndex: 0 };
        const itemWidth = slider.children[0].offsetWidth;
        const gap = parseInt(window.getComputedStyle(slider).gap) || 24;
        const visibleCount = Math.floor((wrapper.offsetWidth + gap) / (itemWidth + gap)) || 1;
        const maxIndex = Math.max(0, slider.children.length - visibleCount);
        return { itemWidth, gap, visibleCount, maxIndex };
    };

    const updatePosition = (smooth = true) => {
        const { itemWidth, gap, maxIndex } = getMetrics();
        if (currentIndex > maxIndex) currentIndex = maxIndex;
        if (currentIndex < 0) currentIndex = 0;
        
        const visibleWidth = wrapper.offsetWidth;
        const maxScroll = Math.max(0, slider.scrollWidth - visibleWidth);
        let targetX = -currentIndex * (itemWidth + gap);
        
        if (targetX < -maxScroll) targetX = -maxScroll;
        if (targetX > 0) targetX = 0;
        
        slider.style.transition = smooth ? 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)' : 'none';
        slider.style.transform = `translateX(${targetX}px)`;
        
        // Update button states
        if (prevBtn) prevBtn.style.opacity = currentIndex === 0 ? '0.5' : '1';
        if (nextBtn) nextBtn.style.opacity = currentIndex >= maxIndex ? '0.5' : '1';
    };

    // --- Mouse Drag Logic ---
    wrapper.style.cursor = 'grab';
    wrapper.style.userSelect = 'none';

    const startDragging = (e) => {
        isDragging = true;
        wrapper.style.cursor = 'grabbing';
        startX = (e.pageX || e.touches[0].pageX) - slider.offsetLeft;
        slider.style.transition = 'none';
    };

    const stopDragging = (e) => {
        if (!isDragging) return;
        isDragging = false;
        wrapper.style.cursor = 'grab';
        
        const { itemWidth, gap } = getMetrics();
        const movedX = (e.pageX || (e.changedTouches ? e.changedTouches[0].pageX : 0)) - startX;
        
        // Horizontal snap logic
        const threshold = (itemWidth + gap) / 4;
        const diff = Math.round(movedX / (itemWidth + gap));
        
        if (Math.abs(movedX) > threshold) {
            currentIndex -= diff;
        }
        updatePosition();
    };

    const moveDragging = (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX || e.touches[0].pageX;
        const walk = (x - startX);
        const { itemWidth, gap } = getMetrics();
        const currentX = -currentIndex * (itemWidth + gap);
        slider.style.transform = `translateX(${currentX + walk}px)`;
    };

    // Events
    wrapper.addEventListener('mousedown', startDragging);
    window.addEventListener('mousemove', moveDragging);
    window.addEventListener('mouseup', stopDragging);
    
    wrapper.addEventListener('touchstart', startDragging, { passive: false });
    wrapper.addEventListener('touchmove', moveDragging, { passive: false });
    wrapper.addEventListener('touchend', stopDragging);

    if (prevBtn && nextBtn) {
        prevBtn.onclick = (e) => { e.preventDefault(); if (currentIndex > 0) { currentIndex--; updatePosition(); } };
        nextBtn.onclick = (e) => { 
            e.preventDefault();
            const { maxIndex } = getMetrics();
            if (currentIndex < maxIndex) { currentIndex++; updatePosition(); } 
        };
    }

    window.addEventListener('resize', debounce(() => updatePosition(false), 150));
    updatePosition();
}

// Global debounce if not exists in main6.js
if (typeof window.debounce !== 'function') {
    window.debounce = function(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    };
}

document.addEventListener("DOMContentLoaded", initLazyLoading);
