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
    $styles = [
        'bg_color' => $_POST['bg_color'] ?? '#ffffff', 
        'card_bg_color' => $_POST['card_bg_color'] ?? '#ffffff',
        'card_title_color' => $_POST['card_title_color'] ?? '#1F2937',
        'price_color' => $_POST['price_color'] ?? '#1a3d32',
        'compare_price_color' => $_POST['compare_price_color'] ?? '#9ca3af',
        'badge_bg_color' => $_POST['badge_bg_color'] ?? '#ef4444',
        'badge_text_color' => $_POST['badge_text_color'] ?? '#ffffff',
        
        // Action Buttons
        'btn_bg_color' => $_POST['btn_bg_color'] ?? '#ffffff',
        'btn_icon_color' => $_POST['btn_icon_color'] ?? '#000000',
        'btn_hover_bg_color' => $_POST['btn_hover_bg_color'] ?? '#000000',
        'btn_hover_icon_color' => $_POST['btn_hover_icon_color'] ?? '#ffffff',
        'btn_active_bg_color' => $_POST['btn_active_bg_color'] ?? '#000000',
        'btn_active_icon_color' => $_POST['btn_active_icon_color'] ?? '#ffffff',
        
        // Add to Cart Button (Specific)
        'atc_btn_bg_color' => $_POST['atc_btn_bg_color'] ?? '#1a3d32',
        'atc_btn_text_color' => $_POST['atc_btn_text_color'] ?? '#ffffff',
        'atc_btn_hover_bg_color' => $_POST['atc_btn_hover_bg_color'] ?? '#000000',
        'atc_btn_hover_text_color' => $_POST['atc_btn_hover_text_color'] ?? '#ffffff',
        
        // Tooltips
        'tooltip_bg_color' => $_POST['tooltip_bg_color'] ?? '#000000',
        'tooltip_text_color' => $_POST['tooltip_text_color'] ?? '#ffffff'
    ];

    if ($settingsObj->set('global_card_styles', json_encode($styles), 'appearance')) {
        $success = "Global product card settings updated successfully!";
    } else {
        $error = "Failed to save settings.";
    }
}

// Fetch Current Settings
$savedStylesJson = $settingsObj->get('global_card_styles', '{}');
$savedStyles = json_decode($savedStylesJson, true);

// Defaults (matching Best Selling defaults)
$s_card_bg_color = $savedStyles['card_bg_color'] ?? '#ffffff';
$s_card_title_color = $savedStyles['card_title_color'] ?? '#1F2937';
$s_price_color = $savedStyles['price_color'] ?? '#1a3d32';
$s_compare_price_color = $savedStyles['compare_price_color'] ?? '#9ca3af';
$s_badge_bg_color = $savedStyles['badge_bg_color'] ?? '#ef4444';
$s_badge_text_color = $savedStyles['badge_text_color'] ?? '#ffffff';

$s_btn_bg_color = $savedStyles['btn_bg_color'] ?? '#ffffff';
$s_btn_icon_color = $savedStyles['btn_icon_color'] ?? '#000000';
$s_btn_hover_bg_color = $savedStyles['btn_hover_bg_color'] ?? '#000000';
$s_btn_hover_icon_color = $savedStyles['btn_hover_icon_color'] ?? '#ffffff';
$s_btn_active_bg_color = $savedStyles['btn_active_bg_color'] ?? '#000000';
$s_btn_active_icon_color = $savedStyles['btn_active_icon_color'] ?? '#ffffff';

$s_atc_btn_bg_color = $savedStyles['atc_btn_bg_color'] ?? '#1a3d32';
$s_atc_btn_text_color = $savedStyles['atc_btn_text_color'] ?? '#ffffff';
$s_atc_btn_hover_bg_color = $savedStyles['atc_btn_hover_bg_color'] ?? '#000000';
$s_atc_btn_hover_text_color = $savedStyles['atc_btn_hover_text_color'] ?? '#ffffff';

$s_tooltip_bg_color = $savedStyles['tooltip_bg_color'] ?? '#000000';
$s_tooltip_text_color = $savedStyles['tooltip_text_color'] ?? '#ffffff';

