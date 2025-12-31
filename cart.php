<?php
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/classes/Cart.php';

$cart = new Cart();
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();
?>

<section class="py-16 md:py-24 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-heading font-bold mb-8">Shopping Cart</h1>
        
        <?php if (empty($cartItems)): ?>
        <div class="bg-white rounded-lg p-12 text-center">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-bold mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Start adding some products to your cart!</p>
            <a href="<?php echo $baseUrl; ?>/" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-dark transition">
                Continue Shopping
            </a>
        </div>
        <?php else: ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4" id="cartItemsContainer">
                <?php foreach ($cartItems as $item): 
                    $productSlug = $item['slug'] ?? '';
                    $productUrl = !empty($productSlug) ? url('product?slug=' . urlencode($productSlug)) : '#';
                ?>
                <div class="bg-white rounded-lg p-6 flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6 cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                    <a href="<?php echo $productUrl; ?>">
                        <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/150'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="w-32 h-32 object-cover rounded">
                    </a>
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold mb-2">
                            <a href="<?php echo $productUrl; ?>" class="hover:text-primary transition">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </a>
                        </h3>
                        <p class="text-gray-600">Price: $<span class="item-price"><?php echo number_format($item['price'], 2); ?></span></p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center border rounded">
                            <button onclick="decrementCartItem(<?php echo $item['product_id']; ?>)" 
                                    class="px-4 py-2 hover:bg-gray-100">-</button>
                            <span class="px-4 py-2 item-quantity" data-product-id="<?php echo $item['product_id']; ?>"><?php echo $item['quantity']; ?></span>
                            <button onclick="incrementCartItem(<?php echo $item['product_id']; ?>)" 
                                    class="px-4 py-2 hover:bg-gray-100">+</button>
                        </div>
                        <p class="text-xl font-bold w-24 text-right item-total">
                            $<span><?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </p>
                        <button onclick="removeFromCart(<?php echo $item['product_id']; ?>)" 
                                class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash text-xl"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Cart Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg p-6 sticky top-20">
                    <h2 class="text-2xl font-bold mb-6">Order Summary</h2>
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span id="cartSubtotal">$<?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span>$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax</span>
                            <span>$0.00</span>
                        </div>
                        <div class="border-t pt-4 flex justify-between text-xl font-bold">
                            <span>Total</span>
                            <span id="cartTotal">$<?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo url('checkout'); ?>" 
                       class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-dark transition mb-4">
                        Proceed to Checkout
                    </a>
                    <a href="<?php echo $baseUrl; ?>/" 
                       class="block w-full text-center py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</section>

<script>
// Helper functions to increment/decrement cart items
function incrementCartItem(productId) {
    const quantitySpan = document.querySelector(`.item-quantity[data-product-id="${productId}"]`);
    if (quantitySpan) {
        const currentQuantity = parseInt(quantitySpan.textContent) || 1;
        if (typeof updateCartItem === 'function') {
            updateCartItem(productId, currentQuantity + 1);
        }
    }
}

function decrementCartItem(productId) {
    const quantitySpan = document.querySelector(`.item-quantity[data-product-id="${productId}"]`);
    if (quantitySpan) {
        const currentQuantity = parseInt(quantitySpan.textContent) || 1;
        const newQuantity = Math.max(1, currentQuantity - 1);
        if (typeof updateCartItem === 'function') {
            updateCartItem(productId, newQuantity);
        }
    }
}

// Make functions globally available
window.incrementCartItem = incrementCartItem;
window.decrementCartItem = decrementCartItem;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


