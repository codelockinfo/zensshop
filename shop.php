<?php
$pageTitle = 'Shop';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Wishlist.php';

// Define Base URL if not already defined (since header is included later)
// Store ID is only used for admin side logic. 
// On the front side we show everything regardless of store_id as it's filtered by domain/installation.
$storeId = null;

$baseUrl = getBaseUrl();
// require_once __DIR__ . '/includes/header.php'; // Moved down

$product = new Product();
$db = Database::getInstance();
$wishlistObj = new Wishlist();

// Get Wishlist IDs for checking status
$wishlistItems = $wishlistObj->getWishlist();
$wishlistIds = array_column($wishlistItems, 'product_id');

// Get filters from URL
$categorySlug = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$stockStatus = $_GET['stock'] ?? '';
$sort = trim($_GET['sort'] ?? 'created_at DESC'); // Trim whitespace
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

// Build filters
$filters = [
    'status' => 'active'
];

if ($categorySlug) {
    $filters['category_slug'] = $categorySlug;
    // Get category info for hero section
    $category = $db->fetchOne("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$categorySlug]);
} else {
    $category = null;
}

if ($search) {
    $filters['search'] = $search;
}

if ($minPrice) {
    $filters['min_price'] = floatval($minPrice);
}

if ($maxPrice) {
    $filters['max_price'] = floatval($maxPrice);
}

if ($stockStatus) {
    $filters['stock_status'] = $stockStatus;
}

$filters['sort'] = $sort;

// Get total count (without limit)
$countFilters = $filters;
unset($countFilters['limit'], $countFilters['offset']);
$allProducts = $product->getAll($countFilters);
$totalProducts = count($allProducts);
$totalPages = ceil($totalProducts / $perPage);

// Get paginated products
$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;
$products = $product->getAll($filters);

// Get all categories for sidebar (only show categories with products)
$categories = $db->fetchAll("SELECT c.*, 
                               (SELECT COUNT(DISTINCT p.id) 
                                FROM products p 
                                LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                                WHERE p.status = 'active' AND (p.category_id = c.id OR pc.category_id = c.id)
                               ) as product_count 
                               FROM categories c 
                               WHERE c.status = 'active' AND (c.store_id = ? OR c.store_id IS NULL)
                               HAVING product_count > 0
                               ORDER BY c.sort_order ASC, c.name ASC", [CURRENT_STORE_ID]);

// Get stock counts
$inStockCount = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count 
                                FROM products p 
                                LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                                WHERE p.status = 'active' AND p.stock_status = 'in_stock'" . 
                                ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ? AND (store_id = ? OR store_id IS NULL) LIMIT 1) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.product_id AND c2.slug = ? AND (c2.store_id = ? OR c2.store_id IS NULL)))" : ""),
                                array_merge([], $categorySlug ? [$categorySlug, CURRENT_STORE_ID, $categorySlug, CURRENT_STORE_ID] : []))['count'] ?? 0;

$outOfStockCount = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count 
                                   FROM products p 
                                   LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                                   WHERE p.status = 'active' AND p.stock_status = 'out_of_stock'" . 
                                   ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ? AND (store_id = ? OR store_id IS NULL) LIMIT 1) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.product_id AND c2.slug = ? AND (c2.store_id = ? OR c2.store_id IS NULL)))" : ""),
                                   array_merge([], $categorySlug ? [$categorySlug, CURRENT_STORE_ID, $categorySlug, CURRENT_STORE_ID] : []))['count'] ?? 0;

// Get price range
$priceRange = $db->fetchOne("SELECT MIN(COALESCE(p.sale_price, p.price)) as min_price, 
                              MAX(COALESCE(p.sale_price, p.price)) as max_price 
                              FROM products p 
                              LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                              WHERE p.status = 'active'" . 
                              ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ? AND (store_id = ? OR store_id IS NULL) LIMIT 1) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.product_id AND c2.slug = ? AND (c2.store_id = ? OR c2.store_id IS NULL)))" : ""),
                              array_merge([], $categorySlug ? [$categorySlug, CURRENT_STORE_ID, $categorySlug, CURRENT_STORE_ID] : []));
$minPriceRange = $priceRange['min_price'] ?? 0;
$maxPriceRange = $priceRange['max_price'] ?? 1000;

