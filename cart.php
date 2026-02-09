<?php
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/classes/Cart.php';

$cart = new Cart();
$cartItems = $cart->getCart();
$cartTotal = $cart->getTotal();

// Calculate estimated tax (Intrastate default)
$taxTotal = 0;
foreach ($cartItems as $item) {
    if (!empty($item['is_taxable']) && !empty($item['gst_percent'])) {
        $itemTotal = $item['price'] * $item['quantity'];
        $taxAmount = ($itemTotal * $item['gst_percent']) / 100;
        $taxTotal += $taxAmount;
    }
}
?>

<section class="py-16 md:py-24 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-heading font-bold mb-8">Shopping Cart</h1>
        <?php 
            // Determine currency for totals (use first item's currency or default)
            $cartCurrency = !empty($cartItems) ? ($cartItems[0]['currency'] ?? 'USD') : 'USD';
        ?>
        
        <?php if (empty($cartItems)): ?>
        <div class="bg-white rounded-lg p-12 text-center">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-bold mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Start adding some products to your cart!</p>
            <a href="<?php echo $baseUrl; ?>/shop" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-light hover:text-white transition">
                Continue Shopping
            </a>
        </div>
        <?php else: ?>
        
        <!-- Cart Skeleton -->
        <div id="cartSkeleton" class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-16">
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-lg p-6 flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6 animate-pulse">
                    <div class="w-32 h-32 bg-gray-200 rounded"></div>
                    <div class="flex-1 space-y-3">
                         <div class="h-6 bg-gray-200 rounded w-1/2"></div>
                         <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                         <div class="h-4 bg-gray-200 rounded w-1/6"></div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-32 h-10 bg-gray-200 rounded"></div>
                        <div class="w-24 h-6 bg-gray-200 rounded"></div>
                        <div class="w-6 h-6 bg-gray-200 rounded"></div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg p-6 animate-pulse">
                    <div class="h-8 bg-gray-200 rounded w-1/2 mb-6"></div>
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between"><div class="h-4 bg-gray-200 rounded w-1/4"></div><div class="h-4 bg-gray-200 rounded w-1/4"></div></div>
                        <div class="flex justify-between"><div class="h-4 bg-gray-200 rounded w-1/4"></div><div class="h-4 bg-gray-200 rounded w-1/4"></div></div>
                        <div class="border-t pt-4 flex justify-between"><div class="h-6 bg-gray-200 rounded w-1/4"></div><div class="h-6 bg-gray-200 rounded w-1/4"></div></div>
                    </div>
                    <div class="h-12 bg-gray-200 rounded w-full mb-4"></div>
                </div>
            </div>
        </div>

        <div id="mainCartContent" class="grid grid-cols-1 lg:grid-cols-3 gap-8 hidden">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4" id="cartItemsContainer">
                <?php foreach ($cartItems as $item): 
                    $productSlug = $item['slug'] ?? '';
                    $productUrl = !empty($productSlug) ? url('product?slug=' . urlencode($productSlug)) : '#';
                ?>
                <?php 
                    $variantAttributes = $item['variant_attributes'] ?? [];
                    $attributesJson = json_encode($variantAttributes);
                    $attributesEscaped = htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8');
                ?>
                <div class="cart-item-wrapper" data-product-id="<?php echo $item['product_id']; ?>" data-attributes='<?php echo $attributesEscaped; ?>'>
                    <div class="bg-white rounded-lg p-6 flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6 cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                        <a href="<?php echo $productUrl; ?>">
                            <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://placehold.co/600x600?text=Product+Image'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="w-32 h-32 object-cover rounded"
                                 onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                        </a>
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold mb-1 line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['name']); ?>">
                                <a href="<?php echo $productUrl; ?>" class="hover:text-primary transition">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h3>
                            <?php if (!empty($variantAttributes)): ?>
                                <div class="mb-2 space-y-0.5">
                                    <?php foreach ($variantAttributes as $key => $v): ?>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($key); ?>: <span class="font-medium"><?php echo htmlspecialchars($v); ?></span></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p class="text-gray-600">Price: <span class="item-price"><?php echo format_price($item['price'], $item['currency'] ?? 'USD'); ?></span></p>
                            <?php if (!empty($item['is_taxable'])): ?>
                                <!-- <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">GST: <?php echo number_format($item['gst_percent'], 2); ?>%</span>
                                    <?php if (!empty($item['hsn_code'])): ?>
                                        <span class="text-[10px] text-gray-400">HSN: <?php echo htmlspecialchars($item['hsn_code']); ?></span>
                                    <?php endif; ?>
                                </div> -->
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center border rounded">
                                <button onclick='decrementCartItem(<?php echo $item['product_id']; ?>, this, <?php echo $attributesEscaped; ?>)' 
                                        class="px-4 py-2 hover:bg-gray-100" data-loading-text="">-</button>
                                <span class="w-12 text-center py-2 item-quantity font-semibold" data-product-id="<?php echo $item['product_id']; ?>" data-attributes='<?php echo $attributesEscaped; ?>'><?php echo $item['quantity']; ?></span>
                                <button onclick='incrementCartItem(<?php echo $item['product_id']; ?>, this, <?php echo $attributesEscaped; ?>)' 
                                        class="px-4 py-2 hover:bg-gray-100" data-loading-text="">+</button>
                            </div>
                            <p class="text-xl font-bold w-24 text-right item-total">
                                <span><?php echo format_price($item['price'] * $item['quantity'], $item['currency'] ?? 'USD'); ?></span>
                            </p>
                            <button onclick='showInlineRemoveConfirm(<?php echo $item['product_id']; ?>, <?php echo $attributesEscaped; ?>)' 
                                    class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Inline Remove Confirmation -->
                    <div class="remove-confirm-inline bg-white rounded-lg p-6 flex items-center space-x-4 shadow-md border border-gray-300 hidden" data-product-id="<?php echo $item['product_id']; ?>" data-attributes='<?php echo $attributesEscaped; ?>'>
                        <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://placehold.co/600x600?text=Product+Image'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="w-20 h-20 object-cover rounded border border-gray-200"
                             onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                        <div class="flex-1">
                            <h3 class="text-base font-semibold mb-1 text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-gray-600 text-sm mb-3">Add to wishlist before remove?</p>
                            <div class="flex space-x-3">
                                <button onclick='confirmInlineRemoveWithWishlist(<?php echo $item['product_id']; ?>, this, <?php echo $attributesEscaped; ?>)' 
                                        class="px-6 py-2 bg-black text-white text-sm font-medium rounded hover:bg-gray-800 transition"
                                        data-loading-text="Removing...">
                                    Yes
                                </button>
                                <button onclick='confirmInlineRemoveWithoutWishlist(<?php echo $item['product_id']; ?>, this, <?php echo $attributesEscaped; ?>)' 
                                        class="px-6 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50 transition"
                                        data-loading-text="Removing...">
                                    No
                                </button>
                            </div>
                        </div>
                        <button onclick='cancelInlineRemoveConfirm(<?php echo $item['product_id']; ?>, <?php echo $attributesEscaped; ?>)' 
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
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
                            <span id="cartSubtotal"><?php echo format_price($cartTotal, $cartCurrency); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span><?php echo format_price(0, $cartCurrency); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax (Estimated)</span>
                            <span class="text-sm font-semibold" id="cartTax"><?php echo format_price($taxTotal, $cartCurrency); ?></span>
                        </div>
                        <div class="border-t pt-4 flex justify-between text-xl font-bold">
                            <span>Total</span>
                            <span id="cartTotal"><?php echo format_price($cartTotal + $taxTotal, $cartCurrency); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo url('checkout'); ?>" 
                       class="block w-full bg-black text-white text-center py-3 rounded-lg hover:text-white hover:bg-gray-800 transition mb-4 font-bold">
                        Proceed to Checkout
                    </a>
                    <a href="<?php echo $baseUrl; ?>/shop" 
                       class="block w-full text-center py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition bg-primary text-white hover:font-bold">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const skeleton = document.getElementById('cartSkeleton');
    const content = document.getElementById('mainCartContent');
    if (skeleton && content) {
        skeleton.classList.add('hidden');
        content.classList.remove('hidden');
    }
});
</script>

