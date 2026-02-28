<?php
ob_start();
define('IS_ACCOUNT_PAGE', true);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Wishlist.php';

$auth = new CustomerAuth();
$isLoggedIn = $auth->isLoggedIn();
$customer = null;

if ($isLoggedIn) {
    $customer = $auth->getCurrentCustomer();
    if (!$customer) {
        // Session is valid but customer record not found for this store
        $auth->logout();
        $isLoggedIn = false;
    }
}

$orderModel = new Order();
$wishlistModel = new Wishlist();

// Handle POST actions
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
$addresses = [];
$wishlistItems = [];
$paymentOrders = [];
$totalSpend = 0;

if ($isLoggedIn) {
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
        
        // Fetch cancellation/refund requests for these orders
        $db = Database::getInstance();
        $orderIds = array_column($orders, 'id');
        $requests = [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            // Sort by ID DESC so that later requests for the same order overwrite earlier ones in the associative array
            $reqData = $db->fetchAll("SELECT order_id, type, cancel_status FROM ordercancel WHERE order_id IN ($placeholders) AND customer_id = ? ORDER BY id ASC", [...$orderIds, $customer['customer_id']]);
            foreach ($reqData as $rd) {
                $requests[$rd['order_id']] = $rd;
            }
        }
        
        // Get full items for each order
        foreach ($orders as &$o) {
            $o['items'] = $orderModel->getOrderItems($o['order_number']);
            $o['request'] = $requests[$o['id']] ?? null;
        }
    }

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

    if ($section === 'wishlist') {
        $wishlistItems = $wishlistModel->getItems($customer['customer_id'], CURRENT_STORE_ID);
    }

    if ($section === 'payments') {
        $paymentOrders = $orderModel->getAll([
            'user_id' => $customer['customer_id'],
            'store_id' => CURRENT_STORE_ID
        ]);
    }

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
}


$pageTitle = 'Your Account';
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || 
          (isset($_POST['ajax']) && $_POST['ajax'] == '1') || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Handle Auth POSTs early to prevent "headers already sent"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLoggedIn) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // Check if it's likely one of our auth forms being submitted to this page
    $isLoginSub = isset($_POST['email']) && isset($_POST['password']);
    $isRegSub = isset($_POST['email']) && isset($_POST['password']) && isset($_POST['name']);
    $isForgotSub = isset($_POST['email']) && !isset($_POST['password']);

    if ($isLoginSub || $isRegSub || $isForgotSub || $isAjax) {
        $initialAuth = 'login';
        if (strpos($requestUri, 'register') !== false || isset($_GET['register']) || isset($_GET['signup'])) $initialAuth = 'register';
        elseif (strpos($requestUri, 'forgot-password') !== false || isset($_GET['forgot-password'])) $initialAuth = 'forgot-password';
        
        // Use output buffering to catch any HTML from the included file
        // so it doesn't send headers before we're ready
        ob_start();
        include __DIR__ . '/' . $initialAuth . '.php';
        
        if ($isAjax) {
            ob_end_flush();
            exit;
        } else {
            // Check if login was successful (it would have redirected/exited)
            // If we're here, it failed. Discard the form HTML from the buffer
            // so we can render the full page wrapper properly below.
            ob_end_clean();
        }
    }
}



if (!$isAjax) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="pt-8 md:pt-24 pb-8 md:pb-20 bg-gray-50 flex flex-col items-center">';
    echo '<div class="container mx-auto px-4 ' . ($isLoggedIn ? 'max-w-8xl' : 'max-w-md') . '">';
}
?>
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 20px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 20px;
        border: 3px solid #f8fafc;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }
    /* Firefox */
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #e2e8f0 #f8fafc;
    }
</style>

