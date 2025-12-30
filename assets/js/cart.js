/**
 * Cart Functionality with AJAX
 */

let cartData = [];

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', function() {
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
        cartBtn.addEventListener('click', function() {
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
    document.addEventListener('keydown', function(e) {
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
async function addToCart(productId, quantity = 1) {
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;
            updateCartUI();
            updateCartCount();
            
            // Show success message
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Product added to cart!', 'success');
            } else if (typeof showNotification === 'function') {
                showNotification('Product added to cart!', 'success');
            }
            
            // Open cart panel
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
    }
}

// Update cart item quantity
async function updateCartItem(productId, quantity) {
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;
            updateCartUI();
            updateCartCount();
        } else {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to update cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to update cart', 'error');
            }
        }
    } catch (error) {
        console.error('Error updating cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

// Remove from cart
async function removeFromCart(productId) {
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove',
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cartData = Array.isArray(data.cart) ? data.cart : [];
            updateCartUI();
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
            }
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
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
        if (cartTotal) cartTotal.textContent = '$0.00';
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
        
        html += `
            <div class="flex items-center space-x-4 mb-4 pb-4 border-b" data-product-id="${item.product_id}">
                <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.name)}" class="w-20 h-20 object-cover rounded" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+PGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4='">
                <div class="flex-1">
                    <h4 class="font-semibold text-sm">${escapeHtml(item.name)}</h4>
                    <p class="text-gray-600 text-sm">${formatCurrency(itemPrice)}</p>
                    <div class="flex items-center space-x-2 mt-2">
                        <button onclick="updateCartItem(${item.product_id}, ${itemQuantity - 1})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100 text-sm">-</button>
                        <span class="w-8 text-center text-sm">${itemQuantity}</span>
                        <button onclick="updateCartItem(${item.product_id}, ${itemQuantity + 1})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100 text-sm">+</button>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-sm">${formatCurrency(itemTotal)}</p>
                    <button onclick="removeFromCart(${item.product_id})" class="text-red-500 hover:text-red-700 mt-2 text-sm" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsContainer.innerHTML = html;
    if (cartTotal) cartTotal.textContent = formatCurrency(total);
}

// Update cart count badge
function updateCartCount() {
    const count = cartData.reduce((sum, item) => sum + item.quantity, 0);
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
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-500' : 
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
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Make functions globally available
window.addToCart = addToCart;
window.updateCartItem = updateCartItem;
window.removeFromCart = removeFromCart;
window.refreshCart = refreshCart;
window.updateCartUI = updateCartUI;
window.updateCartCount = updateCartCount;

