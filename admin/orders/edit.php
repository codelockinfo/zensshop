<?php
// Start output buffering to prevent headers already sent errors
if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$order = new Order();
$product = new Product();
$error = '';
$success = '';

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
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $baseUrl . '/admin/orders/list.php');
    exit;
}

// Decode addresses
$billingAddress = !empty($orderData['billing_address']) ? json_decode($orderData['billing_address'], true) : [];
$shippingAddress = !empty($orderData['shipping_address']) ? json_decode($orderData['shipping_address'], true) : [];

// Handle form submission BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Track if items were modified (to trigger recalculation)
        $itemsModified = false;
        
        // Handle order items updates
        if (isset($_POST['order_items_data']) && is_array($_POST['order_items_data'])) {
            $existingItems = [];
            foreach ($orderData['items'] as $item) {
                $existingItems[$item['id']] = $item;
            }
            
            // Delete items marked for deletion
            if (isset($_POST['delete_items']) && is_array($_POST['delete_items'])) {
                foreach ($_POST['delete_items'] as $itemId) {
                    $order->deleteOrderItem($itemId);
                    unset($existingItems[$itemId]);
                    $itemsModified = true;
                }
            }
            
            // Update existing items
            foreach ($_POST['order_items_data'] as $itemData) {
                if (isset($itemData['id']) && isset($existingItems[$itemData['id']])) {
                    $existingItem = $existingItems[$itemData['id']];
                    // Check if values actually changed
                    if ($existingItem['product_name'] != $itemData['product_name'] ||
                        $existingItem['quantity'] != $itemData['quantity'] ||
                        $existingItem['price'] != $itemData['price']) {
                        $order->updateOrderItem($itemData['id'], [
                            'product_name' => $itemData['product_name'],
                            'quantity' => $itemData['quantity'],
                            'price' => $itemData['price']
                        ]);
                        $itemsModified = true;
                    }
                    unset($existingItems[$itemData['id']]);
                }
            }
            
            // Delete any remaining items that weren't in the form (shouldn't happen, but safety check)
            foreach ($existingItems as $itemId => $item) {
                $order->deleteOrderItem($itemId);
                $itemsModified = true;
            }
        }
        
        // Handle new order item
        if (!empty($_POST['new_product_id']) && !empty($_POST['new_quantity']) && !empty($_POST['new_price'])) {
            $productData = $product->getById($_POST['new_product_id'], $storeId);
            if ($productData) {
                $order->addOrderItem($orderId, [
                    'product_id' => $_POST['new_product_id'],
                    'product_name' => $productData['name'],
                    'product_sku' => $productData['sku'] ?? null,
                    'quantity' => $_POST['new_quantity'],
                    'price' => $_POST['new_price']
                ]);
                $itemsModified = true;
            }
        }
        
        // Always recalculate totals if items were modified
        if ($itemsModified) {
            $order->recalculateTotals($orderId);
            // Reload order to get recalculated values
            $orderData = $order->getById($orderId, $storeId);
        }
        
        // Prepare update data
        $updateData = [];
        
        // Customer information
        if (isset($_POST['customer_name'])) {
            $updateData['customer_name'] = $_POST['customer_name'];
        }
        if (isset($_POST['customer_email'])) {
            $updateData['customer_email'] = $_POST['customer_email'];
        }
        if (isset($_POST['customer_phone'])) {
            $updateData['customer_phone'] = $_POST['customer_phone'];
        }
        
        // Addresses
        if (isset($_POST['billing_address'])) {
            $billingAddr = [
                'street' => $_POST['billing_street'] ?? '',
                'address_line1' => $_POST['billing_address_line1'] ?? '',
                'address_line2' => $_POST['billing_address_line2'] ?? '',
                'city' => $_POST['billing_city'] ?? '',
                'state' => $_POST['billing_state'] ?? '',
                'zip' => $_POST['billing_zip'] ?? '',
                'postal_code' => $_POST['billing_postal_code'] ?? '',
                'country' => $_POST['billing_country'] ?? ''
            ];
            $updateData['billing_address'] = $billingAddr;
        }
        
        if (isset($_POST['shipping_address'])) {
            $shippingAddr = [
                'street' => $_POST['shipping_street'] ?? '',
                'address_line1' => $_POST['shipping_address_line1'] ?? '',
                'address_line2' => $_POST['shipping_address_line2'] ?? '',
                'city' => $_POST['shipping_city'] ?? '',
                'state' => $_POST['shipping_state'] ?? '',
                'zip' => $_POST['shipping_zip'] ?? '',
                'postal_code' => $_POST['shipping_postal_code'] ?? '',
                'country' => $_POST['shipping_country'] ?? ''
            ];
            $updateData['shipping_address'] = $shippingAddr;
        }
        
        // Order status and payment (always update these if they exist in POST)
        if (isset($_POST['order_status'])) {
            $updateData['order_status'] = $_POST['order_status'];
        }
        if (isset($_POST['payment_status'])) {
            $updateData['payment_status'] = $_POST['payment_status'];
        }
        if (isset($_POST['payment_method'])) {
            $updateData['payment_method'] = $_POST['payment_method'] ?? null;
        }
        
        // Tracking and notes (allow empty values)
        $updateData['tracking_number'] = $_POST['tracking_number'] ?? null;
        $updateData['notes'] = $_POST['notes'] ?? null;
        
        // Financial fields - use recalculated values if items were modified, otherwise use form values
        if ($itemsModified) {
            // Use recalculated values from database (already loaded above)
            $updateData['subtotal'] = $orderData['subtotal'];
            $updateData['total_amount'] = $orderData['total_amount'];
            // Still allow manual updates to discount, shipping, tax
            $updateData['discount_amount'] = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : $orderData['discount_amount'];
            $updateData['shipping_amount'] = isset($_POST['shipping_amount']) ? floatval($_POST['shipping_amount']) : $orderData['shipping_amount'];
            $updateData['tax_amount'] = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : $orderData['tax_amount'];
            // Recalculate total with updated discount/shipping/tax
            $updateData['total_amount'] = $updateData['subtotal'] - $updateData['discount_amount'] + $updateData['shipping_amount'] + $updateData['tax_amount'];
        } else {
            // No items modified, use form values
            $updateData['subtotal'] = isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : 0;
            $updateData['discount_amount'] = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
            $updateData['shipping_amount'] = isset($_POST['shipping_amount']) ? floatval($_POST['shipping_amount']) : 0;
            $updateData['tax_amount'] = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
            $updateData['total_amount'] = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        }
        
        // Recalculate totals if manually requested (Recalculate Total button)
        if (isset($_POST['recalculate_totals']) && $_POST['recalculate_totals'] == '1') {
            $order->recalculateTotals($orderId);
            // Reload order to get recalculated values
            $orderData = $order->getById($orderId, $storeId);
            // Update with recalculated totals
            $updateData['subtotal'] = $orderData['subtotal'];
            $updateData['total_amount'] = $orderData['total_amount'];
        }
        
        // Update order (always update, even if some fields are empty)
        $order->update($orderId, $updateData);
        
        // Redirect after successful update
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $baseUrl . '/admin/orders/detail.php?order_number=' . urlencode($orderData['order_number']) . '&success=updated');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Reload order data after potential updates
$orderData = $order->getById($orderId, $storeId);
$billingAddress = !empty($orderData['billing_address']) ? json_decode($orderData['billing_address'], true) : [];
$shippingAddress = !empty($orderData['shipping_address']) ? json_decode($orderData['shipping_address'], true) : [];

