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
$customers = $customer->getAllCustomers($filters);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Customer List</h1>
    <p class="text-gray-600">Dashboard > Customers > Customer List</p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
        <div class="flex-1">
            <p class="text-sm text-gray-600">Tip search by Customer Email: Each customer is provided with a unique email, which you can rely on to find the exact customer you need.</p>
        </div>
        <div class="flex items-center space-x-4">
            <select class="border rounded px-3 py-2">
                <option>Showing 10 entries</option>
                <option>Showing 25 entries</option>
                <option>Showing 50 entries</option>
            </select>
            <form method="GET" action="" class="flex items-center space-x-2" id="searchForm">
                <input type="text" 
                       name="search"
                       placeholder="Search here..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                            <p class="font-semibold"><?php echo htmlspecialchars($item['name']); ?></p>
                            <?php if ($item['is_registered']): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 mt-1">
                                <i class="fas fa-check-circle mr-1"></i>Registered
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-800 mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i>Guest
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?php echo $item['id'] ? '#' . $item['id'] : '-'; ?></td>
                <td><?php echo htmlspecialchars($item['email']); ?></td>
                <td><?php echo !empty($item['phone']) ? htmlspecialchars($item['phone']) : '-'; ?></td>
                <td><?php echo $item['total_orders']; ?></td>
                <td>$<?php echo number_format($item['total_spent'], 2); ?></td>
                <td>
                    <?php if ($item['is_registered']): ?>
                    <span class="px-2 py-1 rounded <?php echo $item['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($item['status']); ?>
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-1 rounded bg-gray-100 text-gray-800">
                        Guest
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="/oecom/admin/customers/view.php?<?php echo $item['id'] ? 'id=' . $item['id'] : 'email=' . urlencode($item['email']); ?>" class="text-blue-500 hover:text-blue-700">
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

