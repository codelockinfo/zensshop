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
$heading = $settingsObj->get('wishlist_heading', 'Wishlist');
$subheading = $settingsObj->get('wishlist_subheading', 'Explore your saved items, blending quality and style for a refined living experience.');

$stylesJson = $settingsObj->get('wishlist_styles', '[]');
$styles = json_decode($stylesJson, true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // General Settings
        $settingsObj->set('wishlist_heading', $_POST['heading'] ?? 'Wishlist');
        $settingsObj->set('wishlist_subheading', $_POST['subheading'] ?? 'Explore your saved items, blending quality and style for a refined living experience.');

        // Visual Styles
        $newStyles = $styles;
        $newStyles['page_bg_color'] = $_POST['page_bg_color'] ?? ($styles['page_bg_color'] ?? '#ffffff');
        $newStyles['heading_color'] = $_POST['heading_color'] ?? ($styles['heading_color'] ?? '#1f2937');
        $newStyles['subheading_color'] = $_POST['subheading_color'] ?? ($styles['subheading_color'] ?? '#4b5563');

        $keys_to_remove = ['card_bg_color', 'card_title_color', 'price_color', 'btn_bg_color', 'btn_text_color', 'btn_hover_bg_color', 'btn_hover_text_color', 'remove_btn_bg_color', 'remove_btn_icon_color', 'remove_btn_hover_bg_color', 'remove_btn_hover_icon_color', 'quick_view_bg_color', 'quick_view_icon_color', 'quick_view_hover_bg_color', 'quick_view_hover_icon_color', 'stock_status_color'];
        foreach ($keys_to_remove as $key) {
            unset($newStyles[$key]);
        }
        
        $settingsObj->set('wishlist_styles', json_encode($newStyles), 'wishlist');
        
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

// Defaults
$s_page_bg_color = $styles['page_bg_color'] ?? '#ffffff';
$s_heading_color = $styles['heading_color'] ?? '#1f2937';
$s_subheading_color = $styles['subheading_color'] ?? '#4b5563';

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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Content Settings -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Page Content</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Text</label>
                            <input type="text" name="heading" value="<?php echo htmlspecialchars($heading); ?>" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Text</label>
                            <textarea name="subheading" rows="3" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none"><?php echo htmlspecialchars($subheading); ?></textarea>
                        </div>
                    </div>
                </div>

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
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="heading_color" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="subheading_color" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Side -->
            <div>
                 <div class="sticky top-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-eye text-blue-600"></i>
                        Live Preview
                    </h2>
                    
                    <div id="wishlistPreview" class="rounded-xl border border-gray-200 overflow-hidden shadow-lg p-8 transition-all duration-300 min-h-[500px]" style="background-color: <?php echo $s_page_bg_color; ?>;">
                        <div class="flex flex-col h-full">
                            <nav class="text-xs text-gray-500 mb-8 flex items-center gap-2 justify-center opacity-60">
                                <span>Home</span>
                                <i class="fas fa-chevron-right text-[10px]"></i>
                                <span class="font-semibold text-gray-900">Wishlist</span>
                            </nav>

                            <div class="text-center mb-12">
                                <h1 id="prevHeading" class="text-3xl font-bold mb-3" style="color: <?php echo $s_heading_color; ?>;">
                                    <?php echo htmlspecialchars($heading); ?>
                                </h1>
                                <p id="prevSubheading" class="text-sm max-w-xs mx-auto leading-relaxed" style="color: <?php echo $s_subheading_color; ?>;">
                                    <?php echo htmlspecialchars($subheading); ?>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 px-4">
                                <!-- Sample Card -->
                                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden group">
                                    <div class="aspect-square bg-gray-50 flex items-center justify-center relative">
                                        <i class="fas fa-image text-gray-300 text-3xl"></i>
                                    </div>
                                    <div class="p-3">
                                        <div class="h-3 w-3/4 bg-gray-100 rounded mb-2"></div>
                                        <div class="h-3 w-1/2 bg-gray-100 rounded"></div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden group opacity-50">
                                    <div class="aspect-square bg-gray-50 flex items-center justify-center relative">
                                        <i class="fas fa-image text-gray-300 text-3xl"></i>
                                    </div>
                                    <div class="p-3">
                                        <div class="h-3 w-3/4 bg-gray-100 rounded mb-2"></div>
                                        <div class="h-3 w-1/2 bg-gray-100 rounded"></div>
                                    </div>
                                </div>
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

<script>
window.initWishlistSettings = function() {
    var container = document.querySelector('form') || document;
    var inputs = {
        heading: container.querySelector('input[name="heading"]'),
        subheading: container.querySelector('textarea[name="subheading"]'),
        headingColor: container.querySelector('input[name="heading_color"]'),
        subheadingColor: container.querySelector('input[name="subheading_color"]'),
        pageBg: container.querySelector('input[name="page_bg_color"]')
    };

    var preview = {
        heading: document.getElementById('prevHeading'),
        subheading: document.getElementById('prevSubheading'),
        container: document.getElementById('wishlistPreview')
    };

    window.updateWishlistPreview = function() {
        if (preview.heading && inputs.heading) preview.heading.textContent = inputs.heading.value;
        if (preview.subheading && inputs.subheading) preview.subheading.textContent = inputs.subheading.value;
        if (preview.heading && inputs.headingColor) preview.heading.style.color = inputs.headingColor.value;
        if (preview.subheading && inputs.subheadingColor) preview.subheading.style.color = inputs.subheadingColor.value;
        if (preview.container && inputs.pageBg) preview.container.style.backgroundColor = inputs.pageBg.value;
    };

    Object.values(inputs).forEach(input => {
        if (input) {
            input.addEventListener('input', window.updateWishlistPreview);
            if(input.nextElementSibling && input.nextElementSibling.tagName === 'INPUT') {
                input.nextElementSibling.addEventListener('input', window.updateWishlistPreview);
            }
        }
    });

    window.updateWishlistPreview();
};

window.initWishlistSettings();
</script>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
