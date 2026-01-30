<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Categories';
require_once __DIR__ . '/../../includes/admin-header.php';

$db = Database::getInstance();
$storeId = $_SESSION['store_id'] ?? null;
$categories = $db->fetchAll("SELECT * FROM categories WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);
// Debug: uncomment if needed
// print_r($categories);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Categories</h1>
    <p class="text-gray-600 text-sm md:text-base">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > Category
    </p>
</div>

<div class="admin-card mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <a href="<?php echo url('admin/categories/manage.php'); ?>" class="admin-btn admin-btn-primary">
        + Add Category
    </a>
    
    <div class="relative w-full md:w-auto">
        <input type="text" id="categorySearch" placeholder="Search categories..." class="w-full md:w-64 border border-gray-300 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow">
        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('categorySearch');
    const tableRows = document.querySelectorAll('.admin-table tbody tr');

    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<div class="admin-card overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Sort Order</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?php echo $cat['id']; ?></td>
                <td>
                    <?php if (!empty($cat['image'])): ?>
                        <img src="<?php echo $baseUrl . '/' . $cat['image']; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="w-10 h-10 object-cover rounded border">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center text-gray-500">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                <td>
                    <?php 
                    $status = !empty($cat['status']) ? $cat['status'] : 'active';
                    $isActive = ($status === 'active');
                    $bgColor = $isActive ? '#d1fae5' : '#f3f4f6';
                    $textColor = $isActive ? '#065f46' : '#374151';
                    ?>
                    <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                        <?php echo ucfirst($status); ?>
                    </span>
                </td>
                <td><?php echo $cat['sort_order']; ?></td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/categories/manage.php?id=' . $cat['id']); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteCategory(<?php echo $cat['id']; ?>)" class="text-red-500 hover:text-red-700">
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
function deleteCategory(id) {
    showConfirmModal('Are you sure you want to delete this category? This action cannot be undone.', function() {
        fetch(BASE_URL + '/admin/api/categories.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showConfirmModal(data.message || 'Error deleting category', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Error' });
            }
        })
        .catch(error => {
            showConfirmModal('An error occurred while deleting the category.', function() {
                closeConfirmModal();
            }, { isError: true, title: 'Error' });
        });
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

