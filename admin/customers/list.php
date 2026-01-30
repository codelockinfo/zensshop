<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Customer.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Customers';
require_once __DIR__ . '/../../includes/admin-header.php';

$customer = new Customer();

// Get filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search) {
    $filters['search'] = $search;
}
if ($status) {
    $filters['status'] = $status;
}

// Get all customers
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = Database::getInstance()->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$filters['store_id'] = $storeId;
$customers = $customer->getAllCustomers($filters);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Customer List</h1>
    <p class="text-gray-600 text-sm md:text-base">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > Customer List
    </p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col justify-between items-start space-y-4">
        <div class="flex-1">
            <p class="text-sm text-gray-600">Tip search by Customer Email: Each customer is provided with a unique email, which you can rely on to find the exact customer you need.</p>
        </div>
        <div class="flex items-center flex-wrap w-full md:w-auto gap-4">
            <select class="border rounded px-3 py-2 text-sm md:text-base w-full md:w-auto">
                <option>Showing 10 entries</option>
                <option>Showing 25 entries</option>
                <option>Showing 50 entries</option>
            </select>
            <form method="GET" action="" class="flex items-center flex-wrap space-x-2 text-sm md:text-base w-full md:w-auto" id="searchForm">
                <input type="text" 
                       name="search"
                       placeholder="Search here..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base w-full md:w-auto"
                       onkeypress="if(event.key === 'Enter') { document.getElementById('searchForm').submit(); }">
                <?php if ($status): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="admin-card overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Customer ID</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
            <tr>
                <td colspan="8" class="text-center py-8 text-gray-500">
                    No customers found
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($customers as $item): ?>
            <tr>
                <td>
                    <div class="flex items-center space-x-3">
                        <div class="w-16 h-16 bg-primary-light rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-primary-dark text-xl"></i>
                        </div>
                        <div>
                            <p class="font-semibold" style="margin-bottom: 2px;"><?php echo htmlspecialchars($item['name']); ?></p>
                            <?php if ($item['is_registered']): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded shadow-sm" style="background-color: #d1fae5; color: #065f46; font-size: 0.75rem; font-weight: 600; vertical-align: middle;">
                                <i class="fas fa-check-circle mr-1" style="font-size: 0.8rem;"></i>Registered
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded shadow-sm" style="background-color: #f3f4f6; color: #374151; font-size: 0.75rem; font-weight: 600; vertical-align: middle;">
                                <i class="fas fa-shopping-cart mr-1" style="font-size: 0.8rem;"></i>Guest
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?php echo $item['id'] ? '#' . $item['id'] : '-'; ?></td>
                <td><?php echo htmlspecialchars($item['email']); ?></td>
                <td><?php echo !empty($item['phone']) ? htmlspecialchars($item['phone']) : '-'; ?></td>
                <td><?php echo $item['total_orders']; ?></td>
                <td><?php echo format_price($item['total_spent']); ?></td>
                <td>
                    <?php 
                    if ($item['is_registered']): 
                        $status = !empty($item['status']) ? $item['status'] : 'active';
                        $isActive = ($status === 'active');
                        $bgColor = $isActive ? '#d1fae5' : '#fee2e2';
                        $textColor = $isActive ? '#065f46' : '#991b1b';
                    ?>
                        <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                            <?php echo ucfirst($status); ?>
                        </span>
                    <?php else: ?>
                        <span class="px-2 py-1 rounded shadow-sm" style="background-color: #f3f4f6; color: #374151; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                            Guest
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/customers/view.php?' . ($item['id'] ? 'id=' . $item['id'] : 'email=' . urlencode($item['email']))); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

