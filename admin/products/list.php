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

// For admin, get ALL products regardless of status
$sql = "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
        FROM products p 
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        LEFT JOIN categories c ON pc.category_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$products = $db->fetchAll($sql, $params);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Product List</h1>
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Ecommerce > Product List</p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col justify-between items-start space-y-4">
        <div class="flex-1">
            <p class="text-xs md:text-sm text-gray-600">Tip search by Product ID: Each product is provided with a unique ID, which you can rely on to find the exact product you need.</p>
        </div>
        <div class="flex flex-col md:flex-row w-full md:w-auto items-center gap-4">
            <!-- <select class="border rounded px-3 py-2 text-sm md:text-base w-full md:w-auto">
                <option>Showing all entries</option>
            </select> -->
            <form method="GET" action="" class="w-full md:w-auto">
                <input type="text" 
                       name="search"
                       placeholder="Search here..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base w-full md:w-80">
            </form>
            <a href="<?php echo url('admin/products/add.php'); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition text-sm md:text-base">
                    + Add new
            </a>
        </div>
    </div>
</div>

<div class="admin-card overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Product ID</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Sale</th>
                <th>Stock</th>
                <th>Start date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $item): 
                $mainImage = getProductImage($item);
            ?>
            <tr>
                <td>
                    <div class="flex items-center space-x-3">
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-16 h-16 object-cover rounded">
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($item['name']); ?></p>
                        </div>
                    </div>
                </td>
                <td>#<?php echo $item['id']; ?></td>
                <td><?php echo format_price($item['price'], $item['currency'] ?? 'USD'); ?></td>
                <td><?php echo $item['stock_quantity']; ?></td>
                <td><?php echo $item['sale_price'] ? round((($item['price'] - $item['sale_price']) / $item['price']) * 100) : 0; ?>%</td>
                <td>
                    <span class="px-2 py-1 rounded <?php echo $item['stock_status'] === 'in_stock' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $item['stock_status'])); ?>
                    </span>
                </td>
                <td><?php echo date('m/d/Y', strtotime($item['created_at'])); ?></td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/products/view.php?id=' . $item['id']); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo url('admin/products/edit.php?id=' . $item['id']); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteProduct(<?php echo $item['id']; ?>)" class="text-red-500 hover:text-red-700">
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
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

