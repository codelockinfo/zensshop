<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'View Product';
require_once __DIR__ . '/../../includes/admin-header.php';

$product = new Product();

// Get product ID
$productId = $_GET['id'] ?? null;
if (!$productId) {
    header('Location: /oecom/admin/products/list.php');
    exit;
}

// Get product data
$productData = $product->getById($productId);
if (!$productData) {
    header('Location: /oecom/admin/products/list.php');
    exit;
}

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
            <a href="/oecom/admin/products/edit.php?id=<?php echo $productId; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-edit mr-2"></i> Edit Product
            </a>
            <a href="/oecom/admin/products/list.php" class="admin-btn border border-gray-300 text-gray-600">
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
                    <p class="text-gray-700">#<?php echo $productData['id']; ?></p>
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
                    <label class="admin-form-label">Price</label>
                    <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($productData['price'], 2); ?></p>
                </div>
                
                <?php if ($productData['sale_price']): ?>
                <div>
                    <label class="admin-form-label">Sale Price</label>
                    <p class="text-xl font-bold text-red-600">$<?php echo number_format($productData['sale_price'], 2); ?></p>
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

