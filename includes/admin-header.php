<?php
require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();
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
    <link rel="stylesheet" href="/oecom/assets/css/admin.css">
</head>
<body class="bg-gray-100">
    <?php if ($currentUser): ?>
    <!-- Admin Header -->
    <header class="admin-header flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <button class="text-gray-600 hover:text-gray-800" id="sidebarToggle">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <a href="/oecom/admin/dashboard.php" class="flex items-center space-x-2 text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white font-bold">R</div>
                <span class="text-xl font-bold text-gray-800">Remos</span>
            </div>
        </div>
        
        <div class="flex items-center space-x-4">
            <input type="text" placeholder="Search here..." class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
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
                    if ($profileImage && file_exists(__DIR__ . '/../' . ltrim($profileImage, '/'))) {
                        $imageUrl = $profileImage;
                    } else {
                        $imageUrl = '/oecom/assets/images/default-avatar.svg';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="User" 
                         class="w-10 h-10 rounded-full object-cover border-2 border-gray-300"
                         onerror="this.src='/oecom/assets/images/default-avatar.svg'">
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
                            <a href="/oecom/admin/account.php" class="user-dropdown-item">
                                <i class="fas fa-user w-5"></i>
                                <span>Account</span>
                            </a>
                        </li>
                        <li>
                            <a href="/oecom/admin/inbox.php" class="user-dropdown-item">
                                <i class="fas fa-envelope w-5"></i>
                                <span>Inbox</span>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">27</span>
                            </a>
                        </li>
                        <li>
                            <a href="/oecom/admin/taskboard.php" class="user-dropdown-item">
                                <i class="fas fa-clipboard-list w-5"></i>
                                <span>Taskboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="/oecom/admin/settings.php" class="user-dropdown-item">
                                <i class="fas fa-cog w-5"></i>
                                <span>Setting</span>
                            </a>
                        </li>
                        <li>
                            <a href="/oecom/admin/support.php" class="user-dropdown-item">
                                <i class="fas fa-headset w-5"></i>
                                <span>Support</span>
                            </a>
                        </li>
                        <li class="border-t border-gray-200 mt-1 pt-1">
                            <a href="/oecom/admin/api/auth.php?action=logout" class="user-dropdown-item text-red-600 hover:text-red-700">
                                <i class="fas fa-sign-out-alt w-5"></i>
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
                <a href="/oecom/admin/dashboard.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-700' : ''; ?>" title="Dashboard">
                    <i class="fas fa-th-large text-lg"></i>
                    <span class="sidebar-menu-text">Dashboard</span>
                </a>
            </div>
            
            <div class="mb-8 sidebar-section">
                <h3 class="sidebar-section-title mb-4">ALL PAGE</h3>
                <a href="/oecom/admin/products/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'bg-gray-700' : ''; ?>" title="Ecommerce">
                    <i class="fas fa-shopping-cart text-lg"></i>
                    <span class="sidebar-menu-text">Ecommerce</span>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="/oecom/admin/products/add.php" class="flex items-center space-x-2 py-1 px-4 text-sm" title="Add Product">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="/oecom/admin/products/list.php" class="flex items-center space-x-2 py-1 px-4 text-sm" title="Product List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Product List</span>
                    </a>
                </div>
                <!-- Category with Submenu -->
                <div class="category-menu-parent mt-2">
                    <a href="/oecom/admin/categories/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category">
                        <i class="fas fa-layer-group text-lg"></i>
                        <span class="sidebar-menu-text">Category</span>
                        <i class="fas fa-chevron-up text-xs ml-auto category-arrow"></i>
                    </a>
                    <div class="sidebar-submenu mt-2 space-y-1 <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? '' : 'hidden'; ?>">
                        <a href="/oecom/admin/categories/list.php" class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'list.php' && strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'bg-gray-700' : ''; ?>" title="Category List">
                            <i class="fas fa-gem text-xs"></i>
                            <span>Category List</span>
                        </a>
                        <a href="/oecom/admin/categories/manage.php" class="flex items-center space-x-2 py-1 px-4 text-sm <?php echo basename($_SERVER['PHP_SELF']) === 'manage.php' ? 'bg-gray-700' : ''; ?>" title="New Category">
                            <i class="fas fa-gem text-xs"></i>
                            <span>New Category</span>
                        </a>
                    </div>
                </div>
                <a href="/oecom/admin/orders/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'bg-gray-700' : ''; ?>" title="Order">
                    <i class="fas fa-file-alt text-lg"></i>
                    <span class="sidebar-menu-text">Order</span>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="/oecom/admin/orders/list.php" class="flex items-center space-x-2 py-1 px-4 text-sm" title="Order List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Order List</span>
                    </a>
                </div>
                <a href="/oecom/admin/discounts/manage.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2" title="Discounts">
                    <i class="fas fa-tag text-lg"></i>
                    <span class="sidebar-menu-text">Discounts</span>
                </a>
                <a href="/oecom/admin/customers/list.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo strpos($_SERVER['PHP_SELF'], 'customers') !== false ? 'bg-primary-light text-primary-dark' : ''; ?>" title="Customers">
                    <i class="fas fa-users text-lg"></i>
                    <span class="sidebar-menu-text">Customers</span>
                </a>
                <div class="sidebar-submenu mt-2 space-y-1">
                    <a href="/oecom/admin/customers/list.php" class="flex items-center space-x-2 py-1 px-4 text-sm" title="Customer List">
                        <i class="fas fa-gem text-xs"></i>
                        <span>Customer List</span>
                    </a>
                </div>
                <a href="/oecom/admin/report.php" class="sidebar-menu-item flex items-center space-x-3 py-2 px-4 mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'report.php' ? 'bg-gray-700' : ''; ?>" title="Report">
                    <i class="fas fa-chart-bar text-lg"></i>
                    <span class="sidebar-menu-text">Report</span>
                </a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content Wrapper -->
    <div class="admin-content-wrapper">
        <!-- Main Content -->
        <main class="admin-content">
    <?php endif; ?>