<script>
// Helper functions to increment/decrement cart items
function incrementCartItem(productId, btn, attributes = {}) {
    const attributesJson = JSON.stringify(attributes);
    const quantitySpan = document.querySelector(`.item-quantity[data-product-id="${productId}"][data-attributes='${attributesJson}']`);
    if (quantitySpan) {
        const currentQuantity = parseInt(quantitySpan.textContent) || 1;
        if (typeof updateCartItem === 'function') {
            updateCartItem(productId, currentQuantity + 1, btn, attributes);
        }
    }
}

function decrementCartItem(productId, btn, attributes = {}) {
    const attributesJson = JSON.stringify(attributes);
    const quantitySpan = document.querySelector(`.item-quantity[data-product-id="${productId}"][data-attributes='${attributesJson}']`);
    if (quantitySpan) {
        const currentQuantity = parseInt(quantitySpan.textContent) || 1;
        const newQuantity = Math.max(1, currentQuantity - 1);
        if (typeof updateCartItem === 'function') {
            updateCartItem(productId, newQuantity, btn, attributes);
        }
    }
}

// Inline Remove Confirm Functions
function showInlineRemoveConfirm(productId, attributes = {}) {
    const attributesJson = JSON.stringify(attributes);
    const wrapper = document.querySelector(`.cart-item-wrapper[data-product-id="${productId}"][data-attributes='${attributesJson}']`);
    if (wrapper) {
        const cartItem = wrapper.querySelector('.cart-item');
        const confirmBox = wrapper.querySelector('.remove-confirm-inline');
        if (cartItem && confirmBox) {
            cartItem.classList.add('hidden');
            confirmBox.classList.remove('hidden');
        }
    }
}

