<?php
ob_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php'; 

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();
if (!$auth->isAdmin()) {
    header('Location: ' . $baseUrl . '/admin/login.php');
    exit;
}

$db = Database::getInstance();
$settingsObj = new Settings();
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'heading_text' => $_POST['heading_text'] ?? 'Search Our Site',
        'trending_text' => $_POST['trending_text'] ?? 'Trending Search',
        'popular_heading_text' => $_POST['popular_heading_text'] ?? 'Popular Products',
        'overlay_bg_color' => $_POST['overlay_bg_color'] ?? '#ffffff',
        'heading_color' => $_POST['heading_color'] ?? '#000000',
        'general_text_color' => $_POST['general_text_color'] ?? '#374151',
        'input_text_color' => $_POST['input_text_color'] ?? '#000000',
        'card_bg_color' => $_POST['card_bg_color'] ?? '#ffffff',
        'card_border_color' => $_POST['card_border_color'] ?? '#e5e7eb',
        'card_heading_color' => $_POST['card_heading_color'] ?? '#111827',
        'card_sale_price_color' => $_POST['card_sale_price_color'] ?? '#1a3d32',
        'card_compare_price_color' => $_POST['card_compare_price_color'] ?? '#9ca3af',
        'view_all_bg_color' => $_POST['view_all_bg_color'] ?? '#000000',
        'view_all_text_color' => $_POST['view_all_text_color'] ?? '#ffffff',
        'view_all_hover_bg_color' => $_POST['view_all_hover_bg_color'] ?? '#333333',
        'view_all_hover_text_color' => $_POST['view_all_hover_text_color'] ?? '#ffffff',
    ];

    $saved = $settingsObj->set('search_section_settings', json_encode($settings), 'appearance');

    // Check if AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        if ($saved) {
            echo json_encode(['success' => true, 'message' => 'Search section settings updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save settings.']);
        }
        exit;
    }

    if ($saved) {
        $success = "Search section settings updated successfully!";
    } else {
        $error = "Failed to save settings.";
    }
}

// Fetch Current Settings
$savedSettingsJson = $settingsObj->get('search_section_settings', '{}');
$savedSettings = json_decode($savedSettingsJson, true);

// Defaults
$val_heading_text = $savedSettings['heading_text'] ?? 'Search Our Site';
$val_trending_text = $savedSettings['trending_text'] ?? 'Trending Search';
$val_popular_heading_text = $savedSettings['popular_heading_text'] ?? 'Popular Products';
$val_overlay_bg_color = $savedSettings['overlay_bg_color'] ?? '#ffffff';
$val_heading_color = $savedSettings['heading_color'] ?? '#000000';
$val_general_text_color = $savedSettings['general_text_color'] ?? '#374151';
$val_input_text_color = $savedSettings['input_text_color'] ?? '#000000';
$val_card_bg_color = $savedSettings['card_bg_color'] ?? '#ffffff';
$val_card_border_color = $savedSettings['card_border_color'] ?? '#e5e7eb';
$val_card_heading_color = $savedSettings['card_heading_color'] ?? '#111827';
$val_card_sale_price_color = $savedSettings['card_sale_price_color'] ?? '#1a3d32';
$val_card_compare_price_color = $savedSettings['card_compare_price_color'] ?? '#9ca3af';
$val_view_all_bg_color = $savedSettings['view_all_bg_color'] ?? '#000000';
$val_view_all_text_color = $savedSettings['view_all_text_color'] ?? '#ffffff';
$val_view_all_hover_bg_color = $savedSettings['view_all_hover_bg_color'] ?? '#333333';
$val_view_all_hover_text_color = $savedSettings['view_all_hover_text_color'] ?? '#ffffff';

