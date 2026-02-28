<?php
/**
 * Helper Functions
 */

// Load constants if not already loaded
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';

/**
 * Get the current Store ID based on domain or session
 */
function getCurrentStoreId() {
    if (defined('CURRENT_STORE_ID')) {
        return CURRENT_STORE_ID;
    }
    
    // Fallback if constant not defined
    return $_SESSION['store_id'] ?? 'DEFAULT';
}

/**
 * Retrieve the store name from the settings table.
 */
function getStoreName($storeId = null): string {
    $name = getSetting('site_name', '', $storeId);
    if (empty($name)) {
        $name = getSetting('store_name', 'My Store', $storeId);
    }
    return $name;
}

/**
 * Get base URL for the site
 * Uses SITE_URL constant which automatically detects environment (local vs production)
 */
function getBaseUrl() {
    if (defined('SITE_URL')) {
        // Use the configured SITE_URL (automatically handles local vs production)
        // Remove trailing slash if present to avoid double slashes
        return rtrim(SITE_URL, '/');
    }
    
    // Fallback detection (should not be reached if config is loaded)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    return rtrim($protocol . $host . $path, '/');
}

/**
 * Generate clean URL without .php extension
 */
function url($path = '') {
    $baseUrl = getBaseUrl();
    
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    // Handle query strings
    $queryString = '';
    if (strpos($path, '?') !== false) {
        $parts = explode('?', $path, 2);
        $path = $parts[0];
        $queryString = '?' . $parts[1];
    }
    
    // Remove extension if present (e.g. .php, .html)
    // This regex is already aggressive enough to strip .php, .html, .htm
    $path = preg_replace('/\.(php|html|htm)$/i', '', $path);
    
    // If path is empty, return base URL
    if (empty($path)) {
        return $baseUrl . '/' . $queryString;
    }
    
    return $baseUrl . '/' . $path . $queryString;
}

/**
 * Get full image URL from relative path
 */
function getImageUrl($path) {
    if (empty($path)) {
        return 'https://placehold.co/300x300?text=No+Image';
    }
    
    // If it's a full URL or data URI, return as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, 'data:') === 0) {
        return $path;
    }
    
    $baseUrl = getBaseUrl();
    $parsedBase = parse_url($baseUrl, PHP_URL_PATH); // e.g., /zensshop
    
    // Remove base path if it exists at the start of $path (de-duplicate)
    $cleanPath = $path;
    if ($parsedBase && $parsedBase !== '/' && strpos($cleanPath, $parsedBase) === 0) {
        $cleanPath = substr($cleanPath, strlen($parsedBase));
    }
    $cleanPath = ltrim($cleanPath, '/');
    
    // If path doesn't contain 'assets/' or 'uploads/', it might be a root file or belongs in uploads
    if (strpos($cleanPath, 'assets/') === false && strpos($cleanPath, 'uploads/') === false) {
        // Check if file exists in root (for favicons)
        if (!file_exists(__DIR__ . '/../' . $cleanPath)) {
            // Check if it's a known subfolder in assets/images (like special_offers)
            $firstSegment = explode('/', $cleanPath)[0];
            if (is_dir(__DIR__ . '/../assets/images/' . $firstSegment)) {
                $cleanPath = 'assets/images/' . $cleanPath;
            } else {
                $cleanPath = 'assets/images/uploads/' . $cleanPath;
            }
        }
    } elseif (strpos($cleanPath, 'uploads/') === 0) {
        // If it starts with uploads/, it likely needs assets/images/ prepended
        $cleanPath = 'assets/images/' . $cleanPath;
    }
    
    // Ensure no double slashes during concatenation
    return rtrim($baseUrl, '/') . '/' . ltrim($cleanPath, '/');
}

/**
 * Get product image with fallback
 */
