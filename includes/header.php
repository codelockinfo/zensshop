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
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo htmlspecialchars(getImageUrl($faviconPng)); ?>">
    <?php endif; ?>


    <?php 
    $finalMetaDesc = !empty($metaDescription) ? $metaDescription : $globalMetaDesc;
    if (!empty($finalMetaDesc)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($finalMetaDesc); ?>">
    <?php endif; ?>

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?php echo htmlspecialchars($siteTitleSuffix); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($finalMetaDesc); ?>">
    <meta property="og:url" content="<?php echo $baseUrl . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteLogoText); ?>">
    
    <?php 
    $ogImageVal = $settingsObj->get('og_image');
    if ($ogImageVal) {
        $ogImageUrl = getImageUrl($ogImageVal); 
        // Ensure absolute URL
        if (strpos($ogImageUrl, 'http') !== 0) {
            $ogImageUrl = $baseUrl . '/' . ltrim($ogImageUrl, '/');
        }
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
    <?php } elseif (!empty($siteLogo) && $siteLogoType !== 'text') { 
        // Fallback to site logo if no specific OG image
        $logoUrl = getImageUrl($siteLogo);
        if (strpos($logoUrl, 'http') !== 0) {
            $logoUrl = $baseUrl . '/' . ltrim($logoUrl, '/');
        }
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($logoUrl); ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($logoUrl); ?>">
    <?php } ?>

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

    <?php
    $bcItems = [];
    // 1. Home
    $bcItems[] = [
        "@type" => "ListItem",
        "position" => 1,
        "name" => "Home",
        "item" => $baseUrl . '/'
    ];
    $pos = 2;

    $currentPageFile = basename($_SERVER['PHP_SELF']);
    
    // Shop / Category / Product contexts
    if ($currentPageFile === 'shop.php' || (isset($productData) && $currentPageFile === 'product.php')) {
        $bcItems[] = [
            "@type" => "ListItem",
            "position" => $pos++,
            "name" => "Shop",
            "item" => $baseUrl . '/shop'
        ];
    }
    
    // Context: Product Page
    if (isset($productData) && !empty($productData['name'])) {
         // Try to find category (Check if $productCategories is available in global scope)
         if (isset($productCategories) && !empty($productCategories)) {
             $cat = $productCategories[0];
             $bcItems[] = [
                "@type" => "ListItem",
                "position" => $pos++,
                "name" => $cat['name'],
                "item" => $baseUrl . '/shop?category=' . $cat['slug']
            ];
         }
         
         $bcItems[] = [
            "@type" => "ListItem",
            "position" => $pos++,
            "name" => $productData['name'],
            "item" => $baseUrl . '/product/' . $productData['slug']
        ];
    }
    // Context: Category Page (shop.php?category=...)
    elseif ($currentPageFile === 'shop.php' && !empty($_GET['category'])) {
        $cSlug = $_GET['category'];
        $cName = ucfirst(str_replace('-', ' ', $cSlug)); // Fallback
        if (isset($db)) {
            $cRow = $db->fetchOne("SELECT name FROM categories WHERE slug = ?", [$cSlug]);
            if ($cRow) $cName = $cRow['name'];
        }
        $bcItems[] = [
            "@type" => "ListItem",
            "position" => $pos++,
            "name" => $cName,
            "item" => $baseUrl . '/shop?category=' . $cSlug
        ];
    }
    // Context: Special Page / Landing Page (Generic or Special Product)
    elseif ((isset($lp) && !empty($lp['name'])) || (isset($landingPage) && !empty($landingPage))) {
        // Handle $lp (from settings/page.php) or $landingPage (from special-product.php)
        $pName = isset($lp) ? $lp['name'] : ($landingPage['name'] ?? $pageTitle ?? 'Page');
        $pSlug = isset($lp) ? $lp['slug'] : ($landingPage['slug'] ?? $_GET['page'] ?? '');
        $pUrl = $baseUrl . '/special-product.php?page=' . $pSlug; // Or typically just /page/slug if routed
        
        // If routed URL is cleaner, use that. But special-product.php seems to use query param.
        
        $bcItems[] = [
            "@type" => "ListItem",
            "position" => $pos++,
            "name" => $pName,
            "item" => $pUrl
        ];
    }
    // Context: Standard Pages
    elseif ($currentPageFile === 'cart.php') {
        $bcItems[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Shopping Cart", "item" => $baseUrl . '/cart'];
    }
    elseif ($currentPageFile === 'checkout.php') {
        $bcItems[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Checkout", "item" => $baseUrl . '/checkout'];
    }
    elseif ($currentPageFile === 'contact.php') {
        $bcItems[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Contact Us", "item" => $baseUrl . '/contact'];
    }
    
    if (count($bcItems) > 1):
    ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": <?php echo json_encode($bcItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    }
    </script>
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
    <style>
        /* Essential Utility Classes */
        .translate-x-full { transform: translateX(100%); }
        .-translate-x-full { transform: translateX(-100%); }
        .-translate-y-full { transform: translateY(-100%); }
        .hidden { display: none; }
    </style>
    
    <!-- Remove 'defer' to ensure Tailwind parses immediately to prevent unstyled content -->
    <script src="https://cdn.tailwindcss.com?plugins=typography" fetchpriority="high"></script>
    
    <script>
        // Ensure body is visible
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.visibility = 'visible';
            document.body.style.opacity = '1';
        });
    </script>
    
    <!-- Custom CSS -->
    <link rel="preload"
        href="<?php echo $baseUrl; ?>/assets/css/main7.css"
        as="style"
        onload="this.onload=null;this.rel='stylesheet'">

    <noscript>
        <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/main7.css">
    </noscript>

    
    <!-- Font Awesome (Local optimized with font-display:swap) -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/fontawesome-custom.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/fontawesome-custom.css"></noscript>
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
                <!-- Hamburger Menu  -->
                <button class="xl:hidden text-black hover:text-gray-600 transition focus:outline-none" 
                        aria-label="Open mobile menu"
                        aria-controls="mobile-menu-main"
                        aria-expanded="false"
                        id="mobileMenuBtn" 
                        style="position: relative; z-index: 60; cursor: pointer;"
                        onclick="event.stopPropagation(); 
                                 var overlay = document.getElementById('mobile-menu-overlay');
                                 var main = document.getElementById('mobile-menu-main');
                                 if(overlay && main) {
                                     overlay.style.display = 'block';
                                     main.style.display = 'flex';
                                     void overlay.offsetWidth; 
                                     overlay.classList.remove('opacity-0');
                                     main.classList.remove('-translate-x-full');
                                     document.body.style.overflow = 'hidden';
                                 }">
                    <i class="fas fa-bars text-xl" aria-hidden="true"></i>
                </button>
                
                <!-- Logo (Left on desktop, Centered on mobile/tablet) -->
                <div class="flex-shrink-0 xl:flex-shrink-0 absolute xl:relative left-1/2 xl:left-auto transform xl:transform-none -translate-x-1/2 xl:translate-x-0">
                    <a href="<?php echo $baseUrl; ?>/" class="flex items-center">
                        <?php if ($siteLogoType === 'text'): ?>
                            <span class="text-3xl font-heading font-bold text-black"><?php echo htmlspecialchars($siteLogoText); ?></span>
                        <?php else: ?>
                            <img src="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($siteLogo); ?>" 
                                 alt="Site Logo" 
                                 class="h-14 object-contain"
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
                    <button class="text-black hover:text-gray-600 transition focus:outline-none header-icon" aria-label="Open search" id="searchBtn">
                        <i class="fas fa-search text-lg" aria-hidden="true"></i>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($showUserIcon): ?>
                    <!-- User Account - Only visible on xl screens -->
                    <a href="<?php echo url('account'); ?>" class="hidden xl:block text-black hover:text-gray-600 transition header-icon" aria-label="Manage Account">
                        <i class="fas fa-user text-lg" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($showWishlistIcon): ?>
                    <!-- Wishlist - Only visible on xl screens -->
                    <a href="<?php echo url('wishlist'); ?>" class="hidden xl:block text-gray-800 hover:text-primary transition relative" aria-label="View Wishlist">
                        <i class="fas fa-heart text-xl" aria-hidden="true"></i>
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
                        <a href="<?php echo url('cart'); ?>" class="text-black hover:text-gray-600 transition relative focus:outline-none header-icon inline-block" aria-label="View Shopping Cart">
                            <i class="fas fa-shopping-cart text-lg" aria-hidden="true"></i>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center cart-count font-bold border-2 border-white"><?php echo $cartCount; ?></span>
                        </a>
                    <?php else: ?>
                        <button class="text-black hover:text-gray-600 transition relative focus:outline-none header-icon" aria-label="Open Shopping Cart Drawer" id="cartBtn">
                            <i class="fas fa-shopping-cart text-lg" aria-hidden="true"></i>
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
    </nav>
    
    <!-- NEW REBUILT MOBILE MENU START -->
    
    <!-- Dark Overlay (Shared) -->
    <!-- Dark Overlay (Shared) -->
    <div id="mobile-menu-overlay" 
         class="fixed inset-0 z-40 opacity-0 transition-opacity duration-300 ease-in-out" 
         style="display: none; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);"
         onclick="closeAllMobileMenus()"></div>

    <!-- Main Menu Drawer -->
    <div id="mobile-menu-main" 
         class="fixed top-0 left-0 bottom-0 w-80 bg-white z-50 transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Header -->
        <div class="bg-black text-white px-5 py-4 flex items-center justify-between flex-shrink-0">
            <span class="font-bold text-lg tracking-wide">Menu</span>
            <button type="button" aria-label="Close mobile menu" onclick="closeAllMobileMenus()" class="text-white hover:text-gray-300 focus:outline-none p-1">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto bg-white">
            <div class="flex flex-col py-2">
                <?php 
                $menuItemCounter = 0;
                // Render Top Level Items
                foreach ($headerMenuItems as $item) {
                    $hasChildren = !empty($item['children']);
                    $url = url($item['url'] ?? '#');
                    $name = htmlspecialchars($item['label'] ?? $item['name'] ?? '');
                    // Use a more robust ID strategy or ensure consistent uniqid if passed from backend
                    // Fallback to counter if no ID
                    $itemId = $item['id'] ?? 'menu-item-' . $menuItemCounter++;
                    $subId = 'mobile-menu-sub-' . $itemId; 
                    
                    if ($hasChildren) {
                        // Item with Submenu
                        echo '<button type="button" onclick="openMobileSubmenu(\''.$subId.'\')" class="flex items-center justify-between px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition text-left group">';
                        echo '<span class="font-medium group-hover:text-black">'.$name.'</span>';
                        echo '<i class="fas fa-chevron-right text-gray-400 text-sm group-hover:text-black"></i>';
                        echo '</button>';
                    } else {
                        // Standard Link
                        echo '<a href="'.$url.'" onclick="closeAllMobileMenus()" class="flex items-center justify-between px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition group">';
                        echo '<span class="font-medium group-hover:text-black">'.$name.'</span>';
                        echo '</a>';
                    }
                }
                ?>

                <!-- Standard Extra Links -->
                <a href="<?php echo url('wishlist'); ?>" onclick="closeAllMobileMenus()" class="flex items-center px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition group">
                    <i class="fas fa-heart text-gray-400 mr-3 group-hover:text-black transition"></i>
                    <span class="font-medium group-hover:text-black">Wishlist</span>
                </a>
                
                <?php if ($currentCustomer): ?>
                    <a href="<?php echo url('account'); ?>" onclick="closeAllMobileMenus()" class="flex items-center px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition group">
                        <i class="fas fa-user text-gray-400 mr-3 group-hover:text-black transition"></i>
                        <span class="font-medium group-hover:text-black">Account</span>
                    </a>
                <?php else: ?>
                    <a href="<?php echo url('login'); ?>" onclick="closeAllMobileMenus()" class="flex items-center px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition group">
                        <i class="fas fa-user text-gray-400 mr-3 group-hover:text-black transition"></i>
                        <span class="font-medium group-hover:text-black">Login / Register</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recursive Submenus Generation -->
    <?php
    if (!function_exists('renderNewMobileSubmenus')) {
        function renderNewMobileSubmenus($items, &$globalCounter) {
            foreach ($items as $item) {
                if (!empty($item['children'])) {
                    $name = htmlspecialchars($item['label'] ?? $item['name'] ?? '');
                    
                    // REPLICATE ID GENERATION EXACTLY
                    $itemId = $item['id'] ?? 'menu-item-' . $globalCounter++;
                    $thisId = 'mobile-menu-sub-' . $itemId;
                    
                    ?>
                    <div id="<?php echo $thisId; ?>" 
                         class="fixed top-0 left-0 bottom-0 w-80 bg-white z-[60] transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col shadow-2xl overflow-hidden">
                        
                        <!-- Header with Back & Close -->
                        <div class="bg-black text-white px-5 py-4 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center space-x-3">
                                <button type="button" aria-label="Close submenu" onclick="closeMobileSubmenu('<?php echo $thisId; ?>')" class="text-white hover:text-gray-300 focus:outline-none p-1">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span class="font-bold text-lg tracking-wide truncate max-w-[150px]"><?php echo $name; ?></span>
                            </div>
                            <button type="button" aria-label="Close mobile menu" onclick="closeAllMobileMenus()" class="text-white hover:text-gray-300 focus:outline-none p-1">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 overflow-y-auto bg-white">
                            <div class="flex flex-col py-2">
                                <?php
                                foreach ($item['children'] as $child) {
                                    $cName = htmlspecialchars($child['label'] ?? $child['name'] ?? '');
                                    $cUrl = url($child['url'] ?? '#');
                                    $cHasChildren = !empty($child['children']);
                                    $cSubId = 'mobile-menu-sub-' . ($child['id'] ?? uniqid());

                                    if ($cHasChildren) {
                                        echo '<button type="button" onclick="openMobileSubmenu(\''.$cSubId.'\')" class="flex items-center justify-between px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition text-left group">';
                                        echo '<span class="font-medium group-hover:text-black">'.$cName.'</span>';
                                        echo '<i class="fas fa-chevron-right text-gray-400 text-sm group-hover:text-black"></i>';
                                        echo '</button>';
                                    } else {
                                        echo '<a href="'.$cUrl.'" onclick="closeAllMobileMenus()" class="flex items-center justify-between px-6 py-4 text-gray-800 border-b border-gray-100 hover:bg-gray-50 transition group">';
                                        echo '<span class="font-medium group-hover:text-black">'.$cName.'</span>';
                                        echo '</a>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    // Recurse for deeper levels (Note: deeper levels need their own ID logic if they are to be opened. 
                    // For now assuming 1 level deep or consistent IDs from DB)
                    // If we have deeper levels, we need to pass the counter by reference or handle it.
                    // Since we are iterating strictly in order, the counter should align IF the structure matches.
                    // However, to be safe for >2 levels, we'd need a more complex ID map. 
                    // For 2 levels (Main -> Sub), this reference counter works if we iterate top-level again.
                    // BUT: We are inside a function. 
                    // BETTER APPROACH: Re-iterate the MAIN list to ensure order effectively.
                    renderNewMobileSubmenus($item['children'], $globalCounter);
                } else {
                     // Increment counter for items without children too, to keep alignment if mixed
                     $globalCounter++;
                }
            }
        }
    }
    // RESET COUNTER and Call for top level items
    $submenuCounter = 0;
    renderNewMobileSubmenus($headerMenuItems, $submenuCounter);
    ?>

    </script>
    <script>
    // --- NEW MOBILE MENU JS CONTROL ---
    
    // Ensure functions are on window
    
    // Open Main Menu
    window.openMobileMenu = function() {
        const overlay = document.getElementById('mobile-menu-overlay');
        const main = document.getElementById('mobile-menu-main');
        
        if (!overlay || !main) return;

        // 1. Make visible immediately
        overlay.style.display = 'block';
        main.style.display = 'flex'; // Ensure flex layout
        
        // 2. Force browser paint
        void overlay.offsetWidth;
        
        // 3. Start transitions
        overlay.classList.remove('opacity-0');
        main.classList.remove('-translate-x-full');
        
        document.body.style.overflow = 'hidden';
    }

    // Open Specific Submenu (slides OVER the main menu)
    window.openMobileSubmenu = function(id) {
        const sub = document.getElementById(id);
        if (sub) {
            sub.classList.remove('-translate-x-full');
        }
    }

    // Close Specific Submenu (Back Button)
    window.closeMobileSubmenu = function(id) {
        const sub = document.getElementById(id);
        if (sub) {
            sub.classList.add('-translate-x-full');
        }
    }

    // Close EVERYTHING (X Button or Overlay Click)
    window.closeAllMobileMenus = function() {
        const overlay = document.getElementById('mobile-menu-overlay');
        const main = document.getElementById('mobile-menu-main');
        
        // 1. Slide out ALL drawers (main and subs)
        const drawers = document.querySelectorAll('[id^="mobile-menu-main"], [id^="mobile-menu-sub-"]');
        drawers.forEach(el => {
            el.classList.add('-translate-x-full');
        });

        // 2. Fade out overlay
        if (overlay) {
            overlay.classList.add('opacity-0');
            setTimeout(() => {
                overlay.style.display = 'none'; // Use inline style
            }, 300);
        }

        document.body.style.overflow = '';
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
                                    
                                    const rating = Math.floor(p.rating || 5);
                                    let ratingHtml = '';
                                    for (let i = 0; i < 5; i++) {
                                        ratingHtml += `<i class="fas fa-star ${i < rating ? '' : 'text-gray-300'}"></i>`;
                                    }

                                    html += `
                                        <div class="min-w-[200px] w-[200px] flex-shrink-0">
                                            <a href="${baseUrl}/product?slug=${p.slug}" class="block h-full bg-white border border-gray-100 rounded-lg shadow-sm hover:shadow-md transition p-3 group">
                                                <div class="relative overflow-hidden rounded-lg mb-3 aspect-[3/4]">
                                                    <img src="${imgSrc}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='${baseUrl}/assets/images/placeholder.png'">
                                                </div>
                                                <h4 class="font-medium text-gray-900 text-sm mb-1 truncate group-hover:text-primary transition">${p.name}</h4>
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-gray-900 font-semibold text-sm">${formatPrice(price)}</span>
                                                </div>
                                                <div class="flex text-yellow-400 text-xs mt-1">
                                                    ${ratingHtml}
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

        function formatPrice(val) {
            let symbol = typeof CURRENCY_SYMBOL !== 'undefined' ? CURRENCY_SYMBOL : '₹';
            // Safety: if symbol is numeric and long (like a bug result), fallback to Rupee
            if (!isNaN(parseInt(symbol)) && String(symbol).length > 2) {
                symbol = '₹';
            }
            return symbol + parseFloat(val).toFixed(2);
        }
    });
    </script>
