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
        <input type="text" 
               placeholder="Search here..." 
               value="<?php echo htmlspecialchars($filters['search']); ?>"
               class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 w-80">
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
                    <div class="flex items-center space-x-3">
                        <img src="<?php echo htmlspecialchars($productImage); ?>" 
                             alt="Product" 
                             class="w-12 h-12 object-cover rounded"
                             onerror="this.src='https://via.placeholder.com/50'">
                        <span><?php echo htmlspecialchars($item['customer_name']); ?></span>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($item['order_number']); ?></td>
                <td><?php echo format_currency($item['total_amount']); ?></td>
                <td><?php echo $item['total_quantity'] ?? 0; ?></td>
                <td><?php echo $item['payment_status']; ?></td>
                <!-- <td>
                    <span class="px-2 py-1 rounded <?php 
                        echo $item['order_status'] === 'Success' || $item['order_status'] === 'delivered' ? 'bg-green-100 text-green-800' : 
                            ($item['order_status'] === 'Cancel' || $item['order_status'] === 'cancelled' ? 'bg-orange-100 text-orange-800' : 
                            'bg-gray-100 text-gray-800'); 
                    ?>">
                        <?php echo ucfirst($item['order_status']); ?>
                    </span>
                </td> -->
              <td>
    <select
        class="order-status-select px-2 py-1 rounded border text-sm
        <?php 
            echo ($item['order_status'] === 'delivered' || $item['order_status'] === 'success')
                ? 'bg-green-100 text-green-800'
                : (($item['order_status'] === 'cancelled' || $item['order_status'] === 'cancel')
                    ? 'bg-orange-100 text-orange-800'
                    : 'bg-gray-100 text-gray-800');
        ?>"
        data-order-id="<?php echo $item['id']; ?>"
    >
        <?php
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        foreach ($statuses as $status) {
            $selected = ($item['order_status'] === $status) ? 'selected' : '';
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
                        <a href="<?php echo $baseUrl; ?>/admin/orders/detail?id=<?php echo $item['id']; ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo $baseUrl; ?>/admin/orders/edit?id=<?php echo $item['id']; ?>" class="text-green-500 hover:text-green-700">
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
        fetch(BASE_URL + '/admin/api/orders', {
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

