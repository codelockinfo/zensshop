// Cart Management System
let cartData = [];

function setupCartUI() {
    const cartBtn = document.getElementById('cartBtn');
    const closeCart = document.getElementById('closeCart');
    const sideCart = document.getElementById('sideCart');
    const cartOverlay = document.getElementById('cartOverlay');
    
    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            refreshCart();
            sideCart.classList.remove('translate-x-full');
            cartOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if (closeCart) {
        closeCart.addEventListener('click', closeCartPanel);
    }
    
    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCartPanel);
    }
    
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

function loadCart() {
    const cartCookie = getCookie('cart_items');
    if (cartCookie) {
        try {
            let parsed = null;
            try {
                parsed = JSON.parse(cartCookie);
            } catch (e) {
                try {
                    parsed = JSON.parse(decodeURIComponent(cartCookie));
                } catch (e2) {
                    console.error('Error parsing cart cookie:', e2);
                    parsed = null;
                }
            }
            cartData = (parsed && Array.isArray(parsed)) ? parsed : [];
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

async function refreshCart() {
    renderCartSkeleton();
    
    try {
        let baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        baseUrl = baseUrl.replace(/\/$/, '');
        console.log('[CART] Refreshing from:', baseUrl + '/api/cart.php');
        
        const response = await fetch(baseUrl + '/api/cart.php?t=' + new Date().getTime(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;
            
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
            loadCart();
        }
    } catch (error) {
        console.error('Error refreshing cart:', error);
        loadCart();
    }
}

function renderCartSkeleton() {
    const cartItems = document.getElementById('cartItems');
    if (!cartItems) return;
    
    let skeletonHTML = '';
    for (let i = 0; i < 3; i++) {
        skeletonHTML += `
            <div class="mb-4 pb-4 border-b animate-pulse">
                <div class="flex items-center space-x-4">
                    <div class="w-20 h-20 bg-gray-200 rounded"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                        <div class="h-3 bg-gray-100 rounded w-1/4"></div>
                    </div>
                    <div class="w-12 text-right space-y-2">
                        <div class="h-4 bg-gray-200 rounded ml-auto w-full"></div>
                        <div class="h-6 w-6 bg-gray-100 rounded ml-auto"></div>
                    </div>
                </div>
            </div>
        `;
    }
    cartItems.innerHTML = skeletonHTML;
}

async function addToCart(productId, quantity = 1, btn = null, variantAttributes = {}) {
    if (btn) setBtnLoading(btn, true);
    
    try {
        let baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        baseUrl = baseUrl.replace(/\/$/, '');
        console.log('[CART] Add URL:', baseUrl + '/api/cart.php');
        
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                variant_attributes: variantAttributes
            })
        });
        
        // Safely parse JSON - handle PHP error pages gracefully
        let data;
        const rawText = await response.text();
        try {
            data = JSON.parse(rawText);
        } catch (parseErr) {
            console.error('[CART] Invalid JSON response:', rawText.substring(0, 200));
            if (typeof showNotificationModal === 'function') {
                showNotificationModal('Server error. Please try again.', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification('Server error. Please try again.', 'error');
            }
            return { success: false, message: 'Server error' };
        }
        
        if (!(data.success && Array.isArray(data.cart))) {
            if (typeof showNotificationModal === 'function') {
                showNotificationModal(data.message || 'Failed to add product to cart', 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to add product to cart', 'error');
            }
            return { success: false, message: data.message };
        } else {
            cartData = data.cart;
            
            // Update cart count from server response
            if (data.count !== undefined) {
                window.lastCartCount = data.count;
            }
            
            if (data.cookie_data) {
                try {
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    const cookieStr = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieStr;
                } catch (e) {
                    console.error('[CART] Error setting cookie:', e);
                }
            } else if (data.cart && Array.isArray(data.cart)) {
                try {
                    const cookieData = JSON.stringify(data.cart);
                    const expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    const cookieStr = `cart_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                    document.cookie = cookieStr;
                } catch (e) {
                    console.error('[CART] Error setting cookie (fallback):', e);
                }
            }
            
            loadCart();
            
            const isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');
            if (isCartPage) {
                window.location.reload();
                return data;
            }
            
            updateCartUI();
            updateCartCount();
            
            // Show success message with quantity info
            const addedQty = quantity || 1;
            const successMsg = addedQty > 1
                ? `${addedQty} items added to cart!`
                : (data.message || 'Product added to cart!');

            if (typeof showNotificationModal === 'function') {
                showNotificationModal(successMsg, 'success');
            } else if (typeof showNotification === 'function') {
                showNotification(successMsg, 'success');
            }
            
            const sideCart = document.getElementById('sideCart');
            const cartOverlay = document.getElementById('cartOverlay');
            if (sideCart && cartOverlay) {
                sideCart.classList.remove('translate-x-full');
                cartOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            
            return data;
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('An error occurred. Please try again.', 'error');
        return { success: false, message: error.message };
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}


async function updateCartItem(productId, quantity, btn = null, variantAttributes = {}) {
    if (btn) setBtnLoading(btn, true);

    // Immediate UI update (Optimistic)
    let itemIndex = cartData.findIndex(item => {
        let attrs = item.variant_attributes || {};
        return item.product_id == productId && JSON.stringify(attrs) === JSON.stringify(variantAttributes || {});
    });

    if (itemIndex > -1) {
        cartData[itemIndex].quantity = quantity;
        updateCartUI();
        
        let row = document.querySelector(`.cart-item-wrapper[data-product-id="${productId}"]`);
        if (row) {
             let qtySpan = row.querySelector('.item-quantity');
             if(qtySpan) qtySpan.textContent = quantity;
             
             let totalSpan = row.querySelector('.item-total span');
             if(totalSpan) {
                 let price = parseFloat(cartData[itemIndex].price);
                 let currency = cartData[itemIndex].currency || 'USD';
                 totalSpan.textContent = formatCurrency(price * quantity, currency);
             }
        }
        
        let subtotal = cartData.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0);
        let tax_total = cartData.reduce((sum, item) => {
             if(item.is_taxable && item.gst_percent) {
                 return sum + (parseFloat(item.price) * parseInt(item.quantity) * parseFloat(item.gst_percent) / 100);
             }
             return sum;
        }, 0);
        let grand_total = subtotal + tax_total;
        
        let currency = cartData[0]?.currency || 'USD';
        
        let cartSubtotalEl = document.getElementById('cartSubtotal');
        if(cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(subtotal, currency);

        let cartTaxEl = document.getElementById('cartTax');
        if(cartTaxEl) cartTaxEl.textContent = formatCurrency(tax_total, currency);
        
        let cartTotalEl = document.getElementById('cartTotal');
        if(cartTotalEl) cartTotalEl.textContent = formatCurrency(grand_total, currency);
    }

    if (quantity < 1) quantity = 1;

    try {
        let baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        let response = await fetch(baseUrl + '/api/cart.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                variant_attributes: variantAttributes
            })
        });

        let data = await response.json();

        if (data.success && Array.isArray(data.cart)) {
            cartData = data.cart;
            
            if (data.cookie_data) {
                try {
                    let expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(data.cookie_data)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie:', e);
                }
            } else if (data.cart) {
                 try {
                    let cookieData = JSON.stringify(data.cart);
                    let expiry = new Date();
                    expiry.setTime(expiry.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = `cart_items=${encodeURIComponent(cookieData)}; expires=${expiry.toUTCString()}; path=/; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
                } catch (e) {
                    console.error('[CART] Error updating cookie (fallback):', e);
                }
            }
            
            updateCartUI();
            
            let isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');
            if (isCartPage) {
                let row = document.querySelector(`.cart-item-wrapper[data-product-id="${productId}"]`);
                if(row) {
                    let item = cartData.find(i => i.product_id == productId);
                    if(item) {
                         let totalSpan = row.querySelector('.item-total span');
                         if(totalSpan) {
                             totalSpan.textContent = formatCurrency(item.price * item.quantity, item.currency);
                         }
                    }
                }

                if (data.total !== undefined) {
                    let cartSubtotal = document.getElementById('cartSubtotal');
                    let cartTotal = document.getElementById('cartTotal');
                    let cartTax = document.getElementById('cartTax');
                    let currency = (cartData.length > 0) ? cartData[0].currency : 'USD';

                    if (cartSubtotal) cartSubtotal.textContent = formatCurrency(data.total, currency);
                    if (cartTotal) cartTotal.textContent = formatCurrency(data.grand_total || data.total, currency);
                    if (cartTax && data.tax_total !== undefined) cartTax.textContent = formatCurrency(data.tax_total, currency);
                }
            }

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
                console.log(data.message || 'Failed to update cart');
            }
        }
    } catch (error) {
        console.error('Error updating cart:', error);
         if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        } else {
            console.log('An error occurred. Please try again.');
        }
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}

async function removeFromCart(productId, btn = null, variantAttributes = {}) {
    if (btn) setBtnLoading(btn, true);
    
    const isCartPage = window.location.pathname.includes('/cart') || document.querySelector('.cart-item');
    
    try {
        let baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
        
        const response = await fetch(baseUrl + '/api/cart.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                variant_attributes: variantAttributes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cartData = Array.isArray(data.cart) ? data.cart : [];
            
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
            
            loadCart();
            
            if (isCartPage) {
                const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                if (cartItem) {
                    cartItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    cartItem.style.opacity = '0';
                    cartItem.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        cartItem.remove();
                        
                        if (data.total !== undefined) {
                            const cartSubtotal = document.getElementById('cartSubtotal');
                            const cartTotal = document.getElementById('cartTotal');
                            const cartTax = document.getElementById('cartTax');
                            
                            if (cartSubtotal) cartSubtotal.textContent = formatCurrency(data.total);
                            if (cartTotal) cartTotal.textContent = formatCurrency(data.grand_total || data.total);
                            if (cartTax && data.tax_total !== undefined) cartTax.textContent = formatCurrency(data.tax_total);
                        }
                        
                        const remainingItems = document.querySelectorAll('.cart-item');
                        if (remainingItems.length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                } else {
                    window.location.reload();
                    return;
                }
            } else {
                updateCartUI();
            }
            
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
                console.log(data.message || 'Failed to remove product');
            }
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred. Please try again.', 'error');
        } else {
            console.log('An error occurred. Please try again.');
        }
    } finally {
        if (btn) setBtnLoading(btn, false);
    }
}

function updateCartUI() {
    const cartItems = document.getElementById('cartItems');
    const sideCartFooter = document.getElementById('sideCartFooter');
    const baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
    
    if (!cartItems) return;
    
    if (cartData.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                <p>Your cart is empty</p>
            </div>
        `;
        
        if (sideCartFooter) {
            sideCartFooter.innerHTML = `
                <a href="${baseUrl}/shop" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-light hover:text-white transition w-full text-center hover:font-bold">
                    Continue Shopping
                </a>
            `;
        }
        return;
    }
    
    let html = '';
    let total = 0;
    
    cartData.forEach(item => {
        if (!item || !item.product_id || !item.name) {
            console.warn('Invalid cart item:', item);
            return;
        }
        
        const price = parseFloat(item.price) || 0;
        const quantity = parseInt(item.quantity) || 1;
        const itemTotal = price * quantity;
        total += itemTotal;
        
        // Image URL is already properly formatted by Cart.php using getImageUrl()
        let imgUrl = item.image || "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+PGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMjAiIGZpbGw9IiM5QjdBOEEiLz48L3N2Zz4=";
        
        const variantAttrs = item.variant_attributes || {};
        const variantDisplay = Object.entries(variantAttrs)
            .map(([key, value]) => `<span class="text-xs text-gray-500 block">${key}: ${value}</span>`)
            .join('');
        const variantJSON = JSON.stringify(variantAttrs).replace(/"/g, '&quot;');
        
        html += `
            <div class="side-cart-item-wrapper mb-4 pb-4 border-b" data-product-id="${item.product_id}" data-attributes='${variantJSON}'>
                <div class="flex items-center space-x-4 side-cart-item" data-product-id="${item.product_id}">
                    <a href="product?slug=${item.slug}" class="shrink-0">
                        <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(item.name)}" class="w-20 h-20 object-cover rounded" onerror="this.src='https://placehold.co/150x150?text=Product+Image'">
                    </a>
                    <div class="flex-1">
                        <h4 class="font-semibold text-sm mb-1">
                            <a href="product?slug=${item.slug}" class="hover:text-[#1a3d32] transition-colors uppercase line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${escapeHtml(item.name)}</a>
                        </h4>
                        ${variantDisplay}
                        <p class="text-gray-600 text-sm mt-1">${formatCurrency(price, item.currency)}</p>
                        <div class="flex items-center space-x-2 mt-2 border rounded justify-between  w-28">
                            <button onclick="updateCartItem(${item.product_id}, ${quantity - 1}, this, ${variantJSON})" class="w-10 h-8 flex items-center justify-center hover:bg-gray-100 text-sm">-</button>
                            <span class=" text-center text-sm font-semibold">${quantity}</span>
                            <button onclick="updateCartItem(${item.product_id}, ${quantity + 1}, this, ${variantJSON})" class="w-10 h-8 flex items-center justify-center hover:bg-gray-100 text-sm">+</button>
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
                    <a href="product?slug=${item.slug}" class="shrink-0">
                        <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(item.name)}" class="w-16 h-16 object-cover rounded border border-gray-200" onerror="this.src='https://placehold.co/150x150?text=Product+Image'">
                    </a>
                    <div class="flex-1">
                        <h4 class="font-semibold text-sm mb-1 text-gray-800">
                            <a href="product?slug=${item.slug}" class="hover:text-[#1a3d32] transition-colors uppercase line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${escapeHtml(item.name)}</a>
                        </h4>
                        <p class="text-gray-600 text-xs mb-2">Add to wishlist before remove?</p>
                        <div class="flex space-x-2">
                            <button onclick="confirmSideCartInlineRemoveWithWishlist(this, ${item.product_id}, ${variantJSON})" class="px-4 py-1.5 bg-black text-white text-xs font-medium rounded hover:bg-gray-800 transition">Yes</button>
                            <button onclick="confirmSideCartInlineRemoveWithoutWishlist(this, ${item.product_id}, ${variantJSON})" class="px-4 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded hover:bg-gray-50 transition">No</button>
                        </div>
                    </div>
                    <button onclick="cancelSideCartInlineRemoveConfirm(this)" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;
    
    const currency = (cartData.length > 0 && cartData[0].currency) || 'USD';
    
    if (sideCartFooter) {

        const cartItemsJson = JSON.stringify(cartData.map(item => ({
            id: item.product_id,
            name: item.name,
            price: item.price,
            quantity: item.quantity,
            variant: item.variant_attributes
        }))).replace(/"/g, '&quot;');

        sideCartFooter.innerHTML = `
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg font-semibold">Total:</span>
                <span class="text-xl font-bold" id="cartTotal">${formatCurrency(total, currency)}</span>
            </div>
            <a href="${baseUrl}/cart" 
               id="viewCartBtn"
               data-cart-total="${total}"
               data-cart-currency="${currency}"
               data-cart-items="${cartItemsJson}"
               class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-light hover:text-white transition mb-2">
                View Cart
            </a>
            <a href="${baseUrl}/checkout" 
               id="checkoutBtn"
               data-cart-total="${total}"
               data-cart-currency="${currency}"
               data-cart-items="${cartItemsJson}"
               class="block w-full bg-black text-white text-center py-3 rounded-lg hover:text-white hover:bg-gray-800 transition">
                Checkout
            </a>
         `;
    } else {
        const cartTotalEl = document.getElementById('cartTotal');
        if (cartTotalEl) {
            cartTotalEl.textContent = formatCurrency(total, currency);
        }
    }
}

function updateCartCount() {
    let count = 0;
    if (window.lastCartCount !== undefined) {
        count = window.lastCartCount;
    } else {
        count = cartData.reduce((sum, item) => sum + parseInt(item.quantity || 0), 0);
    }
    
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(el => {
        el.textContent = count;
    });
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function showNotification(message, type = 'info') {
    if (typeof showNotificationModal === 'function') {
        showNotificationModal(message, type);
        return;
    }
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'} text-white`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount, currency) {
    if (currency) {
        const currencySymbols = {
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
        const symbol = currencySymbols[currency.toUpperCase()] || '₹';
        return symbol + parseFloat(amount).toFixed(2);
    }
    
    const symbol = (typeof CURRENCY_SYMBOL !== 'undefined') ? CURRENCY_SYMBOL : '₹';
    return symbol + parseFloat(amount).toFixed(2);
}

function showSideCartInlineRemoveConfirm(btn) {
    const wrapper = btn.closest('.side-cart-item-wrapper');
    if (wrapper) {
        const itemDiv = wrapper.querySelector('.side-cart-item');
        const confirmDiv = wrapper.querySelector('.side-cart-remove-confirm-inline');
        if (itemDiv && confirmDiv) {
            itemDiv.classList.add('hidden');
            confirmDiv.classList.remove('hidden');
        }
    }
}

function cancelSideCartInlineRemoveConfirm(btn) {
    const wrapper = btn.closest('.side-cart-item-wrapper');
    if (wrapper) {
        const itemDiv = wrapper.querySelector('.side-cart-item');
        const confirmDiv = wrapper.querySelector('.side-cart-remove-confirm-inline');
        if (itemDiv && confirmDiv) {
            itemDiv.classList.remove('hidden');
            confirmDiv.classList.add('hidden');
        }
    }
}

async function confirmSideCartInlineRemoveWithWishlist(btn, productId, variantAttributes = {}) {
    if (btn) setBtnLoading(btn, true);
    
    let baseUrl = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.location.pathname.split('/').slice(0, -1).join('/') || '');
    
    try {
        const response = await fetch(baseUrl + '/api/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success && typeof refreshWishlist === 'function') {
            await refreshWishlist();
        }
        
        await removeFromCart(productId, null, variantAttributes);
    } catch (error) {
        console.error('Error adding to wishlist:', error);
        await removeFromCart(productId, null, variantAttributes);
    } finally {
        if (btn && document.body.contains(btn)) {
            setBtnLoading(btn, false);
        }
    }
}

async function confirmSideCartInlineRemoveWithoutWishlist(btn, productId, variantAttributes = {}) {
    await removeFromCart(productId, btn, variantAttributes);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    setupCartUI();
});

// Export functions to window
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
