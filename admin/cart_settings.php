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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Cart Styling (Consolidated)
        $cartStyling = [];
        $drawerKeys = [
            'cart_drawer_bg_color', 'cart_drawer_header_text_color', 'cart_drawer_header_text_hover_color',
            'cart_drawer_price_color', 'cart_drawer_qty_color', 'cart_drawer_trash_color',
            'cart_drawer_trash_hover_color', 'cart_drawer_total_color', 'cart_drawer_view_btn_bg',
            'cart_drawer_view_btn_text', 'cart_drawer_view_btn_hover_bg', 'cart_drawer_view_btn_hover_text',
            'cart_drawer_checkout_btn_bg', 'cart_drawer_checkout_btn_text', 'cart_drawer_checkout_btn_hover_bg',
            'cart_drawer_checkout_btn_hover_text', 'cart_drawer_divider_color', 'cart_drawer_close_icon_color'
        ];
        
        $pageKeys = [
            'cart_page_bg_color', 'cart_page_header_text_color', 'cart_page_header_text_hover_color',
            'cart_page_price_color', 'cart_page_qty_color', 'cart_page_trash_color',
            'cart_page_trash_hover_color', 'cart_page_total_color', 'cart_page_checkout_btn_bg',
            'cart_page_checkout_btn_text', 'cart_page_checkout_btn_hover_bg', 'cart_page_checkout_btn_hover_text',
            'cart_page_continue_btn_bg', 'cart_page_continue_btn_text', 'cart_page_continue_btn_hover_bg',
            'cart_page_continue_btn_hover_text', 'cart_page_card_bg_color', 'cart_page_summary_bg_color',
            'cart_page_summary_border_color'
        ];

        foreach ($drawerKeys as $key) {
            $cartStyling[$key] = $_POST["setting_$key"] ?? '';
        }
        foreach ($pageKeys as $key) {
            $cartStyling[$key] = $_POST["setting_$key"] ?? '';
        }

        $settingsObj->set('cart_page_styling', json_encode($cartStyling), 'cart_styling');

        $_SESSION['flash_success'] = "Cart settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/cart_settings.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load Consolidated Settings
$cartStylingJson = $settingsObj->get('cart_page_styling', '');
$cartStyling = !empty($cartStylingJson) ? json_decode($cartStylingJson, true) : [];

// Migration Helper Helper
function getCartStyle($key, $default, $settingsObj, $cartStyling) {
    if (isset($cartStyling[$key])) return $cartStyling[$key];
    return $settingsObj->get($key, $default);
}

