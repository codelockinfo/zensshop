<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/RetryHandler.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$error = '';
$success = '';

// Process POST request BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = new Product();
    $retryHandler = new RetryHandler();
    
    // Check if POST data is empty but method is POST and content-length > 0 (post_max_size exceeded)
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "The total size of your images and data exceeds the server limit (" . ini_get('post_max_size') . "). Please upload fewer or smaller images.";
    } else {
        try {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'short_description' => $_POST['short_description'] ?? '',
                // Fix category mapping: check both singular and array versions
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : (!empty($_POST['category_ids'][0]) ? $_POST['category_ids'][0] : null),
                'category_ids' => !empty($_POST['category_ids']) ? $_POST['category_ids'] : (!empty($_POST['category_id']) ? [$_POST['category_id']] : []),
                'price' => $_POST['price'] ?? 0,
                'currency' => $_POST['currency'] ?? 'INR',
                'sale_price' => !empty($_POST['sale_price']) ? $_POST['sale_price'] : null,
                'cost_per_item' => $_POST['cost_per_item'] ?? 0,
                'total_expense' => $_POST['total_expense'] ?? 0,
                'stock_quantity' => $_POST['stock_quantity'] ?? 0,
                'stock_status' => $_POST['stock_status'] ?? 'in_stock',
                'gender' => $_POST['gender'] ?? 'unisex',
                'brand' => $_POST['brand'] ?? null,
                'status' => $_POST['status'] ?? 'draft',
                // Fix SKU: If empty, use NULL to avoid duplicate key error ('')
                'sku' => !empty($_POST['sku']) ? trim($_POST['sku']) : null,
                'featured' => isset($_POST['featured']) ? 1 : 0,
                'highlights' => $_POST['highlights'] ?? null,
                'shipping_policy' => $_POST['shipping_policy'] ?? null,
                'return_policy' => $_POST['return_policy'] ?? null,
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
                            $uploadedImages[] = $baseUrl . '/assets/images/uploads/' . $newName;
                        }
                    }
                }
                
                if (!empty($uploadedImages)) {
                    $data['images'] = array_merge($data['images'] ?? [], $uploadedImages);
                    if (empty($data['featured_image']) && !empty($uploadedImages[0])) {
                        $data['featured_image'] = $uploadedImages[0];
                    }
                }
            }
            
            // Handle variants data
            if (!empty($_POST['variants_data'])) {
                $variantsData = json_decode($_POST['variants_data'], true);
                if (is_array($variantsData)) {
                    $data['variants'] = $variantsData;
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
            header('Location: ' . $baseUrl . '/admin/products/list');
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Now include header after POST processing
$pageTitle = 'Add Product';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/functions.php';

$categories = $db->fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Fetch brands from site_settings
$brandsResult = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands'");
$brands = $brandsResult ? json_decode($brandsResult['setting_value'], true) : [];
?>

<div class="mb-6 flex justify-between items-center sticky top-0 bg-[#f7f8fc] pb-5 z-50">
    <div>
        <h1 class="text-2xl md:text-3xl font-bold">Add Product</h1>
        <p class="text-gray-600 text-sm md:text-base">
            <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
            <a href="<?php echo url('admin/products/list.php'); ?>" class="hover:text-blue-600">Ecommerce</a> > 
            Add Product
        </p>
    </div>
    <button type="submit" form="productForm" id="topSubmitBtn" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 shadow-md transition-all flex items-center">
        <i class="fas fa-save mr-2"></i> <span>Save Product</span>
    </button>
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
            <h2 class="text-lg md:text-xl font-bold mb-4">Product Information</h2>
            
            <form id="productForm" method="POST" action="" enctype="multipart/form-data">
                <div class="admin-form-group">
                    <label class="admin-form-label">Product name *</label>
                    <input type="text" 
                           name="name" 
                           required
                           placeholder="Enter product name"
                           class="admin-form-input">
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Description *</label>
                    <textarea name="description" 
                              id="description_editor"
                              placeholder="Description"
                              class="admin-form-input admin-form-textarea"></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">SKU</label>
                    <input type="text" 
                           name="sku" 
                           placeholder="Enter product SKU"
                           class="admin-form-input">
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
                    <div class="flex items-center justify-between mb-2">
                        <label class="admin-form-label mb-0">Brand *</label>
                        <button type="button" onclick="openBrandModal()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-plus-circle"></i> Add/Manage
                        </button>
                    </div>
                    <select name="brand" id="brandSelect" class="admin-form-select">
                        <option value="">Choose brand</option>
                        <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                


                <div class="admin-form-group">
                    <label class="admin-form-label flex justify-between items-center">
                        Product Highlights
                        <button type="button" onclick="addHighlightRow()" class="text-blue-500 text-xs hover:underline">
                            <i class="fas fa-plus mr-1"></i> Add More
                        </button>
                    </label>
                    <div id="highlights-container" class="space-y-3">
                        <div class="flex gap-3 highlight-row">
                            <div class="w-1/3">
                                <input type="text" name="highlight_icons[]" placeholder="Icon (e.g. fas fa-truck)" class="admin-form-input text-sm">
                            </div>
                            <div class="flex-1">
                                <div class="admin-form-input text-sm highlight-text-editor" contenteditable="true" style="min-height: 38px; cursor: text;"></div>
                            </div>
                            <button type="button" onclick="this.parentElement.remove()" class="text-red-500 px-2 mt-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="highlights" id="highlights_json">
                    <p class="text-xs text-gray-500 mt-2">Use Font Awesome classes for icons (e.g., fas fa-truck, fas fa-tag).</p>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Shipping Policy</label>
                    <textarea name="shipping_policy" id="shipping_policy_editor" class="admin-form-input admin-form-textarea"></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Return Policy</label>
                    <textarea name="return_policy" id="return_policy_editor" class="admin-form-input admin-form-textarea"></textarea>
                </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="space-y-6">
        <div class="admin-card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg md:text-xl font-bold">Upload images</h2>
                <span class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded border border-blue-200">
                    <i class="fas fa-arrows-alt mr-1"></i> Drag to reorder
                </span>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4" id="imageUploadArea">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="<?php echo $i; ?>">
                    <input type="file" accept="image/*" class="hidden image-file-input" data-index="<?php echo $i; ?>" multiple>
                    <div class="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
                    </div>
                    <div class="image-preview hidden"></div>
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="mb-4">
                <button type="button" onclick="addMoreImage()" class="admin-btn border border-dashed border-blue-500 text-blue-500 w-full hover:bg-blue-50">
                    <i class="fas fa-plus mr-2"></i> Add more image
                </button>
            </div>
            
            <p class="text-sm text-gray-600">You need to add at least 4 images. Pay attention to the quality of the pictures you add, comply with the background color standards. Pictures must be in certain dimensions. Notice that the product shows all the details.</p>
            <input type="hidden" name="images" id="imagesInput" value="">
        </div>
        
        <div class="admin-card" id="variants_section">
            <h2 class="text-lg md:text-xl font-bold mb-4">Product Variants</h2>
            <p class="text-sm text-gray-600 mb-4">Add variant options like Size, Color, Material, etc. (Maximum 2 options)</p>
            
            <!-- Variant Options Container -->
            <div id="variantOptionsContainer" class="space-y-4 mb-4">
                <!-- Variant options will be added here dynamically -->
            </div>
            
            <!-- Add Variant Option Button -->
            <button type="button" 
                    id="addVariantOptionBtn" 
                    class="admin-btn border border-blue-500 text-blue-500 mb-4"
                    onclick="addVariantOption()">
                <i class="fas fa-plus mr-2"></i>Add Variant Option
            </button>
            
            <!-- Generated Variants Table -->
            <div id="variantsTableContainer" class="hidden">
                <h3 class="text-lg md:text-xl font-semibold mb-3">Generated Variants</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-3 py-2 text-left">Variant</th>
                                <th class="border border-gray-300 px-3 py-2 text-left">SKU</th>
                                <th class="border border-gray-300 px-3 py-2 text-left">Price</th>
                                <th class="border border-gray-300 px-3 py-2 text-left">Sale Price</th>
                                <th class="border border-gray-300 px-3 py-2 text-left">Stock</th>
                                <th class="border border-gray-300 px-3 py-2 text-left">Image</th>
                            </tr>
                        </thead>
                        <tbody id="variantsTableBody">
                            <!-- Variants will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Hidden input to store variants data -->
            <input type="hidden" name="variants_data" id="variantsDataInput" value="">
        </div>
        
        <div class="admin-card">
            <h2 class="text-lg md:text-xl font-bold mb-4">Product Details</h2>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Currency *</label>
                <select name="currency" required class="admin-form-select">
                    <option value="INR" selected>INR (₹)</option>
                    <option value="USD">USD ($)</option>
                    <option value="EUR">EUR (€)</option>
                    <option value="GBP">GBP (£)</option>
                    <option value="CAD">CAD ($)</option>
                    <option value="AUD">AUD ($)</option>
                </select>
            </div>

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

            <div class="grid grid-cols-2 gap-4">
                <div class="admin-form-group">
                    <label class="admin-form-label">Cost per item</label>
                    <input type="number" 
                           name="cost_per_item" 
                           step="0.01"
                           class="admin-form-input">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Total expense</label>
                    <input type="number" 
                           name="total_expense" 
                           step="0.01"
                           class="admin-form-input">
                </div>
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
            <h2 class="text-lg md:text-xl font-bold mb-4">Product date</h2>
            <input type="date" 
                   value="<?php echo date('Y-m-d'); ?>"
                   class="admin-form-input">
        </div>
        
        <div class="flex space-x-4">
            <button type="submit" id="bottomSubmitBtn" class="admin-btn admin-btn-primary flex-1 flex items-center justify-center">
                <span>Add product</span>
            </button>
            <button type="reset" class="admin-btn border border-gray-300 text-gray-600 flex-1">
                Reset
            </button>
        </div>
            </form>
    </div>
</div>

<script>
// BASE_URL is already declared in admin-header.php, so check if it exists first
if (typeof BASE_URL === 'undefined') {
    const BASE_URL = '<?php echo $baseUrl; ?>';
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#shipping_policy_editor, #return_policy_editor, #description_editor',
    license_key: 'gpl',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
    toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
    height: 200,
    menubar: false,
    promotion: false,
    branding: false
});

// Inline mini editor for highlights (looks like a text field)
function initHighlightEditor(selector) {
    tinymce.init({
        target: selector,
        inline: true,
        license_key: 'gpl',
        plugins: 'link lists',
        toolbar: 'bold italic link',
        menubar: false,
        promotion: false,
        branding: false,
        fixed_toolbar_container: false
    });
}

// Initialize existing highlight editors
document.querySelectorAll('.highlight-text-editor').forEach(el => {
    initHighlightEditor(el);
});

let highlightCounter = 0;
function addHighlightRow() {
    const container = document.getElementById('highlights-container');
    const rowId = 'highlight_text_' + Date.now();
    const row = document.createElement('div');
    row.className = 'flex gap-3 highlight-row';
    row.innerHTML = `
        <div class="w-1/3">
            <input type="text" name="highlight_icons[]" placeholder="Icon (e.g. fas fa-truck)" class="admin-form-input text-sm">
        </div>
        <div class="flex-1">
            <div id="${rowId}" class="admin-form-input text-sm highlight-text-editor" contenteditable="true" style="min-height: 38px; cursor: text;"></div>
        </div>
        <button type="button" onclick="removeHighlightRow(this, '${rowId}')" class="text-red-500 px-2 mt-2">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(row);
    
    // Init TinyMCE on the new textarea
    initHighlightEditor(document.getElementById(rowId));
}

function removeHighlightRow(btn, editorId) {
    if (tinymce.get(editorId)) {
        tinymce.get(editorId).remove();
    }
    btn.parentElement.remove();
}

document.getElementById('productForm').addEventListener('submit', function(e) {
    // Prevent double-submission
    const submitBtns = [document.getElementById('topSubmitBtn'), document.getElementById('bottomSubmitBtn')];
    submitBtns.forEach(btn => {
        if (btn) {
            btn.disabled = true;
            const span = btn.querySelector('span');
            if (span) span.textContent = 'Processing...';
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'fas fa-spinner fa-spin mr-2';
        }
    });

    // Sync all TinyMCE instances
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }

    const icons = Array.from(document.querySelectorAll('input[name="highlight_icons[]"]')).map(i => i.value);
    const editors = Array.from(document.querySelectorAll('.highlight-text-editor'));
    
    const highlights = [];
    for(let i=0; i<icons.length; i++) {
        // For inline editors, we must use tinymce.get().getContent() 
        const editorId = editors[i].id;
        const content = (editorId && tinymce.get(editorId)) ? tinymce.get(editorId).getContent() : editors[i].innerHTML;
        if(icons[i] || content) {
            highlights.push({ icon: icons[i], text: content });
        }
    }
    document.getElementById('highlights_json').value = JSON.stringify(highlights);
});
</script>
<script src="<?php echo $baseUrl; ?>/assets/js/admin-image-upload3.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/product-variants4.js"></script>

<!-- Brand Management Modal -->
<div id="brandModal" class="hidden fixed inset-0 bg-black/50 z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="brandModalContent">
        <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800">Manage Brands</h3>
            <button type="button" onclick="closeBrandModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6">
            <!-- Add Brand Form -->
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-2">Add New Brand</label>
                <div class="flex gap-2">
                    <input type="text" id="newBrandName" class="admin-form-input py-2" placeholder="Brand name...">
                    <button type="button" onclick="addNewBrand()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Add
                    </button>
                </div>
            </div>
            
            <!-- Brand List -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Existing Brands</label>
                <div class="max-h-60 overflow-y-auto custom-scrollbar border rounded-lg bg-gray-50" id="brandListContainer">
                    <!-- Brands will be loaded here via JS -->
                    <div class="p-4 text-center text-gray-500">Loading brands...</div>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t text-right">
            <button type="button" onclick="closeBrandModal()" class="px-5 py-2 text-gray-700 font-medium hover:text-gray-900 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function openBrandModal() {
    const modal = document.getElementById('brandModal');
    const content = document.getElementById('brandModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
    loadBrands();
}

function closeBrandModal() {
    const modal = document.getElementById('brandModal');
    const content = document.getElementById('brandModalContent');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

async function loadBrands() {
    const container = document.getElementById('brandListContainer');
    try {
        const response = await fetch('<?php echo $baseUrl; ?>/admin/api/settings.php?action=get_brands');
        const data = await response.json();
        
        if (data.success) {
            renderBrandList(data.brands);
            updateBrandSelect(data.brands);
        } else {
            container.innerHTML = '<div class="p-4 text-red-500">' + data.message + '</div>';
        }
    } catch (error) {
        container.innerHTML = '<div class="p-4 text-red-500">Failed to load brands</div>';
    }
}

function renderBrandList(brands) {
    const container = document.getElementById('brandListContainer');
    if (!brands || brands.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-gray-500">No brands added yet.</div>';
        return;
    }
    
    let html = '';
    brands.forEach(function(brand) {
        html += '<div class="flex items-center justify-between px-4 py-3 border-b last:border-0 hover:bg-white transition-colors">';
        html += '<span class="font-medium text-gray-700">' + brand + '</span>';
        html += '<button type="button" onclick="removeBrand(\'' + brand.replace(/'/g, "\\'") + '\')" class="text-red-500 hover:text-red-700 transition-colors">';
        html += '<i class="fas fa-trash-alt"></i>';
        html += '</button>';
        html += '</div>';
    });
    container.innerHTML = html;
}

function updateBrandSelect(brands) {
    const select = document.getElementById('brandSelect');
    const currentValue = select.value;
    
    let html = '<option value="">Choose brand</option>';
    brands.forEach(function(brand) {
        var selected = (brand === currentValue) ? 'selected' : '';
        html += '<option value="' + brand + '" ' + selected + '>' + brand + '</option>';
    });
    select.innerHTML = html;
}

async function addNewBrand() {
    const input = document.getElementById('newBrandName');
    const brand = input.value.trim();
    
    if (!brand) return;
    
    try {
        const response = await fetch('<?php echo $baseUrl; ?>/admin/api/settings.php?action=add_brand', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ brand })
        });
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            renderBrandList(data.brands);
            updateBrandSelect(data.brands);
        } else {
            console.log(data.message);
        }
    } catch (error) {
        console.log('Failed to add brand');
    }
}

async function removeBrand(brand) {
    try {
        const response = await fetch('<?php echo $baseUrl; ?>/admin/api/settings.php?action=remove_brand', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ brand: brand })
        });
        const data = await response.json();
        
        if (data.success) {
            renderBrandList(data.brands);
            updateBrandSelect(data.brands);
        } 
    } catch (error) {
        console.log(error);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

