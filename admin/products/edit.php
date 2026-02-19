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
$product = new Product();
$error = '';
$success = '';

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Fetch brands from site_settings (after storeId detection)
$brandsResult = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands' AND store_id = ?", [$storeId]);
$brands = $brandsResult ? json_decode($brandsResult['setting_value'], true) : [];

// Get product ID or 10-digit product_id
$id = $_GET['id'] ?? null;
$productIdParam = $_GET['product_id'] ?? null;

if ($productIdParam) {
    $productData = $product->getByProductId($productIdParam, $storeId);
    $productId = $productData['id'] ?? null;
} elseif ($id) {
    $productData = $product->getById($id, $storeId);
    $productId = $id;
} else {
    header('Location: ' . $baseUrl . '/admin/products/list');
    exit;
}

if (!$productData) {
    header('Location: ' . $baseUrl . '/admin/products/list');
    exit;
}

// Handle form submission BEFORE including header (to allow redirects)
// Handle form submission BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $retryHandler = new RetryHandler();
    
    // Check if POST data is empty but method is POST/length > 0 (exceeds post_max_size)
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "The total size of your images and data exceeds the server limit (" . ini_get('post_max_size') . "). Please upload fewer or smaller images.";
    } elseif (isset($_POST['name'])) {
        try {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'short_description' => $_POST['short_description'] ?? '',
                // Fix category mapping
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : (!empty($_POST['category_ids'][0]) ? $_POST['category_ids'][0] : null),
                'category_ids' => !empty($_POST['category_ids']) ? $_POST['category_ids'] : (!empty($_POST['category_id']) ? [$_POST['category_id']] : []),
                'price' => $_POST['price'] ?? 0,
                'currency' => $_POST['currency'] ?? 'INR',
                'sale_price' => !empty($_POST['sale_price']) ? $_POST['sale_price'] : null,
                'cost_per_item' => $_POST['cost_per_item'] ?? 0,
                'total_expense' => $_POST['total_expense'] ?? 0,
                'stock_quantity' => $_POST['stock_quantity'] ?? 0,
                'stock_status' => ($_POST['stock_quantity'] ?? 0) <= 0 ? 'out_of_stock' : ($_POST['stock_status'] ?? 'in_stock'),
                'brand' => $_POST['brand'] ?? null,
                'status' => $_POST['status'] ?? 'draft',
                // Fix SKU NULL handling
                'sku' => !empty($_POST['sku']) ? trim($_POST['sku']) : null,
                'featured' => isset($_POST['featured']) ? 1 : 0,
                'is_taxable' => isset($_POST['is_taxable']) ? 1 : 0,
                'hsn_code' => $_POST['hsn_code'] ?? null,
                'gst_percent' => $_POST['gst_percent'] ?? 0.00,
                'weight' => $_POST['weight'] ?? 0.00,
                'length' => $_POST['length'] ?? 0.00,
                'width' => $_POST['width'] ?? 0.00,
                'height' => $_POST['height'] ?? 0.00,
                'highlights' => $_POST['highlights'] ?? null,
                'shipping_policy' => $_POST['shipping_policy'] ?? null,
                'return_policy' => $_POST['return_policy'] ?? null,
                'images' => []
            ];
        
        // Handle image uploads
        if (isset($_POST['images'])) {
            $imagePaths = json_decode($_POST['images'], true);
            if (is_array($imagePaths)) {
                $data['images'] = array_values(array_filter($imagePaths));
                $data['featured_image'] = !empty($data['images'][0]) ? $data['images'][0] : null;
            } else {
                // Fallback to existing
                $data['images'] = json_decode($productData['images'] ?? '[]', true);
                $data['featured_image'] = $productData['featured_image'];
            }
        } else {
            // Keep existing if no data sent
            $data['images'] = json_decode($productData['images'] ?? '[]', true);
            $data['featured_image'] = $productData['featured_image'];
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
                        $uploadedImages[] = '/assets/images/uploads/' . $newName;
                    }
                }
            }
            
            if (!empty($uploadedImages)) {
                // Merge with the already processed list (which includes removals)
                $data['images'] = array_merge($data['images'], $uploadedImages);
                if (empty($data['featured_image']) && !empty($uploadedImages[0])) {
                    $data['featured_image'] = $uploadedImages[0];
                }
            }
        }
        
        // Handle variants data
        if (isset($_POST['variants_data'])) {
            $variantsData = json_decode($_POST['variants_data'], true);
            if (is_array($variantsData)) {
                $data['variants'] = $variantsData;
            } else {
                // If variants_data is empty or invalid, set empty array to clear variants
                $data['variants'] = [];
            }
        }
        
        $retryHandler->executeWithRetry(
            function() use ($product, $productId, $data) {
                // Update product first
                $product->update($productId, $data);
                
                // Handle variants: always delete existing and save new ones (even if empty)
                $product->deleteVariants($productId);
                
                // Save new variants if provided
                if (!empty($data['variants']) && is_array($data['variants'])) {
                    $product->saveVariants($productId, $data['variants']);
                }
                
                return true;
            },
            'Update Product',
            ['id' => $productId, 'data' => $data]
        );
        
        // Redirect after successful update
        header('Location: ' . $baseUrl . '/admin/products/list?success=updated');
        exit;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate entry') !== false) {
                // Extract the duplicate value if needed, or just generic message
                $error = "Duplicate: A product with this SKU already exists.";
            } else {
                $error = $msg;
            }
        }
    }
}

