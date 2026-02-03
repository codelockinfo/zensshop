<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Wishlist.php';

$auth = new CustomerAuth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

$customer = $auth->getCurrentCustomer();
if (!$customer) {
    // Session is valid but customer record not found for this store
    $auth->logout();
    header('Location: ' . url('login'));
    exit;
}
$orderModel = new Order();
$wishlistModel = new Wishlist();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'remove_address') {
        $auth->updateProfile(['shipping_address' => null]);
    } elseif ($_POST['action'] === 'set_address') {
        $auth->updateProfile(['shipping_address' => $_POST['address']]);
    } elseif ($_POST['action'] === 'update_details') {
        $updateData = [
            'name' => $_POST['name'],
            'phone' => $_POST['phone']
        ];
        // Only allow email update if it's not a google login (optional, but good practice). 
        // For now, let's allow it as requested.
        if (!empty($_POST['email'])) {
            $updateData['email'] = $_POST['email'];
        }
        $auth->updateProfile($updateData);
        header('Location: ?section=details&success=1');
        exit;
    } elseif ($_POST['action'] === 'remove_wishlist') {
        $productId = $_POST['product_id'] ?? null;
        if ($productId) {
            $wishlistModel->removeItem($productId);
        }
        header('Location: ?section=wishlist');
        exit;
    }
    header('Location: ?section=addresses');
    exit;
}
// Remove success param on refresh/load so it doesn't persist
if (isset($_GET['success'])) {
    // We'll handle this with JS to clean the URL
}

$section = $_GET['section'] ?? 'orders';
$tab = $_GET['tab'] ?? 'all'; // For orders: current, unpaid, all

// Fetch data based on section
$orders = [];
if ($section === 'orders') {
    $filters = [
        'user_id' => $customer['customer_id'],
        'store_id' => CURRENT_STORE_ID
    ];
    if ($tab === 'unpaid') {
        $filters['payment_status'] = 'pending';
    } elseif ($tab === 'current') {
        // Current could mean pending/processing/shipped
        // For simplicity, just show all for now or filter by status
    }
    $orders = $orderModel->getAll($filters);
    
    // Get full items for each order
    foreach ($orders as &$o) {
        $o['items'] = $orderModel->getOrderItems($o['order_number']);
    }
}

$addresses = [];
if ($section === 'addresses') {
    // 1. Get unique shipping addresses from past orders (Store Specific)
    $pastOrders = $orderModel->getAll([
        'user_id' => $customer['customer_id'],
        'store_id' => CURRENT_STORE_ID
    ]);
    $uniqueAddresses = [];
    foreach ($pastOrders as $po) {
        $addr = $po['shipping_address'];
        if ($addr) {
            $hash = md5($addr);
            if (!isset($uniqueAddresses[$hash])) {
                $uniqueAddresses[$hash] = $addr;
            }
        }
    }
    $orderAddresses = $uniqueAddresses;
    
    // 2. Current saved address
    $savedAddress = $customer['shipping_address'] ?? null;
}

$wishlistItems = [];
if ($section === 'wishlist') {
    $wishlistItems = $wishlistModel->getItems($customer['customer_id'], CURRENT_STORE_ID);
}

$paymentOrders = [];
if ($section === 'payments') {
    $paymentOrders = $orderModel->getAll([
        'user_id' => $customer['customer_id'],
        'store_id' => CURRENT_STORE_ID
    ]);
}

$totalSpend = 0;
if ($section === 'details') {
    // Calculate total spend (Store Specific)
    $allOrders = $orderModel->getAll([
        'user_id' => $customer['customer_id'],
        'store_id' => CURRENT_STORE_ID
    ]);
    foreach ($allOrders as $ord) {
        if ($ord['payment_status'] === 'paid' && $ord['order_status'] !== 'cancelled') {
            $totalSpend += $ord['total_amount'];
        }
    }
    
    // If phone is missing, try to get from last order to pre-fill
    if (empty($customer['phone'])) {
        $lastOrder = $orderModel->getAll([
            'user_id' => $customer['customer_id'], 
            'store_id' => CURRENT_STORE_ID,
            'limit' => 1
        ]);
        if (!empty($lastOrder[0]['customer_phone'])) {
            $customer['phone'] = $lastOrder[0]['customer_phone'];
        }
    }
}