<?php if ($isLoggedIn): ?>

    <?php if (!$isAjax): ?>
        <div class="flex flex-col lg:flex-row gap-8 w-full items-start relative">
            <!-- Sidebar -->
            <div class="w-full lg:w-72 flex-shrink-0 lg:sticky lg:top-28 self-start z-10">
                <!-- Header -->
                <div class="mb-10">
                    <h1 class="text-4xl font-bold text-gray-900">Your Account</h1>
                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($customer['name'] ?? ''); ?></p>
                </div>
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
                        <div class="space-y-6 h-[800px] overflow-y-auto pr-4 custom-scrollbar">
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

                                            <div>
                                                <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mb-2">Invoice</p>
                                                <div class="flex flex-row gap-2">
                                                    <a href="<?php echo url('invoice.php?order_number=' . $order['order_number']); ?>" target="_blank" class="inline-block bg-red-500 text-white px-4 py-1.5 rounded text-sm font-bold hover:bg-red-600 transition text-center">
                                                        Download
                                                    </a>
                                                    <?php 
                                                    $req = $order['request'] ?? null;
                                                    $reqStatus = $req['cancel_status'] ?? null;
                                                    
                                                    // Show request status if it's pending or approved
                                                    // If it's rejected, we show the status AND allow retrying if status permits
                                                    if ($req && ($reqStatus === 'pending' || $reqStatus === 'approved')): 
                                                        $labelClass = $reqStatus === 'approved' ? 'bg-green-100 text-green-600 border-green-200' : 'bg-gray-100 text-gray-500';
                                                        $labelText = $reqStatus === 'approved' ? ucfirst($req['type'] ?? 'Request') . ' Approved' : 'Processing...';
                                                        $icon = $reqStatus === 'approved' ? 'fa-check-circle' : 'fa-clock animate-pulse';
                                                    ?>
                                                        <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded text-sm font-bold border <?php echo $labelClass; ?>">
                                                            <i class="fas <?php echo $icon; ?> text-[10px]"></i>
                                                            <?php echo $labelText; ?>
                                                        </span>
                                                    <?php else: ?>

                                                        <?php if (in_array($order['order_status'], ['pending', 'processing', 'on_hold'])): ?>
                                                            <button onclick="openCancelModal('<?php echo $order['order_number']; ?>', 'cancel')" class="inline-block border border-red-500 text-red-500 px-4 py-1.5 rounded text-sm font-bold hover:bg-red-50 transition whitespace-nowrap">
                                                                Cancel Order
                                                            </button>
                                                        <?php elseif ($order['order_status'] === 'delivered'): 
                                                            $deliveryDate = !empty($order['delivery_date']) ? strtotime($order['delivery_date']) : (!empty($order['updated_at']) ? strtotime($order['updated_at']) : null);
                                                            if ($deliveryDate && (time() - $deliveryDate) <= (7 * 24 * 60 * 60)):
                                                        ?>
                                                            <button onclick="openCancelModal('<?php echo $order['order_number']; ?>', 'refund')" class="inline-block border border-gray-800 text-gray-800 px-4 py-1.5 rounded text-sm font-bold hover:bg-gray-100 transition whitespace-nowrap">
                                                                Return / Refund
                                                            </button>
                                                        <?php endif; endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Order Items -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-2xl">
                                                    <a href="<?php echo url('product?slug=' . ($item['product_slug'] ?? '')); ?>" class="w-20 h-24 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 block">
                                                        <?php if (!empty($item['product_image'])): ?>
                                                            <img src="<?php echo getProductImage(['featured_image'=>$item['product_image']]); ?>" alt="" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/150x150?text=Product+Image'">
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
                        // Clean URL immediately
                        const url = new URL(window.location);
                        url.searchParams.delete('success');
                        window.history.replaceState({}, '', url);
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
                                        <img src="<?php echo getProductImage($product); ?>" alt="" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
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
                </div> <!-- End flex group -->
                <?php endif; ?>
