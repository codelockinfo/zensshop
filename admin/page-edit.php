<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

$baseUrl = getBaseUrl();
$pageId = $_GET['page_id'] ?? null;
$page = null;
$contentData = ['html' => '', 'banner' => [], 'seo' => []];

if ($pageId) {
    $page = $db->fetchOne("SELECT * FROM pages WHERE page_id = ? AND store_id = ?", [$pageId, $storeId]);
    if (!$page) {
        die("Page not found or access denied.");
    }
    // Decode content JSON
    if (!empty($page['content'])) {
        $decoded = json_decode($page['content'], true);
        if (is_array($decoded)) {
            $contentData = array_merge($contentData, $decoded);
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    if (empty($slug)) {
        // Generate slug from title
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    } else {
         $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug)));
    }
    
    $status = $_POST['status'];
    $htmlContent = $_POST['html_content'] ?? '';
    
    // File Upload (Banner)
    $bannerImg = $contentData['banner']['image'] ?? '';
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fname = time() . '_p_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['banner_image']['name']));
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . $fname)) {
            $bannerImg = 'assets/images/uploads/' . $fname;
        }
    }

    // Prepare JSON Content
    $newContentData = [
        'html' => $htmlContent,
        'banner' => [
            'image' => $bannerImg,
            'heading' => $_POST['banner_heading'] ?? '',
            'subheading' => $_POST['banner_subheading'] ?? '',
            'btn_text' => $_POST['banner_btn_text'] ?? '',
            'btn_link' => $_POST['banner_btn_link'] ?? '',
            'bg_color' => $_POST['banner_bg_color'] ?? '#ffffff',
            'text_color' => $_POST['banner_text_color'] ?? '#000000',
            'heading_color' => $_POST['banner_heading_color'] ?? '#000000',
            'subheading_color' => $_POST['banner_subheading_color'] ?? '#666666',
            'btn_bg_color' => $_POST['banner_btn_bg_color'] ?? '#ffffff',
            'btn_text_color' => $_POST['banner_btn_text_color'] ?? '#000000',
            'btn_hover_bg_color' => $_POST['banner_btn_hover_bg_color'] ?? '#f3f4f6',
            'btn_hover_text_color' => $_POST['banner_btn_hover_text_color'] ?? '#000000'
        ],
        'seo' => [
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? ''
        ],
        'settings' => [
            'content_alignment' => $_POST['content_alignment'] ?? 'left',
            'layout' => $_POST['content_layout'] ?? 'standard',
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff'
        ]
    ];
    
    $jsonContent = json_encode($newContentData);

    try {
        if ($pageId) {
            $db->execute(
                "UPDATE pages SET title=?, slug=?, content=?, status=? WHERE page_id=? AND store_id=?", 
                [$title, $slug, $jsonContent, $status, $pageId, $storeId]
            );
            $_SESSION['flash_success'] = "Page updated successfully!";
            // Redirect to same page with page_id
            header("Location: page-edit.php?page_id=" . $pageId);
            exit;
        } else {
            // Check slug uniqueness
            $exists = $db->fetchOne("SELECT id FROM pages WHERE slug = ? AND store_id = ?", [$slug, $storeId]);
            if ($exists) {
                $slug .= '-' . time(); // Append timestamp to make unique
            }
            
            // Generate unique 10-digit Page ID
            $page_uid = rand(1000000000, 9999999999);
            while ($db->fetchOne("SELECT id FROM pages WHERE page_id = ?", [$page_uid])) {
                $page_uid = rand(1000000000, 9999999999);
            }

            $db->execute(
                "INSERT INTO pages (store_id, title, slug, content, status, page_id) VALUES (?, ?, ?, ?, ?, ?)", 
                [$storeId, $title, $slug, $jsonContent, $status, $page_uid]
            );
            $_SESSION['flash_success'] = "Page created successfully!";
            
            // Redirect to edit page with new page_id
            header("Location: page-edit.php?page_id=" . $page_uid);
            exit;
        }
    } catch (Exception $e) {
        $error = "Error saving page: " . $e->getMessage();
    }
}

$pageTitle = $pageId ? 'Edit Page' : 'Create Page';
require_once __DIR__ . '/../includes/admin-header.php';
?>



