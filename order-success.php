<?php
/**
 * Order Success Page - Thank You Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Order.php';

$baseUrl = getBaseUrl();
$order = new Order();

// Get order identifier from URL
$orderId = $_GET['id'] ?? $_GET['order_id'] ?? null;
$orderNumber = $_GET['order_number'] ?? null;

// Debug: Log identifiers for troubleshooting
error_log("Order Success Page - Identification: Order ID: " . var_export($orderId, true) . ", Order Number: " . var_export($orderNumber, true));

$orderData = null;
if ($orderNumber) {
    $orderData = $order->getByOrderNumber($orderNumber);
} elseif ($orderId) {
    // Convert to integer if it's a numeric ID
    if (is_numeric($orderId)) {
        $orderData = $order->getById((int)$orderId);
    }
}

if (!$orderData) {
    error_log("Order Success Page - Order not found for Identifier. Redirecting to home or showing error.");
    die("Error: Order not found. Please check your confirmation mail or contact support.");
}

// Format customer name (first name only for thank you message)
$customerFirstName = explode(' ', $orderData['customer_name'])[0];

// Calculate order dates for tracking timeline
$orderDate = new DateTime($orderData['created_at']);
$confirmedDate = $orderDate->format('M j');
$shippingDate = (clone $orderDate)->modify('+6 days')->format('M j');
$deliveryDate = (clone $orderDate)->modify('+7 days')->format('M j');

$pageTitle = 'Order Confirmation';
// Treat as checkout page to hide standard header elements
$isCheckout = true;
require_once __DIR__ . '/includes/header.php';

// Re-fetch settings locally to ensure variables exist if header doesn't pass them
$settingsObj = new Settings();
$storeId = $orderData['store_id'] ?? null;
$logoType = $settingsObj->get('footer_logo_type', 'image', $storeId);
$logoText = $settingsObj->get('footer_logo_text', 'HomeproX', $storeId);
$logo = $settingsObj->get('footer_logo_image', null, $storeId);
// Load Success Page Styling (Consolidated)
$successStylingJson = $settingsObj->get('success_page_styling', '', $storeId);
$successStyling = !empty($successStylingJson) ? json_decode($successStylingJson, true) : [];

// Helper function locally for success page
function getSuccessStyle($key, $default, $settingsObj, $successStyling, $storeId) {
    if (isset($successStyling[$key])) return $successStyling[$key];
    return $settingsObj->get($key, $default, $storeId);
}
?>

<style>
/* Hide announcement bar and header on order success page - Reinforcement */
.bg-black.text-white.text-sm.py-2,
nav.bg-white.sticky.top-0 {
    display: none !important;
}

