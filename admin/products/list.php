<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Product List';
require_once __DIR__ . '/../../includes/admin-header.php';

$product = new Product();
$db = Database::getInstance();
$search = $_GET['search'] ?? '';
$storeId = $_SESSION['store_id'] ?? null;

// For admin, get products for their store
$sql = "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
        FROM products p 
        LEFT JOIN product_categories pc ON p.product_id = pc.product_id
        LEFT JOIN categories c ON pc.category_id = c.id 
        WHERE p.status != 'archived' AND p.store_id = ?";
$params = [$storeId];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$products = $db->fetchAll($sql, $params);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold pt-4 pl-2">Product List</h1>
    <p class="text-gray-600 text-sm md:text-base pl-2">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
        <a href="<?php echo url('admin/products/list.php'); ?>" class="hover:text-blue-600">Ecommerce</a> > 
        Product List
    </p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col justify-between items-start space-y-4">
        <div class="flex-1">
            <p class="text-xs md:text-sm text-gray-600">Tip: Search by Product Name, SKU, or ID to find the exact product you need.</p>
        </div>
        <div class="flex flex-col md:flex-row w-full items-center justify-between gap-4">
            <form method="GET" action="" class="w-full md:flex-1 md:max-w-xl">
                <input type="text" 
                       id="searchInput"
                       name="search"
                       placeholder="Search by title, ID, or Product ID..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base w-full">
            </form>
            <a href="<?php echo url('admin/products/add.php'); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition text-sm md:text-base whitespace-nowrap">
                    + Add new
            </a>
        </div>
    </div>
</div>

<script>
window.initProductSearch = function() {
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('tbody tr');
    
    if (!searchInput) return;

    function filterRows(query) {
        let visibleCount = 0;
        tableRows.forEach(row => {
            if (row.id === 'noDataMessage') return; // Skip the message row itself
            
            const text = row.innerText.toLowerCase();
            const sku = (row.dataset.sku || '').toLowerCase();
            const productId = (row.dataset.productId || '').toLowerCase();
            
            if (query === '' || text.includes(query) || sku.includes(query) || productId.includes(query)) {
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
    }

    // Initial filter if search param exists
    if (searchInput.value.trim() !== '') {
        filterRows(searchInput.value.trim().toLowerCase());
    }

    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        filterRows(query);
        
        // Update URL without reload
        const newUrl = new URL(window.location);
        if (query) {
            newUrl.searchParams.set('search', query);
        } else {
            newUrl.searchParams.delete('search');
        }
        window.history.replaceState({}, '', newUrl);
    });
};

window.initProductSearch();
</script>

<div class="admin-card overflow-x-auto admin-card-list">
    <table class="admin-table ">
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
                <th>Product</th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="product_id">
                    <div class="flex items-center justify-between">
                        <span>Product ID</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>

                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="price">
                    <div class="flex items-center justify-between">
                        <span>Price</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="stock_quantity">
                    <div class="flex items-center justify-between">
                        <span>Quantity</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th>Sale</th>
                <th>Stock</th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="status">
                    <div class="flex items-center justify-between">
                        <span>Status</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="created_at">
                    <div class="flex items-center justify-between">
                        <span>Start date</span>
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
            <?php foreach ($products as $index => $item): 
                $mainImage = getProductImage($item);
            ?>
            <tr data-row-number="<?php echo $index + 1; ?>" 
                data-sku="<?php echo htmlspecialchars($item['sku'] ?? ''); ?>" 
                data-product-id="<?php echo htmlspecialchars($item['product_id'] ?? ''); ?>">
                <td><?php echo $index + 1; ?></td>
                <td>
                    <div class="flex items-center space-x-3">
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-16 h-16 object-cover rounded flex-shrink-0">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm line-clamp-2 max-w-[200px]" title="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </p>
                        </div>
                    </div>
                </td>
                <td>#<?php echo $item['product_id']; ?></td>

                <td><?php echo format_price($item['price'], $item['currency'] ?? 'USD'); ?></td>
                <td class="<?php echo ($item['stock_quantity'] < 0) ? 'text-red-600 font-bold' : ''; ?>">
                    <?php echo $item['stock_quantity']; ?>
                </td>
                <td><?php echo $item['sale_price'] ? round((($item['price'] - $item['sale_price']) / $item['price']) * 100) : 0; ?>%</td>
                <td>
                    <?php 
                    $stockQuantity = isset($item['stock_quantity']) ? (int)$item['stock_quantity'] : 0;
                    $totalSold = isset($item['total_sales']) ? (int)$item['total_sales'] : 0;
                    $stockStatus = !empty($item['stock_status']) ? $item['stock_status'] : 'in_stock';
                    $displayText = get_stock_status_text($stockStatus, $stockQuantity, $totalSold);
                    
                    $isAvailable = ($displayText === 'In Stock');
                    $isSoldOut = ($displayText === 'Sold Out');
                    $isOutOfStock = ($displayText === 'Out of Stock');
                    $isOversold = ($stockQuantity < 0);
                    
                    // Priority colors: Oversold (Red) -> Sold Out (Red) -> Out of Stock (Orange) -> In Stock (Green)
                    $stockBg = $isAvailable ? '#d1fae5' : ($isOversold || $isSoldOut ? '#fee2e2' : '#ffedd5');
                    $stockText = $isAvailable ? '#065f46' : ($isOversold || $isSoldOut ? '#991b1b' : '#9a3412');
                    
                    if ($isOversold) {
                        $displayText = 'Oversold';
                    }
                    ?>
                    <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $stockBg; ?>; color: <?php echo $stockText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                        <?php echo $displayText; ?>
                    </span>
                </td>
                <td>
                    <?php 
                    $status = !empty($item['status']) ? $item['status'] : 'draft';
                    $isActive = ($status === 'active');
                    $isDraft = ($status === 'draft');
                    $statusBg = $isActive ? '#d1fae5' : ($isDraft ? '#dbeafe' : '#fee2e2');
                    $statusText = $isActive ? '#065f46' : ($isDraft ? '#1e40af' : '#991b1b');
                    ?>
                    <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $statusBg; ?>; color: <?php echo $statusText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                        <?php echo ucfirst($status); ?>
                    </span>
                </td>
                <td><?php echo date('m/d/Y', strtotime($item['created_at'])); ?></td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/products/view.php?product_id=' . $item['product_id']); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo url('admin/products/edit.php?product_id=' . $item['product_id']); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteProduct(<?php echo $item['id']; ?>)" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
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
// BASE_URL is already declared in admin-header.php, so check if it exists first
if (typeof BASE_URL === 'undefined') {
    const BASE_URL = '<?php echo $baseUrl; ?>';
}
function deleteProduct(id) {
    showConfirmModal('Are you sure you want to delete this product? This action cannot be undone.', function() {
        fetch(BASE_URL + '/admin/api/products.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showConfirmModal(data.message || 'Error deleting product', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Error' });
            }
        })
        .catch(error => {
            showConfirmModal('An error occurred while deleting the product.', function() {
                closeConfirmModal();
            }, { isError: true, title: 'Error' });
        });
    });
}

