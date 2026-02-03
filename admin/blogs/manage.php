<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
    $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
    $storeId = $storeUser['store_id'] ?? null;
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $db->execute("DELETE FROM blogs WHERE id = ? AND store_id = ?", [$_POST['delete_id'], $storeId]);
    $_SESSION['flash_success'] = "Blog post deleted successfully.";
    header("Location: " . $baseUrl . '/admin/blogs/manage');
    exit;
}

$blogs = $db->fetchAll("SELECT * FROM blogs WHERE store_id = ? ORDER BY created_at DESC", [$storeId]);

$pageTitle = 'Manage Blogs';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Blog Posts</h1>
            <p class="text-gray-600">Manage your blog articles.</p>
        </div>
        <a href="<?php echo $baseUrl; ?>/admin/blogs/edit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i> Add New Post
        </a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <?php if (empty($blogs)): ?>
            <div class="p-8 text-center text-gray-500">
                <p class="mb-4 text-lg">No blog posts found.</p>
                <a href="<?php echo $baseUrl; ?>/admin/blogs/edit" class="text-blue-600 hover:underline">Create your first post</a>
            </div>
        <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Blog ID</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($blogs as $blog): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($blog['image']): ?>
                            <img src="<?php echo $baseUrl . '/' . $blog['image']; ?>" class="h-12 w-12 object-cover rounded border border-gray-200">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-gray-100 rounded border border-gray-200 flex items-center justify-center text-gray-400"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                        <?php echo htmlspecialchars($blog['blog_id'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($blog['title']); ?></div>
                        <div class="text-xs text-gray-500">/<?php echo htmlspecialchars($blog['slug']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $blog['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo ucfirst($blog['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo date('M j, Y', strtotime($blog['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="<?php echo $baseUrl; ?>/blog/<?php echo $blog['slug']; ?>" target="_blank" class="text-gray-600 hover:text-gray-900 mr-4" title="View Live"><i class="fas fa-eye"></i></a>
                        <a href="<?php echo $baseUrl; ?>/admin/blogs/edit?blog_id=<?php echo $blog['blog_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="delete_id" value="<?php echo $blog['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
    // Auto-hide success message after 3 seconds
    setTimeout(function() {
        const flashMsg = document.querySelector('.bg-green-100');
        if(flashMsg) {
            flashMsg.style.transition = "opacity 0.5s ease";
            flashMsg.style.opacity = "0";
            setTimeout(() => flashMsg.remove(), 500);
        }
    }, 3000);
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
