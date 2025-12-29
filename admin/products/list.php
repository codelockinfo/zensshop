<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Product List';
require_once __DIR__ . '/../../includes/admin-header.php';

$product = new Product();
$search = $_GET['search'] ?? '';
$filters = ['search' => $search];
$products = $product->getAll($filters);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Product List</h1>
    <p class="text-gray-600">Dashboard > Ecommerce > Product List</p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
        <div class="flex-1">
            <p class="text-sm text-gray-600">Tip search by Product ID: Each product is provided with a unique ID, which you can rely on to find the exact product you need.</p>
        </div>
        <div class="flex items-center space-x-4">
            <select class="border rounded px-3 py-2">
                <option>Showing 10 entries</option>
                <option>Showing 25 entries</option>
                <option>Showing 50 entries</option>
            </select>
            <input type="text" 
                   placeholder="Search here..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <a href="/oecom/admin/products/add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
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
                <td>$<?php echo number_format($item['price'], 2); ?></td>
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
                        <a href="/oecom/admin/products/view.php?id=<?php echo $item['id']; ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/oecom/admin/products/edit.php?id=<?php echo $item['id']; ?>" class="text-green-500 hover:text-green-700">
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
function deleteProduct(id) {
    showConfirmModal('Are you sure you want to delete this product? This action cannot be undone.', function() {
        fetch('/oecom/admin/api/products.php', {
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

