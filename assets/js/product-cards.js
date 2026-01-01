/**
 * Product Card Interactions
 * Handles wishlist, quick view, compare, and hover effects
 */

// Initialize product card interactions
function initializeProductCards() {
    // Wishlist buttons
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        // Remove existing listeners
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.getAttribute('data-product-id');
            toggleWishlist(productId, this);
        });
    });
    
    // Quick view buttons
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        // If it's already a link, don't override it
        if (btn.tagName === 'A') {
            return;
        }
        
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productSlug = this.getAttribute('data-product-slug');
            const productId = this.getAttribute('data-product-id');
            
            if (productSlug) {
                // Navigate to product detail page
                const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
                window.location.href = baseUrl + '/product?slug=' + encodeURIComponent(productSlug);
            } else {
                showQuickView(productId);
            }
        });
    });
    
    // Compare buttons
    document.querySelectorAll('.compare-btn').forEach(btn => {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.getAttribute('data-product-id');
            addToCompare(productId);
        });
    });
    
    // Add to cart hover buttons
    document.querySelectorAll('.add-to-cart-hover-btn').forEach(btn => {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = parseInt(this.getAttribute('data-product-id'));
            if (typeof addToCart === 'function') {
                addToCart(productId, 1);
            }
        });
    });
    
    // Initialize countdown timers
    initializeCountdownTimers();
}

function toggleWishlist(productId, button) {
    // Use global toggleWishlist function if available
    if (typeof window.toggleWishlist === 'function') {
        window.toggleWishlist(productId, button);
    } else {
        // Fallback to simple toggle
        const icon = button.querySelector('i');
        if (icon.classList.contains('fas')) {
            icon.classList.remove('fas');
            icon.classList.add('far');
            button.classList.remove('bg-black', 'text-white');
            button.classList.add('bg-white');
            if (typeof showNotification === 'function') {
                showNotification('Removed from wishlist', 'info');
            }
        } else {
            icon.classList.remove('far');
            icon.classList.add('fas');
            button.classList.add('bg-black', 'text-white');
            button.classList.remove('bg-white');
            if (typeof showNotification === 'function') {
                showNotification('Added to wishlist', 'success');
            }
        }
    }
}

function showQuickView(productId) {
    // Try to get slug from the button or navigate by ID
    const btn = document.querySelector(`[data-product-id="${productId}"]`);
    const slug = btn ? btn.getAttribute('data-product-slug') : null;
    
    if (slug) {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        window.location.href = baseUrl + '/product?slug=' + encodeURIComponent(slug);
    } else {
        // Fallback: try to fetch product by ID and redirect
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        fetch(baseUrl + '/api/products.php?id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product && data.product.slug) {
                    window.location.href = baseUrl + '/product?slug=' + encodeURIComponent(data.product.slug);
                } else {
                    showNotification('Product not found', 'error');
                }
            })
            .catch(() => {
                showNotification('Unable to load product', 'error');
            });
    }
}

function addToCompare(productId) {
    // Add to compare (you can implement this later)
    showNotification('Product added to compare', 'success');
}

function initializeCountdownTimers() {
    const timers = document.querySelectorAll('.countdown-timer');
    timers.forEach(timer => {
        // Skip if already initialized
        if (timer.dataset.initialized === 'true') return;
        timer.dataset.initialized = 'true';
        
        // Set countdown to 24 hours from now
        const endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
        
        function updateTimer() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance < 0) {
                timer.innerHTML = '00 d : 00 h : 00 m : 00 s';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const daysEl = timer.querySelector('.countdown-days');
            const hoursEl = timer.querySelector('.countdown-hours');
            const minutesEl = timer.querySelector('.countdown-minutes');
            const secondsEl = timer.querySelector('.countdown-seconds');
            
            if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
            if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
            if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
            if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeProductCards();
});

// Make function globally available
window.initializeProductCards = initializeProductCards;

