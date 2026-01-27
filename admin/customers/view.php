<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Customer Details';
// Moved header inclusion to after logic processing to prevent headers already sent error

$customer = new Customer();
$order = new Order();

// Get customer ID or email
$customerId = $_GET['id'] ?? null;
$customerEmail = $_GET['email'] ?? null;

if ($customerId) {
    $customerData = $customer->getById($customerId);
    if (!$customerData) {
        header('Location: ' . $baseUrl . '/admin/customers/list.php');
        exit;
    }
    $customerData['is_registered'] = true;
    $orders = $customer->getCustomerOrders($customerId);
    
    // Check if customer is a newsletter subscriber
    require_once __DIR__ . '/../../classes/Database.php';
    $db = Database::getInstance();
    $isSubscriber = $db->fetchOne("SELECT id FROM subscribers WHERE user_id = ?", [$customerId]);
    $customerData['is_subscriber'] = !empty($isSubscriber);
} else if ($customerEmail) {
    $customerData = $customer->getCustomerByEmail($customerEmail);
    if (!$customerData) {
        header('Location: ' . $baseUrl . '/admin/customers/list.php');
        exit;
    }
    $orders = $customer->getCustomerOrders(null, $customerEmail);
    $customerData['is_subscriber'] = false; // Guest customers don't have subscriber status linked
} else {
    header('Location: ' . $baseUrl . '/admin/customers/list.php');
    exit;
}
require_once __DIR__ . '/../../includes/admin-header.php';
?>


        <!-- Breadcrumbs -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Customer Details</h1>
                <p class="text-gray-600 text-sm mt-1">View customer information and orders</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="<?php echo $baseUrl; ?>/admin/customers/list.php" class="admin-btn bg-gray-500 text-white">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Customer Information -->
            <div class="lg:col-span-1">
                <div class="admin-card mb-6">
                    <h2 class="text-xl font-bold mb-4">Customer Information</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-200">
                            <div class="w-16 h-16 bg-primary-light rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-2xl text-primary-dark"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-lg text-gray-800"><?php echo htmlspecialchars($customerData['name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($customerData['email']); ?></p>
                                <?php if ($customerData['is_registered']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 mt-1">
                                    <i class="fas fa-check-circle mr-1"></i>Registered Customer
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-800 mt-1">
                                    <i class="fas fa-shopping-cart mr-1"></i>Guest Customer
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($customerData['is_subscriber'])): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800 mt-1 ml-1">
                                    <i class="fas fa-envelope mr-1"></i>Subscriber
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Email</label>
                            <p class="text-gray-800 mt-1"><?php echo htmlspecialchars($customerData['email']); ?></p>
                        </div>
                        
                        <?php if (!empty($customerData['phone'])): ?>
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Phone</label>
                            <p class="text-gray-800 mt-1"><?php echo htmlspecialchars($customerData['phone']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($customerData['is_registered']): ?>
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Status</label>
                            <p class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $customerData['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($customerData['status']); ?>
                                </span>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Member Since</label>
                            <p class="text-gray-800 mt-1"><?php echo date('F d, Y', strtotime($customerData['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="admin-card">
                    <h2 class="text-xl font-bold mb-4">Statistics</h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Orders</span>
                            <span class="font-bold text-lg text-gray-800"><?php echo $customerData['total_orders']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Spent</span>
                            <span class="font-bold text-lg text-primary"><?php echo format_price($customerData['total_spent']); ?></span>
                        </div>
                        <?php if (!empty($customerData['last_order_date'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Last Order</span>
                            <span class="text-sm text-gray-800"><?php echo date('M d, Y', strtotime($customerData['last_order_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Addresses and Orders -->
            <div class="lg:col-span-2">
                <!-- Addresses -->
                <div class="admin-card mb-6">
                    <h2 class="text-xl font-bold mb-4">Addresses</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">Billing Address</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (!empty($customerData['billing_address'])): 
                                    $billingAddr = $customerData['billing_address'];
                                    $decodedBilling = json_decode($billingAddr, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBilling)) {
                                        $street = $decodedBilling['street'] ?? '';
                                        $city = $decodedBilling['city'] ?? '';
                                        $state = $decodedBilling['state'] ?? '';
                                        $zip = $decodedBilling['zip'] ?? '';
                                        $country = $decodedBilling['country'] ?? '';
                                        
                                        echo '<p class="text-sm text-gray-800">';
                                        if ($street) echo htmlspecialchars($street) . '<br>';
                                        echo htmlspecialchars(trim("$city, $state $zip", ", ")) . '<br>';
                                        if ($country) echo htmlspecialchars($country);
                                        echo '</p>';
                                    } else {
                                        echo '<p class="text-sm text-gray-800 whitespace-pre-line">' . htmlspecialchars($billingAddr) . '</p>';
                                    }
                                else: ?>
                                <p class="text-sm text-gray-400">No billing address available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">Shipping Address</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (!empty($customerData['shipping_address'])): 
                                    $shippingAddr = $customerData['shipping_address'];
                                    $decodedShipping = json_decode($shippingAddr, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedShipping)) {
                                        $street = $decodedShipping['street'] ?? '';
                                        $city = $decodedShipping['city'] ?? '';
                                        $state = $decodedShipping['state'] ?? '';
                                        $zip = $decodedShipping['zip'] ?? '';
                                        $country = $decodedShipping['country'] ?? '';
                                        
                                        echo '<p class="text-sm text-gray-800">';
                                        if ($street) echo htmlspecialchars($street) . '<br>';
                                        echo htmlspecialchars(trim("$city, $state $zip", ", ")) . '<br>';
                                        if ($country) echo htmlspecialchars($country);
                                        echo '</p>';
                                    } else {
                                        echo '<p class="text-sm text-gray-800 whitespace-pre-line">' . htmlspecialchars($shippingAddr) . '</p>';
                                    }
                                else: ?>
                                <p class="text-sm text-gray-400">No shipping address available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Orders -->
                <div class="admin-card">
                    <h2 class="text-xl font-bold mb-4">Orders (<?php echo count($orders); ?>)</h2>
                    
                    <?php if (empty($orders)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-shopping-bag text-4xl mb-3 text-gray-300"></i>
                        <p>No orders found</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Order #</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Date</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Items</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                                    <th class="text-right py-3 px-4 font-semibold text-gray-700">Total</th>
                                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $orderItem): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-4 px-4">
                                        <a href="<?php echo $baseUrl; ?>/admin/orders/list.php?search=<?php echo urlencode($orderItem['order_number']); ?>" 
                                           class="text-primary hover:underline font-semibold">
                                            #<?php echo htmlspecialchars($orderItem['order_number']); ?>
                                        </a>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-sm text-gray-800"><?php echo date('M d, Y', strtotime($orderItem['created_at'])); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($orderItem['created_at'])); ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-sm text-gray-800"><?php echo $orderItem['item_count']; ?> item(s)</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'shipped' => 'bg-purple-100 text-purple-800',
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800'
                                            ];
                                            echo $statusColors[$orderItem['order_status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $orderItem['order_status'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <span class="font-semibold text-gray-800"><?php echo format_price($orderItem['total_amount'], $orderItem['currency'] ?? 'INR'); ?></span>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <a href="<?php echo $baseUrl; ?>/admin/orders/list.php?search=<?php echo urlencode($orderItem['order_number']); ?>" 
                                           class="admin-btn bg-blue-500 text-white text-sm px-3 py-1">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

