<?php
/**
 * Category Page - Display products by category
 */

// Start output buffering to prevent headers already sent errors
ob_start();


// Process redirects BEFORE any output
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = getBaseUrl();
$product = new Product();
$db = Database::getInstance();
require_once __DIR__ . '/classes/Wishlist.php';
$wishlistObj = new Wishlist();
$wishlistItems = $wishlistObj->getWishlist();
$wishlistIds = array_column($wishlistItems, 'product_id');

// Get category slug from URL
$categorySlug = $_GET['slug'] ?? '';
$sort = trim($_GET['sort'] ?? 'created_at DESC');

if (empty($categorySlug)) {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . url('collections.php'));
    exit;
}

// Get current Store ID
$storeId = defined('CURRENT_STORE_ID') ? CURRENT_STORE_ID : ($_SESSION['store_id'] ?? null);

// Get category info (Store Specific or Global)
$category = $db->fetchOne(
    "SELECT * FROM categories WHERE slug = ? AND status = 'active' AND (store_id = ? OR store_id IS NULL OR ? = 'DEFAULT')",
    [$categorySlug, $storeId, $storeId]
);

// If not found with strict slug, try case-insensitive check
if (!$category) {
    $category = $db->fetchOne(
        "SELECT * FROM categories WHERE LOWER(slug) = LOWER(?) AND status = 'active'",
        [$categorySlug]
    );
}

if (!$category) {
    ob_end_clean(); // Clear any buffered output
    header('Location: ' . url('collections.php'));
    exit;
}

// Get products for this category with sorting
$filters = [
    'category_slug' => $categorySlug,
    'sort' => $sort,
    'status' => 'active',
    'store_id' => $storeId
];
$products = $product->getAll($filters);

// Handle AJAX Request for sorting
if (isset($_GET['ajax'])) {
    ob_end_clean();
    if (empty($products)) {
        echo '<div class="col-span-full text-center py-16">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-bold mb-2">No products found</h2>
                <p class="text-gray-600 mb-6">This category doesn\'t have any products matching your criteria.</p>
              </div>';
    } else {
        foreach ($products as $item) {
            $mainImage = getProductImage($item);
            $price = $item['sale_price'] ?? $item['price'];
            $originalPrice = $item['sale_price'] ? $item['price'] : null;
            $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
            $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
            $inWishlist = in_array($currentId, $wishlistIds);

            // Get first variant for default attributes
            $vData = $product->getVariants($item['product_id']);
            $defaultAttributes = [];
            if (!empty($vData['variants'])) {
                $defaultVariant = $vData['variants'][0];
                foreach ($vData['variants'] as $v) {
                    if (!empty($v['is_default'])) {
                        $defaultVariant = $v;
                        break;
                    }
                }
                $defaultAttributes = $defaultVariant['variant_attributes'];
            }
            $attributesJson = json_encode($defaultAttributes);
            $isOutOfStock = ($item['stock_status'] === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
            
            echo '<div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">
                    <div class="relative overflow-hidden">
                        <a href="' . url('product.php?slug=' . urlencode($item['slug'] ?? '')) . '">
                            <img src="' . htmlspecialchars($mainImage) . '" 
                                 alt="' . htmlspecialchars($item['name']) . '" 
                                 class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                        </a>';
            
            if ($discount > 0) {
                echo '<span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-' . $discount . '%</span>';
            }
            
            echo '      <button class="absolute top-2 right-2 w-10 h-10 rounded-full flex items-center justify-center ' . ($inWishlist ? 'bg-black text-white' : 'bg-white text-black') . ' hover:bg-black hover:text-white transition z-20 wishlist-btn" 
                                aria-label="' . ($inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist') . '"
                                data-product-id="' . $currentId . '"
                                title="' . ($inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist') . '">
                            <i class="' . ($inWishlist ? 'fas' : 'far') . ' fa-heart" aria-hidden="true"></i>
                            <span class="product-tooltip">' . ($inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist') . '</span>
                        </button>
                        
                        <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                            <a href="' . $baseUrl . '/product.php?slug=' . urlencode($item['slug'] ?? '') . '" 
                               class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                               data-product-id="' . $item['product_id'] . '"
                               aria-label="Quick view product"
                               data-product-slug="' . htmlspecialchars($item['slug'] ?? '') . '">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span class="product-tooltip">Quick View</span>
                            </a>
                            <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group ' . ($isOutOfStock ? 'opacity-50 cursor-not-allowed' : '') . '" 
                                    data-product-id="' . $item['product_id'] . '"
                                    aria-label="Add product to cart"
                                    data-attributes=\'' . htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8') . '\'
                                    ' . ($isOutOfStock ? 'disabled' : '') . '>
                                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                <span class="product-tooltip">' . ($isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart') . '</span>
                            </button>
                        </div>
                    </div>';
            
            echo '  <div class="p-4">
                        <h3 class="font-semibold text-gray-800 mb-2 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 3rem; line-height: 1.5rem;" title="' . htmlspecialchars($item['name'] ?? 'Product') . '">
                            <a href="' . $baseUrl . '/product.php?slug=' . urlencode($item['slug'] ?? '') . '" class="hover:text-primary transition">
                                ' . htmlspecialchars($item['name']) . '
                            </a>
                        </h3>
                        <div class="flex items-center mb-3">
                            <div class="flex text-yellow-400">';
            
            $itemRating = floor($item['rating'] ?? 5);
            for ($i = 0; $i < 5; $i++) {
                echo '<i class="fas fa-star text-sm ' . ($i < $itemRating ? '' : 'text-gray-300') . '"></i>';
            }
            
            echo '          </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-md font-bold ' . ($discount > 0 ? 'text-[#1a3d32]' : 'text-primary') . '">' . format_price($price, $item['currency'] ?? 'USD') . '</p>';
            
            if ($originalPrice) {
                echo '<span class="text-gray-400 line-through text-sm block">' . format_price($originalPrice, $item['currency'] ?? 'USD') . '</span>';
            }
            
            echo '      </div>
                    </div>
                </div>';
        }
    }
    exit;
}

