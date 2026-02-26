<?php
// Ensure baseUrl is available
if (!isset($baseUrl) && function_exists('getBaseUrl')) {
    $baseUrl = getBaseUrl();
} elseif (!isset($baseUrl)) {
    require_once __DIR__ . '/functions.php';
    $baseUrl = getBaseUrl();
}

// Fetch Footer Data
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/CustomerAuth.php';
$db = Database::getInstance();
$settingsObj = new Settings();
$customerAuth = new CustomerAuth();
$customer = $customerAuth->getCurrentCustomer();
$storeId = getCurrentStoreId();

// Fetch Footer Menus (Store Specific)
// We now use a SINGLE "Footer Menu" (footer_main)
$footerMenuIdVal = $db->fetchOne("SELECT id FROM menus WHERE location = 'footer_main' AND (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [$storeId]);
$footerColumns = [];
if ($footerMenuIdVal) {
    $rawItems = $db->fetchAll("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC", [$footerMenuIdVal['id']]);
    $footerColumns = buildMenuTree($rawItems);
}

// Function helper to get setting easily
$getFooterSetting = function($key, $default = '') use ($settingsObj) {
    return $settingsObj->get($key, $default);
};

function renderFooterLinkRecursive($item, $baseUrl) {
    $url = htmlspecialchars(str_replace('SITE_URL', $baseUrl, $item['url']));
    // Remove .php extension if present
    $url = preg_replace('/\.php($|\?)/', '$1', $url);
    $label = htmlspecialchars($item['label']);
    
    // Special check for Social Icons in the "Follow Us" column
    // Ideally we detect this via parent name, but recursive function doesn't know parent easily without passing it.
    // For now, render standard link.
    
    echo "<li><a href=\"$url\" class=\"text-gray-700 hover:text-gray-600 transition text-sm block group/link\">";
    echo "<span class=\"inline-block group-hover/link:translate-x-2 transition-transform duration-300\">$label</span>";
    echo "</a>";
    if (!empty($item['children'])) {
        echo '<ul class="pl-4 mt-2 space-y-2 border-l border-gray-200 ml-1">';
        foreach ($item['children'] as $child) {
            renderFooterLinkRecursive($child, $baseUrl);
        }
        echo '</ul>';
    }
    echo "</li>";
}

// Fetch Footer Visual Styles
// Fetch Footer Visual Styles (Consolidated JSON)
$fStylesJson = $getFooterSetting('footer_styles', '[]');
$fStyles = json_decode($fStylesJson, true);
$footerBg = $fStyles['bg_color'] ?? $getFooterSetting('footer_bg_color', '#ffffff');
$footerText = $fStyles['text_color'] ?? $getFooterSetting('footer_text_color', '#000000');
$footerHover = $fStyles['hover_color'] ?? $getFooterSetting('footer_hover_color', '#000000');
// Fetch Quick View Visual Styles (Nested in product_page_styles)
$pStylesJson = $settingsObj->get('product_page_styles', '[]');
$pStyles = json_decode($pStylesJson, true);
$qvStyles = $pStyles['quickview'] ?? [];

$qv_modal_bg = $qvStyles['modal_bg_color'] ?? '#ffffff';
$qv_overlay = $qvStyles['overlay_color'] ?? '#6b7280bf';
$qv_atc_bg = $qvStyles['atc_btn_color'] ?? '#000000';
$qv_atc_text = $qvStyles['atc_btn_text_color'] ?? '#ffffff';
$qv_atc_hover_bg = $qvStyles['atc_hover_bg_color'] ?? '#374151';
$qv_atc_hover_text = $qvStyles['atc_hover_text_color'] ?? '#ffffff';
$qv_buy_bg = $qvStyles['buy_now_btn_color'] ?? '#b91c1c';
$qv_buy_text = $qvStyles['buy_now_btn_text_color'] ?? '#ffffff';
$qv_buy_hover_bg = $qvStyles['buy_now_hover_bg_color'] ?? '#991b1b';
$qv_buy_hover_text = $qvStyles['buy_now_hover_text_color'] ?? '#ffffff';
$qv_price_color = $qvStyles['price_color'] ?? '#1a3d32';
$qv_variant_bg = $qvStyles['variant_bg_color'] ?? '#154D35';
$qv_variant_text = $qvStyles['variant_text_color'] ?? '#ffffff';
$qv_qty_border = $qvStyles['qty_border_color'] ?? '#000000';
$qv_title_color = $qvStyles['title_color'] ?? '#111827';
$qv_desc_color = $qvStyles['desc_color'] ?? '#4b5563';
$qv_stock_color = $qvStyles['stock_color'] ?? '#1a3d32';
$qv_actions_color = $qvStyles['actions_color'] ?? '#6b7280';
$qv_policy_color = $qvStyles['policy_color'] ?? '#374151';
?>
    <style>
        footer.bg-white {
            background-color: <?php echo $footerBg; ?> !important;
            color: <?php echo $footerText; ?> !important;
            
        }
        footer .text-black,
        footer .text-gray-700,
        footer .text-gray-900,
        footer a {
             color: <?php echo $footerText; ?> !important;
        }
        /* Hover effect for links */
        footer a:hover,
        footer a:hover *,
        footer .group:hover *,
        footer .group:hover span,
        footer .group:hover i {
            color: <?php echo $footerHover; ?> !important;
            opacity: 1 !important;
        }
        
        /* Prevent H3 title color change on hover */
        footer .group:hover h3,
        footer .group:hover h3.nav-link {
            color: <?php echo $footerText; ?> !important;
        }

        
        /* Social icons hover */
        footer .footer-social-icon:hover {
            background-color: <?php echo $footerHover; ?> !important;
            border-color: <?php echo $footerHover; ?> !important;
        }
        footer .footer-social-icon:hover i {
            color: <?php echo $footerBg; ?> !important;
        }
        
        /* Cart Drawer Styles */
        <?php
        // Load Cart Styling (Consolidated)
        $cartStylingJson = $settingsObj->get('cart_page_styling', '');
        $cartStyling = !empty($cartStylingJson) ? json_decode($cartStylingJson, true) : [];

        // Helper function locally for cart drawer/page
        if (!function_exists('getCartStyle')) {
            function getCartStyle($key, $default, $settingsObj, $cartStyling) {
                if (isset($cartStyling[$key])) return $cartStyling[$key];
                return $settingsObj->get($key, $default);
            }
        }

        // Fetch Cart Drawer Settings
        $cd_bg_color = getCartStyle('cart_drawer_bg_color', '#ffffff', $settingsObj, $cartStyling);
        $cd_header_color = getCartStyle('cart_drawer_header_text_color', '#111827', $settingsObj, $cartStyling);
        $cd_header_hover = getCartStyle('cart_drawer_header_text_hover_color', '#3b82f6', $settingsObj, $cartStyling);
        $cd_price_color = getCartStyle('cart_drawer_price_color', '#1f2937', $settingsObj, $cartStyling);
        $cd_qty_color = getCartStyle('cart_drawer_qty_color', '#374151', $settingsObj, $cartStyling);
        $cd_trash_color = getCartStyle('cart_drawer_trash_color', '#ef4444', $settingsObj, $cartStyling);
        $cd_trash_hover = getCartStyle('cart_drawer_trash_hover_color', '#b91c1c', $settingsObj, $cartStyling);
        $cd_total_color = getCartStyle('cart_drawer_total_color', '#111827', $settingsObj, $cartStyling);
        $cd_divider_color = getCartStyle('cart_drawer_divider_color', '#e5e7eb', $settingsObj, $cartStyling);
        $cd_close_icon_color = getCartStyle('cart_drawer_close_icon_color', '#9ca3af', $settingsObj, $cartStyling);
        
        $cd_view_bg = getCartStyle('cart_drawer_view_btn_bg', '#3b82f6', $settingsObj, $cartStyling);
        $cd_view_text = getCartStyle('cart_drawer_view_btn_text', '#ffffff', $settingsObj, $cartStyling);
        $cd_view_hover_bg = getCartStyle('cart_drawer_view_btn_hover_bg', '#2563eb', $settingsObj, $cartStyling);
        $cd_view_hover_text = getCartStyle('cart_drawer_view_btn_hover_text', '#ffffff', $settingsObj, $cartStyling);
        
        $cd_checkout_bg = getCartStyle('cart_drawer_checkout_btn_bg', '#000000', $settingsObj, $cartStyling);
        $cd_checkout_text = getCartStyle('cart_drawer_checkout_btn_text', '#ffffff', $settingsObj, $cartStyling);
        $cd_checkout_hover_bg = getCartStyle('cart_drawer_checkout_btn_hover_bg', '#1f2937', $settingsObj, $cartStyling);
        $cd_checkout_hover_text = getCartStyle('cart_drawer_checkout_btn_hover_text', '#ffffff', $settingsObj, $cartStyling);
        ?>
        
        /* Cart Drawer Divider */
        #sideCart .border-b, #sideCart .border-t {
            border-color: <?php echo $cd_divider_color; ?> !important;
        }

        /* Cart Drawer Close Icon */
        #sideCart button i.fa-times, #sideCart .fa-times {
            color: <?php echo $cd_close_icon_color; ?> !important;
        }

        /* Cart Drawer Background */
        #sideCart {
             background-color: <?php echo $cd_bg_color; ?> !important;
        }

        /* Cart Drawer Header Text */
        #sideCart .side-cart-item h4 a {
            color: <?php echo $cd_header_color; ?> !important;
        }
        #sideCart .side-cart-item h4 a:hover {
            color: <?php echo $cd_header_hover; ?> !important;
        }
        
        /* Cart Drawer Price */
        #sideCart .side-cart-item p.text-gray-600,
        #sideCart .side-cart-item .text-right p.font-semibold {
            color: <?php echo $cd_price_color; ?> !important;
        }
        
        /* Cart Drawer Quantity */
        #sideCart .side-cart-item .border.rounded span {
            color: <?php echo $cd_qty_color; ?> !important;
        }
        
        /* Cart Drawer Trash Icon */
        #sideCart .side-cart-item button.text-red-500 {
            color: <?php echo $cd_trash_color; ?> !important;
        }
        #sideCart .side-cart-item button.text-red-500:hover {
            color: <?php echo $cd_trash_hover; ?> !important;
        }
        
        /* Cart Drawer Total */
        #sideCartFooter span.text-xl.font-bold, #sideCartFooter span.text-lg.font-semibold {
            color: <?php echo $cd_total_color; ?> !important;
        }
        
        /* Cart Drawer View Cart Button */
        #viewCartBtn {
            background-color: <?php echo $cd_view_bg; ?> !important;
            color: <?php echo $cd_view_text; ?> !important;
        }
        #viewCartBtn:hover {
            background-color: <?php echo $cd_view_hover_bg; ?> !important;
            color: <?php echo $cd_view_hover_text; ?> !important;
        }
        
        /* Cart Drawer Checkout Button */
        #checkoutBtn {
            background-color: <?php echo $cd_checkout_bg; ?> !important;
            color: <?php echo $cd_checkout_text; ?> !important;
        }
        #checkoutBtn:hover {
            background-color: <?php echo $cd_checkout_hover_bg; ?> !important;
            color: <?php echo $cd_checkout_hover_text; ?> !important;
        }

        /* Quick View Styles */
        #quickViewBackdrop {
            background-color: <?php echo $qv_overlay; ?> !important;
        }
        #quickViewPanel {
            background-color: <?php echo $qv_modal_bg; ?> !important;
        }
        #quickViewContent, #quickViewContent > div, #quickViewPanel div {
            background-color: transparent !important;
        }
        #qvPrice {
            color: <?php echo $qv_price_color; ?> !important;
        }
        #quickViewModal .qv-variant-btn.bg-\[\#154D35\] {
            background-color: <?php echo $qv_variant_bg; ?> !important;
            color: <?php echo $qv_variant_text; ?> !important;
            border-color: <?php echo $qv_variant_bg; ?> !important;
        }
        #qvQuantityContainer {
            border-color: <?php echo $qv_qty_border; ?> !important;
        }
        #qvAddToCartBtn {
            background-color: <?php echo $qv_atc_bg; ?> !important;
            color: <?php echo $qv_atc_text; ?> !important;
        }
        #qvAddToCartBtn:hover {
            background-color: <?php echo $qv_atc_hover_bg; ?> !important;
            color: <?php echo $qv_atc_hover_text; ?> !important;
        }
        #qvBuyNowBtn {
            background-color: <?php echo $qv_buy_bg; ?> !important;
            color: <?php echo $qv_buy_text; ?> !important;
        }
        #qvBuyNowBtn:hover {
            background-color: <?php echo $qv_buy_hover_bg; ?> !important;
            color: <?php echo $qv_buy_hover_text; ?> !important;
        }
        #qvTitle {
            color: <?php echo $qv_title_color; ?> !important;
        }
        .truncate-3-lines {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        #qvDesc {
            color: <?php echo $qv_desc_color; ?> !important;
        }
        #qvStockCountContainer, #qvStockCountContainer * {
            color: <?php echo $qv_stock_color; ?> !important;
        }
        #qvActionsContainer button, #qvActionsContainer button * {
            color: <?php echo $qv_actions_color; ?> !important;
        }
        #qvPolicyBox, #qvPolicyBox * {
            color: <?php echo $qv_policy_color; ?> !important;
        }
        #qvBuyNowBtn {
            background-color: <?php echo $qv_buy_bg; ?> !important;
            color: <?php echo $qv_buy_text; ?> !important;
        }
        #qvBuyNowBtn:hover {
            background-color: <?php echo $qv_buy_hover_bg; ?> !important;
            color: <?php echo $qv_buy_hover_text; ?> !important;
        }
    </style>

    <script>
    function toggleFooterAccordion(element) {
        if (window.innerWidth >= 768) return;
        
        const content = element.nextElementSibling;
        const icon = element.querySelector('i');
        
        if (!content || !icon) return;

        // Check if currently open
        if (content.style.maxHeight && content.style.maxHeight !== '0px') {
            // Close
            content.style.maxHeight = '0px';
            content.classList.remove('opacity-100');
            content.classList.add('opacity-0');
            
            icon.classList.remove('fa-minus');
            icon.classList.add('fa-plus');
            icon.style.transform = 'rotate(0deg)';
        } else {
            // Open
            content.style.maxHeight = content.scrollHeight + "px";
            content.classList.remove('opacity-0');
            content.classList.add('opacity-100');
            
            icon.classList.remove('fa-plus');
            icon.classList.add('fa-minus');
            icon.style.transform = 'rotate(180deg)';
        }
    }
    </script>
    <!-- Footer -->
    <footer class="bg-white text-black relative">
        <div class="container footer-block mx-auto px-4 pt-20">
            <div class="row flex flex-wrap -mx-4">
                <!-- About Us / Footer Info Column -->
                <div class="column w-full md:w-1/2 lg:w-1/3 px-4 mb-8 lg:mb-0">
                    <div class="mb-4">
                        <?php 
                        $logoType = $getFooterSetting('footer_logo_type', 'text');
                        $logoText = $getFooterSetting('footer_logo_text', 'CookPro');
                        $logoImage = $getFooterSetting('footer_logo_image', '');
                        
                        if ($logoType === 'image' && !empty($logoImage)) {
                            // Display Logo Image
                            echo '<a href="'.$baseUrl.'"><img src="'.getImageUrl($logoImage).'" alt="'.htmlspecialchars($logoText).'" class="h-[60px] object-contain"></a>';
                        } else {
                            // Display Logo Text
                            echo '<a href="'.$baseUrl.'" class="text-xl font-bold font-sans text-black nav-link">'.htmlspecialchars($logoText).'</a>';
                        }
                        ?>
                    </div>
                
                    <div class="text-gray-700 text-sm leading-relaxed mb-4"><?php echo $getFooterSetting('footer_description'); ?></div>
                    
                    <?php if(!empty($getFooterSetting('footer_learn_more_url'))): ?>
                    <a href="<?php echo htmlspecialchars($getFooterSetting('footer_learn_more_url')); ?>" class="text-black underline hover:no-underline transition text-sm mb-4 inline-block font-semibold">Learn more</a>
                    <?php endif; ?>
                    
                    <div class="space-y-2 text-black mt-4">
                        <?php if (!empty($getFooterSetting('footer_address'))): ?>
                        <a href="https://maps.google.com/?q=<?php echo urlencode($getFooterSetting('footer_address')); ?>" target="_blank" class="flex items-center text-sm text-gray-900 hover:text-black transition group">
                            <i class="fas fa-map-marker-alt w-5 text-sm group-hover:text-black"></i>
                            <span class="group-hover:underline"><?php echo htmlspecialchars($getFooterSetting('footer_address')); ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($getFooterSetting('footer_phone'))): ?>
                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $getFooterSetting('footer_phone'))); ?>" class="flex items-center text-sm text-gray-900 hover:text-black transition group">
                            <i class="fas fa-phone w-5 text-sm group-hover:text-black"></i>
                            <span class="group-hover:underline"><?php echo htmlspecialchars($getFooterSetting('footer_phone')); ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($getFooterSetting('footer_email'))): ?>
                        <a href="mailto:<?php echo htmlspecialchars($getFooterSetting('footer_email')); ?>" class="flex items-center text-sm text-gray-900 hover:text-black transition group">
                            <i class="fas fa-envelope w-5 text-sm group-hover:text-black"></i>
                            <span class="group-hover:underline"><?php echo htmlspecialchars($getFooterSetting('footer_email')); ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex space-x-3 mt-6">
                        <?php 
                        $socialJson = $getFooterSetting('footer_social_json', '[]');
                        $socialLinks = json_decode($socialJson, true) ?: [];
                        
                        // Fallback to legacy keys if JSON is empty (for backward compatibility during transition)
                        if(empty($socialLinks)) {
                            // ... previous logic removed for cleanliness, assuming user will save new settings.
                            // Or we can migrate on the fly. Let's stick to the new system.
                        }
                        
                        foreach ($socialLinks as $soc):
                            if (!empty($soc['url'])):
                        ?>
                        <a href="<?php echo htmlspecialchars($soc['url']); ?>" class="footer-social-icon relative w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center transition group">
                            <i class="<?php echo htmlspecialchars($soc['icon']); ?>"></i>
                        </a>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                
                <!-- Dynamic Footer Columns -->
                <?php 
                // We display up to 4 columns from the menu
                // If there are more, they will wrap or we can limit them.
                foreach ($footerColumns as $column): 
                    $colTitle = $column['label'];
                    $colItems = $column['children'] ?? [];
                    
                    // Special handing for "Follow Us" to show icons if needed?
                    // For now, standard list as requested by "change text" requirement.
                ?>
                <div class="column w-full md:w-1/2 lg:w-1/6 px-4 mb-6 lg:mb-0 border-b border-gray-100 md:border-none pb-4 md:pb-0 last:border-0">
                    <div class="flex items-center justify-between cursor-pointer md:cursor-default group" onclick="toggleFooterAccordion(this)">
                        <h3 class="text-lg font-sans text-black nav-link font-bold select-none"><?php echo htmlspecialchars($colTitle); ?></h3>
                        <span class="md:hidden text-gray-500 group-hover:text-black transition-colors">
                            <i class="fas fa-plus transition-transform duration-300"></i>
                        </span>
                    </div>
                
                    <div class="max-h-0 opacity-0 md:max-h-none md:opacity-100 md:block pt-4 footer-accordion-content overflow-hidden md:overflow-visible transition-all duration-500 ease-in-out">
                    <?php if (stripos($colTitle, 'Follow') !== false): // Basic detection for Follow Us ?>
                        <!-- Social Icons Logic -->
                        <div class="flex space-x-3">
                             <?php foreach ($colItems as $item): 
                                 $icon = 'link';
                                 $lbl = strtolower($item['label']);
                                 if(strpos($lbl,'face')!==false) $icon='facebook-f';
                                 elseif(strpos($lbl,'insta')!==false) $icon='instagram';
                                 elseif(strpos($lbl,'tube')!==false) $icon='youtube';
                                 elseif(strpos($lbl,'tik')!==false) $icon='tiktok';
                                 elseif(strpos($lbl,'twit')!==false || strpos($lbl,'x')!==false) $icon='twitter';
                                 elseif(strpos($lbl,'pin')!==false) $icon='pinterest-p';
                             ?>
                             <a href="<?php echo htmlspecialchars($item['url']); ?>" class="footer-social-icon relative group w-10 h-10 rounded-full border border-gray-300 bg-white flex items-center justify-center transition">
                                <i class="fab fa-<?php echo $icon; ?> text-base text-gray-700"></i>
                             </a>
                             <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Standard Links -->
                        <ul class="space-y-2 text-black">
                            <?php foreach ($colItems as $item) { renderFooterLinkRecursive($item, $baseUrl); } ?>
                        </ul>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Bottom Bar -->
            <div class="border-t border-gray-300 mt-8 pt-6 pb-6">
                <div class="flex flex-wrap md:flex-nowrap justify-between md:justify-between items-center gap-5 md:gap-8">
                    <!-- Left Section: Currency & Copyright -->
                    <div class="flex flex-wrap gap-5 md:gap-8 justify-center md:justify-start items-center">
                        <!-- Currency Selector -->
                        <div class="relative hidden">
                            <?php
                            $currencies = getCurrencies();
                            $selectedCurrency = $currencies[0] ?? ['code'=>'in','name'=>'India','currency_name'=>'INR','symbol'=>'₹','flag'=>'https://cdn.shopify.com/static/images/flags/in.svg'];
                            ?>
                            <button class="flex items-center gap-2 text-black hover:text-gray-600 transition cursor-pointer focus:outline-none whitespace-nowrap" id="footerCurrencySelector">
                                <span class="flex items-center gap-2">
                                    <span class="rounded-full border border-gray-300 overflow-hidden" style="width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;">
                                        <img src="<?php echo $selectedCurrency['flag']; ?>" alt="<?php echo $selectedCurrency['name']; ?>" id="footerSelectedFlagImg" class="w-full h-full object-cover" style="width: 20px; height: 20px;">
                                    </span>
                                    <span class="text-sm">
                                        <span class="text-gray-500" id="footerCountryCode"></span>
                                        <span id="footerSelectedCurrency" class="text-gray-700"><?php echo $selectedCurrency['name'] . ' (' . $selectedCurrency['currency_name'] . ' ' . $selectedCurrency['symbol'] . ')'; ?></span>
                                    </span>
                                </span>
                                <svg class="icon-down flex-shrink-0" width="10" height="6" style="margin-left: 4px;">
                                    <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <!-- Currency Dropdown -->
                            <div class="absolute left-0 bottom-full mb-3 bg-white text-black shadow-lg rounded-lg py-1 min-w-[240px] hidden z-50 border border-gray-200" id="footerCurrencyDropdown">
                                <?php foreach ($currencies as $curr): ?>
                                <a href="#" class="block px-4 py-2.5 hover:bg-gray-50 transition footer-currency-option" data-flag="<?php echo $curr['flag']; ?>" data-code="<?php echo $curr['code']; ?>" data-currency="<?php echo $curr['name'] . ' (' . $curr['currency_name'] . ' ' . $curr['symbol'] . ')'; ?>">
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
                        
                        <!-- Copyright -->
                        <div class="text-gray-700 text-sm">
                            <?php echo htmlspecialchars($getFooterSetting('footer_copyright', '© ' . date('Y') . ' CookPro store. All rights reserved.')); ?>
                        </div>
                    </div>
                    
                    <!-- Right Section: Payment Icons -->
                    <div class="flex flex-wrap gap-2 justify-center md:justify-end items-center">
                        <ul class="list-unstyled flex flex-wrap gap-2 justify-center md:justify-end items-center">
                            <?php 
                            $paymentIconsJson = $getFooterSetting('footer_payment_icons_json', '[]');
                            $paymentIcons = json_decode($paymentIconsJson, true) ?: [];
                            
                            foreach ($paymentIcons as $icon):
                                if (empty($icon['svg'])) continue;
                            ?>
                            <li class="inline-flex items-center" title="<?php echo htmlspecialchars($icon['name']); ?>">
                                <?php echo $icon['svg']; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Back to Top Button -->
        <button id="backToTop" class="fixed bottom-8 right-8 w-12 h-12 bg-black text-white rounded-full flex flex-col items-center justify-center hover:bg-gray-800 transition shadow-lg z-40 hidden" style="gap: 2px;">
            <i class="fas fa-chevron-up text-xs"></i>
        </button>
    </footer>

    <!-- Side Cart -->
    <div class="fixed right-0 top-0 h-full w-full md:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300" id="sideCart">
        <div class="flex flex-col h-full">
            <!-- Cart Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-heading font-bold">Shopping Cart</h2>
                <button class="text-gray-500 hover:text-gray-800" id="closeCart" data-aria-label="Close cart">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-6" id="cartItems">
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                    <p>Your cart is empty</p>
                </div>
            </div>
            
            <!-- Cart Footer -->
            <div class="border-t p-6" id="sideCartFooter">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold">Total:</span>
                    <span class="text-xl font-bold" id="cartTotal">₹0.00</span>
                </div>
                <a href="<?php echo url('cart'); ?>" id="viewCartBtn" class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-light hover:text-white transition mb-2">
                            View Cart
                        </a>
                <a href="<?php echo url('checkout'); ?>" id="checkoutBtn" class="block w-full bg-black text-white text-center py-3 rounded-lg hover:text-white hover:bg-gray-800 transition">
                    Checkout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Cart Overlay -->
    <div class="hidden fixed inset-0 bg-black bg-opacity-50 z-40" id="cartOverlay"></div>
    
    <!-- Remove from Cart Confirmation Modal -->
    <div id="removeConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center" style="display: none;">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 relative shadow-xl">
            <button onclick="closeRemoveConfirm()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <div class="flex flex-col items-center mb-6 pt-4">
                <img id="removeConfirmImage" src="" alt="Product" class="w-20 h-20 object-cover rounded-lg mb-4 border border-gray-200">
                <h3 id="removeConfirmName" class="text-base font-semibold text-center mb-4 text-gray-800"></h3>
                <p class="text-gray-600 text-center text-sm mb-6">Would you like to add this product in wishlist?</p>
            </div>
            
            <div class="flex space-x-3">
                <button onclick="confirmRemoveWithWishlist(this)" 
                        class="flex-1 bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-800 transition font-medium text-sm"
                        data-loading-text="Processing...">
                    Yes
                </button>
                <button onclick="confirmRemoveWithoutWishlist(this)" 
                        class="flex-1 bg-white text-black border-2 border-gray-300 px-6 py-3 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition font-medium text-sm"
                        data-loading-text="Removing...">
                    No
                </button>
            </div>
        </div>
    </div>
    
    <!-- Notification Modal -->
    <!-- <div id="notificationModal" class="hidden notification-modal-overlay">
        <div class="notification-modal">
            <div class="notification-modal-header">
                <div class="notification-modal-icon" id="notificationIcon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="notification-modal-title" id="notificationTitle">Success</h3>
            </div>
            <div class="notification-modal-body">
                <p class="notification-modal-message" id="notificationMessage"></p>
            </div>
            <div class="notification-modal-footer">
                <button class="notification-modal-btn primary" id="notificationOkBtn" onclick="closeNotificationModal()">OK</button>
            </div>
        </div>
    </div> -->
    
    
    <!-- Scripts -->
    <script src="<?php echo $baseUrl; ?>/assets/js/main6.js?v=2" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/cart19.js?v=1" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/product-cards7.js?v=2" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/wishlist10.js?v=3" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/notification1.js?v=2" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/quickview20.js?v=2" defer></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/add-to-cart3.js?v=2" defer></script>
    
    <!-- Remove from Cart Confirmation Script -->
    <script>
    // Remove confirmation modal variables
    let pendingRemoveProductId = null;
    let pendingRemoveProductName = null;
    let pendingRemoveProductImage = null;

    // Show remove confirmation modal
    function showRemoveConfirm(productId, productName, productImage) {
        pendingRemoveProductId = productId;
        pendingRemoveProductName = productName;
        pendingRemoveProductImage = productImage;
        
        const modal = document.getElementById('removeConfirmModal');
        const imageEl = document.getElementById('removeConfirmImage');
        const nameEl = document.getElementById('removeConfirmName');
        
        if (modal && imageEl && nameEl) {
            imageEl.src = productImage || '<?php echo $baseUrl; ?>/assets/images/default-avatar.svg';
            nameEl.textContent = productName;
            
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    // Close remove confirmation modal
    function closeRemoveConfirm() {
        const modal = document.getElementById('removeConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        pendingRemoveProductId = null;
        pendingRemoveProductName = null;
        pendingRemoveProductImage = null;
    }

    // Confirm remove with wishlist (Yes button)
    async function confirmRemoveWithWishlist(btn) {
        if (!pendingRemoveProductId) return;
        
        const productId = pendingRemoveProductId;
        if (btn) setBtnLoading(btn, true);
        // closeRemoveConfirm(); // Moved to after process completes
        
        try {
            // First, add to wishlist
            const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
            const wishlistResponse = await fetch(baseUrl + '/api/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId
                })
            });
            
            const wishlistResult = await wishlistResponse.json();
            
            // Update wishlist count in header
            if (wishlistResult.success && typeof refreshWishlist === 'function') {
                await refreshWishlist();
            }
            
            // Then remove from cart
            if (typeof removeFromCart === 'function') {
                await removeFromCart(productId, btn);
                closeRemoveConfirm();
            }
        } catch (error) {
            console.error('Error adding to wishlist:', error);
            // Still remove from cart even if wishlist add fails
            if (typeof removeFromCart === 'function') {
                await removeFromCart(productId, btn);
                closeRemoveConfirm();
            }
        } finally {
            if (btn) setBtnLoading(btn, false);
        }
    }

    // Confirm remove without wishlist (No button)
    async function confirmRemoveWithoutWishlist(btn) {
        if (!pendingRemoveProductId) return;
        
        const productId = pendingRemoveProductId;
        if (btn) setBtnLoading(btn, true);
        
        // Just remove from cart
        if (typeof removeFromCart === 'function') {
            await removeFromCart(productId, btn);
            closeRemoveConfirm();
            if (btn) setBtnLoading(btn, false);
        }
    }

    // Make functions globally available
    window.showRemoveConfirm = showRemoveConfirm;
    window.closeRemoveConfirm = closeRemoveConfirm;
    window.confirmRemoveWithWishlist = confirmRemoveWithWishlist;
    window.confirmRemoveWithoutWishlist = confirmRemoveWithoutWishlist;
    </script>

