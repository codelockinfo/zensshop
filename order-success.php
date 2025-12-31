<?php
/**
 * Order Success Page - Thank You Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Order.php';

$baseUrl = getBaseUrl();
$order = new Order();

// Get order ID from URL - try both 'id' and 'order_id' parameters
$orderId = $_GET['id'] ?? $_GET['order_id'] ?? null;

// Convert to integer if it's a string number
if ($orderId && is_numeric($orderId)) {
    $orderId = (int)$orderId;
}

// Debug: Log order ID for troubleshooting
error_log("Order Success Page - Order ID from URL: " . var_export($orderId, true));
error_log("Order Success Page - GET params: " . var_export($_GET, true));
error_log("Order Success Page - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

if (!$orderId) {
    error_log("Order Success Page - No order ID provided, redirecting to home");
    // Don't redirect in production - show error instead
    die("Error: Order ID is required. Please check your order confirmation email.");
}

// Get order details
$orderData = $order->getById($orderId);

error_log("Order Success Page - Order data: " . var_export($orderData ? 'Found' : 'Not Found', true));

if (!$orderData) {
    error_log("Order Success Page - Order not found for ID: " . $orderId . ", redirecting to home");
    // Don't redirect - show error instead
    die("Error: Order not found. Please contact support with your order number.");
}

// Format customer name (first name only for thank you message)
$customerFirstName = explode(' ', $orderData['customer_name'])[0];

// Calculate order dates for tracking timeline
$orderDate = new DateTime($orderData['created_at']);
$confirmedDate = $orderDate->format('M j');
$shippingDate = (clone $orderDate)->modify('+6 days')->format('M j');
$deliveryDate = (clone $orderDate)->modify('+7 days')->format('M j');

$pageTitle = 'Order Confirmation';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Hide announcement bar and header on order success page */
.bg-black.text-white.text-sm.py-2,
nav.bg-white.sticky.top-0 {
    display: none !important;
}

    .order-timeline {
        position: relative;
        padding-left: 2rem;
    }
    
    .order-timeline::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 2rem;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -31px;
        top: 0.25rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        background: white;
        border: 2px solid #e5e7eb;
        z-index: 1;
    }
    
    .timeline-item.active::before {
        background: #8b5cf6;
        border-color: #8b5cf6;
    }
    
    .timeline-item.completed::before {
        background: #10b981;
        border-color: #10b981;
    }
    
    .order-success-icon {
        width: 3rem;
        height: 3rem;
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
</style>

<section class="min-h-screen bg-white">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-6xl mx-auto">
            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column - Order Details & Tracking -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Logo -->
                    <div class="mb-8">
                        <a href="<?php echo $baseUrl; ?>/" class="text-3xl font-heading font-bold text-black">milano</a>
                    </div>
                    
                    <!-- Order Confirmation Header -->
                    <div class="flex items-start space-x-4 mb-8">
                        <div class="order-success-icon flex-shrink-0">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2 mb-2">
                                <h1 class="text-2xl font-bold text-gray-900">Order #<?php echo htmlspecialchars($orderData['order_number']); ?></h1>
                            </div>
                            <p class="text-xl text-gray-700">Thank you <?php echo htmlspecialchars($customerFirstName); ?>!</p>
                        </div>
                    </div>
                    
                    <!-- Order Tracking Timeline -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Order Tracking</h2>
                        <div class="order-timeline">
                            <!-- Confirmed -->
                            <div class="timeline-item completed">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                        <span class="font-medium text-gray-900">Confirmed</span>
                                    </div>
                                    <span class="text-sm text-gray-500"><?php echo $confirmedDate; ?></span>
                                </div>
                            </div>
                            
                            <!-- On its way -->
                            <div class="timeline-item active">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-truck text-purple-500"></i>
                                        <span class="font-medium text-gray-900">On its way</span>
                                    </div>
                                    <span class="text-sm text-gray-500"><?php echo $shippingDate; ?></span>
                                </div>
                            </div>
                            
                            <!-- Out for delivery -->
                            <div class="timeline-item">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-shipping-fast text-gray-400"></i>
                                        <span class="font-medium text-gray-500">Out for delivery</span>
                                    </div>
                                    <span class="text-sm text-gray-400"><?php echo $deliveryDate; ?></span>
                                </div>
                            </div>
                            
                            <!-- Delivered -->
                            <div class="timeline-item">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-home text-gray-400"></i>
                                        <span class="font-medium text-gray-500">Delivered</span>
                                    </div>
                                    <span class="text-sm text-gray-400"><?php echo $deliveryDate; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tracking Number -->
                        <?php if (!empty($orderData['tracking_number'])): ?>
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tracking Number:</label>
                            <p class="text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($orderData['tracking_number']); ?></p>
                        </div>
                        <?php else: ?>
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tracking Number:</label>
                            <p class="text-sm text-gray-400 italic">Tracking number will be available once your order ships</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Shipping Address -->
                    <?php 
                    $shippingAddress = !empty($orderData['shipping_address']) ? json_decode($orderData['shipping_address'], true) : null;
                    if ($shippingAddress):
                    ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Address</h2>
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
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Right Column - Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-50 rounded-lg p-6 sticky top-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Order Summary</h2>
                        
                        <!-- Order Items -->
                        <div class="space-y-4 mb-6">
                            <?php foreach ($orderData['items'] as $item): ?>
                            <div class="flex items-start space-x-4">
                                <?php 
                                $imageUrl = !empty($item['featured_image']) 
                                    ? (strpos($item['featured_image'], 'http') === 0 
                                        ? $item['featured_image'] 
                                        : $baseUrl . '/' . ltrim($item['featured_image'], '/'))
                                    : $baseUrl . '/assets/images/placeholder.jpg';
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     class="w-16 h-16 object-cover rounded">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 line-clamp-2">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Quantity: <?php echo $item['quantity']; ?>
                                    </p>
                                    <?php if (!empty($item['product_sku'])): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        SKU: <?php echo htmlspecialchars($item['product_sku']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900">
                                        <?php echo format_currency($item['subtotal']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Price Breakdown -->
                        <div class="border-t border-gray-200 pt-4 space-y-3">
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
                            
                            <!-- Total -->
                            <div class="flex justify-between text-lg font-bold pt-4 border-t border-gray-200">
                                <span class="text-gray-900">Total</span>
                                <span class="text-gray-900"><?php echo format_currency($orderData['total_amount']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Payment Info -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="text-xs text-gray-500 space-y-1">
                                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($orderData['payment_method'] ?? 'Razorpay'); ?></p>
                                <p><strong>Payment Status:</strong> <span class="capitalize text-green-600"><?php echo htmlspecialchars($orderData['payment_status']); ?></span></p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-6 space-y-3">
                            <a href="<?php echo url('/'); ?>" 
                               class="block w-full bg-black text-white text-center py-3 rounded-lg hover:bg-gray-800 transition font-medium">
                                Continue Shopping
                            </a>
                            <a href="<?php echo url('shop'); ?>" 
                               class="block w-full bg-white border border-gray-300 text-gray-900 text-center py-3 rounded-lg hover:bg-gray-50 transition font-medium">
                                Browse Products
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</section>
