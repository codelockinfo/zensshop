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
        $newStyles = [
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff',
            'heading_color' => $_POST['heading_color'] ?? '#1f2937',
            'subheading_color' => $_POST['subheading_color'] ?? '#4b5563',
            
            // Preserve other settings from global or existing to avoid breaking anything
            'card_bg_color' => $styles['card_bg_color'] ?? '#ffffff',
            'card_title_color' => $styles['card_title_color'] ?? '#1f2937',
            'price_color' => $styles['price_color'] ?? '#1a3d32',
            'btn_bg_color' => $styles['btn_bg_color'] ?? '#1a3d32',
            'btn_text_color' => $styles['btn_text_color'] ?? '#ffffff',
            'btn_hover_bg_color' => $styles['btn_hover_bg_color'] ?? '#000000',
            'btn_hover_text_color' => $styles['btn_hover_text_color'] ?? '#ffffff',
            'remove_btn_bg_color' => $styles['remove_btn_bg_color'] ?? '#ffffff',
            'remove_btn_icon_color' => $styles['remove_btn_icon_color'] ?? '#6b7280',
            'remove_btn_hover_bg_color' => $styles['remove_btn_hover_bg_color'] ?? '#000000',
            'remove_btn_hover_icon_color' => $styles['remove_btn_hover_icon_color'] ?? '#ffffff',
            'quick_view_bg_color' => $styles['quick_view_bg_color'] ?? '#ffffff',
            'quick_view_icon_color' => $styles['quick_view_icon_color'] ?? '#1f2937',
            'quick_view_hover_bg_color' => $styles['quick_view_hover_bg_color'] ?? '#000000',
            'quick_view_hover_icon_color' => $styles['quick_view_hover_icon_color'] ?? '#ffffff',
            'stock_status_color' => $styles['stock_status_color'] ?? '#ef4444',
        ];
        
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
        <!-- Content Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Page Content</h2>
                <p class="text-sm text-gray-500">Update the text and colors for the wishlist page header.</p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Text</label>
                        <input type="text" name="heading" value="<?php echo htmlspecialchars($heading); ?>" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="heading_color" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                            <input type="text" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                        </div>
                    </div>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Text</label>
                     <textarea name="subheading" rows="2" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none mb-4"><?php echo htmlspecialchars($subheading); ?></textarea>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="subheading_color" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Page Background</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
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

    <!-- Preview Section -->
    <div class="mt-12">
        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fas fa-eye text-blue-600"></i>
            Live Preview
        </h2>
        
        <div id="wishlistPreview" class="rounded-xl border border-gray-200 overflow-hidden shadow-lg p-8 transition-all duration-300" style="background-color: <?php echo $s_page_bg_color; ?>;">
            <div class="max-w-4xl mx-auto">
                <nav class="text-xs text-gray-500 mb-6 flex items-center gap-2">
                    <span>Home</span>
                    <i class="fas fa-chevron-right text-[10px]"></i>
                    <span class="font-semibold text-gray-900">Wishlist</span>
                </nav>

                <h1 id="prevHeading" class="text-3xl font-bold text-center mb-3" style="color: <?php echo $s_heading_color; ?>;">
                    <?php echo htmlspecialchars($heading); ?>
                </h1>
                <p id="prevSubheading" class="text-center mb-10 text-sm max-w-lg mx-auto" style="color: <?php echo $s_subheading_color; ?>;">
                    <?php echo htmlspecialchars($subheading); ?>
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <!-- Sample Card -->
                    <div class="rounded-lg border border-gray-100 shadow-sm overflow-hidden bg-white">
                        <div class="h-48 bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-image text-gray-300 text-4xl"></i>
                        </div>
                        <div class="p-4">
                            <div class="h-4 w-3/4 bg-gray-100 rounded mb-2"></div>
                            <div class="h-4 w-1/2 bg-gray-100 rounded mb-4"></div>
                            <div class="h-10 w-full bg-gray-100 rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const headingInput = document.querySelector('input[name="heading"]');
    const subheadingInput = document.querySelector('textarea[name="subheading"]');
    const headingColorInput = document.querySelector('input[name="heading_color"]');
    const subheadingColorInput = document.querySelector('input[name="subheading_color"]');
    const pageBgColorInput = document.querySelector('input[name="page_bg_color"]');

    const prevHeading = document.getElementById('prevHeading');
    const prevSubheading = document.getElementById('prevSubheading');
    const wishlistPreview = document.getElementById('wishlistPreview');

    function updatePreview() {
        prevHeading.textContent = headingInput.value;
        prevSubheading.textContent = subheadingInput.value;
        prevHeading.style.color = headingColorInput.value;
        prevSubheading.style.color = subheadingColorInput.value;
        wishlistPreview.style.backgroundColor = pageBgColorInput.value;
    }

    [headingInput, subheadingInput, headingColorInput, subheadingColorInput, pageBgColorInput].forEach(input => {
        if (input) {
            input.addEventListener('input', updatePreview);
        }
    });
});
</script>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
