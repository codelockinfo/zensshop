/**
 * Optimized Lazy Loading for Sections
 * Version 7.0 - IntersectionObserver + Improved Slider Lifecycle
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
    }, { rootMargin: '400px' });

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
            if (typeof slider.updatePosition === 'function') {
                slider.updatePosition(false);
            }
        });
    }, 150));
}

async function loadSection(id, endpoint) {
    const container = document.getElementById(id);
    if (!container || container.dataset.loaded === "true") return;
    
    // Check if caching is allowed
    const isCachingAllowed = () => {
        const consentRaw = localStorage.getItem('cookieConsent');
        if (!consentRaw) return false;
        try {
            const data = JSON.parse(consentRaw);
            return data && data.value === 'allowed' && data.expires > new Date().getTime();
        } catch (e) {
            return consentRaw === 'allowed';
        }
    };

    // 1. Try to load from Cache first
    if (isCachingAllowed()) {
        const cachedHtml = localStorage.getItem('cache_' + endpoint);
        if (cachedHtml) {
            container.innerHTML = cachedHtml;
            container.classList.remove("section-loading");
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
            if (container.innerHTML !== html) {
                container.innerHTML = html;
                container.classList.remove("section-loading");
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(() => initializeSectionContent(container));
                } else {
                    setTimeout(() => initializeSectionContent(container), 50);
                }
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

function initializeSectionContent(container) {
    // 1. Sliders - check for existence and initialize precisely
    const bestSelling = container.querySelector("#bestSellingSlider");
    if (bestSelling) setupCustomSlider(bestSelling, "bestSellingPrev", "bestSellingNext");

    const trending = container.querySelector("#trendingSlider");
    if (trending) setupCustomSlider(trending, "trendingPrev", "trendingNext");

    const videoSlider = container.querySelector("#videoSectionSlider");
    if (videoSlider) {
        container.querySelectorAll("video").forEach(v => { v.muted = true; v.playsInline = true; v.play().catch(() => {}); });
        setupCustomSlider(videoSlider, "videoSectionPrev", "videoSectionNext");
    }

    // 2. Wiring for product cards
    if (typeof initializeProductCards === 'function') {
        initializeProductCards();
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

    const getMetrics = () => {
        if (!sliderElement.children.length) return { itemWidth: 0, gap: 0, maxIndex: 0 };
        const itemWidth = sliderElement.children[0].offsetWidth;
        const gap = parseInt(window.getComputedStyle(sliderElement).gap) || 24;
        const visibleCount = Math.floor((wrapper.offsetWidth + gap) / (itemWidth + gap)) || 1;
        const maxIndex = Math.max(0, sliderElement.children.length - visibleCount);
        return { itemWidth, gap, maxIndex };
    };

    const updatePosition = (smooth = true) => {
        const { itemWidth, gap, maxIndex } = getMetrics();
        if (currentIndex > maxIndex) currentIndex = maxIndex;
        if (currentIndex < 0) currentIndex = 0;
        
        const maxScroll = Math.max(0, sliderElement.scrollWidth - wrapper.offsetWidth);
        let targetX = -currentIndex * (itemWidth + gap);
        
        if (targetX < -maxScroll) targetX = -maxScroll;
        if (targetX > 0) targetX = 0;
        
        sliderElement.style.transition = smooth ? 'transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none';
        sliderElement.style.transform = `translateX(${targetX}px)`;
        
        if (prevBtn) prevBtn.style.opacity = currentIndex === 0 ? '0.3' : '1';
        if (nextBtn) nextBtn.style.opacity = currentIndex >= maxIndex ? '0.3' : '1';
    };

    // Events for Dragging
    wrapper.style.cursor = 'grab';
    wrapper.style.userSelect = 'none';
    wrapper.style.webkitUserSelect = 'none';
    
    // Prevent default browser image dragging
    sliderElement.querySelectorAll('img').forEach(img => img.setAttribute('draggable', 'false'));
    sliderElement.querySelectorAll('a').forEach(a => a.setAttribute('draggable', 'false'));

    const startDragging = (e) => {
        isDragging = true;
        wrapper.style.cursor = 'grabbing';
        startX = (e.pageX || e.touches[0].pageX);
        sliderElement.style.transition = 'none';
    };

    const stopDragging = (e) => {
        if (!isDragging) return;
        isDragging = false;
        wrapper.style.cursor = 'grab';
        
        const endX = (e.pageX || (e.changedTouches ? e.changedTouches[0].pageX : 0));
        const movedX = endX - startX;
        
        // If movement was significant, prevent the next click event from firing on children
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
        // Prevent scrolling while dragging horizontally
        if (e.cancelable) e.preventDefault();
        
        const x = e.pageX || e.touches[0].pageX;
        const walk = (x - startX);
        const { itemWidth, gap } = getMetrics();
        const currentX = -currentIndex * (itemWidth + gap);
        sliderElement.style.transform = `translateX(${currentX + walk}px)`;
    };

    wrapper.addEventListener('mousedown', startDragging);
    window.addEventListener('mousemove', moveDragging, { passive: false });
    window.addEventListener('mouseup', stopDragging);
    
    wrapper.addEventListener('touchstart', startDragging, { passive: true });
    wrapper.addEventListener('touchmove', moveDragging, { passive: false });
    wrapper.addEventListener('touchend', stopDragging);

    if (prevBtn && nextBtn) {
        prevBtn.onclick = (e) => { e.preventDefault(); if (currentIndex > 0) { currentIndex--; updatePosition(); } };
        nextBtn.onclick = (e) => { e.preventDefault(); const { maxIndex } = getMetrics(); if (currentIndex < maxIndex) { currentIndex++; updatePosition(); } };
    }

    // Register instance for global events
    activeSliders.set(sliderElement.id || Math.random(), { updatePosition });
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
