<?php
// Load functions if not already loaded
if (!function_exists('getBaseUrl')) {
    require_once __DIR__ . '/functions.php';
}

require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();

// Get unread message count for header
// Get unread message count for header
$h_storeId = $_SESSION['store_id'] ?? null;
require_once __DIR__ . '/../classes/Database.php';
$h_db = Database::getInstance();

if (empty($h_storeId) && !empty($currentUser) && isset($currentUser['email'])) {
    try {
        $h_storeUser = $h_db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$currentUser['email']]);
        $h_storeId = $h_storeUser['store_id'] ?? null;
    } catch (Exception $e) {
        // Ignore error if users table issue
    }
}

$h_unreadCount = 0;
if (!empty($h_storeId)) {
    try {
        $h_result = $h_db->fetchOne("SELECT COUNT(*) as count FROM support_messages WHERE store_id = ? AND status = 'open'", [$h_storeId]);
        if ($h_result && isset($h_result['count'])) {
            $h_unreadCount = $h_result['count'];
        }
    } catch (Exception $e) {
        $h_unreadCount = 0;
    }
}

// Get base URL using the centralized function
$baseUrl = getBaseUrl();

// Ensure url() function is available
if (!function_exists('url')) {
    function url($path = '') {
        $baseUrl = getBaseUrl();
        $path = ltrim($path, '/');
        $queryString = '';
        if (strpos($path, '?') !== false) {
            $parts = explode('?', $path, 2);
            $path = $parts[0];
            $queryString = '?' . $parts[1];
        }
        $path = preg_replace('/\.php$/', '', $path);
        if (empty($path)) {
            return $baseUrl . '/' . $queryString;
        }
        return $baseUrl . '/' . $path . $queryString;
    }
}

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $path);
$module = $segments[count($segments) - 2] ?? '';  // products, orders
$action = $segments[count($segments) - 1] ?? '';  // add, list

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin Dashboard - CookPro</title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/admin/Images/admin-logo.png">
    
    <!-- Tailwind CSS with Typography Plugin -->
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/admin1.css">
    <script>
    // Make BASE_URL available globally for all admin pages
    const BASE_URL = '<?php echo $baseUrl; ?>';
    </script>