// Table sorting functionality
window.initProductSort = function() {
    const table = document.querySelector('.admin-table');
    if (!table || table.dataset.sortInitialized) return;
    table.dataset.sortInitialized = 'true';

    const thead = table.querySelector('thead');
    if (!thead) return;

    thead.addEventListener('click', function(e) {
        const header = e.target.closest('th.sortable');
        if (!header) return;

        const column = header.dataset.column;
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr:not(#noDataMessage)'));
        if (rows.length <= 1) return;

        // Determine direction
        const currentDir = header.dataset.direction || 'none';
        const direction = currentDir === 'asc' ? 'desc' : 'asc';

        // Reset all other headers
        table.querySelectorAll('th.sortable').forEach(h => {
            h.dataset.direction = '';
            h.querySelectorAll('i').forEach(icon => {
                icon.classList.remove('text-blue-600');
                icon.classList.add('text-gray-400');
            });
        });

        // Set current header
        header.dataset.direction = direction;
        const upArrow = header.querySelector('.fa-caret-up');
        const downArrow = header.querySelector('.fa-caret-down');
        if (direction === 'asc' && upArrow) {
            upArrow.classList.replace('text-gray-400', 'text-blue-600');
        } else if (direction === 'desc' && downArrow) {
            downArrow.classList.replace('text-gray-400', 'text-blue-600');
        }

        // Get cell index
        const cellIndex = Array.from(header.parentElement.children).indexOf(header);

        // Sort rows
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        
        rows.sort((a, b) => {
            let aVal, bVal;
            const aCell = a.children[cellIndex];
            const bCell = b.children[cellIndex];

            if (column === 'row_number') {
                aVal = parseInt(a.dataset.rowNumber) || 0;
                bVal = parseInt(b.dataset.rowNumber) || 0;
            } else if (column === 'price' || column === 'stock_quantity') {
                aVal = parseFloat(aCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                bVal = parseFloat(bCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            } else if (column === 'created_at') {
                aVal = new Date(aCell.textContent);
                bVal = new Date(bCell.textContent);
            } else {
                aVal = aCell.textContent.trim();
                bVal = bCell.textContent.trim();
                return direction === 'asc' ? collator.compare(aVal, bVal) : collator.compare(bVal, aVal);
            }

            if (aVal < bVal) return direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        // Re-append rows using fragment for performance
        const fragment = document.createDocumentFragment();
        rows.forEach(row => fragment.appendChild(row));
        
        const noData = document.getElementById('noDataMessage');
        if (noData) fragment.appendChild(noData);
        
        tbody.appendChild(fragment);
    });
};

window.initProductSort();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

