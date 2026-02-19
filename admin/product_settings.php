<?php
ob_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$settingsObj = new Settings();
$baseUrl = getBaseUrl();
$success = '';

// Fetch Current Settings
$stylesJson = $settingsObj->get('product_page_styles', '[]');
$styles = json_decode($stylesJson, true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pageStyles = [
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff',
            'info_box_bg_color' => $_POST['info_box_bg_color'] ?? '#f9fafb',
            'info_box_text_color' => $_POST['info_box_text_color'] ?? '#374151',
            'sale_price_color' => $_POST['sale_price_color'] ?? '#1a3d32',
            'reg_price_color' => $_POST['reg_price_color'] ?? '#9ca3af',
            'variant_bg_color' => $_POST['variant_bg_color'] ?? '#1a3d32',
            'variant_text_color' => $_POST['variant_text_color'] ?? '#ffffff',
            'atc_btn_color' => $_POST['atc_btn_color'] ?? '#000000',
            'atc_btn_text_color' => $_POST['atc_btn_text_color'] ?? '#ffffff',
            'buy_now_btn_color' => $_POST['buy_now_btn_color'] ?? '#b91c1c',
            'buy_now_btn_text_color' => $_POST['buy_now_btn_text_color'] ?? '#ffffff',
            'action_links_color' => $_POST['action_links_color'] ?? '#4b5563',
            'border_color' => $_POST['border_color'] ?? '#e5e7eb',
            'divider_color' => $_POST['divider_color'] ?? '#e5e7eb',
            'in_stock_color' => $_POST['in_stock_color'] ?? '#1a3d32',
            'out_stock_color' => $_POST['out_stock_color'] ?? '#b91c1c',
            'atc_hover_bg_color' => $_POST['atc_hover_bg_color'] ?? '#000000',
            'atc_hover_text_color' => $_POST['atc_hover_text_color'] ?? '#ffffff',
            'buy_now_hover_bg_color' => $_POST['buy_now_hover_bg_color'] ?? '#991b1b',
            'buy_now_hover_text_color' => $_POST['buy_now_hover_text_color'] ?? '#ffffff',
            
            // Related Products Section
            'show_related' => isset($_POST['show_related']) ? '1' : '0',
            'related_title' => $_POST['related_title'] ?? 'People Also Bought',
            'related_subtitle' => $_POST['related_subtitle'] ?? "Here's some of our most similar products people are buying. Click to discover trending style.",
            
            // Recently Viewed Section
            'show_recent' => isset($_POST['show_recent']) ? '1' : '0',
            'recent_title' => $_POST['recent_title'] ?? 'Recently Viewed',
            'recent_subtitle' => $_POST['recent_subtitle'] ?? "Explore your recently viewed items, blending quality and style for a refined living experience.",
        ];
        
        // Merge with existing to preserve 'quickview' sub-key
        $updatedStyles = array_merge($styles, $pageStyles);
        
        $settingsObj->set('product_page_styles', json_encode($updatedStyles), 'product');
        
        $_SESSION['flash_success'] = "Product page settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/product_settings.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check for success message
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Defaults
$s_page_bg_color = $styles['page_bg_color'] ?? '#ffffff';
$s_info_box_bg_color = $styles['info_box_bg_color'] ?? '#f9fafb';
$s_info_box_text_color = $styles['info_box_text_color'] ?? '#374151';
$s_sale_price_color = $styles['sale_price_color'] ?? '#1a3d32';
$s_reg_price_color = $styles['reg_price_color'] ?? '#9ca3af';
$s_variant_bg_color = $styles['variant_bg_color'] ?? '#1a3d32';
$s_variant_text_color = $styles['variant_text_color'] ?? '#ffffff';
$s_atc_btn_color = $styles['atc_btn_color'] ?? '#000000';
$s_atc_btn_text_color = $styles['atc_btn_text_color'] ?? '#ffffff';
$s_buy_now_btn_color = $styles['buy_now_btn_color'] ?? '#b91c1c';
$s_buy_now_btn_text_color = $styles['buy_now_btn_text_color'] ?? '#ffffff';
$s_action_links_color = $styles['action_links_color'] ?? '#4b5563';
$s_border_color = $styles['border_color'] ?? '#e5e7eb';
$s_divider_color = $styles['divider_color'] ?? '#e5e7eb';
$s_in_stock_color = $styles['in_stock_color'] ?? '#1a3d32';
$s_out_stock_color = $styles['out_stock_color'] ?? '#b91c1c';
$s_atc_hover_bg_color = $styles['atc_hover_bg_color'] ?? '#000000';
$s_atc_hover_text_color = $styles['atc_hover_text_color'] ?? '#ffffff';
$s_buy_now_hover_bg_color = $styles['buy_now_hover_bg_color'] ?? '#991b1b';
$s_buy_now_hover_text_color = $styles['buy_now_hover_text_color'] ?? '#ffffff';

$s_show_related = $styles['show_related'] ?? '1';
$s_related_title = $styles['related_title'] ?? 'People Also Bought';
$s_related_subtitle = $styles['related_subtitle'] ?? "Here's some of our most similar products people are buying. Click to discover trending style.";

$s_show_recent = $styles['show_recent'] ?? '1';
$s_recent_title = $styles['recent_title'] ?? 'Recently Viewed';
$s_recent_subtitle = $styles['recent_subtitle'] ?? "Explore your recently viewed items, blending quality and style for a refined living experience.";

$pageTitle = 'Product Page Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <form method="POST">
        <!-- Sticky Header -->
        <div class="sticky top-0 z-[100] bg-white border-b border-gray-200 -mx-6 px-6 py-4 mb-8 shadow-sm">
            <div class="container mx-auto flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Product Page Settings</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Customize the appearance and sections of the product detail page.</p>
                </div>
                <div class="flex items-center gap-4">
                    <button type="submit" class="bg-blue-600 text-white px-8 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg flex items-center gap-2 btn-loading">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Settings Side -->
            <div class="space-y-6">
                <!-- Visual Styles -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Visual Styles</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Page Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Info Box Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="info_box_bg_color" value="<?php echo htmlspecialchars($s_info_box_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_info_box_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Info Box Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="info_box_text_color" value="<?php echo htmlspecialchars($s_info_box_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_info_box_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sale Price Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sale_price_color" value="<?php echo htmlspecialchars($s_sale_price_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sale_price_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Regular Price Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="reg_price_color" value="<?php echo htmlspecialchars($s_reg_price_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_reg_price_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Variant Active Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="variant_bg_color" value="<?php echo htmlspecialchars($s_variant_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_variant_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Variant Active Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="variant_text_color" value="<?php echo htmlspecialchars($s_variant_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_variant_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Add to Cart Button</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_color" value="<?php echo htmlspecialchars($s_atc_btn_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Add to Cart Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_text_color" value="<?php echo htmlspecialchars($s_atc_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Buy Now Button</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="buy_now_btn_color" value="<?php echo htmlspecialchars($s_buy_now_btn_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_buy_now_btn_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Buy Now Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="buy_now_btn_text_color" value="<?php echo htmlspecialchars($s_buy_now_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_buy_now_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Links Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="action_links_color" value="<?php echo htmlspecialchars($s_action_links_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_action_links_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Border Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="border_color" value="<?php echo htmlspecialchars($s_border_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_border_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Divider Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="divider_color" value="<?php echo htmlspecialchars($s_divider_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_divider_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">In Stock Status Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="in_stock_color" value="<?php echo htmlspecialchars($s_in_stock_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_in_stock_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Out Stock Status Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="out_stock_color" value="<?php echo htmlspecialchars($s_out_stock_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_out_stock_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Add to Cart Hover BG</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_hover_bg_color" value="<?php echo htmlspecialchars($s_atc_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Add to Cart Hover Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_hover_text_color" value="<?php echo htmlspecialchars($s_atc_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Buy Now Hover BG</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="buy_now_hover_bg_color" value="<?php echo htmlspecialchars($s_buy_now_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_buy_now_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Buy Now Hover Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="buy_now_hover_text_color" value="<?php echo htmlspecialchars($s_buy_now_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_buy_now_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- People Also Bought Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800">People Also Bought</h2>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_related" value="1" class="sr-only peer" <?php echo $s_show_related === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Section Heading</label>
                            <input type="text" name="related_title" value="<?php echo htmlspecialchars($s_related_title); ?>" class="w-full border rounded p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Section Subheading</label>
                            <textarea name="related_subtitle" class="w-full border rounded p-2 text-sm h-20"><?php echo htmlspecialchars($s_related_subtitle); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Recently Viewed Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800">Recently Viewed</h2>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_recent" value="1" class="sr-only peer" <?php echo $s_show_recent === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Section Heading</label>
                            <input type="text" name="recent_title" value="<?php echo htmlspecialchars($s_recent_title); ?>" class="w-full border rounded p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Section Subheading</label>
                            <textarea name="recent_subtitle" class="w-full border rounded p-2 text-sm h-20"><?php echo htmlspecialchars($s_recent_subtitle); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Side -->
            <div class="sticky top-32">
                 <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i class="fas fa-eye text-blue-600"></i>
                    Product Page Live Preview
                </h2>
                
                <div id="productPreview" class="rounded-xl border border-gray-200 overflow-hidden shadow-2xl p-6 transition-all duration-300" style="background-color: <?php echo $s_page_bg_color; ?>;">
                    <!-- Product Header -->
                    <div class="flex gap-6 mb-8">
                        <div class="w-1/2 aspect-square bg-gray-100 rounded-xl"></div>
                        <div class="flex-1 space-y-3">
                            <div class="h-2 w-1/4 bg-gray-200 rounded"></div>
                            <div class="h-5 w-3/4 bg-gray-300 rounded"></div>
                            
                            <!-- Price Preview -->
                            <div class="flex items-center gap-3">
                                <span id="prevSalePrice" class="text-lg font-bold" style="color: <?php echo $s_sale_price_color; ?>;">$40.00</span>
                                <span id="prevRegPrice" class="text-sm line-through" style="color: <?php echo $s_reg_price_color; ?>;">$50.00</span>
                            </div>

                            <!-- Variant Preview -->
                            <div class="space-y-1">
                                <div class="h-2 w-1/3 bg-gray-100 rounded"></div>
                                <div class="flex gap-2">
                                    <div id="prevVariant" class="px-3 py-1.5 rounded text-[8px] font-bold" style="background-color: <?php echo $s_variant_bg_color; ?>; color: <?php echo $s_variant_text_color; ?>;">Standard</div>
                                    <div class="px-3 py-1.5 rounded text-[8px] font-bold border border-gray-200 text-gray-400">Premium</div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <div id="prevAtcBtn" class="flex-1 h-9 rounded-lg flex items-center justify-center text-[10px] font-bold" style="background-color: <?php echo $s_atc_btn_color; ?>; color: <?php echo $s_atc_btn_text_color; ?>;">
                                    ADD TO CART
                                </div>
                                <div id="prevBuyNowBtn" class="flex-1 h-9 rounded-lg flex items-center justify-center text-[10px] font-bold" style="background-color: <?php echo $s_buy_now_btn_color; ?>; color: <?php echo $s_buy_now_btn_text_color; ?>;">
                                    BUY IT NOW
                                </div>
                            </div>

                            <!-- Action Links Preview -->
                            <div id="prevActionLinks" class="flex gap-3 text-[8px] font-bold" style="color: <?php echo $s_action_links_color; ?>;">
                                <span><i class="fas fa-heart mr-1"></i> Wishlist</span>
                                 <span><i class="fas fa-question-circle mr-1"></i> Ask</span>
                                <span><i class="fas fa-share-alt mr-1"></i> Share</span>
                            </div>

                            <div class="flex items-center gap-1 text-[8px] font-bold" id="prevStockStatus" style="color: <?php echo $s_in_stock_color; ?>;">
                                <i class="fas fa-check-circle"></i> 2 items available
                            </div>

                            <!-- Divider Preview -->
                            <div class="space-y-2">
                                <div class="h-[1px] w-full prevDivider" style="background-color: <?php echo $s_divider_color; ?>;"></div>
                                <div class="flex justify-between items-center h-4">
                                    <div class="h-2 w-1/3 bg-gray-100 rounded"></div>
                                    <i class="fas fa-plus text-[6px] text-gray-400"></i>
                                </div>
                                <div class="h-[1px] w-full prevDivider" style="background-color: <?php echo $s_divider_color; ?>;"></div>
                            </div>

                            <div id="prevInfoBox" class="p-3 rounded-lg text-[10px]" style="background-color: <?php echo $s_info_box_bg_color; ?>; color: <?php echo $s_info_box_text_color; ?>;">
                                <i class="fas fa-store mr-1 text-blue-600"></i>
                                Pickup available at Shop location.
                            </div>

                            <!-- Border Item Preview (Button) -->
                            <div class="flex justify-center">
                                <div class="prevBorder px-4 py-1.5 rounded-lg text-[8px] font-bold border" style="border-color: <?php echo $s_border_color; ?>;">
                                    WRITE A REVIEW
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Related Products Preview -->
                    <div id="prevRelatedSection" class="mb-8 <?php echo $s_show_related === '1' ? '' : 'hidden'; ?>">
                        <div class="text-center mb-4">
                            <h3 id="prevRelatedTitle" class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($s_related_title); ?></h3>
                            <p id="prevRelatedSubtitle" class="text-[8px] text-gray-500 mt-1"><?php echo htmlspecialchars($s_related_subtitle); ?></p>
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100"></div>
                        </div>
                    </div>

                    <!-- Recently Viewed Preview -->
                    <div id="prevRecentSection" class="<?php echo $s_show_recent === '1' ? '' : 'hidden'; ?>">
                        <div class="text-center mb-4">
                            <h3 id="prevRecentTitle" class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($s_recent_title); ?></h3>
                            <p id="prevRecentSubtitle" class="text-[8px] text-gray-500 mt-1"><?php echo htmlspecialchars($s_recent_subtitle); ?></p>
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100 shadow-sm transition-all duration-300 hover:shadow-lg cursor-pointer"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100 shadow-sm transition-all duration-300 hover:shadow-lg cursor-pointer"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100 shadow-sm transition-all duration-300 hover:shadow-lg cursor-pointer"></div>
                            <div class="aspect-[3/4] bg-gray-100 rounded-lg border border-gray-100 shadow-sm transition-all duration-300 hover:shadow-lg cursor-pointer"></div>
                        </div>
                    </div>
                </div>

                <style id="previewStyles">
                    #prevAtcBtn:hover {
                        background-color: <?php echo $s_atc_hover_bg_color; ?> !important;
                        color: <?php echo $s_atc_hover_text_color; ?> !important;
                    }
                    #prevBuyNowBtn:hover {
                        background-color: <?php echo $s_buy_now_hover_bg_color; ?> !important;
                        color: <?php echo $s_buy_now_hover_text_color; ?> !important;
                    }
                    #productPreview .grid div:hover {
                        border-color: <?php echo $s_atc_hover_bg_color; ?> !important;
                    }
                </style>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const container = document.getElementById('ajax-content-inner') || document;
    function updatePreview() {
        const settings = {};
        container.querySelectorAll('form input[name], form textarea[name], form select[name]').forEach(input => {
            if (input.type === 'checkbox') {
                settings[input.name] = input.checked ? '1' : '0';
            } else {
                settings[input.name] = input.value;
            }
        });

        // High Level Styles
        const previewEl = document.getElementById('productPreview');
        if (previewEl) previewEl.style.backgroundColor = settings.page_bg_color;
        
        // Prices
        const salePrice = document.getElementById('prevSalePrice');
        if (salePrice) salePrice.style.color = settings.sale_price_color;
        
        const regPrice = document.getElementById('prevRegPrice');
        if (regPrice) regPrice.style.color = settings.reg_price_color;

        // Variants
        const variantEl = document.getElementById('prevVariant');
        if (variantEl) {
            variantEl.style.backgroundColor = settings.variant_bg_color;
            variantEl.style.color = settings.variant_text_color;
        }

        // Buttons
        const atcBtn = document.getElementById('prevAtcBtn');
        if (atcBtn) {
            atcBtn.style.backgroundColor = settings.atc_btn_color;
            atcBtn.style.color = settings.atc_btn_text_color;
        }
        const buyNowBtn = document.getElementById('prevBuyNowBtn');
        if (buyNowBtn) {
            buyNowBtn.style.backgroundColor = settings.buy_now_btn_color;
            buyNowBtn.style.color = settings.buy_now_btn_text_color;
        }

        // Action Links
        const actionLinks = document.getElementById('prevActionLinks');
        if (actionLinks) actionLinks.style.color = settings.action_links_color;

        const infoBox = document.getElementById('prevInfoBox');
        if (infoBox) {
            infoBox.style.backgroundColor = settings.info_box_bg_color;
            infoBox.style.color = settings.info_box_text_color;
        }

        // Borders & Dividers
        container.querySelectorAll('.prevDivider').forEach(el => el.style.backgroundColor = settings.divider_color);
        container.querySelectorAll('.prevBorder').forEach(el => el.style.borderColor = settings.border_color);

        // Stock Status
        const stockStatus = document.getElementById('prevStockStatus');
        if (stockStatus) stockStatus.style.color = settings.in_stock_color;

        // Visibility Toggles
        const relSec = document.getElementById('prevRelatedSection');
        if (relSec) relSec.classList.toggle('hidden', settings.show_related !== '1');
        
        const recSec = document.getElementById('prevRecentSection');
        if (recSec) recSec.classList.toggle('hidden', settings.show_recent !== '1');

        // Texts
        const relTitle = document.getElementById('prevRelatedTitle');
        if (relTitle) relTitle.textContent = settings.related_title;
        
        const relSub = document.getElementById('prevRelatedSubtitle');
        if (relSub) relSub.textContent = settings.related_subtitle;
        
        const recTitle = document.getElementById('prevRecentTitle');
        if (recTitle) recTitle.textContent = settings.recent_title;
        
        const recSub = document.getElementById('prevRecentSubtitle');
        if (recSub) recSub.textContent = settings.recent_subtitle;

        // Hove Styles
        const styleTag = document.getElementById('previewStyles');
        if (styleTag) {
            styleTag.innerHTML = `
                #prevAtcBtn:hover {
                    background-color: ${settings.atc_hover_bg_color} !important;
                    color: ${settings.atc_hover_text_color} !important;
                }
                #prevBuyNowBtn:hover {
                    background-color: ${settings.buy_now_hover_bg_color} !important;
                    color: ${settings.buy_now_hover_text_color} !important;
                }
                #productPreview .grid div:hover {
                    border-color: ${settings.atc_hover_bg_color} !important;
                }
            `;
        }
    }

    // Attach listeners
    container.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    // Color sync
    container.querySelectorAll('input[type="color"]').forEach(colorInput => {
        const textInput = colorInput.nextElementSibling;
        colorInput.addEventListener('input', () => {
            if (textInput) textInput.value = colorInput.value.toUpperCase();
        });
        if (textInput) {
            textInput.addEventListener('input', () => {
                const val = textInput.value;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    colorInput.value = val;
                }
            });
        }
    });

    updatePreview();
})();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
```
