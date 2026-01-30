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

// --- Helper to calculate percentage change ---
function getPercentageChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return (($current - $previous) / $previous) * 100;
}

function getTrendHtml($percent) {
    $percent = round($percent, 2);
    if ($percent > 0) {
        return '<p class="text-green-600 text-sm mt-2"><i class="fas fa-arrow-up"></i> ' . $percent . '%</p>';
    } elseif ($percent < 0) {
        return '<p class="text-red-600 text-sm mt-2"><i class="fas fa-arrow-down"></i> ' . abs($percent) . '%</p>';
    } else {
        return '<p class="text-gray-600 text-sm mt-2"><i class="fas fa-minus"></i> 0%</p>';
    }
}

// --- Store ID Filtering ---
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// --- Total Statistics (All Time) ---
$totalProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE store_id = ?", [$storeId])['count'];
$totalOrders = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE store_id = ?", [$storeId])['count'];
$totalRevenue = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND store_id = ?", [$storeId])['total'] ?? 0;
$totalCustomers = $db->fetchOne("SELECT COUNT(DISTINCT customer_email) as count FROM orders WHERE store_id = ?", [$storeId])['count'];

// --- Monthly Growth Statistics (This Month vs Last Month) ---
// Orders
$ordersThisMonth = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND store_id = ?", [$storeId])['count'];
$ordersLastMonth = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) AND store_id = ?", [$storeId])['count'];
$ordersGrowth = getPercentageChange($ordersThisMonth, $ordersLastMonth);

// Revenue
$revenueThisMonth = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND store_id = ?", [$storeId])['total'] ?? 0;
$revenueLastMonth = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) AND store_id = ?", [$storeId])['total'] ?? 0;
$revenueGrowth = getPercentageChange($revenueThisMonth, $revenueLastMonth);

// Customers
$customersThisMonth = $db->fetchOne("SELECT COUNT(DISTINCT customer_email) as count FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND store_id = ?", [$storeId])['count'];
$customersLastMonth = $db->fetchOne("SELECT COUNT(DISTINCT customer_email) as count FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) AND store_id = ?", [$storeId])['count'];
$customersGrowth = getPercentageChange($customersThisMonth, $customersLastMonth);


// --- Last 30 Days Statistics (Rolling Window for Bottom Cards) ---
$revenueLast30Days = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= NOW() - INTERVAL 30 DAY AND store_id = ?", [$storeId])['total'] ?? 0;
$revenuePrevious30Days = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= NOW() - INTERVAL 60 DAY AND created_at < NOW() - INTERVAL 30 DAY AND store_id = ?", [$storeId])['total'] ?? 0;
$revenue30DayGrowth = getPercentageChange($revenueLast30Days, $revenuePrevious30Days);
$profitLast30Days = $revenueLast30Days * 0.75; // Estimated 75% profit margin logic
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Dashboard</h1>
    <p class="text-gray-600 text-sm md:text-base">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a>
    </p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-3 gap-6 mb-8 dashboard-stats">
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Orders</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalOrders); ?></h2>
                <?php echo getTrendHtml($ordersGrowth); ?>
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
                <h2 class="text-xl md:text-2xl font-bold"><?php echo format_currency($totalRevenue); ?></h2>
                <?php echo getTrendHtml($revenueGrowth); ?>
            </div>
            <div class="w-12 h-12 md:w-16 md:h-16 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-shopping-cart text-green-500 text-lg md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 text-sm md:text-base">Total Customer</p>
                <h2 class="text-xl md:text-2xl font-bold"><?php echo number_format($totalCustomers); ?></h2>
                <?php echo getTrendHtml($customersGrowth); ?>
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
                <option value="30">Last 30 days</option>
                <option value="7">Last 7 days</option>
                <option value="365">Last year</option>
            </select>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-500 rounded-full"></div>
                    <span>Revenue (Last 30d)</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo format_currency($revenueLast30Days); ?></p>
                    <?php echo getTrendHtml($revenue30DayGrowth); ?>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-300 rounded-full"></div>
                    <span>Profit (Est. 75%)</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo format_currency($profitLast30Days); ?></p>
                    <?php echo getTrendHtml($revenue30DayGrowth); // Assuming profit tracks revenue ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="flex flex-wrap items-center justify-between mb-4 gap-2">
            <h3 class="text-md md:text-xl font-bold">Total Sale</h3>
            <select class="border rounded px-3 py-1 text-sm md:text-base">
                <option value="30">Last 30 days</option>
                <option value="7">Last 7 days</option>
                <option value="365">Last year</option>
            </select>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-500 rounded-full"></div>
                    <span>Revenue (Last 30d)</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo format_currency($revenueLast30Days); ?></p>
                    <?php echo getTrendHtml($revenue30DayGrowth); ?>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-sm md:text-base">
                    <div class="w-2 h-2 md:w-3 md:h-3 bg-blue-300 rounded-full"></div>
                    <span>Profit (Est. 75%)</span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-sm md:text-base"><?php echo format_currency($profitLast30Days); ?></p>
                    <?php echo getTrendHtml($revenue30DayGrowth); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
