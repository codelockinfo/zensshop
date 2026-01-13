<?php
// Load functions if not already loaded
if (!function_exists('getBaseUrl')) {
    require_once __DIR__ . '/functions.php';
}

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';

$auth = new Auth();
$customerAuth = new CustomerAuth();

$currentUser = $auth->getCurrentUser();
$currentCustomer = $customerAuth->getCurrentCustomer();

require_once __DIR__ . '/../classes/Cart.php';
$cart = new Cart();
$cartCount = $cart->getCount();

require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();
$landingPagesList = $db->fetchAll("SELECT name, slug FROM landing_pages ORDER BY name ASC");

// Fetch Header Menu
$headerMenuIdVal = $db->fetchOne("SELECT id FROM menus WHERE location = 'header_main'");
$headerMenuItems = [];
if ($headerMenuIdVal) {
    $allItems = $db->fetchAll("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC", [$headerMenuIdVal['id']]);
    if (function_exists('buildMenuTree')) {
        $headerMenuItems = buildMenuTree($allItems);
    }
}

// Fetch Header Settings
$siteLogoType = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_type'")['setting_value'] ?? 'image';
$siteLogoText = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_text'")['setting_value'] ?? 'milano';
$siteLogo = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo'")['setting_value'] ?? 'logo.png';
$showSearchIcon = ($db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_search'")['setting_value'] ?? '1') == '1';
$showUserIcon = ($db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_user'")['setting_value'] ?? '1') == '1';
$showWishlistIcon = ($db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_wishlist'")['setting_value'] ?? '1') == '1';
$showCartIcon = ($db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_cart'")['setting_value'] ?? '1') == '1';

// Fetch Top Bar Settings
$topbarSlidesRow = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'topbar_slides'");
$topbarSlides = json_decode($topbarSlidesRow['setting_value'] ?? '[]', true) ?: [
    ['text' => '100% secure online payment', 'link' => '', 'link_text' => ''],
    ['text' => 'Free Shipping for all order over $99', 'link' => '', 'link_text' => ''],
    ['text' => 'Sign up for 10% off your first order.', 'link' => 'signup.php', 'link_text' => 'Sign up']
];

$topbarLinksRow = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'topbar_links'");
$topbarLinks = json_decode($topbarLinksRow['setting_value'] ?? '[]', true) ?: [
    ['label' => 'Contact Us', 'url' => 'contact.php'],
    ['label' => 'About Us', 'url' => 'about.php'],
    ['label' => 'Help Center', 'url' => 'help.php'],
    ['label' => 'Our Store', 'url' => 'store.php']
];


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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Milano - Elegant Jewelry Store</title>
    
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>

    <?php if (!empty($customSchema)): ?>
    <script type="application/ld+json">
        <?php echo $customSchema; // Outputting raw JSON as requested ?>
    </script>
    <?php endif; ?>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/main.css">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    // Make BASE_URL available globally for all frontend pages
    const BASE_URL = '<?php echo $baseUrl; ?>';
    // Make currency symbol available globally
    const CURRENCY_SYMBOL = '<?php echo defined("CURRENCY_SYMBOL") ? CURRENCY_SYMBOL : "₹"; ?>';
    
    // Adjust mega menu position to prevent overflow
    function adjustMegaMenuPosition(menuElement) {
        // Use requestAnimationFrame for better timing
        requestAnimationFrame(() => {
            // Reset positioning
            menuElement.style.left = '';
            menuElement.style.right = '';
            menuElement.style.transform = '';
            
            // Get measurements
            const rect = menuElement.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const containerPadding = 20; // Safety padding
            
            // Check if menu overflows on the right
            if (rect.right > (viewportWidth - containerPadding)) {
                const overflow = rect.right - viewportWidth + containerPadding;
                // Shift left using transform for smoother rendering
                menuElement.style.transform = `translateX(-${overflow}px)`;
                
                // Double-check if it now overflows on the left
                const newRect = menuElement.getBoundingClientRect();
                if (newRect.left < containerPadding) {
                    // If too far left, just align to right edge of viewport
                    menuElement.style.transform = '';
                    menuElement.style.left = 'auto';
                    menuElement.style.right = `${containerPadding}px`;
                }
            }
        });
    }
    
    // Auto-adjust all mega menus on window resize
    window.addEventListener('resize', () => {
        document.querySelectorAll('.mega-menu-dropdown').forEach(menu => {
            if (menu.offsetParent !== null) { // Only if visible
                adjustMegaMenuPosition(menu);
            }
        });
    });
    </script>
    <style>
    /* Multi-level Dropdown Support */
    .group:hover .group-hover\:block { display: block; }
    .group\/sub:hover > .group-hover\/sub\:block { display: block; }
    
    /* Mega Menu Viewport Constraint - Always constrain width */
    .mega-menu-dropdown {
        max-width: calc(100vw - 40px) !important;
        /* Use right positioning by default to prevent overflow */
        left: auto !important;
        right: 0 !important;
    }
    
    /* For very wide screens, we can center or left-align */
    @media (min-width: 1920px) {
        .mega-menu-dropdown {
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
        }
    }
    </style>
</head>
<body class="font-body">
    <!-- Top Bar -->
    <div class="hidden xl:block bg-black text-white text-sm py-2" style="padding: 12px 0;">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="relative flex-1 overflow-hidden flex items-center" style="min-height: 20px; gap: 20px;">
                <div class="arrow">
                <!-- Left Arrow -->
                <button class="top-bar-arrow top-bar-arrow-left flex-shrink-0 mr-2 text-gray-500 hover:text-white transition" id="topBarPrev" aria-label="Previous">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>

                <!-- Right Arrow -->
                <button class="top-bar-arrow top-bar-arrow-right flex-shrink-0 ml-2 text-gray-500 hover:text-white transition" id="topBarNext" aria-label="Next">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
                </div>
                
                <!-- Slider Container -->
                <div class="relative flex-1 overflow-hidden">
                    <div class="top-bar-slider flex transition-transform duration-500 ease-in-out" id="topBarSlider">
                        <?php foreach ($topbarSlides as $slide): ?>
                        <div class="top-bar-slide flex-shrink-0 w-full flex items-center">
                            <span>
                                <?php echo htmlspecialchars($slide['text']); ?>
                                <?php if (!empty($slide['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="hover:text-gray-300 transition ml-1 underline underline-offset-4">
                                        <?php 
                                            if (!empty($slide['link_text'])) {
                                                echo htmlspecialchars($slide['link_text']);
                                            } else {
                                                // Automatic fallback
                                                if (stripos($slide['text'], 'Sign up') !== false) echo 'Sign up';
                                                elseif (stripos($slide['text'], 'Shop Now') !== false) echo 'Shop Now';
                                                else echo 'Learn More';
                                            }
                                        ?>
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>  
            </div>
            <div class="flex items-center space-x-4">
                <?php foreach ($topbarLinks as $link): ?>
                <a href="<?php echo url($link['url']); ?>" class="hover:text-gray-300 transition"><?php echo htmlspecialchars($link['label']); ?></a>
                <?php endforeach; ?>
                <!-- Currency/Region Selector -->
                <div class="relative ml-4 pl-4 border-l border-gray-700">
                    <?php
                    $currencies = getCurrencies();
                    $selectedCurrency = $currencies[0] ?? ['code'=>'in','name'=>'India','currency_name'=>'INR','symbol'=>'₹','flag'=>'https://cdn.shopify.com/static/images/flags/in.svg'];
                    ?>
                    <button class="flex items-center gap-2 hover:text-gray-300 transition cursor-pointer focus:outline-none whitespace-nowrap" id="currencySelector">
                        <span class="flex items-center gap-2">
                            <span class="rounded-full border border-gray-300 overflow-hidden" style="width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;">
                                <img src="<?php echo $selectedCurrency['flag']; ?>" alt="<?php echo $selectedCurrency['name']; ?>" id="selectedFlagImg" class="w-full h-full object-cover" style="width: 20px; height: 20px;">
                            </span>
                            <span class="text-sm">
                                <span class="text-gray-400" id="countryCode"></span>
                                <span id="selectedCurrency" class="text-white"><?php echo $selectedCurrency['name'] . ' (' . $selectedCurrency['currency_name'] . ' ' . $selectedCurrency['symbol'] . ')'; ?></span>
                            </span>
                        </span>
                        <svg class="icon-down flex-shrink-0" width="10" height="6" style="margin-left: 4px;">
                            <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <!-- Currency Dropdown -->
                    <div class="absolute right-0 top-full mt-2 bg-white text-black shadow-lg rounded-lg py-1 min-w-[240px] hidden z-50 border border-gray-200" id="currencyDropdown">
                        <?php foreach ($currencies as $curr): ?>
                        <a href="#" class="block px-4 py-2.5 hover:bg-gray-50 transition currency-option" data-flag="<?php echo $curr['flag']; ?>" data-code="<?php echo $curr['code']; ?>" data-currency="<?php echo $curr['name'] . ' (' . $curr['currency_name'] . ' ' . $curr['symbol'] . ')'; ?>">
                            <span class="flex items-center gap-2">
                                <span class="rounded-full border border-gray-300 overflow-hidden" style="width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;">
                                    <img src="<?php echo $curr['flag']; ?>" alt="<?php echo $curr['name']; ?>" class="w-full h-full object-cover" style="width: 20px; height: 20px;">
                                </span>
                                <span class="text-sm"><?php echo $curr['name'] . ' (' . $curr['currency_name'] . ' ' . $curr['symbol'] . ')'; ?></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="bg-white sticky top-0 z-50 header-shadow">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-20">
                <!-- Hamburger Menu  -->
                <button class="xl:hidden text-black hover:text-gray-600 transition focus:outline-none" id="mobileMenuBtn">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Logo (Left on desktop, Centered on mobile/tablet) -->
                <div class="flex-shrink-0 xl:flex-shrink-0 absolute xl:relative left-1/2 xl:left-auto transform xl:transform-none -translate-x-1/2 xl:translate-x-0">
                    <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                        <?php if ($siteLogoType === 'text'): ?>
                            <span class="text-3xl font-heading font-bold text-black xl:lowercase lowercase"><?php echo htmlspecialchars($siteLogoText); ?></span>
                        <?php else: ?>
                            <img src="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($siteLogo); ?>" 
                                 alt="Site Logo" 
                                 class="h-8 object-contain"
                                 onerror="this.parentElement.innerHTML='<span class=\'text-3xl font-heading font-bold text-black xl:lowercase lowercase\'>milano</span>'">
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Desktop Navigation - Centered (Hidden on mobile/tablet, visible on xl+) -->
                <div class="hidden xl:flex items-center space-x-8 absolute left-1/2 transform -translate-x-1/2 z-10">
<?php
if (!empty($headerMenuItems)) {
    foreach ($headerMenuItems as $item) {
        renderFrontendMenuItem($item, $landingPagesList ?? []);
    }
}
?>
                </div>
                
                <!-- Right Icons -->
                <div class="flex items-center space-x-5">
                    <?php if ($showSearchIcon): ?>
                    <!-- Search -->
                    <button class="text-black hover:text-gray-600 transition focus:outline-none header-icon" id="searchBtn">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($showUserIcon): ?>
                    <!-- User Account - Only visible on xl screens -->
                    <a href="<?php echo url('account.php'); ?>" class="hidden xl:block text-black hover:text-gray-600 transition header-icon">
                        <i class="fas fa-user text-lg"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($showWishlistIcon): ?>
                    <!-- Wishlist - Only visible on xl screens -->
                    <a href="<?php echo url('wishlist.php'); ?>" class="hidden xl:block text-gray-800 hover:text-primary transition relative">
                        <i class="fas fa-heart text-xl"></i>
                        <span class="wishlist-count absolute -top-1 -right-1.5 font-medium bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                            <?php 
                            if ($currentCustomer) {
                                require_once __DIR__ . '/../classes/Wishlist.php';
                                $wishlist = new Wishlist();
                                echo count($wishlist->getItems($currentCustomer['id']));
                            } else {
                                echo '0';
                            }
                            ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($showCartIcon): ?>
                    <!-- Cart -->
                    <?php
                    // Check if we're on checkout or cart page
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                    $isCheckoutPage = ($currentPage === 'checkout.php' || strpos($requestUri, '/checkout') !== false);
                    $isCartPage = ($currentPage === 'cart.php' || strpos($requestUri, '/cart') !== false);
                    
                    if ($isCheckoutPage || $isCartPage): ?>
                        <a href="<?php echo url('cart'); ?>" class="text-black hover:text-gray-600 transition relative focus:outline-none header-icon inline-block">
                            <i class="fas fa-shopping-cart text-lg"></i>
                            <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center cart-count font-medium" style="font-size: 10px;"><?php echo $cartCount; ?></span>
                        </a>
                    <?php else: ?>
                        <button class="text-black hover:text-gray-600 transition relative focus:outline-none header-icon" id="cartBtn">
                            <i class="fas fa-shopping-cart text-lg"></i>
                            <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center cart-count font-medium" style="font-size: 10px;"><?php echo $cartCount; ?></span>
                        </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mobile Navigation (Old - Hidden) -->
            <div class="hidden pb-4" id="mobileMenu">
                <div class="flex flex-col space-y-4">
                    <a href="<?php echo url(''); ?>" class="text-gray-800 hover:text-primary transition">Home</a>
                    <a href="<?php echo url('shop.php'); ?>" class="text-gray-800 hover:text-primary transition">Shop</a>
                    <a href="<?php echo url('products.php'); ?>" class="text-gray-800 hover:text-primary transition">Products</a>
                    <a href="<?php echo url('pages.php'); ?>" class="text-gray-800 hover:text-primary transition">Pages</a>
                    <a href="<?php echo url('blog.php'); ?>" class="text-gray-800 hover:text-primary transition">Blog</a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Menu Drawer -->
    <div id="mobileMenuOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 xl:hidden"></div>
    <div id="mobileMenuDrawer" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <!-- Drawer Header -->
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <span class="font-semibold">Menu</span>
            </div>
            <button onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Menu Items -->
        <div class="flex flex-col">
            <a href="<?php echo url(''); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Home</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <button onclick="openSubmenu('shop')" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition w-full text-left">
                <span>Shop</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </button>
            <button onclick="openSubmenu('products')" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition w-full text-left">
                <span>Products</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </button>
            <button onclick="openSubmenu('pages')" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition w-full text-left">
                <span>Pages</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </button>
            <a href="<?php echo url('blog.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Blog</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="#" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Buy Theme!</span>
            </a>
            
            <!-- Wishlist -->
            <a href="<?php echo url('wishlist.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <i class="fas fa-heart text-sm mr-3 text-gray-600"></i>
                <span>Wishlist</span>
            </a>
            
            <!-- Login / Register -->
            <a href="<?php echo url('account.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <i class="fas fa-user text-sm mr-3 text-gray-600"></i>
                <span>Login / Register</span>
            </a>
        </div>
    </div>
    
    <!-- Shop Submenu -->
    <div id="shopSubmenu" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="closeSubmenu('shop')" class="text-white hover:text-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="font-semibold">Shop</span>
            </div>
            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="flex flex-col">
            <a href="<?php echo url('collections.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Collections</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>All Products</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
        </div>
    </div>
    
    <!-- Shop Layouts Submenu -->
    <div id="shopLayoutsSubmenu" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="closeSubSubmenu('shop-layouts')" class="text-white hover:text-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="font-semibold">Shop Layouts</span>
            </div>
            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="flex flex-col">
            <a href="<?php echo url('shop.php?layout=filter-left'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Filter left sidebar</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?layout=filter-right'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Filter right sidebar</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?layout=horizontal'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Horizontal filter</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?layout=drawer'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Filter drawer</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?layout=grid-3'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Grid 3 columns</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?layout=grid-4'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Grid 4 columns</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>All collections</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
        </div>
    </div>
    
    <!-- Shop Pages Submenu -->
    <div id="shopPagesSubmenu" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="closeSubSubmenu('shop-pages')" class="text-white hover:text-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="font-semibold">Shop Pages</span>
            </div>
            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="flex flex-col">
            <a href="<?php echo url('collection-v1.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Collection list v1</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('collection-v2.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Collection list v2</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?scroll=infinity'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Infinity scroll</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?load=more'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Load more button</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('shop.php?pagination=1'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Pagination page</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('banner-collection.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Banner collection</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
        </div>
    </div>
    
    <!-- Products Submenu -->
    <div id="productsSubmenu" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center space-x-4">
                <button onclick="closeSubmenu('products')" class="text-white hover:text-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="font-semibold">Products</span>
            </div>
            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 mb-6">
                <div>
                    <h3 class="font-bold text-lg mb-4">Shop Layouts</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo url('shop.php?layout=filter-left'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Filter left sidebar</a></li>
                        <li><a href="<?php echo url('shop.php?layout=filter-right'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Filter right sidebar</a></li>
                        <li><a href="<?php echo url('shop.php?layout=horizontal'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Horizontal filter</a></li>
                        <li><a href="<?php echo url('shop.php?layout=drawer'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Filter drawer</a></li>
                        <li><a href="<?php echo url('shop.php?layout=grid-3'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Grid 3 columns</a></li>
                        <li><a href="<?php echo url('shop.php?layout=grid-4'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Grid 4 columns</a></li>
                        <li><a href="<?php echo url('shop.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">All collections</a></li>
                    </ul>
                </div>
                <div class="border-t pt-6">
                    <h3 class="font-bold text-lg mb-4">Shop Pages</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo url('collection-v1.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Collection list v1</a></li>
                        <li><a href="<?php echo url('collection-v2.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Collection list v2</a></li>
                        <li><a href="<?php echo url('shop.php?scroll=infinity'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Infinity scroll</a></li>
                        <li><a href="<?php echo url('shop.php?load=more'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Load more button</a></li>
                        <li><a href="<?php echo url('shop.php?pagination=1'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Pagination page</a></li>
                        <li><a href="<?php echo url('banner-collection.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block text-gray-600 hover:text-primary transition py-2">Banner collection</a></li>
                    </ul>
                </div>
            </div>
            <!-- Featured Categories -->
            <div class="grid gap-4 mt-6">
                <a href="<?php echo url('category.php?slug=bracelets'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block group">
                    <div class="relative overflow-hidden rounded-lg">
                        <img src="https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=300&h=400&fit=crop" 
                             alt="Bracelets" 
                             class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute bottom-0 left-0 right-0 p-3 flex items-center justify-center">
                            <div class="bg-white px-4 py-2 w-full max-w-[90%] rounded-full">
                                <h3 class="text-center text-sm font-semibold text-gray-900">Bracelets</h3>
                            </div>
                        </div>
                    </div>
                </a>
                <a href="<?php echo url('category.php?slug=rings'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="block group">
                    <div class="relative overflow-hidden rounded-lg">
                        <img src="https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=300&h=400&fit=crop" 
                             alt="Rings" 
                             class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute bottom-0 left-0 right-0 p-3 flex items-center justify-center">
                            <div class="bg-white px-4 py-2 w-full max-w-[90%] rounded-full">
                                <h3 class="text-center text-sm font-semibold text-gray-900">Rings</h3>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Pages Submenu -->
    <div id="pagesSubmenu" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
        <div class="bg-black text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="closeSubmenu('pages')" class="text-white hover:text-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="font-semibold">Pages</span>
            </div>
            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="flex flex-col">
            <a href="<?php echo url('about.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>About us</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('contact.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Contact us</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('sale.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Sale</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('store.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Our store</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('faq.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>FAQ</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('wishlist.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Wishlist</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('compare.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Compare</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('location.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Store location</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <a href="<?php echo url('recently-viewed.php'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <span>Recently viewed products</span>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
        </div>
    </div>
    
    <!-- Search Overlay -->
    <div class="hidden fixed inset-0 bg-black bg-opacity-50 z-50" id="searchOverlay">
        <div class="container mx-auto px-4 pt-20">
            <div class="max-w-2xl mx-auto">
                <input type="text" placeholder="Search products..." class="w-full px-6 py-4 text-lg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                <button class="absolute right-8 top-24 text-gray-500 hover:text-white" id="closeSearch">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
    </div>