// Now include header after POST processing
$pageTitle = 'Edit Product';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-header.php';

$categories = $db->fetchAll("SELECT * FROM categories WHERE (store_id = ? OR store_id IS NULL) AND (status = 'active' OR id IN (SELECT category_id FROM product_categories WHERE product_id = ?)) ORDER BY name", [$storeId, $productData['product_id']]);

// Get existing product categories
$existingCategoryIds = $db->fetchAll(
    "SELECT category_id FROM product_categories WHERE product_id = ?",
    [$productData['product_id']]
);
$existingCategoryIds = array_column($existingCategoryIds, 'category_id');

// Fallback to product table category_id if mapping table is empty
if (empty($existingCategoryIds) && !empty($productData['category_id'])) {
    $catId = trim($productData['category_id']);
    
    // Attempt to decode JSON (multiple IDs)
    $decoded = json_decode($catId, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $existingCategoryIds = array_map('strval', $decoded);
    } else {
        // More robust: Extract numeric IDs via regex even if JSON is slightly malformed
        if (preg_match_all('/\d+/', $catId, $matches)) {
            $existingCategoryIds = $matches[0];
        } else {
            $existingCategoryIds = [(string)$catId];
        }
    }
}

// Parse existing images
$existingImages = json_decode($productData['images'] ?? '[]', true);
if (!is_array($existingImages)) {
    $existingImages = [];
}

// Get existing variants
$existingVariants = $product->getVariants($productId);
?>

