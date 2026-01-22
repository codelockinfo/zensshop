/**
 * Cart Functionality with AJAX
 */

let cartData = [];

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', function () {
    loadCart();
    setupCartUI();
});

// Setup Cart UI
function setupCartUI() {
    const cartBtn = document.getElementById('cartBtn');
    const closeCart = document.getElementById('closeCart');
    const sideCart = document.getElementById('sideCart');
    const cartOverlay = document.getElementById('cartOverlay');

    // Open cart
    if (cartBtn) {
        cartBtn.addEventListener('click', function () {
            // Refresh cart data when opening
            refreshCart();
            sideCart.classList.remove('translate-x-full');
            cartOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }

    // Close cart
    if (closeCart) {
        closeCart.addEventListener('click', closeCartPanel);
    }

    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCartPanel);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !sideCart.classList.contains('translate-x-full')) {
            closeCartPanel();
        }
    });
}

function closeCartPanel() {
    const sideCart = document.getElementById('sideCart');
    const cartOverlay = document.getElementById('cartOverlay');

    sideCart.classList.add('translate-x-full');
    cartOverlay.classList.add('hidden');
    document.body.style.overflow = '';
}

// Load cart from cookie
function loadCart() {
    const cartCookie = getCookie('cart_items');
    if (cartCookie) {
        try {
            // Try parsing as-is first
            let parsed = null;
            try {
                parsed = JSON.parse(cartCookie);
            } catch (e1) {
                // Try URL decoding
                try {
                    parsed = JSON.parse(decodeURIComponent(cartCookie));
                } catch (e2) {
                    console.error('Error parsing cart cookie:', e2);
                    parsed = null;
                }
            }

            if (parsed && Array.isArray(parsed)) {
                cartData = parsed;
            } else {
                cartData = [];
            }
        } catch (e) {
            console.error('Error parsing cart cookie:', e);
            cartData = [];
        }
    } else {
        cartData = [];
    }

    updateCartUI();
    updateCartCount();
}

// Refresh cart from API
async function refreshCart() {
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;

            // Update cookie from API response if cookie_data is provided
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie from refresh:', e);
                }
            }

            updateCartUI();
            updateCartCount();
        } else {
            // Fallback to cookie
            loadCart();
        }
    } catch (error) {
        console.error('Error refreshing cart:', error);
        // Fallback to cookie
        loadCart();
    }
}