$pageTitle = 'Cart Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <form method="POST">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 sticky top-0 z-20 bg-gray-100 py-4 -mx-6 px-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Cart Settings</h1>
                <p class="text-sm text-gray-500 mt-1">Customize the appearance of the Cart Drawer and Cart Page.</p>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            
            <!-- SETTINGS COLUMN (Spans 2 columns on large screens) -->
            <div class="xl:col-span-2 space-y-8">
                
                <!-- Cart Drawer Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-shopping-cart"></i> Cart Drawer Design
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        
                        <!-- Background Color -->
                        <div>
                             <label class="block text-xs font-semibold text-gray-500 mb-1">Drawer Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="setting_cart_drawer_bg_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_bg">
                                <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                            </div>
                        </div>

                        <!-- Header / Product Title -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Product Title Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_header_text_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_header_text_color', '#111827', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_header_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_header_text_color', '#111827', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Product Title Hover</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_header_text_hover_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_header_text_hover_color', '#3b82f6', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_header_text_hover_color', '#3b82f6', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <!-- Price & Quantity -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_price_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_price_color', '#1f2937', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_price_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_price_color', '#1f2937', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity/Item Total Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_qty_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_qty_color', '#374151', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_qty_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_qty_color', '#374151', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <!-- Trash Icon -->
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Trash Icon Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_trash_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_trash_color', '#ef4444', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_trash_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_trash_color', '#ef4444', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Trash Icon Hover</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_trash_hover_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_trash_hover_color', '#b91c1c', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_trash_hover_color', '#b91c1c', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <!-- Total Section -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Bottom Total Text Color</label>
                            <div class="flex items-center gap-2">
                                 <input type="color" name="setting_cart_drawer_total_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_total_color', '#111827', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_total_color">
                                 <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_total_color', '#111827', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                            </div>
                        </div>

                        <!-- Divider & Close Icon -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Divider Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_divider_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_divider_color', '#e5e7eb', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_divider_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_divider_color', '#e5e7eb', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Close Icon Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_drawer_close_icon_color" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_close_icon_color', '#9ca3af', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_close_icon_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_close_icon_color', '#9ca3af', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <div class="border-t pt-4 mt-2">
                            <h3 class="text-sm font-bold text-gray-700 mb-3">View Cart Button</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_view_btn_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_bg', '#3b82f6', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_view_bg">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_bg', '#3b82f6', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_view_btn_text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_view_text">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_view_btn_hover_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_hover_bg', '#2563eb', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_hover_bg', '#2563eb', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_view_btn_hover_text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_view_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-sm font-bold text-gray-700 mb-3">Checkout Button</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_checkout_btn_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_bg', '#000000', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_check_bg">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_bg', '#000000', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_checkout_btn_text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cd_check_text">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateDrawerPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_checkout_btn_hover_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_hover_bg', '#1f2937', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_hover_bg', '#1f2937', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_drawer_checkout_btn_hover_text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_drawer_checkout_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Cart Page Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-file-alt"></i> Cart Page Design
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Background Color -->
                        <div>
                             <label class="block text-xs font-semibold text-gray-500 mb-1">Page Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="setting_cart_page_bg_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_bg_color', '#f9fafb', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_bg">
                                <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_bg_color', '#f9fafb', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                            </div>
                        </div>

                        <!-- Card & Summary Backgrounds -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Product Card Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_card_bg_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_card_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_card_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_card_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Order Summary Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_summary_bg_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_summary_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_summary_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_summary_bg_color', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Order Summary Border</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_summary_border_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_summary_border_color', '#e5e7eb', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_summary_border">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_summary_border_color', '#e5e7eb', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <!-- Header / Product Title -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Product Title Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_header_text_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_header_text_color', '#111827', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_header_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_header_text_color', '#111827', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Product Title Hover</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_header_text_hover_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_header_text_hover_color', '#3b82f6', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_header_text_hover_color', '#3b82f6', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <!-- Price & Quantity -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_price_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_price_color', '#1f2937', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_price_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_price_color', '#1f2937', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity/Item Total Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_qty_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_qty_color', '#374151', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_qty_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_qty_color', '#374151', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <!-- Trash Icon -->
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Trash Icon Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_trash_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_trash_color', '#ef4444', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_trash_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_trash_color', '#ef4444', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Trash Icon Hover</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_cart_page_trash_hover_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_trash_hover_color', '#b91c1c', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                    <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_trash_hover_color', '#b91c1c', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <!-- Total Section -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Bottom Total Text Color</label>
                            <div class="flex items-center gap-2">
                                 <input type="color" name="setting_cart_page_total_color" value="<?php echo htmlspecialchars(getCartStyle('cart_page_total_color', '#111827', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_total_color">
                                 <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_total_color', '#111827', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                            </div>
                        </div>

                         <div class="border-t pt-4 mt-2">
                            <h3 class="text-sm font-bold text-gray-700 mb-3">Checkout Button (Cart Page)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_checkout_btn_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_bg', '#000000', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_check_bg">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_bg', '#000000', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_checkout_btn_text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_check_text">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_checkout_btn_hover_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_hover_bg', '#1f2937', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_hover_bg', '#1f2937', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_checkout_btn_hover_text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_checkout_btn_hover_text', '#ffffff', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-sm font-bold text-gray-700 mb-3 mt-4">Continue Shopping Button (Cart Page)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_continue_btn_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_bg', '#e5e7eb', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_continue_bg">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_bg', '#e5e7eb', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_continue_btn_text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_text', '#374151', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_cp_continue_text">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_text', '#374151', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updatePagePreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_continue_btn_hover_bg" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_hover_bg', '#d1d5db', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_hover_bg', '#d1d5db', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="setting_cart_page_continue_btn_hover_text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_hover_text', '#111827', $settingsObj, $cartStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                        <input type="text" value="<?php echo htmlspecialchars(getCartStyle('cart_page_continue_btn_hover_text', '#111827', $settingsObj, $cartStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                 </div>
            </div>

            <!-- PREVIEW COLUMN (Sticky) -->
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-6">
                    
                    <!-- CART DRAWER PREVIEW -->
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase">Cart Drawer Preview</h3>
                        </div>
                        <div id="preview-cart-drawer" class="p-6 transition-colors duration-200 border-l border-r border-b relative">
                            <!-- Overlay to simulate drawer shadow/depth if needed -->
                            
                            <!-- Header Mock -->
                            <div class="flex justify-between items-center mb-6 pb-4 border-b preview-divider">
                                <h2 class="text-xl font-bold preview-header-item">Shopping Cart (1)</h2>
                                <button class="preview-close-icon"><i class="fas fa-times"></i></button>
                            </div>

                            <!-- Item Mock -->
                            <div class="flex gap-4 mb-6">
                                <div class="w-16 h-16 bg-gray-200 rounded shrink-0"></div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-sm mb-1">
                                        <a href="#" class="preview-item-title transition-colors">Premium Cookware Set</a>
                                    </h4>
                                    <p class="text-sm mb-2 preview-item-price">$129.00</p>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center border rounded w-24">
                                            <button class="w-8 h-8 flex items-center justify-center border-r hover:bg-gray-100">-</button>
                                            <span class="flex-1 text-center text-sm font-semibold preview-item-qty">1</span>
                                            <button class="w-8 h-8 flex items-center justify-center border-l hover:bg-gray-100">+</button>
                                        </div>
                                        <button class="preview-trash-icon transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Mock -->
                            <div class="pt-4 border-t preview-divider">
                                 <div class="flex justify-between items-center mb-4">
                                    <span class="text-lg font-semibold preview-total-text">Total:</span>
                                    <span class="text-xl font-bold preview-total-text">$129.00</span>
                                </div>
                                <button class="w-full py-3 rounded-lg mb-2 transition text-sm font-semibold text-center preview-view-btn">View Cart</button>
                                <button class="w-full py-3 rounded-lg transition text-sm font-semibold text-center preview-checkout-btn">Checkout</button>
                            </div>

                        </div>
                    </div>

                    <!-- CART PAGE PREVIEW -->
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase">Cart Page Preview</h3>
                        </div>
                         <div id="preview-cart-page" class="p-6 transition-colors duration-200 min-h-[300px]">
                            
                            <!-- Header Mock -->
                             <h1 class="text-2xl font-bold mb-6 text-gray-800">Shopping Cart</h1>

                            <!-- Cart Table/Grid Mock -->
                            <div class="preview-cp-card rounded-lg shadow-sm p-4 mb-6 border">
                                <div class="flex gap-4">
                                    <div class="w-20 h-20 bg-gray-200 rounded shrink-0"></div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                 <h3 class="font-semibold text-lg mb-1">
                                                    <a href="#" class="preview-cp-title transition-colors">Premium Cookware Set</a>
                                                </h3>
                                                <p class="text-sm mb-2 preview-cp-price">$129.00</p>
                                            </div>
                                            <p class="font-bold text-lg preview-cp-total">$129.00</p>
                                        </div>
                                        
                                        <div class="flex justify-between items-center mt-2">
                                             <div class="flex items-center border rounded w-24">
                                                <button class="w-8 h-8 flex items-center justify-center border-r hover:bg-gray-100">-</button>
                                                <span class="flex-1 text-center text-sm font-semibold preview-cp-qty">1</span>
                                                <button class="w-8 h-8 flex items-center justify-center border-l hover:bg-gray-100">+</button>
                                            </div>
                                             <button class="preview-cp-trash transition-colors">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Summary Mock -->
                            <div class="preview-cp-summary rounded-lg shadow-sm p-4 border">
                                 <div class="flex justify-between items-center mb-4 pt-2 border-t mt-2">
                                    <span class="text-lg font-bold text-gray-800">Grand Total</span>
                                    <span class="text-2xl font-bold preview-cp-grand-total">$129.00</span>
                                </div>
                                 <button class="w-full py-4 rounded-lg transition text-lg font-bold text-center preview-cp-checkout-btn mb-2">Proceed to Checkout</button>
                                 <button class="w-full py-4 rounded-lg transition text-lg font-bold text-center preview-cp-continue-btn border">Continue Shopping</button>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>

