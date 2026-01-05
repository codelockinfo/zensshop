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
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY sort_order ASC");
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Categories</h1>
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Category</p>
</div>

<div class="admin-card mb-6">
    <a href="<?php echo $baseUrl; ?>/admin/categories/manage.php" class="admin-btn admin-btn-primary">
        + Add Category
    </a>
</div>

<div class="admin-card overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
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
                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                <td>
                    <span class="px-2 py-1 rounded <?php echo $cat['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo ucfirst($cat['status']); ?>
                    </span>
                </td>
                <td><?php echo $cat['sort_order']; ?></td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo $baseUrl; ?>/admin/categories/manage.php?id=<?php echo $cat['id']; ?>" class="text-green-500 hover:text-green-700">
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
        fetch(BASE_URL + '/admin/api/categories', {
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