$pageTitle = 'Search Section Styling';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto">
    <!-- Sticky Header -->
    <div class="mb-6 flex justify-between items-end sticky top-0 bg-[#f7f8fc] py-4 z-50 pt-0">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 pt-4 pl-2">Search Section Styling</h1>
            <p class="text-sm text-gray-500 mt-1 pl-2">
                <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
                Customization > 
                Search Styling
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" id="saveSearchBtn" form="searchStylingForm" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i> <span>Save Changes</span>
            </button>
        </div>
    </div>

    <!-- Success/Error Alert -->
    <div id="statusAlert" class="hidden p-4 mb-6 rounded shadow-sm flex items-center justify-between animate-fade-in">
        <div class="flex items-center">
            <i class="status-icon mr-3 text-xl"></i>
            <p class="status-msg"></p>
        </div>
        <button onclick="document.getElementById('statusAlert').classList.add('hidden')" class="hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center justify-between animate-fade-in" id="alert-success">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3 text-xl"></i>
                <p><?php echo $success; ?></p>
            </div>
            <button onclick="document.getElementById('alert-success').remove()" class="text-green-700 hover:text-green-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 pb-20">
        <!-- Form Column (Left) -->
        <div class="lg:col-span-12 xl:col-span-5 order-2 xl:order-1">
            <form id="searchStylingForm" method="POST" class="space-y-6">
                <input type="hidden" name="ajax" value="1">
                <!-- Text Content Settings -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center gap-3 mb-6 border-b pb-4">
                        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-font"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Content Settings</h3>
                            <p class="text-xs text-gray-500">Labels and headings text</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="input-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Main Overlay Title</label>
                            <input type="text" name="heading_text" id="in_heading_text" value="<?php echo htmlspecialchars($val_heading_text); ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        </div>

                        <div class="input-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Trending Label</label>
                            <input type="text" name="trending_text" id="in_trending_text" value="<?php echo htmlspecialchars($val_trending_text); ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        </div>

                        <div class="input-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Popular Products Label</label>
                            <input type="text" name="popular_heading_text" id="in_popular_heading_text" value="<?php echo htmlspecialchars($val_popular_heading_text); ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        </div>
                    </div>
                </div>

                <!-- Main Layout Styles -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center gap-3 mb-6 border-b pb-4">
                        <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Overlay Colors</h3>
                            <p class="text-xs text-gray-500">Global search section colors</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Overlay Background</label>
                                <p class="text-[11px] text-gray-500">Backdrop color of the search</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_overlay_bg_color"></span>
                                <input type="color" name="overlay_bg_color" id="in_overlay_bg_color" value="<?php echo $val_overlay_bg_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Heading Color</label>
                                <p class="text-[11px] text-gray-500">Color for title and subtitles</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_heading_color"></span>
                                <input type="color" name="heading_color" id="in_heading_color" value="<?php echo $val_heading_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">General Text Color</label>
                                <p class="text-[11px] text-gray-500">Color for labels and trending tags</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_general_text_color"></span>
                                <input type="color" name="general_text_color" id="in_general_text_color" value="<?php echo $val_general_text_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Input Field Text Color</label>
                                <p class="text-[11px] text-gray-500">Color for text typed in search bar</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_input_text_color"></span>
                                <input type="color" name="input_text_color" id="in_input_text_color" value="<?php echo $val_input_text_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- View All Results Button Styles -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center gap-3 mb-6 border-b pb-4">
                        <div class="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-external-link-alt"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">"View All" Button Styles</h3>
                            <p class="text-xs text-gray-500">Customize the search results button</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                       <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Background</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="view_all_bg_color" id="in_view_all_bg_color" value="<?php echo $val_view_all_bg_color; ?>" class="w-8 h-8 border-0 p-0 bg-white cursor-pointer rounded shadow-sm color-picker">
                                <span class="text-[9px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_view_all_bg_color"></span>
                            </div>
                        </div>
                        <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="view_all_text_color" id="in_view_all_text_color" value="<?php echo $val_view_all_text_color; ?>" class="w-8 h-8 border-0 p-0 bg-white cursor-pointer rounded shadow-sm color-picker">
                                <span class="text-[9px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_view_all_text_color"></span>
                            </div>
                        </div>
                        <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Hover BG</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="view_all_hover_bg_color" id="in_view_all_hover_bg_color" value="<?php echo $val_view_all_hover_bg_color; ?>" class="w-8 h-8 border-0 p-0 bg-white cursor-pointer rounded shadow-sm color-picker">
                                <span class="text-[9px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_view_all_hover_bg_color"></span>
                            </div>
                        </div>
                        <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Hover Text</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="view_all_hover_text_color" id="in_view_all_hover_text_color" value="<?php echo $val_view_all_hover_text_color; ?>" class="w-8 h-8 border-0 p-0 bg-white cursor-pointer rounded shadow-sm color-picker">
                                <span class="text-[9px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_view_all_hover_text_color"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Card Styles -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center gap-3 mb-6 border-b pb-4">
                        <div class="w-10 h-10 bg-green-50 text-green-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-th-large"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Product Card Styles</h3>
                            <p class="text-xs text-gray-500">Customize card results display</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Card Background</label>
                                <p class="text-[11px] text-gray-500">Background of result cards</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_card_bg_color"></span>
                                <input type="color" name="card_bg_color" id="in_card_bg_color" value="<?php echo $val_card_bg_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Card Border Color</label>
                                <p class="text-[11px] text-gray-500">Color for card outlines</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_card_border_color"></span>
                                <input type="color" name="card_border_color" id="in_card_border_color" value="<?php echo $val_card_border_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Result Title Color</label>
                                <p class="text-[11px] text-gray-500">Color for product names</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_card_heading_color"></span>
                                <input type="color" name="card_heading_color" id="in_card_heading_color" value="<?php echo $val_card_heading_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Sale Price Color</label>
                                <p class="text-[11px] text-gray-500">Color for discounted price</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_card_sale_price_color"></span>
                                <input type="color" name="card_sale_price_color" id="in_card_sale_price_color" value="<?php echo $val_card_sale_price_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold text-gray-700">Compare Price Color</label>
                                <p class="text-[11px] text-gray-500">Color for struck-through price</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-mono uppercase hex-label" data-hex-for="in_card_compare_price_color"></span>
                                <input type="color" name="card_compare_price_color" id="in_card_compare_price_color" value="<?php echo $val_card_compare_price_color; ?>" class="w-10 h-10 border-0 p-0.5 bg-white cursor-pointer rounded-lg shadow-sm color-picker">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Preview Column (Right) -->
        <div class="lg:col-span-12 xl:col-span-7 order-1 xl:order-2">
            <div class="sticky top-24">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-sm"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Live Preview</h2>
                </div>
                
                <!-- Front-end Simulation -->
                <div class="rounded-2xl shadow-xl overflow-hidden border border-gray-200 bg-white transition-all duration-300" id="previewContainer">
                    <!-- Overlay Content Preview -->
                    <div id="prevOverlay" class="p-8 min-h-[500px] flex flex-col items-center transition-colors duration-300">
                        <h2 id="prevHeading" class="text-3xl font-serif text-center mb-8">Search Our Site</h2>
                        
                        <div class="w-full max-w-2xl relative mb-10">
                            <div class="relative group border border-gray-300 rounded-full px-4 flex items-center bg-white/50">
                                <input type="text" id="prevInput" value="Test Search..." 
                                       class="w-full px-4 py-3 text-lg font-light bg-transparent focus:outline-none placeholder-gray-400">
                                <i class="fas fa-search text-xl mr-2 text-gray-400" id="prevSearchIcon"></i>
                            </div>
                        </div>

                        <div class="mb-10 text-center w-full">
                            <h3 id="prevTrendingTitle" class="text-lg font-serif mb-6">Trending Search</h3>
                            <div class="flex flex-wrap justify-center gap-3" id="prevTrendingContainer">
                                <a href="#" class="prevTrendingTag px-6 py-2 rounded-full border border-gray-200 text-sm transition-all">Kitchenware</a>
                                <a href="#" class="prevTrendingTag px-6 py-2 rounded-full border border-gray-200 text-sm transition-all">Cookware</a>
                                <a href="#" class="prevTrendingTag px-6 py-2 rounded-full border border-gray-200 text-sm transition-all">Accessories</a>
                            </div>
                        </div>

                        <div class="w-full mb-8">
                            <h3 id="prevPopularTitle" class="text-lg font-serif mb-8 text-center">Popular Products</h3>
                            <div class="flex justify-center gap-6 overflow-hidden">
                                <!-- Sample Card 1 -->
                                <div class="prevProductCard w-48 flex-shrink-0 border rounded-xl shadow-sm p-3 transition-colors duration-300">
                                    <div class="aspect-[3/4] rounded-lg mb-3 bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-300 text-3xl"></i>
                                    </div>
                                    <h4 class="prevCardName font-medium text-sm mb-1 truncate">Premium Dutch Oven</h4>
                                    <div class="flex items-center gap-2">
                                        <div class="prevCardSalePrice font-semibold text-sm">₹4,999.00</div>
                                        <div class="prevCardComparePrice text-xs line-through">₹5,999.00</div>
                                    </div>
                                </div>
                                <!-- Sample Card 2 -->
                                <div class="prevProductCard w-48 flex-shrink-0 border rounded-xl shadow-sm p-3 transition-colors duration-300">
                                    <div class="aspect-[3/4] rounded-lg mb-3 bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-300 text-3xl"></i>
                                    </div>
                                    <h4 class="prevCardName font-medium text-sm mb-1 truncate">Cast Iron Skillet</h4>
                                    <div class="flex items-center gap-2">
                                        <div class="prevCardSalePrice font-semibold text-sm">₹2,499.00</div>
                                        <div class="prevCardComparePrice text-xs line-through">₹2,999.00</div>
                                    </div>
                                </div>
                                <!-- Sample Card 3 -->
                                <div class="prevProductCard hidden md:block w-48 flex-shrink-0 border rounded-xl shadow-sm p-3 transition-colors duration-300">
                                    <div class="aspect-[3/4] rounded-lg mb-3 bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-300 text-3xl"></i>
                                    </div>
                                    <h4 class="prevCardName font-medium text-sm mb-1 truncate">Chef's Knife Set</h4>
                                    <div class="flex items-center gap-2">
                                        <div class="prevCardSalePrice font-semibold text-sm">₹8,500.00</div>
                                        <div class="prevCardComparePrice text-xs line-through">₹9,999.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center mt-4">
                            <button id="prevViewAllBtn" class="px-8 py-3 rounded-full font-semibold text-sm transition-all duration-300 shadow-md">
                                View All Results
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-100 flex gap-3 text-sm text-blue-700">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <p>This preview provides a <b>live representation</b> of how the search overlay will appear on your store front-end.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('searchStylingForm');
    const saveBtn = document.getElementById('saveSearchBtn');
    const alertBox = document.getElementById('statusAlert');
    
    function showNotification(success, message) {
        alertBox.classList.remove('hidden', 'bg-green-100', 'bg-red-100', 'text-green-700', 'text-red-700', 'border-l-4', 'border-green-500', 'border-red-500');
        
        if (success) {
            alertBox.classList.add('bg-green-100', 'text-green-700', 'border-l-4', 'border-green-500');
            alertBox.querySelector('.status-icon').className = 'fas fa-check-circle mr-3 text-xl status-icon';
        } else {
            alertBox.classList.add('bg-red-100', 'text-red-700', 'border-l-4', 'border-red-500');
            alertBox.querySelector('.status-icon').className = 'fas fa-exclamation-circle mr-3 text-xl status-icon';
        }
        
        alertBox.querySelector('.status-msg').innerText = message;
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            alertBox.classList.add('hidden');
        }, 5000);
    }

    function updateLivePreview() {
        // Collect values
        const headingText = document.getElementById('in_heading_text').value;
        const trendingText = document.getElementById('in_trending_text').value;
        const popularText = document.getElementById('in_popular_heading_text').value;
        
        const overlayBg = document.getElementById('in_overlay_bg_color').value;
        const headingColor = document.getElementById('in_heading_color').value;
        const generalTextColor = document.getElementById('in_general_text_color').value;
        const inputTextBoxColor = document.getElementById('in_input_text_color').value;
        
        const cardBg = document.getElementById('in_card_bg_color').value;
        const cardBorder = document.getElementById('in_card_border_color').value;
        const cardHeadingColor = document.getElementById('in_card_heading_color').value;
        const cardSalePriceColor = document.getElementById('in_card_sale_price_color').value;
        const cardComparePriceColor = document.getElementById('in_card_compare_price_color').value;

        const viewAllBg = document.getElementById('in_view_all_bg_color').value;
        const viewAllText = document.getElementById('in_view_all_text_color').value;
        const viewAllHoverBg = document.getElementById('in_view_all_hover_bg_color').value;
        const viewAllHoverText = document.getElementById('in_view_all_hover_text_color').value;

        // Update Text
        document.getElementById('prevHeading').innerText = headingText;
        document.getElementById('prevTrendingTitle').innerText = trendingText;
        document.getElementById('prevPopularTitle').innerText = popularText;

        // Update Main Styles
        document.getElementById('previewContainer').style.backgroundColor = overlayBg;
        document.getElementById('prevOverlay').style.backgroundColor = overlayBg;
        
        document.getElementById('prevHeading').style.color = headingColor;
        document.getElementById('prevTrendingTitle').style.color = headingColor;
        document.getElementById('prevPopularTitle').style.color = headingColor;
        
        document.getElementById('prevInput').style.color = inputTextBoxColor;
        document.getElementById('prevSearchIcon').style.color = generalTextColor;

        // Update Tags
        const tags = document.querySelectorAll('.prevTrendingTag');
        tags.forEach(tag => {
            tag.style.color = generalTextColor;
            tag.style.borderColor = generalTextColor + '44';
        });

        // Update Cards
        const cards = document.querySelectorAll('.prevProductCard');
        cards.forEach(card => {
            card.style.backgroundColor = cardBg;
            card.style.borderColor = cardBorder;
            card.querySelector('.prevCardName').style.color = cardHeadingColor;
            card.querySelector('.prevCardSalePrice').style.color = cardSalePriceColor;
            card.querySelector('.prevCardComparePrice').style.color = cardComparePriceColor;
        });

        // Update View All Button
        const viewAllBtn = document.getElementById('prevViewAllBtn');
        viewAllBtn.style.backgroundColor = viewAllBg;
        viewAllBtn.style.color = viewAllText;
        
        viewAllBtn.onmouseenter = () => {
            viewAllBtn.style.backgroundColor = viewAllHoverBg;
            viewAllBtn.style.color = viewAllHoverText;
        };
        viewAllBtn.onmouseleave = () => {
            viewAllBtn.style.backgroundColor = viewAllBg;
            viewAllBtn.style.color = viewAllText;
        };

        // Update Hex Labels
        document.querySelectorAll('.hex-label').forEach(label => {
            const pickerId = label.getAttribute('data-hex-for');
            const picker = document.getElementById(pickerId);
            if (picker) {
                label.innerText = picker.value.toUpperCase();
            }
        });
    }

    // AJAX Form Submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Add loading state
        const originalText = saveBtn.querySelector('span').innerText;
        saveBtn.disabled = true;
        saveBtn.classList.add('opacity-75', 'cursor-not-allowed');
        saveBtn.querySelector('i').className = 'fas fa-spinner fa-spin';
        saveBtn.querySelector('span').innerText = 'Saving...';

        const formData = new FormData(this);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showNotification(data.success, data.message);
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(false, 'An error occurred while saving.');
        })
        .finally(() => {
            // Restore button
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            saveBtn.querySelector('i').className = 'fas fa-save';
            saveBtn.querySelector('span').innerText = originalText;
        });
    });

    // Attach events for real-time preview
    form.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', updateLivePreview);
    });

    // Initial trigger
    updateLivePreview();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
