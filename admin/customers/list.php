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

// Auto-update customer status based on order activity (admin tracking only)
// Active: ordered within last 30 days | Inactive: no orders in 30+ days
$db = Database::getInstance();

// Update status to 'inactive' for customers with no orders in the last 30 days
$db->execute("
    UPDATE customers c
    LEFT JOIN (
        SELECT customer_email, MAX(created_at) as last_order_date
        FROM orders
        WHERE store_id = ?
        GROUP BY customer_email
    ) o ON c.email = o.customer_email
    SET c.status = 'inactive'
    WHERE c.store_id = ?
    AND (o.last_order_date IS NULL OR o.last_order_date < DATE_SUB(NOW(), INTERVAL 30 DAY))
    AND c.status != 'inactive'
", [$storeId, $storeId]);

// Update status to 'active' for customers who ordered within the last 30 days
$db->execute("
    UPDATE customers c
    INNER JOIN (
        SELECT customer_email, MAX(created_at) as last_order_date
        FROM orders
        WHERE store_id = ?
        GROUP BY customer_email
    ) o ON c.email = o.customer_email
    SET c.status = 'active'
    WHERE c.store_id = ?
    AND o.last_order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND c.status != 'active'
", [$storeId, $storeId]);

$customers = $customer->getAllCustomers($filters);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold pt-4 pl-2">Customer List</h1>
    <p class="text-gray-600 text-sm md:text-base pl-2">
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
                       id="searchInput"
                       name="search"
                       placeholder="Search here..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base w-full md:w-auto"
                       onkeypress="if(event.key === 'Enter'){event.preventDefault(); return false;}">
                <?php if ($status): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="admin-card overflow-x-auto admin-card-list">
    <table class="admin-table">
        <thead class="list-header">
            <tr>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="row_number">
                    <div class="flex items-center justify-between">
                        <span>NO</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th>Customer</th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="id">
                    <div class="flex items-center justify-between">
                        <span>Customer ID</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th>Email</th>
                <th>Phone</th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="total_orders">
                    <div class="flex items-center justify-between">
                        <span>Orders</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="total_spent">
                    <div class="flex items-center justify-between">
                        <span>Total Spent</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="status">
                    <div class="flex items-center justify-between">
                        <span>Status</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
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
            <?php foreach ($customers as $index => $item): ?>
            <tr data-row-number="<?php echo $index + 1; ?>"
                data-email="<?php echo htmlspecialchars($item['email'] ?? ''); ?>"
                data-customer-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>">
                <td><?php echo $index + 1; ?></td>
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
            <tr id="noDataMessage" class="hidden">
                <td colspan="9" class="text-center py-8 text-gray-500">
                    <i class="fas fa-search mb-2 text-2xl block"></i>
                    No data match
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script>
// Table sorting functionality
window.initCustomerSort = function() {
    const table = document.querySelector('.admin-table');
    if (!table) return;
    const headers = table.querySelectorAll('th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.column;
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Skip if no customers
            if (rows.length === 1 && rows[0].querySelector('td[colspan]')) {
                return;
            }
            
            // Toggle direction
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
                currentSort.column = column;
            }
            
            // Reset all arrows
            headers.forEach(h => {
                const upArrow = h.querySelector('.fa-caret-up');
                const downArrow = h.querySelector('.fa-caret-down');
                if (upArrow) upArrow.classList.remove('text-blue-600');
                if (upArrow) upArrow.classList.add('text-gray-400');
                if (downArrow) downArrow.classList.remove('text-blue-600');
                if (downArrow) downArrow.classList.add('text-gray-400');
            });
            
            // Highlight active arrow
            const upArrow = this.querySelector('.fa-caret-up');
            const downArrow = this.querySelector('.fa-caret-down');
            if (currentSort.direction === 'asc') {
                if (upArrow) {
                    upArrow.classList.remove('text-gray-400');
                    upArrow.classList.add('text-blue-600');
                }
            } else {
                if (downArrow) {
                    downArrow.classList.remove('text-gray-400');
                    downArrow.classList.add('text-blue-600');
                }
            }
            
            // Sort rows
            rows.sort((a, b) => {
                let aVal, bVal;
                
                // Get cell index
                const cellIndex = Array.from(this.parentElement.children).indexOf(this);
                const aCell = a.children[cellIndex];
                const bCell = b.children[cellIndex];
                
                if (column === 'row_number') {
                    // Row number sort
                    aVal = parseInt(a.dataset.rowNumber) || 0;
                    bVal = parseInt(b.dataset.rowNumber) || 0;
                } else if (column === 'id' || column === 'total_orders' || column === 'total_spent') {
                    // Numeric sort
                    aVal = parseFloat(aCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                    bVal = parseFloat(bCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                } else {
                    // Text sort
                    aVal = aCell.textContent.trim().toLowerCase();
                    bVal = bCell.textContent.trim().toLowerCase();
                }
                
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Re-append sorted rows
            rows.forEach(row => {
                if (row.id !== 'noDataMessage') {
                    tbody.appendChild(row);
                }
            });
            
            // Ensure noDataMessage is always at the bottom
            const noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) tbody.appendChild(noDataMessage);
        });
    });
};

document.addEventListener('DOMContentLoaded', window.initCustomerSort);
document.addEventListener('adminPageLoaded', window.initCustomerSort);
</script>

<script>
window.initCustomerSearch = function() {
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('tbody tr');

    // Make search work as user types
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                if (row.id === 'noDataMessage') return;
                
                // Search in all visible text plus data attributes
                const text = row.innerText.toLowerCase();
                const email = (row.dataset.email || '').toLowerCase();
                const customerId = (row.dataset.customerId || '').toLowerCase();
                
                if (query === '' || text.includes(query) || email.includes(query) || customerId.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide "No data match" message
            const noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) {
                if (visibleCount === 0) {
                    noDataMessage.classList.remove('hidden');
                } else {
                    noDataMessage.classList.add('hidden');
                }
            }
            
            // Update URL without reload for bookmarks/refresh
            const newUrl = new URL(window.location);
            if (query) {
                newUrl.searchParams.set('search', query);
            } else {
                newUrl.searchParams.delete('search');
            }
            window.history.replaceState({}, '', newUrl);
        });
    }
};

document.addEventListener('DOMContentLoaded', window.initCustomerSearch);
document.addEventListener('adminPageLoaded', window.initCustomerSearch);
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>


