<?php
$pageTitle = 'Shop';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Wishlist.php';

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
$sort = $_GET['sort'] ?? 'created_at DESC';
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

// Get all categories for sidebar
$categories = $db->fetchAll("SELECT c.*, 
                               (SELECT COUNT(DISTINCT p.id) 
                                FROM products p 
                                LEFT JOIN product_categories pc ON p.id = pc.product_id
                                WHERE p.status = 'active' AND (p.category_id = c.id OR pc.category_id = c.id)
                               ) as product_count 
                               FROM categories c 
                               WHERE c.status = 'active' 
                               ORDER BY c.sort_order ASC, c.name ASC");

// Get stock counts
$inStockCount = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count 
                                FROM products p 
                                LEFT JOIN product_categories pc ON p.id = pc.product_id
                                WHERE p.status = 'active' AND p.stock_status = 'in_stock'" . 
                                ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ?) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.id AND c2.slug = ?))" : ""),
                                $categorySlug ? [$categorySlug, $categorySlug] : [])['count'] ?? 0;

$outOfStockCount = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count 
                                   FROM products p 
                                   LEFT JOIN product_categories pc ON p.id = pc.product_id
                                   WHERE p.status = 'active' AND p.stock_status = 'out_of_stock'" . 
                                   ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ?) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.id AND c2.slug = ?))" : ""),
                                   $categorySlug ? [$categorySlug, $categorySlug] : [])['count'] ?? 0;

// Get price range
$priceRange = $db->fetchOne("SELECT MIN(COALESCE(p.sale_price, p.price)) as min_price, 
                              MAX(COALESCE(p.sale_price, p.price)) as max_price 
                              FROM products p 
                              LEFT JOIN product_categories pc ON p.id = pc.product_id
                              WHERE p.status = 'active'" . 
                              ($categorySlug ? " AND (p.category_id = (SELECT id FROM categories WHERE slug = ?) OR EXISTS (SELECT 1 FROM product_categories pc2 INNER JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.product_id = p.id AND c2.slug = ?))" : ""),
                              $categorySlug ? [$categorySlug, $categorySlug] : []);
$minPriceRange = $priceRange['min_price'] ?? 0;
$maxPriceRange = $priceRange['max_price'] ?? 1000;
?>

<!-- Hero Section -->
<?php if ($category && !empty($category['banner'])): ?>
    <?php
    $bannerUrl = $baseUrl . '/' . $category['banner'];
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
        <button onclick="openFilterDrawer()" class="lg:hidden mb-4 flex items-center space-x-2 bg-primary text-white px-4 py-2 rounded hover:bg-primary-light hover:text-white transition">
            <i class="fas fa-filter"></i>
            <span>Filters</span>
        </button>
        
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
                            <a href="<?php echo $baseUrl; ?>/shop.php" class="block text-gray-600 hover:text-primary transition <?php echo !$categorySlug ? 'font-semibold text-primary' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $cat): ?>
                            <a href="<?php echo $baseUrl; ?>/shop.php?category=<?php echo urlencode($cat['slug'] ?? ''); ?>" 
                               class="block text-gray-600 hover:text-primary transition <?php echo $categorySlug === ($cat['slug'] ?? '') ? 'font-semibold text-primary' : ''; ?>">
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
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <p class="text-gray-600 text-sm mb-4 md:mb-0">
                        There are <?php echo $totalProducts; ?> results in total
                    </p>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
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
                            <option value="name ASC" <?php echo $sort === 'name ASC' ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="name DESC" <?php echo $sort === 'name DESC' ? 'selected' : ''; ?>>Name: Z to A</option>
                            <option value="rating DESC" <?php echo $sort === 'rating DESC' ? 'selected' : ''; ?>>Rating: High to Low</option>
                        </select>
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
                            <a href="<?php echo url('product.php?slug=' . urlencode($itemSlug)); ?>">
                                <img src="<?php echo htmlspecialchars($mainImage); ?>" 
                                     alt="<?php echo htmlspecialchars($itemName); ?>" 
                                     class="w-full h-64 object-contain  group-hover:scale-110 transition-transform duration-500">
                            </a>
                            
                            <?php if ($discount > 0): ?>
                            <span class="absolute top-3 left-3 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">
                                -<?php echo $discount; ?>%
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            $currentId = !empty($item['product_id']) ? $item['product_id'] : $itemId;
                            $inWishlist = in_array($currentId, $wishlistIds);
                            ?>
                            <button class="wishlist-btn absolute top-3 right-3 rounded-full w-9 h-9 flex items-center justify-center transition <?php echo $inWishlist ? 'bg-black text-white' : 'bg-white text-black'; ?> hover:bg-black hover:text-white"
                                    data-product-id="<?php echo $currentId; ?>"
                                    title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        </div>
                        
                        <div class="p-4 card-content">
                            <h3 class="font-semibold text-md mb-2 card-title">
                                <a href="<?php echo $baseUrl; ?>/product.php?slug=<?php echo urlencode($itemSlug); ?>" class="hover:text-primary transition">
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
                                    <span class="text-gray-400 text-sm line-through mr-2 text-red-500 font-bold"><?php echo format_price($originalPrice, $item['currency'] ?? 'USD'); ?></span>
                                    <?php endif; ?>
                                    <span class="text-md font-bold text-primary"><?php echo format_price($price, $item['currency'] ?? 'USD'); ?></span>
                                </div>
                                 <button onclick="window.addToCart(<?php echo $itemId; ?>, 1, this)" 
                                        class="bg-primary text-white px-4 py-2 rounded hover:bg-primary-light hover:text-white transition text-sm">
                                    <i class="fas fa-shopping-cart mr-1"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-center items-center space-x-2 mt-8">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="px-4 py-2 border rounded hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= min($totalPages, 5); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="px-4 py-2 border rounded <?php echo $page === $i ? 'bg-primary text-white' : 'hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="px-4 py-2 border rounded hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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

function applyFilters() {
    const params = new URLSearchParams(window.location.search);
    
    // Category (keep existing)
    if (params.has('category')) {
        // Keep category
    }
    
    // Stock status
    const stockCheckboxes = document.querySelectorAll('input[name="stock"]:checked');
    if (stockCheckboxes.length === 1) {
        params.set('stock', stockCheckboxes[0].value);
    } else {
        // If 0 or 2 (both) are checked, show all (remove filter)
        params.delete('stock');
    }
    
    // Price
    const minPrice = document.getElementById('minPrice').value;
    const maxPrice = document.getElementById('maxPrice').value;
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
    
    // Sort
    const sort = document.getElementById('sortSelect').value;
    params.set('sort', sort);
    
    // Reset to page 1
    params.delete('page');
    
    window.location.href = '<?php echo $baseUrl; ?>/shop.php?' + params.toString();
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
    
    window.location.href = '<?php echo $baseUrl; ?>/shop.php?' + params.toString();
}
</script>

<!-- Mobile Filter Drawer -->
<div id="filterOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>
<div id="filterDrawer" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto lg:hidden">
    <div class="p-6">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b">
            <h2 class="text-xl font-bold">Filters</h2>
            <button onclick="closeFilterDrawer()" class="text-gray-500 hover:text-gray-800" 
              data-aria-label="Close filter drawer">
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
                <a href="<?php echo $baseUrl; ?>/shop.php" class="block text-gray-600 hover:text-primary transition <?php echo !$categorySlug ? 'font-semibold text-primary' : ''; ?>" onclick="closeFilterDrawer()">
                    All Categories
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?php echo $baseUrl; ?>/shop.php?category=<?php echo urlencode($cat['slug'] ?? ''); ?>" 
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

