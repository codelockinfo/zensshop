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
            cartData = JSON.parse(cartCookie);
            updateCartUI();
        } catch (e) {
            console.error('Error parsing cart cookie:', e);
            cartData = [];
        }
    }
}

// Add to cart
async function addToCart(productId, quantity = 1) {
    try {
        const response = await fetch('/oecom/api/cart.php', {
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
        
        if (data.success) {
            cartData = data.cart;
            updateCartUI();
            updateCartCount();
            
            // Show success message
            showNotification('Product added to cart!', 'success');
            
            // Open cart panel
            const sideCart = document.getElementById('sideCart');
            const cartOverlay = document.getElementById('cartOverlay');
            if (sideCart && cartOverlay) {
                sideCart.classList.remove('translate-x-full');
                cartOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        } else {
            showNotification(data.message || 'Failed to add product to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

// Update cart item quantity
async function updateCartItem(productId, quantity) {
    try {
        const response = await fetch('/oecom/api/cart.php', {
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
        
        if (data.success) {
            cartData = data.cart;
            updateCartUI();
            updateCartCount();
        } else {
            showNotification(data.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Error updating cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

// Remove from cart
async function removeFromCart(productId) {
    try {
        const response = await fetch('/oecom/api/cart.php', {
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
            cartData = data.cart;
            updateCartUI();
            updateCartCount();
            showNotification('Product removed from cart', 'success');
        } else {
            showNotification(data.message || 'Failed to remove product', 'error');
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
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        html += `
            <div class="flex items-center space-x-4 mb-4 pb-4 border-b" data-product-id="${item.product_id}">
                <img src="${item.image || 'https://via.placeholder.com/80'}" alt="${item.name}" class="w-20 h-20 object-cover rounded">
                <div class="flex-1">
                    <h4 class="font-semibold">${item.name}</h4>
                    <p class="text-gray-600">${formatCurrency(item.price)}</p>
                    <div class="flex items-center space-x-2 mt-2">
                        <button onclick="updateCartItem(${item.product_id}, ${item.quantity - 1})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100">-</button>
                        <span class="w-8 text-center">${item.quantity}</span>
                        <button onclick="updateCartItem(${item.product_id}, ${item.quantity + 1})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100">+</button>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold">${formatCurrency(itemTotal)}</p>
                    <button onclick="removeFromCart(${item.product_id})" class="text-red-500 hover:text-red-700 mt-2">
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
    // Create notification element
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

// Make functions globally available
window.addToCart = addToCart;
window.updateCartItem = updateCartItem;
window.removeFromCart = removeFromCart;

