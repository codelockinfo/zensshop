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
    
    // Auto-generate slug from name if empty
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^\w\s-]/', '', $slug);
        $slug = preg_replace('/[\s_-]+/', '-', $slug);
        $slug = trim($slug, '-');
    }

    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $sortOrder = $_POST['sort_order'] ?? 0;
    
    // Handle Image Upload
    $imagePath = ($id && $category) ? $category['image'] : '';
    
    if (isset($_FILES['image'])) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $uploadFileDir = __DIR__ . '/../../assets/categories/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }
                
                $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
                $newFileName = time() . '_' . $cleanFileName;
                $dest_path = $uploadFileDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagePath = 'assets/categories/' . $newFileName;
                } else {
                     $error = "Failed to move uploaded image file.";
                }
            } else {
                $error = "Invalid image file type. Allowed: jpg, png, webp.";
            }
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
             $error = "Image upload failed with error code: " . $_FILES['image']['error'];
        }
    } else if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        $imagePath = '';
    }

    // Handle Banner Upload
    $bannerPath = ($id && $category) ? ($category['banner'] ?? '') : '';

    if (isset($_FILES['banner'])) {
        if ($_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['banner']['tmp_name'];
            $fileName = $_FILES['banner']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                $uploadFileDir = __DIR__ . '/../../assets/categories/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }
                
                $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
                $newFileName = 'banner_' . time() . '_' . $cleanFileName;
                $dest_path = $uploadFileDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $bannerPath = 'assets/categories/' . $newFileName;
                } else {
                    $error = "Failed to move uploaded banner file. Check directory permissions.";
                }
            } else {
                $error = "Invalid banner file type. Allowed: jpg, png, webp.";
            }
        } elseif ($_FILES['banner']['error'] !== UPLOAD_ERR_NO_FILE) {
             switch ($_FILES['banner']['error']) {
                 case UPLOAD_ERR_INI_SIZE:
                 case UPLOAD_ERR_FORM_SIZE:
                     $error = "Banner file is too large.";
                     break;
                 default:
                     $error = "Banner upload failed with error code: " . $_FILES['banner']['error'];
             }
        }
    }
    
    if (isset($_POST['remove_banner']) && $_POST['remove_banner'] == '1') {
        $bannerPath = ''; 
    }

    $icon = $_POST['icon'] ?? '';

    try {
        if ($id && $category) {
            $db->execute(
                "UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, sort_order = ?, image = ?, banner = ?, icon = ? WHERE id = ? AND store_id = ?",
                [$name, $slug, $description, $status, $sortOrder, $imagePath, $bannerPath, $icon, $id, $storeId]
            );
            header('Location: ' . url('admin/categories/list.php'));
            exit;
        } else {
            $db->insert(
                "INSERT INTO categories (name, slug, description, status, sort_order, image, banner, icon, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $slug, $description, $status, $sortOrder, $imagePath, $bannerPath, $icon, $storeId]
            );
            header('Location: ' . url('admin/categories/list.php'));
            exit;
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false && strpos($msg, 'unique_slug_store') !== false) {
            $error = "A category with the slug '$slug' already exists. Please use a unique slug.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        error_log("Manage Category Error: " . $msg);
    }
}