// Get all products for adding items
$products = $db->fetchAll("SELECT id, name, price, sale_price, sku FROM products WHERE status = 'active' AND store_id = ? ORDER BY name", [$storeId]);

$pageTitle = 'Edit Order - ' . $orderData['order_number'];
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">Edit Order</h1>
            <p class="text-gray-600">Dashboard > Order > Edit Order</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="<?php echo $baseUrl; ?>/admin/orders/detail.php?order_number=<?php echo urlencode($orderData['order_number']); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                <i class="fas fa-eye mr-2"></i>View Order Details
            </a>
            <a href="<?php echo $baseUrl; ?>/admin/orders/list.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Orders
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<form method="POST" action="" id="orderEditForm">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Main Order Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status & Payment -->
            <div class="admin-card">
                <h2 class="text-xl font-semibold mb-4">Order Status & Payment</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Order Status *</label>
                        <select name="order_status" required class="admin-form-select">
                            <option value="pending" <?php echo $orderData['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $orderData['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $orderData['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $orderData['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $orderData['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Payment Status *</label>
                        <select name="payment_status" required class="admin-form-select">
                            <option value="pending" <?php echo $orderData['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $orderData['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?php echo $orderData['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $orderData['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Payment Method</label>
                    <input type="text" name="payment_method" value="<?php echo htmlspecialchars($orderData['payment_method'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Tracking Number</label>
                    <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($orderData['tracking_number'] ?? ''); ?>" class="admin-form-input" placeholder="Enter tracking number">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Notes</label>
                    <textarea name="notes" rows="4" class="admin-form-input" placeholder="Internal notes about this order"><?php echo htmlspecialchars($orderData['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="admin-card">
                <h2 class="text-xl font-semibold mb-4">Customer Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Customer Name *</label>
                        <input type="text" name="customer_name" value="<?php echo htmlspecialchars($orderData['customer_name']); ?>" required class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Customer Email *</label>
                        <input type="email" name="customer_email" value="<?php echo htmlspecialchars($orderData['customer_email']); ?>" required class="admin-form-input">
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Customer Phone</label>
                    <input type="text" name="customer_phone" value="<?php echo htmlspecialchars($orderData['customer_phone'] ?? ''); ?>" class="admin-form-input">
                </div>
            </div>

            <!-- Billing Address -->
            <div class="admin-card">
                <h2 class="text-xl font-semibold mb-4">Billing Address</h2>
                <input type="hidden" name="billing_address" value="1">
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Street</label>
                    <input type="text" name="billing_street" value="<?php echo htmlspecialchars($billingAddress['street'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Address Line 1</label>
                    <input type="text" name="billing_address_line1" value="<?php echo htmlspecialchars($billingAddress['address_line1'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Address Line 2</label>
                    <input type="text" name="billing_address_line2" value="<?php echo htmlspecialchars($billingAddress['address_line2'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">City</label>
                        <input type="text" name="billing_city" value="<?php echo htmlspecialchars($billingAddress['city'] ?? ''); ?>" class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">State</label>
                        <input type="text" name="billing_state" value="<?php echo htmlspecialchars($billingAddress['state'] ?? ''); ?>" class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">ZIP / Postal Code</label>
                        <input type="text" name="billing_zip" value="<?php echo htmlspecialchars($billingAddress['zip'] ?? $billingAddress['postal_code'] ?? ''); ?>" class="admin-form-input">
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Country</label>
                    <input type="text" name="billing_country" value="<?php echo htmlspecialchars($billingAddress['country'] ?? ''); ?>" class="admin-form-input">
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="admin-card">
                <h2 class="text-xl font-semibold mb-4">Shipping Address</h2>
                <input type="hidden" name="shipping_address" value="1">
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Street</label>
                    <input type="text" name="shipping_street" value="<?php echo htmlspecialchars($shippingAddress['street'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Address Line 1</label>
                    <input type="text" name="shipping_address_line1" value="<?php echo htmlspecialchars($shippingAddress['address_line1'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Address Line 2</label>
                    <input type="text" name="shipping_address_line2" value="<?php echo htmlspecialchars($shippingAddress['address_line2'] ?? ''); ?>" class="admin-form-input">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">City</label>
                        <input type="text" name="shipping_city" value="<?php echo htmlspecialchars($shippingAddress['city'] ?? ''); ?>" class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">State</label>
                        <input type="text" name="shipping_state" value="<?php echo htmlspecialchars($shippingAddress['state'] ?? ''); ?>" class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">ZIP / Postal Code</label>
                        <input type="text" name="shipping_zip" value="<?php echo htmlspecialchars($shippingAddress['zip'] ?? $shippingAddress['postal_code'] ?? ''); ?>" class="admin-form-input">
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Country</label>
                    <input type="text" name="shipping_country" value="<?php echo htmlspecialchars($shippingAddress['country'] ?? ''); ?>" class="admin-form-input">
                </div>
            </div>

            <!-- Order Items -->
            <div class="admin-card">
                <h2 class="text-xl font-semibold mb-4">Order Items</h2>
                
                <div id="orderItemsContainer" class="space-y-4 mb-4">
                    <?php 
                    $itemIndex = 0;
                    foreach ($orderData['items'] as $item): 
                    ?>
                    <div class="border rounded p-4 order-item" data-item-id="<?php echo $item['id']; ?>">
                        <input type="hidden" name="order_items_data[<?php echo $itemIndex; ?>][id]" value="<?php echo $item['id']; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Product Name</label>
                                <input type="text" name="order_items_data[<?php echo $itemIndex; ?>][product_name]" value="<?php echo htmlspecialchars($item['product_name']); ?>" class="admin-form-input" required>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-form-label">Quantity</label>
                                <input type="number" name="order_items_data[<?php echo $itemIndex; ?>][quantity]" value="<?php echo $item['quantity']; ?>" min="1" class="admin-form-input item-quantity" required>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-form-label">Price</label>
                                <input type="number" step="0.01" name="order_items_data[<?php echo $itemIndex; ?>][price]" value="<?php echo $item['price']; ?>" class="admin-form-input item-price" required>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-form-label">Subtotal</label>
                                <input type="text" value="<?php echo format_currency($item['subtotal']); ?>" class="admin-form-input item-subtotal" readonly>
                                <button type="button" onclick="removeOrderItem(this)" class="mt-2 text-red-500 hover:text-red-700 text-sm">
                                    <i class="fas fa-trash mr-1"></i>Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $itemIndex++;
                    endforeach; 
                    ?>
                </div>
                
                <!-- Add New Item -->
                <div class="border-t pt-4">
                    <h3 class="font-semibold mb-3">Add New Item</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Product</label>
                            <select name="new_product_id" id="new_product_id" class="admin-form-select">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>" data-price="<?php echo $prod['sale_price'] ?? $prod['price']; ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?> - <?php echo format_currency($prod['sale_price'] ?? $prod['price']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Quantity</label>
                            <input type="number" name="new_quantity" id="new_quantity" min="1" value="1" class="admin-form-input">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Price</label>
                            <input type="number" step="0.01" name="new_price" id="new_price" class="admin-form-input">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">&nbsp;</label>
                            <button type="button" onclick="addOrderItem()" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                <i class="fas fa-plus mr-2"></i>Add Item
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Financial Summary -->
        <div class="lg:col-span-1">
            <div class="admin-card sticky top-8">
                <h2 class="text-xl font-semibold mb-4">Financial Summary</h2>
                
                <div class="space-y-3">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Subtotal</label>
                        <input type="number" step="0.01" name="subtotal" id="subtotal" value="<?php echo $orderData['subtotal']; ?>" class="admin-form-input financial-field">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Discount Amount</label>
                        <input type="number" step="0.01" name="discount_amount" id="discount_amount" value="<?php echo $orderData['discount_amount'] ?? 0; ?>" class="admin-form-input financial-field">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Shipping Amount</label>
                        <input type="number" step="0.01" name="shipping_amount" id="shipping_amount" value="<?php echo $orderData['shipping_amount'] ?? 0; ?>" class="admin-form-input financial-field">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Tax Amount</label>
                        <input type="number" step="0.01" name="tax_amount" id="tax_amount" value="<?php echo $orderData['tax_amount'] ?? 0; ?>" class="admin-form-input financial-field">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Total Amount</label>
                        <input type="number" step="0.01" name="total_amount" id="total_amount" value="<?php echo $orderData['total_amount']; ?>" class="admin-form-input font-bold text-lg" readonly>
                    </div>
                    
                    <div class="pt-4 border-t">
                        <button type="button" onclick="recalculateTotal()" class="w-full bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition mb-2">
                            <i class="fas fa-calculator mr-2"></i>Recalculate Total
                        </button>
                        <input type="hidden" name="recalculate_totals" id="recalculate_totals" value="0">
                    </div>
                </div>
        </div>
    <div class="mt-6 flex justify-end space-x-3 sticky top-[83%]">
        <a href="<?php echo $baseUrl; ?>/admin/orders/detail.php?order_number=<?php echo urlencode($orderData['order_number']); ?>" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition">
            <i class="fas fa-save mr-2"></i>Update Order
        </button>
    </div>
        </div>
    </div>
</form>

<script>
<?php
// Ensure CURRENCY_SYMBOL is defined
if (!defined('CURRENCY_SYMBOL')) {
    require_once __DIR__ . '/../../config/constants.php';
}
?>
const CURRENCY_SYMBOL = '<?php echo defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '$'; ?>';

// Global functions - must be defined outside DOMContentLoaded
function removeOrderItem(button) {
    showConfirmModal('Are you sure you want to remove this item?', function() {
        const itemRow = button.closest('.order-item');
        // Mark as removed by hiding and disabling inputs
        itemRow.style.display = 'none';
        itemRow.querySelectorAll('input').forEach(input => {
            input.disabled = true;
        });
        // Add a hidden field to mark it for deletion
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_items[]';
        deleteInput.value = itemRow.getAttribute('data-item-id');
        itemRow.appendChild(deleteInput);
        recalculateTotal();
    });
}

function addOrderItem() {
    const productId = document.getElementById('new_product_id').value;
    const quantity = document.getElementById('new_quantity').value;
    const price = document.getElementById('new_price').value;
    
    if (!productId || !quantity || !price) {
        console.log('Please fill in all fields to add an item.');
        return;
    }
    
    // This will be handled on form submit
    // For now, just submit the form
    document.getElementById('orderEditForm').submit();
}

function recalculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const shipping = parseFloat(document.getElementById('shipping_amount').value) || 0;
    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    
    const total = subtotal - discount + shipping + tax;
    document.getElementById('total_amount').value = total.toFixed(2);
    document.getElementById('recalculate_totals').value = '1';
}

// Update item subtotal when quantity or price changes
document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.item-quantity');
    const priceInputs = document.querySelectorAll('.item-price');
    
    function updateItemSubtotal(itemRow) {
        const quantity = parseFloat(itemRow.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(itemRow.querySelector('.item-price').value) || 0;
        const subtotal = quantity * price;
        itemRow.querySelector('.item-subtotal').value = CURRENCY_SYMBOL + subtotal.toFixed(2);
    }
    
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            updateItemSubtotal(this.closest('.order-item'));
        });
    });
    
    priceInputs.forEach(input => {
        input.addEventListener('input', function() {
            updateItemSubtotal(this.closest('.order-item'));
        });
    });
    
    // Auto-fill price when product is selected
    const newProductSelect = document.getElementById('new_product_id');
    if (newProductSelect) {
        newProductSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('new_price').value = price;
            }
        });
    }
    
    // Recalculate when financial fields change
    document.querySelectorAll('.financial-field').forEach(field => {
        field.addEventListener('input', recalculateTotal);
    });
    
    // Handle item deletion on form submit
    const orderForm = document.getElementById('orderEditForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function() {
            // Items marked for deletion will be handled server-side
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../../includes/admin-footer.php';
// Flush output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

