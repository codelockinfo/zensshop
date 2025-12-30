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

if ($id) {
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
}

// Process POST request BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $sortOrder = $_POST['sort_order'] ?? 0;
    
    try {
        if ($id && $category) {
            $db->execute(
                "UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, sort_order = ? WHERE id = ?",
                [$name, $slug, $description, $status, $sortOrder, $id]
            );
            $success = 'Category updated successfully!';
        } else {
            $db->insert(
                "INSERT INTO categories (name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, ?)",
                [$name, $slug, $description, $status, $sortOrder]
            );
            // Redirect before any output
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

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">Category information</h1>
    <p class="text-sm text-gray-500">Dashboard > Category > <?php echo $id ? 'Edit category' : 'New category'; ?></p>
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
    <form method="POST" action="">
        <div class="admin-form-group">
            <label class="admin-form-label">Product name *</label>
            <input type="text" 
                   name="name" 
                   required
                   value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                   placeholder="Category name"
                   class="admin-form-input">
        </div>
        
        <div class="admin-form-group">
            <label class="admin-form-label">Upload images *</label>
            <div class="category-image-upload border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-500 transition-colors relative">
                <input type="file" accept="image/*" class="hidden category-image-input" multiple>
                <div class="upload-placeholder">
                    <i class="fas fa-cloud-upload-alt text-5xl text-blue-500 mb-3"></i>
                    <p class="text-sm text-gray-600">
                        Drop your images here or <span class="text-blue-500 underline cursor-pointer">click to browse</span>.
                    </p>
                </div>
                <div class="image-preview hidden grid grid-cols-4 gap-3 mt-4"></div>
                <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <input type="hidden" name="image" id="categoryImage" value="<?php echo htmlspecialchars($category['image'] ?? ''); ?>">
        </div>
        
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

<script>
// Category image upload
document.addEventListener('DOMContentLoaded', function() {
    const uploadBox = document.querySelector('.category-image-upload');
    const fileInput = uploadBox.querySelector('.category-image-input');
    const placeholder = uploadBox.querySelector('.upload-placeholder');
    const preview = uploadBox.querySelector('.image-preview');
    const removeBtn = uploadBox.querySelector('.remove-image-btn');
    const hiddenInput = document.getElementById('categoryImage');
    
    // Click to browse
    uploadBox.addEventListener('click', function(e) {
        if (e.target !== removeBtn && !e.target.closest('.remove-image-btn')) {
            fileInput.click();
        }
    });
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                const imageUrl = e.target.result;
                preview.innerHTML = `<img src="${imageUrl}" alt="Preview" class="w-full h-24 object-cover rounded">`;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
                removeBtn.classList.remove('hidden');
                if (hiddenInput) {
                    hiddenInput.value = imageUrl;
                }
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Drag and drop
    uploadBox.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadBox.classList.add('border-blue-500', 'bg-blue-50');
    });
    
    uploadBox.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadBox.classList.remove('border-blue-500', 'bg-blue-50');
    });
    
    uploadBox.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadBox.classList.remove('border-blue-500', 'bg-blue-50');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
    
    // Remove image
    removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        preview.classList.add('hidden');
        preview.innerHTML = '';
        placeholder.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        fileInput.value = '';
        if (hiddenInput) {
            hiddenInput.value = '';
        }
    });
    
    // Show existing image if editing
    <?php if (!empty($category['image'])): ?>
    preview.innerHTML = `<img src="<?php echo htmlspecialchars($category['image']); ?>" alt="Preview" class="w-full h-24 object-cover rounded">`;
    preview.classList.remove('hidden');
    placeholder.classList.add('hidden');
    removeBtn.classList.remove('hidden');
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