function cancelInlineRemoveConfirm(productId, attributes = {}) {
    const attributesJson = JSON.stringify(attributes);
    const wrapper = document.querySelector(`.cart-item-wrapper[data-product-id="${productId}"][data-attributes='${attributesJson}']`);
    if (wrapper) {
        const cartItem = wrapper.querySelector('.cart-item');
        const confirmBox = wrapper.querySelector('.remove-confirm-inline');
        if (cartItem && confirmBox) {
            cartItem.classList.remove('hidden');
            confirmBox.classList.add('hidden');
        }
    }
}

async function confirmInlineRemoveWithWishlist(productId, btn, attributes = {}) {
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
        if (typeof removeFromCart === 'function') {
            await removeFromCart(productId, btn, attributes);
        }
    } catch (error) {
        console.error('Error adding to wishlist:', error);
        if (typeof removeFromCart === 'function') {
            await removeFromCart(productId, btn, attributes);
        }
    }
}

async function confirmInlineRemoveWithoutWishlist(productId, btn, attributes = {}) {
    if (typeof removeFromCart === 'function') {
        await removeFromCart(productId, btn, attributes);
    }
}

// Make functions globally available
window.incrementCartItem = incrementCartItem;
window.decrementCartItem = decrementCartItem;
window.showInlineRemoveConfirm = showInlineRemoveConfirm;
window.cancelInlineRemoveConfirm = cancelInlineRemoveConfirm;
window.confirmInlineRemoveWithWishlist = confirmInlineRemoveWithWishlist;
window.confirmInlineRemoveWithoutWishlist = confirmInlineRemoveWithoutWishlist;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