// Add to cart
async function addToCart(productId, quantity = 1, btn = null, attributes = {}) {
    if (btn) setBtnLoading(btn, true);
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const requestBody = {
            product_id: productId,
            quantity: quantity,
            variant_attributes: attributes
        };

        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json();

        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;

            // ALWAYS set cookie via JavaScript from response data - MUST be before any return
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
                    // Always use path=/ for cookies - works for all subdirectories
                    const cookieString = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[CART] Error setting cookie:', e);
                }
            } else if (data.cart && Array.isArray(data.cart)) {
                // Fallback: set cookie from cart data
                try {
                    const cookieData = JSON.stringify(data.cart);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    const cookieString = `cart_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieString;
                } catch (e) {
                    console.error('[CART] Error setting cookie (fallback):', e);
                }
            }

            // Reload cart from cookie to ensure UI is updated with latest data
            loadCart();

            // Check if we're on the cart page
            const isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');

            if (isCartPage) {
                // Reload cart page to show new item
                window.location.reload();
                return;
            }

            // Ensure UI is updated (loadCart already does this, but double-check)
            updateCartUI();
            updateCartCount();

            // Show success message
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Product added to cart!', 'success');
            } else if (typeof showNotification === 'function') {
                showNotification('Product added to cart!', 'success');
            }

            // Open cart panel (only if not on cart page)
            const sideCart = document.getElementById('sideCart');
            const cartOverlay = document.getElementById('cartOverlay');
            if (sideCart && cartOverlay) {
                sideCart.classList.remove('translate-x-full');
                cartOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to add product to cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to add product to cart', 'error');
            }
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}

// Update cart item quantity
async function updateCartItem(productId, quantity, btn = null, attributes = {}) {
    if (btn) setBtnLoading(btn, true);
    if (quantity < 1) {
        quantity = 1;
    }

    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                variant_attributes: attributes
            })
        });

        const data = await response.json();

        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;

            // ALWAYS set cookie via JavaScript from response data
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie:', e);
                }
            } else if (data.cart) {
                try {
                    const cookieData = JSON.stringify(data.cart);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie (fallback):', e);
                }
            }

            // Reload cart from cookie to update UI
            loadCart();

            // Check if we're on the cart page
            const isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');

            if (isCartPage) {
                // Update cart page DOM
                const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                if (cartItem) {
                    // Update quantity display
                    const qtySpan = cartItem.querySelector('.item-quantity');
                    if (qtySpan) {
                        qtySpan.textContent = quantity;
                    }

                    // Update total for this item
                    const itemPriceEl = cartItem.querySelector('.item-price');
                    if (itemPriceEl) {
                        const itemPrice = parseFloat(itemPriceEl.textContent.replace(/[$,]/g, ''));
                        const itemTotal = cartItem.querySelector('.item-total span');
                        if (itemTotal) {
                            itemTotal.textContent = (itemPrice * quantity).toFixed(2);
                        }
                    }

                    // Update cart totals
                    if (data.total !== undefined) {
                        const cartSubtotal = document.getElementById('cartSubtotal');
                        const cartTotal = document.getElementById('cartTotal');
                        const currency = cartData.length > 0 ? (cartData[0].currency || 'USD') : 'USD';
                        if (cartSubtotal) cartSubtotal.textContent = formatCurrency(data.total, currency);
                        if (cartTotal) cartTotal.textContent = formatCurrency(data.total, currency);
                    }
                } else {
                    // Item not found, reload page
                    window.location.reload();
                    return;
                }
            } else {
                // Update side cart UI
                updateCartUI();
            }

            // Update cart count in header
            if (data.count !== undefined) {
                window.lastCartCount = data.count;
            }
            updateCartCount();
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to update cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to update cart', 'error');
            } else {
                alert(data.message || 'Failed to update cart');
            }
        }
    } catch (error) {
        console.error('Error updating cart:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        } else {
            alert('An error occurred. Please try again.');
        }
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}

// Remove from cart
async function removeFromCart(productId, btn = null, attributes = {}) {
    if (btn) setBtnLoading(btn, true);
    // Check if we're on the cart page
    const isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');

    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                variant_attributes: attributes
            })
        });

        const data = await response.json();

        if (data.success) {
            cartData = Array.isArray(data.cart) ? data.cart : [];

            // ALWAYS set cookie via JavaScript from response data
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie (remove):', e);
                }
            } else if (data.cart) {
                try {
                    const cookieData = JSON.stringify(data.cart);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie (remove fallback):', e);
                }
            }

            // Reload cart from cookie to update UI
            loadCart();

            if (isCartPage) {
                // Remove item from cart page DOM immediately
                const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                if (cartItem) {
                    cartItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    cartItem.style.opacity = '0';
                    cartItem.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        cartItem.remove();

                        // Update cart totals
                        if (data.total !== undefined) {
                            const cartSubtotal = document.getElementById('cartSubtotal');
                            const cartTotal = document.getElementById('cartTotal');
                            if (cartSubtotal) cartSubtotal.textContent = formatCurrency(data.total);
                            if (cartTotal) cartTotal.textContent = formatCurrency(data.total);
                        }

                        // Check if cart is empty
                        const remainingItems = document.querySelectorAll('.cart-item');
                        if (remainingItems.length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                } else {
                    // Item not found, reload page
                    window.location.reload();
                    return;
                }
            } else {
                // Update side cart UI
                updateCartUI();
            }

            // Update cart count in header
            if (data.count !== undefined) {
                window.lastCartCount = data.count;
            }
            updateCartCount();

            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Product removed from cart', 'success');
            } else if (typeof showNotification === 'function') {
                showNotification('Product removed from cart', 'success');
            }
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to remove product', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to remove product', 'error');
            } else {
                alert(data.message || 'Failed to remove product');
            }
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        } else {
            alert('An error occurred. Please try again.');
        }
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}

// Update cart UI
function updateCartUI() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');

    if (!cartItemsContainer) return;

    if (cartData.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                <p>Your cart is empty</p>
            </div>
        `;
        if (cartTotal) cartTotal.textContent = formatCurrency(0, 'USD');
        return;
    }

    let html = '';
    let total = 0;

    cartData.forEach(item => {
        // Ensure we have valid data
        if (!item || !item.product_id || !item.name) {
            console.warn('Invalid cart item:', item);
            return;
        }

        const itemPrice = parseFloat(item.price) || 0;
        const itemQuantity = parseInt(item.quantity) || 1;
        const itemTotal = itemPrice * itemQuantity;
        total += itemTotal;

        // Get image URL - handle both relative and absolute paths
        let imageUrl = item.image || '';
        if (!imageUrl || imageUrl === 'null' || imageUrl === 'undefined' || imageUrl === '') {
            // Use placeholder SVG
            imageUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+PGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4=';
        } else if (imageUrl.indexOf('http') !== 0 && imageUrl.indexOf('/') !== 0 && imageUrl.indexOf('data:') !== 0) {
            const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
            imageUrl = baseUrl + '/assets/images/uploads/' + imageUrl;
        } else if (imageUrl.indexOf('/') !== 0 && imageUrl.indexOf('http') !== 0 && imageUrl.indexOf('data:') !== 0) {
            const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
            imageUrl = baseUrl + imageUrl;
        }

        const variantAttributes = item.variant_attributes || {};
        const variantLabel = Object.entries(variantAttributes)
            .map(([key, val]) => `<span class="text-xs text-gray-500 block">${key}: ${val}</span>`)
            .join('');
        
        const attributesJson = JSON.stringify(variantAttributes).replace(/"/g, '&quot;');

        html += `
            <div class="side-cart-item-wrapper mb-4 pb-4 border-b" data-product-id="${item.product_id}" data-attributes='${attributesJson}'>
                <div class="flex items-center space-x-4 side-cart-item" data-product-id="${item.product_id}">
                    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.name)}" class="w-20 h-20 object-cover rounded" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+PGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                    <div class="flex-1">
                        <h4 class="font-semibold text-sm mb-1">${escapeHtml(item.name)}</h4>
                        ${variantLabel}
                        <p class="text-gray-600 text-sm mt-1">${formatCurrency(itemPrice, item.currency)}</p>
                        <div class="flex items-center space-x-2 mt-2">
                            <button onclick="updateCartItem(${item.product_id}, ${itemQuantity - 1}, null, ${attributesJson})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100 text-sm">-</button>
                            <span class="w-8 text-center text-sm">${itemQuantity}</span>
                            <button onclick="updateCartItem(${item.product_id}, ${itemQuantity + 1}, null, ${attributesJson})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100 text-sm">+</button>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-sm">${formatCurrency(itemTotal, item.currency)}</p>
                        <button onclick="showSideCartInlineRemoveConfirm(this)" class="text-red-500 hover:text-red-700 mt-2 text-sm" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <!-- Side Cart Inline Remove Confirmation -->
                <div class="side-cart-remove-confirm-inline flex items-center space-x-4 p-4 bg-gray-50 rounded border border-gray-300 hidden" data-product-id="${item.product_id}">
                    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.name)}" class="w-16 h-16 object-cover rounded border border-gray-200" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+PGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                    <div class="flex-1">
                        <h4 class="font-semibold text-sm mb-1 text-gray-800">${escapeHtml(item.name)}</h4>
                        <p class="text-gray-600 text-xs mb-2">Add to wishlist before remove?</p>
                        <div class="flex space-x-2">
                            <button onclick="confirmSideCartInlineRemoveWithWishlist(${item.product_id}, ${attributesJson})" class="px-4 py-1.5 bg-black text-white text-xs font-medium rounded hover:bg-gray-800 transition">Yes</button>
                            <button onclick="confirmSideCartInlineRemoveWithoutWishlist(${item.product_id}, ${attributesJson})" class="px-4 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded hover:bg-gray-50 transition">No</button>
                        </div>
                    </div>
                    <button onclick="cancelSideCartInlineRemoveConfirm(this)" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
        `;
    });

    cartItemsContainer.innerHTML = html;
    const cartCurrency = cartData.length > 0 ? (cartData[0].currency || 'USD') : 'USD';
    if (cartTotal) cartTotal.textContent = formatCurrency(total, cartCurrency);
}

// Update cart count badge
function updateCartCount() {
    // Use API response count if available, otherwise calculate from cartData
    let count = 0;
    if (typeof window.lastCartCount !== 'undefined') {
        count = window.lastCartCount;
    } else {
        count = cartData.reduce((sum, item) => sum + parseInt(item.quantity || 0), 0);
    }

    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(el => {
        el.textContent = count;
    });
}

// Get cookie value
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Show notification
function showNotification(message, type = 'info') {
    // Use custom modal if available
    if (typeof showNotificationModal === 'function') {
        showNotificationModal(message, type);
        return;
    }

    // Fallback to simple notification
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' :
                'bg-blue-500'
        } text-white`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format currency
function formatCurrency(amount, currencyCode) {
    const symbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'INR': '₹',
        'CAD': 'C$',
        'AUD': 'A$',
        'JPY': '¥',
        'KRW': '₩',
        'CNY': '¥',
        'RUB': '₽'
    };

    // If currencyCode provided, use it
    if (currencyCode) {
        const code = currencyCode.toUpperCase();
        const symbol = symbols[code] || '₹';
        return symbol + parseFloat(amount).toFixed(2);
    }

    // Fallback to global symbol
    const symbol = typeof CURRENCY_SYMBOL !== 'undefined' ? CURRENCY_SYMBOL : '₹';
    return symbol + parseFloat(amount).toFixed(2);
}

// Side Cart Inline Remove Confirm Functions
function showSideCartInlineRemoveConfirm(btn) {
    const wrapper = btn.closest('.side-cart-item-wrapper');
    if (wrapper) {
        const cartItem = wrapper.querySelector('.side-cart-item');
        const confirmBox = wrapper.querySelector('.side-cart-remove-confirm-inline');
        if (cartItem && confirmBox) {
            cartItem.classList.add('hidden');
            confirmBox.classList.remove('hidden');
        }
    }
}

function cancelSideCartInlineRemoveConfirm(btn) {
    const wrapper = btn.closest('.side-cart-item-wrapper');
    if (wrapper) {
        const cartItem = wrapper.querySelector('.side-cart-item');
        const confirmBox = wrapper.querySelector('.side-cart-remove-confirm-inline');
        if (cartItem && confirmBox) {
            cartItem.classList.remove('hidden');
            confirmBox.classList.add('hidden');
        }
    }
}

async function confirmSideCartInlineRemoveWithWishlist(productId, attributes = {}) {
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
    try {
        // Add to wishlist
        const wishlistResponse = await fetch(baseUrl + '/api/wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });
        const wishlistResult = await wishlistResponse.json();

        // Update wishlist count
        if (wishlistResult.success && typeof refreshWishlist === 'function') {
            await refreshWishlist();
        }

        // Remove from cart
        await removeFromCart(productId, null, attributes);
    } catch (error) {
        console.error('Error adding to wishlist:', error);
        await removeFromCart(productId, null, attributes);
    }
}

async function confirmSideCartInlineRemoveWithoutWishlist(productId, attributes = {}) {
    await removeFromCart(productId, null, attributes);
}

// Make functions globally available
window.addToCart = addToCart;
window.updateCartItem = updateCartItem;
window.removeFromCart = removeFromCart;
window.refreshCart = refreshCart;
window.updateCartUI = updateCartUI;
window.updateCartCount = updateCartCount;
window.showSideCartInlineRemoveConfirm = showSideCartInlineRemoveConfirm;
window.cancelSideCartInlineRemoveConfirm = cancelSideCartInlineRemoveConfirm;
window.confirmSideCartInlineRemoveWithWishlist = confirmSideCartInlineRemoveWithWishlist;
window.confirmSideCartInlineRemoveWithoutWishlist = confirmSideCartInlineRemoveWithoutWishlist;