// Handle AJAX Request
if (isset($_GET['ajax'])) {
    if (empty($products)) {
        echo '<div class="col-span-full text-center py-12">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No products found</p>
              </div>';
    } else {
        foreach ($products as $item) {
            // Skip invalid items
            if (empty($item) || !is_array($item)) continue;
            
            $mainImage = getProductImage($item);
            $price = $item['sale_price'] ?? $item['price'] ?? 0;
            $originalPrice = !empty($item['sale_price']) ? ($item['price'] ?? 0) : null;
            $discount = $originalPrice && $originalPrice > 0 ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
            $itemName = $item['name'] ?? 'Product';
            $itemSlug = $item['slug'] ?? '';
            $itemId = $item['id'] ?? 0;
            
            $currentId = !empty($item['product_id']) ? $item['product_id'] : $itemId;
            $inWishlist = in_array($currentId, $wishlistIds);
            
            // Rating calculation
            $rating = floatval($item['rating'] ?? 0);
            $fullStars = floor($rating);
            $hasHalfStar = ($rating - $fullStars) >= 0.5;
            
            echo '<div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">';
                
                // Image Wrapper
                echo '<div class="relative overflow-hidden card-image-wrap">';
                    echo '<a href="' . url('product?slug=' . urlencode($itemSlug)) . '">';
                    echo '<img src="' . htmlspecialchars($mainImage) . '" alt="' . htmlspecialchars($itemName) . '" class="w-full h-64 object-contain group-hover:scale-110 transition-transform duration-500">';
                    echo '</a>';
                    
                    if ($discount > 0) {
                        echo '<span class="absolute top-3 left-3 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">-' . $discount . '%</span>';
                    }
                    
                    echo '<button class="wishlist-btn absolute top-3 right-3 rounded-full w-9 h-9 flex items-center justify-center transition ' . ($inWishlist ? 'bg-black text-white' : 'bg-white text-black') . ' hover:bg-black hover:text-white" data-product-id="' . $currentId . '" title="' . ($inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist') . '">';
                    echo '<i class="' . ($inWishlist ? 'fas' : 'far') . ' fa-heart"></i>';
                    echo '</button>';

                    // Hover Action Buttons
                    echo '<div class="product-actions absolute right-3 top-12 flex flex-col gap-2 mt-2 pr-[10px] md:pr-0 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-300 z-30">';
                        echo '<a href="' . $baseUrl . '/product?slug=' . urlencode($itemSlug) . '" class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" data-product-id="' . $currentId . '" data-product-slug="' . htmlspecialchars($itemSlug) . '">';
                        echo '<i class="fas fa-eye"></i>';
                        echo '<span class="product-tooltip">Quick View</span>';
                        echo '</a>';

                        // Get first variant for default attributes
                        $vData = $product->getVariants($currentId);
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

                        echo '<button class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group ' . ($isOutOfStock ? 'opacity-50 cursor-not-allowed' : '') . '" data-product-id="' . $currentId . '" data-attributes=\'' . htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8') . '\' ' . ($isOutOfStock ? 'disabled' : '') . '>';
                        echo '<i class="fas fa-shopping-cart"></i>';
                        echo '<span class="product-tooltip">' . ($isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity'] ?? 0) : 'Add to Cart') . '</span>';
                        echo '</button>';
                    echo '</div>'; 

                echo '</div>'; // End card-image-wrap
                
                // Content Wrapper
                echo '<div class="p-4 card-content">';
                    echo '<h3 class="font-semibold text-md mb-2 h-10 overflow-hidden line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="' . htmlspecialchars($itemName) . '">';
                    echo '<a href="' . $baseUrl . '/product?slug=' . urlencode($itemSlug) . '" class="hover:text-primary transition">' . htmlspecialchars($itemName) . '</a>';
                    echo '</h3>';
                    
                    // Star Rating
                    echo '<div class="flex items-center mb-3 card-rating">';
                    for ($i = 0; $i < 5; $i++) {
                        $starClass = ($i < $fullStars) ? 'text-yellow-400' : (($i === $fullStars && $hasHalfStar) ? 'text-yellow-400' : 'text-gray-300');
                        echo '<i class="fas fa-star text-sm ' . $starClass . '"></i>';
                    }
                    echo '</div>';
                    
                    // Description
                    echo '<div class="card-description">';
                    $desc = !empty($item['short_description']) ? $item['short_description'] : ($item['description'] ?? '');
                    $desc = strip_tags($desc);
                    echo htmlspecialchars($desc);
                    echo '</div>';
                    
                    // Actions
                    echo '<div class="flex items-center justify-between card-actions">';
                        echo '<div>';
                        if ($originalPrice) {
                            echo '<span class="text-gray-400 text-sm line-through mr-2 font-bold">' . format_price($originalPrice, $item['currency'] ?? 'USD') . '</span>';
                        }
                        echo '<span class="text-md font-bold ' . ($originalPrice ? 'text-[#1a3d32]' : 'text-primary') . '">' . format_price($price, $item['currency'] ?? 'USD') . '</span>';
                        echo '</div>';
                        
                    echo '</div>'; // End actions
                
                echo '</div>'; // End card-content
                
            echo '</div>'; // End product-card
        }
    }
    
    // Pagination (Output hidden or processed by JS)
    if ($totalPages > 1) {
        echo '<div id="ajax-pagination" class="hidden">';
        echo '<div class="flex justify-center items-center space-x-2 mt-8">';
        
        if ($page > 1) {
            echo '<button onclick="applyFilters(' . ($page - 1) . ')" class="px-4 py-2 border rounded hover:bg-gray-50"><i class="fas fa-chevron-left"></i></button>';
        }
        
        for ($i = 1; $i <= min($totalPages, 5); $i++) {
            $activeClass = ($page === $i) ? 'bg-primary text-white' : 'hover:bg-gray-50';
            echo '<button onclick="applyFilters(' . $i . ')" class="px-4 py-2 border rounded ' . $activeClass . '">' . $i . '</button>';
        }
        
        if ($page < $totalPages) {
             echo '<button onclick="applyFilters(' . ($page + 1) . ')" class="px-4 py-2 border rounded hover:bg-gray-50"><i class="fas fa-chevron-right"></i></button>';
        }
        echo '</div>';
        echo '</div>';
    }
    
    exit; // Stop executing script
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<!-- Hero Section -->
<?php 
    $settingsObjShop = new Settings();
    $allCatBanner = $settingsObjShop->get('all_category_banner');
?>

<?php if ($category && (!empty($category['banner']) || !empty($category['image']))): ?>
    <?php
    $bannerPath = !empty($category['banner']) ? $category['banner'] : $category['image'];
    $bannerUrl = $baseUrl . '/' . $bannerPath;
    ?>
    <section class="relative bg-cover bg-center py-20 md:py-32" 
             style="background-image: url('<?php echo htmlspecialchars($bannerUrl); ?>');">
        
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center">
                <nav class="text-sm text-gray-200 mb-4">
                    <a href="<?php echo $baseUrl; ?>/" class="hover:text-white">Home</a> > 
                    <span class="text-white"><?php echo htmlspecialchars($category['name'] ?? ''); ?></span>
                </nav>
                <h1 class="text-2xl md:text-4xl font-heading font-bold mb-4 text-white"><?php echo htmlspecialchars($category['name'] ?? ''); ?></h1>
                <?php if (!empty($category['description'])): ?>
                <p class="text-sm text-gray-100 max-w-2xl mx-auto"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php elseif (!$category && $allCatBanner): ?>
    <?php 
        $bannerUrl = $baseUrl . '/assets/images/' . $allCatBanner;
    ?>
    <section class="relative bg-cover bg-center py-20 md:py-32" 
             style="background-image: url('<?php echo htmlspecialchars($bannerUrl); ?>');">
        
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center">
                <nav class="text-sm text-gray-200 mb-4">
                    <a href="<?php echo $baseUrl; ?>/" class="hover:text-white">Home</a> > 
                    <span class="text-white">Shop</span>
                </nav>
                <h1 class="text-2xl md:text-4xl font-heading font-bold mb-4 text-white">Shop</h1>
                <p class="text-sm text-gray-100 max-w-2xl mx-auto">
                    Discover our curated collection of products
                </p>
            </div>
        </div>
    </section>

<?php else: ?>
    <section class="relative bg-gray-200 py-20 md:py-32">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <nav class="text-sm text-gray-600 mb-4">
                    <a href="<?php echo $baseUrl; ?>/" class="hover:text-primary">Home</a> > 
                    <span class="text-gray-900"><?php echo htmlspecialchars($category['name'] ?? 'Shop'); ?></span>
                </nav>
                <h1 class="text-2xl md:text-4xl font-heading font-bold mb-4"><?php echo htmlspecialchars($category['name'] ?? 'Shop'); ?></h1>
                <p class="text-sm text-gray-600 max-w-2xl mx-auto">
                    <?php echo htmlspecialchars($category['description'] ?? 'Discover our curated collection of products'); ?>
                </p>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- List View Styles -->
<style>
/* List View Styles */
button.active {
    background-color: #000; /* Primary color or black */
    color: white;
    border-color: #000;
}
#productsGrid.list-view .product-card {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding: 1rem;
    gap: 2rem;
}

#productsGrid.list-view .card-image-wrap {
    width: 280px;
    flex-shrink: 0;
    height: 250px;
}

#productsGrid.list-view .card-image-wrap img {
    height: 100%;
    width: 100%;
    object-fit: contain;
}