<?php else: ?>
<?php if (!$isAjax): ?><div id="authContainer" class="w-full"><?php endif; ?>
                <?php 
                // Determine which form to show based on URL or query params
                $initialAuth = 'login';
                $requestUri = $_SERVER['REQUEST_URI'];
                $isRegister = strpos($requestUri, 'register') !== false || isset($_GET['register']) || isset($_GET['signup']);
                $isForgot = strpos($requestUri, 'forgot-password') !== false || isset($_GET['forgot-password']);

                if ($isRegister) $initialAuth = 'register';
                elseif ($isForgot) $initialAuth = 'forgot-password';
                
                // Set ajax flag so the included file doesn't render header/footer
                // Set method to GET so inclusion only renders the view, not re-process the POST
                $origMethod = $_SERVER['REQUEST_METHOD'];
                $_SERVER['REQUEST_METHOD'] = 'GET';
                include __DIR__ . '/' . $initialAuth . '.php'; 
                $_SERVER['REQUEST_METHOD'] = $origMethod;
                ?>
            <?php if (!$isAjax): ?></div><?php endif; ?>
        <?php endif; ?>

<?php if (!$isAjax): ?>
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
        const url = window.location.href;
        if (document.getElementById('accountContent')) {
            loadAccountSection(url);
        } else if (document.getElementById('authContainer')) {
            loadAuthForm(url, false); // Don't update URL again
        }
    });


    // Auth Loading and Switching
    const authContainer = document.getElementById('authContainer');
    if (authContainer) {
        // Initialize components inside auth container (like Google Sign-In)
        function initAuthComponents(container) {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                const gLoad = container.querySelector('#g_id_onload');
                if (gLoad) {
                    window.google.accounts.id.initialize({
                        client_id: gLoad.dataset.client_id,
                        context: gLoad.dataset.context,
                        ux_mode: gLoad.dataset.ux_mode,
                        login_uri: gLoad.dataset.login_uri,
                        auto_prompt: gLoad.dataset.auto_prompt === 'true'
                    });
                }
                const gBtn = container.querySelector('.g_id_signin');
                if (gBtn) {
                    window.google.accounts.id.renderButton(gBtn, {
                        theme: gBtn.dataset.theme,
                        size: gBtn.dataset.size,
                        width: gBtn.dataset.width
                    });
                }
            }
        }

        async function loadAuthForm(path, updateUrl = true) {
            authContainer.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20">
                    <i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Loading...</p>
                </div>
            `;
            
            if (updateUrl && path !== window.location.href) {
                window.history.pushState({ path: path }, '', path);
            }

            const fetchUrl = path + (path.includes('?') ? '&' : '?') + 'ajax=1';

            try {
                const res = await fetch(fetchUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await res.text();
                authContainer.innerHTML = html;
                
                // Re-initialize components
                initAuthComponents(authContainer);

            } catch (e) {
                console.error(e);
                authContainer.innerHTML = '<p class="text-center text-red-500 py-10">Error loading. Please refresh.</p>';
            }
        }



        // Event Delegation for Clicks (Switching Forms)
        authContainer.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link) {
                const href = link.getAttribute('href');
                if (href && (href.includes('login') || href.includes('register') || href.includes('forgot-password'))) {
                    e.preventDefault();
                    loadAuthForm(href);
                }
            }
        });

        // Event Delegation for Form Submissions
        authContainer.addEventListener('submit', async (e) => {
            const form = e.target.closest('form');
            if (!form) return;
            
            // Skip if it's not an auth form handler (e.g. if we add other forms later)
            if (form.id === 'accountSupportForm') return; 

            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

            const formData = new FormData(form);
            formData.append('ajax', '1');
            const baseUrl = form.getAttribute('action') || window.location.pathname;
            const action = baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'ajax=1';

            try {
                const submitRes = await fetch(action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await submitRes.json();
                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        // If it's forgot-password, just reload the form to show next step
                        if (window.location.pathname.includes('forgot-password') || (form.action && form.action.includes('forgot-password'))) {
                            loadAuthForm('forgot-password');
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    // Show error
                    let errDiv = authContainer.querySelector('.bg-red-50');
                    if (!errDiv) {
                        errDiv = document.createElement('div');
                        errDiv.className = 'bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center';
                        const h1 = authContainer.querySelector('h1');
                        if (h1 && h1.parentElement) h1.parentElement.insertAdjacentElement('afterend', errDiv);
                    }
                    errDiv.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i> ${data.message}`;
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    errDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } catch (err) {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });

        // Initialize the components for the server-side rendered form
        initAuthComponents(authContainer);
    }

    // Shared Auth Helpers

    window.togglePassword = function(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    };

    window.submitBack = function() {
        const form = document.getElementById('backForm');
        if(form) {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    };

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


<!-- Cancel Order Modals -->
<div id="cancelReasonModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 relative animate-[fadeIn_0.2s_ease-out]">
        <button onclick="closeCancelModals()" class="absolute top-4 right-4 text-gray-400 hover:text-black">
            <i class="fas fa-times"></i>
        </button>
        
        <h3 class="text-xl font-bold mb-4">Why do you want to cancel?</h3>
        <p class="text-gray-500 text-sm mb-6">Please select a reason for cancellation.</p>
        
        <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2" id="reasonList">
            <!-- Populated by JS -->
        </div>
    </div>
</div>

<div id="cancelOtherModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 relative animate-[fadeIn_0.2s_ease-out]">
        <button onclick="openCancelModal(currentCancelOrderNum)" class="absolute top-4 left-4 text-gray-400 hover:text-black">
            <i class="fas fa-arrow-left"></i>
        </button>
        <button onclick="closeCancelModals()" class="absolute top-4 right-4 text-gray-400 hover:text-black">
            <i class="fas fa-times"></i>
        </button>
        
        <h3 class="text-xl font-bold mb-4">Other Reason</h3>
        <p class="text-gray-500 text-sm mb-4">Please specify the reason for cancellation.</p>
        
        <form id="otherReasonForm">
            <textarea id="customReason" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-black mb-4" rows="4" placeholder="Type your reason here..." required></textarea>
            <button type="submit" class="w-full bg-black text-white py-3 rounded-lg font-bold hover:bg-gray-800">Submit Request</button>
        </form>
    </div>
</div>

<div id="cancelConfirmModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 relative animate-[fadeIn_0.2s_ease-out]">
        <button onclick="openCancelModal(currentCancelOrderNum)" class="absolute top-4 left-4 text-gray-400 hover:text-black">
            <i class="fas fa-arrow-left"></i>
        </button>
        <button onclick="closeCancelModals()" class="absolute top-4 right-4 text-gray-400 hover:text-black">
            <i class="fas fa-times"></i>
        </button>
        
        <h3 class="text-xl font-bold mb-4">Confirm Cancellation</h3>
        <p class="text-gray-600 mb-2">Are you sure you want to cancel this order?</p>
        <p class="text-gray-800 font-medium bg-gray-50 p-3 rounded mb-6">Reason: <span id="confirmReasonText"></span></p>
        
        <div class="grid grid-cols-2 gap-4">
            <button onclick="closeCancelModals()" class="w-full border border-gray-300 py-3 rounded-lg font-bold hover:bg-gray-50">No, Keep Order</button>
            <button onclick="submitCancellation()" class="w-full bg-black text-white py-3 rounded-lg font-bold hover:bg-gray-800">Yes, Cancel</button>
        </div>
    </div>
</div>

<script>
    const cancelReasons = [
        "Duplicate Order",
        "Ordered by Mistake",
        "Found Better Price",
        "Items Not Arriving on Time",
        "Shipping Cost Too High",
        "Change of Mind",
        "Forgot to Use Coupon",
        "Wrong Shipping Address",
        "Product Description Was Unclear",
        "Need to Change Payment Method",
        "Decided to Buy Different Product",
        "Wait Time Is Too Long",
        "Unexpected Financial Issue",
        "Preferred Brand Not Available",
        "Item Out of Stock Elsewhere",
        "Discovered Hidden Fees",
        "Order Process Was Too Complex",
        "Selected Wrong Variant/Color",
        "Other"
    ];

    const refundReasons = [
        "Product Damaged on Arrival",
        "Wrong Item Sent",
        "Item Defective / Not Working",
        "Arrived Later Than Promised",
        "Better Price Found Elsewhere",
        "Quality Did Not Meet Expectations",
        "Missing Parts or Accessories",
        "Received Extra Item by Mistake",
        "Product Different From Images",
        "Size/Fit Issues",
        "Changed Mind After Delivery",
        "Parcel Refused Due to Damage",
        "Incomplete Set Received",
        "Compatibility Issues",
        "Instruction Manual Missing",
        "Item Performance Inconsistent",
        "Allergic Reaction/Sensitivity",
        "Gift No Longer Needed",
        "Other"
    ];
    
    let currentCancelOrderNum = null;
    let currentType = 'cancel'; 
    let selectedReason = null;
    let selectedComments = '';
    
    function openCancelModal(orderNum, type = 'cancel') {
        currentCancelOrderNum = orderNum;
        currentType = type;
        closeCancelModals();
        
        const modalTitle = document.querySelector('#cancelReasonModal h3');
        const modalDesc = document.querySelector('#cancelReasonModal p');
        
        if (type === 'refund') {
            modalTitle.textContent = "Why do you want to return/refund?";
            modalDesc.textContent = "Please select a reason for your return request.";
        } else {
            modalTitle.textContent = "Why do you want to cancel?";
            modalDesc.textContent = "Please select a reason for cancellation.";
        }
        
        const reasons = type === 'refund' ? refundReasons : cancelReasons;
        const list = document.getElementById('reasonList');
        list.innerHTML = reasons.map(r => `
            <button onclick="selectReason('${r}')" class="w-full text-left px-4 py-3 rounded-lg border border-gray-200 hover:border-black hover:bg-gray-50 transition flex justify-between items-center group">
                <span class="font-medium text-gray-700 group-hover:text-black">${r}</span>
                <i class="fas fa-chevron-right text-gray-300 group-hover:text-black"></i>
            </button>
        `).join('');
        
        document.getElementById('cancelReasonModal').classList.replace('hidden', 'flex');
    }
    
    function selectReason(reason) {
        selectedReason = reason;
        document.getElementById('cancelReasonModal').classList.replace('flex', 'hidden');
        
        if (reason === 'Other') {
            document.getElementById('cancelOtherModal').classList.replace('hidden', 'flex');
        } else {
            showConfirmation(reason);
        }
    }

    function showConfirmation(displayReason) {
        const confirmTitle = document.querySelector('#cancelConfirmModal h3');
        const confirmDesc = document.querySelector('#cancelConfirmModal p');
        const confirmBtn = document.querySelector('#cancelConfirmModal button.bg-black');
        
        if (currentType === 'refund') {
            confirmTitle.textContent = "Confirm Return Request";
            confirmDesc.textContent = "Are you sure you want to request a return/refund for this order?";
            confirmBtn.textContent = "Submit Request";
        } else {
            confirmTitle.textContent = "Confirm Cancellation";
            confirmDesc.textContent = "Are you sure you want to cancel this order?";
            confirmBtn.textContent = "Yes, Cancel";
        }

        document.getElementById('confirmReasonText').textContent = displayReason;
        document.getElementById('cancelConfirmModal').classList.replace('hidden', 'flex');
    }
    
    function closeCancelModals() {
        document.getElementById('cancelReasonModal').classList.replace('flex', 'hidden');
        document.getElementById('cancelOtherModal').classList.replace('flex', 'hidden');
        document.getElementById('cancelConfirmModal').classList.replace('flex', 'hidden');
    }
    
    document.getElementById('otherReasonForm').addEventListener('submit', (e) => {
        e.preventDefault();
        selectedComments = document.getElementById('customReason').value;
        document.getElementById('cancelOtherModal').classList.replace('flex', 'hidden');
        showConfirmation('Other: ' + selectedComments);
    });
    
    async function submitCancellation() {
        if (!currentCancelOrderNum || !selectedReason) return;
        
        const confirmBtn = document.querySelector('#cancelConfirmModal button.bg-black');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        confirmBtn.disabled = true;
        
        try {
            const res = await fetch('<?php echo $baseUrl; ?>/api/cancel_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: currentCancelOrderNum,
                    reason: selectedReason,
                    comments: selectedComments,
                    type: currentType
                })
            });
            
            const data = await res.json();
            
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to process request.');
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            alert('An error occurred. Please try again.');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
<?php 
if (ob_get_level() > 0) ob_end_flush(); 
?>
