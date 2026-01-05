<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-header.php';

$db = Database::getInstance();
$product = new Product();
$order = new Order();

// Get statistics
$totalProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
$totalOrders = $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'];
$totalRevenue = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'")['total'] ?? 0;
$totalCustomers = $db->fetchOne("SELECT COUNT(DISTINCT customer_email) as count FROM orders")['count'];
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Dashboard</h1>
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Report</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-3 gap-6 mb-8 dashboard-stats">
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Amount</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalOrders); ?></h2>
                <p class="text-green-600 text-sm mt-2">
                    <i class="fas fa-arrow-up"></i> 1.56%
                </p>
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
                <h2 class="text-xl md:text-2xl font-bold">$<?php echo number_format($totalRevenue, 2); ?></h2>
                <p class="text-red-600 text-sm mt-2">
                    <i class="fas fa-arrow-down"></i> 1.56%
                </p>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-green-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Customer</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalCustomers); ?></h2>
                <p class="text-gray-600 text-sm mt-2">
                    <i class="fas fa-arrow-up"></i> 0.00%
                </p>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-purple-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="admin-card">
        <div class="flex flex-wrap items-center justify-between mb-4 gap-2">
            <h3 class="text-md md:text-xl font-bold">Seller Statistic</h3>
            <select class="border rounded px-3 py-1 text-sm md:text-base">
                <option>Last 30 days</option>
                <option>Last 7 days</option>
                <option>Last year</option>
            </select>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-500 rounded-full"></div>
                    <span>Revenue</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base">$<?php echo number_format($totalRevenue, 2); ?></p>
                    <p class="text-green-600 text-xs md:text-md"><i class="fas fa-arrow-up"></i> 0.56%</p>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-300 rounded-full"></div>
                    <span>Profit</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base">$<?php echo number_format($totalRevenue * 0.75, 2); ?></p>
                    <p class="text-green-600 text-xs md:text-md"><i class="fas fa-arrow-up"></i> 0.56%</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex flex-wrap items-center justify-between mb-4 gap-2">
            <h3 class="text-md md:text-xl font-bold">Total Sale</h3>
            <select class="border rounded px-3 py-1 text-sm md:text-base">
                <option>Last 30 days</option>
                <option>Last 7 days</option>
                <option>Last year</option>
            </select>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-500 rounded-full"></div>
                    <span>Revenue</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base">$<?php echo number_format($totalRevenue, 2); ?></p>
                    <p class="text-green-600 text-xs md:text-md"><i class="fas fa-arrow-up"></i> 0.56%</p>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-300 rounded-full"></div>
                    <span>Profit</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base">$<?php echo number_format($totalRevenue * 0.75, 2); ?></p>
                    <p class="text-green-600 text-xs md:text-md"><i class="fas fa-arrow-up"></i> 0.56%</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