<!-- Ask a Question Modal (Shared) -->
<div id="askQuestionModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" aria-hidden="true" onclick="toggleAskQuestionModal(false)"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
            <div class="bg-white px-6 py-8 sm:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900" id="modal-title">Ask a Question</h3>
                    <button onclick="toggleAskQuestionModal(false)" class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg" id="aq_product_info">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Question about: <span class="font-bold underline" id="aq_product_name_display">Product Name</span>
                            </p>
                        </div>
                    </div>
                </div>
                <form id="askQuestionForm" class="space-y-5">
                    <input type="hidden" id="aq_subject" name="subject" value="">
                    <div>
                        <label for="aq_name" class="block text-sm font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Your Name</label>
                        <input type="text" id="aq_name" name="name" value="<?php echo $customer ? htmlspecialchars($customer['name']) : ''; ?>" required 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary border-transparent transition-all outline-none" placeholder="Enter your name">
                    </div>
                    <div>
                        <label for="aq_email" class="block text-sm font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Email Address</label>
                        <input type="email" id="aq_email" name="email" value="<?php echo $customer ? htmlspecialchars($customer['email']) : ''; ?>" required 
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary border-transparent transition-all outline-none" placeholder="Enter your email">
                    </div>
                    <div>
                        <label for="aq_message" class="block text-sm font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Your Question</label>
                        <textarea id="aq_message" name="message" rows="4" required 
                                  class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary border-transparent transition-all outline-none" placeholder="Tell us what you'd like to know..."></textarea>
                    </div>
                    <div id="aq_status" class="hidden text-sm p-4 rounded-xl border font-medium"></div>
                    <div class="pt-4">
                        <button type="submit" id="aq_submit" class="w-full bg-black text-white py-4 rounded-xl font-bold uppercase tracking-widest hover:bg-gray-800 active:scale-[0.98] transition-all flex items-center justify-center gap-3 shadow-lg shadow-black/10">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Message</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAskQuestionModal(show, productName = '') {
    const modal = document.getElementById('askQuestionModal');
    if (show) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Update product name in modal
        const nameDisplay = document.getElementById('aq_product_name_display');
        const subjectInput = document.getElementById('aq_subject');
        const productInfo = document.getElementById('aq_product_info');
        
        if (productName) {
            nameDisplay.textContent = productName;
            subjectInput.value = "To know about this product: " + productName;
            productInfo.classList.remove('hidden');
        } else {
            subjectInput.value = "General Inquiry";
            productInfo.classList.add('hidden');
        }

        // Reset status
        const statusDiv = document.getElementById('aq_status');
        statusDiv.classList.add('hidden');
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>

<!-- Cookie Consent Popup -->
<div id="cookieConsentBanner" class="hidden fixed bottom-6 left-6 right-6 md:left-auto md:max-w-md bg-white/95 backdrop-blur-md p-6 rounded-2xl shadow-2xl z-[100] transform translate-y-20 opacity-0 pointer-events-none transition-all duration-700 border border-gray-100 flex flex-col gap-4">
    <div class="flex items-start gap-4">
        <div class="bg-black text-white p-3 rounded-xl flex-shrink-0">
            <i class="fas fa-cookie-bite text-xl"></i>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Speed up your experience?</h3>
            <p class="text-sm text-gray-600">
                Enable local caching for an <b>instant</b> loading experience.
            </p>
        </div>
    </div>
    <div class="flex gap-3">
        <button onclick="handleCookieConsent('allowed')" class="flex-1 bg-black text-white py-2.5 rounded-lg font-bold text-sm hover:bg-gray-800 transition active:scale-95 shadow-lg shadow-black/10">
            Allow Cookies
        </button>
        <button onclick="handleCookieConsent('rejected')" class="text-gray-500 hover:text-black transition text-sm font-medium px-4 py-2 border border-gray-200 rounded-lg">
            Maybe later
        </button>
    </div>
</div>

<script>
// Cookie Consent & Service Worker Logic
function handleCookieConsent(choice) {
    const banner = document.getElementById('cookieConsentBanner');
    if (choice === 'allowed') {
        const now = new Date().getTime();
        const expiry = now + (7 * 24 * 60 * 60 * 1000); // 7 days
        const consentData = {
            value: 'allowed',
            expires: expiry
        };
        localStorage.setItem('cookieConsent', JSON.stringify(consentData));
        registerServiceWorker();
    } else {
        // "Maybe later" - Hide only for current browser session
        sessionStorage.setItem('cookieConsent_dismissed', 'true');
    }

    if (banner) {
        banner.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
        banner.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
        setTimeout(() => banner.classList.add('hidden'), 700);
    }
}

function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister();
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const consentRaw = localStorage.getItem('cookieConsent');
    let isAllowed = false;
    
    if (consentRaw) {
        try {
            const data = JSON.parse(consentRaw);
            if (data && data.value === 'allowed' && data.expires > new Date().getTime()) {
                isAllowed = true;
            } else {
                localStorage.removeItem('cookieConsent');
            }
        } catch (e) {
            if (consentRaw === 'allowed') {
                isAllowed = true;
            }
        }
    }

    const sessionDismissed = sessionStorage.getItem('cookieConsent_dismissed');
    const banner = document.getElementById('cookieConsentBanner');

    if (isAllowed) {
        registerServiceWorker();
    } else if (!sessionDismissed) {
        // Show banner only if no valid consent exists AND it wasn't dismissed this session
        setTimeout(() => {
            if (banner) {
                banner.classList.remove('hidden');
                // Force a reflow to ensure transition works
                banner.offsetHeight; 
                banner.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
                banner.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
            }
        }, 2000);
    }
});
</script>
<style>
#cookieConsentBanner {
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.2);
}
</style>
<script>
document.getElementById('askQuestionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('aq_submit');
    const statusDiv = document.getElementById('aq_status');
    const origBtnContent = submitBtn.innerHTML;
    
    // UI Loading State
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Sending...</span>';
    statusDiv.classList.add('hidden');
    
    const formData = {
        name: document.getElementById('aq_name').value,
        email: document.getElementById('aq_email').value,
        subject: document.getElementById('aq_subject').value,
        message: document.getElementById('aq_message').value
    };
    
    try {
        const response = await fetch('<?php echo $baseUrl; ?>/api/support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            statusDiv.textContent = data.message;
            statusDiv.className = 'text-sm p-4 rounded-xl border font-medium bg-green-50 border-green-200 text-green-700';
            statusDiv.classList.remove('hidden');
            this.reset();
            
            // Auto close after success
            setTimeout(() => {
                toggleAskQuestionModal(false);
            }, 3000);
        } else {
            throw new Error(data.message || 'Failed to send message');
        }
    } catch (error) {
        statusDiv.textContent = error.message;
        statusDiv.className = 'text-sm p-4 rounded-xl border font-medium bg-red-50 border-red-200 text-red-700';
        statusDiv.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnContent;
    }
});
</script>

<?php require_once __DIR__ . '/development_popup.php'; ?>
</body>
</html>
<style>
    
    @media (max-width: 768px) {
        .footer-block {
            padding-top: 2rem !important;
        }
    }
</style>