#productsGrid.list-view .card-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 0;
}

#productsGrid.list-view .card-title {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

#productsGrid.list-view .card-rating {
    margin-bottom: 0.5rem;
}

/* Description - Hidden by default (Grid view), Shown in List view */
.card-description {
    display: none;
    width: 100%;
    max-width: 550px;
}

#productsGrid.list-view .card-description {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 1rem;
    color: #666;
    font-size: 0.875rem;
    line-height: 1.5;
}

#productsGrid.list-view .card-actions {
    display: flex;
    justify-content: flex-start; /* Align left */
    align-items: center;
    width: 100%;
    margin-top: auto;
    border: none;
    padding-top: 0;
    gap: 1.5rem;
}

#productsGrid.list-view .card-actions > button {
    order: 1;
}

#productsGrid.list-view .card-actions > div {
    order: 2;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    #productsGrid.list-view .product-card {
        flex-direction: column;
        align-items: flex-start;
    }
    #productsGrid.list-view .card-image-wrap {
        width: 100%;
        margin-right: 0;
        margin-bottom: 1rem;
        height: auto;
    }
    #productsGrid.list-view .card-actions {
        border-top: none;
        padding-top: 0;
    }
    /* Optional: Hide description on very small screens if needed, strictly keeping 2 lines is fine though */
}
</style>

