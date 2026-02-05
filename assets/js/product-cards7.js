/**
 * Product Card Interactions
 * Handles wishlist, quick view, compare, and hover effects
 */

/**
 * Initialize product card interactions
 * @param {HTMLElement|Document} container - Optional scoping container
 */
function initializeProductCards(container = document) {
    // 1. Quick view buttons
    container.querySelectorAll('.quick-view-btn').forEach(btn => {
        if (btn.dataset.qvInit === "true") return;
        btn.dataset.qvInit = "true";
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productSlug = this.getAttribute('data-product-slug');
            
            if (productSlug && typeof window.openQuickView === 'function') {
                window.openQuickView(productSlug);
            } else if (productSlug) {
                const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
                window.location.href = baseUrl + '/product?slug=' + encodeURIComponent(productSlug);
            }
        });
    });
    
    // 2. Compare buttons
    container.querySelectorAll('.compare-btn').forEach(btn => {
        if (btn.dataset.compareInit === "true") return;
        btn.dataset.compareInit = "true";
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.getAttribute('data-product-id');
            if (typeof addToCompare === 'function') addToCompare(productId);
        });
    });
    
    // 3. Wishlist State Sync (scoping doesn't apply to global state sync but we trigger it)
    if (typeof window.initializeWishlistButtons === 'function') {
        window.initializeWishlistButtons();
    }
    
    // 4. Initialize countdown timers
    initializeCountdownTimers(container);
}

/**
 * Global Fallback for Wishlist Toggle
 */
function toggleWishlist(productId, button) {
    if (typeof window.toggleWishlist === 'function' && window.toggleWishlist !== toggleWishlist) {
        window.toggleWishlist(productId, button);
    } else {
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('fas');
            icon.classList.toggle('far');
            button.classList.toggle('bg-black');
            button.classList.toggle('text-white');
        }
    }
}

function addToCompare(productId) {
    if (typeof showNotification === 'function') {
        showNotification('Product added to compare', 'success');
    }
}

function initializeCountdownTimers(container = document) {
    container.querySelectorAll('.countdown-timer').forEach(timer => {
        if (timer.dataset.initialized === 'true') return;
        timer.dataset.initialized = 'true';
        
        const h = Math.floor(Math.random() * 24);
        const m = Math.floor(Math.random() * 60);
        const s = Math.floor(Math.random() * 60);
        const endTime = Date.now() + (h * 3600 + m * 60 + s) * 1000;
        
        const hEl = timer.querySelector('.countdown-hours');
        const mEl = timer.querySelector('.countdown-minutes');
        const sEl = timer.querySelector('.countdown-seconds');
        
        function updateTimer() {
            const now = Date.now();
            const d = endTime - now;
            if (d < 0) return;
            
            const hours = Math.floor((d % (86400000)) / 3600000);
            const minutes = Math.floor((d % (3600000)) / 60000);
            const seconds = Math.floor((d % (60000)) / 1000);
            
            if (hEl) hEl.textContent = String(hours).padStart(2, '0');
            if (mEl) mEl.textContent = String(minutes).padStart(2, '0');
            if (sEl) sEl.textContent = String(seconds).padStart(2, '0');
        }
        setInterval(updateTimer, 1000);
        updateTimer();
    });
}

document.addEventListener('DOMContentLoaded', () => initializeProductCards());
window.initializeProductCards = initializeProductCards;