// Now include header after POST processing
$pageTitle = 'Manage Category';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold pt-4 pl-2">Category information</h1>
    <p class="text-sm md:text-base text-gray-600 pl-2">
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
            <!-- Upload Image -->
            <div class="admin-form-group">
                <label class="admin-form-label">Upload image *</label>
                <div class="category-image-upload border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors relative" id="imageUploadArea">
                    <input type="file" name="image" id="fileInput" accept="image/*" class="hidden">
                    <div class="upload-placeholder <?php echo !empty($category['image']) ? 'hidden' : ''; ?>">
                        <i class="fas fa-cloud-upload-alt text-5xl text-blue-500 mb-3"></i>
                        <p class="text-sm text-gray-600">
                            Drop image here<br><span class="text-xs text-gray-400">(Recommended 3:4)</span>
                        </p>
                    </div>
                    <div class="image-preview <?php echo !empty($category['image']) ? '' : 'hidden'; ?> mt-4 relative inline-block">
                        <?php if (!empty($category['image'])): ?>
                            <img src="<?php echo $baseUrl . '/' . $category['image']; ?>" alt="Preview" class="h-32 object-cover rounded border">
                        <?php endif; ?>
                    </div>
                    <button type="button" id="removeImageBtn" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 <?php echo !empty($category['image']) ? '' : 'hidden'; ?>">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <input type="hidden" name="remove_image" id="removeImageInput" value="0">
            </div>

            <!-- Upload Banner -->
            <div class="admin-form-group">
                <label class="admin-form-label">Upload banner</label>
                <div class="category-banner-upload border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors relative" id="bannerUploadArea">
                    <input type="file" name="banner" id="bannerInput" accept="image/*" class="hidden">
                    <div class="banner-placeholder <?php echo !empty($category['banner']) ? 'hidden' : ''; ?>">
                        <i class="fas fa-image text-5xl text-blue-500 mb-3"></i>
                        <p class="text-sm text-gray-600">
                            Drop banner here<br><span class="text-xs text-gray-400">(Recommended 16:9)</span>
                        </p>
                    </div>
                    <div class="banner-preview <?php echo !empty($category['banner']) ? '' : 'hidden'; ?> mt-4 relative inline-block w-full">
                        <?php if (!empty($category['banner'])): ?>
                            <img src="<?php echo $baseUrl . '/' . $category['banner']; ?>" alt="Banner Preview" class="w-full h-32 object-cover rounded border">
                        <?php endif; ?>
                    </div>
                    <button type="button" id="removeBannerBtn" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 <?php echo !empty($category['banner']) ? '' : 'hidden'; ?>">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <input type="hidden" name="remove_banner" id="removeBannerInput" value="0">
            </div>
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
            <button type="submit" class="admin-btn admin-btn-primary px-6 py-2.5 btn-loading">
                Save
            </button>
            <a href="<?php echo url('admin/categories/list.php'); ?>" class="admin-btn border border-gray-300 text-gray-600 px-6 py-2.5">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ========== Image Upload ==========
    var imageArea = document.getElementById('imageUploadArea');
    var fileInput = document.getElementById('fileInput');
    var imagePreview = document.querySelector('.image-preview');
    var imagePlaceholder = document.querySelector('.upload-placeholder');
    var removeImageBtn = document.getElementById('removeImageBtn');
    var removeImageInput = document.getElementById('removeImageInput');

    if (imageArea && fileInput) {
        imageArea.addEventListener('click', function(e) {
            // Only skip click if user clicked the remove button itself
            var clickedRemove = removeImageBtn && (e.target === removeImageBtn || removeImageBtn.contains(e.target));
            if (!clickedRemove) {
                fileInput.click();
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = '<img src="' + e.target.result + '" class="h-32 object-cover rounded border" style="pointer-events:none;">';
                    imagePreview.classList.remove('hidden');
                    imagePlaceholder.classList.add('hidden');
                    if (removeImageBtn) removeImageBtn.classList.remove('hidden');
                    removeImageInput.value = '0';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            fileInput.value = '';
            imagePreview.innerHTML = '';
            imagePreview.classList.add('hidden');
            imagePlaceholder.classList.remove('hidden');
            removeImageBtn.classList.add('hidden');
            removeImageInput.value = '1';
        });
    }

    // ========== Banner Upload ==========
    var bannerArea = document.getElementById('bannerUploadArea');
    var bannerInput = document.getElementById('bannerInput');
    var bannerPreview = document.querySelector('.banner-preview');
    var bannerPlaceholder = document.querySelector('.banner-placeholder');
    var removeBannerBtn = document.getElementById('removeBannerBtn');
    var removeBannerInput = document.getElementById('removeBannerInput');

    if (bannerArea && bannerInput) {
        bannerArea.addEventListener('click', function(e) {
            var clickedRemove = removeBannerBtn && (e.target === removeBannerBtn || removeBannerBtn.contains(e.target));
            if (!clickedRemove) {
                bannerInput.click();
            }
        });
    }

    if (bannerInput) {
        bannerInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    bannerPreview.innerHTML = '<img src="' + e.target.result + '" class="w-full h-32 object-cover rounded border" style="pointer-events:none;">';
                    bannerPreview.classList.remove('hidden');
                    bannerPlaceholder.classList.add('hidden');
                    if (removeBannerBtn) removeBannerBtn.classList.remove('hidden');
                    removeBannerInput.value = '0';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (removeBannerBtn) {
        removeBannerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            bannerInput.value = '';
            bannerPreview.innerHTML = '';
            bannerPreview.classList.add('hidden');
            bannerPlaceholder.classList.remove('hidden');
            removeBannerBtn.classList.add('hidden');
            removeBannerInput.value = '1';
        });
    }

    // Make existing preview images non-blocking for clicks
    document.querySelectorAll('.image-preview img, .banner-preview img').forEach(function(img) {
        img.style.pointerEvents = 'none';
    });

    // ========== Auto-generate slug from name ==========
    var nameInput = document.querySelector('input[name="name"]');
    var slugInput = document.querySelector('input[name="slug"]');

    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            var slug = this.value
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

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
