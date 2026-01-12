<?php
// Start output buffering to prevent headers already sent errors
if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

// Get order ID from URL
$orderId = $_GET['id'] ?? null;

// Convert to integer if it's a string number
if ($orderId && is_numeric($orderId)) {
    $orderId = (int)$orderId;
}

if (!$orderId) {
    // Clear any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $baseUrl . '/admin/orders/list');
    exit;
}

$order = new Order();
$orderData = $order->getById($orderId);

if (!$orderData) {
    // Clear any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $baseUrl . '/admin/orders/list');
    exit;
}

// Decode addresses
$billingAddress = !empty($orderData['billing_address']) ? json_decode($orderData['billing_address'], true) : null;
$shippingAddress = !empty($orderData['shipping_address']) ? json_decode($orderData['shipping_address'], true) : null;

$pageTitle = 'Order Details - ' . $orderData['order_number'];
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">Order Details</h1>
            <p class="text-gray-600">Dashboard > Order > Order Details</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="<?php echo $baseUrl; ?>/admin/orders/edit?id=<?php echo $orderId; ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                <i class="fas fa-edit mr-2"></i>Edit Order
            </a>
            <a href="<?php echo $baseUrl; ?>/admin/orders/list" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Orders
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Order Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Order Header -->
        <div class="admin-card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold">Order #<?php echo htmlspecialchars($orderData['order_number']); ?></h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Placed on <?php echo date('F j, Y g:i A', strtotime($orderData['created_at'])); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded text-sm font-medium <?php 
                        echo $orderData['order_status'] === 'delivered' ? 'bg-green-100 text-green-800' : 
                            ($orderData['order_status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 
                            ($orderData['order_status'] === 'shipped' ? 'bg-blue-100 text-blue-800' : 
                            'bg-yellow-100 text-yellow-800')); 
                    ?>">
                        <?php echo ucfirst($orderData['order_status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                <div>
                    <p class="text-sm text-gray-500">Payment Status</p>
                    <p class="font-medium <?php echo $orderData['payment_status'] === 'paid' ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <?php echo ucfirst($orderData['payment_status']); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Payment Method</p>
                    <p class="font-medium"><?php echo htmlspecialchars($orderData['payment_method'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="admin-card">
            <h2 class="text-xl font-semibold mb-4">Order Items</h2>
            
            <?php if (!empty($orderData['items']) && is_array($orderData['items'])): ?>
            <div class="space-y-4">
                <?php foreach ($orderData['items'] as $item): ?>
                <div class="flex items-start space-x-4 pb-4 border-b last:border-b-0">
                    <?php 
                    $imageUrl = !empty($item['product_image']) 
                        ? getImageUrl($item['product_image'])
                        : 'https://via.placeholder.com/100';
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                         class="w-20 h-20 object-cover rounded"
                         onerror="this.src='https://via.placeholder.com/100'">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </h3>
                        <?php if (!empty($item['product_sku'])): ?>
                        <p class="text-sm text-gray-500 mt-1">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-600 mt-2">
                            Quantity: <span class="font-medium"><?php echo $item['quantity']; ?></span>
                        </p>
                        <p class="text-sm text-gray-600">
                            Price: <span class="font-medium"><?php echo format_currency($item['price']); ?></span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900">
                            <?php echo format_currency($item['subtotal']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500">No items found in this order.</p>
            <?php endif; ?>
        </div>

        <!-- Shipping Address -->
        <?php if ($shippingAddress): ?>
        <div class="admin-card">
            <h2 class="text-xl font-semibold mb-4">Shipping Address</h2>
            <div class="text-gray-700 space-y-1">
                <p class="font-medium"><?php echo htmlspecialchars($orderData['customer_name']); ?></p>
                
                <?php if (!empty($shippingAddress['street'])): ?>
                <p><?php echo htmlspecialchars($shippingAddress['street']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($shippingAddress['address_line1'])): ?>
                <p><?php echo htmlspecialchars($shippingAddress['address_line1']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($shippingAddress['address_line2'])): ?>
                <p><?php echo htmlspecialchars($shippingAddress['address_line2']); ?></p>
                <?php endif; ?>
                
                <p>
                    <?php 
                    $addressParts = array_filter([
                        $shippingAddress['city'] ?? '',
                        $shippingAddress['state'] ?? '',
                        $shippingAddress['zip'] ?? $shippingAddress['postal_code'] ?? ''
                    ]);
                    echo htmlspecialchars(implode(', ', $addressParts));
                    ?>
                </p>
                
                <?php if (!empty($shippingAddress['country'])): ?>
                <p><?php echo htmlspecialchars($shippingAddress['country']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($orderData['customer_phone'])): ?>
                <p class="mt-2 text-gray-600">Phone: <?php echo htmlspecialchars($orderData['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Billing Address -->
        <?php if ($billingAddress): ?>
        <div class="admin-card">
            <h2 class="text-xl font-semibold mb-4">Billing Address</h2>
            <div class="text-gray-700 space-y-1">
                <p class="font-medium"><?php echo htmlspecialchars($orderData['customer_name']); ?></p>
                
                <?php if (!empty($billingAddress['street'])): ?>
                <p><?php echo htmlspecialchars($billingAddress['street']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($billingAddress['address_line1'])): ?>
                <p><?php echo htmlspecialchars($billingAddress['address_line1']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($billingAddress['address_line2'])): ?>
                <p><?php echo htmlspecialchars($billingAddress['address_line2']); ?></p>
                <?php endif; ?>
                
                <p>
                    <?php 
                    $addressParts = array_filter([
                        $billingAddress['city'] ?? '',
                        $billingAddress['state'] ?? '',
                        $billingAddress['zip'] ?? $billingAddress['postal_code'] ?? ''
                    ]);
                    echo htmlspecialchars(implode(', ', $addressParts));
                    ?>
                </p>
                
                <?php if (!empty($billingAddress['country'])): ?>
                <p><?php echo htmlspecialchars($billingAddress['country']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column - Order Summary -->
    <div class="lg:col-span-1">
        <div class="admin-card sticky top-8">
            <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
            
            <!-- Price Breakdown -->
            <div class="space-y-3 mb-4">
                <!-- Subtotal -->
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['subtotal']); ?></span>
                </div>
                
                <!-- Discount -->
                <?php if ($orderData['discount_amount'] > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Discount</span>
                    <span class="text-green-600 font-medium">-<?php echo format_currency($orderData['discount_amount']); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Shipping -->
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Shipping</span>
                    <span class="text-gray-900 font-medium">
                        <?php 
                        if ($orderData['shipping_amount'] > 0) {
                            echo format_currency($orderData['shipping_amount']);
                        } else {
                            echo '<span class="text-green-600">Free</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- Tax -->
                <?php if ($orderData['tax_amount'] > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Taxes</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['tax_amount']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Total -->
            <div class="flex justify-between text-lg font-bold pt-4 border-t border-gray-200 mb-4">
                <span class="text-gray-900">Total</span>
                <span class="text-gray-900"><?php echo format_currency($orderData['total_amount']); ?></span>
            </div>

            <!-- Payment Info -->
            <div class="pt-4 border-t border-gray-200 space-y-2">
                <div class="text-sm">
                    <span class="text-gray-600">Payment Method:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($orderData['payment_method'] ?? 'N/A'); ?></span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Payment Status:</span>
                    <span class="font-medium ml-2 <?php echo $orderData['payment_status'] === 'paid' ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <?php echo ucfirst($orderData['payment_status']); ?>
                    </span>
                </div>
                <?php if (!empty($orderData['razorpay_payment_id'])): ?>
                <div class="text-sm">
                    <span class="text-gray-600">Payment ID:</span>
                    <span class="font-medium ml-2 text-xs"><?php echo htmlspecialchars($orderData['razorpay_payment_id']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($orderData['tracking_number'])): ?>
                <div class="text-sm">
                    <span class="text-gray-600">Tracking:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($orderData['tracking_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Customer Info -->
            <div class="pt-4 border-t border-gray-200 mt-4 space-y-2">
                <div class="text-sm">
                    <span class="text-gray-600">Customer:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($orderData['customer_name']); ?></span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Email:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($orderData['customer_email']); ?></span>
                </div>
                <?php if (!empty($orderData['customer_phone'])): ?>
                <div class="text-sm">
                    <span class="text-gray-600">Phone:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($orderData['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/admin-footer.php';
// Flush output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