<div class="mb-6 flex justify-between items-center sticky top-0 bg-[#f7f8fc] pb-5 z-50">
    <div>
        <h1 class="text-3xl font-bold pt-4 pl-2">Edit Product</h1>
        <p class="text-gray-600 pl-2">
            <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
            <a href="<?php echo url('admin/products/list.php'); ?>" class="hover:text-blue-600">Ecommerce</a> > 
            Edit product
        </p>
    </div>
    <button type="submit" form="productForm" id="topSubmitBtn" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 shadow-md transition-all flex items-center btn-loading">
        <i class="fas fa-save mr-2"></i> <span>Save Changes</span>
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
            <h2 class="text-xl font-bold mb-4">Product Information</h2>
            
            <form id="productForm" method="POST" action="" enctype="multipart/form-data">
                <div class="admin-form-group">
                    <label class="admin-form-label">Product name *</label>
                    <input type="text" 
                           name="name" 
                           required
                           placeholder="Enter product name"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $productData['name']); ?>"
                           class="admin-form-input">
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Description *</label>
                    <textarea name="description" 
                              id="description_editor"
                              placeholder="Description"
                              class="admin-form-input admin-form-textarea rich-text-editor"><?php echo htmlspecialchars($_POST['description'] ?? $productData['description'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">SKU</label>
                    <input type="text" 
                           name="sku" 
                           placeholder="Enter product SKU"
                           value="<?php echo htmlspecialchars($_POST['sku'] ?? $productData['sku'] ?? ''); ?>"
                           class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Category *</label>
                    
                    <!-- Custom Multi-Select UI -->
                    <div id="category-multiselect-container" class="relative">
                        <!-- Hidden Select for Form Submission -->
                        <select name="category_ids[]" id="real-category-select" multiple class="hidden">
                            <?php 
                            // Ensure clean array of selected IDs
                            $selectedCats = isset($_POST['category_ids']) ? $_POST['category_ids'] : $existingCategoryIds;
                            $selectedCats = array_map('strval', $selectedCats); // Standardize to string for JS comparison
                            
                            // Re-render options for fallback
                            foreach ($categories as $cat) {
                                $isSelected = in_array((string)$cat['id'], $selectedCats);
                                echo '<option value="' . htmlspecialchars($cat['id']) . '" ' . ($isSelected ? 'selected' : '') . '>' . htmlspecialchars($cat['name']) . '</option>';
                            }
                            ?>
                        </select>

                        <!-- Visual Input -->
                        <div class="admin-form-input min-h-[42px] flex flex-wrap gap-2 p-2 focus-within:ring-2 focus-within:ring-blue-500 cursor-text bg-white" 
                             onclick="document.getElementById('category-search').focus()">
                             
                            <!-- Selected Tags Container - Removed 'contents' class for stability -->
                            <div id="category-tags" class="flex flex-wrap gap-2 items-center"></div>
                            
                            <!-- Search Input -->
                            <input type="text" id="category-search" placeholder="Select category..." 
                                   class="flex-1 min-w-[120px] outline-none bg-transparent text-sm p-1" autocomplete="off">
                        </div>
                        
                        <!-- Dropdown List -->
                        <div id="category-dropdown" class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto hidden">
                            <!-- Items injected by JS -->
                        </div>
                    </div>

                    <script>
                    (function() {
                        const realSelect = document.getElementById('real-category-select');
                        const tagsContainer = document.getElementById('category-tags');
                        const searchInput = document.getElementById('category-search');
                        const dropdown = document.getElementById('category-dropdown');
                        const container = document.getElementById('category-multiselect-container');
                        
                        // Pass PHP data directly to JS to avoid DOM parsing issues
                        const categoriesData = <?php echo json_encode($categories); ?>;
                        const selectedIds = <?php echo json_encode(array_values($selectedCats)); ?>.map(String);
                        
                        // Initialize options state
                        let allOptions = categoriesData.map(cat => ({
                            value: String(cat.id),
                            text: cat.name,
                            selected: selectedIds.includes(String(cat.id))
                        }));
                        
                        function escapeHtml(text) {
                            if (!text) return text;
                            return text.toString()
                                .replace(/&/g, "&amp;")
                                .replace(/</g, "&lt;")
                                .replace(/>/g, "&gt;")
                                .replace(/"/g, "&quot;")
                                .replace(/'/g, "&#039;");
                        }

                        function renderTags() {
                            tagsContainer.innerHTML = '';
                            const selected = allOptions.filter(o => o.selected);
                            
                            selected.forEach(opt => {
                                const tag = document.createElement('span');
                                // Use inline styles for colors to avoid generic class conflicts (like .alert auto-hide)
                                tag.className = 'text-xs font-semibold px-2 py-1 rounded flex items-center gap-1 select-none border border-blue-200';
                                tag.style.backgroundColor = '#e0f2fe'; // Safe Sky Blue
                                tag.style.color = '#0369a1';
                                
                                tag.innerHTML = `
                                    ${escapeHtml(opt.text)}
                                    <button type="button" style="color: #0369a1;" class="hover:text-blue-900 focus:outline-none" onclick="toggleCategory('${escapeHtml(opt.value)}'); event.stopPropagation();">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                                tagsContainer.appendChild(tag);
                            });
                            
                            // Adjust placeholder
                            searchInput.placeholder = selected.length > 0 ? '' : 'Select category...';
                        }

                        function renderDropdown(filter = '') {
                            dropdown.innerHTML = '';
                            // Filter OUT selected options
                            const filtered = allOptions.filter(o => !o.selected && o.text.toLowerCase().includes(filter.toLowerCase()));
                            
                            if (filtered.length === 0) {
                                dropdown.innerHTML = '<div class="p-2 text-gray-500 text-sm">No available categories</div>';
                            } else {
                                filtered.forEach(opt => {
                                    const item = document.createElement('div');
                                    item.className = 'p-2 hover:bg-gray-50 cursor-pointer text-sm text-gray-700 flex justify-between items-center';
                                    item.innerHTML = `<span>${escapeHtml(opt.text)}</span>`;
                                    
                                    item.onclick = (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        toggleCategory(opt.value);
                                        searchInput.value = '';
                                        searchInput.focus();
                                        renderDropdown(''); // Refresh list
                                    };
                                    dropdown.appendChild(item);
                                });
                            }
                        }

                        // Attach to window so onclick attribute works
                        window.toggleCategory = function(val) {
                            // Find in our JS state
                            const opt = allOptions.find(o => o.value === val);
                            if (opt) {
                                opt.selected = !opt.selected;
                                
                                // Sync with real hidden select for form submission
                                // We iterate options to match value
                                for (let i = 0; i < realSelect.options.length; i++) {
                                    if (realSelect.options[i].value === val) {
                                        realSelect.options[i].selected = opt.selected;
                                        break;
                                    }
                                }
                                
                                renderTags();
                                renderDropdown(searchInput.value);
                            }
                        }

                        // Event Listeners
                        searchInput.addEventListener('focus', () => {
                            dropdown.classList.remove('hidden');
                            renderDropdown(searchInput.value);
                        });
                        
                        document.addEventListener('click', (e) => {
                            if (!container.contains(e.target)) {
                                dropdown.classList.add('hidden');
                            }
                        });

                        searchInput.addEventListener('input', (e) => {
                            dropdown.classList.remove('hidden');
                            renderDropdown(e.target.value);
                        });

                        // Initial Render
                        renderTags();
                    })();
                    </script>
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
                        <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo (trim($_POST['brand'] ?? $productData['brand'] ?? '') === trim($brand)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand); ?>
                        </option>
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
                        <?php 
                        // Prioritize POST data for highlights
                        if (isset($_POST['highlights']) && !empty($_POST['highlights'])) {
                            $highlights = json_decode($_POST['highlights'], true);
                        } else {
                            $highlights = json_decode($productData['highlights'] ?? '[]', true);
                        }
                        if(empty($highlights) || !is_array($highlights)) $highlights = [['icon' => '', 'text' => '']];
                        $hCount = 0;
                        foreach($highlights as $h): 
                            $hCount++;
                            $rowId = "highlight_text_init_" . $hCount;
                        ?>
                        <div class="flex gap-3 highlight-row">
                            <div class="w-1/3">
                                <input type="text" name="highlight_icons[]" value="<?php echo htmlspecialchars($h['icon']); ?>" placeholder="Icon (e.g. fas fa-truck)" class="admin-form-input text-sm">
                            </div>
                            <div class="flex-1">
                                <div id="<?php echo $rowId; ?>" class="admin-form-input text-sm highlight-text-editor" contenteditable="true" style="min-height: 38px; cursor: text;"><?php echo $h['text']; ?></div>
                            </div>
                            <button type="button" onclick="removeHighlightRow(this, '<?php echo $rowId; ?>')" class="text-red-500 px-2 mt-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="highlights" id="highlights_json" value="<?php echo htmlspecialchars($_POST['highlights'] ?? $productData['highlights'] ?? '[]'); ?>">
                    <p class="text-xs text-gray-500 mt-2">Use Font Awesome classes for icons (e.g., fas fa-truck, fas fa-tag).</p>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Shipping Policy</label>
                    <textarea name="shipping_policy" id="shipping_policy_editor" class="admin-form-input admin-form-textarea rich-text-editor"><?php echo htmlspecialchars($_POST['shipping_policy'] ?? $productData['shipping_policy'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Return Policy</label>
                    <textarea name="return_policy" id="return_policy_editor" class="admin-form-input admin-form-textarea rich-text-editor"><?php echo htmlspecialchars($_POST['return_policy'] ?? $productData['return_policy'] ?? ''); ?></textarea>
                </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="space-y-6">
        <div class="admin-card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Upload images</h2>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4" id="imageUploadArea">
                <?php 
                // Determine how many boxes to show. 
                // Show existing images + 1 empty, or at least 4 boxes total if there are few images.
                // Actually, let's just show existing images. If none, show 4 empty ones. 
                // Then provide a button to add more.
                
                $count = count($existingImages);
                $initialBoxes = max($count, 4); // Start with at least 4
                
                for ($i = 0; $i < $initialBoxes; $i++): 
                    $hasImage = isset($existingImages[$i]);
                ?>
                <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative group" data-index="<?php echo $i; ?>" <?php echo $hasImage ? 'data-image-path="' . htmlspecialchars($existingImages[$i]) . '"' : ''; ?>>
                    <!-- Drag Handle -->
                    <div class="absolute top-2 left-2 cursor-grab move-handle text-grey-800 w-8 h-8 flex items-center justify-center rounded shadow-md z-20 transition-colors" title="Drag to reorder">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    
                    <input type="file" accept="image/*,video/*" class="hidden image-file-input" data-index="<?php echo $i; ?>" multiple>
                    
                    <div class="upload-placeholder <?php echo $hasImage ? 'hidden' : ''; ?>">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Drop media (Images/Videos)<br><span class="text-xs text-gray-400">(Recommended 1:1)</span></p>
                        <span class="text-blue-500 text-sm">click to browse</span>
                    </div>
                    
                    <div class="image-preview <?php echo $hasImage ? '' : 'hidden'; ?>">
                        <?php if ($hasImage): 
                            $existingImageUrl = getImageUrl($existingImages[$i]);
                            $ext = strtolower(pathinfo($existingImageUrl, PATHINFO_EXTENSION));
                            $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov']);
                        ?>
                        <?php if ($isVideo): ?>
                            <video src="<?php echo htmlspecialchars($existingImageUrl); ?>" class="w-full h-32 object-cover rounded" controls></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($existingImageUrl); ?>" alt="Preview" class="w-full h-32 object-cover rounded">
                        <?php endif; ?>
                        <p class="text-xs text-gray-600 mt-2 truncate">Existing File</p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 <?php echo $hasImage ? '' : 'hidden'; ?> z-40">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                    <?php if ($i >= 4): ?>
                    <button type="button" onclick="event.stopPropagation(); removeImageBox(this);" class="remove-box-btn absolute top-2 right-2 bg-gray-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-gray-700 z-30 <?php echo $hasImage ? 'hidden' : ''; ?>" title="Remove this box">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="mb-4">
                <button type="button" onclick="addMoreImage()" class="admin-btn border border-dashed border-blue-500 text-blue-500 w-full hover:bg-blue-50">
                    <i class="fas fa-plus mr-2"></i> Add more image
                </button>
            </div>
            
            <p class="text-sm text-gray-600">You need to add at least 4 images. Pay attention to the quality of the pictures you add, comply with the background color standards. Pictures must be in certain dimensions. Notice that the product shows all the details.</p>
            <input type="hidden" name="images" id="imagesInput" value="<?php echo htmlspecialchars(json_encode($existingImages)); ?>">
        </div>

        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Shipping Dimensions</h2>
            <div class="admin-form-group">
                <label class="admin-form-label">Weight (kg) *</label>
                <input type="number" name="weight" step="0.01" value="<?php echo htmlspecialchars($_POST['weight'] ?? $productData['weight'] ?? '0.50'); ?>" class="admin-form-input" placeholder="0.50">
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div class="admin-form-group">
                    <label class="admin-form-label">Length (cm)</label>
                    <input type="number" name="length" step="0.01" value="<?php echo htmlspecialchars($_POST['length'] ?? $productData['length'] ?? '10.00'); ?>" class="admin-form-input" placeholder="10">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Width (cm)</label>
                    <input type="number" name="width" step="0.01" value="<?php echo htmlspecialchars($_POST['width'] ?? $productData['width'] ?? '10.00'); ?>" class="admin-form-input" placeholder="10">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Height (cm)</label>
                    <input type="number" name="height" step="0.01" value="<?php echo htmlspecialchars($_POST['height'] ?? $productData['height'] ?? '10.00'); ?>" class="admin-form-input" placeholder="10">
                </div>
            </div>
            <p class="text-xs text-gray-500">Weight and dimensions are crucial for accurate shipping cost calculation and labels.</p>
        </div>
        
        <div class="admin-card" id="variants_section">
            <h2 class="text-xl font-bold mb-4">Product Variants</h2>
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
                <h3 class="text-lg font-semibold mb-3">Generated Variants</h3>
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
            <h2 class="text-xl font-bold mb-4">Product Details</h2>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Currency *</label>
                <select name="currency" required class="admin-form-select">
                    <?php $curr = $_POST['currency'] ?? $productData['currency'] ?? 'INR'; ?>
                    <option value="USD" <?php echo $curr === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                    <option value="EUR" <?php echo $curr === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                    <option value="GBP" <?php echo $curr === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                    <option value="INR" <?php echo $curr === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                    <option value="CAD" <?php echo $curr === 'CAD' ? 'selected' : ''; ?>>CAD ($)</option>
                    <option value="AUD" <?php echo $curr === 'AUD' ? 'selected' : ''; ?>>AUD ($)</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Price *</label>
                <input type="number" 
                       name="price" 
                       id="base_price"
                       step="0.01"
                       required
                       value="<?php echo htmlspecialchars($_POST['price'] ?? $productData['price']); ?>"
                       class="admin-form-input"
                       oninput="calculateTaxDetail()">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Sale Price</label>
                <input type="number" 
                       name="sale_price" 
                       id="sale_price"
                       step="0.01"
                       value="<?php echo htmlspecialchars($_POST['sale_price'] ?? $productData['sale_price'] ?? ''); ?>"
                       class="admin-form-input"
                       oninput="calculateTaxDetail()">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="admin-form-group">
                    <label class="admin-form-label">Cost per item</label>
                    <input type="number" 
                           name="cost_per_item" 
                           step="0.01"
                           value="<?php echo htmlspecialchars($_POST['cost_per_item'] ?? $productData['cost_per_item'] ?? ''); ?>"
                           class="admin-form-input">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Total expense</label>
                    <input type="number" 
                           name="total_expense" 
                           step="0.01"
                           value="<?php echo htmlspecialchars($_POST['total_expense'] ?? $productData['total_expense'] ?? ''); ?>"
                           class="admin-form-input">
                </div>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Stock Quantity *</label>
                <input type="number" 
                       name="stock_quantity" 
                       id="stock_quantity"
                       required
                       value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? $productData['stock_quantity']); ?>"
                       class="admin-form-input"
                       oninput="checkStockStatus(this)">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Stock Status *</label>
                <select name="stock_status" id="stock_status" required class="admin-form-select">
                    <?php $ss = $_POST['stock_status'] ?? $productData['stock_status'] ?? 'in_stock'; ?>
                    <option value="in_stock" <?php echo $ss === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="out_of_stock" <?php echo $ss === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="on_backorder" <?php echo $ss === 'on_backorder' ? 'selected' : ''; ?>>On Backorder</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Status *</label>
                <select name="status" required class="admin-form-select">
                    <?php $st = $_POST['status'] ?? $productData['status'] ?? 'draft'; ?>
                    <option value="draft" <?php echo $st === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $st === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="flex items-center">
                    <?php 
                        $isFeatured = isset($_POST['featured']) ? true : (isset($_POST) && count($_POST) > 0 ? false : $productData['featured']);
                    ?>
                    <input type="checkbox" name="featured" class="mr-2" <?php echo $isFeatured ? 'checked' : ''; ?>>
                    <span>Featured Product</span>
                </label>
            </div>

            <hr class="my-4">
            <h3 class="font-bold mb-3">GST / Tax Details</h3>

            <div class="admin-form-group">
                <label class="flex items-center">
                    <?php 
                        $isTaxable = isset($_POST['is_taxable']) ? true : (isset($_POST) && count($_POST) > 0 ? false : ($productData['is_taxable'] ?? false));
                    ?>
                    <input type="checkbox" name="is_taxable" id="is_taxable" class="mr-2" <?php echo $isTaxable ? 'checked' : ''; ?> onchange="toggleGSTFields()">
                    <span>Taxable Product</span>
                </label>
            </div>

            <div id="gst_fields" class="<?php echo $isTaxable ? '' : 'hidden'; ?>">
                <div class="admin-form-group">
                    <label class="admin-form-label">HSN Code</label>
                    <input type="text" name="hsn_code" value="<?php echo htmlspecialchars($_POST['hsn_code'] ?? $productData['hsn_code'] ?? ''); ?>" class="admin-form-input" placeholder="e.g. 7113">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">GST Percent (%)</label>
                    <input type="number" name="gst_percent" id="gst_percent" step="0.01" value="<?php echo htmlspecialchars($_POST['gst_percent'] ?? $productData['gst_percent'] ?? '0.00'); ?>" class="admin-form-input" placeholder="e.g. 18.00" oninput="calculateTaxDetail()">
                </div>
                
                <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded border border-dashed border-gray-300">
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase">Tax Amount</label>
                        <p class="text-lg font-bold text-gray-700" id="display_tax_amount">₹0.00</p>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase">Price (Inc. Tax)</label>
                        <p class="text-lg font-bold text-green-600" id="display_total_price">₹0.00</p>
                    </div>
                </div>
            </div>

            <script>
            function toggleGSTFields() {
                const box = document.getElementById('is_taxable');
                const fields = document.getElementById('gst_fields');
                if (box.checked) {
                    fields.classList.remove('hidden');
                    calculateTaxDetail();
                } else {
                    fields.classList.add('hidden');
                }
            }

            function calculateTaxDetail() {
                const price = parseFloat(document.getElementById('base_price').value) || 0;
                const salePrice = parseFloat(document.getElementById('sale_price').value) || 0;
                
                // Use sale price if available and valid, otherwise allow price
                const effectivePrice = (salePrice > 0) ? salePrice : price;
                
                const gstPercent = parseFloat(document.getElementById('gst_percent').value) || 0;
                const isTaxable = document.getElementById('is_taxable').checked;

                if (isTaxable && gstPercent > 0) {
                    const taxAmount = (effectivePrice * gstPercent) / 100;
                    const total = effectivePrice + taxAmount;
                    
                    document.getElementById('display_tax_amount').innerText = '₹' + taxAmount.toFixed(2);
                    document.getElementById('display_total_price').innerText = '₹' + total.toFixed(2);
                } else {
                    document.getElementById('display_tax_amount').innerText = '₹0.00';
                    document.getElementById('display_total_price').innerText = '₹' + effectivePrice.toFixed(2);
                }
            }
            
            // Initial calculation on load
            document.addEventListener('DOMContentLoaded', calculateTaxDetail);
            </script>
        </div>
        
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product date</h2>
            <input type="date" 
                   value="<?php echo date('Y-m-d', strtotime($productData['created_at'])); ?>"
                   class="admin-form-input"
                   readonly>
        </div>
        
        <div class="flex space-x-4">
            <button type="submit" id="bottomSubmitBtn" class="admin-btn admin-btn-primary flex-1 flex items-center justify-center">
                <span>Update product</span>
            </button>
            <a href="<?php echo url('admin/products/list.php'); ?>" class="admin-btn border border-gray-300 text-gray-600 flex-1 text-center">
                Cancel
            </a>
        </div>
            </form>
    </div>
</div>

</script>

<script>
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
    // Validate Category
    const catSelect = document.getElementById('real-category-select');
    let hasCategory = false;
    for (let j = 0; j < catSelect.options.length; j++) {
        if (catSelect.options[j].selected) {
            hasCategory = true;
            break;
        }
    }
    
    if (!hasCategory) {
        e.preventDefault();
        // Scroll to category section
        document.getElementById('category-multiselect-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // Prevent double-submission
    const submitBtns = [document.getElementById('topSubmitBtn'), document.getElementById('bottomSubmitBtn')];
    submitBtns.forEach(btn => {
        if (btn) {
            btn.disabled = true;
            const span = btn.querySelector('span');
            if (span) span.textContent = 'Processing...';
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
        const editorId = editors[i].id;
        const content = (editorId && tinymce.get(editorId)) ? tinymce.get(editorId).getContent() : editors[i].innerHTML;
        if(icons[i] || content) {
            highlights.push({ icon: icons[i], text: content });
        }
    }
    document.getElementById('highlights_json').value = JSON.stringify(highlights);
});
</script>
<script src="<?php echo $baseUrl; ?>/assets/js/admin-image-upload9.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/product-variants6.js"></script>
<script>
// Initialize with existing images
document.addEventListener('DOMContentLoaded', function() {
    const existingImages = <?php echo json_encode($existingImages); ?>;
    const imagesInput = document.getElementById('imagesInput');
    
    if (existingImages && existingImages.length > 0 && imagesInput) {
        imagesInput.value = JSON.stringify(existingImages);
    }
    
    // Initialize variants with existing data
    const existingVariants = <?php echo json_encode($existingVariants); ?>;
    if (existingVariants && existingVariants.options && existingVariants.options.length > 0) {
        initializeVariantsFromData(existingVariants);
    }
});

/**
 * Initialize variants from existing data
 */
function initializeVariantsFromData(variantsData) {
    // Clear any existing options
    variantOptions = [];
    generatedVariants = [];
    
    // Add variant options
    variantsData.options.forEach((option, index) => {
        // Add the option card
        addVariantOption();
        
        // Wait for DOM to update
        setTimeout(() => {
            const card = document.querySelector(`[data-option-index="${index}"]`);
            if (card) {
                // Set option name
                const nameSelect = card.querySelector('select[id$="_name"]');
                const nameInput = card.querySelector('input[id$="_name_custom"]');
                
                if (commonOptionNames.includes(option.option_name)) {
                    if (nameSelect) {
                        nameSelect.value = option.option_name;
                        updateVariantOptionName(index);
                    }
                } else {
                    if (nameInput) {
                        nameInput.value = option.option_name;
                        updateVariantOptionName(index);
                    }
                }
                
                // Add tags for option values
                const tagContainer = card.querySelector(`[id$="_tags"]`);
                
                if (option.option_values && Array.isArray(option.option_values)) {
                    option.option_values.forEach(value => {
                        if (tagContainer && value) {
                            // Use the same escapeHtml function from product-variants2.js
                            const escapedValue = value.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                            const escapedValueForOnClick = value.replace(/'/g, "\\'");
                            const tagHtml = `
                                <span class="tag-item" data-value="${escapedValue}">
                                    <span class="tag-text">${escapedValue}</span>
                                    <button type="button" class="tag-remove" onclick="removeTag(${index}, '${escapedValueForOnClick}')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </span>
                            `;
                            tagContainer.insertAdjacentHTML('beforeend', tagHtml);
                        }
                    });
                    
                    // Update variant options array
                    if (variantOptions[index]) {
                        variantOptions[index].name = option.option_name;
                        variantOptions[index].values = option.option_values || [];
                    }
                }
            }
        }, 100 * (index + 1));
    });
    
    // After all options are loaded, generate variants and populate with existing data
    setTimeout(() => {
        generateVariants();
        
        // Populate existing variant data
        if (variantsData.variants && variantsData.variants.length > 0) {
            variantsData.variants.forEach((existingVariant, idx) => {
                // Find matching variant in generated variants
                const matchingVariant = generatedVariants.find(v => {
                    return JSON.stringify(v.attributes) === JSON.stringify(existingVariant.variant_attributes);
                });
                
                if (matchingVariant) {
                    matchingVariant.sku = existingVariant.sku || '';
                    matchingVariant.price = existingVariant.price || '';
                    matchingVariant.sale_price = existingVariant.sale_price || '';
                    matchingVariant.stock_quantity = existingVariant.stock_quantity || 0;
                    matchingVariant.stock_status = existingVariant.stock_status || 'in_stock';
                    matchingVariant.image = existingVariant.image || '';
                    matchingVariant.is_default = existingVariant.is_default || 0;
                }
            });
            
            // Re-render table with populated data
            renderVariantsTable();
            updateVariantsDataInput();
        }
    }, 500);
}
</script>

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
            console.error('Failed to add brand:', data.message);
        }
    } catch (error) {
        console.error('Error adding brand:', error);
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

function checkStockStatus(input) {
    const stockStatus = document.getElementById('stock_status');
    if (input.value && parseInt(input.value) <= 0) {
        stockStatus.value = 'out_of_stock';
    } else if (input.value && parseInt(input.value) > 0) {
        // Optional: Automatically set back to in_stock if user enters a positive number
        if(stockStatus.value === 'out_of_stock') {
            stockStatus.value = 'in_stock';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

