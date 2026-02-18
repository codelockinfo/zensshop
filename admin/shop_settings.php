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
$stylesJson = $settingsObj->get('shop_styles', '[]');
$styles = json_decode($stylesJson, true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle Banner Upload
        if (!empty($_FILES['setting_all_category_banner']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['setting_all_category_banner']['name'], PATHINFO_EXTENSION);
            $fileName = 'banner_all_cat_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['setting_all_category_banner']['tmp_name'], $uploadDir . $fileName)) {
                $settingsObj->set('all_category_banner', 'assets/images/' . $fileName, 'general');
            }
        }

        // Visual Styles
        $newStyles = [
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff',
            'sidebar_bg_color' => $_POST['sidebar_bg_color'] ?? '#ffffff',
            'sidebar_text_color' => $_POST['sidebar_text_color'] ?? '#1f2937',
            'filter_btn_bg_color' => $_POST['filter_btn_bg_color'] ?? '#1a3d32',
            'filter_btn_text_color' => $_POST['filter_btn_text_color'] ?? '#ffffff',
            'filter_btn_hover_bg_color' => $_POST['filter_btn_hover_bg_color'] ?? '#000000',
            'filter_btn_hover_text_color' => $_POST['filter_btn_hover_text_color'] ?? '#ffffff',
            'sort_dropdown_bg_color' => $_POST['sort_dropdown_bg_color'] ?? '#ffffff',
            'sort_dropdown_text_color' => $_POST['sort_dropdown_text_color'] ?? '#1f2937',
            'active_view_btn_color' => $_POST['active_view_btn_color'] ?? '#000000',
            'hero_bg_color' => $_POST['hero_bg_color'] ?? '#f3f4f6',
            'hero_text_color' => $_POST['hero_text_color'] ?? '#111827',
            'input_bg_color' => $_POST['input_bg_color'] ?? '#ffffff',
            'input_text_color' => $_POST['input_text_color'] ?? '#1f2937',
            'results_count_text_color' => $_POST['results_count_text_color'] ?? '#4b5563',
            'sidebar_border_color' => $_POST['sidebar_border_color'] ?? '#e5e7eb',
            'sidebar_divider_color' => $_POST['sidebar_divider_color'] ?? '#e5e7eb',
        ];
        
        $settingsObj->set('shop_styles', json_encode($newStyles), 'shop');
        
        $_SESSION['flash_success'] = "Shop settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/shop_settings.php');
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
$s_sidebar_bg_color = $styles['sidebar_bg_color'] ?? '#ffffff';
$s_sidebar_text_color = $styles['sidebar_text_color'] ?? '#1f2937';
$s_filter_btn_bg_color = $styles['filter_btn_bg_color'] ?? '#1a3d32';
$s_filter_btn_text_color = $styles['filter_btn_text_color'] ?? '#ffffff';
$s_filter_btn_hover_bg_color = $styles['filter_btn_hover_bg_color'] ?? '#000000';
$s_filter_btn_hover_text_color = $styles['filter_btn_hover_text_color'] ?? '#ffffff';
$s_sort_dropdown_bg_color = $styles['sort_dropdown_bg_color'] ?? '#ffffff';
$s_sort_dropdown_text_color = $styles['sort_dropdown_text_color'] ?? '#1f2937';
$s_active_view_btn_color = $styles['active_view_btn_color'] ?? '#000000';
$s_hero_bg_color = $styles['hero_bg_color'] ?? '#f3f4f6';
$s_hero_text_color = $styles['hero_text_color'] ?? '#111827';
$s_input_bg_color = $styles['input_bg_color'] ?? '#ffffff';
$s_input_text_color = $styles['input_text_color'] ?? '#1f2937';
$s_results_count_text_color = $styles['results_count_text_color'] ?? '#4b5563';
$s_sidebar_border_color = $styles['sidebar_border_color'] ?? '#e5e7eb';
$s_sidebar_divider_color = $styles['sidebar_divider_color'] ?? '#e5e7eb';

// Fetch All Category Banner
$allCatBanner = $settingsObj->get('all_category_banner');

