/**
 * Add to Cart Script
 * Unified handler for all cart additions using event delegation.
 */
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