/* Dynamic Order Success Styles */
:root {
    --succ-icon-color: <?php echo getSuccessStyle('success_icon_color', '#8b5cf6', $settingsObj, $successStyling, $storeId); ?>; /* Gradient start or solid */
    --succ-text-color: <?php echo getSuccessStyle('success_text_color', '#111827', $settingsObj, $successStyling, $storeId); ?>;
    --succ-sec-text-color: <?php echo getSuccessStyle('success_secondary_text_color', '#374151', $settingsObj, $successStyling, $storeId); ?>;
    
    --succ-tl-active: <?php echo getSuccessStyle('success_timeline_active_color', '#8b5cf6', $settingsObj, $successStyling, $storeId); ?>;
    --succ-tl-comp: <?php echo getSuccessStyle('success_timeline_completed_color', '#10b981', $settingsObj, $successStyling, $storeId); ?>;
    
    /* Tracking Steps */
    --succ-step1: <?php echo getSuccessStyle('success_step1_color', '#10b981', $settingsObj, $successStyling, $storeId); ?>;
    --succ-step2: <?php echo getSuccessStyle('success_step2_color', '#8b5cf6', $settingsObj, $successStyling, $storeId); ?>;
    --succ-step3: <?php echo getSuccessStyle('success_step3_color', '#f59e0b', $settingsObj, $successStyling, $storeId); ?>;
    --succ-step4: <?php echo getSuccessStyle('success_step4_color', '#10b981', $settingsObj, $successStyling, $storeId); ?>;
    
    /* Button 1 (Invoice) */
    --succ-btn1-bg: <?php echo getSuccessStyle('success_btn1_bg', '#dc2626', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn1-text: <?php echo getSuccessStyle('success_btn1_text', '#ffffff', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn1-hover-bg: <?php echo getSuccessStyle('success_btn1_hover_bg', '#b91c1c', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn1-hover-text: <?php echo getSuccessStyle('success_btn1_hover_text', '#ffffff', $settingsObj, $successStyling, $storeId); ?>;
    
    /* Button 2 (Continue Shopping) */
    --succ-btn2-bg: <?php echo getSuccessStyle('success_btn2_bg', '#000000', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn2-text: <?php echo getSuccessStyle('success_btn2_text', '#ffffff', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn2-hover-bg: <?php echo getSuccessStyle('success_btn2_hover_bg', '#1f2937', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn2-hover-text: <?php echo getSuccessStyle('success_btn2_hover_text', '#ffffff', $settingsObj, $successStyling, $storeId); ?>;
    
    /* Button 3 (Browse Products) - Bordered */
    --succ-btn3-bg: <?php echo getSuccessStyle('success_btn3_bg', '#ffffff', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn3-text: <?php echo getSuccessStyle('success_btn3_text', '#111827', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn3-hover-bg: <?php echo getSuccessStyle('success_btn3_hover_bg', '#f9fafb', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn3-hover-text: <?php echo getSuccessStyle('success_btn3_hover_text', '#111827', $settingsObj, $successStyling, $storeId); ?>;
    --succ-btn3-border: <?php echo getSuccessStyle('success_btn3_border', '#d1d5db', $settingsObj, $successStyling, $storeId); ?>;
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
    
    /* Step 1: Confirmed */
    .timeline-item:nth-child(1).active::before,
    .timeline-item:nth-child(1).completed::before {
        background: var(--succ-step1) !important;
        border-color: var(--succ-step1) !important;
    }
    .timeline-item:nth-child(1).active i,
    .timeline-item:nth-child(1).completed i {
        color: var(--succ-step1) !important;
    }

    /* Step 2: On its way */
    .timeline-item:nth-child(2).active::before,
    .timeline-item:nth-child(2).completed::before {
        background: var(--succ-step2) !important;
        border-color: var(--succ-step2) !important;
    }
    .timeline-item:nth-child(2).active i,
    .timeline-item:nth-child(2).completed i {
        color: var(--succ-step2) !important;
    }

    /* Step 3: Out for delivery */
    .timeline-item:nth-child(3).active::before,
    .timeline-item:nth-child(3).completed::before {
        background: var(--succ-step3) !important;
        border-color: var(--succ-step3) !important;
    }
    .timeline-item:nth-child(3).active i,
    .timeline-item:nth-child(3).completed i {
        color: var(--succ-step3) !important;
    }

    /* Step 4: Delivered */
    .timeline-item:nth-child(4).active::before,
    .timeline-item:nth-child(4).completed::before {
        background: var(--succ-step4) !important;
        border-color: var(--succ-step4) !important;
    }
    .timeline-item:nth-child(4).active i,
    .timeline-item:nth-child(4).completed i {
        color: var(--succ-step4) !important;
    }
    
    .order-success-icon {
        width: 3rem;
        height: 3rem;
        background: var(--succ-icon-color) !important; /* Override gradient with single color if set, or we can handle gradient in admin later */
        /* If user wants gradient, they might lose it here if we force a color. 
           But admin input is type='color' so it creates a single color. 
           To keep premium feel, we might want to default to a gradient if not set? 
           The settingsObj->get returns a hex code, so it will be a solid color. 
        */
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    /* Text Colors */
    .success-main-text { color: var(--succ-text-color) !important; }
    .success-sec-text { color: var(--succ-sec-text-color) !important; }
    
    /* Button 1 */
    .success-btn1 {
        background-color: var(--succ-btn1-bg) !important;
        color: var(--succ-btn1-text) !important;
        transition: all 0.3s ease;
    }
    .success-btn1:hover {
        background-color: var(--succ-btn1-hover-bg) !important;
        color: var(--succ-btn1-hover-text) !important;
    }
    
    /* Button 2 */
    .success-btn2 {
        background-color: var(--succ-btn2-bg) !important;
        color: var(--succ-btn2-text) !important;
        transition: all 0.3s ease;
    }
    .success-btn2:hover {
        background-color: var(--succ-btn2-hover-bg) !important;
        color: var(--succ-btn2-hover-text) !important;
    }
    
    /* Button 3 */
    .success-btn3 {
        background-color: var(--succ-btn3-bg) !important;
        color: var(--succ-btn3-text) !important;
        border: 1px solid var(--succ-btn3-border) !important;
        transition: all 0.3s ease;
    }
    .success-btn3:hover {
        background-color: var(--succ-btn3-hover-bg) !important;
        color: var(--succ-btn3-hover-text) !important;
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
                        <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                            <?php if ($logoType == 'image' && !empty($logo)): ?>
                                <img src="<?php echo getImageUrl($logo); ?>" alt="<?php echo htmlspecialchars($logoText); ?>" class="h-[60px] w-auto object-contain">
                            <?php else: ?>
                                <span class="text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($logoText); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="flex items-start space-x-4 mb-8">
                        <div class="order-success-icon flex-shrink-0">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2 mb-2">
                                <h1 class="text-2xl font-bold text-gray-900 success-main-text">Order #<?php echo htmlspecialchars($orderData['order_number']); ?></h1>
                            </div>
                            <p class="text-xl text-gray-700 success-sec-text">Thank you <?php echo htmlspecialchars($customerFirstName); ?>!</p>
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
                                $imageUrl = getProductImage($item);
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     class="w-16 h-16 object-cover rounded"
                                     onerror="this.src='https://placehold.co/150x150?text=Product+Image'">
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
                                    
                                    <?php 
                                    $variantAttributes = !empty($item['variant_attributes']) ? (is_array($item['variant_attributes']) ? $item['variant_attributes'] : json_decode($item['variant_attributes'], true)) : [];
                                    if (!empty($variantAttributes) && is_array($variantAttributes)): 
                                    ?>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <?php foreach ($variantAttributes as $key => $value): ?>
                                                <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200">
                                                    <?php echo htmlspecialchars($key); ?>: <?php echo htmlspecialchars($value); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
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
                                <span class="text-gray-600 font-medium">-<?php echo format_currency($orderData['discount_amount']); ?></span>
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
                            <a href="<?php echo url('invoice.php?order_number=' . $orderData['order_number']); ?>" 
                               target="_blank"
                               class="block w-full text-center py-3 rounded-lg font-medium success-btn1">
                                <i class="fas fa-file-invoice mr-2"></i>Download Invoice
                            </a>
                            <a href="<?php echo url('/shop'); ?>" 
                               class="block w-full text-center py-3 rounded-lg font-medium success-btn2">
                                Continue Shopping
                            </a>
                            <a href="<?php echo url('/shop'); ?>" 
                               class="block w-full text-center py-3 rounded-lg font-medium success-btn3">
                                Browse Products
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</section>
