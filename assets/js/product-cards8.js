/**
 * Product Card Interactions + Native Scroll Slider
 * Touch sliding handled 100% by the browser via CSS scroll-snap.
 * Arrows use scrollLeft for smooth scrolling.
 */

/* ──────────────────────────────────────────────────────────
   1.  NATIVE SCROLL SLIDER
   ────────────────────────────────────────────────────────── */
function initSlider(viewportId, prevBtnSel, nextBtnSel) {
    var viewport = document.getElementById(viewportId);
    if (!viewport) return;

    function getItemWidth() {
        var item = viewport.querySelector('.slider-snap-item');
        if (!item) return 300;
        var style = window.getComputedStyle(viewport);
        var gap   = parseFloat(style.gap || style.columnGap) || 24;
        return item.getBoundingClientRect().width + gap;
    }

    function scrollByCards(direction) {
        var w = getItemWidth();
        viewport.scrollBy({ left: direction * w, behavior: 'smooth' });
    }

    function bindBtn(sel, dir) {
        document.querySelectorAll(sel).forEach(function(btn) {
            if (btn._sliderBound) return;
            btn._sliderBound = true;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                scrollByCards(dir);
            });
        });
    }

    bindBtn(prevBtnSel, -1);
    bindBtn(nextBtnSel,  1);
}

// Videos section arrows
function initVideoSlider() {
    var viewport = document.getElementById('videoSectionViewport');
    if (!viewport) return;

    function getItemWidth() {
        var item = viewport.querySelector('.slider-snap-item');
        if (!item) return 300;
        return item.getBoundingClientRect().width + 24;
    }

    var prev = document.getElementById('videoSectionPrev');
    var next = document.getElementById('videoSectionNext');

    if (prev && !prev._sliderBound) {
        prev._sliderBound = true;
        prev.addEventListener('click', function(e) {
            e.preventDefault();
            viewport.scrollBy({ left: -getItemWidth(), behavior: 'smooth' });
        });
    }
    if (next && !next._sliderBound) {
        next._sliderBound = true;
        next.addEventListener('click', function(e) {
            e.preventDefault();
            viewport.scrollBy({ left: getItemWidth(), behavior: 'smooth' });
        });
    }
}

/* ──────────────────────────────────────────────────────────
   2.  CARD CLICK → PRODUCT PAGE
   ────────────────────────────────────────────────────────── */
function initCardClickRedirect(container) {
    container = container || document;
    container.querySelectorAll('.product-card').forEach(function(card) {
        if (card.dataset.clickInit) return;
        card.dataset.clickInit = 'true';
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            if (e.target.closest('button, a, input, select, textarea')) return;
            var link = card.querySelector('.product-card-view-link, a[href*="product"]');
            if (link && link.href) window.location.href = link.href;
        });
    });
}

/* ──────────────────────────────────────────────────────────
   3.  MISC CARD INTERACTIONS
   ────────────────────────────────────────────────────────── */
function initializeProductCards(container) {
    container = container || document;
    container.querySelectorAll('.compare-btn').forEach(function(btn) {
        if (btn.dataset.compareInit) return;
        btn.dataset.compareInit = 'true';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof addToCompare === 'function') addToCompare(btn.getAttribute('data-product-id'));
        });
    });
    if (typeof window.initializeWishlistButtons === 'function') window.initializeWishlistButtons();
    initializeCountdownTimers(container);
    initCardClickRedirect(container);
}

function initializeCountdownTimers(container) {
    container = container || document;
    container.querySelectorAll('.countdown-timer').forEach(function(timer) {
        if (timer.dataset.initialized === 'true') return;
        timer.dataset.initialized = 'true';
        var endTime = Date.now() + (Math.floor(Math.random()*24)*3600 + Math.floor(Math.random()*60)*60 + Math.floor(Math.random()*60)) * 1000;
        var hEl = timer.querySelector('.countdown-hours');
        var mEl = timer.querySelector('.countdown-minutes');
        var sEl = timer.querySelector('.countdown-seconds');
        function tick() {
            var d = endTime - Date.now();
            if (d < 0) return;
            if (hEl) hEl.textContent = String(Math.floor((d % 86400000) / 3600000)).padStart(2,'0');
            if (mEl) mEl.textContent = String(Math.floor((d % 3600000)  / 60000)).padStart(2,'0');
            if (sEl) sEl.textContent = String(Math.floor((d % 60000)    / 1000)).padStart(2,'0');
        }
        setInterval(tick, 1000); tick();
    });
}

/* ──────────────────────────────────────────────────────────
   4.  BOOT
   ────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    initSlider('trendingSliderViewport',    '.trending-prev',     '.trending-next');
    initSlider('bestSellingSliderViewport', '.best-selling-prev', '.best-selling-next');
    initVideoSlider();
    initializeProductCards();
});

window.initializeProductCards = initializeProductCards;
window.initSlider = initSlider;