$pageTitle = 'Your Account';
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if (!$isAjax) {
    require_once __DIR__ . '/includes/header.php';
}
?>

<?php if (!$isAjax): ?>
<div class="min-h-screen pt-32 pb-20 bg-gray-50">
    <div class="container mx-auto px-4 max-w-6xl">
        <!-- Header -->
        <div class="mb-10">
            <h1 class="text-4xl font-bold text-gray-900">Your Account</h1>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($customer['name'] ?? ''); ?></p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full lg:w-72 flex-shrink-0">
                <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-2 space-y-1">
                        <a href="?section=orders" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'orders' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-truck text-sm"></i>
                            </div>
                            <span class="font-semibold">My orders</span>
                        </a>
                        <a href="?section=details" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'details' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <span class="font-semibold">My Details</span>
                        </a>
                        <a href="?section=addresses" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'addresses' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-sm"></i>
                            </div>
                            <span class="font-semibold">Your addresses</span>
                        </a>
                        <!-- <a href="?section=security" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'security' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-lock text-sm"></i>
                            </div>
                            <span class="font-semibold">Login & security</span>
                        </a> -->
                        <a href="?section=payments" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'payments' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-credit-card text-sm"></i>
                            </div>
                            <span class="font-semibold">Payments</span>
                        </a>
                        <a href="?section=wishlist" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'wishlist' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-heart text-sm"></i>
                            </div>
                            <span class="font-semibold">Saved items</span>
                        </a>
                        <a href="?section=support" 
                           class="account-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $section === 'support' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-comment-dots text-sm"></i>
                            </div>
                            <span class="font-semibold">Customer support</span>
                        </a>
                        <div class="pt-2 mt-2 border-t border-gray-100">
                            <a href="logout" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-500 hover:bg-red-50 transition">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-sign-out-alt text-sm"></i>
                                </div>
                                <span class="font-semibold">Log out</span>
                            </a>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Content Area -->
             <div class="flex-1" id="accountContent">