<!-- Main Content -->
<section class="py-12 bg-white">
    <div class="container mx-auto px-4">
        <!-- Mobile Filter Button -->

        
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filters (Desktop) -->
            <aside class="hidden lg:block lg:w-1/4">
                <div class="bg-white border border-gray-200 rounded-lg p-6 sticky top-24">
                    <!-- Products Category -->
                    <div class="mb-6">
                        <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('category')">
                            Products Category
                            <i class="fas fa-chevron-down text-sm" id="category-arrow"></i>
                        </h3>
                        <div id="category-filter" class="space-y-2">
                            <a href="<?php echo $baseUrl; ?>/shop" 
                               class="block text-gray-600 hover:text-primary transition <?php echo !$categorySlug ? 'font-semibold text-primary' : ''; ?>"
                               onclick="event.preventDefault(); selectCategory('');">
                                All Categories
                            </a>
                            <?php foreach ($categories as $cat): ?>
                            <a href="<?php echo $baseUrl; ?>/shop?category=<?php echo urlencode($cat['slug'] ?? ''); ?>" 
                               class="block text-gray-600 hover:text-primary transition <?php echo $categorySlug === ($cat['slug'] ?? '') ? 'font-semibold text-primary' : ''; ?>"
                               onclick="event.preventDefault(); selectCategory('<?php echo htmlspecialchars($cat['slug']); ?>');">
                                <?php echo htmlspecialchars($cat['name'] ?? ''); ?> (<?php echo $cat['product_count'] ?? 0; ?>)
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Availability -->
                    <div class="mb-6 border-t pt-6">
                        <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('availability')">
                            Availability
                            <i class="fas fa-chevron-down text-sm" id="availability-arrow"></i>
                        </h3>
                        <div id="availability-filter" class="space-y-2">
                            <label class="flex items-center text-md">
                                <input type="checkbox" name="stock" value="in_stock" 
                                       <?php echo $stockStatus === 'in_stock' ? 'checked' : ''; ?>
                                       onchange="applyFilters()" class="mr-2">
                                <span>In stock (<?php echo $inStockCount; ?>)</span>
                            </label>
                            <label class="flex items-center text-md">
                                <input type="checkbox" name="stock" value="out_of_stock" 
                                       <?php echo $stockStatus === 'out_of_stock' ? 'checked' : ''; ?>
                                       onchange="applyFilters()" class="mr-2">
                                <span>Out of stock (<?php echo $outOfStockCount; ?>)</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="mb-6 border-t pt-6">
                        <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('price')">
                            Price
                            <i class="fas fa-chevron-down text-sm" id="price-arrow"></i>
                        </h3>
                        <div id="price-filter" class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <input type="number" id="minPrice" placeholder="Min" 
                                       value="<?php echo htmlspecialchars($minPrice); ?>"
                                       min="0" step="0.01" class="w-full border rounded px-3 py-2">
                                <span>-</span>
                                <input type="number" id="maxPrice" placeholder="Max" 
                                       value="<?php echo htmlspecialchars($maxPrice); ?>"
                                       min="0" step="0.01" class="w-full border rounded px-3 py-2">
                            </div>
                            <button onclick="applyFilters()" class="w-full bg-primary text-white py-2 rounded hover:bg-primary-light hover:text-white transition">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
            
            <!-- Products Grid -->
            <div class="lg:w-3/4">
                <!-- Results and Sorting -->
                <div class="flex flex-col-reverse md:flex-row justify-between items-center mb-6 gap-4">
                    <p class="text-gray-600 text-sm mb-0 w-full md:w-auto text-center md:text-left">
                        There are <?php echo $totalProducts; ?> results in total
                    </p>
                    <div class="flex w-full md:w-auto justify-between md:justify-end items-center gap-4">
                         <!-- Filter Button (Mobile/Tablet only) -->
                        <button onclick="openFilterDrawer()" class="lg:hidden flex items-center space-x-2 bg-primary text-white px-4 py-2 rounded hover:bg-primary-light hover:text-white transition">
                            <i class="fas fa-filter"></i>
                            <span>Filters</span>
                        </button>

                        <div class="flex items-center gap-4">
                            <!-- Grid/List Toggle - Hidden on small screens -->
                            <div class="hidden md:flex items-center space-x-2">
                                <button id="gridView" class="p-2 border rounded active bg-primary text-white" onclick="setView('grid')">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button id="listView" class="p-2 border rounded" onclick="setView('list')">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                            <select id="sortSelect" onchange="applyFilters()" class="border rounded text-sm pl-2 py-2">
                                <option value="created_at DESC" <?php echo $sort === 'created_at DESC' ? 'selected' : ''; ?>>Sort by: Featured</option>
                                <option value="price ASC" <?php echo $sort === 'price ASC' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price DESC" <?php echo $sort === 'price DESC' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name_ASC" <?php echo ($sort === 'name_ASC' || $sort === 'name ASC') ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="name_DESC" <?php echo ($sort === 'name_DESC' || $sort === 'name DESC') ? 'selected' : ''; ?>>Name: Z to A</option>
                                <option value="rating DESC" <?php echo $sort === 'rating DESC' ? 'selected' : ''; ?>>Rating: High to Low</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($products)): ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">No products found</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($products as $item): 
                        // Skip invalid items
                        if (empty($item) || !is_array($item)) continue;
                        
                        $mainImage = getProductImage($item);
                        $price = $item['sale_price'] ?? $item['price'] ?? 0;
                        $originalPrice = !empty($item['sale_price']) ? ($item['price'] ?? 0) : null;
                        $discount = $originalPrice && $originalPrice > 0 ? round((($originalPrice - $price) / $originalPrice) * 100) : 0;
                        $itemName = $item['name'] ?? 'Product';
                        $itemSlug = $item['slug'] ?? '';
                        $itemId = $item['id'] ?? 0;
                    ?>
                    <div class="product-card bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group relative">

                        <div class="relative overflow-hidden card-image-wrap">
                            <a href="<?php echo url('product?slug=' . urlencode($itemSlug)); ?>">
                                <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                                     alt="<?php echo htmlspecialchars($itemName); ?>" 
                                     class="w-full h-64 object-contain  group-hover:scale-110 transition-transform duration-500">
                            </a>
                            
                            <?php if ($discount > 0): ?>
                            <span class="absolute top-3 left-3 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold z-10">
                                -<?php echo $discount; ?>%
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            $currentId = !empty($item['product_id']) ? $item['product_id'] : $itemId;
                            $inWishlist = in_array($currentId, $wishlistIds);
                            ?>
                            <button type="button" class="absolute top-2 right-2 w-10 h-10 rounded-full flex items-center justify-center <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white transition z-20 wishlist-btn" 
                                    data-product-id="<?php echo $currentId; ?>"
                                    title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span class="product-tooltip"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                            </button>
                            
                            <!-- Hover Action Buttons -->
                            <div class="product-actions absolute right-2 top-12 flex flex-col gap-2 mt-2 pr-[10px] md:pr-0 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-300 z-30">
                                <button type="button" class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg quick-view-btn relative group" 
                                       data-product-id="<?php echo $currentId; ?>"
                                       data-product-slug="<?php echo htmlspecialchars($itemSlug); ?>">
                                    <i class="fas fa-eye"></i>
                                    <span class="product-tooltip">Quick View</span>
                                </button>
                                
                                <?php
                                // Get first variant for default attributes
                                $vData = $product->getVariants($currentId);
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
                                <button type="button" class="product-action-btn w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-black hover:text-white transition shadow-lg add-to-cart-hover-btn relative group <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                        data-product-id="<?php echo $currentId; ?>"
                                        data-attributes='<?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>'
                                        <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                    <i class="fas fa-shopping-cart"></i>
                                    <span class="product-tooltip"><?php echo $isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-4 card-content">
                            <h3 class="font-semibold text-md mb-2 card-title">
                                <a href="<?php echo $baseUrl; ?>/product?slug=<?php echo urlencode($itemSlug); ?>" class="hover:text-primary transition">
                                    <?php echo htmlspecialchars($itemName); ?>
                                </a>
                            </h3>
                            
                            <!-- Star Rating -->
                            <div class="flex items-center mb-3 card-rating">
                                <?php 
                                $rating = floatval($item['rating'] ?? 0);
                                $fullStars = floor($rating);
                                $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                for ($i = 0; $i < 5; $i++): 
                                ?>
                                <i class="fas fa-star text-sm <?php echo $i < $fullStars ? 'text-yellow-400' : ($i === $fullStars && $hasHalfStar ? 'text-yellow-400' : 'text-gray-300'); ?>"></i>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Description for List View -->
                            <div class="card-description">
                                <?php 
                                $desc = !empty($item['short_description']) ? $item['short_description'] : ($item['description'] ?? '');
                                $desc = strip_tags($desc);
                                echo htmlspecialchars($desc); 
                                ?>
                            </div>
                            
                            <div class="flex items-center justify-between card-actions">
                                <div>
                                    <?php if ($originalPrice): ?>
                                    <span class="text-gray-400 text-sm line-through mr-2 font-bold"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                    <span class="text-md font-bold <?php echo $originalPrice ? 'text-[#1a3d32]' : 'text-primary'; ?>"><?php echo format_price($price, $item['currency'] ?? 'USD'); ?></span>
                                </div>
                                 <?php $isOutOfStock = ($item['stock_status'] === 'out_of_stock' || (isset($item['stock_quantity']) && $item['stock_quantity'] <= 0)); ?>
                                 <button onclick='addToCart(<?php echo $currentId; ?>, 1, this, <?php echo htmlspecialchars($attributesJson, ENT_QUOTES, 'UTF-8'); ?>)' 
                                        class="bg-primary text-white px-4 py-2 rounded hover:bg-primary-light hover:text-white transition text-sm <?php echo $isOutOfStock ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                        <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                    <i class="fas fa-shopping-cart mr-1"></i> <?php echo $isOutOfStock ? get_stock_status_text($item['stock_status'], $item['stock_quantity']) : 'Add to Cart'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <!-- Pagination Container -->
                <div id="pagination-container">
                    <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center items-center space-x-2 mt-8">
                        <?php if ($page > 1): ?>
                        <button onclick="applyFilters(<?php echo $page - 1; ?>)" 
                           class="px-4 py-2 border rounded hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= min($totalPages, 5); $i++): ?>
                        <button onclick="applyFilters(<?php echo $i; ?>)" 
                           class="px-4 py-2 border rounded <?php echo $page === $i ? 'bg-primary text-white' : 'hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </button>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <button onclick="applyFilters(<?php echo $page + 1; ?>)" 
                           class="px-4 py-2 border rounded hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function toggleFilter(filterName) {
    const filter = document.getElementById(filterName + '-filter');
    const arrow = document.getElementById(filterName + '-arrow');
    if (filter && arrow) {
        if (filter.classList.contains('hidden')) {
            filter.classList.remove('hidden');
            // Check if it's a drawer filter (chevron-right) or sidebar filter (chevron-up)
            if (arrow.classList.contains('fa-chevron-right')) {
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-down');
            } else {
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            }
        } else {
            filter.classList.add('hidden');
            // Check if it's a drawer filter (should go to chevron-right) or sidebar filter (should go to chevron-up)
            if (filterName.includes('drawer')) {
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-right');
            } else {
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
            }
        }
    }
}


