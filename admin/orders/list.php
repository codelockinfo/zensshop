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
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Order > Order List</p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
        <form method="GET" action="" class="w-full md:w-auto">
            <!-- Preserve other filters if any -->
            <?php if (!empty($filters['order_status'])): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['order_status']); ?>">
            <?php endif; ?>
            <input type="text" 
                   name="search"
                   placeholder="Search here..." 
                   value="<?php echo htmlspecialchars($filters['search']); ?>"
                   class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 w-80">
        </form>
        <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
            <i class="fas fa-file-export mr-2"></i>Export all order
        </button>
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
                    $isDelivered = ($orderStatus === 'delivered' || $orderStatus === 'success');
                    $isCancelled = ($orderStatus === 'cancelled' || $orderStatus === 'cancel');
                    $selectBg = $isDelivered ? '#d1fae5' : ($isCancelled ? '#fee2e2' : '#f3f4f6');
                    $selectText = $isDelivered ? '#065f46' : ($isCancelled ? '#991b1b' : '#374151');
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
                        <a href="<?php echo $baseUrl; ?>/admin/orders/detail.php?order_number=<?php echo urlencode($item['order_number']); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo $baseUrl; ?>/admin/orders/edit.php?order_number=<?php echo urlencode($item['order_number']); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteOrder(<?php echo $item['id']; ?>)" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
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
        select.className = 'order-status-select px-2 py-1 rounded border text-sm'; // Reset
        if (newStatus === 'delivered' || newStatus === 'success') {
            select.classList.add('bg-green-100', 'text-green-800');
        } else if (newStatus === 'cancelled' || newStatus === 'cancel') {
            select.classList.add('bg-orange-100', 'text-orange-800');
        } else {
            select.classList.add('bg-gray-100', 'text-gray-800');
        }

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
                alert('Failed to update order status: ' + (data.message || 'Unknown error'));
                // Revert selection if needed, but for now simple alert is enough
            }
        })
        .catch(err => {
            console.error('Something went wrong:', err);
            alert('System error while updating status');
        });
    })
</script>


<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

