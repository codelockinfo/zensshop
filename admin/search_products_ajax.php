<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$search = $_GET['search'] ?? '';

$sql = "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
        FROM products p 
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        LEFT JOIN categories c ON pc.category_id = c.id 
        WHERE p.status != 'archived'";
$params = [];

if (!empty($search)) {
    // Search by name, description, SKU, or ID
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$products = $db->fetchAll($sql, $params);

if (empty($products)) {
    echo '<tr><td colspan="9" class="text-center py-4 text-gray-500">No products found</td></tr>';
    exit;
}

foreach ($products as $item): 
    $mainImage = getProductImage($item);
?>
<tr>
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
    <td><?php echo $item['stock_quantity']; ?></td>
    <td><?php echo $item['sale_price'] ? round((($item['price'] - $item['sale_price']) / $item['price']) * 100) : 0; ?>%</td>
    <td>
        <?php 
        $stockStatus = !empty($item['stock_status']) ? $item['stock_status'] : 'in_stock';
        $isInStock = ($stockStatus === 'in_stock');
        $stockBg = $isInStock ? '#d1fae5' : '#ffedd5';
        $stockText = $isInStock ? '#065f46' : '#9a3412';
        ?>
        <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $stockBg; ?>; color: <?php echo $stockText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
            <?php echo ucfirst(str_replace('_', ' ', $stockStatus)); ?>
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
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/products/view.php?product_id=<?php echo $item['product_id']; ?>" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-eye"></i>
            </a>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/products/edit.php?product_id=<?php echo $item['product_id']; ?>" class="text-green-500 hover:text-green-700">
                <i class="fas fa-edit"></i>
            </a>
            <button onclick="deleteProduct(<?php echo $item['id']; ?>)" class="text-red-500 hover:text-red-700">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