// Add event listeners for stock checkboxes to implement radio-like behavior
document.addEventListener('DOMContentLoaded', function() {
    const stockCheckboxes = document.querySelectorAll('input[name="stock"]');
    stockCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                // If checking this one, uncheck others
                stockCheckboxes.forEach(other => {
                    if (other !== this) {
                        other.checked = false;
                    }
                });
            }
            // Trigger filters automatically
            applyFilters();
        });
    });
});

// function to handle category clicks without reload and update banner
function selectCategory(slug) {
    const params = new URLSearchParams(window.location.search);
    if (slug) {
        params.set('category', slug);
    } else {
        params.delete('category');
    }
    params.delete('page'); // Reset page
    
    const newUrl = '<?php echo $baseUrl; ?>/shop?' + params.toString();
    history.pushState({}, '', newUrl);
    
    // Update active class on links
    document.querySelectorAll('#category-filter a').forEach(a => {
        a.classList.remove('font-semibold', 'text-primary');
        if ((slug === '' && a.href.endsWith('/shop')) || a.href.includes('category=' + slug)) {
             a.classList.add('font-semibold', 'text-primary');
        }
    });

    applyFilters(1);
}

function applyFilters(page = 1) {
    const params = new URLSearchParams(window.location.search);
    
     // ... (Params logic is handled by selectCategory URL update or inputs)
     // BUT wait, applyFilters reads from INPUTS. The URL is the source of truth for category now.
     
    // Re-read inputs to ensure everything is synced
    const stockCheckboxes = document.querySelectorAll('input[name="stock"]:checked');
    if (stockCheckboxes.length > 0) params.set('stock', stockCheckboxes[0].value);
    
    const minPrice = document.getElementById('minPrice').value;
    if (minPrice) params.set('min_price', minPrice);
    
    const maxPrice = document.getElementById('maxPrice').value;
    if (maxPrice) params.set('max_price', maxPrice);
    
    const sort = document.getElementById('sortSelect').value;
    params.set('sort', sort);

    if (page > 1) {
        params.set('page', page);
    } else {
        params.delete('page');
    }
    
    const queryString = params.toString();
    const newUrl = '<?php echo $baseUrl; ?>/shop?' + queryString;
    history.pushState({}, '', newUrl); // Update URL again just in case
    
    // Perform AJAX request
    fetch('<?php echo $baseUrl; ?>/shop?ajax=1&' + queryString)
        .then(response => response.text())
        .then(html => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // 1. Update Products Grid
            const grid = document.getElementById('productsGrid');
            const newGrid = tempDiv.querySelector('.grid'); // It returns full grid HTML from ajax
            // wait, our AJAX response is CURRENTLY just returning product cards + hidden pagination div.
            // It is NOT returning the full page structure.
            // We need the AJAX response to include the HERO section too if we want to replace it.
            
            // To fix "Banner not updating": We need to reload page OR change AJAX behavior.
            // User requested AJAX. So we must request the full page or a specific partial that includes header.
            // But currently `shop.php` checks `if (isset($_GET['ajax']))` and ONLY echoes products.
            
            // FIX: We will do a full page fetch (without `ajax=1` special minimal mode) 
            // OR modify the php to return JSON with {hero: ..., grid: ...}
            // EASIEST ROBUST WAY: Fetch full page HTML via AJAX, parse it, replace Hero + Grid.
        
            return fetch(newUrl); // Fetch FULL PAGE HTML
        })
        .then(response => response.text())
        .then(fullHtml => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(fullHtml, 'text/html');
            
            // Update Hero Section
            const currentHero = document.querySelector('section.relative');
            const newHero = doc.querySelector('section.relative');
            if(currentHero && newHero) {
                currentHero.replaceWith(newHero);
            }
            
            // Update Grid
            const currentGrid = document.getElementById('productsGrid');
            const newGrid = doc.getElementById('productsGrid');
            if(currentGrid && newGrid) {
                currentGrid.innerHTML = newGrid.innerHTML;
            }
            
            // Update Pagination
            const currentPag = document.getElementById('pagination-container');
            const newPag = doc.getElementById('pagination-container');
            if(currentPag && newPag) {
                currentPag.innerHTML = newPag.innerHTML;
            }
            
            // Update Result Count text
             const currentCount = document.querySelector('.text-gray-600.text-sm.mb-0');
             const newCount = doc.querySelector('.text-gray-600.text-sm.mb-0');
             if(currentCount && newCount) {
                 currentCount.innerText = newCount.innerText;
             }
        })
        .catch(error => console.error('Error fetching products:', error));
}

