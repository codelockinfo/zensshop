<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();

// Store ID Logic
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
    $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
    $storeId = $storeUser['store_id'] ?? null;
}

$id = $_GET['id'] ?? null;
$blogIdParam = $_GET['blog_id'] ?? null;

// If blog_id is passed, attempt to resolve it to internal ID
if (!$id && $blogIdParam) {
    // Determine Store ID (logic duplicated from below because we need it now)
    $tempStoreId = $_SESSION['store_id'] ?? null;
    if (!$tempStoreId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $tempStoreId = $storeUser['store_id'] ?? null;
    }
    
    $resolvedBlog = $db->fetchOne("SELECT id FROM blogs WHERE blog_id = ? AND store_id = ?", [$blogIdParam, $tempStoreId]);
    if ($resolvedBlog) {
        $id = $resolvedBlog['id'];
    }
}

$blog = null;
if ($id) {
    $blog = $db->fetchOne("SELECT * FROM blogs WHERE id = ? AND store_id = ?", [$id, $storeId]);
    if (!$blog) {
        $_SESSION['flash_error'] = "Blog post not found.";
        header("Location: $baseUrl/admin/blogs/manage");
        exit;
    }
}

// Initialize settings
$blogSettings = [
    'banner' => [
        'bg_color' => '#ffffff',
        'text_color' => '#ffffff',
        'heading_color' => '#ffffff',
        'subheading_color' => '#ffffff',
        'btn_text' => '',
        'btn_link' => '',
        'btn_bg_color' => '#ffffff',
        'btn_text_color' => '#000000',
        'btn_hover_bg_color' => '#f3f4f6',
        'btn_hover_text_color' => '#000000'
    ],
    'page_bg_color' => '#ffffff'
];

