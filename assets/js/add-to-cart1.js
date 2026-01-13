document.addEventListener('DOMContentLoaded', function(){
        const cartQuickViewButtons = document.getElementById('addToCartByProductIcon');
        if (!cartQuickViewButtons) return;
        cartQuickViewButtons.addEventListener('click', function(btn){
            btn.preventDefault();
            const productId = this.getAttribute('data-product-id');
        addToCart(productId, 1);
        })
})