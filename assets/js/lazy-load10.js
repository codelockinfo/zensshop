/**
 * Optimized Lazy Loading for Sections
 * Version 8.0 - Performance Optimized (Cached Metrics + Dynamic Listeners)
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

// Global registry for slider instances to handle shared events like resize
const activeSliders = new Map();
let isCachePolicyAllowed = null;

function initLazyLoading() {
    if (!('IntersectionObserver' in window)) {
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
    }, { rootMargin: '200px' }); // Reduced margin from 400px to 200px for better initial performance

    sections.forEach(s => {
        const el = document.getElementById(s.id);
        if (el) {
            if (s.id === "categories-section") {
                loadSection(s.id, s.endpoint);
            } else {
                observer.observe(el);
            }
        }
    });

    // Single global resize listener for all sliders
    window.addEventListener('resize', debounce(() => {
        activeSliders.forEach(slider => {
            if (typeof slider.refresh === 'function') {
                slider.refresh();
            }
        });
    }, 200));
}

async function loadSection(id, endpoint) {
    const container = document.getElementById(id);
    if (!container || container.dataset.loaded === "true") return;
    
    // Caching check (highly optimized)
    const isCachingAllowed = () => {
        if (isCachePolicyAllowed !== null) return isCachePolicyAllowed;
        const consentRaw = localStorage.getItem('cookieConsent');
        if (!consentRaw) return (isCachePolicyAllowed = false);
        try {
            const data = JSON.parse(consentRaw);
            isCachePolicyAllowed = data && data.value === 'allowed' && data.expires > Date.now();
        } catch (e) {
            isCachePolicyAllowed = consentRaw === 'allowed';
        }
        return isCachePolicyAllowed;
    };

    // 1. Try to load from Cache first
    if (isCachingAllowed()) {
        const cachedHtml = localStorage.getItem('cache_' + endpoint);
        if (cachedHtml) {
            container.innerHTML = cachedHtml;
            container.classList.remove("section-loading");
            scheduleInitialization(container);
        }
    }

    container.dataset.loaded = "true";

    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        const response = await fetch(`${baseUrl}/api/sections.php?section=${endpoint}`);
        const html = await response.text();

        if (html) {
            // Only update if content actually changed to avoid redundant reflows
            if (container.innerHTML.trim() !== html.trim()) {
                container.innerHTML = html;
                container.classList.remove("section-loading");
                scheduleInitialization(container);
            }
            if (isCachingAllowed()) {
                localStorage.setItem('cache_' + endpoint, html);
            }
        }
    } catch (error) {
        console.error(`Error loading section ${endpoint}:`, error);
        container.dataset.loaded = "false";
    }
}

function scheduleInitialization(container) {
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => initializeSectionContent(container));
    } else {
        setTimeout(() => initializeSectionContent(container), 0);
    }
}

function initializeSectionContent(container) {
    // 1. Sliders - check for existence and initialize precisely
    const bestSelling = container.querySelector("#bestSellingSlider");
    if (bestSelling) setupCustomSlider(bestSelling, "bestSellingPrev", "bestSellingNext");

    const trending = container.querySelector("#trendingSlider");
    if (trending) setupCustomSlider(trending, "trendingPrev", "trendingNext");

    const videoSlider = container.querySelector("#videoSectionSlider");
    if (videoSlider) {
        container.querySelectorAll("video").forEach(v => { 
            v.muted = true; 
            v.playsInline = true; 
            v.setAttribute('autoplay', '');
            v.play().catch(() => {}); 
        });
        setupCustomSlider(videoSlider, "videoSectionPrev", "videoSectionNext");
    }

    // 2. Wiring for product cards - optimize by only scanning the current container if possible
    if (typeof initializeProductCards === 'function') {
        initializeProductCards(container);
    }
}

function setupCustomSlider(sliderElement, prevBtnId, nextBtnId) {
    if (!sliderElement || sliderElement.dataset.sliderInit === "true") return;
    sliderElement.dataset.sliderInit = "true";

    const wrapper = sliderElement.parentElement;
    const prevBtn = document.getElementById(prevBtnId);
    const nextBtn = document.getElementById(nextBtnId);
    
    let isDragging = false;
    let startX;
    let currentIndex = 0;
    let metrics = null;

    const getMetrics = (force = false) => {
        if (metrics && !force) return metrics;
        if (!sliderElement.children.length) return { itemWidth: 0, gap: 0, maxIndex: 0 };
        
        const item = sliderElement.children[0];
        const itemWidth = item.offsetWidth;
        const gap = parseInt(window.getComputedStyle(sliderElement).gap) || 24;
        const visibleCount = Math.floor((wrapper.offsetWidth + gap) / (itemWidth + gap)) || 1;
        const maxIndex = Math.max(0, sliderElement.children.length - visibleCount);
        
        metrics = { itemWidth, gap, maxIndex };
        return metrics;
    };

    const updatePosition = (smooth = true) => {
        const { itemWidth, gap, maxIndex } = getMetrics();
        if (currentIndex > maxIndex) currentIndex = maxIndex;
        if (currentIndex < 0) currentIndex = 0;
        
        const targetX = -currentIndex * (itemWidth + gap);
        
        sliderElement.style.transition = smooth ? 'transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none';
        sliderElement.style.transform = `translateX(${targetX}px)`;
        
        if (prevBtn) prevBtn.style.opacity = currentIndex === 0 ? '0.3' : '1';
        if (nextBtn) nextBtn.style.opacity = currentIndex >= maxIndex ? '0.3' : '1';
    };

    const startDragging = (e) => {
        isDragging = true;
        wrapper.style.cursor = 'grabbing';
        startX = (e.pageX || (e.touches ? e.touches[0].pageX : 0));
        sliderElement.style.transition = 'none';
        
        // Attach dynamic listeners to window only while dragging
        window.addEventListener('mousemove', moveDragging, { passive: false });
        window.addEventListener('mouseup', stopDragging);
        window.addEventListener('touchmove', moveDragging, { passive: false });
        window.addEventListener('touchend', stopDragging);
    };

    const stopDragging = (e) => {
        if (!isDragging) return;
        isDragging = false;
        wrapper.style.cursor = 'grab';
        
        // Remove dynamic listeners
        window.removeEventListener('mousemove', moveDragging);
        window.removeEventListener('mouseup', stopDragging);
        window.removeEventListener('touchmove', moveDragging);
        window.removeEventListener('touchend', stopDragging);
        
        const endX = (e.pageX || (e.changedTouches ? e.changedTouches[0].pageX : 0));
        const movedX = endX - startX;
        
        if (Math.abs(movedX) > 10) {
            const preventClick = (e) => {
                e.stopImmediatePropagation();
                e.preventDefault();
                window.removeEventListener('click', preventClick, true);
            };
            window.addEventListener('click', preventClick, true);
        }

        const { itemWidth, gap } = getMetrics();
        if (Math.abs(movedX) > 50) {
            const shift = Math.round(movedX / (itemWidth + gap));
            currentIndex -= shift;
        }
        updatePosition();
    };

    const moveDragging = (e) => {
        if (!isDragging) return;
        if (e.cancelable) e.preventDefault();
        
        const x = e.pageX || (e.touches ? e.touches[0].pageX : 0);
        const walk = (x - startX);
        const { itemWidth, gap } = getMetrics();
        const currentX = -currentIndex * (itemWidth + gap);
        sliderElement.style.transform = `translateX(${currentX + walk}px)`;
    };

    // Static event listeners for initiation
    wrapper.addEventListener('mousedown', startDragging);
    wrapper.addEventListener('touchstart', startDragging, { passive: true });
    
    // Disable native dragging
    sliderElement.querySelectorAll('img, a').forEach(el => el.setAttribute('draggable', 'false'));

    if (prevBtn && nextBtn) {
        prevBtn.onclick = (e) => { e.preventDefault(); if (currentIndex > 0) { currentIndex--; updatePosition(); } };
        nextBtn.onclick = (e) => { e.preventDefault(); const { maxIndex } = getMetrics(); if (currentIndex < maxIndex) { currentIndex++; updatePosition(); } };
    }

    // Register instance for global resize
    activeSliders.set(sliderElement.id || Math.random(), { 
        refresh: () => { metrics = null; updatePosition(false); } 
    });
    
    updatePosition();
}

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
