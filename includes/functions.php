<?php
/**
 * Helper Functions
 */

// Load constants if not already loaded
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
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
    
    // Remove .php extension if present - DISABLED: Server requires .php
    // $path = preg_replace('/\.php$/', '', $path);
    
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
        return 'https://via.placeholder.com/300';
    }
    
    // If already a full URL, return as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    
    // If it's a base64 data URI, return as is
    if (strpos($path, 'data:image') === 0 || strpos($path, 'data:') === 0) {
        return $path;
    }
    
    // If starts with /, it's already a relative path from root
    if (strpos($path, '/') === 0) {
        return $path;
    }
    
    // Otherwise, prepend base path
    $baseUrl = getBaseUrl();
    return $baseUrl . '/assets/images/uploads/' . $path;
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
function format_currency($amount, $decimals = 2) {
    // Explicitly use Rupee symbol to override potential bad configuration on server
    $symbol = '₹'; 
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
                <div class="inline-block bg-white border border-gray-200 rounded-full px-4 py-1 text-sm font-semibold text-gray-900 group-hover/card:bg-red-50 group-hover/card:text-red-700 transition shadow-sm">
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
                 <div class="mega-menu-dropdown absolute top-full left-0 pt-3 hidden group-hover:block z-50 transition-all duration-200 w-max max-w-screen-xl" onmouseenter="adjustMegaMenuPosition(this)">
                     <div class="bg-white rounded-lg shadow-xl border border-gray-100 p-6">
                         <div class="flex gap-8">
                             <?php foreach ($children as $colItem): 
                                 $colHasChildren = !empty($colItem['children']);
                                 $colHasImage = !empty($colItem['image_path']);
                                 $colLabel = htmlspecialchars($colItem['label']);
                                 $colUrl = url($colItem['url']);
                                 $colImage = $colHasImage ? getImageUrl($colItem['image_path']) : '';
                                 $colBadge = $colItem['badge_text'] ?? '';
                             ?>
                                 <!-- Mega Menu Column -->
                                 <div class="flex flex-col space-y-3 min-w-[200px]">
                                     
                                     <?php if ($colHasImage && !$colHasChildren): ?>
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
                                         <!-- Text List Column (with or without small icon) -->
                                         <a href="<?php echo $colUrl; ?>" class="font-bold text-gray-900 border-b border-transparent hover:text-red-700 transition inline-block mb-2 text-md font-sans">
                                             <?php if($colHasImage): ?>
                                                 <img src="<?php echo $colImage; ?>" alt="" class="inline-block w-5 h-5 mr-2 object-contain">
                                             <?php endif; ?>
                                             <?php echo $colLabel; ?>
                                             <?php if($colBadge): ?> <span class="text-[9px] bg-blue-500 text-white px-1 rounded ml-1 align-top"><?php echo $colBadge; ?></span> <?php endif; ?>
                                         </a>
                                         
                                         <?php if($colHasChildren): ?>
                                             <ul class="space-y-2">
                                                 <?php foreach($colItem['children'] as $subItem): 
                                                      $subBadge = $subItem['badge_text'] ?? '';
                                                 ?>
                                                     <li>
                                                         <a href="<?php echo url($subItem['url']); ?>" class="text-[15px] leading-loose text-gray-500 hover:text-red-700 transition flex items-center group/link font-sans">
                                                             <span class="inline-block group-hover/link:translate-x-2 transition-transform duration-300"><?php echo htmlspecialchars($subItem['label']); ?></span>
                                                             <?php if($subBadge): ?> <span class="ml-2 bg-red-500 text-white text-[9px] font-bold px-1.5 rounded-sm"><?php echo $subBadge; ?></span> <?php endif; ?>
                                                         </a>
                                                     </li>
                                                 <?php endforeach; ?>
                                             </ul>
                                         <?php endif; ?>
                                     <?php endif; ?>
                                     
                                 </div>
                             <?php endforeach; ?>
                         </div>
                     </div>
                 </div>
                 
                 <?php else: ?>
                 <!-- Standard Single Dropdown (Level 0 -> Level 1) -->
                 <div class="absolute top-full left-0 pt-3 hidden group-hover:block z-50 min-w-[200px]">
                     <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-2 flex flex-col">
                        <?php foreach ($children as $child): 
                             renderFrontendMenuItem($child, [], $level + 1, false);
                        endforeach; ?>
                     </div>
                 </div>
                 <?php endif; ?>
            </div>
            <?php
        } 
        else {
            // Deep Level Flyout (Level > 0)
             ?>
             <div class="<?php echo $parentClass; ?> w-full">
                 <a href="<?php echo $itemUrl; ?>" class="px-4 py-2 text-gray-700 hover:text-red-700 hover:bg-gray-50 transition relative flex items-center justify-between w-full">
                    <span><?php echo $displayLabel; ?></span>
                    <i class="fas fa-chevron-right text-xs"></i>
                 </a>
                 <!-- Flyout -->
                 <div class="absolute top-0 left-full pl-1 hidden group-hover/sub:block z-50 min-w-[180px]">
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
             <a href="<?php echo $itemUrl; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:text-red-700 transition hover:bg-gray-50 flex items-center group/link font-sans">
                <span class="inline-block group-hover/link:translate-x-2 transition-transform duration-300"><?php echo $displayLabel; ?></span>
            </a>
            <?php
        }
    }
}