$pageTitle = 'Shop Page Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

    <form method="POST" enctype="multipart/form-data">
    <div class="sticky top-0 z-[100] bg-white border-b border-gray-200 -mx-6 px-6 py-4 mb-8 shadow-sm">
        <div class="container mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Shop Page Settings</h1>
                <p class="text-xs text-gray-500 mt-0.5">Customize the appearance of the main shop/products page.</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="<?php echo $baseUrl; ?>/shop" target="_blank" class="text-gray-600 hover:text-blue-600 font-bold text-sm transition transition-all flex items-center gap-2">
                     <i class="fas fa-external-link-alt"></i> View Page
                </a>
                <button type="submit" class="bg-blue-600 text-white px-8 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
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
            <!-- Styling Settings -->
            <div class="space-y-6">
                <!-- Shop Banner Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Shop Header Banner</h2>
                    </div>
                    <div class="p-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">All Category Banner</label>
                        <p class="text-xs text-gray-500 mb-4">This banner appears on the main shop page when no specific category is selected.</p>
                        
                        <div class="relative group cursor-pointer w-full h-40 border-2 <?php echo !empty($allCatBanner) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition" onclick="document.getElementById('allCatBannerInput').click()">
                            <input type="file" id="allCatBannerInput" name="setting_all_category_banner" class="hidden" onchange="previewBanner(this, 'previewAllCatBanner')">
                            
                            <div id="previewAllCatBanner" class="w-full h-full flex items-center justify-center">
                                <?php if($allCatBanner): ?>
                                    <img src="<?php echo getImageUrl($allCatBanner); ?>" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                        <i class="fas fa-camera text-white text-3xl opacity-0 group-hover:opacity-100 transition"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-gray-400">
                                        <i class="fas fa-image text-4xl mb-2"></i>
                                        <p class="text-sm">Click to upload banner</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Page & Sidebar</h2>
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
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sidebar Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sidebar_bg_color" value="<?php echo htmlspecialchars($s_sidebar_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sidebar_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sidebar Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sidebar_text_color" value="<?php echo htmlspecialchars($s_sidebar_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sidebar_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Input Background (Sidebar)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="input_bg_color" value="<?php echo htmlspecialchars($s_input_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_input_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Input Text Color (Sidebar)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="input_text_color" value="<?php echo htmlspecialchars($s_input_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_input_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sidebar Border Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sidebar_border_color" value="<?php echo htmlspecialchars($s_sidebar_border_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sidebar_border_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sidebar Divider Line</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sidebar_divider_color" value="<?php echo htmlspecialchars($s_sidebar_divider_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sidebar_divider_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Breadcrumbs & Category Title (Hero)</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Header Background Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="hero_bg_color" value="<?php echo htmlspecialchars($s_hero_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_hero_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Hero Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="hero_text_color" value="<?php echo htmlspecialchars($s_hero_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_hero_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Filter "Apply" Button</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="filter_btn_bg_color" value="<?php echo htmlspecialchars($s_filter_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_filter_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="filter_btn_text_color" value="<?php echo htmlspecialchars($s_filter_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_filter_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Hover Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="filter_btn_hover_bg_color" value="<?php echo htmlspecialchars($s_filter_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_filter_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Hover Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="filter_btn_hover_text_color" value="<?php echo htmlspecialchars($s_filter_btn_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_filter_btn_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Controls & Sorting</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Active View Icon</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="active_view_btn_color" value="<?php echo htmlspecialchars($s_active_view_btn_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_active_view_btn_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sort Dropdown Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sort_dropdown_bg_color" value="<?php echo htmlspecialchars($s_sort_dropdown_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sort_dropdown_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sort Dropdown Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="sort_dropdown_text_color" value="<?php echo htmlspecialchars($s_sort_dropdown_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_sort_dropdown_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Results Count Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="results_count_text_color" value="<?php echo htmlspecialchars($s_results_count_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_results_count_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
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
                        Shop Live Preview
                    </h2>
                    
                    <div id="shopPreview" class="rounded-xl border border-gray-200 overflow-hidden shadow-2xl p-6 transition-all duration-300" style="background-color: <?php echo $s_page_bg_color; ?>;">
                        <div class="flex gap-4">
                            <!-- Sidebar Preview Area -->
                            <div id="prevSidebar" class="w-1/3 p-4 rounded-xl border transition-all duration-300" style="background-color: <?php echo $s_sidebar_bg_color; ?>; color: <?php echo $s_sidebar_text_color; ?>; border-color: <?php echo $s_sidebar_border_color; ?>;">
                                <h4 class="font-bold text-[10px] mb-4 border-b pb-2 uppercase tracking-wider" style="border-color: <?php echo $s_sidebar_divider_color; ?>;">Filters</h4>
                                <div class="space-y-3 mb-6">
                                    <div class="h-1.5 w-full bg-current opacity-10 rounded"></div>
                                    <div class="h-1.5 w-2/3 bg-current opacity-10 rounded"></div>
                                </div>
                                <div class="flex gap-2 mb-6">
                                    <input type="text" value="0" class="w-1/2 text-[8px] p-1.5 rounded border transition-all" style="background-color: <?php echo $s_input_bg_color; ?>; color: <?php echo $s_input_text_color; ?>;" readonly>
                                    <input type="text" value="100" class="w-1/2 text-[8px] p-1.5 rounded border transition-all" style="background-color: <?php echo $s_input_bg_color; ?>; color: <?php echo $s_input_text_color; ?>;" readonly>
                                </div>
                                <div id="prevFilterBtn" class="py-2.5 px-4 transition-all duration-300 w-full text-center text-[10px] font-bold rounded-lg shadow-sm" style="background-color: <?php echo $s_filter_btn_bg_color; ?>; color: <?php echo $s_filter_btn_text_color; ?>;">
                                    APPLY FILTERS
                                </div>
                            </div>

                             <!-- Products Area Preview -->
                            <div class="flex-1">
                                <!-- Hero Preview -->
                                <div id="prevHero" class="rounded-xl p-5 mb-5 text-center transition-all duration-300 shadow-sm relative overflow-hidden" style="background-color: <?php echo $s_hero_bg_color; ?>; color: <?php echo $s_hero_text_color; ?>; background-image: url('<?php echo $allCatBanner ? getImageUrl($allCatBanner) : ''; ?>'); background-size: cover; background-position: center; min-height: 120px; display: flex; flex-direction: column; justify-content: center;">
                                    <!-- Overlay to ensure text readability -->
                                    <div id="prevHeroOverlay" class="absolute inset-0 bg-black transition-all duration-300 pointer-events-none <?php echo $allCatBanner ? 'bg-opacity-20' : 'bg-opacity-0'; ?>"></div>
                                    <div class="relative z-10">
                                        <nav class="text-[9px] mb-1 opacity-80 uppercase tracking-widest font-bold">
                                            Home &rsaquo; <span>Category</span>
                                        </nav>
                                        <h1 class="text-xl font-bold leading-tight">Category Title</h1>
                                        <p class="text-[9px] mt-1 opacity-70">Curated collection for you</p>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mb-5">
                                    <div id="prevResultsCount" class="text-[9px] font-medium" style="color: <?php echo $s_results_count_text_color; ?>;">2 products found</div>
                                    <div class="flex gap-2">
                                        <div id="prevActiveBtn" class="w-7 h-7 rounded-lg border flex items-center justify-center text-[10px] shadow-sm" style="background-color: <?php echo $s_active_view_btn_color; ?>; color: #ffffff; border-color: <?php echo $s_active_view_btn_color; ?>;">
                                            <i class="fas fa-th"></i>
                                        </div>
                                        <div id="prevSort" class="w-24 h-7 border rounded-lg px-2 flex items-center justify-between text-[9px] shadow-sm transition-all" style="background-color: <?php echo $s_sort_dropdown_bg_color; ?>; color: <?php echo $s_sort_dropdown_text_color; ?>;">
                                            <span>Sort by: Newest</span>
                                            <i class="fas fa-chevron-down opacity-50"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-white p-2 rounded-xl border border-gray-100 shadow-sm aspect-[3/4] flex flex-col gap-2">
                                        <div class="bg-gray-50 rounded-lg flex-1"></div>
                                        <div class="h-2 w-3/4 bg-gray-100 rounded"></div>
                                        <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                                    </div>
                                    <div class="bg-white p-2 rounded-xl border border-gray-100 shadow-sm aspect-[3/4] flex flex-col gap-2">
                                        <div class="bg-gray-50 rounded-lg flex-1"></div>
                                        <div class="h-2 w-3/4 bg-gray-100 rounded"></div>
                                        <div class="h-2 w-1/2 bg-gray-100 rounded"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <style id="previewStyles">
                        #prevFilterBtn:hover {
                            background-color: <?php echo $s_filter_btn_hover_bg_color; ?> !important;
                            color: <?php echo $s_filter_btn_hover_text_color; ?> !important;
                        }
                    </style>
                 </div>
            </div>
        </div>

        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    const textInputs = document.querySelectorAll('input[type="text"]');
    
    // Banner Preview Function (needs to be global or accessible)
    window.previewBanner = function(input, containerId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                const container = document.getElementById(containerId);
                container.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                         <i class="fas fa-camera text-white text-3xl opacity-0 group-hover:opacity-100 transition"></i>
                    </div>
                `;
                container.parentElement.classList.remove('border-dashed', 'border-gray-300');
                container.parentElement.classList.add('border-gray-200');

                // Also update the hero preview background and overlay
                const heroPrev = document.getElementById('prevHero');
                const heroOverlay = document.getElementById('prevHeroOverlay');
                if (heroPrev) {
                    heroPrev.style.backgroundImage = `url('${e.target.result}')`;
                    if (heroOverlay) {
                        heroOverlay.classList.remove('bg-opacity-0');
                        heroOverlay.classList.add('bg-opacity-20');
                    }
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    const previewEl = document.getElementById('shopPreview');
    const sidebarEl = document.getElementById('prevSidebar');
    const filterBtn = document.getElementById('prevFilterBtn');
    const activeViewBtn = document.getElementById('prevActiveBtn');
    const sortEl = document.getElementById('prevSort');
    const styleTag = document.getElementById('previewStyles');

    function updatePreview() {
        const settings = {};
        document.querySelectorAll('form input[name]').forEach(input => {
            if (input.type === 'color' || input.type === 'text') {
                settings[input.name] = input.value;
            }
        });

        // Main Page Background
        const previewEl = document.getElementById('shopPreview');
        if (previewEl && settings.page_bg_color) {
            previewEl.style.backgroundColor = settings.page_bg_color;
        }

        // Sidebar
        const sidebarEl = document.getElementById('prevSidebar');
        if (sidebarEl) {
            sidebarEl.style.backgroundColor = settings.sidebar_bg_color;
            sidebarEl.style.color = settings.sidebar_text_color;
            sidebarEl.style.borderColor = settings.sidebar_border_color;
            
            // Sub-elements in sidebar
            const divider = sidebarEl.querySelector('.border-b');
            if (divider) divider.style.borderColor = settings.sidebar_divider_color;
            
            sidebarEl.querySelectorAll('input').forEach(inp => {
                inp.style.backgroundColor = settings.input_bg_color;
                inp.style.color = settings.input_text_color;
            });
        }
        
        // Filter Button
        const filterBtn = document.getElementById('prevFilterBtn');
        if (filterBtn) {
            filterBtn.style.backgroundColor = settings.filter_btn_bg_color;
            filterBtn.style.color = settings.filter_btn_text_color;
        }
        
        // Controls
        const activeViewBtn = document.getElementById('prevActiveBtn');
        if (activeViewBtn) {
            activeViewBtn.style.backgroundColor = settings.active_view_btn_color;
            activeViewBtn.style.borderColor = settings.active_view_btn_color;
        }
        
        const sortEl = document.getElementById('prevSort');
        if (sortEl) {
            sortEl.style.backgroundColor = settings.sort_dropdown_bg_color;
            sortEl.style.color = settings.sort_dropdown_text_color;
        }

        // Hero Section
        const heroEl = document.getElementById('prevHero');
        if (heroEl) {
            heroEl.style.backgroundColor = settings.hero_bg_color;
            heroEl.style.color = settings.hero_text_color;
            heroEl.querySelectorAll('nav, h1, p, span').forEach(el => {
                el.style.color = settings.hero_text_color;
            });
        }

        // Results Count
        const resultsCountEl = document.getElementById('prevResultsCount');
        if (resultsCountEl) {
            resultsCountEl.style.color = settings.results_count_text_color;
        }

        // Hover Styles via Style Tag
        const styleTag = document.getElementById('previewStyles');
        if (styleTag) {
            styleTag.innerHTML = `
                #prevFilterBtn:hover {
                    background-color: \${settings.filter_btn_hover_bg_color} !important;
                    color: \${settings.filter_btn_hover_text_color} !important;
                }
            `;
        }
    }

    // Attach listeners to ALL relevant inputs
    document.querySelectorAll('input[type="color"]').forEach(colorInput => {
        const textInput = colorInput.nextElementSibling;
        
        // Update from Color Picker
        colorInput.addEventListener('input', () => {
            if (textInput) textInput.value = colorInput.value.toUpperCase();
            updatePreview();
        });

        // Update from Text Input
        if (textInput && textInput.type === 'text') {
            textInput.addEventListener('input', () => {
                const val = textInput.value;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    colorInput.value = val;
                    updatePreview();
                }
            });
        }
    });

    // Initial call
    updatePreview();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
