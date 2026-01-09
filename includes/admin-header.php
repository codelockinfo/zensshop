<?php
// Load functions if not already loaded
if (!function_exists('getBaseUrl')) {
    require_once __DIR__ . '/functions.php';
}

require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();

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
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin Dashboard - Milano</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/admin.css">
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
            <button class="text-gray-600 hover:text-gray-800" id="sidebarToggle">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="flex items-center space-x-2 text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white font-bold">R</div>
                <span class="text-xl font-bold text-gray-800">Remos</span>
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
            <button class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-globe text-xl"></i>
            </button>
            <button class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-moon text-xl"></i>
            </button>
            <button class="text-gray-600 hover:text-gray-800 relative">
                <i class="fas fa-bell text-xl"></i>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">1</span>
            </button>
            <button class="text-gray-600 hover:text-gray-800 relative">
                <i class="fas fa-comment text-xl"></i>
                <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">1</span>
            </button>
            
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
                            <a href="<?php echo $baseUrl; ?>/admin/account.php" class="user-dropdown-item">
                                <i class="fas fa-user w-5"></i>
                                <span>Account</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/inbox.php" class="user-dropdown-item">
                                <i class="fas fa-envelope w-5"></i>
                                <span>Inbox</span>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">27</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/taskboard.php" class="user-dropdown-item">
                                <i class="fas fa-clipboard-list w-5"></i>
                                <span>Taskboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/settings.php" class="user-dropdown-item">
                                <i class="fas fa-cog w-5"></i>
                                <span>Setting</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/support.php" class="user-dropdown-item">
                                <i class="fas fa-headset w-5"></i>
                                <span>Support</span>
                            </a>
                        </li>
                        <li class="border-t border-gray-200 mt-1 pt-1">
                            <a href="<?php echo url('admin/api/auth.php?action=logout'); ?>" class="user-dropdown-item text-red-600 hover:text-red-700">
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
                <a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-700' : ''; ?>" title="Dashboard">
                    <i class="fas fa-th-large text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Dashboard</span>
                </a>
            </div>
            
            <div class="mb-8 sidebar-section">
                <h3 class="sidebar-section-title mb-4">ALL PAGE</h3>
                <a href="<?php echo $baseUrl; ?>/admin/products/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'bg-gray-700' : ''; ?>" title="Ecommerce">
                    <i class="fas fa-shopping-cart text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Ecommerce</span>
                    <i class="fas fa-chevron-down text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="<?php echo $baseUrl; ?>/admin/products/add.php" class=" <?php echo ($module === 'products' && in_array($action, ['add', 'add.php'])) ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Add Product">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="<?php echo $baseUrl; ?>/admin/products/list.php" class=" <?php echo ($module === 'products' && $action === 'list') ? 'bg-gray-700' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Product List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Product List</span>
                    </a>
                </div>
                <!-- Category with Submenu -->
                <div class="category-menu-parent mt-2">
                    <a href="<?php echo $baseUrl; ?>/admin/categories/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category">
                        <i class="fas fa-layer-group text-md md:text-lg"></i>
                        <span class="sidebar-menu-text">Category</span>
                        <i class="fas fa-chevron-up text-xs ml-auto category-arrow"></i>
                    </a>
                    <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? '' : 'hidden'; ?>">
                        <a href="<?php echo $baseUrl; ?>/admin/categories/list.php" class=" flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'list.php' && strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category List">
                            <i class="fas fa-gem text-xs"></i>
                            <span>Category List</span>
                        </a>
                        <a href="<?php echo $baseUrl; ?>/admin/categories/manage.php" class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'manage.php' ? 'bg-gray-700' : ''; ?>" title="New Category">
                            <i class="fas fa-gem text-xs"></i>
                            <span>New Category</span>
                        </a>
                    </div>
                </div>
                <a href="<?php echo $baseUrl; ?>/admin/orders/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'bg-gray-700' : ''; ?>" title="Order">
                    <i class="fas fa-file-alt text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Order</span>
                    <i class="fas fa-chevron-down text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="<?php echo $baseUrl; ?>/admin/orders/list.php" class="<?php echo ($module === 'orders' && in_array($action, ['list'])) ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Order List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Order List</span>
                    </a>
                </div>
                <a href="<?php echo $baseUrl; ?>/admin/discounts/manage.php" class=" <?php echo strpos($_SERVER['PHP_SELF'], 'discounts') !== false ? 'bg-gray-700' : ''; ?> sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2" title="Discounts">
                    <i class="fas fa-tag text-lg"></i>
                    <span class="sidebar-menu-text">Discounts</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/admin/customers/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'bg-gray-700' : ''; ?>" title="Customers">
                    <i class="fas fa-users text-lg"></i>
                    <span class="sidebar-menu-text">Customers</span>
                    <i class="fas fa-chevron-down text-xs ml-auto"></i>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="<?php echo $baseUrl; ?>/admin/customers/list.php" class="<?php echo ($module === 'customers' && in_array($action, ['list'])) ? 'bg-gray-700 text-white' : ''; ?> flex items-center space-x-2 py-1 px-4 text-sm" title="Customer List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Customer List</span>
                    </a>
                </div>
                <a href="<?php echo $baseUrl; ?>/admin/report.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'report.php' ? 'bg-gray-700' : ''; ?>" title="Report">
                    <i class="fas fa-chart-bar text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Report</span>
                </a>

                <a href="<?php echo $baseUrl; ?>/admin/settings.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-gray-700' : ''; ?>" title="Settings">
                    <i class="fas fa-cog text-md md:text-lg"></i>
                    <span class="sidebar-menu-text">Settings</span>
                </a>

                <a href="<?php echo $baseUrl; ?>/admin/logout.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'bg-gray-700' : ''; ?>" title="Logout">
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

