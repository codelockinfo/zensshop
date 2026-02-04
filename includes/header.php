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
require_once __DIR__ . '/../classes/Settings.php';
$db = Database::getInstance();
$settingsObj = new Settings();
$storeId = getCurrentStoreId();

// Filter content by detected Store ID
$landingPagesList = $db->fetchAll("SELECT name, slug FROM landing_pages WHERE store_id = ? OR store_id IS NULL ORDER BY name ASC", [$storeId]);

// Fetch Header Menu (Store Specific)
$headerMenuIdVal = $db->fetchOne("SELECT id FROM menus WHERE location = 'header_main' AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [$storeId]);
$headerMenuItems = [];
if ($headerMenuIdVal) {
    $allItems = $db->fetchAll("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC", [$headerMenuIdVal['id']]);
    if (function_exists('buildMenuTree')) {
        $headerMenuItems = buildMenuTree($allItems);
    }
}

// Fetch Header Settings (Automatic store filtering via Settings class)
$siteLogoType = $settingsObj->get('site_logo_type', 'image');
$siteLogoText = $settingsObj->get('site_logo_text', 'CookPro');
$siteLogo = $settingsObj->get('site_logo', 'logo.png');
$showSearchIcon = $settingsObj->get('header_icon_search', '1') == '1';
$showUserIcon = $settingsObj->get('header_icon_user', '1') == '1';
$showWishlistIcon = $settingsObj->get('header_icon_wishlist', '1') == '1';
$showCartIcon = $settingsObj->get('header_icon_cart', '1') == '1';

// Fetch SEO & Branding Settings
$siteTitleSuffix = $settingsObj->get('site_title_suffix', 'CookPro - Elegant Jewelry Store');
$faviconPng = $settingsObj->get('favicon_png', '');
$faviconIco = $settingsObj->get('favicon_ico', ''); 
$globalMetaDesc = $settingsObj->get('global_meta_description', '');
$globalSchema = $settingsObj->get('global_schema_json', '');
$headerScripts = $settingsObj->get('header_scripts', '');

// Fetch Top Bar Settings
$topbarSlidesRaw = $settingsObj->get('topbar_slides', '[]');
$topbarSlides = json_decode($topbarSlidesRaw, true) ?: [
    ['text' => '100% secure online payment', 'link' => '', 'link_text' => ''],
    ['text' => 'Free Shipping for all order over $99', 'link' => '', 'link_text' => ''],
    ['text' => 'Sign up for 10% off your first order.', 'link' => 'signup', 'link_text' => 'Sign up']
];

$topbarLinksRaw = $settingsObj->get('topbar_links', '[]');
$topbarLinks = json_decode($topbarLinksRaw, true) ?: [
    ['label' => 'Contact Us', 'url' => 'contact'],
    ['label' => 'About Us', 'url' => 'about'],
    ['label' => 'Help Center', 'url' => 'help'],
    ['label' => 'Our Store', 'url' => 'store']
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
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?php echo htmlspecialchars($siteTitleSuffix); ?></title>
    
    <?php if ($faviconPng): ?>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($faviconPng); ?>">
    <?php endif; ?>
    <?php if ($faviconIco): ?>
    <link rel="shortcut icon" href="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($faviconIco); ?>">
    <?php endif; ?>

    <?php 
    $finalMetaDesc = !empty($metaDescription) ? $metaDescription : $globalMetaDesc;
    if (!empty($finalMetaDesc)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($finalMetaDesc); ?>">
    <?php endif; ?>

    <?php 
    if (!empty($globalSchema)): 
        // 1. Prepare Dynamic Values
        $siteNameVal = $settingsObj->get('site_name', $settingsObj->get('site_logo_text', 'Zensshop'));
        $siteLogoVal = $settingsObj->get('footer_logo_image', '');
        if ($siteLogoVal) {
            $siteLogoVal = $baseUrl . '/' . ltrim($siteLogoVal, '/');
        } else {
            $siteLogoVal = $baseUrl . '/assets/images/logo.png';
        }
        $siteDescVal = $settingsObj->get('footer_description', $globalMetaDesc);
        $sitePhoneVal = $settingsObj->get('footer_phone', '');
        $siteEmailVal = $settingsObj->get('footer_email', '');

        // 2. Perform Replacements
        $replacements = [
            '{{SITE_NAME}}' => $siteNameVal,
            '{{SITE_URL}}' => $baseUrl . '/',
            '{{SITE_LOGO}}' => $siteLogoVal,
            '{{SITE_DESCRIPTION}}' => strip_tags($siteDescVal),
            '{{SITE_PHONE}}' => $sitePhoneVal,
            '{{SITE_EMAIL}}' => $siteEmailVal,
            'https://yourdomain.com' => $baseUrl,
            'http://yourdomain.com' => $baseUrl
        ];

        foreach ($replacements as $tag => $val) {
            $globalSchema = str_replace($tag, $val, $globalSchema);
        }
    ?>
    <script type="application/ld+json">
        <?php echo $globalSchema; ?>
    </script>
    <?php else: 
        // Fallback: Generate Dynamic Organization Schema from settings
        $orgName = $settingsObj->get('footer_logo_text', $settingsObj->get('site_logo_text', 'Zensshop'));
        $orgLogo = $settingsObj->get('footer_logo_image', '');
        if ($orgLogo) {
            $orgLogo = $baseUrl . '/' . ltrim($orgLogo, '/');
        } else {
            $orgLogo = $baseUrl . '/assets/images/logo.png'; // Fallback
        }
        $orgDesc = $settingsObj->get('footer_description', 'Premium products store.');
        $orgAddress = $settingsObj->get('footer_address', '');
        $orgPhone = $settingsObj->get('footer_phone', '');
        $orgEmail = $settingsObj->get('footer_email', '');
        
        // Social links
        $socialJson = $settingsObj->get('footer_social_json', '[]');
        if (empty($socialJson) || $socialJson == '[]') {
            // Try individual social fields as fallback
            $socials = [];
            $fb = $settingsObj->get('footer_facebook'); if($fb) $socials[] = $fb;
            $ig = $settingsObj->get('footer_instagram'); if($ig) $socials[] = $ig;
            $tk = $settingsObj->get('footer_tiktok'); if($tk) $socials[] = $tk;
            $yt = $settingsObj->get('footer_youtube'); if($yt) $socials[] = $yt;
        } else {
            $socialLinks = json_decode($socialJson, true) ?: [];
            $socials = array_column($socialLinks, 'url');
        }

        $orgSchema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => $orgName,
            "url" => $baseUrl . '/',
            "logo" => $orgLogo,
            "description" => strip_tags($orgDesc),
            "sameAs" => $socials
        ];

        if ($orgAddress) {
            $orgSchema["address"] = [
                "@type" => "PostalAddress",
                "streetAddress" => $orgAddress
            ];
        }

        if ($orgPhone || $orgEmail) {
            $orgSchema["contactPoint"] = [
                "@type" => "ContactPoint",
                "telephone" => $orgPhone,
                "email" => $orgEmail,
                "contactType" => "customer service"
            ];
        }
    ?>
    <script type="application/ld+json">
        <?php echo json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
    <?php endif; ?>

    <?php if (!empty($customSchema)): ?>
    <script type="application/ld+json">
        <?php echo $customSchema; // Outputting raw JSON as requested ?>
    </script>
    <?php endif; ?>
    
    <?php if (!empty($headerScripts)): ?>
    <!-- Global Header Scripts (Analytics, Pixels, etc.) -->
    <?php echo $headerScripts; ?>
    <?php endif; ?>
    
    <!-- Resource Hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    
    <!-- Critical Fonts Preload -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Google Fonts Optimized -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Tailwind CSS - Deferred for PageSpeed (Render Blocking Fix) -->
    <script src="https://cdn.tailwindcss.com?plugins=typography" defer fetchpriority="high"></script>
    
    <!-- Critical FOUC Prevention -->
    <style>
        body { opacity: 0; transition: opacity 0.2s ease-in; }
        body.styled { opacity: 1; }
    </style>
    <script>
        // Once tailwind is ready, it will trigger an event or we can just wait for DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            // Give tailwind a tiny bit of time to process
            setTimeout(() => document.body.classList.add('styled'), 50);
        });
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/main6.css" fetchpriority="high">

    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <script>
    // Make BASE_URL available globally for all frontend pages
    const BASE_URL = '<?php echo $baseUrl; ?>';
    // Make currency symbol available globally
    const CURRENCY_SYMBOL = '<?php echo defined("CURRENCY_SYMBOL") ? CURRENCY_SYMBOL : "₹"; ?>';
    
    // Adjust mega menu position to prevent overflow
    function adjustMegaMenuPosition(menuElement) {
        // Use requestAnimationFrame for better timing
        requestAnimationFrame(() => {
            // Reset positioning to default to calculate natural dimensions
            menuElement.style.left = '0px';
            menuElement.style.right = 'auto';
            menuElement.style.transform = 'none';
            
            // Get measurements
            const rect = menuElement.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const containerPadding = 20; // Safety padding
            
            let shiftX = 0;
            
            // Check if menu overflows on the right
            if (rect.right > (viewportWidth - containerPadding)) {
                shiftX = (viewportWidth - containerPadding) - rect.right;
            }
            
            // Apply the right shift first to check if it causes left overflow
            if (shiftX !== 0) {
                 // Check if this shift pushes it off the left side
                 if ((rect.left + shiftX) < containerPadding) {
                     // If it does, we need to clamp it.
                     // Calculate the shift needed to align with left edge
                     shiftX = containerPadding - rect.left;
                 }
            } else {
                // If no right overflow, check if it naturally overflows left (unlikely with left=0, but possible with transforms)
                if (rect.left < containerPadding) {
                     shiftX = containerPadding - rect.left;
                }
            }
            
            // Apply the final calculated shift
            if (shiftX !== 0) {
                menuElement.style.transform = `translateX(${shiftX}px)`;
            }
        });
    }
    
    // Auto-adjust all mega menus on window resize and hover
    window.addEventListener('resize', () => {
        document.querySelectorAll('.mega-menu-dropdown').forEach(menu => {
            // Check visibility by offsetParent or display style
            if (menu.offsetParent !== null || window.getComputedStyle(menu).display !== 'none') {
                 adjustMegaMenuPosition(menu);
            }
        });
    });
    
    // Add hover listeners to trigger adjustment immediately when menu opens
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.group, .group\\/sub').forEach(group => {
            group.addEventListener('mouseenter', () => {
                const menu = group.querySelector('.mega-menu-dropdown');
                if (menu) adjustMegaMenuPosition(menu);
            });
        });
    });
    </script>
    <style>
    /* Multi-level Dropdown Support */
    .group:hover .group-hover\:block { display: block; }
    .group\/sub:hover > .group-hover\/sub\:block { display: block; }
    
    /* Mega Menu Viewport Constraint */
    .mega-menu-dropdown {
        max-width: calc(100vw - 40px) !important;
        /* Default to left align, let JS handle the shift */
        left: 0;
        right: auto;
    }
    
    /* For smaller screens, ensure it doesn't break layout */
    @media (max-width: 1024px) {
        .mega-menu-dropdown {
            max-width: 100% !important;
            left: 0 !important;
            right: 0 !important;
            transform: none !important; /* Disable JS positioning on mobile if used */
        }
    }
    </style>
