document.addEventListener('DOMContentLoaded', function(){
    // Select all elements with the ID (handling duplicate IDs in HTML)
    const cartButtons = document.querySelectorAll('[id="addToCartByProductIcon"]');
    
    cartButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e){
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            if (productId) {
                // Check if global addToCart exists
                if (typeof addToCart === 'function') {
                    addToCart(productId, 1);
                } else {
                    console.error('addToCart function not found');
                }
            }
        });
    });
});