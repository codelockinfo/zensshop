<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/RetryHandler.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$error = '';
$success = '';

// Process POST request BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = new Product();
    $retryHandler = new RetryHandler();
    
    try {
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'short_description' => $_POST['short_description'] ?? '',
            'category_id' => !empty($_POST['category_ids'][0]) ? $_POST['category_ids'][0] : null,
            'category_ids' => $_POST['category_ids'] ?? [],
            'price' => $_POST['price'] ?? 0,
            'sale_price' => $_POST['sale_price'] ?? null,
            'stock_quantity' => $_POST['stock_quantity'] ?? 0,
            'stock_status' => $_POST['stock_status'] ?? 'in_stock',
            'gender' => $_POST['gender'] ?? 'unisex',
            'brand' => $_POST['brand'] ?? null,
            'status' => $_POST['status'] ?? 'draft',
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'images' => []
        ];
        
        // Handle image uploads
        if (!empty($_POST['images'])) {
            $imagesJson = $_POST['images'];
            $imagePaths = json_decode($imagesJson, true);
            if (is_array($imagePaths) && !empty($imagePaths)) {
                // Filter out empty values
                $imagePaths = array_filter($imagePaths);
                $data['images'] = array_values($imagePaths);
                // Set first image as featured
                if (!empty($imagePaths[0])) {
                    $data['featured_image'] = $imagePaths[0];
                }
            }
        }
        
        // Handle direct file uploads (fallback)
        if (!empty($_FILES['product_images']['name'][0])) {
            $uploadDir = __DIR__ . '/../../assets/images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $uploadedImages = [];
            foreach ($_FILES['product_images']['name'] as $key => $name) {
                if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['product_images']['tmp_name'][$key];
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    $newName = 'product_' . uniqid() . '_' . time() . '.' . $extension;
                    $destination = $uploadDir . $newName;
                    
                    if (move_uploaded_file($tmpName, $destination)) {
                        $uploadedImages[] = '/oecom/assets/images/uploads/' . $newName;
                    }
                }
            }
            
            if (!empty($uploadedImages)) {
                $data['images'] = $uploadedImages;
                if (!empty($uploadedImages[0])) {
                    $data['featured_image'] = $uploadedImages[0];
                }
            }
        }
        
        $productId = $retryHandler->executeWithRetry(
            function() use ($product, $data) {
                return $product->create($data);
            },
            'Add Product',
            ['data' => $data]
        );
        
        // Redirect before any output
        header('Location: /oecom/admin/products/list.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Now include header after POST processing
$pageTitle = 'Add Product';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/functions.php';

$categories = $db->fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Add Product</h1>
    <p class="text-gray-600">Dashboard > Ecommerce > Add product</p>
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column -->
    <div class="space-y-6">
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product Information</h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="admin-form-group">
                    <label class="admin-form-label">Product name *</label>
                    <input type="text" 
                           name="name" 
                           required
                           maxlength="20"
                           placeholder="Enter product name"
                           class="admin-form-input">
                    <p class="text-sm text-gray-500 mt-1">Do not exceed 20 characters when entering the product name.</p>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Category *</label>
                    <select name="category_id" required class="admin-form-select">
                        <option value="">Choose category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Gender *</label>
                    <select name="gender" required class="admin-form-select">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="unisex">Unisex</option>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Brand *</label>
                    <select name="brand" class="admin-form-select">
                        <option value="">Choose brand</option>
                        <option value="Milano">Milano</option>
                        <option value="Luxury">Luxury</option>
                        <option value="Premium">Premium</option>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Description *</label>
                    <textarea name="description" 
                              required
                              maxlength="100"
                              placeholder="Description"
                              class="admin-form-input admin-form-textarea"></textarea>
                    <p class="text-sm text-gray-500 mt-1">Do not exceed 100 characters when entering the product name.</p>
                </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="space-y-6">
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Upload images</h2>
            <div class="grid grid-cols-2 gap-4 mb-4" id="imageUploadArea">
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="0">
                    <input type="file" accept="image/*" class="hidden image-file-input" data-index="0" multiple>
                    <div class="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
                    </div>
                    <div class="image-preview hidden"></div>
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="1">
                    <input type="file" accept="image/*" class="hidden image-file-input" data-index="1" multiple>
                    <div class="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
                    </div>
                    <div class="image-preview hidden"></div>
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="2">
                    <input type="file" accept="image/*" class="hidden image-file-input" data-index="2" multiple>
                    <div class="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
                    </div>
                    <div class="image-preview hidden"></div>
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="3">
                    <input type="file" accept="image/*" class="hidden image-file-input" data-index="3" multiple>
                    <div class="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
                    </div>
                    <div class="image-preview hidden"></div>
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            </div>
            <p class="text-sm text-gray-600">You need to add at least 4 images. Pay attention to the quality of the pictures you add, comply with the background color standards. Pictures must be in certain dimensions. Notice that the product shows all the details.</p>
            <input type="hidden" name="images" id="imagesInput" value="">
        </div>
        
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Add size</h2>
            <select class="admin-form-select mb-4">
                <option>EU - 44</option>
            </select>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 38.5</button>
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 39</button>
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 40</button>
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 41.5</button>
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 42</button>
                <button type="button" class="px-4 py-2 border rounded hover:bg-gray-100">EU - 43</button>
            </div>
        </div>
        
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product Details</h2>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Price *</label>
                <input type="number" 
                       name="price" 
                       step="0.01"
                       required
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Sale Price</label>
                <input type="number" 
                       name="sale_price" 
                       step="0.01"
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Stock Quantity *</label>
                <input type="number" 
                       name="stock_quantity" 
                       required
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Stock Status *</label>
                <select name="stock_status" required class="admin-form-select">
                    <option value="in_stock">In Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="on_backorder">On Backorder</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Status *</label>
                <select name="status" required class="admin-form-select">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="flex items-center">
                    <input type="checkbox" name="featured" class="mr-2">
                    <span>Featured Product</span>
                </label>
            </div>
        </div>
        
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product date</h2>
            <input type="date" 
                   value="<?php echo date('Y-m-d'); ?>"
                   class="admin-form-input">
        </div>
        
        <div class="flex space-x-4">
            <button type="submit" class="admin-btn admin-btn-primary flex-1">
                Add product
            </button>
            <button type="button" class="admin-btn border border-blue-500 text-blue-500 flex-1">
                Save product
            </button>
            <button type="button" class="admin-btn border border-gray-300 text-gray-600 flex-1">
                Schedule
            </button>
        </div>
            </form>
    </div>
</div>

<script src="/oecom/assets/js/admin-image-upload.js"></script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