<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold"><?php echo $pageTitle; ?></h1>
        <p class="text-gray-600"><a href="pages.php" class="hover:underline">Pages</a> > <?php echo htmlspecialchars($page['title'] ?? 'New Page'); ?></p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="bg-white rounded shadow-lg p-6">
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Main Content Area -->
        <div class="md:col-span-2 space-y-6">
            
            <div>
                <label class="block text-sm font-bold mb-2">Page Title</label>
                <input type="text" name="title" id="pageTitle" value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>" class="w-full border p-2 rounded" required placeholder="e.g. About Us">
            </div>
            
            <div>
                <label class="block text-sm font-bold mb-2">Page Content</label>
                <textarea name="html_content" id="editor" class="rich-text-editor rich-text-full"><?php echo htmlspecialchars($contentData['html'] ?? ''); ?></textarea>
            </div>
            
            <!-- SEO Section -->
            <div class="border-t pt-4">
                <h3 class="font-bold text-lg mb-4">SEO Settings</h3>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-bold mb-1">Meta Title</label>
                        <input type="text" name="meta_title" value="<?php echo htmlspecialchars($contentData['seo']['meta_title'] ?? ''); ?>" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Meta Description</label>
                        <textarea name="meta_description" class="w-full border p-2 rounded" rows="2"><?php echo htmlspecialchars($contentData['seo']['meta_description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

        </div>
        
        <!-- Sidebar Options -->
        <div class="space-y-6">
            
            <div class="bg-gray-50 p-4 rounded border">
                <label class="block text-sm font-bold mb-2">Status</label>
                <select name="status" class="w-full border p-2 rounded bg-white">
                    <option value="active" <?php echo ($page['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($page['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                </select>
            </div>

            <!-- Content Layout Setting -->
            <div class="bg-gray-50 p-4 rounded border">
                <label class="block text-sm font-bold mb-2">Content Layout</label>
                <select name="content_layout" class="w-full border p-2 rounded bg-white">
                    <?php $currentLayout = $contentData['settings']['layout'] ?? 'standard'; ?>
                    <option value="standard" <?php echo $currentLayout === 'standard' ? 'selected' : ''; ?>>Standard (Centered)</option>
                    <option value="wide" <?php echo $currentLayout === 'wide' ? 'selected' : ''; ?>>Wide Content</option>
                    <option value="full_width" <?php echo $currentLayout === 'full_width' ? 'selected' : ''; ?>>Full Width</option>
                </select>
            </div>


            <div class="bg-gray-50 p-4 rounded border">
                <label class="block text-sm font-bold mb-2">URL Slug</label>
                <input type="text" name="slug" id="pageSlug" value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>" class="w-full border p-2 rounded bg-white" placeholder="Auto-generated if empty">
                <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate from title.</p>
            </div>



            <!-- Banner Settings -->
            <div class="bg-gray-50 p-4 rounded border">
                <h3 class="font-bold text-sm mb-3 text-blue-800">Banner Settings</h3>
                
                <div class="mb-5">
                    <label class="block text-xs font-bold mb-1">Banner Image</label>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-2 text-center hover:bg-gray-50 transition relative" id="bannerUploadContainer">
                        
                        <!-- Hidden File Input -->
                        <input type="file" name="banner_image" id="bannerInput" class="hidden" accept="image/*">
                        
                        <!-- Preview Image Area -->
                        <div class="cursor-pointer" onclick="document.getElementById('bannerInput').click()">
                            <?php 
                            $bgImage = !empty($contentData['banner']['image']) ? $baseUrl . '/' . $contentData['banner']['image'] : ''; 
                            $hasImage = !empty($bgImage);
                            ?>
                            
                            <!-- Placeholder when no image -->
                            <div id="bannerPlaceholder" class="<?php echo $hasImage ? 'hidden' : 'block'; ?> py-6">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                <p class="text-xs text-gray-500">Click to upload banner</p>
                            </div>
                            
                            <!-- Check URI for correctness just in case -->
                            <img id="bannerPreview" 
                                 src="<?php echo htmlspecialchars($bgImage); ?>" 
                                 class="<?php echo $hasImage ? 'block' : 'hidden'; ?> w-full h-40 object-cover rounded shadow-sm">
                        </div>

                        <!-- Remove/Change Button -->
                        <?php if($hasImage): ?>
                        <button type="button" onclick="document.getElementById('bannerInput').click()" class="absolute bottom-2 right-2 bg-white text-gray-700 text-xs px-2 py-1 rounded shadow hover:bg-gray-100">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Recommended size: 1200x400px</p>
                </div>
                
                <script>
                    document.getElementById('bannerInput').addEventListener('change', function(event) {
                        const file = event.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const preview = document.getElementById('bannerPreview');
                                const placeholder = document.getElementById('bannerPlaceholder');
                                
                                preview.src = e.target.result;
                                preview.classList.remove('hidden');
                                preview.classList.add('block');
                                
                                placeholder.classList.add('hidden');
                                placeholder.classList.remove('block');
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                </script>
                
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Heading</label>
                    <input type="text" name="banner_heading" value="<?php echo htmlspecialchars($contentData['banner']['heading'] ?? ''); ?>" class="w-full border p-2 rounded text-sm bg-white">
                </div>
                
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Subheading</label>
                    <textarea name="banner_subheading" class="w-full border p-2 rounded text-sm bg-white" rows="2"><?php echo htmlspecialchars($contentData['banner']['subheading'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Button Text</label>
                    <input type="text" name="banner_btn_text" value="<?php echo htmlspecialchars($contentData['banner']['btn_text'] ?? ''); ?>" class="w-full border p-2 rounded text-sm bg-white">
                </div>
                
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Button Link</label>
                    <input type="text" name="banner_btn_link" value="<?php echo htmlspecialchars($contentData['banner']['btn_link'] ?? ''); ?>" class="w-full border p-2 rounded text-sm bg-white">
                </div>

                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div>
                        <label class="block text-xs font-bold mb-1">Background Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_bg_color" value="<?php echo htmlspecialchars($contentData['banner']['bg_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['bg_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Text Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_text_color" value="<?php echo htmlspecialchars($contentData['banner']['text_color'] ?? '#000000'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['text_color'] ?? '#000000'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div>
                        <label class="block text-xs font-bold mb-1">Heading Color</label>
                         <div class="flex items-center gap-1">
                            <input type="color" name="banner_heading_color" value="<?php echo htmlspecialchars($contentData['banner']['heading_color'] ?? '#000000'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['heading_color'] ?? '#000000'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Subheading Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_subheading_color" value="<?php echo htmlspecialchars($contentData['banner']['subheading_color'] ?? '#666666'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['subheading_color'] ?? '#666666'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div>
                        <label class="block text-xs font-bold mb-1">Button BG Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_btn_bg_color" value="<?php echo htmlspecialchars($contentData['banner']['btn_bg_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['btn_bg_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Button Text Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_btn_text_color" value="<?php echo htmlspecialchars($contentData['banner']['btn_text_color'] ?? '#000000'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['btn_text_color'] ?? '#000000'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div>
                        <label class="block text-xs font-bold mb-1">Hover BG Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_btn_hover_bg_color" value="<?php echo htmlspecialchars($contentData['banner']['btn_hover_bg_color'] ?? '#f3f4f6'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['btn_hover_bg_color'] ?? '#f3f4f6'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Hover Text Color</label>
                        <div class="flex items-center gap-1">
                            <input type="color" name="banner_btn_hover_text_color" value="<?php echo htmlspecialchars($contentData['banner']['btn_hover_text_color'] ?? '#000000'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                            <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['banner']['btn_hover_text_color'] ?? '#000000'); ?>" class="w-full border p-1 rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="mt-4 border-t pt-4">
                    <label class="block text-xs font-bold mb-1">Page Background Color</label>
                    <div class="flex items-center gap-1">
                        <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($contentData['settings']['page_bg_color'] ?? '#ffffff'); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0 shadow-sm">
                        <input type="text" oninput="this.previousElementSibling.value = this.value" value="<?php echo htmlspecialchars($contentData['settings']['page_bg_color'] ?? '#ffffff'); ?>" class="w-full border p-1 rounded text-xs uppercase" readonly>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded shadow hover:bg-blue-700 transition btn-loading">
                <?php echo $pageId ? 'Update Page' : 'Publish Page'; ?>
            </button>
            
            <?php if ($pageId): ?>
            <a href="<?php echo $baseUrl; ?>/page.php?slug=<?php echo $page['slug']; ?>" target="_blank" class="block text-center w-full bg-green-600 text-white font-bold py-2 px-4 rounded shadow hover:bg-green-700 transition mt-2">
                View Live
            </a>
            <?php endif; ?>

        </div>
    </div>
</form>




<script>
// Auto-slug generation
var titleInput = document.getElementById('pageTitle');
var slugInput = document.getElementById('pageSlug');

if (titleInput && slugInput) {
    slugInput.addEventListener('input', function() {
        this.setAttribute('data-manual', 'true');
    });

    titleInput.addEventListener('input', function() {
        if (slugInput.getAttribute('data-manual') !== 'true') {
            const slug = this.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)+/g, '');
            slugInput.value = slug;
        }
    });
}
</script>




<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
