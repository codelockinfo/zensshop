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

// Get order identifier from URL
$orderId = $_GET['id'] ?? null;
$orderNumber = $_GET['order_number'] ?? null;

$order = new Order();
$orderData = null;

$storeId = $_SESSION['store_id'] ?? null;
if ($orderNumber) {
    $orderData = $order->getByOrderNumber($orderNumber, $storeId);
    if ($orderData) {
        $orderId = $orderData['id'];
    }
} elseif ($orderId) {
    // Convert to integer if it's a numeric ID
    if (is_numeric($orderId)) {
        $orderId = (int)$orderId;
        $orderData = $order->getById($orderId, $storeId);
    }
}

if (!$orderData) {
    // Clear any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $baseUrl . '/admin/orders/list.php');
    exit;
}

// Decode addresses
$billingAddress = !empty($orderData['billing_address']) ? json_decode($orderData['billing_address'], true) : null;
$shippingAddress = !empty($orderData['shipping_address']) ? json_decode($orderData['shipping_address'], true) : null;

// Fetch cancellation/refund request
$db = Database::getInstance();
$request = $db->fetchOne("SELECT * FROM ordercancel WHERE order_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 1", [$orderData['id'], $storeId]);

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
            <a href="<?php echo url('admin/orders/invoice.php?id=' . $orderData['id']); ?>" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-file-invoice mr-2"></i>Invoice
            </a>
            <a href="<?php echo url('admin/orders/edit.php?order_number=' . urlencode($orderData['order_number'])); ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                <i class="fas fa-edit mr-2"></i>Edit Order
            </a>
            <a href="<?php echo url('admin/orders/list.php'); ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
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
                    <?php if (!empty($orderData['delivery_date'])): ?>
                    <p class="text-sm text-gray-500 mt-1">
                        Estimated Delivery: <span class="font-bold text-gray-800"><?php echo date('F j, Y', strtotime($orderData['delivery_date'])); ?></span>
                    </p>
                    <?php endif; ?>
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
                        : 'https://placehold.co/100';
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                         class="w-20 h-20 object-cover rounded"
                         onerror="this.src='https://placehold.co/100'">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </h3>
                        <?php if (!empty($item['product_sku'])): ?>
                        <p class="text-sm text-gray-500 mt-1">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                        <?php endif; ?>
                        
                        <?php 
                        $variantAttributes = !empty($item['variant_attributes']) ? json_decode($item['variant_attributes'], true) : [];
                        if (!empty($variantAttributes)): 
                        ?>
                        <div class="mt-1 space-y-0.5">
                            <?php foreach ($variantAttributes as $key => $value): ?>
                            <p class="text-xs text-gray-600 font-medium"><?php echo htmlspecialchars($key); ?>: <?php echo htmlspecialchars($value); ?></p>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <p class="text-sm text-gray-600 mt-2">
                            Quantity: <span class="font-medium"><?php echo $item['quantity']; ?></span>
                            <?php if (!empty($item['oversold_quantity']) && $item['oversold_quantity'] > 0): ?>
                                <span class="ml-2 bg-red-100 text-red-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase italic"><i class="fas fa-exclamation-circle mr-1"></i> Oversold: <?php echo $item['oversold_quantity']; ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            Price: <span class="font-medium"><?php echo format_currency($item['price']); ?></span>
                        </p>
                    </div>
                    <div class="text-right flex flex-col items-end">
                        <p class="font-semibold text-gray-900">
                            <?php echo format_currency($item['line_total'] ?? $item['subtotal']); ?>
                        </p>
                        <div class="text-[10px] text-gray-500 mt-1 text-right">
                            <?php if (!empty($item['hsn_code'])): ?>
                                <p>HSN: <?php echo htmlspecialchars($item['hsn_code']); ?></p>
                            <?php endif; ?>
                            <p>GST: <?php echo number_format($item['gst_percent'] ?? 0, 2); ?>%</p>
                            <?php if (($item['cgst_amount'] ?? 0) > 0): ?>
                                <p>CGST: <?php echo format_currency($item['cgst_amount']); ?></p>
                                <p>SGST: <?php echo format_currency($item['sgst_amount'] ?? $item['cgst_amount']); ?></p>
                            <?php elseif (($item['igst_amount'] ?? 0) > 0): ?>
                                <p>IGST: <?php echo format_currency($item['igst_amount']); ?></p>
                            <?php endif; ?>
                        </div>
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
    <!-- Right Column - Order Summary -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Delhivery Shipping Card -->
        <div class="admin-card">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-truck mr-2 text-orange-600"></i>
                Shipping & Logistics
            </h2>
            
            <?php if (empty($orderData['tracking_number'])): ?>
                <div class="p-4 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <p class="text-sm text-gray-500 mb-4 text-center">No shipment created yet for this order.</p>
                    <div class="grid grid-cols-2 gap-4 border-b border-dashed pb-3 mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Est. Weight</p>
                            <p class="text-xs font-bold text-gray-700"><?php echo number_format($orderData['total_weight'] ?? 0.5, 2); ?> kg</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Packages</p>
                            <p class="text-xs font-bold text-gray-700"><?php echo (int)($orderData['package_count'] ?? 1); ?></p>
                        </div>
                    </div>
                    <button onclick="handleDelhiveryAction('create_shipment')" id="createShipmentBtn" 
                            class="w-full bg-orange-600 text-white py-2 rounded font-semibold hover:bg-orange-700 transition flex items-center justify-center">
                        <i class="fas fa-plus-circle mr-2"></i> Create Delhivery Shipment
                    </button>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">Waybill / Tracking No.</p>
                        <p class="text-lg font-mono font-bold text-blue-600"><?php echo htmlspecialchars($orderData['tracking_number']); ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t pt-2 mt-2">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Total Weight</p>
                            <p class="text-sm font-bold text-gray-700"><?php echo number_format($orderData['total_weight'] ?? 0.5, 2); ?> kg</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Packages</p>
                            <p class="text-sm font-bold text-gray-700"><?php echo (int)($orderData['package_count'] ?? 1); ?></p>
                        </div>
                    </div>
                    
                    <div id="trackingStatusContainer" class="p-3 bg-blue-50 rounded border border-blue-100 text-sm">
                        <i class="fas fa-sync fa-spin mr-2"></i> Fetching status...
                    </div>

                    <div class="flex flex-col gap-2">
                        <button onclick="handleDelhiveryAction('track_shipment')" 
                                class="w-full bg-blue-600 text-white py-2 rounded text-sm font-semibold hover:bg-blue-700 transition">
                            <i class="fas fa-search-location mr-2"></i> Track Live Status
                        </button>
                        <button onclick="handleDelhiveryAction('cancel_shipment')" 
                                class="w-full bg-white border border-red-200 text-red-600 py-2 rounded text-sm font-semibold hover:bg-red-50 transition">
                            <i class="fas fa-times-circle mr-2"></i> Cancel Shipment
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
        async function handleDelhiveryAction(action) {
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            
            if (action === 'cancel_shipment' && !confirm('Are you sure you want to cancel this shipment?')) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

            try {
                const response = await fetch('<?php echo url("admin/api/delhivery_actions.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, order_number: '<?php echo $orderData['order_number']; ?>' })
                });
                
                const data = await response.json();
                if (data.success) {
                    if (action === 'track_shipment') {
                        // Display tracking data in console instead of alert
                        console.log('Delhivery Live Tracking Data:', data.data);
                    } else {
                        location.reload();
                    }
                } else {
                    console.error('Delhivery Action Failed:', data.message || 'Action failed');
                }
            } catch (error) {
                console.error('Delhivery Network Error:', error);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        <?php if (!empty($orderData['tracking_number'])): ?>
        // Auto-fetch basic status
        document.addEventListener('DOMContentLoaded', async () => {
            const statusDiv = document.getElementById('trackingStatusContainer');
            try {
                const response = await fetch('<?php echo url("admin/api/delhivery_actions.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'track_shipment', order_number: '<?php echo $orderData['order_number']; ?>' })
                });
                const data = await response.json();
                if (data.success && data.data.ShipmentData && data.data.ShipmentData[0]) {
                    const ship = data.data.ShipmentData[0].Shipment;
                    statusDiv.innerHTML = `<span class="font-bold text-blue-800">${ship.Status.Status || 'Active'}</span><br><span class="text-xs text-gray-500">${ship.Status.Instructions || ''}</span>`;
                } else {
                    statusDiv.innerHTML = '<span class="text-gray-500">Status unavailable</span>';
                }
            } catch (e) {
                statusDiv.innerHTML = '<span class="text-red-500">Failed to load status</span>';
            }
        });
        <?php endif; ?>
        </script>

        <div class="admin-card">
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

                <!-- COD Charge -->
                <?php if (($orderData['cod_charge'] ?? 0) > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">COD Service Charge</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['cod_charge']); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Tax Breakup -->
                <?php if (($orderData['cgst_total'] ?? 0) > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">CGST</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['cgst_total']); ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">SGST</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['sgst_total']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (($orderData['igst_total'] ?? 0) > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">IGST</span>
                    <span class="text-gray-900 font-medium"><?php echo format_currency($orderData['igst_total']); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($orderData['tax_amount'] > 0 && ($orderData['cgst_total'] ?? 0) == 0 && ($orderData['igst_total'] ?? 0) == 0): ?>
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
<?php if ($request): ?>
        <div class="admin-card border-2 border-red-500">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-xl">
                        <i class="fas <?php echo $request['type'] === 'refund' ? 'fa-undo' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?php echo $request['type'] === 'refund' ? 'Refund Requested' : 'Cancellation Requested'; ?></h2>
                        <p class="text-sm text-gray-500">Submitted on <?php echo date('F j, Y, g:i a', strtotime($request['created_at'])); ?></p>
                    </div>
                </div>
                <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider <?php 
                    echo $request['cancel_status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                        ($request['cancel_status'] === 'rejected' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700'); 
                ?>">
                    <?php echo htmlspecialchars($request['cancel_status'] ?? 'pending'); ?>
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Reason for <?php echo ucfirst($request['type']); ?></p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($request['cancel_reason']); ?></p>
                </div>
                
                <?php if (!empty($request['cancel_comment'])): ?>
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Customer Comments</p>
                    <div class="text-gray-700 bg-gray-50 p-4 rounded-xl border-l-4 border-red-500 italic">
                        "<?php echo nl2br(htmlspecialchars($request['cancel_comment'])); ?>"
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($request['cancel_status'] === 'pending' || $request['cancel_status'] === 'requested'): ?>
            <div class="pt-6 border-t border-gray-100 flex items-center gap-4">
                <?php if ($request['type'] === 'refund'): ?>
                    <button onclick="handleRequestAction(event, '<?php echo $request['id']; ?>', 'approved', 'refund')" class="bg-black text-white px-8 py-3 rounded-xl font-bold hover:bg-gray-800 transition flex items-center gap-2 shadow-lg">
                        <i class="fas fa-check-circle"></i> Approve and Process Refund
                    </button>
                <?php else: ?>
                    <button onclick="handleRequestAction(event, '<?php echo $request['id']; ?>', 'approved', 'cancel')" class="bg-red-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-red-700 transition flex items-center gap-2 shadow-lg">
                        <i class="fas fa-check-circle"></i> Approve Cancellation
                    </button>
                <?php endif; ?>
                
                <button onclick="handleRequestAction(event, '<?php echo $request['id']; ?>', 'rejected')" class="bg-white border-2 border-gray-200 text-gray-600 px-8 py-3 rounded-xl font-bold hover:bg-gray-50 transition">
                    Reject Request
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
async function handleRequestAction(event, requestId, status, type = '') {
    const actionText = status === 'approved' ? 'Approve' : 'Reject';
    // if (!confirm(`Are you sure you want to ${actionText} this request?`)) return;
    
    const btn = event.currentTarget;
    const btns = btn.parentElement.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        const res = await fetch('<?php echo url("admin/api/handle_request.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ requestId, status, type })
        });
        
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            console.error('Request Action Failed:', data.message || 'Error updating request');
            btns.forEach(b => b.disabled = false);
            btn.innerHTML = originalContent;
        }
    } catch (e) {
        console.error('Network Error in handleRequestAction:', e);
        btns.forEach(b => b.disabled = false);
        btn.innerHTML = originalContent;
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/admin-footer.php';
// Flush output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