<script>
    // Live Preview Logic for Cart Drawer
    function updateDrawerPreview() {
        const drawer = document.getElementById('preview-cart-drawer');
        const bgInput = document.getElementById('input_cd_bg');
        
        // Background
        if (bgInput) drawer.style.backgroundColor = bgInput.value;

        // Header Title (For both main title and product title in mock)
        const headerColorInput = document.getElementById('input_cd_header_color');
        const headerLinks = drawer.querySelectorAll('.preview-item-title');
        // const mainHeader = drawer.querySelector('.preview-header-item'); // If we want to color the 'Shopping Cart' text too? Usually main headers are standard, but let's stick to product title for now as per label.
        
        if (headerColorInput) {
            headerLinks.forEach(el => el.style.color = headerColorInput.value);
            // if(mainHeader) mainHeader.style.color = headerColorInput.value; 
        }

        // Price
        const priceInput = document.getElementById('input_cd_price_color');
        const prices = drawer.querySelectorAll('.preview-item-price');
        if (priceInput) {
            prices.forEach(el => el.style.color = priceInput.value);
        }

        // Qty
        const qtyInput = document.getElementById('input_cd_qty_color');
        const qties = drawer.querySelectorAll('.preview-item-qty');
        if (qtyInput) {
            qties.forEach(el => el.style.color = qtyInput.value);
        }

        // Trash
        const trashInput = document.getElementById('input_cd_trash_color');
        const trashes = drawer.querySelectorAll('.preview-trash-icon');
        if (trashInput) {
            trashes.forEach(el => el.style.color = trashInput.value);
        }

        // Total
        const totalInput = document.getElementById('input_cd_total_color');
        const totals = drawer.querySelectorAll('.preview-total-text');
        if (totalInput) {
            totals.forEach(el => el.style.color = totalInput.value);
        }

        // Divider
        const dividerInput = document.getElementById('input_cd_divider_color');
        const dividers = drawer.querySelectorAll('.preview-divider');
        if (dividerInput) {
            dividers.forEach(el => el.style.borderColor = dividerInput.value);
        }

        // Close Icon
        const closeIconInput = document.getElementById('input_cd_close_icon_color');
        const closeIcon = drawer.querySelector('.preview-close-icon');
        if (closeIcon && closeIconInput) {
            closeIcon.style.color = closeIconInput.value;
        }

        // View Cart Button
        const viewBg = document.getElementById('input_cd_view_bg');
        const viewText = document.getElementById('input_cd_view_text');
        const viewBtn = drawer.querySelector('.preview-view-btn');
        if (viewBtn) {
            if (viewBg) viewBtn.style.backgroundColor = viewBg.value;
            if (viewText) viewBtn.style.color = viewText.value;
        }

        // Checkout Cart Button
        const checkBg = document.getElementById('input_cd_check_bg');
        const checkText = document.getElementById('input_cd_check_text');
        const checkBtn = drawer.querySelector('.preview-checkout-btn');
        if (checkBtn) {
             if (checkBg) checkBtn.style.backgroundColor = checkBg.value;
             if (checkText) checkBtn.style.color = checkText.value;
        }
    }

    // Live Preview Logic for Cart Page
    function updatePagePreview() {
        const pagePreview = document.getElementById('preview-cart-page');
        const bgInput = document.getElementById('input_cp_bg');
        
        // Background
        if (bgInput) pagePreview.style.backgroundColor = bgInput.value;

        // Product Card Background
        const cardBgInput = document.getElementById('input_cp_card_bg');
        const cards = pagePreview.querySelectorAll('.preview-cp-card');
        if (cardBgInput) {
            cards.forEach(el => el.style.backgroundColor = cardBgInput.value);
        }

        // Order Summary Background
        const summaryBgInput = document.getElementById('input_cp_summary_bg');
        const summaries = pagePreview.querySelectorAll('.preview-cp-summary');
        if (summaryBgInput) {
            summaries.forEach(el => el.style.backgroundColor = summaryBgInput.value);
        }

        // Order Summary Border
        const summaryBorderInput = document.getElementById('input_cp_summary_border');
        if (summaryBorderInput) {
            summaries.forEach(el => el.style.borderColor = summaryBorderInput.value);
        }

        // Product Title
        const headerColorInput = document.getElementById('input_cp_header_color');
        const headerLinks = pagePreview.querySelectorAll('.preview-cp-title');
        if (headerColorInput) {
            headerLinks.forEach(el => el.style.color = headerColorInput.value);
        }

        // Price
        const priceInput = document.getElementById('input_cp_price_color');
        const prices = pagePreview.querySelectorAll('.preview-cp-price');
        if (priceInput) {
            prices.forEach(el => el.style.color = priceInput.value);
        }

        // Qty & Item Total (using same color setting as per form)
        const qtyInput = document.getElementById('input_cp_qty_color');
        const qties = pagePreview.querySelectorAll('.preview-cp-qty');
        const itemTotals = pagePreview.querySelectorAll('.preview-cp-total');
        if (qtyInput) {
            qties.forEach(el => el.style.color = qtyInput.value);
            itemTotals.forEach(el => el.style.color = qtyInput.value);
        }

        // Trash
        const trashInput = document.getElementById('input_cp_trash_color');
        const trashes = pagePreview.querySelectorAll('.preview-cp-trash');
        if (trashInput) {
            trashes.forEach(el => el.style.color = trashInput.value);
        }

        // Total (Grand Total)
        const totalInput = document.getElementById('input_cp_total_color');
        const grandTotals = pagePreview.querySelectorAll('.preview-cp-grand-total');
        if (totalInput) {
            grandTotals.forEach(el => el.style.color = totalInput.value);
        }

        // Checkout Button
        const checkBg = document.getElementById('input_cp_check_bg');
        const checkText = document.getElementById('input_cp_check_text');
        const checkBtn = pagePreview.querySelector('.preview-cp-checkout-btn');
        if (checkBtn) {
             if (checkBg) checkBtn.style.backgroundColor = checkBg.value;
             if (checkText) checkBtn.style.color = checkText.value;
        }

        // Continue Shopping Button
        const continueBg = document.getElementById('input_cp_continue_bg');
        const continueText = document.getElementById('input_cp_continue_text');
        const continueBtn = pagePreview.querySelector('.preview-cp-continue-btn');
        if (continueBtn) {
             if (continueBg) continueBtn.style.backgroundColor = continueBg.value;
             if (continueText) continueBtn.style.color = continueText.value;
        }
    }

    // Function to handle bulk listeners
    function attachListeners() {
        updateDrawerPreview();
        updatePagePreview();

        const drawerInputs = [
            'input_cd_bg', 'input_cd_header_color', 'input_cd_price_color', 
            'input_cd_qty_color', 'input_cd_trash_color', 'input_cd_total_color',
            'input_cd_divider_color', 'input_cd_close_icon_color',
            'input_cd_view_bg', 'input_cd_view_text', 'input_cd_check_bg', 'input_cd_check_text'
        ];

        drawerInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateDrawerPreview);
                if(el.nextElementSibling) el.nextElementSibling.addEventListener('input', updateDrawerPreview);
            }
        });

        const pageInputs = [
            'input_cp_bg', 'input_cp_header_color', 'input_cp_price_color', 
            'input_cp_qty_color', 'input_cp_trash_color', 'input_cp_total_color',
            'input_cp_check_bg', 'input_cp_check_text',
            'input_cp_continue_bg', 'input_cp_continue_text',
            'input_cp_card_bg', 'input_cp_summary_bg', 'input_cp_summary_border'
        ];

        pageInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updatePagePreview);
                if(el.nextElementSibling) el.nextElementSibling.addEventListener('input', updatePagePreview);
            }
        });
    }

    // Attach listeners
    document.addEventListener('DOMContentLoaded', attachListeners);

</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
