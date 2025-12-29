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
    SELECT p.id, p.name, p.price, COUNT(oi.id) as order_count, SUM(oi.quantity) as total_sold
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
    <h1 class="text-3xl font-bold">Report</h1>
    <p class="text-gray-600">Dashboard > Ecommerce > Report</p>
</div>

<!-- Date Range Filter -->
<div class="admin-card mb-6">
    <div class="flex items-center space-x-4">
        <label class="admin-form-label mb-0">Date Range:</label>
        <select id="dateRange" class="admin-form-select" style="width: auto; min-width: 200px;" onchange="window.location.href='?range=' + this.value">
            <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="365" <?php echo $dateRange === '365' ? 'selected' : ''; ?>>Last year</option>
        </select>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1">Total Orders</p>
                <h2 class="text-3xl font-bold"><?php echo number_format($totalOrders); ?></h2>
            </div>
            <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-500 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1">Total Revenue</p>
                <h2 class="text-3xl font-bold">$<?php echo number_format($totalRevenue, 2); ?></h2>
            </div>
            <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-green-500 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1">Total Customers</p>
                <h2 class="text-3xl font-bold"><?php echo number_format($totalCustomers); ?></h2>
            </div>
            <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-purple-500 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1">Total Products</p>
                <h2 class="text-3xl font-bold"><?php echo number_format($totalProducts); ?></h2>
            </div>
            <div class="w-16 h-16 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-box text-orange-500 text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Sales by Status -->
    <div class="admin-card">
        <h3 class="text-xl font-bold mb-4">Sales by Status</h3>
        <div class="space-y-4">
            <?php foreach ($salesByStatus as $status): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-4 h-4 rounded-full <?php 
                        echo $status['payment_status'] === 'paid' ? 'bg-green-500' : 
                            ($status['payment_status'] === 'pending' ? 'bg-yellow-500' : 'bg-red-500'); 
                    ?>"></div>
                    <span class="capitalize"><?php echo htmlspecialchars($status['payment_status']); ?></span>
                </div>
                <div class="text-right">
                    <p class="font-bold"><?php echo number_format($status['count']); ?> orders</p>
                    <p class="text-gray-600 text-sm">$<?php echo number_format($status['total'] ?? 0, 2); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="admin-card">
        <h3 class="text-xl font-bold mb-4">Top Selling Products</h3>
        <div class="space-y-3">
            <?php if (empty($topProducts)): ?>
            <p class="text-gray-500 text-center py-4">No sales data available</p>
            <?php else: ?>
            <?php foreach ($topProducts as $index => $item): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-0">
                <div class="flex items-center space-x-3">
                    <span class="text-gray-400 font-bold">#<?php echo $index + 1; ?></span>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($item['name']); ?></p>
                        <p class="text-sm text-gray-500">$<?php echo number_format($item['price'], 2); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-bold"><?php echo number_format($item['total_sold'] ?? 0); ?> sold</p>
                    <p class="text-sm text-gray-500"><?php echo number_format($item['order_count'] ?? 0); ?> orders</p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="admin-card">
    <h3 class="text-xl font-bold mb-4">Recent Orders</h3>
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
                        <span class="px-2 py-1 rounded text-xs <?php 
                            echo $orderItem['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                ($orderItem['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                        ?>">
                            <?php echo htmlspecialchars(ucfirst($orderItem['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="px-2 py-1 rounded text-xs <?php 
                            echo $orderItem['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                ($orderItem['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                        ?>">
                            <?php echo htmlspecialchars(ucfirst($orderItem['payment_status'])); ?>
                        </span>
                    </td>
                    <td class="font-bold">$<?php echo number_format($orderItem['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

