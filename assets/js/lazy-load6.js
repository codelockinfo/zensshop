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
    let currentIndex = 0;

    const updatePosition = () => {
        if (!slider.children.length) return;
        const itemWidth = slider.children[0].offsetWidth;
        const gap = parseInt(window.getComputedStyle(slider).gap) || 24;
        const visibleWidth = wrapper.offsetWidth;
        const maxScroll = slider.scrollWidth - visibleWidth;
        let targetX = -currentIndex * (itemWidth + gap);
        if (targetX < -maxScroll) targetX = -maxScroll;
        if (targetX > 0) targetX = 0;
        slider.style.transition = 'transform 0.5s ease';
        slider.style.transform = `translateX(${targetX}px)`;
    };

    if (prevBtn && nextBtn) {
        // Clear old listeners if any (though unlikely here)
        prevBtn.onclick = null;
        nextBtn.onclick = null;
        
        prevBtn.addEventListener('click', () => { if (currentIndex > 0) { currentIndex--; updatePosition(); } });
        nextBtn.addEventListener('click', () => { 
            const itemWidth = slider.children[0].offsetWidth;
            const gap = parseInt(window.getComputedStyle(slider).gap) || 24;
            const visibleCount = Math.floor((wrapper.offsetWidth + gap) / (itemWidth + gap));
            if (currentIndex < slider.children.length - visibleCount) { currentIndex++; updatePosition(); } 
        });
    }
    window.addEventListener('resize', updatePosition);
    updatePosition();
}

document.addEventListener("DOMContentLoaded", initLazyLoading);