function setView(view) {
    const grid = document.getElementById('productsGrid');
    const gridBtn = document.getElementById('gridView');
    const listBtn = document.getElementById('listView');
    
    if (view === 'grid') {
        grid.classList.remove('grid-cols-1', 'list-view');
        grid.classList.add('grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3');
        gridBtn.classList.add('active', 'bg-primary', 'text-white');
        listBtn.classList.remove('active', 'bg-primary', 'text-white');
    } else {
        grid.classList.remove('sm:grid-cols-2', 'lg:grid-cols-3');
        grid.classList.add('grid-cols-1', 'list-view');
        listBtn.classList.add('active', 'bg-primary', 'text-white');
        gridBtn.classList.remove('active', 'bg-primary', 'text-white');
    }
}


// Filter Drawer Functions
function openFilterDrawer() {
    const drawer = document.getElementById('filterDrawer');
    const overlay = document.getElementById('filterOverlay');
    drawer.classList.remove('hidden');
    drawer.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeFilterDrawer() {
    const drawer = document.getElementById('filterDrawer');
    const overlay = document.getElementById('filterOverlay');
    drawer.classList.add('-translate-x-full');
    // Add hidden class after transition completes
    setTimeout(() => {
        drawer.classList.add('hidden');
    }, 300);
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

// Close drawer when clicking overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('filterOverlay');
    if (overlay) {
        overlay.addEventListener('click', closeFilterDrawer);
    }
    
    // Close drawer on Escape key
    document.addEventListener('keydown', function(e) {
        const drawer = document.getElementById('filterDrawer');
        if (e.key === 'Escape' && drawer && !drawer.classList.contains('-translate-x-full')) {
            closeFilterDrawer();
        }
    });
});

