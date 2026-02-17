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
        // General Settings
        $settingsObj->set('wishlist_heading', $_POST['heading'] ?? 'Wishlist');
        $settingsObj->set('wishlist_subheading', $_POST['subheading'] ?? 'Explore your saved items, blending quality and style for a refined living experience.');

        // Visual Styles
        $styles = [
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff',
            'card_bg_color' => $_POST['card_bg_color'] ?? '#ffffff',
            'card_title_color' => $_POST['card_title_color'] ?? '#1f2937',
            'price_color' => $_POST['price_color'] ?? '#1a3d32',
            'stock_status_color' => $_POST['stock_status_color'] ?? '#ef4444',
            
            // Add to Cart Button
            'btn_bg_color' => $_POST['btn_bg_color'] ?? '#1a3d32',
            'btn_text_color' => $_POST['btn_text_color'] ?? '#ffffff',
            'btn_hover_bg_color' => $_POST['btn_hover_bg_color'] ?? '#000000',
            'btn_hover_text_color' => $_POST['btn_hover_text_color'] ?? '#ffffff',

            // Remove Button
            'remove_btn_bg_color' => $_POST['remove_btn_bg_color'] ?? '#ffffff',
            'remove_btn_icon_color' => $_POST['remove_btn_icon_color'] ?? '#6b7280',
            'remove_btn_hover_bg_color' => $_POST['remove_btn_hover_bg_color'] ?? '#000000',
            'remove_btn_hover_icon_color' => $_POST['remove_btn_hover_icon_color'] ?? '#ffffff',
            
            // Quick View Button
            'quick_view_bg_color' => $_POST['quick_view_bg_color'] ?? '#ffffff',
            'quick_view_icon_color' => $_POST['quick_view_icon_color'] ?? '#1f2937',
            'quick_view_hover_bg_color' => $_POST['quick_view_hover_bg_color'] ?? '#000000',
            'quick_view_hover_icon_color' => $_POST['quick_view_hover_icon_color'] ?? '#ffffff',
        ];
        
        $settingsObj->set('wishlist_styles', json_encode($styles), 'wishlist');
        
        $_SESSION['flash_success'] = "Wishlist settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/wishlist_settings.php');
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

// Fetch Current Settings
$heading = $settingsObj->get('wishlist_heading', 'Wishlist');
$subheading = $settingsObj->get('wishlist_subheading', 'Explore your saved items, blending quality and style for a refined living experience.');

$stylesJson = $settingsObj->get('wishlist_styles', '[]');
$styles = json_decode($stylesJson, true);

// Defaults
$s_page_bg_color = $styles['page_bg_color'] ?? '#ffffff';
$s_card_bg_color = $styles['card_bg_color'] ?? '#ffffff';
$s_card_title_color = $styles['card_title_color'] ?? '#1f2937';
$s_price_color = $styles['price_color'] ?? '#1a3d32';
$s_stock_status_color = $styles['stock_status_color'] ?? '#ef4444';

$s_btn_bg_color = $styles['btn_bg_color'] ?? '#1a3d32';
$s_btn_text_color = $styles['btn_text_color'] ?? '#ffffff';
$s_btn_hover_bg_color = $styles['btn_hover_bg_color'] ?? '#000000';
$s_btn_hover_text_color = $styles['btn_hover_text_color'] ?? '#ffffff';

$s_remove_btn_bg_color = $styles['remove_btn_bg_color'] ?? '#ffffff';
$s_remove_btn_icon_color = $styles['remove_btn_icon_color'] ?? '#6b7280';
$s_remove_btn_hover_bg_color = $styles['remove_btn_hover_bg_color'] ?? '#000000';
$s_remove_btn_hover_icon_color = $styles['remove_btn_hover_icon_color'] ?? '#ffffff';

$s_quick_view_bg_color = $styles['quick_view_bg_color'] ?? '#ffffff';
$s_quick_view_icon_color = $styles['quick_view_icon_color'] ?? '#1f2937';
$s_quick_view_hover_bg_color = $styles['quick_view_hover_bg_color'] ?? '#000000';
$s_quick_view_hover_icon_color = $styles['quick_view_hover_icon_color'] ?? '#ffffff';

$pageTitle = 'Wishlist Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Wishlist Settings</h1>
            <p class="text-sm text-gray-500 mt-1">Customize the appearance of the wishlist page.</p>
        </div>
        <div>
             <a href="<?php echo $baseUrl; ?>/wishlist" target="_blank" class="mr-3 text-gray-600 hover:text-blue-600 font-bold text-sm">
                 <i class="fas fa-external-link-alt mr-1"></i> View Page
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- Content Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Page Content</h2>
            </div>
            <div class="p-6 grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Heading</label>
                    <input type="text" name="heading" value="<?php echo htmlspecialchars($heading); ?>" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                     <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading</label>
                     <textarea name="subheading" rows="2" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none"><?php echo htmlspecialchars($subheading); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Visual Style Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Visual Styling</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Page & Card Colors -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Page Background</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Card Background</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="card_bg_color" value="<?php echo htmlspecialchars($s_card_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_card_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Card Title Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="card_title_color" value="<?php echo htmlspecialchars($s_card_title_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_card_title_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="price_color" value="<?php echo htmlspecialchars($s_price_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_price_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Text Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="stock_status_color" value="<?php echo htmlspecialchars($s_stock_status_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_stock_status_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>

            </div>
        </div>

        <!-- Button Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Action Buttons</h2>
            </div>
            
            <div class="p-6">
                <!-- Add to Cart -->
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 border-b pb-2">Add to Cart Button</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="btn_bg_color" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                             <label class="block text-sm font-semibold text-gray-700 mb-2">Text/Icon (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="btn_text_color" value="<?php echo htmlspecialchars($s_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="btn_hover_bg_color" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                             <label class="block text-sm font-semibold text-gray-700 mb-2">Text/Icon (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="btn_hover_text_color" value="<?php echo htmlspecialchars($s_btn_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remove Button -->
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 border-b pb-2">Remove Button (X Icon)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="remove_btn_bg_color" value="<?php echo htmlspecialchars($s_remove_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_remove_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Icon Color (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="remove_btn_icon_color" value="<?php echo htmlspecialchars($s_remove_btn_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_remove_btn_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                         <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="remove_btn_hover_bg_color" value="<?php echo htmlspecialchars($s_remove_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_remove_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Icon Color (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="remove_btn_hover_icon_color" value="<?php echo htmlspecialchars($s_remove_btn_hover_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_remove_btn_hover_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick View Button -->
                <div>
                     <h3 class="text-sm font-bold text-gray-800 mb-4 border-b pb-2">Quick View Button</h3>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="quick_view_bg_color" value="<?php echo htmlspecialchars($s_quick_view_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_quick_view_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Icon Color (Normal)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="quick_view_icon_color" value="<?php echo htmlspecialchars($s_quick_view_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_quick_view_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                         <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Background (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="quick_view_hover_bg_color" value="<?php echo htmlspecialchars($s_quick_view_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_quick_view_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Icon Color (Hover)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="quick_view_hover_icon_color" value="<?php echo htmlspecialchars($s_quick_view_hover_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_quick_view_hover_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                     </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