function getProductImage($product, $index = 0) {
    if (empty($product) || !is_array($product)) {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="300" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#F3F4F6"/><circle cx="150" cy="150" r="40" fill="#9B7A8A"/><path d="M80 250C80 220 110 190 150 190C190 190 220 220 220 250" fill="#9B7A8A"/></svg>');
    }
    
    $images = json_decode($product['images'] ?? '[]', true);
    
    // Try featured image first
    if (!empty($product['featured_image'])) {
        return getImageUrl($product['featured_image']);
    }
    
    // Try images array
    if (!empty($images[$index])) {
        return getImageUrl($images[$index]);
    }
    
    // Fallback to inline SVG placeholder
    return 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="300" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#F3F4F6"/><circle cx="150" cy="150" r="40" fill="#9B7A8A"/><path d="M80 250C80 220 110 190 150 190C190 190 220 220 220 250" fill="#9B7A8A"/></svg>');
}

/**
 * Normalize image URL - fixes old /oecom/ paths
 */
function normalizeImageUrl($url) {
    if (empty($url)) {
        return $url;
    }
    
    // Replace old /oecom/ paths with current base URL
    if (strpos($url, '/oecom/') !== false) {
        $baseUrl = getBaseUrl();
        $url = str_replace('/oecom/', $baseUrl . '/', $url);
    }
    
    return $url;
}

/**
 * Sanitize input string
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get display label for stock status
 */
function get_stock_status_text($status, $quantity, $totalSold = 0) {
    if ($status === 'out_of_stock') {
        return 'Out of Stock';
    }
    if ($quantity <= 0) {
        return ($totalSold > 0) ? 'Sold Out' : 'Out of Stock';
    }
    if ($status === 'on_backorder') {
        return 'On Backorder';
    }
    return 'In Stock';
}

/**
 * Format price with currency symbol
 */
function format_price($amount, $currency = 'INR') {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'JPY' => '¥',
        'KRW' => '₩',
        'CNY' => '¥',
        'RUB' => '₽'
    ];
    
    $code = strtoupper($currency);
    $symbol = $symbols[$code] ?? '₹';
    
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Format currency amount with symbol (Backward compatibility)
 */
function format_currency($amount, $decimals = 2, $currencyCode = 'INR') {
    $symbols = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];
    $symbol = $symbols[strtoupper($currencyCode)] ?? '₹';
    return $symbol . number_format((float)$amount, $decimals);
}

/**
 * Build a tree from flat menu items
 */