function applyFiltersFromDrawer() {
    const params = new URLSearchParams(window.location.search);
    
    // Stock status
    const stockCheckboxes = document.querySelectorAll('input[name="stock-drawer"]:checked');
    if (stockCheckboxes.length > 0) {
        params.set('stock', stockCheckboxes[0].value);
    } else {
        params.delete('stock');
    }
    
    // Price
    const minPrice = document.getElementById('minPriceDrawer')?.value;
    const maxPrice = document.getElementById('maxPriceDrawer')?.value;
    if (minPrice) {
        params.set('min_price', minPrice);
    } else {
        params.delete('min_price');
    }
    if (maxPrice) {
        params.set('max_price', maxPrice);
    } else {
        params.delete('max_price');
    }
    
    // Sort (keep existing)
    const sort = document.getElementById('sortSelect')?.value;
    if (sort) {
        params.set('sort', sort);
    }
    
    // Reset to page 1
    params.delete('page');
    
    const queryString = params.toString();
    const newUrl = '<?php echo $baseUrl; ?>/shop?' + queryString;
    
    // Update browser URL
    history.pushState({}, '', newUrl);
    
    // Perform AJAX request (reusing logic but for drawer trigger)
    fetch('<?php echo $baseUrl; ?>/shop?ajax=1&' + queryString)
        .then(response => response.text())
        .then(html => {
             // Create temp div
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Update Pagination
            const paginationContent = tempDiv.querySelector('#ajax-pagination');
            const paginationContainer = document.getElementById('pagination-container');
            if (paginationContent) {
                 if(paginationContainer) paginationContainer.innerHTML = paginationContent.innerHTML;
                 paginationContent.remove();
            } else {
                 if(paginationContainer) paginationContainer.innerHTML = '';
            }
            
            // Update Grid
            const grid = document.getElementById('productsGrid');
            if(grid) {
                grid.innerHTML = tempDiv.innerHTML;
            }
            // Close drawer after apply
            closeFilterDrawer();
        })
        .catch(error => console.error('Error fetching products:', error));
}
</script>

