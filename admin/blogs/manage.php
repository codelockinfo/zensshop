<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Settings.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$settingsObj = new Settings();
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
    header("Location: " . $baseUrl . '/admin/blogs/manage.php');
    exit;
}

// Handle Settings Update
if (isset($_POST['update_settings'])) {
    $blogStyling = [
        'blog_heading' => $_POST['blog_heading'],
        'blog_subheading' => $_POST['blog_subheading'],
        'blog_heading_color' => $_POST['blog_heading_color'],
        'blog_subheading_color' => $_POST['blog_subheading_color'],
        'blog_page_bg_color' => $_POST['blog_page_bg_color'],
        'blog_card_bg_color' => $_POST['blog_card_bg_color'],
        'blog_date_color' => $_POST['blog_date_color'],
        'blog_title_color' => $_POST['blog_title_color'],
        'blog_desc_color' => $_POST['blog_desc_color'],
        'blog_read_more_color' => $_POST['blog_read_more_color']
    ];
    
    $settingsObj->set('blog_page_styling', json_encode($blogStyling));
    
    $_SESSION['flash_success'] = "Blog page settings updated successfully.";
    header("Location: " . $baseUrl . '/admin/blogs/manage.php');
    exit;
}

// Load Consolidated Settings
$blogStylingJson = $settingsObj->get('blog_page_styling', '');
$blogStyling = !empty($blogStylingJson) ? json_decode($blogStylingJson, true) : [];

// Fallback to individual keys if JSON is empty (for migration)
if (empty($blogStyling)) {
    $blogStyling = [
        'blog_heading' => $settingsObj->get('blog_heading', 'Our Blogs'),
        'blog_subheading' => $settingsObj->get('blog_subheading', 'Latest news, updates, and stories from our team.'),
        'blog_heading_color' => $settingsObj->get('blog_heading_color', '#111827'),
        'blog_subheading_color' => $settingsObj->get('blog_subheading_color', '#4b5563'),
        'blog_page_bg_color' => $settingsObj->get('blog_page_bg_color', '#f9fafb'),
        'blog_card_bg_color' => $settingsObj->get('blog_card_bg_color', '#ffffff'),
        'blog_date_color' => $settingsObj->get('blog_date_color', '#6b7280'),
        'blog_title_color' => $settingsObj->get('blog_title_color', '#111827'),
        'blog_desc_color' => $settingsObj->get('blog_desc_color', '#4b5563'),
        'blog_read_more_color' => $settingsObj->get('blog_read_more_color', '#2563eb')
    ];
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

    <!-- Blog Page Settings Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center cursor-pointer" onclick="document.getElementById('blogSettingsForm').classList.toggle('hidden'); document.getElementById('settingsIcon').classList.toggle('rotate-180');">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-paint-brush"></i> Blog Page Styling
            </h2>
            <i class="fas fa-chevron-down transition-transform duration-200" id="settingsIcon"></i>
        </div>
        
        <form method="POST" id="blogSettingsForm" class="hidden p-6">
            <input type="hidden" name="update_settings" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                
                <!-- Main Header Section -->
                <div class="lg:col-span-3">
                    <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 border-b pb-1">Page Header</h3>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2">Page Heading</label>
                    <input type="text" name="blog_heading" value="<?php echo htmlspecialchars($blogStyling['blog_heading'] ?? 'Our Blogs'); ?>" class="w-full border p-2 rounded">
                </div>
                
                <div>
                     <label class="block text-sm font-bold mb-2">Heading Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_heading_color" value="<?php echo htmlspecialchars($blogStyling['blog_heading_color'] ?? '#111827'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_heading_color'] ?? '#111827'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Page Background</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_page_bg_color" value="<?php echo htmlspecialchars($blogStyling['blog_page_bg_color'] ?? '#f9fafb'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_page_bg_color'] ?? '#f9fafb'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-sm font-bold mb-2">Subheading Text</label>
                    <input type="text" name="blog_subheading" value="<?php echo htmlspecialchars($blogStyling['blog_subheading'] ?? 'Latest news, updates, and stories from our team.'); ?>" class="w-full border p-2 rounded">
                </div>
                
                <div>
                     <label class="block text-sm font-bold mb-2">Subheading Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_subheading_color" value="<?php echo htmlspecialchars($blogStyling['blog_subheading_color'] ?? '#4b5563'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_subheading_color'] ?? '#4b5563'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>
                
                <!-- Card Styling Section -->
                <div class="lg:col-span-3 mt-4">
                    <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 border-b pb-1">Article Card Styling</h3>
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2">Card Background</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_card_bg_color" value="<?php echo htmlspecialchars($blogStyling['blog_card_bg_color'] ?? '#ffffff'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_card_bg_color'] ?? '#ffffff'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>
                
                <div>
                     <label class="block text-sm font-bold mb-2">Date Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_date_color" value="<?php echo htmlspecialchars($blogStyling['blog_date_color'] ?? '#6b7280'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_date_color'] ?? '#6b7280'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2">Card Title Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_title_color" value="<?php echo htmlspecialchars($blogStyling['blog_title_color'] ?? '#111827'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_title_color'] ?? '#111827'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2">Description Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_desc_color" value="<?php echo htmlspecialchars($blogStyling['blog_desc_color'] ?? '#4b5563'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_desc_color'] ?? '#4b5563'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2">"Read Article" Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="blog_read_more_color" value="<?php echo htmlspecialchars($blogStyling['blog_read_more_color'] ?? '#2563eb'); ?>" class="h-10 w-10 rounded cursor-pointer border p-1" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($blogStyling['blog_read_more_color'] ?? '#2563eb'); ?>" class="flex-1 border p-2 rounded uppercase" readonly>
                    </div>
                </div>

            </div>

            <div class="text-right">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition">
                    Save Page Settings
                </button>
            </div>
        </form>
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
                        <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo $blog['slug']; ?>" target="_blank" class="text-gray-600 hover:text-gray-900 mr-4" title="View Live"><i class="fas fa-eye"></i></a>
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
