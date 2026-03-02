/**
 * Add to Cart Script
 * Unified handler for all cart additions using event delegation.
 */

// Mobile touch fix: on touch devices, clicking action buttons first triggers :hover (tooltip).
// We use touchend to fire the click immediately, preventing the tooltip showing first.
document.addEventListener('touchend', function(e) {
    const actionBtn = e.target.closest('.product-action-btn, .add-to-cart-hover-btn, .wishlist-btn, .quick-view-btn');
    if (!actionBtn) return;
    // Mark that we handled this touch - the 'click' event will still fire via the browser
    // but the tooltip won't intercept since pointer-events:none is set on it
    // We just need to ensure the click fires, nothing special needed here except
    // preventing the 300ms delay on some browsers
    // The CSS fix (pointer-events:none on tooltip + @media hover:none hiding tooltip) does the heavy lifting
}, { passive: true });

document.addEventListener('click', function(e) {
    // Check for any add-to-cart style button
    const btn = e.target.closest('.add-to-cart-hover-btn, .add-to-cart-btn, #addToCartByProductIcon');
    if (!btn || btn.disabled || btn.classList.contains('cursor-wait')) return;

    e.preventDefault();
    e.stopPropagation();

    const productId = btn.getAttribute('data-product-id');
    if (!productId) return;

    // 1. Get Quantity
    let quantity = 1;
    const qtyInput = document.getElementById('quantity') || document.getElementById('qvQuantity');
    if (qtyInput && (btn.classList.contains('add-to-cart-btn') || btn.id === 'addToCartByProductIcon')) {
        quantity = parseInt(qtyInput.value) || 1;
    }

    // 2. Get Attributes
    let attributes = {};
    try {
        const attrData = btn.getAttribute('data-attributes');
        if (attrData && attrData !== 'null' && attrData !== 'undefined') {
            const parsed = JSON.parse(attrData);
            // Ensure it's an object, not an empty array from PHP json_encode
            attributes = (Array.isArray(parsed) && parsed.length === 0) ? {} : parsed;
        }
    } catch (err) {
        console.error("Error parsing attributes:", err);
    }

    // 3. Call Global addToCart
    if (typeof addToCart === 'function') {
        addToCart(productId, quantity, btn, attributes);
    } else {
        console.error("addToCart function not found!");
        // Re-attempt if cart13.js loaded but maybe not yet initialized?
        if (window.addToCart) {
            window.addToCart(productId, quantity, btn, attributes);
        }
    }
});

