<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$id = $_GET['id'] ?? null;
$category = null;
$error = '';
$success = '';

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

if ($id) {
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
}

// Process POST request BEFORE including header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $sortOrder = $_POST['sort_order'] ?? 0;
    
    // Handle Image Upload
    $imagePath = ($id && $category) ? $category['image'] : ''; // Default to existing
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = __DIR__ . '/../../assets/images/categories/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }
            
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $imagePath = 'assets/images/categories/' . $newFileName;
            }
        }
    } else if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        $imagePath = ''; // User removed image
    }

    // Handle Banner Upload
    $bannerPath = ($id && $category) ? ($category['banner'] ?? '') : ''; // Default to existing

    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['banner']['tmp_name'];
        $fileName = $_FILES['banner']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = __DIR__ . '/../../assets/images/categories/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }
            
            $newFileName = 'banner_' . md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $bannerPath = 'assets/images/categories/' . $newFileName;
            }
        }
    } else if (isset($_POST['remove_banner']) && $_POST['remove_banner'] == '1') {
        $bannerPath = ''; // User removed banner
    }

    $icon = $_POST['icon'] ?? '';

    try {
        if ($id && $category) {
            $db->execute(
                "UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, sort_order = ?, image = ?, banner = ?, icon = ? WHERE id = ? AND store_id = ?",
                [$name, $slug, $description, $status, $sortOrder, $imagePath, $bannerPath, $icon, $id, $storeId]
            );
            $success = 'Category updated successfully!';
            // Refresh category data
            $category = $db->fetchOne("SELECT * FROM categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
        } else {
            $db->insert(
                "INSERT INTO categories (name, slug, description, status, sort_order, image, banner, icon, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $slug, $description, $status, $sortOrder, $imagePath, $bannerPath, $icon, $storeId]
            );
            header('Location: ' . $baseUrl . '/admin/categories/list.php');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Now include header after POST processing
$pageTitle = 'Manage Category';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Category information</h1>
    <p class="text-sm md:text-base text-gray-600">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
        <a href="<?php echo url('admin/categories/list.php'); ?>" class="hover:text-blue-600">Category</a> > 
        <?php echo $id ? 'Edit category' : 'New category'; ?>
    </p>
</div>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error mb-4">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="admin-card">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="admin-form-group">
            <label class="admin-form-label">Product name *</label>
            <input type="text" 
                   name="name" 
                   required
                   value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                   placeholder="Category name"
                   class="admin-form-input">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="admin-form-group">
                <label class="admin-form-label">Upload image *</label>
                <div class="category-image-upload border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors relative" onclick="document.getElementById('fileInput').click()">
                    <input type="file" name="image" id="fileInput" accept="image/*" class="hidden category-image-input">
                    <div class="upload-placeholder <?php echo !empty($category['image']) ? 'hidden' : ''; ?>">
                        <i class="fas fa-cloud-upload-alt text-5xl text-blue-500 mb-3"></i>
                        <p class="text-sm text-gray-600">
                            Drop image here
                        </p>
                    </div>
                    <!-- Preview Container -->
                    <div class="image-preview <?php echo !empty($category['image']) ? '' : 'hidden'; ?> mt-4 relative inline-block">
                        <?php if (!empty($category['image'])): ?>
                            <img src="<?php echo $baseUrl . '/' . $category['image']; ?>" alt="Preview" class="h-32 object-cover rounded border">
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="removeImageBtn" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 <?php echo !empty($category['image']) ? '' : 'hidden'; ?>">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <input type="hidden" name="remove_image" id="removeImageInput" value="0">
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label">Upload banner</label>
                <div class="category-banner-upload border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors relative" onclick="document.getElementById('bannerInput').click()">
                    <input type="file" name="banner" id="bannerInput" accept="image/*" class="hidden category-banner-input">
                    <div class="banner-placeholder <?php echo !empty($category['banner']) ? 'hidden' : ''; ?>">
                        <i class="fas fa-image text-5xl text-blue-500 mb-3"></i>
                        <p class="text-sm text-gray-600">
                            Drop banner here
                        </p>
                    </div>
                    <!-- Preview Container -->
                    <div class="banner-preview <?php echo !empty($category['banner']) ? '' : 'hidden'; ?> mt-4 relative inline-block w-full">
                        <?php if (!empty($category['banner'])): ?>
                            <img src="<?php echo $baseUrl . '/' . $category['banner']; ?>" alt="Banner Preview" class="w-full h-32 object-cover rounded border">
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="removeBannerBtn" class="remove-banner-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 <?php echo !empty($category['banner']) ? '' : 'hidden'; ?>">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <input type="hidden" name="remove_banner" id="removeBannerInput" value="0">
            </div>
        </div>
        
        <script>
        // Inline script for better context handling
        document.addEventListener('DOMContentLoaded', function() {
            // Main Image Logic
            const fileInput = document.getElementById('fileInput');
            const previewContainer = document.querySelector('.image-preview');
            const placeholder = document.querySelector('.upload-placeholder');
            const removeBtn = document.getElementById('removeImageBtn');
            const removeInput = document.getElementById('removeImageInput');

            fileInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.innerHTML = `<img src="${e.target.result}" class="h-32 object-cover rounded border">`;
                        previewContainer.classList.remove('hidden');
                        placeholder.classList.add('hidden');
                        removeBtn.classList.remove('hidden');
                        removeInput.value = '0'; // Reset remove flag
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            removeBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent opening file dialog
                fileInput.value = ''; // Clear input
                previewContainer.innerHTML = '';
                previewContainer.classList.add('hidden');
                placeholder.classList.remove('hidden');
                removeBtn.classList.add('hidden');
                removeInput.value = '1'; // Mark for removal
            });

            // Banner Logic
            const bannerInput = document.getElementById('bannerInput');
            const bannerPreview = document.querySelector('.banner-preview');
            const bannerPlaceholder = document.querySelector('.banner-placeholder');
            const removeBannerBtn = document.getElementById('removeBannerBtn');
            const removeBannerInput = document.getElementById('removeBannerInput');

            bannerInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        bannerPreview.innerHTML = `<img src="${e.target.result}" class="w-full h-32 object-cover rounded border">`;
                        bannerPreview.classList.remove('hidden');
                        bannerPlaceholder.classList.add('hidden');
                        removeBannerBtn.classList.remove('hidden');
                        removeBannerInput.value = '0'; // Reset remove flag
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            removeBannerBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent opening file dialog
                bannerInput.value = ''; // Clear input
                bannerPreview.innerHTML = '';
                bannerPreview.classList.add('hidden');
                bannerPlaceholder.classList.remove('hidden');
                removeBannerBtn.classList.add('hidden');
                removeBannerInput.value = '1'; // Mark for removal
            });

            // Auto-generate slug from name
            const nameInput = document.querySelector('input[name="name"]');
            const slugInput = document.querySelector('input[name="slug"]');

            if (nameInput && slugInput) {
                nameInput.addEventListener('input', function() {
                    const slug = this.value
                        .toLowerCase()
                        .trim()
                        .replace(/[^\w\s-]/g, '')
                        .replace(/[\s_-]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    slugInput.value = slug;
                });
            }
        });
        </script>
        
        <div class="admin-form-group">
            <label class="admin-form-label">Select category icon</label>
            <select name="icon" class="admin-form-select">
                <option value="">Select icon</option>
                <option value="fa-gem" <?php echo ($category['icon'] ?? '') === 'fa-gem' ? 'selected' : ''; ?>>💎 Gem</option>
                <option value="fa-ring" <?php echo ($category['icon'] ?? '') === 'fa-ring' ? 'selected' : ''; ?>>💍 Ring</option>
                <option value="fa-necklace" <?php echo ($category['icon'] ?? '') === 'fa-necklace' ? 'selected' : ''; ?>>📿 Necklace</option>
                <option value="fa-earrings" <?php echo ($category['icon'] ?? '') === 'fa-earrings' ? 'selected' : ''; ?>>👂 Earrings</option>
                <option value="fa-bracelet" <?php echo ($category['icon'] ?? '') === 'fa-bracelet' ? 'selected' : ''; ?>>⌚ Bracelet</option>
            </select>
        </div>
        
        <div class="admin-form-group">
            <label class="admin-form-label">Slug</label>
            <input type="text" 
                   name="slug" 
                   value="<?php echo htmlspecialchars($category['slug'] ?? ''); ?>"
                   placeholder="category-slug"
                   class="admin-form-input">
        </div>
        
        <div class="admin-form-group">
            <label class="admin-form-label">Description</label>
            <textarea name="description" 
                      rows="4"
                      placeholder="Category description"
                      class="admin-form-input admin-form-textarea"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="admin-form-group">
            <label class="admin-form-label">Status *</label>
            <select name="status" required class="admin-form-select">
                <option value="active" <?php echo ($category['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($category['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="admin-form-group">
            <label class="admin-form-label">Sort Order</label>
            <input type="number" 
                   name="sort_order" 
                   value="<?php echo htmlspecialchars($category['sort_order'] ?? '0'); ?>"
                   placeholder="0"
                   class="admin-form-input">
            <p class="text-xs text-gray-500 mt-1">Lower numbers appear first. Default is 0.</p>
        </div>
        
        <div class="flex space-x-4 mt-6">
            <button type="submit" class="admin-btn admin-btn-primary px-6 py-2.5">
                Save
            </button>
            <a href="<?php echo $baseUrl; ?>/admin/categories/list.php" class="admin-btn border border-gray-300 text-gray-600 px-6 py-2.5">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