$pageTitle = 'Global Card Design';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto">
    <!-- Sticky Header -->
    <div class="mb-6 flex justify-between items-end sticky top-0 bg-[#f7f8fc] py-4 z-50 pt-0">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 pt-4 pl-2">Global Card Design</h1>
            <p class="text-sm text-gray-500 mt-1 pl-2">
                <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
                <a href="<?php echo url('admin/settings.php'); ?>" class="hover:text-blue-600">Settings</a> > 
                Global Card Design
            </p>
        </div>
        <div class="flex items-center gap-3">
             <button type="button" onclick="window.location.href='<?php echo url('admin/dashboard.php'); ?>'" class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 bg-white text-gray-700 font-medium transition-colors">Cancel</button>
            <button type="submit" form="settingsForm" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-sm flex items-center gap-2 btn-loading">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>

    <style>
        .product-tooltip {
            position: absolute;
            right: calc(100% + 8px);
            top: 50%;
            transform: translateY(-50%);
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 100 !important;
            pointer-events: none;
            font-weight: 500;
            background-color: <?php echo $s_tooltip_bg_color; ?>;
            color: <?php echo $s_tooltip_text_color; ?>;
        }
        .product-tooltip::after {
            content: "";
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-left-color: <?php echo $s_tooltip_bg_color; ?>;
        }
        .product-action-btn:hover .product-tooltip,
        .group:hover .product-tooltip {
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>

    <div class="px-6">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="settingsForm">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Settings Column -->
                <div class="space-y-6">
                    <!-- Global Card Style -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Global Product Card Style</h2>
                            <p class="text-sm text-gray-500">Define the default appearance for product cards across the store.</p>
                        </div>
                        
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Card Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="card_bg_color" value="<?php echo htmlspecialchars($s_card_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_card_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Product Title Color</label>
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
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Compare Price Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="compare_price_color" value="<?php echo htmlspecialchars($s_compare_price_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_compare_price_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Badge Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="badge_bg_color" value="<?php echo htmlspecialchars($s_badge_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_badge_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Badge Text</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="badge_text_color" value="<?php echo htmlspecialchars($s_badge_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_badge_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                             <h3 class="font-bold text-gray-800">Action Buttons</h3>
                            <p class="text-xs text-gray-500">Customize Wishlist, Quick View, and Add to Cart buttons.</p>
                        </div>
                        
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Normal State -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Background (Normal)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_bg_color" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Icon (Normal)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_icon_color" value="<?php echo htmlspecialchars($s_btn_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            
                            <!-- Hover State -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Background (Hover)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_hover_bg_color" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Icon (Hover)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_hover_icon_color" value="<?php echo htmlspecialchars($s_btn_hover_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>

                            <!-- Active State -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Background (Active/Filled)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_active_bg_color" value="<?php echo htmlspecialchars($s_btn_active_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_active_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Button Icon (Active/Filled)</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_active_icon_color" value="<?php echo htmlspecialchars($s_btn_active_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_active_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            
                            <!-- Tooltip -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Tooltip Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="tooltip_bg_color" value="<?php echo htmlspecialchars($s_tooltip_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_tooltip_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Tooltip Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="tooltip_text_color" value="<?php echo htmlspecialchars($s_tooltip_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_tooltip_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add to Cart Button -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <h3 class="font-bold text-gray-800">Add to Cart Button</h3>
                            <p class="text-xs text-gray-500">Specific styling for the main Add to Cart button.</p>
                        </div>
                        
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Normal State -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_bg_color" value="<?php echo htmlspecialchars($s_atc_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Text/Icon Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_text_color" value="<?php echo htmlspecialchars($s_atc_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            
                            <!-- Hover State -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_hover_bg_color" value="<?php echo htmlspecialchars($s_atc_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Text/Icon Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="atc_btn_hover_text_color" value="<?php echo htmlspecialchars($s_atc_btn_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_atc_btn_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Column -->
                <div>
                     <div class="sticky top-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-eye text-blue-600"></i>
                            Live Preview
                        </h2>
                        
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-8 shadow-inner overflow-hidden flex flex-row flex-wrap justify-center gap-8">
                            
                            <!-- Standard Card Preview -->
                            <div id="previewCard" class="w-64 flex-shrink-0 bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden relative group transition-all duration-300">
                                <div class="relative overflow-hidden h-64 bg-gray-100">
                                    <!-- Discount Badge -->
                                    <span id="prevBadge" class="absolute top-2 left-2 px-2 py-1 text-xs font-bold rounded">-20%</span>
                                    
                                    <!-- Wishlist Button -->
                                    <button id="prevWishlistBtn" class="absolute top-2 right-2 w-8 h-8 rounded-full flex items-center justify-center transition shadow-sm z-20 group" type="button">
                                        <i class="far fa-heart"></i>
                                        <span class="product-tooltip">Add to Wishlist</span>
                                    </button>
                                    
                                    <!-- Hidden Hover Actions -->
                                    <div class="absolute top-12 right-2 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <button id="prevQvBtn" class="w-8 h-8 rounded-full flex items-center justify-center transition shadow-sm group relative" type="button">
                                            <i class="fas fa-eye"></i>
                                            <span class="product-tooltip">Quick View</span>
                                        </button>
                                        <button id="prevAtcBtnIcon" class="w-8 h-8 rounded-full flex items-center justify-center transition shadow-sm group relative" type="button">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span class="product-tooltip">Add to Cart</span>
                                        </button>
                                    </div>

                                    <img src="https://placehold.co/400x400/f3f4f6/a3a3a3?text=Product" class="w-full h-full object-cover">
                                </div>
                                
                                <div class="p-4">
                                    <h3 id="prevTitle" class="text-sm font-semibold mb-1 truncate">Sample Product Title</h3>
                                    
                                    <!-- Rating Stars (Static Gold) -->
                                    <div class="flex text-yellow-400 text-xs mb-2">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    </div>

                                    <div class="flex items-center gap-2 mb-3">
                                        <span id="prevPrice" class="font-bold">$199.00</span>
                                        <span id="prevComparePrice" class="text-sm line-through">$249.00</span>
                                    </div>

                                    <!-- Main ATC Button (Simulating Wishlist layout or special layouts) -->
                                    <button id="prevMainAtcBtn" class="w-full py-2 rounded text-xs font-bold flex items-center justify-center gap-2 transition-all" type="button">
                                        <i class="fas fa-shopping-cart"></i> ADD TO CART
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Wishlist Style Preview (Explicitly using ATC Button styles) -->
                            <div id="previewCardWishlist" class="w-64 flex-shrink-0 bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden relative group transition-all duration-300">
                                 <div class="relative overflow-hidden h-64 bg-gray-100">
                                    <!-- Remove Button -->
                                    <div class="absolute top-2 right-2 z-20">
                                        <button id="prevRemoveBtn" class="w-8 h-8 rounded-full flex items-center justify-center transition shadow-sm group relative product-action-btn" type="button">
                                            <i class="fas fa-times"></i>
                                            <span class="product-tooltip">Remove</span>
                                        </button>
                                    </div>
                                     <img src="https://placehold.co/400x400/f3f4f6/a3a3a3?text=Wishlist+Item" class="w-full h-full object-cover">
                                </div>
                                 <div class="p-4 flex flex-col h-[160px]"> <!-- Fixed height for alignment -->
                                    <h3 id="prevTitleW" class="text-sm font-semibold mb-1 truncate">Wishlist Product</h3>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span id="prevPriceW" class="font-bold">$199.00</span>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <button id="prevMainAtcBtnW" class="w-full py-2.5 rounded text-xs font-bold flex items-center justify-center gap-2 transition-all" type="button">
                                            <i class="fas fa-shopping-cart"></i> ADD TO CART
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                        
                        <div class="mt-4 text-center text-xs text-gray-500">
                             Hover over the cards to see hover effects. Adjust settings on the left to specific updates.
                        </div>

                     </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    (function() {
        const container = document.getElementById('settingsForm') || document;

        function updatePreview() {
            // Get values from form inputs
            const getVal = (name) => {
                const el = container.querySelector(`input[name="${name}"]`);
                return el ? el.value : '';
            };

            const cardBg = getVal('card_bg_color');
            const titleColor = getVal('card_title_color');
            const priceColor = getVal('price_color');
            const compareColor = getVal('compare_price_color');
            const badgeBg = getVal('badge_bg_color');
            const badgeText = getVal('badge_text_color');
            
            const btnBg = getVal('btn_bg_color');
            const btnIcon = getVal('btn_icon_color');
            const btnHoverBg = getVal('btn_hover_bg_color');
            const btnHoverIcon = getVal('btn_hover_icon_color');
            
            const atcBg = getVal('atc_btn_bg_color');
            const atcText = getVal('atc_btn_text_color');
            const atcHoverBg = getVal('atc_btn_hover_bg_color');
            const atcHoverText = getVal('atc_btn_hover_text_color');
            
            const tooltipBg = getVal('tooltip_bg_color');
            const tooltipText = getVal('tooltip_text_color');

            const activeBg = getVal('btn_active_bg_color');
            const activeIcon = getVal('btn_active_icon_color');

            // Apply to Cards
            [document.getElementById('previewCard'), document.getElementById('previewCardWishlist')].forEach(card => {
                if (!card) return;
                card.style.backgroundColor = cardBg;
                card.style.borderColor = (cardBg === '#ffffff' || cardBg === '#fff' || cardBg.toLowerCase() === '#ffffff') ? '#e5e7eb' : 'transparent';
            });

            // Text
            const t1 = document.getElementById('prevTitle'); if(t1) t1.style.color = titleColor;
            const t2 = document.getElementById('prevTitleW'); if(t2) t2.style.color = titleColor;
            const p1 = document.getElementById('prevPrice'); if(p1) p1.style.color = priceColor;
            const p2 = document.getElementById('prevPriceW'); if(p2) p2.style.color = priceColor;
            const cp1 = document.getElementById('prevComparePrice'); if(cp1) cp1.style.color = compareColor;

            // Badge
            const badge = document.getElementById('prevBadge');
            if (badge) {
                badge.style.backgroundColor = badgeBg;
                badge.style.color = badgeText;
            }

            // Action Buttons
            const actionBtns = [
                document.getElementById('prevWishlistBtn'),
                document.getElementById('prevQvBtn'),
                document.getElementById('prevAtcBtnIcon'),
                document.getElementById('prevRemoveBtn')
            ];

            actionBtns.forEach(btn => {
                if(!btn) return;
                btn.style.backgroundColor = btnBg;
                btn.style.color = btnIcon;
                
                btn.onmouseenter = () => {
                   btn.style.backgroundColor = btnHoverBg;
                   btn.style.color = btnHoverIcon;
                };
                btn.onmouseleave = () => {
                   btn.style.backgroundColor = btnBg;
                   btn.style.color = btnIcon;
                };
            });

            // Main ATC Buttons
            const atcBtns = [document.getElementById('prevMainAtcBtn'), document.getElementById('prevMainAtcBtnW')];
            atcBtns.forEach(btn => {
                if(!btn) return;
                btn.style.backgroundColor = atcBg;
                btn.style.color = atcText;
                
                btn.onmouseenter = () => {
                    btn.style.backgroundColor = atcHoverBg;
                    btn.style.color = atcHoverText;
                };
                btn.onmouseleave = () => {
                    btn.style.backgroundColor = atcBg;
                    btn.style.color = atcText;
                };
            });

            // Tooltips
            const tooltips = document.querySelectorAll('.product-tooltip');
            tooltips.forEach(tip => {
                tip.style.backgroundColor = tooltipBg;
                tip.style.color = tooltipText;
            });
            document.documentElement.style.setProperty('--tooltip-bg-preview', tooltipBg);
            
            // Shared Style
            let styleTag = document.getElementById('preview-tooltip-style');
            if (!styleTag) {
                styleTag = document.createElement('style');
                styleTag.id = 'preview-tooltip-style';
                document.head.appendChild(styleTag);
            }
            styleTag.innerHTML = `
                .product-tooltip::after { border-left-color: ${tooltipBg} !important; border-color: transparent !important; border-left-color: ${tooltipBg} !important; }
            `;
        }

        // Attach listeners
        container.querySelectorAll('input[type="color"], input[type="text"]').forEach(input => {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
            
            // Color Sync
            if (input.type === 'color') {
                const textInput = input.nextElementSibling;
                if (textInput && textInput.type === 'text') {
                    // Update text input when color picker changes
                    input.addEventListener('input', () => { textInput.value = input.value.toUpperCase(); updatePreview(); });
                    
                    // Update color picker when text input changes
                    textInput.addEventListener('input', () => {
                        if (textInput.value.startsWith('#')) {
                            input.value = textInput.value;
                            updatePreview();
                        }
                    });
                }
            }
        });

        // Initial call
        updatePreview();
    })();
    </script>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