if ($blog && !empty($blog['settings'])) {
    $decodedSettings = json_decode($blog['settings'], true);
    if (is_array($decodedSettings)) {
        $blogSettings = array_merge($blogSettings, $decodedSettings);
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $content = $_POST['content'];
    $status = $_POST['status'];
    
    // Auto-generate slug if empty
    if (empty($slug)) $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // Check Slug Not Exists
    $check = $db->fetchOne("SELECT id FROM blogs WHERE slug = ? AND store_id = ? AND id != ?", [$slug, $storeId, $id ?? 0]);
    if ($check) {
        $error = "Slug already exists. Please choose another.";
    } else {
        // Image Upload
        $imagePath = $blog['image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/images/blogs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fname = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['image']['name']));
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname)) {
                $imagePath = 'assets/images/blogs/' . $fname;
            }
        }

        try {
            $newSettings = [
                'banner' => [
                    'bg_color' => $_POST['banner_bg_color'] ?? '#ffffff',
                    'text_color' => $_POST['banner_text_color'] ?? '#ffffff',
                    'heading_color' => $_POST['banner_heading_color'] ?? '#ffffff',
                    'subheading_color' => $_POST['banner_subheading_color'] ?? '#ffffff',
                    'btn_text' => $_POST['banner_btn_text'] ?? '',
                    'btn_link' => $_POST['banner_btn_link'] ?? '',
                    'btn_bg_color' => $_POST['banner_btn_bg_color'] ?? '#ffffff',
                    'btn_text_color' => $_POST['banner_btn_text_color'] ?? '#000000',
                    'btn_hover_bg_color' => $_POST['banner_btn_hover_bg_color'] ?? '#f3f4f6',
                    'btn_hover_text_color' => $_POST['banner_btn_hover_text_color'] ?? '#000000'
                ],
                'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff'
            ];
            $jsonSettings = json_encode($newSettings);
            $layout = $_POST['layout'] ?? 'standard';

            if ($id) {
                $db->execute("UPDATE blogs SET title=?, slug=?, content=?, image=?, status=?, layout=?, settings=?, updated_at=NOW() WHERE id=? AND store_id=?", 
                    [$title, $slug, $content, $imagePath, $status, $layout, $jsonSettings, $id, $storeId]);
                $_SESSION['flash_success'] = "Blog updated successfully.";
            } else {
                // Generate unique 10-digit blog_id
                do {
                    $blogId = mt_rand(1000000000, 9999999999);
                    $exists = $db->fetchOne("SELECT id FROM blogs WHERE blog_id = ?", [$blogId]);
                } while ($exists);

                $db->execute("INSERT INTO blogs (store_id, blog_id, title, slug, content, image, status, layout, settings, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", 
                    [$storeId, $blogId, $title, $slug, $content, $imagePath, $status, $layout, $jsonSettings]);
                $_SESSION['flash_success'] = "Blog created successfully.";
            }
            header("Location: $baseUrl/admin/blogs/manage");
            exit;
        } catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$pageTitle = $id ? 'Edit Blog' : 'Create Blog';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex items-center space-x-2 text-gray-500 mb-4">
        <a href="<?php echo $baseUrl; ?>/admin/blogs/manage" class="hover:text-blue-600 font-medium">Blogs</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-800 font-bold"><?php echo $id ? 'Edit Post' : 'New Post'; ?></span>
    </div>

    <!-- Error Display -->
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Post Title</label>
                    <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($blog['title'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition" required oninput="generateSlug()">
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Permalink (Slug)</label>
                    <div class="flex items-center">
                        <span class="bg-gray-100 border border-r-0 border-gray-300 p-2.5 rounded-l-lg text-gray-500 text-sm"><?php echo $baseUrl; ?>/blog/</span>
                        <input type="text" name="slug" id="slug" value="<?php echo htmlspecialchars($blog['slug'] ?? ''); ?>" class="w-full border border-gray-300 p-2.5 rounded-r-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Content</label>
                    <textarea name="content" id="editor" class="rich-text-editor rich-text-full w-full border border-gray-300 p-2 rounded-lg" rows="20"><?php echo htmlspecialchars($blog['content'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-2">You can paste images directly into the editor.</p>
                </div>
            </div>
            
            <div class="space-y-6">
                <!-- Publish Box -->
                <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="font-bold mb-4 text-gray-800 border-b pb-2">Publishing</h3>
                    <div class="mb-4">
                        <label class="block text-xs font-bold mb-2 uppercase text-gray-500">Status</label>
                        <select name="status" class="w-full border border-gray-300 p-2 rounded bg-white">
                            <option value="published" <?php echo ($blog['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo ($blog['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold mb-2 uppercase text-gray-500">Content Layout</label>
                        <select name="layout" class="w-full border border-gray-300 p-2 rounded bg-white">
                            <option value="standard" <?php echo ($blog['layout'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard (Centered)</option>
                            <option value="wide" <?php echo ($blog['layout'] ?? '') === 'wide' ? 'selected' : ''; ?>>Wide Content</option>
                            <option value="full_width" <?php echo ($blog['layout'] ?? '') === 'full_width' ? 'selected' : ''; ?>>Full Width</option>
                        </select>
                    </div>
                    <?php if ($blog): ?>
                    <div class="mb-4 text-sm text-gray-600">
                        <p>Last Saved: <span class="font-medium"><?php echo date('M j, Y H:i', strtotime($blog['updated_at'])); ?></span></p>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 transition shadow-lg transform hover:-translate-y-0.5 btn-loading">
                        <?php echo $id ? 'Update Post' : 'Publish Post'; ?>
                    </button>
                    
                    <?php if($id && ($blog['status']??'') === 'published'): ?>
                        <a href="<?php echo $baseUrl; ?>/blog?slug=<?php echo $blog['slug']; ?>" target="_blank" class="block text-center mt-4 text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center justify-center gap-2 border border-blue-200 py-2 rounded bg-blue-50 hover:bg-blue-100 transition">
                            <i class="fas fa-external-link-alt"></i> View Live Post
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Featured Image & Banner Styling -->
                <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h3 class="font-bold mb-4 text-gray-800 border-b pb-2">Featured Image & Banner</h3>
                    
                    <div class="space-y-4">
                        <div class="w-full">
                             <label class="block text-xs font-bold mb-2 uppercase text-gray-500">Banner Image</label>
                             <div class="relative group cursor-pointer w-full h-48 border-2 <?php echo !empty($blog['image']) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-white hover:bg-gray-50 transition" onclick="document.getElementById('imageInput').click()">
                                 
                                 <input type="file" id="imageInput" name="image" class="hidden" accept="image/*" onchange="previewImage(this, 'previewContainer')">
                                 
                                 <div id="previewContainer" class="w-full h-full flex items-center justify-center">
                                     <?php if(!empty($blog['image'])): ?>
                                         <img src="<?php echo $baseUrl . '/' . $blog['image']; ?>" class="w-full h-full object-cover">
                                         <!-- Overlay -->
                                         <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                              <i class="fas fa-camera text-white text-3xl opacity-0 group-hover:opacity-100 transition"></i>
                                         </div>
                                     <?php else: ?>
                                         <div class="text-center text-gray-400">
                                             <i class="fas fa-cloud-upload-alt text-4xl mb-2"></i>
                                             <p class="text-sm font-medium">Click to upload banner</p>
                                             <p class="text-xs mt-1">1200x600px Recommended</p>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                             </div>
                        </div>

                        <div class="space-y-4 pt-4 border-t">
                             <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold mb-1">Text Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" name="banner_text_color" value="<?php echo htmlspecialchars($blogSettings['banner']['text_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                                        <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($blogSettings['banner']['text_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold mb-1">Heading Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" name="banner_heading_color" value="<?php echo htmlspecialchars($blogSettings['banner']['heading_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                                        <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($blogSettings['banner']['heading_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold mb-1">Subheading Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" name="banner_subheading_color" value="<?php echo htmlspecialchars($blogSettings['banner']['subheading_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                                        <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($blogSettings['banner']['subheading_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                                    </div>
                                </div>
                             </div>

                             <div>
                                <label class="block text-xs font-bold mb-1">Banner Background Color</label>
                                <div class="flex items-center gap-1">
                                    <input type="color" name="banner_bg_color" value="<?php echo htmlspecialchars($blogSettings['banner']['bg_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                                    <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($blogSettings['banner']['bg_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">Used if banner image is not uploaded.</p>
                             </div>

                             <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold mb-1">Btn Text</label>
                                    <input type="text" name="banner_btn_text" value="<?php echo htmlspecialchars($blogSettings['banner']['btn_text'] ?? ''); ?>" class="w-full border p-1.5 rounded text-xs" placeholder="e.g. Read More">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold mb-1">Btn Link</label>
                                    <input type="text" name="banner_btn_link" value="<?php echo htmlspecialchars($blogSettings['banner']['btn_link'] ?? ''); ?>" class="w-full border p-1.5 rounded text-xs" placeholder="e.g. #buy">
                                </div>
                             </div>

                             <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold mb-1">Btn BG</label>
                                    <input type="color" name="banner_btn_bg_color" value="<?php echo htmlspecialchars($blogSettings['banner']['btn_bg_color'] ?? '#ffffff'); ?>" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold mb-1">Btn Text</label>
                                    <input type="color" name="banner_btn_text_color" value="<?php echo htmlspecialchars($blogSettings['banner']['btn_text_color'] ?? '#000000'); ?>" class="w-full h-8 rounded cursor-pointer">
                                </div>
                             </div>

                             <div class="pt-2 border-t mt-4">
                                <label class="block text-xs font-bold mb-1">Page Background Color</label>
                                <div class="flex items-center gap-1">
                                    <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($blogSettings['page_bg_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                                    <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($blogSettings['page_bg_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Rest of the functions (slug, preview)
    function generateSlug() {
        const title = document.getElementById('title').value;
        const slugInput = document.getElementById('slug');
        if (slugInput.getAttribute('data-manual') === 'true') { return; }
        const slug = title.toLowerCase().replace(/[^\w ]+/g, '').replace(/ +/g, '-');
        slugInput.value = slug;
    }
    document.getElementById('slug').addEventListener('input', function() { this.setAttribute('data-manual', 'true'); });
    function previewImage(input, containerId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                const container = document.getElementById(containerId);
                const parent = container.parentElement;
                parent.classList.remove('border-dashed', 'border-gray-300');
                parent.classList.add('border-gray-200');
                container.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                         <i class="fas fa-camera text-white text-3xl opacity-0 group-hover:opacity-100 transition"></i>
                    </div>
                `;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