// Clear output buffer before including header
ob_end_clean();

$pageTitle = 'Category';
require_once __DIR__ . '/includes/header.php';

// Get category image (prefer banner if available, then fallback to image)
$catImageRaw = !empty($category['banner']) ? $category['banner'] : ($category['image'] ?? null);
$categoryImage = getImageUrl($catImageRaw);

if (empty($catImageRaw)) {
    // Use placeholder if absolutely no image
    $categoryImage = 'data:image/svg+xml;base64,' . base64_encode('<svg width="1200" height="400" viewBox="0 0 1200 400" xmlns="http://www.w3.org/2000/svg"><rect width="1200" height="400" fill="#F3F4F6"/><circle cx="600" cy="200" r="80" fill="#9B7A8A"/><path d="M400 350C400 300 500 250 600 250C700 250 800 300 800 350" fill="#9B7A8A"/></svg>');
}

?>

<section class="py-16 md:py-24 bg-white min-h-screen">
    <div class="container mx-auto px-4">
        
        <!-- Category Skeleton -->
        <div id="categorySkeleton" class="animate-pulse">
            <!-- Breadcrumbs Skeleton -->
            <div class="h-4 bg-gray-200 rounded w-1/3 mb-8"></div>

            <!-- Banner Skeleton -->
            <div class="w-full h-[300px] bg-gray-200 rounded-lg mb-12"></div>

            <!-- Toolbar Skeleton -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 border-b pb-6 gap-4">
                <div class="h-6 bg-gray-200 rounded w-48"></div>
                <div class="h-10 bg-gray-200 rounded w-64"></div>
            </div>

            <!-- Grid Skeleton -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php for($i=0; $i<8; $i++): ?>
                <div class="bg-white rounded-lg overflow-hidden shadow-md p-4 space-y-4">
                    <div class="w-full h-64 bg-gray-200 rounded-lg"></div>
                    <div class="space-y-2">
                        <div class="h-6 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                    </div>
                    <div class="h-6 bg-gray-200 rounded w-1/4 pt-2"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div id="mainCategoryContent" class="hidden">
            <!-- Breadcrumbs -->
            <nav class="text-sm text-gray-600 mb-8">
                <a href="<?php echo url(''); ?>" class="hover:text-primary">Home</a> > 
                <a href="<?php echo url('collections.php'); ?>" class="hover:text-primary">Collections</a> > 
                <span class="text-gray-900"><?php echo htmlspecialchars($category['name']); ?></span>
            </nav>

            <!-- Category Hero Section -->
            <div class="mb-12">
                <div class="relative overflow-hidden rounded-lg" style="height: 300px;">
                    <img src="<?php echo htmlspecialchars($categoryImage); ?>" 
                         alt="<?php echo htmlspecialchars($category['name']); ?>"
                         class="w-full h-full object-cover"
                         onerror="this.src='https://placehold.co/1200x400?text=Category+Banner'">
                    <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                        <div class="text-center text-white">
                            <h1 class="text-4xl md:text-5xl font-heading font-bold mb-4 text-white">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h1>
                            <?php if (!empty($category['description'])): ?>
                            <p class="text-lg max-w-2xl mx-auto">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sorting and Toolbar -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 border-b pb-6">
                <div class="text-gray-600">
                    <span id="resultsCount">Showing <?php echo count($products); ?> products</span>
                </div>
                <div class="flex items-center gap-4">
                    <label for="sortSelect" class="text-sm font-medium text-gray-700 whitespace-nowrap">Sort by:</label>
                    <select id="sortSelect" onchange="applySort()" class="border border-gray-300 rounded-md text-sm px-3 py-2 outline-none focus:ring-1 focus:ring-primary">
                        <option value="created_at DESC" <?php echo $sort === 'created_at DESC' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price ASC" <?php echo $sort === 'price ASC' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price DESC" <?php echo $sort === 'price DESC' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_ASC" <?php echo ($sort === 'name_ASC' || $sort === 'name ASC') ? 'selected' : ''; ?>>Name: A-Z</option>
                        <option value="name_DESC" <?php echo ($sort === 'name_DESC' || $sort === 'name DESC') ? 'selected' : ''; ?>>Name: Z-A</option>
                        <option value="rating DESC" <?php echo $sort === 'rating DESC' ? 'selected' : ''; ?>>Rating: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid Container -->
            <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 min-h-[400px]">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $item): 
                        $mainImage = getProductImage($item);
                        $price = $item['sale_price'] ?? $item['price'];
                        $originalPrice = $item['sale_price'] ? $item['price'] : null;
                        $discount = $originalPrice ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
                        $currentId = !empty($item['product_id']) ? $item['product_id'] : $item['id'];
                        $inWishlist = in_array($currentId, $wishlistIds);

                        // Get first variant for default attributes
                        $vData = $product->getVariants($item['product_id']);
                        $defaultAttributes = [];
                        if (!empty($vData['variants'])) {
                            $defaultVariant = $vData['variants'][0];
                            foreach ($vData['variants'] as $v) {
                                if (!empty($v['is_default'])) {
                                    $defaultVariant = $v;
                                    break;
                                }
                            }
                            $defaultAttributes = $defaultVariant['variant_attributes'];
                        }
                        $attributesJson = json_encode($defaultAttributes);
                        $isOutOfStock = ($item['stock_status'] === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0));
                    ?>
                    <div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">
                        <div class="relative overflow-hidden">
                            <a href="<?php echo url('product.php?slug=' . urlencode($item['slug'] ?? '')); ?>">
                                <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                                     onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                            </a>
                            
                            <?php if ($discount > 0): ?>
                            <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                            
                            <button class="absolute top-2 right-2 w-10 h-10 rounded-full flex items-center justify-center <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white transition z-20 wishlist-btn" 
                                    data-product-id="<?php echo $currentId; ?>"
                                    aria-label="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                                    title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
                                <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                            </button>
                            
                            <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30">
                                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" 
                                   class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                                   data-product-id="<?php echo $item['product_id']; ?>"
                                   aria-label="Quick view product"
                                   data-product-slug="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                    <span class="product-tooltip">Quick View</span>
                                </a>
                                <button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                        data-product-id="<?php echo $item['product_id']; ?>"
                                        aria-label="Add product to cart"
                                        data-attributes='<?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>'
                                        <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                    <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                    <span class="product-tooltip"><?php echo $isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-800 mb-2 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 3rem; line-height: 1.5rem;" title="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($item['slug'] ?? ''); ?>" class="hover:text-primary transition">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h3>
                            <div class="flex items-center mb-3">
                                <div class="flex text-yellow-400">
                                    <?php 
                                    $rating = floor($item['rating'] ?? 5);
                                    for ($i = 0; $i < 5; $i++): 
                                    ?>
                                    <i class="fas fa-star text-sm <?php echo $i < $rating ? '' : 'text-gray-300'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <p class="text-md font-bold <?php echo $discount > 0 ? 'text-[#1a3d32]' : 'text-primary'; ?>"><?php echo format_price($price, $item['currency'] ?? 'USD'); ?></p>
                                <?php if ($originalPrice): ?>
                                <span class="text-gray-400 line-through text-sm block"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="col-span-full text-center py-16">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <h2 class="text-2xl font-bold mb-2">No products found</h2>
                    <p class="text-gray-600 mb-6">This category doesn't have any products matching your criteria.</p>
                    <a href="<?php echo url('shop'); ?>" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-light hover:text-white transition">
                        Browse All Products
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Skeleton Loader Handling
document.addEventListener('DOMContentLoaded', function() {
    const skeleton = document.getElementById('categorySkeleton');
    const content = document.getElementById('mainCategoryContent');
    if (skeleton && content) {
        skeleton.classList.add('hidden');
        content.classList.remove('hidden');
    }
});
</script>

<script>
function applySort() {
    const sortSelect = document.getElementById('sortSelect');
    const sortValue = sortSelect.value;
    const grid = document.getElementById('productsGrid');
    
    // Show loading state
    grid.style.opacity = '0.5';
    grid.style.pointerEvents = 'none';
    
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortValue);
    
    const queryString = params.toString();
    const newUrl = window.location.pathname + '?' + queryString;
    
    // Update browser URL without reload
    history.pushState({}, '', newUrl);
    
    // Fetch products via AJAX
    fetch(newUrl + '&ajax=1')
        .then(response => response.text())
        .then(html => {
            grid.innerHTML = html;
            grid.style.opacity = '1';
            grid.style.pointerEvents = 'auto';
            
            // Re-initialize product cards for newly added elements
            if (typeof initializeProductCards === 'function') {
                initializeProductCards();
            }
            
            // Update results count
            const productsCount = grid.querySelectorAll('.product-card').length;
            const countDisplay = document.getElementById('resultsCount');
            if (countDisplay) {
                countDisplay.textContent = `Showing ${productsCount} products`;
            }
        })
        .catch(error => {
            console.error('Error fetching sorted products:', error);
            grid.style.opacity = '1';
            grid.style.pointerEvents = 'auto';
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


