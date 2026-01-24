/**
 * Wishlist Functionality with AJAX
 */

let wishlistData = [];

// Initialize wishlist on page load
document.addEventListener('DOMContentLoaded', function () {
    loadWishlist();
    initializeWishlistButtons();

    // Global event listener for wishlist buttons (handles lazy loaded items too)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.wishlist-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const productId = btn.getAttribute('data-product-id');
            if (productId) {
                toggleWishlist(productId, btn);
            }
        }
    });
});

// Initialize wishlist button states
function initializeWishlistButtons() {
    // Wait a bit for wishlist data to load
    setTimeout(() => {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            const productId = btn.getAttribute('data-product-id');
            const icon = btn.querySelector('i');
            const tooltip = btn.querySelector('.product-tooltip');
            
            const isCardButton = btn.classList.contains('absolute');
            
            if (productId && isInWishlist(productId)) {
                if (isCardButton) {
                    // Card Button: White Heart on Black Bg
                    btn.classList.remove('bg-white', 'text-black');
                    btn.classList.add('bg-black', 'text-white');
                    if (icon) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    }
                } else {
                    // Text Button: Black Filled Heart, No Bg Change
                    if (icon) {
                        icon.classList.remove('far');
                        icon.classList.add('fas', 'text-black');
                    }
                }

                if (tooltip) {
                    tooltip.textContent = 'Remove from Wishlist';
                    btn.setAttribute('title', 'Remove from Wishlist');
                } else if (btn.textContent.toLowerCase().includes('wishlist')) {
                    if(icon) {
                         btn.innerHTML = '';
                         btn.appendChild(icon);
                         btn.appendChild(document.createTextNode(' Remove from Wishlist'));
                    } else {
                         btn.textContent = 'Remove from Wishlist';
                    }
                }
            } else {
                if (isCardButton) {
                    // Card Button: Black Outline Heart on White Bg
                    btn.classList.remove('bg-black', 'text-white');
                    btn.classList.add('bg-white', 'text-black');
                     if (icon) {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                } else {
                     // Text Button: Outline Heart
                     if (icon) {
                        icon.classList.remove('fas', 'text-black');
                        icon.classList.add('far');
                    }
                }

                // Only revert tooltip for generic cards
                if (tooltip) {
                    tooltip.textContent = 'Add to Wishlist';
                    btn.setAttribute('title', 'Add to Wishlist');
                }
            }
        });
    }, 100);
}

// Load wishlist from cookie
function loadWishlist() {
    // Clear any old cookies with wrong path first
    const oldPaths = ['/zensshop', '/oecom'];
    oldPaths.forEach(path => {
        document.cookie = `wishlist_items=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path}; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
    });

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

            if (parsed && Array.isArray(parsed) && parsed.length > 0) {
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
        // Clear old cookies with wrong paths first
        const oldPaths = ['/zensshop', '/oecom'];
        oldPaths.forEach(path => {
            document.cookie = `wishlist_items=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path}; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
        });

        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/wishlist.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.success && Array.isArray(data.wishlist)) {
            wishlistData = data.wishlist;

            // Update cookie with correct path
            if (wishlistData.length > 0) {
                const cookieData = JSON.stringify(wishlistData);
                const expiry = new Date();
                expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = `wishlist_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
            }

            updateWishlistCount();
            initializeWishlistButtons();
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
        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/wishlist.php', {
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

            // ALWAYS set cookie via JavaScript from response data - MUST be before any return
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
                    // Always use path=/ for cookies - works for all subdirectories
                    const cookieString = `wishlist_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[WISHLIST] Error setting cookie:', e);
                }
            } else if (data.wishlist && Array.isArray(data.wishlist)) {
                try {
                    const cookieData = JSON.stringify(data.wishlist);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    const cookieString = `wishlist_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[WISHLIST] Error setting cookie (fallback):', e);
                }
            }

            // Update wishlist data
            wishlistData = data.wishlist || [];
            updateWishlistCount();

            // Reload page if on wishlist page to show updated items
            if (window.location.pathname.includes('wishlist') || window.location.pathname.includes('wishlist.php')) {
                window.location.reload();
                return;
            }

            // Update button state (only if not on wishlist page)
            if (button) {
                const icon = button.querySelector('i');
                const tooltip = button.querySelector('.product-tooltip');
                
                const isCardButton = button.classList.contains('absolute');

                if (isInWishlist) {
                    // It WAS in wishlist (so we are Removing) -> Show "Add" (Outline)
                    if (isCardButton) {
                        button.classList.remove('bg-black', 'text-white');
                        button.classList.add('bg-white', 'text-black');
                        if (icon) {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                        }
                    } else {
                        // Text Button
                        if (icon) {
                            icon.classList.remove('fas', 'text-black');
                            icon.classList.add('far');
                        }
                    }

                    if (tooltip) {
                        tooltip.textContent = 'Add to Wishlist';
                        button.setAttribute('title', 'Add to Wishlist');
                    } else if (button.textContent.toLowerCase().includes('wishlist')) {
                        if(icon) {
                             button.innerHTML = '';
                             button.appendChild(icon);
                             button.appendChild(document.createTextNode(' Add to Wishlist'));
                        } else {
                             button.textContent = 'Add to Wishlist';
                        }
                    }
                } else {
                     // It WAS NOT in wishlist (so we are Adding) -> Show "Remove" (Filled)
                    if (isCardButton) {
                        button.classList.remove('bg-white', 'text-black');
                        button.classList.add('bg-black', 'text-white');
                        if (icon) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                        }
                    } else {
                        // Text Button
                        if (icon) {
                            icon.classList.remove('far');
                            icon.classList.add('fas', 'text-black');
                        }
                    }

                    if (tooltip) {
                        tooltip.textContent = 'Remove from Wishlist';
                        button.setAttribute('title', 'Remove from Wishlist');
                    } else if (button.textContent.toLowerCase().includes('wishlist')) {
                        if(icon) {
                             button.innerHTML = '';
                             button.appendChild(icon);
                             button.appendChild(document.createTextNode(' Remove from Wishlist'));
                        } else {
                             button.textContent = 'Remove from Wishlist';
                        }
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
        const response = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/wishlist.php', {
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

            // ALWAYS set cookie via JavaScript from response data - MUST be before any return
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
                    // Always use path=/ for cookies - works for all subdirectories
                    const cookieString = `wishlist_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[WISHLIST] Error setting cookie:', e);
                }
            } else if (data.wishlist && Array.isArray(data.wishlist)) {
                try {
                    const cookieData = JSON.stringify(data.wishlist);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    const cookieString = `wishlist_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[WISHLIST] Error setting cookie (fallback):', e);
                }
            }

            // Update wishlist data
            wishlistData = data.wishlist || [];
            updateWishlistCount();

            // Reload page if on wishlist page to show updated items
            if (window.location.pathname.includes('wishlist') || window.location.pathname.includes('wishlist.php')) {
                window.location.reload();
                return;
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
    if (!productId) return false;
    const strId = String(productId).trim();
    return wishlistData.some(item => {
        const pId = item.product_id ? String(item.product_id).trim() : null;
        const iId = item.id ? String(item.id).trim() : null;
        return pId === strId || iId === strId;
    });
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

