<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'View Product';
require_once __DIR__ . '/../../includes/admin-header.php';

$product = new Product();

// Get product ID or 10-digit product_id
$id = $_GET['id'] ?? null;
$productIdParam = $_GET['product_id'] ?? null;
$storeId = $_SESSION['store_id'] ?? null;

if ($productIdParam) {
    $productData = $product->getByProductId($productIdParam, $storeId);
} elseif ($id) {
    $productData = $product->getById($id, $storeId);
} else {
    header('Location: ' . url('admin/products/list.php'));
    exit;
}

if (!$productData) {
    header('Location: ' . $baseUrl . '/admin/products/list.php');
    exit;
}

$productId = $productData['id']; // Internal ID for database queries
$product_id = $productData['product_id']; // 10-digit ID for URLs

// Parse images
$images = json_decode($productData['images'] ?? '[]', true);
$mainImage = getProductImage($productData);
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">View Product</h1>
            <p class="text-gray-600">Dashboard > Ecommerce > View product</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="<?php echo url('admin/products/edit.php?product_id=' . $product_id); ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-edit mr-2"></i> Edit Product
            </a>
            <a href="<?php echo url('admin/products/list.php'); ?>" class="admin-btn border border-gray-300 text-gray-600">
                <i class="fas fa-arrow-left mr-2"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column - Product Images -->
    <div class="space-y-6">
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product Images</h2>
            
            <!-- Main Image/Video Display -->
            <div class="mb-4" id="main-media-container">
                <?php 
                $mainExt = strtolower(pathinfo($mainImage, PATHINFO_EXTENSION));
                $isMainVideo = in_array($mainExt, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v']);
                ?>
                
                <?php if ($isMainVideo): ?>
                    <video src="<?php echo htmlspecialchars($mainImage); ?>" 
                           controls 
                           class="w-full h-96 object-contain rounded-lg border border-gray-200 bg-black"></video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                         alt="<?php echo htmlspecialchars($productData['name']); ?>" 
                         class="w-full h-96 object-contain rounded-lg border border-gray-200 bg-gray-50">
                <?php endif; ?>
            </div>
            
            <!-- Thumbnail Images -->
            <?php if (!empty($images)): ?>
            <div class="grid grid-cols-4 gap-3">
                <?php foreach ($images as $index => $image): 
                    $imageUrl = getImageUrl($image);
                    $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
                    $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v']);
                ?>
                <div class="relative group">
                    <?php if ($isVideo): ?>
                        <div class="w-full h-24 rounded border border-gray-200 cursor-pointer hover:border-blue-500 transition bg-black relative overflow-hidden"
                             onclick="showMainMedia('<?php echo htmlspecialchars($imageUrl); ?>', 'video')">
                            <video src="<?php echo htmlspecialchars($imageUrl); ?>" class="w-full h-full object-cover opacity-70"></video>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-play-circle text-white text-3xl opacity-90"></i>
                            </div>
                        </div>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="Product Image <?php echo $index + 1; ?>" 
                             class="w-full h-24 object-cover rounded border border-gray-200 cursor-pointer hover:border-blue-500 transition bg-white"
                             onclick="showMainMedia('<?php echo htmlspecialchars($imageUrl); ?>', 'image')">
                    <?php endif; ?>
                    
                    <?php if ($image === $productData['featured_image']): ?>
                    <span class="absolute top-1 right-1 bg-blue-500 text-white text-xs px-2 py-1 rounded shadow-sm z-10">Featured</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-4">No images available</p>
            <?php endif; ?>

            <script>
            function showMainMedia(url, type) {
                const container = document.getElementById('main-media-container');
                if (type === 'video') {
                    container.innerHTML = `<video src="${url}" controls autoplay class="w-full h-96 object-contain rounded-lg border border-gray-200 bg-black"></video>`;
                } else {
                    container.innerHTML = `<img src="${url}" class="w-full h-96 object-contain rounded-lg border border-gray-200 bg-gray-50">`;
                }
            }
            </script>
        </div>
        
<?php 
$productClass = new Product();
$variantsData = $productClass->getVariants($productId);
$variants = $variantsData['variants'] ?? [];
?>

<?php if (!empty($variants)): ?>
<div class="mt-6 mb-6">
    <div class="admin-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Product Variants</h2>
            <a href="<?php echo url('admin/products/edit.php?product_id=' . $product_id); ?>" 
               class="admin-btn admin-btn-primary py-1.5 px-3 text-sm">
                <i class="fas fa-cog mr-2"></i> Manage All Variants
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase">Image</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase">Variant</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase">SKU</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase">Price</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase">Stock</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($variants as $variant): 
                        $variantImage = !empty($variant['image']) ? $variant['image'] : $mainImage;
                        $attributes = $variant['variant_attributes'];
                        $attrParts = [];
                        if (is_array($attributes)) {
                            foreach ($attributes as $k => $v) {
                                $attrParts[] = ucfirst($k) . ': ' . $v;
                            }
                        }
                        $attrString = !empty($attrParts) ? implode(' / ', $attrParts) : 'Variant';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <img src="<?php echo htmlspecialchars($variantImage); ?>" 
                                 alt="<?php echo htmlspecialchars($attrString); ?>" 
                                 class="w-12 h-12 object-cover rounded border border-gray-200">
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900 max-w-xs leading-snug">
                                <?php echo htmlspecialchars($attrString); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($variant['sku'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm font-bold text-gray-900">
                                <?php echo format_price($variant['price'] ?: $productData['price'], $productData['currency'] ?? 'USD'); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php 
                            $vQty = (int)$variant['stock_quantity'];
                            $vStatus = $variant['stock_status'] ?? 'in_stock';
                            $vLabel = get_stock_status_text($vStatus, $vQty);
                            $vIsNegative = $vQty < 0;
                            ?>
                            <div class="flex flex-col">
                                <span class="px-2 py-1 rounded-full text-xs font-bold whitespace-nowrap inline-block w-fit <?php echo $vQty > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo $vQty; ?> in stock
                                </span>
                                <?php if ($vIsNegative): ?>
                                    <span class="text-[10px] text-red-600 font-bold mt-1 uppercase italic"><i class="fas fa-exclamation-triangle mr-1"></i> Oversold</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?php echo url('admin/products/edit.php?product_id=' . $product_id); ?>" 
                               class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition text-sm font-medium"
                               title="Edit Variant">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

    </div>
    
    <!-- Right Column - Product Information -->
    <div class="space-y-6">
        <!-- Basic Information -->
        <div class="admin-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Product Information</h2>
                <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($productData['slug']); ?>" target="_blank" class="text-primary hover:underline text-sm font-bold flex items-center">
                    <i class="fas fa-eye mr-1"></i> Live Preview
                </a>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="admin-form-label">Product Name</label>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($productData['name']); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Product ID</label>
                    <p class="text-gray-700">#<?php echo $productData['product_id']; ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Slug</label>
                    <p class="text-gray-700"><?php echo htmlspecialchars($productData['slug'] ?? 'N/A'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Category</label>
                    <div class="flex flex-wrap gap-2 py-1">
                    <?php 
                    // 1. Get IDs from Source of Truth (products table)
                    $rawCatId = trim($productData['category_id'] ?? '');
                    $targetIds = [];
                    
                    if (!empty($rawCatId)) {
                        $decoded = json_decode($rawCatId, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $targetIds = array_map('strval', $decoded);
                        } else {
                            // Extract numeric IDs even if JSON is malformed
                            if (preg_match_all('/\d+/', $rawCatId, $matches)) {
                                $targetIds = $matches[0];
                            } else {
                                $targetIds = [(string)$rawCatId];
                            }
                        }
                    }

                    // 2. Fallback to mapping table if still empty
                    if (empty($targetIds)) {
                        $mappingIds = $db->fetchAll("SELECT category_id FROM product_categories WHERE product_id = ?", [$productData['product_id']]);
                        $targetIds = array_column($mappingIds, 'category_id');
                    }

                    // 3. Fetch names for all IDs (Global check, no status/store filter here to see everything)
                    if (!empty($targetIds)) {
                        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
                        $categoriesFound = $db->fetchAll("SELECT name FROM categories WHERE id IN ($placeholders)", $targetIds);
                        
                        if (!empty($categoriesFound)) {
                            foreach ($categoriesFound as $cat) {
                                echo '<span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded shadow-sm">' . htmlspecialchars($cat['name']) . '</span>';
                            }
                        } else {
                            echo '<p class="text-xs text-gray-500 italic">Categories found in record (' . implode(',', $targetIds) . ') but names not found in database.</p>';
                        }
                    } else {
                        echo '<p class="text-gray-700 italic">Uncategorized</p>';
                    }
                    ?>
                    </div>
                </div>
                
                
                <div>
                    <label class="admin-form-label">Brand</label>
                    <p class="text-gray-700"><?php echo htmlspecialchars($productData['brand'] ?? 'N/A'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Description</label>
                    <div class="text-gray-700 prose prose-sm max-w-none"><?php echo $productData['description'] ?? 'No description available'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Pricing & Stock -->
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Pricing & Stock</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="admin-form-label">SKU</label>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($productData['sku'] ?? 'N/A'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Price</label>
                    <p class="text-2xl font-bold text-gray-900"><?php echo format_price($productData['price'], $productData['currency'] ?? 'USD'); ?></p>
                </div>
                
                <?php if ($productData['sale_price']): ?>
                <div>
                    <label class="admin-form-label">Sale Price</label>
                    <p class="text-xl font-bold text-red-600"><?php echo format_price($productData['sale_price'], $productData['currency'] ?? 'USD'); ?></p>
                    <p class="text-sm text-gray-500">
                        Discount: <?php 
                        $discount = round((($productData['price'] - $productData['sale_price']) / $productData['price']) * 100);
                        echo $discount . '% off';
                        ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                    <div>
                        <label class="admin-form-label">Cost per item</label>
                        <p class="text-lg font-semibold text-gray-700"><?php echo format_price($productData['cost_per_item'] ?? 0, $productData['currency'] ?? 'USD'); ?></p>
                    </div>
                    <div>
                        <label class="admin-form-label">Total expense</label>
                        <p class="text-lg font-semibold text-gray-700"><?php echo format_price($productData['total_expense'] ?? 0, $productData['currency'] ?? 'USD'); ?></p>
                    </div>
                </div>

                <div class="mt-2 bg-gray-50 p-2 rounded border border-dashed border-gray-200">
                    <label class="text-[10px] font-bold uppercase text-gray-400">Total Expenditure</label>
                    <p class="text-xl font-bold text-primary">
                        <?php 
                        $totalExp = (float)($productData['cost_per_item'] ?? 0) + (float)($productData['total_expense'] ?? 0);
                        echo format_price($totalExp, $productData['currency'] ?? 'USD'); 
                        ?>
                    </p>
                </div>
                
                <div>
                    <label class="admin-form-label">Stock Quantity</label>
                    <div class="flex items-center space-x-2">
                        <p class="text-lg font-semibold <?php echo $productData['stock_quantity'] < 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                            <?php echo number_format($productData['stock_quantity']); ?>
                        </p>
                        <?php if ($productData['stock_quantity'] < 0): ?>
                            <span class="bg-red-600 text-white text-[10px] px-2 py-0.5 rounded font-bold uppercase ring-2 ring-red-100 animate-pulse">Oversold</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <label class="admin-form-label">Stock Status</label>
                    <?php 
                    $qty = (int)$productData['stock_quantity'];
                    $totalSold = (int)($productData['total_sales'] ?? 0);
                    $status = $productData['stock_status'] ?? 'in_stock';
                    $labelText = get_stock_status_text($status, $qty, $totalSold);
                    
                    $isAvailable = ($labelText === 'In Stock');
                    $isSoldOut = ($labelText === 'Sold Out');
                    $isBackorder = ($labelText === 'On Backorder');
                    
                    $statusClass = $isAvailable ? 'bg-green-100 text-green-800' : 
                                  ($isSoldOut ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800');
                    ?>
                    <span class="px-3 py-1 rounded text-sm font-semibold <?php echo $statusClass; ?>">
                        <?php echo $labelText; ?>
                    </span>
                </div>

                <div>
                    <label class="admin-form-label">Total Sales</label>
                    <p class="text-lg font-bold text-gray-900">
                        <i class="fas fa-shopping-basket text-primary mr-1"></i>
                        <?php echo number_format($totalSold); ?> Units
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Product Status -->
        <div class="admin-card">
            <h2 class="text-xl font-bold mb-4">Product Status</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="admin-form-label">Status</label>
                    <span class="px-3 py-1 rounded text-sm font-semibold <?php 
                        echo $productData['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                            ($productData['status'] === 'draft' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800'); 
                    ?>">
                        <?php echo ucfirst($productData['status']); ?>
                    </span>
                </div>
                
                <div>
                    <label class="admin-form-label">Featured Product</label>
                    <p class="text-gray-700">
                        <?php if ($productData['featured']): ?>
                            <span class="text-green-600 font-semibold"><i class="fas fa-check-circle"></i> Yes</span>
                        <?php else: ?>
                            <span class="text-gray-500"><i class="fas fa-times-circle"></i> No</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div>
                    <label class="admin-form-label">Rating</label>
                    <div class="flex items-center space-x-2">
                        <div class="flex text-yellow-400">
                            <?php 
                            $rating = floor($productData['rating'] ?? 0);
                            for ($i = 0; $i < 5; $i++): 
                            ?>
                            <i class="fas fa-star <?php echo $i < $rating ? '' : 'text-gray-300'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-gray-700"><?php echo number_format($productData['rating'] ?? 0, 1); ?> (<?php echo number_format($productData['review_count'] ?? 0); ?> reviews)</span>
                    </div>
                </div>
                
                <div>
                    <label class="admin-form-label">Created Date</label>
                    <p class="text-gray-700"><?php echo date('F d, Y h:i A', strtotime($productData['created_at'])); ?></p>
                </div>
                
                <?php if ($productData['updated_at'] && $productData['updated_at'] !== $productData['created_at']): ?>
                <div>
                    <label class="admin-form-label">Last Updated</label>
                    <p class="text-gray-700"><?php echo date('F d, Y h:i A', strtotime($productData['updated_at'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