<!-- Mobile Filter Drawer -->
<div id="filterOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>
<div id="filterDrawer" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto lg:hidden">
    <div class="p-6">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b">
            <h2 class="text-xl font-bold">Filters</h2>
            <button onclick="closeFilterDrawer()" class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Products Category -->
        <div class="mb-6">
            <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('category-drawer')">
                Products Category
                <i class="fas fa-chevron-down text-sm" id="category-drawer-arrow"></i>
            </h3>
            <div id="category-drawer-filter" class="space-y-2">
                <a href="<?php echo $baseUrl; ?>/shop" class="block text-gray-600 hover:text-primary transition <?php echo !$categorySlug ? 'font-semibold text-primary' : ''; ?>" onclick="closeFilterDrawer()">
                    All Categories
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?php echo $baseUrl; ?>/shop?category=<?php echo urlencode($cat['slug'] ?? ''); ?>" 
                   class="block text-gray-600 hover:text-primary transition <?php echo $categorySlug === ($cat['slug'] ?? '') ? 'font-semibold text-primary' : ''; ?>"
                   onclick="closeFilterDrawer()">
                    <?php echo htmlspecialchars($cat['name'] ?? ''); ?> (<?php echo $cat['product_count'] ?? 0; ?>)
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Availability -->
        <div class="mb-6 border-t pt-6">
            <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('availability-drawer')">
                Availability
                <i class="fas fa-chevron-down text-sm" id="availability-drawer-arrow"></i>
            </h3>
            <div id="availability-drawer-filter" class="space-y-2">
                <label class="flex items-center text-md">
                    <input type="checkbox" name="stock-drawer" value="in_stock" 
                           <?php echo $stockStatus === 'in_stock' ? 'checked' : ''; ?>
                           onchange="applyFiltersFromDrawer()" class="mr-2">
                    <span>In stock (<?php echo $inStockCount; ?>)</span>
                </label>
                <label class="flex items-center text-md">
                    <input type="checkbox" name="stock-drawer" value="out_of_stock" 
                           <?php echo $stockStatus === 'out_of_stock' ? 'checked' : ''; ?>
                           onchange="applyFiltersFromDrawer()" class="mr-2">
                    <span>Out of stock (<?php echo $outOfStockCount; ?>)</span>
                </label>
            </div>
        </div>
        
        <!-- Price Range -->
        <div class="mb-6 border-t pt-6">
            <h3 class="font-bold text-lg mb-4 flex items-center justify-between cursor-pointer" onclick="toggleFilter('price-drawer')">
                Price
                <i class="fas fa-chevron-down text-sm" id="price-drawer-arrow"></i>
            </h3>
            <div id="price-drawer-filter" class="space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="number" id="minPriceDrawer" placeholder="Min" 
                           value="<?php echo htmlspecialchars($minPrice); ?>"
                           min="0" step="0.01" class="w-full border rounded px-3 py-2">
                    <span>-</span>
                    <input type="number" id="maxPriceDrawer" placeholder="Max" 
                           value="<?php echo htmlspecialchars($maxPrice); ?>"
                           min="0" step="0.01" class="w-full border rounded px-3 py-2">
                </div>
                <button onclick="applyFiltersFromDrawer()" class="w-full bg-primary text-white py-2 rounded hover:bg-primary-light hover:text-white transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

