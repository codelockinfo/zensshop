<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Order List';
require_once __DIR__ . '/../../includes/admin-header.php';

$order = new Order();
$filters = [
    'order_status' => $_GET['status'] ?? null,
    'payment_status' => $_GET['payment'] ?? null,
    'search' => $_GET['search'] ?? ''
];
$orders = $order->getAll($filters);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Order List</h1>
    <p class="text-gray-600 text-sm md:text-base">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > Order List
    </p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
        <form method="GET" action="" class="w-full md:w-auto flex flex-col md:flex-row gap-3 items-center">
            
            <!-- Status Filter -->
            <div class="relative">
                <select name="status" onchange="this.form.submit()" 
                        class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white cursor-pointer h-10 min-w-[150px]">
                    <option value="">All Status</option>
                    <?php 
                    $statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
                    foreach($statuses as $val => $label): 
                        $selected = (isset($_GET['status']) && $_GET['status'] === $val) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $val; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Input -->
            <div class="relative">
                <input type="text" 
                       name="search"
                       placeholder="Search order..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>"
                       class="border border-gray-300 rounded px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64 h-10">
            </div>
        </form>
        <a href="<?php echo url('admin/orders/export_csv.php'); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition flex items-center">
            <i class="fas fa-file-export mr-2"></i>Export all order
        </a>
    </div>
</div>

<div class="admin-card overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Customer</th>
                <th>Order ID</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Tracking</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $item): 
                // Get product image with fallback using helper function
                $productImage = !empty($item['product_image']) ? getImageUrl($item['product_image']) : 'https://via.placeholder.com/50';
            ?>
            <tr>
                <td>
                    <img src="<?php echo htmlspecialchars($productImage); ?>" 
                         alt="Product" 
                         class="w-12 h-12 object-cover rounded"
                         onerror="this.src='https://via.placeholder.com/50'">
                </td>
                <td>
                    <span class="font-medium"><?php echo htmlspecialchars($item['customer_name']); ?></span>
                </td>
                <td><?php echo htmlspecialchars($item['order_number']); ?></td>
                <td><?php echo format_currency($item['total_amount']); ?></td>
                <td><?php echo $item['total_quantity'] ?? 0; ?></td>
                <td>
                    <?php 
                    $payStatus = $item['payment_status'] ?? 'pending';
                    $isPaid = ($payStatus === 'paid');
                    $isPayPending = ($payStatus === 'pending');
                    $payBg = $isPaid ? '#d1fae5' : ($isPayPending ? '#fef3c7' : '#fee2e2');
                    $payText = $isPaid ? '#065f46' : ($isPayPending ? '#92400e' : '#991b1b');
                    ?>
                    <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $payBg; ?>; color: <?php echo $payText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                        <?php echo ucfirst($payStatus); ?>
                    </span>
                </td>
                <td>
                    <?php 
                    $orderStatus = $item['order_status'] ?? 'pending';
                    
                    // define colors for each status
                    $statusColors = [
                        'pending'    => ['bg' => '#fef3c7', 'text' => '#92400e'], // Yellow
                        'processing' => ['bg' => '#dbeafe', 'text' => '#1e40af'], // Blue
                        'shipped'    => ['bg' => '#f3e8ff', 'text' => '#6b21a8'], // Purple
                        'delivered'  => ['bg' => '#d1fae5', 'text' => '#065f46'], // Green
                        'success'    => ['bg' => '#d1fae5', 'text' => '#065f46'], // Green (alias)
                        'cancelled'  => ['bg' => '#fee2e2', 'text' => '#991b1b'], // Red
                        'cancel'     => ['bg' => '#fee2e2', 'text' => '#991b1b'], // Red (alias)
                    ];
                    
                    $colors = $statusColors[$orderStatus] ?? ['bg' => '#f3f4f6', 'text' => '#374151']; // Default Gray
                    $selectBg = $colors['bg'];
                    $selectText = $colors['text'];
                    ?>
                    <select
                        class="order-status-select px-2 py-1 rounded border shadow-sm"
                        style="background-color: <?php echo $selectBg; ?>; color: <?php echo $selectText; ?>; font-size: 0.75rem; font-weight: 600;"
                        data-order-id="<?php echo $item['id']; ?>"
                    >
                        <?php
                        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                        foreach ($statuses as $status) {
                            $selected = ($orderStatus === $status) ? 'selected' : '';
                            echo "<option value='{$status}' {$selected}>" . ucfirst($status) . "</option>";
                        }
                        ?>
                    </select>
                </td>

                <td>
                    <button class="text-blue-500 hover:text-blue-700">Tracking</button>
                </td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/orders/detail.php?order_number=' . urlencode($item['order_number'])); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo url('admin/orders/edit.php?order_number=' . urlencode($item['order_number'])); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// BASE_URL is already declared in admin-header.php, so check if it exists first
if (typeof BASE_URL === 'undefined') {
    const BASE_URL = '<?php echo $baseUrl; ?>';
}
function deleteOrder(id) {
    showConfirmModal('Are you sure you want to delete this order? This action cannot be undone.', function() {
        fetch(BASE_URL + '/admin/api/orders.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showConfirmModal(data.message || 'Error deleting order', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Error' });
            }
        })
        .catch(error => {
            showConfirmModal('An error occurred while deleting the order.', function() {
                closeConfirmModal();
            }, { isError: true, title: 'Error' });
        });
    });
}
</script>

<script>
    document.addEventListener('change', function(e){
        if(!e.target.classList.contains('order-status-select')) return;

        const select = e.target;
        const orderId = select.dataset.orderId;
        const newStatus = select.value;

        // Visual feedback: Update styling immediately
        const statusColors = {
            'pending':    { bg: '#fef3c7', text: '#92400e' },
            'processing': { bg: '#dbeafe', text: '#1e40af' },
            'shipped':    { bg: '#f3e8ff', text: '#6b21a8' },
            'delivered':  { bg: '#d1fae5', text: '#065f46' },
            'success':    { bg: '#d1fae5', text: '#065f46' },
            'cancelled':  { bg: '#fee2e2', text: '#991b1b' },
            'cancel':     { bg: '#fee2e2', text: '#991b1b' }
        };

        const color = statusColors[newStatus] || { bg: '#f3f4f6', text: '#374151' };
        
        // Remove old classes that might conflict
        select.classList.remove('bg-green-100', 'text-green-800', 'bg-orange-100', 'text-orange-800', 'bg-gray-100', 'text-gray-800');
        
        // Apply new styles
        select.style.backgroundColor = color.bg;
        select.style.color = color.text;

        fetch(BASE_URL + '/admin/api/orders.php', {
            method: 'PUT',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                id: orderId,
                order_status: newStatus
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                console.log('Order status updated successfully');
                // Optional: Show a small toast notification
            } else {
                console.log('Failed to update order status: ' + (data.message || 'Unknown error'));
                // Revert selection if needed, but for now simple alert is enough
            }
        })
        .catch(err => {
            console.error('Something went wrong:', err);
            console.log('System error while updating status');
        });
    })
</script>


<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

