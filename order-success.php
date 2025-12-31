<?php
/**
 * Order Success Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Order.php';

$baseUrl = getBaseUrl();
$order = new Order();

// Get order ID from URL
$orderId = $_GET['id'] ?? null;

if (!$orderId) {
    header('Location: ' . url('/'));
    exit;
}

// Get order details
$orderData = $order->getById($orderId);

if (!$orderData) {
    header('Location: ' . url('/'));
    exit;
}

$pageTitle = 'Order Confirmation';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 md:py-24 bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Success Message -->
            <div class="bg-white rounded-lg p-8 text-center mb-8">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-4xl text-green-600"></i>
                </div>
                <h1 class="text-3xl font-bold mb-2">Order Placed Successfully!</h1>
                <p class="text-gray-600 mb-4">Thank you for your purchase. Your order has been received and is being processed.</p>
                <p class="text-lg font-semibold">Order Number: <span class="text-primary"><?php echo htmlspecialchars($orderData['order_number']); ?></span></p>
            </div>
            
            <!-- Order Details -->
            <div class="bg-white rounded-lg p-8 mb-8">
                <h2 class="text-2xl font-bold mb-6">Order Details</h2>
                
                <!-- Customer Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="font-semibold mb-2">Customer Information</h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($orderData['customer_name']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($orderData['customer_email']); ?></p>
                        <?php if (!empty($orderData['customer_phone'])): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($orderData['customer_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="font-semibold mb-2">Order Status</h3>
                        <p class="text-gray-600">Status: <span class="font-semibold capitalize"><?php echo htmlspecialchars($orderData['order_status']); ?></span></p>
                        <p class="text-gray-600">Payment: <span class="font-semibold capitalize"><?php echo htmlspecialchars($orderData['payment_status']); ?></span></p>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="mb-8">
                    <h3 class="font-semibold mb-4">Order Items</h3>
                    <div class="space-y-4">
                        <?php foreach ($orderData['items'] as $item): ?>
                        <div class="flex items-center space-x-4 border-b pb-4">
                            <div class="flex-1">
                                <h4 class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p class="text-gray-600 text-sm">Quantity: <?php echo $item['quantity']; ?></p>
                            </div>
                            <p class="font-bold">$<?php echo number_format($item['subtotal'], 2); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Order Totals -->
                <div class="border-t pt-4">
                    <div class="flex justify-between mb-2">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($orderData['subtotal'], 2); ?></span>
                    </div>
                    <?php if ($orderData['discount_amount'] > 0): ?>
                    <div class="flex justify-between mb-2">
                        <span>Discount</span>
                        <span>-$<?php echo number_format($orderData['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($orderData['shipping_amount'] > 0): ?>
                    <div class="flex justify-between mb-2">
                        <span>Shipping</span>
                        <span>$<?php echo number_format($orderData['shipping_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($orderData['tax_amount'] > 0): ?>
                    <div class="flex justify-between mb-2">
                        <span>Tax</span>
                        <span>$<?php echo number_format($orderData['tax_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold pt-4 border-t">
                        <span>Total</span>
                        <span>$<?php echo number_format($orderData['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="<?php echo url('/'); ?>" class="flex-1 bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-dark transition">
                    Continue Shopping
                </a>
                <a href="<?php echo url('shop'); ?>" class="flex-1 bg-white border border-gray-300 text-center py-3 rounded-lg hover:bg-gray-50 transition">
                    Browse Products
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

