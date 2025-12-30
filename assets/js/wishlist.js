/**
 * Wishlist Functionality with AJAX
 */

let wishlistData = [];

// Initialize wishlist on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWishlist();
    initializeWishlistButtons();
});

// Initialize wishlist button states
function initializeWishlistButtons() {
    // Wait a bit for wishlist data to load
    setTimeout(() => {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            const productId = parseInt(btn.getAttribute('data-product-id'));
            if (productId && isInWishlist(productId)) {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    btn.classList.add('bg-red-500', 'text-white');
                    btn.classList.remove('bg-white');
                }
            } else {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    btn.classList.remove('bg-red-500', 'text-white');
                    btn.classList.add('bg-white');
                }
            }
        });
    }, 100);
}

// Load wishlist from cookie
function loadWishlist() {
    const wishlistCookie = getCookie('wishlist_items');
    if (wishlistCookie) {
        try {
            // Try parsing as-is first
            let parsed = null;
            try {
                parsed = JSON.parse(wishlistCookie);
            } catch (e1) {
                // Try URL decoding
                try {
                    parsed = JSON.parse(decodeURIComponent(wishlistCookie));
                } catch (e2) {
                    console.error('Error parsing wishlist cookie:', e2);
                    parsed = null;
                }
            }
            
            if (parsed && Array.isArray(parsed)) {
                wishlistData = parsed;
            } else {
                wishlistData = [];
            }
        } catch (e) {
            console.error('Error parsing wishlist cookie:', e);
            wishlistData = [];
        }
    } else {
        wishlistData = [];
    }
    
    updateWishlistCount();
}

// Refresh wishlist from API
async function refreshWishlist() {
    try {
        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '/zensshop') + '/api/wishlist.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.wishlist)) {
            wishlistData = data.wishlist;
            updateWishlistCount();
        } else {
            // Fallback to cookie
            loadWishlist();
        }
    } catch (error) {
        console.error('Error refreshing wishlist:', error);
        // Fallback to cookie
        loadWishlist();
    }
}

// Toggle wishlist item
async function toggleWishlist(productId, button) {
    try {
        // Check if already in wishlist
        const isInWishlist = wishlistData.some(item => item.product_id == productId);
        
        const method = isInWishlist ? 'DELETE' : 'POST';
        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '/zensshop') + '/api/wishlist.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.wishlist)) {
            wishlistData = data.wishlist;
            updateWishlistCount();
            
            // Update button state
            if (button) {
                const icon = button.querySelector('i');
                if (icon) {
                    if (isInWishlist) {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        button.classList.remove('bg-red-500', 'text-white');
                        button.classList.add('bg-white');
                    } else {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        button.classList.add('bg-red-500', 'text-white');
                        button.classList.remove('bg-white');
                    }
                }
            }
            
            // Show notification
            const message = isInWishlist ? 'Removed from wishlist' : 'Added to wishlist';
            const type = isInWishlist ? 'info' : 'success';
            
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(message, type);
            } else if (typeof showNotification === 'function') {
                showNotification(message, type);
            }
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to update wishlist', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to update wishlist', 'error');
            }
        }
    } catch (error) {
        console.error('Error toggling wishlist:', error);
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('An error occurred. Please try again.', 'error');
        } else if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        }
    }
}

// Remove from wishlist
async function removeFromWishlist(productId) {
    try {
        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '/zensshop') + '/api/wishlist.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.wishlist)) {
            wishlistData = data.wishlist;
            updateWishlistCount();
            
            // Reload page if on wishlist page
            if (window.location.pathname.includes('wishlist.php')) {
                window.location.reload();
            }
            
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Product removed from wishlist', 'success');
            } else if (typeof showNotification === 'function') {
                showNotification('Product removed from wishlist', 'success');
            }
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to remove product', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to remove product', 'error');
            }
        }
    } catch (error) {
        console.error('Error removing from wishlist:', error);
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('An error occurred. Please try again.', 'error');
        } else if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        }
    }
}

// Update wishlist count badge
function updateWishlistCount() {
    const count = wishlistData.length;
    const wishlistCountElements = document.querySelectorAll('.wishlist-count');
    wishlistCountElements.forEach(el => {
        el.textContent = count;
        if (count === 0) {
            el.style.display = 'none';
        } else {
            el.style.display = 'flex';
        }
    });
}

// Check if product is in wishlist
function isInWishlist(productId) {
    return wishlistData.some(item => item.product_id == productId);
}

// Get cookie value
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Make functions globally available
window.toggleWishlist = toggleWishlist;
window.removeFromWishlist = removeFromWishlist;
window.refreshWishlist = refreshWishlist;
window.updateWishlistCount = updateWishlistCount;
window.isInWishlist = isInWishlist;

