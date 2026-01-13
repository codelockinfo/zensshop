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
$db = Database::getInstance();

// Fetch Settings
$settingsRows = $db->fetchAll("SELECT * FROM site_settings");
$footerSettings = [];
foreach ($settingsRows as $row) {
    $footerSettings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Footer Menus
// We now use a SINGLE "Footer Menu" (footer_main)
// Top level items = Columns
// Children = Links
$footerMenuData = $db->fetchOne("SELECT id FROM menus WHERE location = 'footer_main'");
$footerColumns = [];
if ($footerMenuData) {
    $rawItems = $db->fetchAll("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC", [$footerMenuData['id']]);
    $footerColumns = buildMenuTree($rawItems);
}

function renderFooterLinkRecursive($item, $baseUrl) {
    $url = htmlspecialchars(str_replace('SITE_URL', $baseUrl, $item['url']));
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
?>
    <!-- Footer -->
    <footer class="bg-white text-black relative">
        <div class="container mx-auto px-4 pt-20">
            <div class="row flex flex-wrap -mx-4">
                <!-- About Us / Footer Info Column -->
                <div class="column w-full md:w-1/2 lg:w-1/3 px-4 mb-8 lg:mb-0">
                    <!-- Logo Section -->
                    <div class="mb-4">
                        <?php 
                        $logoType = $footerSettings['footer_logo_type'] ?? 'text';
                        $logoText = $footerSettings['footer_logo_text'] ?? 'ZENSSHOP';
                        $logoImage = $footerSettings['footer_logo_image'] ?? '';
                        
                        if ($logoType === 'image' && !empty($logoImage)) {
                            // Display Logo Image
                            echo '<a href="'.$baseUrl.'"><img src="'.getImageUrl($logoImage).'" alt="'.htmlspecialchars($logoText).'" class="h-10 object-contain"></a>';
                        } else {
                            // Display Logo Text
                            echo '<a href="'.$baseUrl.'" class="text-xl font-bold font-sans text-black nav-link uppercase">'.htmlspecialchars($logoText).'</a>';
                        }
                        ?>
                    </div>
                
                    <p class="text-gray-700 text-sm leading-relaxed mb-4"><?php echo htmlspecialchars($footerSettings['footer_description'] ?? ''); ?></p>
                    
                    <?php if(!empty($footerSettings['footer_learn_more_url'])): ?>
                    <a href="<?php echo htmlspecialchars($footerSettings['footer_learn_more_url']); ?>" class="text-black underline hover:no-underline transition text-sm mb-4 inline-block font-semibold">Learn more</a>
                    <?php endif; ?>
                    
                    <div class="space-y-2 text-black mt-4">
                        <?php if (!empty($footerSettings['footer_address'])): ?>
                        <p class="flex items-center text-sm text-gray-900"><i class="fas fa-map-marker-alt w-5 text-sm"></i><?php echo htmlspecialchars($footerSettings['footer_address']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($footerSettings['footer_phone'])): ?>
                        <p class="flex items-center text-sm text-gray-900"><i class="fas fa-phone w-5 text-sm"></i><?php echo htmlspecialchars($footerSettings['footer_phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($footerSettings['footer_email'])): ?>
                        <p class="flex items-center text-sm text-gray-900"><i class="fas fa-envelope w-5 text-sm"></i><?php echo htmlspecialchars($footerSettings['footer_email']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Global Social Icons -->
                    <div class="flex space-x-3 mt-6">
                        <?php 
                        // Decode JSON if not already decoded (footerSettings usually raw string from DB)
                        $socialJson = $footerSettings['footer_social_json'] ?? '[]';
                        $socialLinks = json_decode($socialJson, true) ?: [];
                        
                        // Fallback to legacy keys if JSON is empty (for backward compatibility during transition)
                        if(empty($socialLinks)) {
                            // ... previous logic removed for cleanliness, assuming user will save new settings.
                            // Or we can migrate on the fly. Let's stick to the new system.
                        }
                        
                        foreach ($socialLinks as $soc):
                            if (!empty($soc['url'])):
                        ?>
                        <a href="<?php echo htmlspecialchars($soc['url']); ?>" class="relative w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-black hover:text-white transition group">
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
                <div class="column w-full md:w-1/2 lg:w-1/6 px-4 mb-8 lg:mb-0">
                    <h3 class="text-lg font-sans mb-4 text-black nav-link"><?php echo htmlspecialchars($colTitle); ?></h3>
                    
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
                             <a href="<?php echo htmlspecialchars($item['url']); ?>" class="footer-social-icon relative group w-10 h-10 rounded-full border border-gray-300 bg-white flex items-center justify-center hover:text-white transition">
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
                <?php endforeach; ?>
            </div>
            
            <!-- Bottom Bar -->
            <div class="border-t border-gray-300 mt-8 pt-6 pb-6">
                <div class="flex flex-wrap md:flex-nowrap justify-between md:justify-between items-center gap-5 md:gap-8">
                    <!-- Left Section: Currency & Copyright -->
                    <div class="flex flex-wrap gap-5 md:gap-8 justify-center md:justify-start items-center">
                        <!-- Currency Selector -->
                        <div class="relative">
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
                            <?php echo htmlspecialchars($footerSettings['footer_copyright'] ?? '© ' . date('Y') . ' Milano store. All rights reserved.'); ?>
                        </div>
                    </div>
                    
                    <!-- Right Section: Payment Icons -->
                    <div class="flex flex-wrap gap-2 justify-center md:justify-end items-center">
                        <ul class="list-unstyled flex flex-wrap gap-2 justify-center md:justify-end items-center">
                            <?php if (($footerSettings['footer_show_visa'] ?? '1') == '1'): ?>
                            <li class="inline-flex items-center">
                                <svg class="icon icon--full-color" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" role="img" width="38" height="24" aria-labelledby="pi-visa"><title id="pi-visa">Visa</title><path opacity=".07" d="M35 0H3C1.3 0 0 1.3 0 3v18c0 1.7 1.4 3 3 3h32c1.7 0 3-1.3 3-3V3c0-1.7-1.4-3-3-3z"></path><path fill="#fff" d="M35 1c1.1 0 2 .9 2 2v18c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V3c0-1.1.9-2 2-2h32"></path><path d="M28.3 10.1H28c-.4 1-.7 1.5-1 3h1.9c-.3-1.5-.3-2.2-.6-3zm2.9 5.9h-1.7c-.1 0-.1 0-.2-.1l-.2-.9-.1-.2h-2.4c-.1 0-.2 0-.2.2l-.3.9c0 .1-.1.1-.1.1h-2.1l.2-.5L27 8.7c0-.5.3-.7.8-.7h1.5c.1 0 .2 0 .2.2l1.4 6.5c.1.4.2.7.2 1.1.1.1.1.1.2zm-13.4-.3l.4-1.8c.1 0 .2.1.2.1.7.3 1.4.5 2.1.4.2 0 .5-.1.7-.2.5-.2.5-.7.1-1.1-.2-.2-.5-.3-.8-.5-.4-.2-.8-.4-1.1-.7-1.2-1-.8-2.4-.1-3.1.6-.4.9-.8 1.7-.8 1.2 0 2.5 0 3.1.2h.1c-.1.6-.2 1.1-.4 1.7-.5-.2-1-.4-1.5-.4-.3 0-.6 0-.9.1-.2 0-.3.1-.4.2-.2.2-.2.5 0 .7l.5.4c.4.2.8.4 1.1.6.5.3 1 .8 1.1 1.4.2.9-.1 1.7-.9 2.3-.5.4-.7.6-1.4.6-1.4 0-2.5.1-3.4-.2-.1.2-.1.2-.1zm-3.5.3c.1-.7.1-.7.2-1 .5-2.2 1-4.5 1.4-6.7.1-.2.1-.3.3-.3H18c-.2 1.2-.4 2.1-.7 3.2-.3 1.5-.6 3-1 4.5 0 .2-.1.2-.3.2M5 8.2c0-.1.2-.2.3-.2h3.4c.5 0 .9.3 1 .8l.9 4.4c0 .1 0 .1.1.2 0-.1.1-.1.1-.1l2.1-5.1c-.1-.1 0-.2.1-.2h2.1c0 .1 0 .1-.1.2l-3.1 7.3c-.1.2-.1.3-.2.4-.1.1-.3 0-.5 0H9.7c-.1 0-.2 0-.2-.2L7.9 9.5c-.2-.5-.5-.5-.9-.6-.6-.3-1.7-.5-1.9-.5L5 8.2z" fill="#142688"></path></svg>
                            </li>
                            <?php endif; ?>

                            <?php if (($footerSettings['footer_show_mastercard'] ?? '1') == '1'): ?>
                            <li class="inline-flex items-center">
                                <svg class="icon icon--full-color" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" role="img" width="38" height="24" aria-labelledby="pi-master"><title id="pi-master">Mastercard</title><path opacity=".07" d="M35 0H3C1.3 0 0 1.3 0 3v18c0 1.7 1.4 3 3 3h32c1.7 0 3-1.3 3-3V3c0-1.7-1.4-3-3-3z"></path><path fill="#fff" d="M35 1c1.1 0 2 .9 2 2v18c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V3c0-1.1.9-2 2-2h32"></path><circle fill="#EB001B" cx="15" cy="12" r="7"></circle><circle fill="#F79E1B" cx="23" cy="12" r="7"></circle><path fill="#FF5F00" d="M22 12c0-2.4-1.2-4.5-3-5.7-1.8 1.3-3 3.4-3 5.7s1.2 4.5 3 5.7c1.8-1.2 3-3.3 3-5.7z"></path></svg>
                            </li>
                            <?php endif; ?>

                            <?php if (($footerSettings['footer_show_amex'] ?? '1') == '1'): ?>
                            <li class="inline-flex items-center">
                                <svg class="icon icon--full-color" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="pi-american_express" viewBox="0 0 38 24" width="38" height="24"><title id="pi-american_express">American Express</title><path fill="#000" d="M35 0H3C1.3 0 0 1.3 0 3v18c0 1.7 1.4 3 3 3h32c1.7 0 3-1.3 3-3V3c0-1.7-1.4-3-3-3Z" opacity=".07"></path><path fill="#006FCF" d="M35 1c1.1 0 2 .9 2 2v18c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V3c0-1.1.9-2 2-2h32Z"></path><path fill="#FFF" d="M22.012 19.936v-8.421L37 11.528v2.326l-1.732 1.852L37 17.573v2.375h-2.766l-1.47-1.622-1.46 1.628-9.292-.02Z"></path><path fill="#006FCF" d="M23.013 19.012v-6.57h5.572v1.513h-3.768v1.028h3.678v1.488h-3.678v1.01h3.768v1.531h-5.572Z"></path><path fill="#006FCF" d="m28.557 19.012 3.083-3.289-3.083-3.282h2.386l1.884 2.083 1.89-2.082H37v.051l-3.017 3.23L37 18.92v.093h-2.307l-1.917-2.103-1.898 2.104h-2.321Z"></path><path fill="#FFF" d="M22.71 4.04h3.614l1.269 2.881V4.04h4.46l.77 2.159.771-2.159H37v8.421H19l3.71-8.421Z"></path><path fill="#006FCF" d="m23.395 4.955-2.916 6.566h2l.55-1.315h2.98l.55 1.315h2.05l-2.904-6.566h-2.31Zm.25 3.777.875-2.09.873 2.09h-1.748Z"></path><path fill="#006FCF" d="M28.581 11.52V4.953l2.811.01L32.84 9l1.456-4.046H37v6.565l-1.74.016v-4.51l-1.644 4.494h-1.59L30.35 7.01v4.51h-1.768Z"></path></svg>
                            </li>
                            <?php endif; ?>

                            <?php if (($footerSettings['footer_show_paypal'] ?? '1') == '1'): ?>
                            <li class="inline-flex items-center">
                                <svg class="icon icon--full-color" viewBox="0 0 38 24" width="38" height="24" role="img" aria-labelledby="pi-paypal"><title id="pi-paypal">PayPal</title><path opacity=".07" d="M35 0H3C1.3 0 0 1.3 0 3v18c0 1.7 1.4 3 3 3h32c1.7 0 3-1.3 3-3V3c0-1.7-1.4-3-3-3z"></path><path fill="#fff" d="M35 1c1.1 0 2 .9 2 2v18c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V3c0-1.1.9-2 2-2h32"></path><path fill="#003087" d="M23.9 8.3c.2-1 0-1.7-.6-2.3-.6-.7-1.7-1-3.1-1h-4.1c-.3 0-.5.2-.6.5L14 15.6c0 .2.1.4.3.4H17l.4-3.4 1.8-2.2 4.7-2.1z"></path><path fill="#3086C8" d="M23.9 8.3l-.2.2c-.5 2.8-2.2 3.8-4.6 3.8H18c-.3 0-.5.2-.6.5l-.6 3.9-.2 1c0 .2.1.4.3.4H19c.3 0 .5-.2.5-.4v-.1l.4-2.4v-.1c0-.2.3-.4.5-.4h.3c2.1 0 3.7-.8 4.1-3.2.2-1 .1-1.8-.4-2.4-.1-.5-.3-.7-.5-.8z"></path><path fill="#012169" d="M23.3 8.1c-.1-.1-.2-.1-.3-.1-.1 0-.2 0-.3-.1-.3-.1-.7-.1-1.1-.1h-3c-.1 0-.2 0-.2.1-.2.1-.3.2-.3.4l-.7 4.4v.1c0-.3.3-.5.6-.5h1.3c2.5 0 4.1-1 4.6-3.8v-.2c-.1-.1-.3-.2-.5-.2h-.1z"></path></svg>
                            </li>
                            <?php endif; ?>

                            <?php if (($footerSettings['footer_show_discover'] ?? '1') == '1'): ?>
                            <li class="inline-flex items-center">
                                <svg class="icon icon--full-color" viewBox="0 0 38 24" width="38" height="24" role="img" aria-labelledby="pi-discover" fill="none" xmlns="http://www.w3.org/2000/svg"><title id="pi-discover">Discover</title><path fill="#000" opacity=".07" d="M35 0H3C1.3 0 0 1.3 0 3v18c0 1.7 1.4 3 3 3h32c1.7 0 3-1.3 3-3V3c0-1.7-1.4-3-3-3z"></path><path d="M35 1c1.1 0 2 .9 2 2v18c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V3c0-1.1.9-2 2-2h32z" fill="#fff"></path><path d="M3.57 7.16H2v5.5h1.57c.83 0 1.43-.2 1.96-.63.63-.52 1-1.3 1-2.11-.01-1.63-1.22-2.76-2.96-2.76zm1.26 4.14c-.34.3-.77.44-1.47.44h-.29V8.1h.29c.69 0 1.11.12 1.47.44.37.33.59.84.59 1.37 0 .53-.22 1.06-.59 1.39zm2.19-4.14h1.07v5.5H7.02v-5.5zm3.69 2.11c-.64-.24-.83-.4-.83-.69 0-.35.34-.61.8-.61.32 0 .59.13.86.45l.56-.73c-.46-.4-1.01-.61-1.62-.61-.97 0-1.72.68-1.72 1.58 0 .76.35 1.15 1.35 1.51.42.15.63.25.74.31.21.14.32.34.32.57 0 .45-.35.78-.83.78-.51 0-.92-.26-1.17-.73l-.69.67c.49.73 1.09 1.05 1.9 1.05 1.11 0 1.9-.74 1.9-1.81.02-.89-.35-1.29-1.57-1.74zm1.92.65c0 1.62 1.27 2.87 2.9 2.87.46 0 .86-.09 1.34-.32v-1.26c-.43.43-.81.6-1.29.6-1.08 0-1.85-.78-1.85-1.9 0-1.06.79-1.89 1.8-1.89.51 0 .9.18 1.34.62V7.38c-.47-.24-.86-.34-1.32-.34-1.61 0-2.92 1.28-2.92 2.88zm12.76.94l-1.47-3.7h-1.17l2.33 5.64h.58l2.37-5.64h-1.16l-1.48 3.7zm3.13 1.8h3.04v-.93h-1.97v-1.48h1.9v-.93h-1.9V8.1h1.97v-.94h-3.04v5.5zm7.29-3.87c0-1.03-.71-1.62-1.95-1.62h-1.59v5.5h1.07v-2.21h.14l1.48 2.21h1.32l-1.73-2.32c.81-.17 1.26-.72 1.26-1.56zm-2.16.91h-.31V8.03h.33c.67 0 1.03.28 1.03.82 0 .55-.36.85-1.05.85z" fill="#231F20"></path><path d="M20.16 12.86a2.931 2.931 0 100-5.862 2.931 2.931 0 000 5.862z" fill="url(#pi-paint0_linear)"></path><path opacity=".65" d="M20.16 12.86a2.931 2.931 0 100-5.862 2.931 2.931 0 000 5.862z" fill="url(#pi-paint1_linear)"></path><path d="M36.57 7.506c0-.1-.07-.15-.18-.15h-.16v.48h.12v-.19l.14.19h.14l-.16-.2c.06-.01.1-.06.1-.13zm-.2.07h-.02v-.13h.02c.06 0 .09.02.09.06 0 .05-.03.07-.09.07z" fill="#231F20"></path><path d="M36.41 7.176c-.23 0-.42.19-.42.42 0 .23.19.42.42.42.23 0 .42-.19.42-.42 0-.23-.19-.42-.42-.42zm0 .77c-.18 0-.34-.15-.34-.35 0-.19.15-.35.34-.35.18 0 .33.16.33.35 0 .19-.15.35-.33.35z" fill="#231F20"></path><path d="M37 12.984S27.09 19.873 8.976 23h26.023a2 2 0 002-1.984l.024-3.02L37 12.985z" fill="#F48120"></path><defs><linearGradient id="pi-paint0_linear" x1="21.657" y1="12.275" x2="19.632" y2="9.104" gradientUnits="userSpaceOnUse"><stop stop-color="#F89F20"></stop><stop offset=".25" stop-color="#F79A20"></stop><stop offset=".533" stop-color="#F68D20"></stop><stop offset=".62" stop-color="#F58720"></stop><stop offset=".723" stop-color="#F48120"></stop><stop offset="1" stop-color="#F37521"></stop></linearGradient><linearGradient id="pi-paint1_linear" x1="21.338" y1="12.232" x2="18.378" y2="6.446" gradientUnits="userSpaceOnUse"><stop stop-color="#F58720"></stop><stop offset=".359" stop-color="#E16F27"></stop><stop offset=".703" stop-color="#D4602C"></stop><stop offset=".982" stop-color="#D05B2E"></stop></linearGradient></defs></svg>
                            </li>
                            <?php endif; ?>
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
                <button class="text-gray-500 hover:text-gray-800" id="closeCart">
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
            <div class="border-t p-6">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold">Total:</span>
                    <span class="text-xl font-bold" id="cartTotal">₹0.00</span>
                </div>
                <a href="<?php echo url('cart.php'); ?>" class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-dark hover:text-white transition mb-2">
                            View Cart
                        </a>
                <a href="<?php echo url('checkout'); ?>" class="block w-full bg-black text-white text-center py-3 rounded-lg hover:text-white hover:bg-gray-800 transition">
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
                <button onclick="confirmRemoveWithWishlist()" 
                        class="flex-1 bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-800 transition font-medium text-sm">
                    Yes
                </button>
                <button onclick="confirmRemoveWithoutWishlist()" 
                        class="flex-1 bg-white text-black border-2 border-gray-300 px-6 py-3 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition font-medium text-sm">
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
    <script src="<?php echo $baseUrl; ?>/assets/js/main.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/cart2.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/product-cards.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/wishlist.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/notification.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/add-to-cart1.js"></script>
    
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
    async function confirmRemoveWithWishlist() {
        if (!pendingRemoveProductId) return;
        
        const productId = pendingRemoveProductId;
        closeRemoveConfirm();
        
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
                await removeFromCart(productId);
            }
        } catch (error) {
            console.error('Error adding to wishlist:', error);
            // Still remove from cart even if wishlist add fails
            if (typeof removeFromCart === 'function') {
                await removeFromCart(productId);
            }
        }
    }

    // Confirm remove without wishlist (No button)
    async function confirmRemoveWithoutWishlist() {
        if (!pendingRemoveProductId) return;
        
        const productId = pendingRemoveProductId;
        closeRemoveConfirm();
        
        // Just remove from cart
        if (typeof removeFromCart === 'function') {
            await removeFromCart(productId);
        }
    }

    // Make functions globally available
    window.showRemoveConfirm = showRemoveConfirm;
    window.closeRemoveConfirm = closeRemoveConfirm;
    window.confirmRemoveWithWishlist = confirmRemoveWithWishlist;
    window.confirmRemoveWithoutWishlist = confirmRemoveWithoutWishlist;
    </script>
</body>
</html>
