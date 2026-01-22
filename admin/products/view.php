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

if ($productIdParam) {
    $productData = $product->getByProductId($productIdParam);
} elseif ($id) {
    $productData = $product->getById($id);
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
            
            <!-- Main Image -->
            <div class="mb-4">
                <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                     alt="<?php echo htmlspecialchars($productData['name']); ?>" 
                     class="w-full h-96 object-cover rounded-lg border border-gray-200">
            </div>
            
            <!-- Thumbnail Images -->
            <?php if (!empty($images)): ?>
            <div class="grid grid-cols-4 gap-3">
                <?php foreach ($images as $index => $image): 
                    $imageUrl = getImageUrl($image);
                ?>
                <div class="relative">
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="Product Image <?php echo $index + 1; ?>" 
                         class="w-full h-24 object-cover rounded border border-gray-200 cursor-pointer hover:border-blue-500 transition"
                         onclick="document.querySelector('.admin-card img').src = this.src">
                    <?php if ($image === $productData['featured_image']): ?>
                    <span class="absolute top-1 right-1 bg-blue-500 text-white text-xs px-2 py-1 rounded">Featured</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-4">No images available</p>
            <?php endif; ?>
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
                        $attrString = is_array($attributes) ? implode(' / ', array_values($attributes)) : 'Variant';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <img src="<?php echo htmlspecialchars($variantImage); ?>" 
                                 alt="<?php echo htmlspecialchars($attrString); ?>" 
                                 class="w-12 h-12 object-cover rounded border border-gray-200">
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($attrString); ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($variant['sku'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm font-bold text-gray-900">
                                <?php echo format_price($variant['price'] ?: $productData['price'], $productData['currency'] ?? 'USD'); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold <?php echo $variant['stock_quantity'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $variant['stock_quantity']; ?> in stock
                            </span>
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
            <h2 class="text-xl font-bold mb-4">Product Information</h2>
            
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
                    <p class="text-gray-700"><?php echo htmlspecialchars($productData['category_name'] ?? 'Uncategorized'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Gender</label>
                    <p class="text-gray-700 capitalize"><?php echo htmlspecialchars($productData['gender'] ?? 'Unisex'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Brand</label>
                    <p class="text-gray-700"><?php echo htmlspecialchars($productData['brand'] ?? 'N/A'); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Description</label>
                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($productData['description'] ?? 'No description available')); ?></p>
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
                        <?php 
                        $discount = round((($productData['price'] - $productData['sale_price']) / $productData['price']) * 100);
                        echo $discount . '% off';
                        ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div>
                    <label class="admin-form-label">Stock Quantity</label>
                    <p class="text-lg font-semibold text-gray-900"><?php echo number_format($productData['stock_quantity']); ?></p>
                </div>
                
                <div>
                    <label class="admin-form-label">Stock Status</label>
                    <span class="px-3 py-1 rounded text-sm font-semibold <?php 
                        echo $productData['stock_status'] === 'in_stock' ? 'bg-green-100 text-green-800' : 
                            ($productData['stock_status'] === 'out_of_stock' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $productData['stock_status'])); ?>
                    </span>
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