</head>
<body class="font-body">
    <!-- Top Bar -->
    <div class="block bg-black text-white text-sm py-2" style="padding: 12px 0;">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <!-- Left side spacer (to balance the right links) -->
            <div class="flex-1 hidden xl:block"></div>

            <!-- Centered Slider Section -->
            <div class="flex-1 flex items-center justify-center space-x-6">
                <!-- Left Arrow -->
                <button class="top-bar-arrow top-bar-arrow-left flex-shrink-0 text-gray-500 hover:text-white transition" id="topBarPrev" aria-label="Previous">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>

                <!-- Slider Window -->
                <div class="relative overflow-hidden w-full max-w-[450px]">
                    <div class="top-bar-slider flex transition-transform duration-500 ease-in-out" id="topBarSlider">
                        <?php foreach ($topbarSlides as $slide): ?>
                        <div class="top-bar-slide flex-shrink-0 w-full flex items-center justify-center text-center">
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

                <!-- Right Arrow -->
                <button class="top-bar-arrow top-bar-arrow-right flex-shrink-0 text-gray-500 hover:text-white transition" id="topBarNext" aria-label="Next">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
            </div>

            <!-- Right side links -->
            <div class="hidden xl:flex flex-1 items-center justify-end space-x-4">
                <?php foreach ($topbarLinks as $link): ?>
                <a href="<?php echo url($link['url']); ?>" class="hover:text-gray-300 transition whitespace-nowrap"><?php echo htmlspecialchars($link['label']); ?></a>
                <?php endforeach; ?>
                <!-- Currency/Region Selector -->
                <div class="relative ml-4 pl-4 border-l border-gray-700 hidden">
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
                            <span class="text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($siteLogoText); ?></span>
                        <?php else: ?>
                            <img src="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($siteLogo); ?>" 
                                 alt="Site Logo" 
                                 class="h-8 object-contain"
                                 onerror="this.parentElement.innerHTML='<span class=\'text-3xl font-heading font-bold text-black\'>CookProo</span>'">
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
                    <a href="<?php echo url('account'); ?>" class="hidden xl:block text-black hover:text-gray-600 transition header-icon">
                        <i class="fas fa-user text-lg"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($showWishlistIcon): ?>
                    <!-- Wishlist - Only visible on xl screens -->
                    <a href="<?php echo url('wishlist'); ?>" class="hidden xl:block text-gray-800 hover:text-primary transition relative">
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
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center cart-count font-bold border-2 border-white"><?php echo $cartCount; ?></span>
                        </a>
                    <?php else: ?>
                        <button class="text-black hover:text-gray-600 transition relative focus:outline-none header-icon" id="cartBtn">
                            <i class="fas fa-shopping-cart text-lg"></i>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center cart-count font-bold border-2 border-white"><?php echo $cartCount; ?></span>
                        </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mobile Navigation (Old - Hidden) -->
            <div class="hidden pb-4" id="mobileMenu">
                <div class="flex flex-col space-y-4">
                    <a href="<?php echo url(''); ?>" class="text-gray-800 hover:text-primary transition">Home</a>
                    <a href="<?php echo url('shop'); ?>" class="text-gray-800 hover:text-primary transition">Shop</a>
                    <a href="<?php echo url('products'); ?>" class="text-gray-800 hover:text-primary transition">Products</a>
                    <a href="<?php echo url('pages'); ?>" class="text-gray-800 hover:text-primary transition">Pages</a>
                    <a href="<?php echo url('blog'); ?>" class="text-gray-800 hover:text-primary transition">Blog</a>
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
        
        <div class="flex flex-col">
            <?php 
            if (!function_exists('renderMobileMenuItem')) {
                function renderMobileMenuItem($item) {
                     $hasChildren = !empty($item['children']);
                     $url = url($item['url'] ?? '#');
                     $name = htmlspecialchars($item['label'] ?? $item['name'] ?? '');
                     $id = $item['id'] ?? uniqid();
                     
                     if ($hasChildren) {
                         echo '<button onclick="openMobileSubmenu(\'mobile-menu-' . $id . '\')" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition w-full text-left">';
                         echo '<span>' . $name . '</span>';
                         echo '<i class="fas fa-chevron-right text-sm text-gray-400"></i>';
                         echo '</button>';
                     } else {
                         echo '<a href="' . $url . '" onclick="if(typeof closeMobileMenu === \'function\') closeMobileMenu();" class="flex items-center justify-between px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">';
                         echo '<span>' . $name . '</span>';
                         if ($hasChildren) { 
                            echo '<i class="fas fa-chevron-right text-sm text-gray-400"></i>';
                         }
                         echo '</a>';
                     }
                }
            }
            
            foreach ($headerMenuItems as $item) {
                renderMobileMenuItem($item);
            }
            ?>
            
             <!-- Wishlist -->
            <a href="<?php echo url('wishlist'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                <i class="fas fa-heart text-sm mr-3 text-gray-600"></i>
                <span>Wishlist</span>
            </a>
            
            <!-- Login / Register Info -->
            <?php if ($currentCustomer): ?>
                <a href="<?php echo url('account'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                    <i class="fas fa-user text-sm mr-3 text-gray-600"></i>
                    <span>Account</span>
                </a>
            <?php else: ?>
                <a href="<?php echo url('login'); ?>" onclick="if(typeof closeMobileMenu === 'function') closeMobileMenu();" class="flex items-center px-6 py-4 text-black border-b border-gray-200 hover:bg-gray-50 transition">
                    <i class="fas fa-user text-sm mr-3 text-gray-600"></i>
                    <span>Login / Register</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Dynamic Submenus -->
    <?php
    if (!function_exists('renderMobileSubmenusRecursive')) {
        function renderMobileSubmenusRecursive($items) {
            foreach ($items as $item) {
                if (!empty($item['children'])) {
                    $id = $item['id'] ?? uniqid();
                    $name = htmlspecialchars($item['label'] ?? $item['name'] ?? '');
                    ?>
                    <div id="mobile-menu-<?php echo $id; ?>" class="hidden fixed top-0 left-0 h-full w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto xl:hidden">
                        <div class="bg-black text-white px-6 py-4 flex items-center justify-between sticky top-0 z-50">
                            <div class="flex items-center space-x-4">
                                <button onclick="closeMobileSubmenu('mobile-menu-<?php echo $id; ?>')" class="text-white hover:text-gray-300">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span class="font-semibold"><?php echo $name; ?></span>
                            </div>
                            <button onclick="closeMobileMenu()" class="text-white hover:text-gray-300">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="flex flex-col">
                            <?php 
                            foreach ($item['children'] as $child) {
                                renderMobileMenuItem($child);
                            } 
                            ?>
                        </div>
                    </div>
                    <?php
                    renderMobileSubmenusRecursive($item['children']);
                }
            }
        }
    }
    renderMobileSubmenusRecursive($headerMenuItems);
    ?>

    <script>
    function openMobileSubmenu(id) {
        const el = document.getElementById(id);
        if(el) {
            el.classList.remove('hidden');
            // Force reflow
            void el.offsetWidth; 
            el.classList.remove('translate-x-full');
        }
    }
    function closeMobileSubmenu(id) {
        const el = document.getElementById(id);
        if(el) {
            el.classList.add('translate-x-full');
            setTimeout(() => {
                el.classList.add('hidden');
            }, 300);
        }
    }
    </script>
    
    <!-- Search Overlay -->
    <div class="fixed inset-0 bg-white z-[60] overflow-y-auto transition-transform duration-500 ease-in-out transform -translate-y-full invisible" id="searchOverlay">
        <button id="closeSearchBtn" class="absolute top-6 right-6 text-gray-400 hover:text-black transition p-2">
            <i class="fas fa-times text-2xl"></i>
        </button>

        <div class="container mx-auto px-4 pt-20 pb-12 relative max-w-6xl">
            <h2 class="text-3xl font-serif text-center mb-8">Search Our Site</h2>
            
            <div class="max-w-3xl mx-auto relative mb-12">
                <form action="<?php echo url('shop'); ?>" method="GET" class="relative group border border-gray-300 rounded-full focus-within:border-black transition-colors px-4" onclick="document.getElementById('headerSearchInput').focus()">
                    <input type="text" name="search" id="headerSearchInput" placeholder="I'm looking for..." 
                           class="w-full px-4 py-3 text-lg font-light bg-transparent text-left text-black focus:outline-none placeholder-gray-400"
                           autocomplete="off">
                    <button type="submit" id="headerSearchSubmitBtn" class="absolute right-4 top-1/2 -translate-y-1/2 text-black p-2">
                        <i class="fas fa-search text-xl"></i>
                    </button>
                    <button type="button" id="headerSearchClearBtn" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-black p-2 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </form>
            </div>

            <!-- Trending Search (Always Visible) -->
            <div class="mb-12 text-center hidden md:block">
                <h3 class="text-lg font-serif mb-6 text-gray-900">Trending Search</h3>
                <div class="flex flex-wrap justify-center gap-3">
                    <?php
                    // Fetch random categories for Trending Search
                    $trendingCats = [];
                    try {
                        $trendingCats = $db->fetchAll("SELECT name, slug FROM categories WHERE status='active' AND (store_id = ? OR store_id IS NULL) ORDER BY RAND() LIMIT 5", [$storeId]);
                    } catch(PDOException $e) {}
                    
                    foreach ($trendingCats as $tc): 
                    ?>
                        <a href="<?php echo url('shop?category=' . $tc['slug']); ?>" 
                           class="px-6 py-2 rounded-full border border-gray-200 text-gray-600 hover:border-black hover:text-black transition text-sm">
                            <?php echo htmlspecialchars($tc['name']); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if(empty($trendingCats)): ?>
                        <a href="<?php echo url('shop'); ?>" class="px-6 py-2 rounded-full border border-gray-200 text-gray-600 hover:border-black hover:text-black transition text-sm">All Products</a>
                        <a href="<?php echo url('shop?sort=best_selling'); ?>" class="px-6 py-2 rounded-full border border-gray-200 text-gray-600 hover:border-black hover:text-black transition text-sm">Best Sellers</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Popular Products (Hidden on Search) -->
            <div id="searchPopularContent" class="animate-fade-in">
                <!-- Popular Products -->
                <div>
                    <h3 class="text-lg font-serif mb-8 text-gray-900 text-center">Popular Products</h3>
                    
                    <!-- Popular Products Slider -->
                    <div class="relative group/slider">
                        <div class="overflow-hidden px-1">
                            <div id="popularProductsSlider" class="flex transition-transform duration-500 ease-out will-change-transform gap-4">
                                <?php
                                // Fetch popular products (random 5 from active products)
                                $popularProducts = [];
                                try {
                                    $popularProducts = $db->fetchAll("SELECT id, name, slug, price, sale_price, images, featured_image FROM products WHERE status='active' AND (store_id = ? OR store_id IS NULL) ORDER BY RAND() LIMIT 5", [$storeId]);
                                } catch(PDOException $e) {}

                                foreach ($popularProducts as $pp):
                                    $ppPrice = $pp['sale_price'] ?? $pp['price'];
                                    $ppOldPrice = $pp['sale_price'] ? $pp['price'] : null;
                                    $ppImg = getProductImage($pp); // using helper function from header/functions
                                    $currencySymbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
                                ?>
                                <div class="min-w-[200px] w-[200px] flex-shrink-0">
                                    <a href="<?php echo url('product?slug=' . $pp['slug']); ?>" class="block h-full bg-white border border-gray-100 rounded-lg shadow-sm hover:shadow-md transition p-3">
                                        <div class="relative overflow-hidden rounded-lg mb-3 aspect-[3/4]">
                                            <img src="<?php echo htmlspecialchars($ppImg); ?>" 
                                                 alt="<?php echo htmlspecialchars($pp['name']); ?>" 
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        </div>
                                        <h4 class="font-medium text-gray-900 text-sm mb-1 truncate group-hover:text-primary transition"><?php echo htmlspecialchars($pp['name']); ?></h4>
                                        <div class="flex items-center gap-2 text-sm">
                                            <?php if ($ppOldPrice): ?>
                                                <span class="text-gray-400 line-through"><?php echo format_price($ppOldPrice); ?></span>
                                                <span class="text-[#1a3d32] font-semibold"><?php echo format_price($ppPrice); ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-900 font-semibold"><?php echo format_price($ppPrice); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex text-yellow-400 text-xs mt-1">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Navigation Arrows (Popular) -->
                        <button class="absolute -left-2 top-1/2 -translate-y-1/2 bg-white shadow-lg border border-gray-100 rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:bg-black hover:text-white transition z-10 hidden" id="popularPrev">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </button>
                        <button class="absolute -right-2 top-1/2 -translate-y-1/2 bg-white shadow-lg border border-gray-100 rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:bg-black hover:text-white transition z-10 hidden" id="popularNext">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Live Search Results -->
            <div id="headerSearchResults" class="hidden mt-8">
                 <!-- Results injected via JS -->
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ... (Existing variables)
        const searchOverlay = document.getElementById('searchOverlay');
        const searchInput = document.getElementById('headerSearchInput');
        const popularContent = document.getElementById('searchPopularContent');
        const searchResults = document.getElementById('headerSearchResults');
        const searchBtn = document.getElementById('searchBtn'); 
        const closeBtn = document.getElementById('closeSearchBtn');
        const searchSubmitBtn = document.getElementById('headerSearchSubmitBtn');
        const searchClearBtn = document.getElementById('headerSearchClearBtn');
        let searchTimeout;

        // --- REUSABLE SLIDER LOGIC ---
        function setupNativeSlider(sliderId, prevId, nextId) {
            const slider = document.getElementById(sliderId);
            const prevBtn = document.getElementById(prevId);
            const nextBtn = document.getElementById(nextId);

            if (!slider || !prevBtn || !nextBtn) return;

            let currentIndex = 0;
            const itemWidth = 200; // width of card
            const gap = 16; // gap-4 = 16px
            let keyHandler = null;
            
            function updateSlider() {
                const containerWidth = slider.parentElement.offsetWidth;
                const totalWidth = slider.scrollWidth;
                const effectiveItemWidth = itemWidth + gap;
                const itemsInView = Math.floor(containerWidth / effectiveItemWidth);
                const maxIndex = Math.max(0, slider.children.length - itemsInView);
                
                // Clamp index
                if (currentIndex < 0) currentIndex = 0;
                if (currentIndex > maxIndex) currentIndex = maxIndex;

                const translateX = -(currentIndex * effectiveItemWidth);
                slider.style.transform = `translateX(${translateX}px)`;

                // Update buttons
                prevBtn.style.display = currentIndex > 0 ? 'flex' : 'none';
                nextBtn.style.display = currentIndex < maxIndex ? 'flex' : 'none';
            }

            // Click Handlers
            prevBtn.onclick = (e) => { e.preventDefault(); currentIndex--; updateSlider(); };
            nextBtn.onclick = (e) => { e.preventDefault(); currentIndex++; updateSlider(); };

            // Input Handling (Touch, Mouse, Keyboard)
            let startX, currentX, isDragging = false;

            const startDrag = (x) => {
                startX = x;
                isDragging = true;
                slider.style.transition = 'none';
                slider.style.cursor = 'grabbing';
            };

            const moveDrag = (x) => {
                if (!isDragging) return;
                currentX = x;
            };

            const endDrag = (x) => {
                if (!isDragging) return;
                isDragging = false;
                slider.style.transition = '';
                slider.style.cursor = 'grab';
                const diff = x - startX;
                
                if (Math.abs(diff) > 50) { 
                    if (diff > 0) currentIndex--; 
                    else currentIndex++; 
                }
                updateSlider();
            };

            // Touch Events
            slider.addEventListener('touchstart', (e) => startDrag(e.touches[0].clientX), {passive: true});
            slider.addEventListener('touchmove', (e) => moveDrag(e.touches[0].clientX), {passive: true});
            slider.addEventListener('touchend', (e) => endDrag(e.changedTouches[0].clientX));

            // Mouse Events
            slider.style.cursor = 'grab';
            slider.addEventListener('mousedown', (e) => { e.preventDefault(); startDrag(e.clientX); });
            slider.addEventListener('mousemove', (e) => { if(isDragging) e.preventDefault(); moveDrag(e.clientX); });
            slider.addEventListener('mouseup', (e) => endDrag(e.clientX));
            slider.addEventListener('mouseleave', (e) => { if (isDragging) endDrag(e.clientX); });

            // Keyboard Navigation
            const handleKeyNav = (e) => {
                if (document.activeElement.id === 'headerSearchInput') return;
                if (!document.getElementById('searchOverlay') || document.getElementById('searchOverlay').classList.contains('invisible')) return;

                // Check visibility of this specific slider
                // If both are present, we might have a conflict, but usually only one is visible
                const wrapper = slider.closest('#searchPopularContent') || slider.closest('#headerSearchResults');
                if (wrapper && (wrapper.classList.contains('hidden') || wrapper.style.display === 'none')) return;

                if (e.key === 'ArrowLeft') { e.preventDefault(); currentIndex--; updateSlider(); } 
                else if (e.key === 'ArrowRight') { e.preventDefault(); currentIndex++; updateSlider(); }
            };
            
            document.removeEventListener('keydown', slider._keyHandler);
            slider._keyHandler = handleKeyNav;
            document.addEventListener('keydown', handleKeyNav);

            // Initial and Resize
            updateSlider();
            window.addEventListener('resize', updateSlider);
        }

        // Initialize Popular Slider immediately
        setupNativeSlider('popularProductsSlider', 'popularPrev', 'popularNext');

        // --- EXISTING LOGIC ---

        // Open Search
        if (searchBtn && searchOverlay) {
            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                searchOverlay.classList.remove('invisible');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                
                // Slight delay to allow display change to register before transform
                requestAnimationFrame(() => {
                    searchOverlay.classList.remove('-translate-y-full');
                });
                
                setTimeout(() => searchInput.focus(), 300);
            });
        }

        // Close Search (General Overlay Close)
        const closeSearch = () => {
            searchOverlay.classList.add('-translate-y-full');
            document.body.style.overflow = '';
            
            // Wait for transition to finish
            setTimeout(() => {
                searchOverlay.classList.add('invisible');
                
                // Reset state
                searchInput.value = '';
                if(popularContent) popularContent.classList.remove('hidden');
                searchResults.classList.add('hidden');
                searchResults.innerHTML = '';
                // Reset Icons
                if(searchSubmitBtn) searchSubmitBtn.classList.remove('hidden');
                if(searchClearBtn) searchClearBtn.classList.add('hidden');
            }, 500); // Match duration-500
        };

        if (closeBtn) closeBtn.addEventListener('click', closeSearch);

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !searchOverlay.classList.contains('invisible')) {
                closeSearch();
            }
        });

        // Close on Click Outside
        searchOverlay.addEventListener('click', (e) => {
            // Check if the click target is the overlay itself or the main container wrapper
            // This prevents closing when clicking inside the functionality
            if (e.target === searchOverlay || e.target.classList.contains('container')) {
                closeSearch();
            }
        });

        // Clear Button Logic
        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                
                // Toggle Icons
                searchSubmitBtn.classList.remove('hidden');
                searchClearBtn.classList.add('hidden');

                // Reset View
                if(popularContent) popularContent.classList.remove('hidden');
                searchResults.classList.add('hidden');
                searchResults.innerHTML = '';
            });
        }

        // Live Search Logic
        if (searchInput && searchResults && popularContent) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                clearTimeout(searchTimeout);

                // Toggle Clear/Submit Buttons
                if (this.value.length > 0) {
                    if (searchSubmitBtn) searchSubmitBtn.classList.add('hidden');
                    if (searchClearBtn) searchClearBtn.classList.remove('hidden');
                } else {
                    if (searchSubmitBtn) searchSubmitBtn.classList.remove('hidden');
                    if (searchClearBtn) searchClearBtn.classList.add('hidden');
                }

                if (query.length < 1) {
                    // Show popular content, hide results
                    popularContent.classList.remove('hidden');
                    searchResults.classList.add('hidden');
                    searchResults.innerHTML = '';
                    return;
                }

                // Hide popular content, show loading or wait for results
                popularContent.classList.add('hidden');
                
                searchTimeout = setTimeout(() => {
                    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
                    searchResults.classList.remove('hidden');
                    searchResults.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-3xl text-gray-300"></i></div>';

                    fetch(`${baseUrl}/api/products.php?search=${encodeURIComponent(query)}&limit=10`)
                        .then(res => res.json())
                        .then(data => {
                            if ((data.success && data.products && data.products.length > 0)) {
                                // Slider Structure
                                let html = `
                                <div class="mb-4">
                                     <h3 class="text-lg font-serif mb-4 text-gray-900 text-center">Search Results</h3>
                                </div>
                                <div class="relative group/slider">
                                    <div class="overflow-hidden px-1">
                                        <div id="searchResultsSlider" class="flex transition-transform duration-500 ease-out will-change-transform gap-4">`;
                                
                                data.products.forEach(p => {
                                    // ... (Existing inner loop logic)
                                    const price = parseFloat(p.sale_price || p.price);
                                    let imgSrc = '';
                                    if (p.images) { try { const imgs = JSON.parse(p.images); imgSrc = imgs[0] || ''; } catch(e) {} }
                                    if (!imgSrc && p.featured_image) imgSrc = p.featured_image;
                                    if (imgSrc && !imgSrc.startsWith('http')) imgSrc = baseUrl + '/' + imgSrc.replace(/^\//, '');
                                    if (!imgSrc) imgSrc = baseUrl + '/assets/images/placeholder.png';
                                    const currencySymbol = typeof CURRENCY_SYMBOL !== 'undefined' ? CURRENCY_SYMBOL : '₹';
                                    
                                    html += `
                                        <div class="min-w-[200px] w-[200px] flex-shrink-0">
                                            <a href="${baseUrl}/product?slug=${p.slug}" class="block h-full bg-white border border-gray-100 rounded-lg shadow-sm hover:shadow-md transition p-3">
                                                <div class="relative overflow-hidden rounded-lg mb-3 aspect-[3/4]">
                                                    <img src="${imgSrc}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='${baseUrl}/assets/images/placeholder.png'">
                                                </div>
                                                <h4 class="font-medium text-gray-900 text-sm mb-1 truncate group-hover:text-primary transition">${p.name}</h4>
                                                <div class="font-semibold text-gray-900 text-sm">
                                                    ${currencySymbol}${price.toFixed(2)}
                                                </div>
                                            </a>
                                        </div>
                                    `;
                                });
                                
                                html += `
                                        </div>
                                    </div>
                                    
                                    <!-- Navigation Arrows -->
                                    <button class="absolute -left-2 top-1/2 -translate-y-1/2 bg-white shadow-lg border border-gray-100 rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:bg-black hover:text-white transition z-10 hidden" id="searchResultPrev">
                                        <i class="fas fa-chevron-left text-sm"></i>
                                    </button>
                                    <button class="absolute -right-2 top-1/2 -translate-y-1/2 bg-white shadow-lg border border-gray-100 rounded-full w-10 h-10 flex items-center justify-center text-gray-800 hover:bg-black hover:text-white transition z-10 hidden" id="searchResultNext">
                                        <i class="fas fa-chevron-right text-sm"></i>
                                    </button>
                                </div>`;
                                
                                // View All Link
                                html += `
                                    <div class="text-center mt-8">
                                        <a href="${baseUrl}/shop.php?search=${encodeURIComponent(query)}" class="inline-block px-8 py-3 bg-black text-white hover:bg-gray-800 transition rounded-full text-sm font-medium hover:font-bold hover:text-white">
                                            View All Results
                                        </a>
                                    </div>
                                `;
                                
                                searchResults.innerHTML = html;
                                
                                // Initialize Slider
                                setTimeout(() => {
                                    setupNativeSlider('searchResultsSlider', 'searchResultPrev', 'searchResultNext');
                                }, 100);
                            } else {
                                searchResults.innerHTML = `
                                    <div class="text-center py-12">
                                        <div class="text-gray-400 text-5xl mb-4"><i class="far fa-sad-tear"></i></div>
                                        <h3 class="text-xl font-medium text-gray-900 mb-2">No products found</h3>
                                        <p class="text-gray-500">Sorry, but nothing matched your search terms for "${escapeHtml(query)}". Please try again with different keywords.</p>
                                    </div>
                                `;
                            }
                        })
                        .catch(e => {
                            console.error("Search Error:", e);
                            searchResults.innerHTML = '<div class="text-center py-12 text-red-500">An error occurred while searching.</div>';
                        });
                }, 300);
            });
        }
        
        function escapeHtml(text) {
          const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
          };
          return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
    </script>