</head>
<body class="bg-gray-100">
    <?php if ($currentUser): ?>
    <!-- Admin Header -->
    <header class="admin-header flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <button class="text-gray-600 hover:text-gray-800" id="sidebarToggle" style="display: none !important;">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <a href="javascript:history.back()" class="flex items-center space-x-2 text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div class="flex items-center space-x-2">
                <?php
                // Fetch site logo settings for admin header
                $ah_logoType = $h_db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_type' AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [$h_storeId])['setting_value'] ?? 'text';
                $ah_logoText = $h_db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_text' AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [$h_storeId])['setting_value'] ?? 'ZENSSHOP';
                $ah_logoImage = $h_db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo' AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [$h_storeId])['setting_value'] ?? '';

                if ($ah_logoType === 'image' && !empty($ah_logoImage)): ?>
                    <img src="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($ah_logoImage); ?>" alt="Logo" class="h-8 object-contain">
                <?php else: ?>
                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white font-bold"><?php echo substr($ah_logoText, 0, 1); ?></div>
                    <span class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($ah_logoText); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Search Container -->
            <div class="relative admin-search-container">
                <button class="text-gray-600 hover:text-gray-800" id="adminSearchToggle">
                    <i class="fas fa-search text-xl"></i>
                </button>
                <div id="adminSearchDropdown" class="admin-search-dropdown hidden">
                    <input type="text" 
                           id="adminSearchInput"
                           placeholder="Search here..." 
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-64"
                           autocomplete="off">
                    <div id="adminSearchResults" class="admin-search-results hidden">
                        <div class="admin-search-loading hidden">
                            <div class="flex items-center justify-center p-4">
                                <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                <span class="ml-2 text-sm text-gray-500">Searching...</span>
                            </div>
                        </div>
                        <div id="adminSearchResultsContent" class="admin-search-results-content">
                            <!-- Results will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
            <button class="text-gray-600 hover:text-gray-800 hidden">
                <i class="fas fa-globe text-xl"></i>
            </button>
            <button class="text-gray-600 hover:text-gray-800 hidden">
                <i class="fas fa-moon text-xl"></i>
            </button>
            <!-- Notifications Dropdown -->
            <div class="relative notification-dropdown">
                <button class="text-gray-600 hover:text-gray-800 relative notification-trigger" id="notificationBell">
                    <i class="fas fa-bell text-xl"></i>
                    <span id="notificationCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
                </button>
                
                <!-- Notification Dropdown Menu -->
                <div class="notification-dropdown-menu hidden" id="notificationDropdown">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="font-semibold text-gray-800">Notifications</h3>
                        <button id="markAllRead" class="text-xs text-blue-600 hover:text-blue-800">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="max-h-96 overflow-y-auto">
                        <!-- Notifications will be loaded here -->
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                    <div class="p-2 border-t text-center">
                        <a href="<?php echo url('admin/notifications.php'); ?>" class="text-sm text-blue-600 hover:text-blue-800">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- Support Messages Dropdown -->
            <div class="relative support-dropdown">
                <button class="text-gray-600 hover:text-gray-800 relative support-trigger" id="supportBell">
                    <i class="fas fa-comment text-xl"></i>
                    <span id="supportCount" class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
                </button>
                
                <!-- Support Dropdown Menu -->
                <div class="notification-dropdown-menu hidden" id="supportDropdown">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="font-semibold text-gray-800">Customer Messages</h3>
                        <a href="<?php echo url('admin/support.php'); ?>" class="text-xs text-blue-600 hover:text-blue-800">View all</a>
                    </div>
                    <div id="supportList" class="max-h-96 overflow-y-auto">
                        <!-- Messages will be loaded here -->
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Profile Dropdown -->
            <div class="relative user-profile-dropdown">
                <div class="flex items-center space-x-2 cursor-pointer user-profile-trigger">
                    <?php 
                    $profileImage = $currentUser['profile_image'] ?? null;
                    $imageUrl = $baseUrl . '/assets/images/default-avatar.svg';
                    
                    if ($profileImage) {
                        // Normalize the URL (fix old /oecom/ paths)
                        $profileImage = normalizeImageUrl($profileImage);
                        
                        // Check if it's already a full URL
                        if (strpos($profileImage, 'http://') === 0 || strpos($profileImage, 'https://') === 0) {
                            $imageUrl = $profileImage;
                        } elseif (strpos($profileImage, 'data:image') === 0) {
                            // It's a base64 data URI, use it directly
                            $imageUrl = $profileImage;
                        } elseif (strpos($profileImage, '/') === 0) {
                            // It's already a path from root
                            $imageUrl = $profileImage;
                        } else {
                            // Remove any base URL prefix and convert to file path
                            $imagePath = str_replace($baseUrl . '/', '', $profileImage);
                            $imagePath = preg_replace('#^[^/]+/#', '', $imagePath); // Remove any leading directory
                            $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
                            
                            if (file_exists($fullPath)) {
                                $imageUrl = $baseUrl . '/' . ltrim($imagePath, '/');
                            }
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="User" 
                         class="w-10 h-10 rounded-full object-cover border-2 border-gray-300"
                         onerror="this.src='<?php echo $baseUrl; ?>/assets/images/default-avatar.svg'">
                    <div>
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-xs text-gray-500">Admin</p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                </div>
                
                <!-- Dropdown Menu -->
                <div class="user-dropdown-menu">
                    <ul class="space-y-1">
                        <li>
                            <a href="<?php echo url('admin/account'); ?>" class="user-dropdown-item">
                                <i class="fas fa-user w-5"></i>
                                <span>Account</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('admin/inbox'); ?>" class="user-dropdown-item">
                                <i class="fas fa-envelope w-5"></i>
                                <span>Inbox</span>
                                <?php if (isset($h_unreadCount) && $h_unreadCount > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $h_unreadCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- <li>
                            <a href="<?php echo url('admin/taskboard'); ?>" class="user-dropdown-item">
                                <i class="fas fa-clipboard-list w-5"></i>
                                <span>Taskboard</span>
                            </a>
                        </li> -->
                        <li>
                            <a href="<?php echo url('admin/settings'); ?>" class="user-dropdown-item">
                                <i class="fas fa-cog w-5"></i>
                                <span>Setting</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('admin/support'); ?>" class="user-dropdown-item">
                                <i class="fas fa-headset w-5"></i>
                                <span>Support</span>
                            </a>
                        </li>
                        <li class="border-t border-gray-200 mt-1 pt-1">
                            <a href="<?php echo url('admin/api/auth?action=logout'); ?>" class="user-dropdown-item text-red-600 hover:text-red-700">
                                <i class="fas fa-sign-out-alt w-5" style="color: #e24c4c;"></i>
                                <span>Log out</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="p-4">
            <div class="mb-8 sidebar-section">
                <h3 class="sidebar-section-title mb-4">MAIN HOME</h3>
                <a href="<?php echo url('admin/dashboard'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-700' : ''; ?>" title="Dashboard">
                    <i class="fas fa-th-large text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Dashboard</span>
                </a>
            </div>
            
            <div class="mb-8 sidebar-section">
                <h3 class="sidebar-section-title mb-4">ALL PAGE</h3>
                <a href="<?php echo url('admin/products/list'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'bg-gray-700' : ''; ?>" title="Ecommerce">
                    <i class="fas fa-shopping-cart text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Ecommerce</span>
                    <i class="fas <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'fa-chevron-up' : 'fa-chevron-down'; ?> text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? '' : 'hidden'; ?>">
                    <a href="<?php echo url('admin/products/add'); ?>" class=" <?php echo ($module === 'products' && in_array($action, ['add', 'add.php'])) ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Add Product">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="<?php echo url('admin/products/list'); ?>" class=" <?php echo ($module === 'products' && $action === 'list') ? 'bg-gray-700' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Product List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Product List</span>
                    </a>
                </div>
                <!-- Category with Submenu -->
                <div class="category-menu-parent mt-2">
                    <a href="<?php echo url('admin/categories/list'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category">
                        <i class="fas fa-layer-group text-md md:text-lg"></i>
                        <span class="sidebar-menu-text">Category</span>
                        <i class="fas <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'fa-chevron-up' : 'fa-chevron-down'; ?> text-xs ml-auto category-arrow"></i>
                    </a>
                    <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? '' : 'hidden'; ?>">
                        <a href="<?php echo url('admin/categories/list'); ?>" class=" flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'list.php' && strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category List">
                            <i class="fas fa-gem text-xs"></i>
                            <span>Category List</span>
                        </a>
                        <a href="<?php echo url('admin/categories/manage'); ?>" class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'manage.php' ? 'bg-gray-700' : ''; ?>" title="New Category">
                            <i class="fas fa-gem text-xs"></i>
                            <span>New Category</span>
                        </a>
                    </div>
                </div>
                <a href="<?php echo url('admin/orders/list'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'bg-gray-700' : ''; ?>" title="Order">
                    <i class="fas fa-file-alt text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Order</span>
                    <i class="fas <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'fa-chevron-up' : 'fa-chevron-down'; ?> text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? '' : 'hidden'; ?>">
                    <a href="<?php echo url('admin/orders/list'); ?>" class="<?php echo ($module === 'orders' && in_array($action, ['list'])) ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Order List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Order List</span>
                    </a>
                </div>
                <a href="<?php echo url('admin/discounts/manage'); ?>" class=" <?php echo strpos($_SERVER['PHP_SELF'], 'discounts') !== false ? 'bg-gray-700' : ''; ?> sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2" title="Discounts">
                    <i class="fas fa-tag text-lg"></i>
                    <span class="sidebar-menu-text">Discounts</span>
                </a>
                <a href="<?php echo url('admin/customers/list'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'bg-gray-700' : ''; ?>" title="Customers">
                    <i class="fas fa-users text-lg"></i>
                    <span class="sidebar-menu-text">Customers</span>
                    <i class="fas <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'fa-chevron-up' : 'fa-chevron-down'; ?> text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? '' : 'hidden'; ?>">
                    <a href="<?php echo url('admin/customers/list'); ?>" class="<?php echo basename($_SERVER['PHP_SELF'], '.php') === 'list' ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Customer List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Customer List</span>
                    </a>
                    <a href="<?php echo url('admin/customers/subscribers'); ?>" class="<?php echo basename($_SERVER['PHP_SELF'], '.php') === 'subscribers' ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Subscriber List">
                        <i class="fas fa-envelope text-xs"></i>
                        <span>Subscriber List</span>
                    </a>
                </div>
                <a href="<?php echo url('admin/report'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF'], '.php') === 'report' ? 'bg-gray-700' : ''; ?>" title="Report">
                    <i class="fas fa-chart-bar text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Report</span>
                </a>


                <!-- Settings Menu Parent -->
<?php
$isSettingsPage = strpos($_SERVER['PHP_SELF'], 'settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'header_info') !== false
               || strpos($_SERVER['PHP_SELF'], 'footer_info') !== false
               || strpos($_SERVER['PHP_SELF'], 'menu_settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'banner_settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'homepage_categories_settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'homepage_products_settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'homepage_videos_settings') !== false
               || strpos($_SERVER['PHP_SELF'], 'pages') !== false
               || strpos($_SERVER['PHP_SELF'], 'page-edit') !== false
               || strpos($_SERVER['REQUEST_URI'], 'admin/blogs') !== false;

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="category-menu-parent mt-2">
    <!-- Parent Menu -->
    <a href="<?php echo url('admin/special-page'); ?>"
       class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo $isSettingsPage ? 'bg-gray-700' : ''; ?>"
       title="Settings">

        <i class="fas fa-layer-group text-md md:text-lg"></i>
        <span class="sidebar-menu-text">Settings</span>

        <i class="fas <?php echo $isSettingsPage ? 'fa-chevron-up' : 'fa-chevron-down'; ?> text-xs ml-auto category-arrow"></i>
    </a>

    <!-- Sub Menu -->
    <div class="sidebar-submenu mt-2 space-y-1 <?php echo $isSettingsPage ? '' : 'hidden'; ?>">

        <a href="<?php echo url('admin/special-page'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'settings' || strpos($_SERVER['REQUEST_URI'], 'special-page') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Product Page">
            <i class="fas fa-gem text-xs"></i>
            <span>Product Page</span>
        </a>

        <a href="<?php echo url('admin/banner'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'banner_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/banner') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Banner Settings">
            <i class="fas fa-images text-xs"></i>
            <span>Banner</span>
        </a>

        <a href="<?php echo url('admin/category'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'homepage_categories_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/category') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Homepage categories">
            <i class="fas fa-th-large text-xs"></i>
            <span>Categories</span>
        </a>

        <a href="<?php echo url('admin/products'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'homepage_products_settings' || preg_match('#/admin/products/?($|\?)#', $_SERVER['REQUEST_URI'])) ? 'bg-gray-700' : ''; ?>"
           title="Homepage Products">
            <i class="fas fa-box-open text-xs"></i>
            <span>Products</span>
        </a>

        <a href="<?php echo url('admin/offers'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'special_offers_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/offers') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Special Offers">
            <i class="fas fa-tags text-xs"></i>
            <span>Special Offers</span>
        </a>

        <a href="<?php echo url('admin/shorts'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'homepage_videos_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/shorts') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Homepage Videos">
            <i class="fas fa-video text-xs"></i>
            <span>Video Reels</span>
        </a>

        <a href="<?php echo url('admin/philosophy'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'philosophy_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/philosophy') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Philosophy Section">
            <i class="fas fa-quote-left text-xs"></i>
            <span>Philosophy</span>
        </a>

        <a href="<?php echo url('admin/features'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'features_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/features') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Features Section">
            <i class="fas fa-star text-xs"></i>
            <span>Features</span>
        </a>

        <a href="<?php echo url('admin/newsletter'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'newsletter_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/newsletter') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Newsletter Section">
            <i class="fas fa-envelope text-xs"></i>
            <span>Newsletter</span>
        </a>

        <a href="<?php echo url('admin/header'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'header_info' || strpos($_SERVER['REQUEST_URI'], 'admin/header') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Header Info">
            <i class="fas fa-gem text-xs"></i>
            <span>Header Info</span>
        </a>

        <a href="<?php echo url('admin/footer'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'footer_info' || strpos($_SERVER['REQUEST_URI'], 'admin/footer') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Footer Info">
            <i class="fas fa-gem text-xs"></i>
            <span>Footer Info</span>
        </a>

        <a href="<?php echo url('admin/menu'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'menu_settings' || strpos($_SERVER['REQUEST_URI'], 'admin/menu') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Menu Settings">
            <i class="fas fa-gem text-xs"></i>
            <span>Menu Settings</span>
        </a>

        <a href="<?php echo url('admin/settings'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo ($currentPage === 'system-settings' || strpos($_SERVER['REQUEST_URI'], 'admin/settings') !== false) ? 'bg-gray-700' : ''; ?>"
           title="System Settings">
            <i class="fas fa-cog text-xs"></i>
            <span>System Settings</span>
        </a>

        <a href="<?php echo url('admin/pages.php'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/pages') !== false || strpos($_SERVER['REQUEST_URI'], 'page-edit') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Custom Pages">
            <i class="fas fa-file-alt text-xs"></i>
            <span>Custom Pages</span>
        </a>

        <?php 
        require_once __DIR__ . '/../classes/Settings.php';
        $settingsObjHeader = new Settings();
        if ($settingsObjHeader->get('enable_blog', '1') == '1'): 
        ?>
        <a href="<?php echo url('admin/blogs/manage'); ?>"
           class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/blogs') !== false) ? 'bg-gray-700' : ''; ?>"
           title="Manage Blogs">
            <i class="fas fa-blog text-xs"></i>
            <span>Blogs</span>
        </a>
        <?php endif; ?>

    </div>
</div>

                

                <a href="<?php echo url('admin/logout.php'); ?>" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF'], '.php') === 'logout' ? 'bg-gray-700' : ''; ?>" title="Logout">
                    <i class="fas fa-sign-out-alt text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Logout</span>
                </a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content Wrapper -->
    <div class="admin-content-wrapper">
        <!-- Main Content -->
        <main class="admin-content">
    <?php endif; ?>