<?php endif; ?>
                <?php if ($section === 'orders'): ?>
                    <!-- Orders Section -->
                    <div class="mb-8 p-1 bg-gray-200 rounded-xl flex flex-wrap md:inline-flex w-full md:w-auto">
                        <a href="?section=orders&tab=current" class="flex-1 text-center md:flex-none px-4 md:px-8 py-2 rounded-lg font-semibold transition text-sm md:text-base <?php echo $tab === 'current' ? 'bg-white shadow-sm' : 'text-gray-600 hover:text-black'; ?>">Current</a>
                        <a href="?section=orders&tab=unpaid" class="flex-1 text-center md:flex-none px-4 md:px-8 py-2 rounded-lg font-semibold transition text-sm md:text-base <?php echo $tab === 'unpaid' ? 'bg-white shadow-sm' : 'text-gray-600 hover:text-black'; ?>">Unpaid</a>
                        <a href="?section=orders&tab=all" class="flex-1 text-center md:flex-none px-4 md:px-8 py-2 rounded-lg font-semibold transition text-sm md:text-base <?php echo $tab === 'all' || $tab === '' ? 'bg-white shadow-sm' : 'text-gray-600 hover:text-black'; ?>">All orders</a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                            <i class="fas fa-box-open text-5xl text-gray-200 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-900">No orders yet</h3>
                            <p class="text-gray-500 mt-2">When you shop, your orders will appear here.</p>
                            <a href="<?php echo url('shop'); ?>" class="inline-block mt-6 bg-black text-white px-8 py-3 rounded-xl font-bold hover:bg-gray-900 transition">Start Shopping</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($orders as $order): ?>
                                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                                    <div class="p-6 md:p-8">
                                        <div class="flex flex-wrap justify-between items-start gap-4 mb-8">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900">Order #: <?php echo htmlspecialchars($order['order_number']); ?></h3>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <?php echo count($order['items']); ?> Products | By <?php echo htmlspecialchars($order['customer_name']); ?> | <?php echo date('H:i, M d, Y', strtotime($order['created_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-block px-4 py-1.5 rounded-full text-sm font-bold bg-orange-50 text-orange-500">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8 text-sm">
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Status</p>
                                                <p class="font-bold text-orange-500"><?php echo ucfirst($order['order_status']); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Date of delivery</p>
                                                <p class="font-bold text-gray-900"><?php echo ($order['delivery_date'] ?? null) ? date('D, d M, Y', strtotime($order['delivery_date'])) : 'Pending'; ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Delivered to</p>
                                                <p class="font-bold text-gray-900">
                                                    <?php 
                                                        $addr = json_decode($order['shipping_address'], true);
                                                        if (is_array($addr)) {
                                                            $addressText = $addr['street'] ?? $addr['address'] ?? '';
                                                            $city = $addr['city'] ?? '';
                                                            $state = $addr['state'] ?? '';
                                                            $zip = $addr['zip'] ?? $addr['postal_code'] ?? '';
                                                            
                                                            $parts = array_filter([$addressText, $city, $state, $zip]);
                                                            echo htmlspecialchars(implode(', ', $parts));
                                                        } else {
                                                            echo htmlspecialchars($order['shipping_address']);
                                                        }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-8 mb-8">
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Total</p>
                                                <p class="text-2xl font-bold text-gray-900"><?php echo format_price($order['total_amount'], $order['currency'] ?? 'INR'); ?></p>
                                            </div>
                                            <?php if (!empty($order['payment_method'])): ?>
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Payment Method</p>
                                                <p class="font-bold text-gray-900 capitalize"><?php echo htmlspecialchars($order['payment_method']); ?></p>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($order['payment_status'])): ?>
                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Payment Status</p>
                                                <p class="font-bold <?php echo ($order['payment_status'] === 'paid' || $order['payment_status'] === 'Success') ? 'text-green-600' : 'text-orange-500'; ?> capitalize">
                                                    <?php echo htmlspecialchars($order['payment_status']); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Order Items -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-2xl">
                                                    <a href="<?php echo url('product?slug=' . ($item['product_slug'] ?? '')); ?>" class="w-20 h-24 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 block">
                                                        <?php if (!empty($item['product_image'])): ?>
                                                            <img src="<?php echo getProductImage(['featured_image'=>$item['product_image']]); ?>" alt="" class="w-full h-full object-cover">
                                                        <?php endif; ?>
                                                    </a>
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="font-bold text-gray-900 truncate hover:text-blue-600 transition">
                                                            <a href="<?php echo url('product?slug=' . ($item['product_slug'] ?? '')); ?>">
                                                                <?php echo htmlspecialchars($item['product_name'] ?? ''); ?>
                                                            </a>
                                                        </h4>
                                                        <p class="text-sm text-gray-500 mt-1">Quantity: <?php echo $item['quantity']; ?>x</p>
                                                        <p class="text-sm text-gray-500 mt-1">Price: <?php echo format_price($item['price'] * $item['quantity'], $item['currency'] ?? 'INR'); ?></p>
                                                        <?php 
                                                        $variantAttributes = !empty($item['variant_attributes']) ? (is_array($item['variant_attributes']) ? $item['variant_attributes'] : json_decode($item['variant_attributes'], true)) : [];
                                                        if (!empty($variantAttributes) && is_array($variantAttributes)): 
                                                        ?>
                                                            <div class="mt-1 flex flex-wrap gap-2">
                                                                <?php foreach ($variantAttributes as $key => $value): ?>
                                                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded border border-gray-200">
                                                                        <?php echo htmlspecialchars($key); ?>: <span class="font-medium text-gray-800"><?php echo htmlspecialchars($value); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($section === 'addresses'): ?>
                    <!-- Addresses Section -->
                    <h2 class="text-2xl font-bold mb-6">Your addresses</h2>
                    
                    <!-- Saved Address -->
                    <?php if ($savedAddress): ?>
                        <div class="mb-10">
                            <h3 class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-4">Default Shipping Address</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-white p-6 rounded-2xl border-2 border-blue-500 shadow-sm relative group">
                                    <div class="absolute top-6 right-6 text-blue-500">
                                        <i class="fas fa-check-circle text-xl"></i>
                                    </div>
                                    <p class="text-gray-600 leading-relaxed mb-4">
                                        <?php 
                                            $addr = json_decode($savedAddress, true);
                                            if (is_array($addr)) {
                                                // Name
                                                $name = trim(($addr['first_name']??'') . ' ' . ($addr['last_name']??''));
                                                if ($name) echo htmlspecialchars($name) . '<br>';
                                                
                                                // Street
                                                $street = $addr['street'] ?? $addr['address'] ?? $addr['address_line1'] ?? '';
                                                if (!empty($addr['address_line2'])) $street .= ', ' . $addr['address_line2'];
                                                if ($street) echo htmlspecialchars($street) . '<br>';
                                                
                                                // City, State, Zip
                                                $parts = [];
                                                if (!empty($addr['city'])) $parts[] = $addr['city'];
                                                if (!empty($addr['state'])) $parts[] = $addr['state'];
                                                if (!empty($addr['zip'])) $parts[] = $addr['zip'];
                                                elseif (!empty($addr['postal_code'])) $parts[] = $addr['postal_code'];
                                                
                                                if (!empty($parts)) echo htmlspecialchars(implode(', ', $parts));

                                                // Country
                                                if (!empty($addr['country'])) echo '<br>' . htmlspecialchars($addr['country']);
                                            } else {
                                                echo nl2br(htmlspecialchars($savedAddress));
                                            }
                                        ?>
                                    </p>
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="remove_address">
                                        <button type="submit" class="text-red-500 text-sm font-medium hover:underline flex items-center gap-2">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                            Remove default address
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Addresses -->
                    <h3 class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-4"><?php echo $savedAddress ? 'Other Recent Addresses' : 'Recent Addresses from Orders'; ?></h3>
                    <?php 
                    $hasOtherAddresses = false;
                    foreach ($orderAddresses as $hash => $addrStr) {
                        if (!$savedAddress || md5($savedAddress) !== $hash) {
                            $hasOtherAddresses = true;
                            break;
                        }
                    }
                    ?>

                    <?php if (!$hasOtherAddresses && !$savedAddress): ?>
                        <div class="bg-white rounded-2xl p-8 border border-gray-100 text-center">
                            <p class="text-gray-500">No addresses found. Complete an order to save your address.</p>
                        </div>
                    <?php elseif (!$hasOtherAddresses): ?>
                        <div class="bg-gray-50 rounded-2xl p-8 border border-dashed border-gray-200 text-center">
                            <p class="text-gray-400 text-sm">No other addresses found in your order history.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($orderAddresses as $hash => $addrStr): 
                                if ($savedAddress && md5($savedAddress) === $hash) continue;
                            ?>
                                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm relative group hover:border-gray-200 transition">
                                    <p class="text-gray-600 leading-relaxed mb-4">
                                        <?php 
                                            $addr = json_decode($addrStr, true);
                                            if (is_array($addr)) {
                                                // Name
                                                $name = trim(($addr['first_name']??'') . ' ' . ($addr['last_name']??''));
                                                if ($name) echo htmlspecialchars($name) . '<br>';
                                                
                                                // Street
                                                $street = $addr['street'] ?? $addr['address'] ?? $addr['address_line1'] ?? '';
                                                if (!empty($addr['address_line2'])) $street .= ', ' . $addr['address_line2'];
                                                if ($street) echo htmlspecialchars($street) . '<br>';
                                                
                                                // City, State, Zip
                                                $parts = [];
                                                if (!empty($addr['city'])) $parts[] = $addr['city'];
                                                if (!empty($addr['state'])) $parts[] = $addr['state'];
                                                if (!empty($addr['zip'])) $parts[] = $addr['zip'];
                                                elseif (!empty($addr['postal_code'])) $parts[] = $addr['postal_code'];
                                                
                                                if (!empty($parts)) echo htmlspecialchars(implode(', ', $parts));

                                                // Country
                                                if (!empty($addr['country'])) echo '<br>' . htmlspecialchars($addr['country']);
                                            } else {
                                                echo nl2br(htmlspecialchars($addrStr));
                                            }
                                        ?>
                                    </p>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_address">
                                        <input type="hidden" name="address" value='<?php echo $addrStr; ?>'>
                                        <button type="submit" class="text-blue-600 text-sm font-medium hover:underline flex items-center gap-2">
                                            <i class="fas fa-thumbtack text-xs"></i>
                                            Set as default
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($section === 'details'): ?>
                    <!-- Customer Details Section -->
                    <h2 class="text-2xl font-bold mb-6">My Details</h2>

                    <?php if (isset($_GET['success'])): ?>
                    <div id="successMessage" class="bg-black text-white p-4 rounded-xl mb-6 shadow-lg transition-opacity duration-500">
                        Details updated successfully!
                    </div>
                    <script>
                        setTimeout(function() {
                            const msg = document.getElementById('successMessage');
                            if (msg) {
                                msg.style.opacity = '0';
                                setTimeout(() => msg.remove(), 500);
                                // Clean URL
                                const url = new URL(window.location);
                                url.searchParams.delete('success');
                                window.history.replaceState({}, '', url);
                            }
                        }, 3000);
                    </script>
                    <?php endif; ?>

                    <!-- Total Spend Card -->
                    <div class="bg-gradient-to-r from-gray-900 to-black rounded-2xl p-8 text-white mb-8 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Lifetime Spend</p>
                                <h3 class="text-4xl font-bold text-white"><?php echo format_price($totalSpend); ?></h3>
                            </div>
                            <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center">
                                <i class="fas fa-wallet text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Details Form -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-6">Personal Information</h3>
                        
                        <form method="POST" action="?section=details">
                            <input type="hidden" name="action" value="update_details">
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">Full Name</label>
                                    <input type="text" name="name" required
                                           value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">Email Address</label>
                                    <input type="email" name="email" required
                                           value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent bg-gray-50" 
                                           <?php echo !empty($customer['google_id']) ? 'readonly title="Signed in with Google"' : ''; ?>>
                                    <?php if (!empty($customer['google_id'])): ?>
                                        <p class="text-xs text-gray-500 mt-1"><i class="fab fa-google mr-1"></i> Managed by Google</p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">Mobile Number</label>
                                    <input type="tel" name="phone" placeholder="+91 99999 99999"
                                           value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent">
                                </div>

                                <div class="pt-4">
                                    <button type="submit" class="bg-black text-white px-8 py-3 rounded-lg font-bold hover:bg-gray-800 transition shadow-lg">
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                <?php elseif ($section === 'payments'): ?>
                    <!-- Payments Section -->
                    <h2 class="text-2xl font-bold mb-6">Your payment history</h2>
                    <?php if (empty($paymentOrders)): ?>
                        <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                            <i class="fas fa-credit-card text-5xl text-gray-200 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-900">No payment records</h3>
                            <p class="text-gray-500 mt-2">When you place orders, your payment details will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-400 font-bold uppercase tracking-widest text-[10px]">
                                    <tr>
                                        <th class="px-6 py-4">Order #</th>
                                        <th class="px-6 py-4">Method</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Amount</th>
                                        <th class="px-6 py-4">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($paymentOrders as $po): ?>
                                        <tr class="text-sm">
                                            <td class="px-6 py-4 font-bold text-gray-900"><?php echo htmlspecialchars($po['order_number']); ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2 text-gray-600">
                                                    <i class="fas <?php echo $po['payment_method'] === 'cash_on_delivery' ? 'fa-money-bill-wave' : 'fa-credit-card'; ?> text-xs"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $po['payment_method'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold <?php echo $po['payment_status'] === 'paid' ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600'; ?>">
                                                    <?php echo strtoupper($po['payment_status']); ?>
                                                </span>
                                            </td>
                                             <td class="px-6 py-4 font-bold text-gray-900"><?php echo format_price($po['total_amount'], $po['currency'] ?? 'INR'); ?></td>
                                            <td class="px-6 py-4 text-gray-500"><?php echo date('M d, Y', strtotime($po['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($section === 'wishlist'): ?>
                     <!-- Wishlist Section -->
                     <h2 class="text-2xl font-bold mb-6">Your Wishlist</h2>
                     <?php if (empty($wishlistItems)): ?>
                        <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                            <i class="fas fa-heart text-5xl text-gray-200 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-900">Wishlist is empty</h3>
                            <a href="<?php echo url('shop'); ?>" class="inline-block mt-6 bg-black text-white px-8 py-3 rounded-xl font-bold">Start Explroring</a>
                        </div>
                     <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($wishlistItems as $product): ?>
                                <!-- Product Card (simplified) -->
                                <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm transition hover:shadow-md relative group">
                                    <!-- Remove Button -->
                                    <form method="POST" action="?section=wishlist" class="absolute top-2 right-2 z-10">
                                        <input type="hidden" name="action" value="remove_wishlist">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" class="w-8 h-8 rounded-full bg-white/80 hover:bg-black hover:text-white flex items-center justify-center shadow-sm transition text-gray-500" title="Remove from wishlist">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>

                                    <a href="<?php echo url('product?slug='.($product['slug'] ?? '')); ?>" class="h-48 bg-gray-100 overflow-hidden block">
                                        <img src="<?php echo getProductImage($product); ?>" alt="" class="w-full h-full object-cover">
                                    </a>
                                    <div class="p-4">
                                        <h3 class="font-bold truncate hover:text-blue-600">
                                            <a href="<?php echo url('product?slug='.($product['slug'] ?? '')); ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h3>
                                        <div class="flex justify-between items-center mt-2">
                                             <span class="font-bold text-gray-900"><?php echo format_price($product['price'], $product['currency'] ?? 'INR'); ?></span>
                                            <a href="<?php echo url('product?slug='.($product['slug'] ?? '')); ?>" class="text-sm text-blue-600 font-bold">View Product</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                     <?php endif; ?>

                <?php elseif ($section === 'support'): ?>
                    <!-- Customer Support Section -->
                    <h2 class="text-2xl font-bold mb-6">Customer Support</h2>
                    
                    <div class="bg-white rounded-2xl border border-gray-100 p-8 mb-6">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-headset text-blue-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Need Help?</h3>
                                <p class="text-gray-600">Send us a message and we'll get back to you within 24 hours</p>
                            </div>
                        </div>

                        <form id="accountSupportForm" class="space-y-6">
                            <div>
                                <label for="supportSubject" class="block text-sm font-semibold mb-2 text-gray-700">Subject *</label>
                                <input type="text" 
                                       id="supportSubject" 
                                       name="subject" 
                                       required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="How can we help you?">
                            </div>

                            <div>
                                <label for="supportMessage" class="block text-sm font-semibold mb-2 text-gray-700">Message *</label>
                                <textarea id="supportMessage" 
                                          name="message" 
                                          rows="6" 
                                          required 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                          placeholder="Please describe your issue or question in detail..."></textarea>
                            </div>

                            <div id="accountSupportMessage" class="hidden"></div>

                            <button type="submit" 
                                    class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 shadow-lg">
                                <i class="fas fa-paper-plane mr-2"></i>Send Message
                            </button>
                        </form>
                    </div>

                    <!-- Quick Help -->
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-2xl p-6 border border-blue-100">
                        <h3 class="font-bold text-gray-900 mb-4">Quick Help</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="?section=orders" class="flex items-center gap-3 p-4 bg-white rounded-lg hover:shadow-md transition">
                                <i class="fas fa-truck text-blue-600"></i>
                                <div>
                                    <h4 class="font-semibold text-sm">Track Order</h4>
                                    <p class="text-xs text-gray-500">View your order status</p>
                                </div>
                            </a>
                            <a href="<?php echo url('support'); ?>" class="flex items-center gap-3 p-4 bg-white rounded-lg hover:shadow-md transition">
                                <i class="fas fa-question-circle text-blue-600"></i>
                                <div>
                                    <h4 class="font-semibold text-sm">FAQ</h4>
                                    <p class="text-xs text-gray-500">Common questions</p>
                                </div>
                            </a>
                        </div>
                    </div>



                <?php endif; ?>
            
<?php if (!$isAjax): ?>
            </div> <!-- End accountContent -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const contentDiv = document.getElementById('accountContent');
    
    // Function to handle navigation
    async function loadAccountSection(url) {
        // Show Loader
        contentDiv.innerHTML = `
            <div class="flex flex-col items-center justify-center py-20 min-h-[400px]">
                <i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Loading...</p>
            </div>
        `;
        
        // Update URL
        if(url !== window.location.href) {
            window.history.pushState(null, '', url);
        }

        const fetchUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
        
        try {
            const res = await fetch(fetchUrl);
            const html = await res.text();
            contentDiv.innerHTML = html;
            
            // Re-initialize any specific plugins if needed (like sliders)
            
        } catch(e) {
            console.error(e);
            contentDiv.innerHTML = '<p class="text-center text-red-500 py-10">Error loading content. Please refresh.</p>';
        }
    }

    // Sidebar clicking
    document.querySelectorAll('.account-sidebar-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const href = e.currentTarget.href;
            
            // Update Sidebar UI
            document.querySelectorAll('.account-sidebar-link').forEach(l => {
                // Remove active classes
                l.classList.remove('bg-blue-50', 'text-blue-600');
                l.classList.add('text-gray-600', 'hover:bg-gray-50');
                
                // Reset icon background
                const iconDiv = l.querySelector('div');
                if(iconDiv) {
                    iconDiv.classList.remove('bg-blue-100');
                    iconDiv.classList.add('bg-gray-100'); // Assuming default is gray-100 if not blue-100?
                    // Actually, looking at code: 
                    // Active: div bg-blue-100
                    // Inactive: div bg-blue-100 (Wait, they are all bg-blue-100 in the HTML?)
                    // Let's re-check the HTML.
                    /* 
                       Line 163: <div class="w-8 h-8 rounded-lg bg-blue-100 ...">
                       They seem to ALWAYS be bg-blue-100 regardless of active state in the original code?
                       Line 162: active ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'
                       So only the link background changes.
                    */
                }
            });
            
            // Add active classes to clicked
            e.currentTarget.classList.remove('text-gray-600', 'hover:bg-gray-50');
            e.currentTarget.classList.add('bg-blue-50', 'text-blue-600');
            
            loadAccountSection(href);
        });
    });
    
    // Delegation for inner tabs (Orders tabs, pagination if any)
    contentDiv.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if(link && link.href && link.href.includes('?section=')) {
             // Avoid actions like "remove address" forms which are buttons/forms, but if they are links:
             if (!link.classList.contains('no-ajax') && !link.target) {
                 e.preventDefault();
                 loadAccountSection(link.href);
             }
        }
    });

    // Handle Back/Forward
    window.addEventListener('popstate', () => {
        loadAccountSection(window.location.href);
    });

    // Support Form Handling (Event Delegation)
    document.addEventListener('submit', async (e) => {
        if (e.target && e.target.id === 'accountSupportForm') {
            e.preventDefault();
            const form = e.target;
            
            const btn = form.querySelector('button[type="submit"]');
            const origText = btn.innerHTML;
            const messageDiv = document.getElementById('accountSupportMessage');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            btn.disabled = true;
            if(messageDiv) messageDiv.classList.add('hidden');
            
            const formData = {
                name: '<?php echo htmlspecialchars($customer['name'] ?? '', ENT_QUOTES); ?>',
                email: '<?php echo htmlspecialchars($customer['email'] ?? '', ENT_QUOTES); ?>',
                subject: document.getElementById('supportSubject').value,
                message: document.getElementById('supportMessage').value
            };
            
            try {
                const response = await fetch('<?php echo $baseUrl; ?>/api/support.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (messageDiv) {
                    if (data.success) {
                        form.reset();
                        messageDiv.textContent = data.message;
                        messageDiv.className = 'p-4 bg-green-50 text-green-700 rounded-lg border border-green-200';
                        messageDiv.classList.remove('hidden');
                        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        messageDiv.textContent = data.message || 'Something went wrong. Please try again.';
                        messageDiv.className = 'p-4 bg-red-50 text-red-700 rounded-lg border border-red-200';
                        messageDiv.classList.remove('hidden');
                    }
                }
            } catch (error) {
                console.error('Support form error:', error);
                if (messageDiv) {
                    messageDiv.textContent = 'An error occurred: ' + (error.message || 'Please try again.');
                    messageDiv.className = 'p-4 bg-red-50 text-red-700 rounded-lg border border-red-200';
                    messageDiv.classList.remove('hidden');
                }
            } finally {
                btn.innerHTML = origText;
                btn.disabled = false;
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
