<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Report';
require_once __DIR__ . '/../includes/admin-header.php';

$db = Database::getInstance();
$product = new Product();
$order = new Order();

// Get date range (default: last 30 days)
$dateRange = $_GET['range'] ?? '30';
$daysAgo = $dateRange === '7' ? 7 : ($dateRange === '365' ? 365 : 30);
$startDate = date('Y-m-d', strtotime("-{$daysAgo} days"));

// Get statistics
$totalProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
$totalOrders = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE created_at >= ?", [$startDate])['count'];
$totalRevenue = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= ?", [$startDate])['total'] ?? 0;
$totalCustomers = $db->fetchOne("SELECT COUNT(DISTINCT customer_email) as count FROM orders WHERE created_at >= ?", [$startDate])['count'];

// Get recent orders
$recentOrders = $db->fetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");

// Get top products
$topProducts = $db->fetchAll("
    SELECT p.id, p.name, COUNT(oi.id) as order_count, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at >= ? OR o.created_at IS NULL
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
", [$startDate]);

// Get sales by status
$salesByStatus = $db->fetchAll("
    SELECT payment_status, COUNT(*) as count, SUM(total_amount) as total
    FROM orders
    WHERE created_at >= ?
    GROUP BY payment_status
", [$startDate]);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Report</h1>
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Ecommerce > Report</p>
</div>

<!-- Date Range Filter -->
<div class="admin-card mb-6">
    <div class="flex items-center flex-wrap gap-4">
        <label class="admin-form-label mb-0">Date Range:</label>
        <select id="dateRange" class="admin-form-select" style="width: auto; min-width: 250px;" onchange="window.location.href='?range=' + this.value">
            <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="365" <?php echo $dateRange === '365' ? 'selected' : ''; ?>>Last year</option>
        </select>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 report-cards">
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Orders</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalOrders); ?></h2>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Revenue</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo format_price($totalRevenue); ?></h2>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-rupee-sign text-green-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Customers</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalCustomers); ?></h2>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-purple-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Products</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalProducts); ?></h2>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-box text-orange-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Charts and Tables Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Sales by Status -->
    <div class="admin-card">
        <h3 class="text-lg md:text-xl font-bold mb-4">Sales by Status</h3>
        <div class="h-64 mb-4">
            <canvas id="salesStatusChart"></canvas>
        </div>
        <div class="space-y-4">
            <?php foreach ($salesByStatus as $status): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 md:w-4 md:h-4 rounded-full <?php 
                        echo $status['payment_status'] === 'paid' ? 'bg-green-500' : 
                            ($status['payment_status'] === 'pending' ? 'bg-yellow-500' : 'bg-red-500'); 
                    ?>"></div>
                    <span class="capitalize text-sm md:text-base"><?php echo htmlspecialchars($status['payment_status']); ?></span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo number_format($status['count']); ?> orders</p>
                    <p class="text-gray-600 text-xs md:text-md"><?php echo format_price($status['total'] ?? 0); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="admin-card">
        <h3 class="text-lg md:text-xl font-bold mb-4">Top Selling Products</h3>
        <div class="h-64 mb-4">
            <canvas id="topProductsChart"></canvas>
        </div>
        <div class="space-y-4 overflow-y-auto max-h-60">
            <?php if (empty($topProducts)): ?>
            <p class="text-gray-500 text-center text-sm md:text-base py-4">No sales data available</p>
            <?php else: ?>
            <?php foreach ($topProducts as $item): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 md:w-4 md:h-4 rounded-full bg-blue-500"></div>
                    <span class="font-semibold text-sm md:text-base truncate max-w-[150px] md:max-w-xs" title="<?php echo htmlspecialchars($item['name']); ?>"><?php echo htmlspecialchars($item['name']); ?></span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo number_format($item['total_sold'] ?? 0); ?> sold</p>
                    <p class="text-gray-600 text-xs md:text-md"><?php echo format_price($item['total_revenue'] ?? 0); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales by Status Chart
    const statusCtx = document.getElementById('salesStatusChart').getContext('2d');
    const salesData = <?php echo json_encode($salesByStatus); ?>;
    
    const statusLabels = salesData.map(item => item.payment_status.charAt(0).toUpperCase() + item.payment_status.slice(1));
    const statusCounts = salesData.map(item => item.count);
    const statusColors = salesData.map(item => {
        if (item.payment_status === 'paid') return '#10B981'; // green-500
        if (item.payment_status === 'pending') return '#F59E0B'; // yellow-500
        return '#EF4444'; // red-500
    });

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // We have our own custom legend
                }
            }
        }
    });

    // Top Products Chart
    const productsCtx = document.getElementById('topProductsChart').getContext('2d');
    const productsData = <?php echo json_encode($topProducts); ?>;
    
    if (productsData.length > 0) {
        new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: productsData.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
                datasets: [{
                    label: 'Units Sold',
                    data: productsData.map(item => item.total_sold),
                    backgroundColor: '#3B82F6', // blue-500
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<!-- Recent Orders Table -->
<div class="admin-card">
    <h3 class="text-lg md:text-xl font-bold mb-4">Recent Orders</h3>
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">No orders found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recentOrders as $orderItem): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($orderItem['id']); ?></td>
                    <td><?php echo htmlspecialchars($orderItem['customer_name'] ?? 'N/A'); ?></td>
                    <td><?php echo date('M d, Y', strtotime($orderItem['created_at'])); ?></td>
                    <td>
                        <?php 
                        $orderStatus = $orderItem['order_status'] ?? 'pending';
                        $isDelivered = ($orderStatus === 'delivered' || $orderStatus === 'completed');
                        $isPending = ($orderStatus === 'pending');
                        $statusBg = $isDelivered ? '#d1fae5' : ($isPending ? '#fef3c7' : '#f3f4f6');
                        $statusText = $isDelivered ? '#065f46' : ($isPending ? '#92400e' : '#374151');
                        ?>
                        <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $statusBg; ?>; color: <?php echo $statusText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                            <?php echo ucfirst($orderStatus); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $payStatus = $orderItem['payment_status'] ?? 'pending';
                        $isPaid = ($payStatus === 'paid');
                        $isPayPending = ($payStatus === 'pending');
                        $payBg = $isPaid ? '#d1fae5' : ($isPayPending ? '#fef3c7' : '#fee2e2');
                        $payText = $isPaid ? '#065f46' : ($isPayPending ? '#92400e' : '#991b1b');
                        ?>
                        <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $payBg; ?>; color: <?php echo $payText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                            <?php echo ucfirst($payStatus); ?>
                        </span>
                    </td>
                    <td class="font-bold"><?php echo format_price($orderItem['total_amount']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