function buildMenuTree(array $elements, $parentId = null) {
    $branch = array();
    foreach ($elements as $element) {
        // Check compatibility for root items (handle NULL and 0 as equivalent for root)
        $isRootMatch = ($parentId === null && $element['parent_id'] == 0);
        
        if ($element['parent_id'] == $parentId || $isRootMatch) {
            $children = buildMenuTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

/**
 * Get Supported Currencies
 */
function getCurrencies() {
    // Check if Database class is available
    if (class_exists('Database')) {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'supported_currencies'");
        if ($row && !empty($row['setting_value'])) {
             return json_decode($row['setting_value'], true);
        }
    }
    
    // Fallback defaults if DB fails or empty
    return [
        [
            'code' => 'in',
            'name' => 'India',
            'currency_name' => 'INR',
            'symbol' => '₹',
            'flag' => 'https://cdn.shopify.com/static/images/flags/in.svg'
        ],
        [
            'code' => 'us',
            'name' => 'United States',
            'currency_name' => 'USD',
            'symbol' => '$',
            'flag' => 'https://cdn.shopify.com/static/images/flags/us.svg'
        ]
    ];
}

/**
 * Format timestamp to relative time string (e.g. 2 hours ago)
 */
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return 'N/A';
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
    } catch (Exception $e) {
        return 'Invalid date';
    }
    $diff = $now->diff($ago);

    $values = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => (int)floor($diff->d / 7),
        'd' => $diff->d % 7,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    foreach ($string as $k => &$v) {
        if ($values[$k]) {
            $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Recursive Function to Render Frontend Menu
 * Moved from header.php to avoid scope/duplication issues.
 */
/**
 * Recursive Function to Render Frontend Menu
 * Now supports images and multi-column dropdowns.
 */
function renderFrontendMenuItem($item, $landingPagesList = [], $level = 0, $showImages = true) {
    // Only fetch children if they exist in the item structure
    $children = $item['children'] ?? [];
    $hasChildren = !empty($children);
    $label = htmlspecialchars($item['label']);
    $itemUrl = url($item['url']);
    $hasImage = $showImages && !empty($item['image_path']);
    $imageUrl = $hasImage ? getImageUrl($item['image_path']) : '';
    $badgeText = $item['badge_text'] ?? '';

    // Badge HTML
    $badgeHtml = '';
    if (!empty($badgeText)) {
         $bClass = (strtolower($badgeText) === 'hot') ? 'bg-red-500' : 'bg-blue-500'; // Simple color logic
         // Position badge slightly up/right
         $badgeHtml = '<span class="ml-2 '.$bClass.' text-white text-[9px] font-bold px-1.5 py-0.5 rounded uppercase relative -top-0.5">'.htmlspecialchars($badgeText).'</span>';
    }

    // Default Display Label (Text + Badge)
    $displayLabel = $label . $badgeHtml;

    // Image Handling
    if ($hasImage) {
        // Check if this is a leaf node (no children) in a submenu
        $isLeafNode = empty($item['children']);
        
        if ($level > 0 && $isLeafNode) {
            // SUBMENU LEAF ITEM WITH IMAGE -> CARD STYLE
            // Only show as card if it's a final item with no children
            ?>
            <a href="<?php echo $itemUrl; ?>" class="block group/card text-center">
                <div class="relative overflow-hidden rounded-lg mb-2">
                    <img src="<?php echo $imageUrl; ?>" alt="<?php echo $label; ?>" class="w-full h-auto object-cover transform group-hover/card:scale-105 transition-transform duration-500">
                    <!-- Optional Overlay -->
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/card:bg-opacity-10 transition-all"></div>
                </div>
                <div class="inline-block bg-white border border-gray-200 rounded-full px-4 py-1 text-sm font-semibold text-gray-900 group-hover/card:text-red-700 transition shadow-sm">
                    <?php echo $label; ?> <?php echo $badgeHtml; ?>
                </div>
            </a>
            <?php
            return; // EXIT FUNCTION for card items
        } else {
             // Item has children OR is top level -> Show as small icon
             $displayLabel = '<img src="'.$imageUrl.'" alt="" class="inline-block w-5 h-5 mr-2 object-contain">' . $displayLabel;
        }
    }
    
    // Special Case: Products (Mega Menu) - Preserve Hardcoded Layout for specific ID/Label if desired
    // But allowing dynamic overriding if user changes structure. 
    // We keep the hardcoded check for backward compatibility but dynamic items take precedence if structure changes.
    if ($item['label'] === 'Products' && !$hasChildren) { 
        // Only trigger hardcoded mega menu if NO children are defined in DB. 
        // If user added children in DB, we use the dynamic renderer below.
         ?>
        <div class="relative mega-menu-parent">
            <a href="<?php echo $itemUrl; ?>" class="text-black hover:text-red-700 transition flex items-center font-sans text-md nav-link">
                <?php echo $displayLabel; ?>
                <i class="fas fa-chevron-down text-xs ml-1"></i>
            </a>
            <!-- Mega Menu Dropdown -->
            <div class="mega-menu mega-menu-products">
                 <!-- Hardcoded Content Preserved -->
                 <div class="grid grid-cols-3 gap-8">
                    <div>
                        <h3 class="font-bold text-gray-900 hover:text-red-700 transition mb-4 text-lg">Shop Layouts</h3>
                        <ul class="space-y-3">
                            <li><a href="<?php echo url('shop.php?layout=filter-left'); ?>" class="text-gray-600 hover:text-red-700 transition">Filter left sidebar</a></li>
                            <li><a href="<?php echo url('shop.php?layout=filter-right'); ?>" class="text-gray-600 hover:text-red-700 transition">Filter right sidebar</a></li>
                            <li>
                                <a href="<?php echo url('shop.php?layout=horizontal'); ?>" class="text-gray-600 hover:text-red-700 transition flex items-center">
                                    Horizontal filter
                                    <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">HOT</span>
                                </a>
                            </li>
                            <li><a href="<?php echo url('shop.php?layout=drawer'); ?>" class="text-gray-600 hover:text-red-700 transition">Filter drawer</a></li>
                            <li><a href="<?php echo url('shop.php?layout=grid-3'); ?>" class="text-gray-600 hover:text-red-700 transition">Grid 3 columns</a></li>
                            <li><a href="<?php echo url('shop.php?layout=grid-4'); ?>" class="text-gray-600 hover:text-red-700 transition">Grid 4 columns</a></li>
                            <li><a href="<?php echo url('shop.php'); ?>" class="text-gray-600 hover:text-red-700 transition">All collections</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 hover:text-red-700 transition mb-4 text-lg">Shop Pages</h3>
                        <ul class="space-y-3">
                            <li><a href="<?php echo url('collection-v1.php'); ?>" class="text-gray-600 hover:text-red-700 transition">Collection list v1</a></li>
                            <li>
                                <a href="<?php echo url('collection-v2.php'); ?>" class="text-gray-600 hover:text-red-700 transition flex items-center">
                                    Collection list v2
                                    <span class="ml-2 bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full">NEW</span>
                                </a>
                            </li>
                            <li><a href="<?php echo url('shop.php?scroll=infinity'); ?>" class="text-gray-600 hover:text-red-700 transition">Infinity scroll</a></li>
                            <li><a href="<?php echo url('shop.php?load=more'); ?>" class="text-gray-600 hover:text-red-700 transition">Load more button</a></li>
                            <li><a href="<?php echo url('shop.php?pagination=1'); ?>" class="text-gray-600 hover:text-red-700 transition">Pagination page</a></li>
                            <li><a href="<?php echo url('banner-collection.php'); ?>" class="text-gray-600 hover:text-red-700 transition">Banner collection</a></li>
                        </ul>
                    </div>
                    <div class="space-y-4">
                        <a href="<?php echo url('category.php?slug=bracelets'); ?>" class="block category-card group">
                            <div class="relative overflow-hidden rounded-lg">
                                <img src="https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=300&h=400&fit=crop" alt="Bracelets" class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-4 flex items-center justify-center">
                                    <div class="bg-white px-6 py-3 w-full max-w-[85%]" style="border-radius: 50px;">
                                        <h3 class="text-center text-md font-semibold text-gray-900">Bracelets</h3>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <a href="<?php echo url('category.php?slug=rings'); ?>" class="block category-card group">
                            <div class="relative overflow-hidden rounded-lg">
                                <img src="https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=300&h=400&fit=crop" alt="Rings" class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-4 flex items-center justify-center">
                                    <div class="bg-white px-6 py-3 w-full max-w-[85%]" style="border-radius: 50px;">
                                        <h3 class="text-center text-md font-semibold text-gray-900">Rings</h3>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } 
    // Recursive Dropdown Logic
    elseif ($hasChildren) {
        $childCount = count($children);

        // Determine if we should use Mega Menu Layout
        // We use Mega Menu if:
        // 1. Level is 0 (Top Bar) AND
        // 2. (Any child has an IMAGE OR Explicit setting)
        // Note: We removed check for "has children" so simple text lists stay as standard dropdowns
        $isMega = false;
        $isMega = !empty($item['is_mega_menu']) && $item['is_mega_menu'] == 1;

        // Layout Render
        $parentClass = "relative group";
        
        // For Mega Menus, make parent static so dropdown positions relative to main nav container (centered)
        if ($isMega && $level === 0) {
            $parentClass = "group static";
        }
        
        if($level > 0) $parentClass .= "/sub"; 

        if ($level === 0) {
            // Level 0 Parent Link
            // Use custom classes if provided, otherwise use defaults
            $linkClasses = !empty($item['custom_classes']) 
                ? $item['custom_classes'] 
                : 'text-black hover:text-red-700 transition relative flex items-center font-sans text-md nav-link px-1 h-full';
            ?>
            <div class="<?php echo $parentClass; ?> h-full flex items-center">
                 <a href="<?php echo $itemUrl; ?>" class="<?php echo $linkClasses; ?>">
                    <span><?php echo $displayLabel; ?></span>
                    <i class="fas fa-chevron-down text-xs ml-1"></i>
                 </a>
                 
                 <?php if ($isMega): ?>
                 <!-- Mega Menu Container -->
                 <div class="mega-menu-dropdown absolute top-full left-1/2 transform -translate-x-1/2 pt-3 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 ease-out z-50 w-max max-w-screen-xl">
                     <div class="bg-white rounded-lg shadow-xl border border-gray-100 p-6">
                         <?php 
                         // Determine column count class dynamically
                         $childCount = count($children);
                         $gridCols = ($childCount >= 6) ? 6 : max(1, $childCount);
                         ?>
                         <div class="grid grid-cols-<?php echo $gridCols; ?> gap-8 text-left">
                             <?php foreach ($children as $colItem): 
                                 $colHasChildren = !empty($colItem['children']);
                                 $colHasImage = !empty($colItem['image_path']);
                                 $colLabel = htmlspecialchars($colItem['label']);
                                 $colUrl = url($colItem['url']);
                                 $colImage = $colHasImage ? getImageUrl($colItem['image_path']) : '';
                                 $colBadge = $colItem['badge_text'] ?? '';
                                 
                                 // SPLIT LOGIC: If this column has > 10 children, we need to split it across multiple grid columns
                                 $maxSubItems = 10;
                                 $subItemChunks = [$colItem['children'] ?? []]; // Default: single chunk (all children)
                                 
                                 if ($colHasChildren && count($colItem['children']) > $maxSubItems) {
                                     $subItemChunks = array_chunk($colItem['children'], $maxSubItems);
                                 }
                                 
                                 // Iterate through chunks. The first chunk gets the main Header/Image. 
                                 // Subsequent chunks are just "continuation" columns.
                                 foreach ($subItemChunks as $chunkIndex => $chunk):
                                     $isFirstChunk = ($chunkIndex === 0);
                             ?>
                                 <!-- Mega Menu Column -->
                                 <div class="flex flex-col space-y-3 min-w-[160px]">
                                     
                                     <?php if ($isFirstChunk && $colHasImage && !$colHasChildren): ?>
                                         <!-- Image Card Column (only for leaf nodes with images) -->
                                          <a href="<?php echo $colUrl; ?>" class="group/card block text-center">
                                            <div class="relative overflow-hidden rounded-lg mb-3 shadow-sm border border-gray-100">
                                                <img src="<?php echo $colImage; ?>" alt="<?php echo $colLabel; ?>" class="w-full h-48 object-cover transform group-hover/card:scale-110 transition-transform duration-700">
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/card:bg-opacity-10 transition-all"></div>
                                                
                                                <!-- Floating Button Look -->
                                                <div class="absolute bottom-4 left-0 right-0 flex justify-center">
                                                    <div class="bg-white px-5 py-2 rounded-full shadow-md text-sm font-bold text-gray-900 group-hover/card:text-red-700 transition">
                                                        <?php echo $colLabel; ?>
                                                        <?php if($colBadge): ?> <span class="text-[9px] bg-red-500 text-white px-1 rounded ml-1"><?php echo $colBadge; ?></span> <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                          </a>
                                     <?php else: ?>
                                         <!-- Text Header (Only for first chunk) -->
                                         <?php if ($isFirstChunk): ?>
                                             <a href="<?php echo $colUrl; ?>" class="font-bold text-gray-900 border-b border-transparent hover:text-red-700 transition inline-block mb-2 text-md font-sans">
                                                 <?php if($colHasImage): ?>
                                                     <img src="<?php echo $colImage; ?>" alt="" class="inline-block w-5 h-5 mr-2 object-contain">
                                                 <?php endif; ?>
                                                 <?php echo $colLabel; ?>
                                                 <?php if($colBadge): ?> <span class="text-[9px] bg-blue-500 text-white px-1 rounded ml-1 align-top"><?php echo $colBadge; ?></span> <?php endif; ?>
                                             </a>
                                         <?php else: ?>
                                             <!-- Spacer for continuation columns to align with items -->
                                             <div class="h-[2rem]"></div> 
                                         <?php endif; ?>
                                         
                                         <!-- Sub Items List -->
                                         <?php if(!empty($chunk)): ?>
                                             <ul class="space-y-2">
                                                 <?php foreach($chunk as $subItem): 
                                                      $subBadge = $subItem['badge_text'] ?? '';
                                                 ?>
                                                     <li>
                                                         <a href="<?php echo url($subItem['url']); ?>" class="text-[15px] leading-loose text-gray-500 hover:text-red-700 transition flex items-center group/link font-sans whitespace-nowrap">
                                                             <span class="inline-block group-hover/link:translate-x-2 transition-transform duration-300"><?php echo htmlspecialchars($subItem['label']); ?></span>
                                                             <?php if($subBadge): ?> <span class="ml-2 bg-red-500 text-white text-[9px] font-bold px-1.5 rounded-sm"><?php echo $subBadge; ?></span> <?php endif; ?>
                                                         </a>
                                                     </li>
                                                 <?php endforeach; ?>
                                             </ul>
                                         <?php endif; ?>
                                     <?php endif; ?>
                                     
                                 </div>
                             <?php endforeach; // End chunks loop ?>
                             <?php endforeach; // End children loop ?>
                         </div>
                     </div>
                 </div>
                 
                 <?php else: ?>
                 <!-- Standard Single Dropdown (Level 0 -> Level 1) -->
                 <?php 
                 $limit = 10;
                 if (count($children) > $limit): 
                     $chunks = array_chunk($children, $limit);
                 ?>
                 <div class="absolute top-full left-0 pt-3 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 ease-out z-50 w-max max-w-screen-xl">
                     <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-2 flex flex-row">
                         <?php foreach ($chunks as $index => $chunk): ?>
                             <div class="flex flex-col w-64 <?php echo $index > 0 ? 'border-gray-100' : ''; ?>">
                                 <?php foreach ($chunk as $child): 
                                      renderFrontendMenuItem($child, [], $level + 1, false);
                                 endforeach; ?>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 </div>
                 <?php else: ?>
                 <div class="absolute top-full left-0 pt-3 invisible opacity-0 translate-y-2 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 ease-out z-50 w-64">
                     <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-2 flex flex-col">
                        <?php foreach ($children as $child): 
                             renderFrontendMenuItem($child, [], $level + 1, false);
                        endforeach; ?>
                     </div>
                 </div>
                 <?php endif; ?>
                 <?php endif; ?>
            </div>
            <?php
        } 
        else {
            // Deep Level Flyout (Level > 0)
             ?>
             <div class="<?php echo $parentClass; ?> w-full">
                 <a href="<?php echo $itemUrl; ?>" class="px-4 py-2 text-gray-700 hover:text-red-700 transition relative flex items-center justify-between w-full">
                    <span><?php echo $displayLabel; ?></span>
                    <i class="fas fa-chevron-right text-xs"></i>
                 </a>
                 <!-- Flyout -->
                 <div class="absolute top-0 left-full pl-1 invisible opacity-0 -translate-x-2 group-hover/sub:visible group-hover/sub:opacity-100 group-hover/sub:translate-x-0 transition-all duration-300 ease-out z-50 w-64">
                     <div class="bg-white rounded-lg py-1 shadow-lg border border-gray-100">
                         <?php foreach ($children as $child): 
                              renderFrontendMenuItem($child, [], $level + 1, $showImages);
                         endforeach; ?>
                     </div>
                 </div>
             </div>
             <?php
        }
    } else {
        // Standard Leaf Node
        if ($level === 0) {
            // Use custom classes if provided, otherwise use defaults
            $leafLinkClasses = !empty($item['custom_classes']) 
                ? $item['custom_classes'] 
                : 'text-black hover:text-red-700 transition relative font-sans text-md nav-link px-1';
             ?>
             <div class="h-full flex items-center">
                <a href="<?php echo $itemUrl; ?>" class="<?php echo $leafLinkClasses; ?>">
                    <?php echo $displayLabel; ?>
                </a>
            </div>
            <?php
         } else {
             ?>
             <a href="<?php echo $itemUrl; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:text-red-700 transition flex items-center group/link font-sans whitespace-nowrap">
                <span class="inline-block group-hover/link:translate-x-2 transition-transform duration-300"><?php echo $displayLabel; ?></span>
            </a>
            <?php
        }
    }
}

/**
 * Calculate GST for a line item
 * 
 * @param float $price Unit price
 * @param float $gstPercent GST percentage (e.g. 18.00)
 * @param string $sellerState State of the seller
 * @param string $customerState State of the customer
 * @param int $quantity Quantity
 * @return array Breakup of GST (cgst, sgst, igst, subtotal, total)
 */
function calculateGST($price, $gstPercent, $sellerState, $customerState, $quantity = 1) {
    $lineSubtotal = round($price * $quantity, 2);
    
    // Normalize states (trim and lowercase)
    $sellerStateNormalized = strtolower(trim($sellerState ?? ''));
    $customerStateNormalized = strtolower(trim($customerState ?? ''));
    
    $cgstAmount = 0;
    $sgstAmount = 0;
    $igstAmount = 0;
    
    if ($gstPercent > 0) {
        // GST is calculated on the value of supply
        $totalTaxAmount = round($lineSubtotal * ($gstPercent / 100), 2);
        
        if ($sellerStateNormalized === $customerStateNormalized || empty($sellerStateNormalized) || empty($customerStateNormalized)) {
            // Intrastate: CGST + SGST (Default if states unknown)
            $cgstAmount = round($totalTaxAmount / 2, 2);
            $sgstAmount = round($totalTaxAmount - $cgstAmount, 2); 
        } else {
            // Interstate: IGST
            $igstAmount = $totalTaxAmount;
        }
    }
    
    $lineTotal = round($lineSubtotal + $cgstAmount + $sgstAmount + $igstAmount, 2);
    
    return [
        'subtotal' => $lineSubtotal,
        'cgst' => $cgstAmount,
        'sgst' => $sgstAmount,
        'igst' => $igstAmount,
        'total' => $lineTotal,
        'gst_percent' => $gstPercent
    ];
}

/**
 * Get single setting value from site_settings or settings
 */
function getSetting($key, $default = '', $storeId = null) {
    try {
        $db = Database::getInstance();
        if ($storeId === null) {
            $storeId = getCurrentStoreId();
        }
        
        // Try site_settings first
        $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = ? AND (store_id = ? OR store_id IS NULL)", [$key, $storeId]);
        if ($result) {
            return $result['setting_value'];
        }
        
        // Try settings table
        $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ? AND (store_id = ? OR store_id IS NULL)", [$key, $storeId]);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

