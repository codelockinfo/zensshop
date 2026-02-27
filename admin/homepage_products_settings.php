<?php
ob_start(); // Buffer output to prevent headers already sent errors and dirty JSON
// Initialize dependencies and logic BEFORE any output
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Settings.php';

$auth = new Auth(); // Ensure session
$db = Database::getInstance();
$productObj = new Product();
$baseUrl = getBaseUrl();
$success = '';

// Auto-migration: Ensure tables can store the external product_id (which might be BIGINT)
try {
    $db->execute("ALTER TABLE section_best_selling_products MODIFY product_id BIGINT");
    $db->execute("ALTER TABLE section_trending_products MODIFY product_id BIGINT");
} catch (Exception $e) { 
    // Ignore errors if column already modified or valid
}



// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bestSellingIds = $_POST['best_selling_ids'] ?? '';
    $trendingIds = $_POST['trending_ids'] ?? '';
    $bs_heading = $_POST['bs_heading'] ?? '';
    $bs_subheading = $_POST['bs_subheading'] ?? '';
    $tr_heading = $_POST['tr_heading'] ?? '';
    $tr_subheading = $_POST['tr_subheading'] ?? '';
    
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }

    // Save Config (Arrows & Headers & Visibility)
    $productsConfig = [
        'show_best_selling_arrows' => isset($_POST['show_best_selling_arrows']),
        'show_trending_arrows' => isset($_POST['show_trending_arrows']),
        'show_best_selling_section' => isset($_POST['show_best_selling_section']),
        'show_trending_section' => isset($_POST['show_trending_section']),
        'bs_heading' => $_POST['bs_heading'] ?? '',
        'bs_subheading' => $_POST['bs_subheading'] ?? '',
        'tr_heading' => $_POST['tr_heading'] ?? '',
        'tr_subheading' => $_POST['tr_subheading'] ?? ''
    ];
    file_put_contents(__DIR__ . '/homepage_products_config.json', json_encode($productsConfig));

    // Save Visual Styles (Best Selling)
    $settingsObj = new Settings();
    $bs_styles = $currentBSStyles;
    $bs_styles['bg_color'] = $_POST['bs_bg_color'] ?? ($currentBSStyles['bg_color'] ?? '#ffffff');
    $bs_styles['heading_color'] = $_POST['bs_heading_color'] ?? ($currentBSStyles['heading_color'] ?? '#1f2937');
    $bs_styles['subheading_color'] = $_POST['bs_subheading_color'] ?? ($currentBSStyles['subheading_color'] ?? '#4b5563');
    $bs_styles['arrow_bg_color'] = $_POST['bs_arrow_bg_color'] ?? ($currentBSStyles['arrow_bg_color'] ?? '#ffffff');
    $bs_styles['arrow_icon_color'] = $_POST['bs_arrow_icon_color'] ?? ($currentBSStyles['arrow_icon_color'] ?? '#1f2937');
    
    $keys_to_remove = ['price_color', 'compare_price_color', 'card_bg_color', 'card_title_color', 'badge_bg_color', 'badge_text_color', 'btn_bg_color', 'btn_icon_color', 'btn_hover_bg_color', 'btn_hover_icon_color', 'btn_active_bg_color', 'btn_active_icon_color', 'tooltip_bg_color', 'tooltip_text_color'];
    foreach ($keys_to_remove as $key) {
        unset($bs_styles[$key]);
    }
    
    $settingsObj->set('best_selling_styles', json_encode($bs_styles), 'homepage');

    // Save Visual Styles (Trending)
    $currentTRStyles = json_decode($settingsObj->get('trending_styles', '{}'), true);
    $tr_styles = $currentTRStyles;
    $tr_styles['bg_color'] = $_POST['tr_bg_color'] ?? ($currentTRStyles['bg_color'] ?? '#ffffff');
    $tr_styles['heading_color'] = $_POST['tr_heading_color'] ?? ($currentTRStyles['heading_color'] ?? '#1f2937');
    $tr_styles['subheading_color'] = $_POST['tr_subheading_color'] ?? ($currentTRStyles['subheading_color'] ?? '#4b5563');
    $tr_styles['arrow_bg_color'] = $_POST['tr_arrow_bg_color'] ?? ($currentTRStyles['arrow_bg_color'] ?? '#ffffff');
    $tr_styles['arrow_icon_color'] = $_POST['tr_arrow_icon_color'] ?? ($currentTRStyles['arrow_icon_color'] ?? '#1f2937');
    
    foreach ($keys_to_remove as $key) {
        unset($tr_styles[$key]);
    }
    
    $settingsObj->set('trending_styles', json_encode($tr_styles), 'homepage');

    try {
        // Process Best Selling
        $db->execute("DELETE FROM section_best_selling_products WHERE store_id = ?", [$storeId]); 
        if (!empty($bestSellingIds)) {
            $ids = array_filter(explode(',', $bestSellingIds));
            $order = 1;
            foreach ($ids as $id) {
                $id = trim($id);
                // Ensure IDs are valid integers to prevent DB errors
                if ($id && is_numeric($id)) {
                    $db->execute("INSERT INTO section_best_selling_products (product_id, sort_order, heading, subheading, store_id) VALUES (?, ?, ?, ?, ?)", [$id, $order++, $bs_heading, $bs_subheading, $storeId]);
                }
            }
        }

        // Process Trending
        $db->execute("DELETE FROM section_trending_products WHERE store_id = ?", [$storeId]); 
        if (!empty($trendingIds)) {
            $ids = array_filter(explode(',', $trendingIds));
            $order = 1;
            foreach ($ids as $id) {
                $id = trim($id);
                if ($id && is_numeric($id)) {
                    $db->execute("INSERT INTO section_trending_products (product_id, sort_order, heading, subheading, store_id) VALUES (?, ?, ?, ?, ?)", [$id, $order++, $tr_heading, $tr_subheading, $storeId]);
                }
            }
        }
        
        $successMsg = "Homepage product settings updated successfully!";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
    
    // Handle AJAX request
    if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['ajax'])) {
        ob_end_clean(); // Clean all buffered output
        header('Content-Type: application/json');
        
        if (isset($errorMsg)) {
            // Return 200 so the JS parses the JSON and we can show the specific error message
            // The JS checks for data.success
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        } else {
            echo json_encode(['success' => true, 'message' => $successMsg]);
        }
        exit;
    }
    
    // Standard POST - Redirect with Session Flash
    if (isset($errorMsg)) {
        $_SESSION['flash_error'] = $errorMsg; // Assuming you have a flash_error handler, or just use error var
        // Since we don't have a flash_error in the view code above (only success), we might just set $error
        $error = $errorMsg; // But we are redirecting... so we need session
        // For now, let's just stay on page if error? Or redirect with error param?
        // Let's assume standard behavior is to redirect back or stay.
        // Given the structure, let's just redirect back to products list with error? 
        // Actually, better to stay on page to show error but we are doing a redirect pattern.
        // Let's just use flash_error if it exists, or just append to URL.
        // But for now, let's just use the success path but with error message in a way the user sees.
        // Actually, the user JS handles the AJAX. The standard POST is fallback.
        // Let's stick to safe redirect.
        $_SESSION['flash_success'] = "Error: " . $errorMsg; // Fallback
    } else {
        $_SESSION['flash_success'] = $successMsg;
    }
    header("Location: " . $baseUrl . '/admin/products');
    exit;
}

// Check for success message from Session Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch Selected Products JOINING on product_id
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$queryBS = "SELECT p.*, h.sort_order, h.heading, h.subheading FROM products p 
            JOIN section_best_selling_products h ON p.product_id = h.product_id 
            WHERE h.store_id = ? AND p.store_id = ? AND p.status = 'active'
            ORDER BY h.sort_order ASC";
$bestSellingProducts = $db->fetchAll($queryBS, [$storeId, $storeId]);

// Get headers for Best Selling
$bsHeaders = $db->fetchOne("SELECT heading, subheading FROM section_best_selling_products WHERE store_id = ? LIMIT 1", [$storeId]);

$queryTR = "SELECT p.*, h.sort_order, h.heading, h.subheading FROM products p 
            JOIN section_trending_products h ON p.product_id = h.product_id 
            WHERE h.store_id = ? AND p.store_id = ? AND p.status = 'active'
            ORDER BY h.sort_order ASC";
$trendingProducts = $db->fetchAll($queryTR, [$storeId, $storeId]);

// Get headers for Trending
$trHeaders = $db->fetchOne("SELECT heading, subheading FROM section_trending_products WHERE store_id = ? LIMIT 1", [$storeId]);

// Create ID strings for JS state - using product_id
$bestSellingIdsStr = implode(',', array_column($bestSellingProducts, 'product_id'));
$trendingIdsStr = implode(',', array_column($trendingProducts, 'product_id'));

// Load Config
$productsConfigPath = __DIR__ . '/homepage_products_config.json';
$showBSArrows = true;
$showTRArrows = true;
$showBSSection = true;
$showTRSection = true;
$savedConfig = null;

if (file_exists($productsConfigPath)) {
    $savedConfig = json_decode(file_get_contents($productsConfigPath), true);
    $showBSArrows = isset($savedConfig['show_best_selling_arrows']) ? $savedConfig['show_best_selling_arrows'] : true;
    $showTRArrows = isset($savedConfig['show_trending_arrows']) ? $savedConfig['show_trending_arrows'] : true;
    $showBSSection = isset($savedConfig['show_best_selling_section']) ? $savedConfig['show_best_selling_section'] : true;
    $showTRSection = isset($savedConfig['show_trending_section']) ? $savedConfig['show_trending_section'] : true;
}

// Prepare headers (JSON is master)
$best_selling_heading = 'Best Selling Products';
$best_selling_subheading = 'Discover our most loved items by customers.';
$trending_heading = 'Trending Products';
$trending_subheading = 'Check out what is currently trending in our store.';

if ($savedConfig !== null) {
    $best_selling_heading = $savedConfig['bs_heading'] ?? ($bsHeaders['heading'] ?? $best_selling_heading);
    $best_selling_subheading = $savedConfig['bs_subheading'] ?? ($bsHeaders['subheading'] ?? $best_selling_subheading);
    $trending_heading = $savedConfig['tr_heading'] ?? ($trHeaders['heading'] ?? $trending_heading);
    $trending_subheading = $savedConfig['tr_subheading'] ?? ($trHeaders['subheading'] ?? $trending_subheading);
} else {
    $best_selling_heading = $bsHeaders['heading'] ?? $best_selling_heading;
    $best_selling_subheading = $bsHeaders['subheading'] ?? $best_selling_subheading;
    $trending_heading = $trHeaders['heading'] ?? $trending_heading;
    $trending_subheading = $trHeaders['subheading'] ?? $trending_subheading;
    $trending_heading = $trHeaders['heading'] ?? $trending_heading;
    $trending_subheading = $trHeaders['subheading'] ?? $trending_subheading;
}

// Fetch Style Settings
$settingsObj = new Settings();
$savedBSStylesJson = $settingsObj->get('best_selling_styles', '{"bg_color":"#ffffff","heading_color":"#1f2937","subheading_color":"#4b5563","price_color":"#1a3d32","compare_price_color":"#9ca3af","arrow_bg_color":"#ffffff","arrow_icon_color":"#1f2937","card_bg_color":"#ffffff","card_title_color":"#1F2937","badge_bg_color":"#ef4444","badge_text_color":"#ffffff","btn_bg_color":"#ffffff","btn_icon_color":"#000000","btn_hover_bg_color":"#000000","btn_hover_icon_color":"#ffffff","btn_active_bg_color":"#000000","btn_active_icon_color":"#ffffff","tooltip_bg_color":"#000000","tooltip_text_color":"#ffffff"}');
$savedBSStyles = json_decode($savedBSStylesJson, true);

$savedTRStylesJson = $settingsObj->get('trending_styles', '{"bg_color":"#ffffff","heading_color":"#1f2937","subheading_color":"#4b5563","price_color":"#1a3d32","compare_price_color":"#9ca3af","arrow_bg_color":"#ffffff","arrow_icon_color":"#1f2937","card_bg_color":"#ffffff","card_title_color":"#1F2937","badge_bg_color":"#ef4444","badge_text_color":"#ffffff","btn_bg_color":"#ffffff","btn_icon_color":"#000000","btn_hover_bg_color":"#000000","btn_hover_icon_color":"#ffffff","btn_active_bg_color":"#000000","btn_active_icon_color":"#ffffff","tooltip_bg_color":"#000000","tooltip_text_color":"#ffffff"}');
$savedTRStyles = json_decode($savedTRStylesJson, true);

// BS Style Defaults
$bs_s_bg_color = $savedBSStyles['bg_color'] ?? '#ffffff';
$bs_s_heading_color = $savedBSStyles['heading_color'] ?? '#1f2937';
$bs_s_subheading_color = $savedBSStyles['subheading_color'] ?? '#4b5563';
$bs_s_price_color = $savedBSStyles['price_color'] ?? '#1a3d32';
$bs_s_compare_price_color = $savedBSStyles['compare_price_color'] ?? '#9ca3af';
$bs_s_arrow_bg_color = $savedBSStyles['arrow_bg_color'] ?? '#ffffff';
$bs_s_arrow_icon_color = $savedBSStyles['arrow_icon_color'] ?? '#1f2937';
$bs_s_card_bg_color = $savedBSStyles['card_bg_color'] ?? '#ffffff';
$bs_s_card_title_color = $savedBSStyles['card_title_color'] ?? '#1F2937';
$bs_s_badge_bg_color = $savedBSStyles['badge_bg_color'] ?? '#ef4444';
$bs_s_badge_text_color = $savedBSStyles['badge_text_color'] ?? '#ffffff';
$bs_s_btn_bg_color = $savedBSStyles['btn_bg_color'] ?? '#ffffff';
$bs_s_btn_icon_color = $savedBSStyles['btn_icon_color'] ?? '#000000';
$bs_s_btn_hover_bg_color = $savedBSStyles['btn_hover_bg_color'] ?? '#000000';
$bs_s_btn_hover_icon_color = $savedBSStyles['btn_hover_icon_color'] ?? '#ffffff';
$bs_s_btn_active_bg_color = $savedBSStyles['btn_active_bg_color'] ?? '#000000';
$bs_s_btn_active_icon_color = $savedBSStyles['btn_active_icon_color'] ?? '#ffffff';
$bs_s_tooltip_bg_color = $savedBSStyles['tooltip_bg_color'] ?? '#000000';
$bs_s_tooltip_text_color = $savedBSStyles['tooltip_text_color'] ?? '#ffffff';

// TR Style Defaults
$tr_s_bg_color = $savedTRStyles['bg_color'] ?? '#ffffff';
$tr_s_heading_color = $savedTRStyles['heading_color'] ?? '#1f2937';
$tr_s_subheading_color = $savedTRStyles['subheading_color'] ?? '#4b5563';
$tr_s_price_color = $savedTRStyles['price_color'] ?? '#1a3d32';
$tr_s_compare_price_color = $savedTRStyles['compare_price_color'] ?? '#9ca3af';
$tr_s_arrow_bg_color = $savedTRStyles['arrow_bg_color'] ?? '#ffffff';
$tr_s_arrow_icon_color = $savedTRStyles['arrow_icon_color'] ?? '#1f2937';
$tr_s_card_bg_color = $savedTRStyles['card_bg_color'] ?? '#ffffff';
$tr_s_card_title_color = $savedTRStyles['card_title_color'] ?? '#1F2937';
$tr_s_badge_bg_color = $savedTRStyles['badge_bg_color'] ?? '#ef4444';
$tr_s_badge_text_color = $savedTRStyles['badge_text_color'] ?? '#ffffff';
$tr_s_btn_bg_color = $savedTRStyles['btn_bg_color'] ?? '#ffffff';
$tr_s_btn_icon_color = $savedTRStyles['btn_icon_color'] ?? '#000000';
$tr_s_btn_hover_bg_color = $savedTRStyles['btn_hover_bg_color'] ?? '#000000';
$tr_s_btn_hover_icon_color = $savedTRStyles['btn_hover_icon_color'] ?? '#ffffff';
$tr_s_btn_active_bg_color = $savedTRStyles['btn_active_bg_color'] ?? '#000000';
$tr_s_btn_active_icon_color = $savedTRStyles['btn_active_icon_color'] ?? '#ffffff';
$tr_s_tooltip_bg_color = $savedTRStyles['tooltip_bg_color'] ?? '#000000';
$tr_s_tooltip_text_color = $savedTRStyles['tooltip_text_color'] ?? '#ffffff';

// START HTML OUTPUT
$pageTitle = 'Homepage Products';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto">
    <!-- Sticky Header -->
    <div class="mb-6 flex justify-between items-end sticky top-0 bg-[#f7f8fc] py-4 z-50 pt-0">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 pt-4 pl-2">Product Sections</h1>
            <p class="text-sm text-gray-500 mt-1 pl-2">
                <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > 
                <a href="<?php echo url('admin/products'); ?>" class="hover:text-blue-600">Products</a> > 
                Homepage Products
            </p>
        </div>
        <div class="flex items-center gap-3">
             <button type="button" onclick="window.location.href='<?php echo url('admin/products'); ?>'" class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 bg-white text-gray-700 font-medium transition-colors">Cancel</button>
            <button type="submit" form="settingsForm" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-sm flex items-center gap-2 btn-loading">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>

    <div class="px-6">

    <!-- Global Message Container -->
    <div id="global_message_container">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" id="settingsForm">

        <!-- Visual Style Settings - Best Selling -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Best Selling Visual Style</h2>
                <p class="text-sm text-gray-500">Customize the colors for the Best Selling section.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Background Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bs_bg_color" value="<?php echo htmlspecialchars($bs_s_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($bs_s_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bs_heading_color" value="<?php echo htmlspecialchars($bs_s_heading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($bs_s_heading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bs_subheading_color" value="<?php echo htmlspecialchars($bs_s_subheading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($bs_s_subheading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Slider Arrow Background</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bs_arrow_bg_color" value="<?php echo htmlspecialchars($bs_s_arrow_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($bs_s_arrow_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Slider Arrow Icon</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bs_arrow_icon_color" value="<?php echo htmlspecialchars($bs_s_arrow_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($bs_s_arrow_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>
        </div>

        <!-- Visual Style Settings - Trending -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Trending Visual Style</h2>
                <p class="text-sm text-gray-500">Customize the colors for the Trending section.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Background Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="tr_bg_color" value="<?php echo htmlspecialchars($tr_s_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($tr_s_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="tr_heading_color" value="<?php echo htmlspecialchars($tr_s_heading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($tr_s_heading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="tr_subheading_color" value="<?php echo htmlspecialchars($tr_s_subheading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($tr_s_subheading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Slider Arrow Background</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="tr_arrow_bg_color" value="<?php echo htmlspecialchars($tr_s_arrow_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($tr_s_arrow_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                 <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Slider Arrow Icon</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="tr_arrow_icon_color" value="<?php echo htmlspecialchars($tr_s_arrow_icon_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($tr_s_arrow_icon_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Visibility Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Visibility Settings</h2>
                <p class="text-sm text-gray-500">Control usage and visibility of product sections.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Best Selling Column -->
                <div class="space-y-4 md:border-r md:border-gray-200 md:pr-6">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Best Selling Section</h3>
                    
                    <!-- Best Selling Section Visibility -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                                <i class="fas fa-eye text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Show Section</h3>
                                <p class="text-sm text-gray-500">Toggle visibility</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_best_selling_section" class="sr-only peer" <?php echo $showBSSection ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    
                    <!-- Best Selling Arrows -->
                    <div class="flex items-center justify-between pb-4">
                        <div class="flex items-center gap-4">
                            <div class="pl-14">
                                <div class="text-gray-700 font-medium">Show Slider Arrows</div>
                                <div class="text-xs text-gray-500">Navigation arrows</div>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_best_selling_arrows" class="sr-only peer" <?php echo $showBSArrows ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Trending Column -->
                <div class="space-y-4">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Trending Section</h3>

                    <!-- Trending Section Visibility -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-purple-50 rounded-lg text-purple-600">
                                <i class="fas fa-eye text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Show Section</h3>
                                <p class="text-sm text-gray-500">Toggle visibility</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_trending_section" class="sr-only peer" <?php echo $showTRSection ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>

                    <!-- Trending Arrows -->
                    <div class="flex items-center justify-between pb-4">
                         <div class="flex items-center gap-4">
                            <div class="pl-14">
                                <div class="text-gray-700 font-medium">Show Slider Arrows</div>
                                <div class="text-xs text-gray-500">Navigation arrows</div>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_trending_arrows" class="sr-only peer" <?php echo $showTRArrows ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best Selling Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Best Selling Section</h2>
                <p class="text-sm text-gray-500">Customize the appearance and product list for the Best Selling section.</p>
            </div>
            <div class="p-6">
                <!-- Section Headers -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Section Heading</label>
                        <input type="text" name="bs_heading" value="<?php echo htmlspecialchars((string)$best_selling_heading); ?>" 
                               class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Section Subheading</label>
                        <input type="text" name="bs_subheading" value="<?php echo htmlspecialchars((string)$best_selling_subheading); ?>" 
                               class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                </div>

                <input type="hidden" name="best_selling_ids" id="best_selling_ids" value="<?php echo htmlspecialchars($bestSellingIdsStr); ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Search or Select Product</label>
                    
                    <!-- Unified Combo Box -->
                    <div class="relative product-combobox" id="combo_best_selling">
                        <input type="text" 
                               class="w-full border rounded-lg p-2 pl-9 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm cursor-text" 
                               placeholder="Start typing to search or click to view list..."
                               autocomplete="off">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 text-xs cursor-pointer toggle-icon"></i>
                        
                        <div class="combobox-options absolute z-10 w-full bg-white shadow-xl rounded-b-lg border border-gray-200 mt-1 max-h-60 overflow-y-auto hidden">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <div id="msg_best_selling" class="hidden"></div>
                
                <div class="bg-gray-50 rounded-lg p-4 min-h-[100px] max-h-96 overflow-y-auto">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Selected Products</h3>
                    <ul id="list_best_selling" class="space-y-2">
                        <?php foreach ($bestSellingProducts as $p): ?>
                        <li class="bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm" data-id="<?php echo $p['product_id']; ?>">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-grip-vertical text-gray-400 cursor-move drag-handle" title="Drag to reorder"></i>
                                <?php $imgUrl = getImageUrl($p['featured_image']); ?>
                                <?php if($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-10 h-10 object-cover rounded">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>
                                <?php endif; ?>
                                <span class="font-medium text-gray-800" style="max-width: 450px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($p['name'] ?? ''); ?></span>
                                <span class="text-xs text-gray-500">(Product ID: <?php echo $p['product_id']; ?>)</span>
                            </div>
                            <button type="button" onclick="removeItem('best_selling', <?php echo $p['product_id']; ?>)" class="text-red-500 hover:text-red-700 p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($bestSellingProducts)): ?>
                        <li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default best sellers will be shown.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Trending Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Trending Section</h2>
                <p class="text-sm text-gray-500">Customize the appearance and product list for the Trending section.</p>
            </div>
            <div class="p-6">
                <!-- Section Headers -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Section Heading</label>
                        <input type="text" name="tr_heading" value="<?php echo htmlspecialchars((string)$trending_heading); ?>" 
                               class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Section Subheading</label>
                        <input type="text" name="tr_subheading" value="<?php echo htmlspecialchars((string)$trending_subheading); ?>" 
                               class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                </div>

                <input type="hidden" name="trending_ids" id="trending_ids" value="<?php echo htmlspecialchars($trendingIdsStr); ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Search or Select Product</label>
                    
                    <!-- Unified Combo Box -->
                    <div class="relative product-combobox" id="combo_trending">
                        <input type="text" 
                               class="w-full border rounded-lg p-2 pl-9 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm cursor-text" 
                               placeholder="Start typing to search or click to view list..."
                               autocomplete="off">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 text-xs cursor-pointer toggle-icon"></i>
                        
                        <div class="combobox-options absolute z-10 w-full bg-white shadow-xl rounded-b-lg border border-gray-200 mt-1 max-h-60 overflow-y-auto hidden">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <div id="msg_trending" class="hidden"></div>
                
                <div class="bg-gray-50 rounded-lg p-4 min-h-[100px] max-h-96 overflow-y-auto">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Selected Products</h3>
                    <ul id="list_trending" class="space-y-2">
                        <?php foreach ($trendingProducts as $p): ?>
                        <li class="bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm" data-id="<?php echo $p['product_id']; ?>">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-grip-vertical text-gray-400 cursor-move drag-handle" title="Drag to reorder"></i>
                                <?php $imgUrl = getImageUrl($p['featured_image']); ?>
                                <?php if($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-10 h-10 object-cover rounded">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>
                                <?php endif; ?>
                                <span class="font-medium text-gray-800" style="max-width: 450px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($p['name'] ?? ''); ?></span>
                                <span class="text-xs text-gray-500">(Product ID: <?php echo $p['product_id']; ?>)</span>
                            </div>
                            <button type="button" onclick="removeItem('trending', <?php echo $p['product_id']; ?>)" class="text-red-500 hover:text-red-700 p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($trendingProducts)): ?>
                        <li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default trending items will be shown.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>


    </form>
    </div> <!-- Close px-6 -->
</div> <!-- Close container -->


<!-- SortableJS Library for Drag and Drop - Load BEFORE our script -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>

var collections = {
    best_selling: new Set(<?php echo $bestSellingIdsStr ? '[' . $bestSellingIdsStr . ']' : '[]'; ?>),
    trending: new Set(<?php echo $trendingIdsStr ? '[' . $trendingIdsStr . ']' : '[]'; ?>)
};

// Initialize Combo Box for a section
window.initComboBox = function(key) {
    var container = document.getElementById('combo_' + key);
    if (!container) return;
    var input = container.querySelector('input');
    var dropdown = container.querySelector('.combobox-options');
    var toggleIcon = container.querySelector('.toggle-icon');
    
    // Render list function
    async function renderList(filterText = '') {
        var term = (filterText || '').trim();
        dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading products...</div>';
        
        try {
            var response = await fetch(`${BASE_URL}/admin/search_products_json.php?term=${encodeURIComponent(term)}`);
            var products = await response.json();
            
            if (Array.isArray(products)) {
                products = products.filter(p => !collections[key].has(parseInt(p.id)));
            }
            
            if (!products || !Array.isArray(products) || products.length === 0) {
                dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">No products found</div>';
            } else {
                dropdown.innerHTML = products.map(p => `
                    <div class="p-2 hover:bg-blue-50 cursor-pointer border-b flex items-center gap-3 transition item-selection" 
                         data-p='${JSON.stringify(p).replace(/'/g, "&apos;")}'>
                        <div class="w-8 h-8 flex-shrink-0 bg-gray-200 rounded overflow-hidden">
                             ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover">` : '<i class="fas fa-image text-gray-400 flex items-center justify-center h-full w-full"></i>'}
                        </div>
                        <div>
                            <div class="font-medium text-sm text-gray-800">${p.name}</div>
                            <div class="text-xs text-gray-500">${p.sku ? 'SKU: ' + p.sku : ''} (ID: ${p.id})</div>
                        </div>
                    </div>
                `).join('');

                dropdown.querySelectorAll('.item-selection').forEach(item => {
                    item.addEventListener('click', function(e) {
                        var p = JSON.parse(this.dataset.p);
                        window.addItem(key, p.id, p.name, p.image);
                        input.value = '';
                        hideDropdown();
                    });
                });
            }
        } catch (error) {
            console.error('Search error:', error);
            dropdown.innerHTML = '<div class="p-3 text-red-500 text-sm">Error fetching products</div>';
        }
    }
    
    function showDropdown() {
        renderList(input.value);
        dropdown.classList.remove('hidden');
    }
    
    function hideDropdown() {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    }
    
    input.addEventListener('focus', showDropdown);
    input.addEventListener('input', (e) => renderList(e.target.value));
    toggleIcon.addEventListener('click', (e) => {
        e.stopPropagation();
        if (dropdown.classList.contains('hidden')) {
            showDropdown();
        } else {
            dropdown.classList.add('hidden');
        }
    });

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
};

window.addItem = function(key, id, name, image) {
    id = parseInt(id);
    if (collections[key].has(id)) {
        window.showMessage(key, 'Product already added.', 'error');
        return;
    }
    collections[key].add(id);
    var list = document.getElementById('list_' + key);
    if (!list) return;

    var emptyMsg = list.querySelector('.empty-msg');
    if (emptyMsg) emptyMsg.remove();
    
    var li = document.createElement('li');
    li.className = 'bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm animate-fade-in';
    li.dataset.id = id;
    
    var imgHtml = image 
        ? `<img src="${image}" class="w-10 h-10 object-cover rounded">`
        : `<div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>`;
        
    li.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas fa-grip-vertical text-gray-400 cursor-move drag-handle" title="Drag to reorder"></i>
            ${imgHtml}
            <span class="font-medium text-gray-800" style="max-width: 450px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${name}</span>
            <span class="text-xs text-gray-500">(Product ID: ${id})</span>
        </div>
        <button type="button" onclick="window.removeItem('${key}', ${id})" class="text-red-500 hover:text-red-700 p-1">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    list.appendChild(li);
    window.updateHiddenInput(key);
};

window.removeItem = function(key, id) {
    collections[key].delete(id);
    var list = document.getElementById('list_' + key);
    if (!list) return;
    var item = list.querySelector(`li[data-id="${id}"]`);
    if (item) item.remove();
    
    if (collections[key].size === 0) {
        list.innerHTML = '<li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default products will be shown.</li>';
    }
    window.updateHiddenInput(key);
};

window.updateHiddenInput = function(key) {
    var list = document.getElementById('list_' + key);
    if (!list) return;
    var items = list.querySelectorAll('li[data-id]');
    var ids = Array.from(items).map(li => li.dataset.id);
    document.getElementById(key + '_ids').value = ids.join(',');
};

window.showMessage = function(key, msg, type='error') {
    var el = document.getElementById('msg_' + key);
    if(!el) return;
    var colorClass = type === 'error' ? 'text-red-500 bg-red-50 border-red-200' : 'text-green-500 bg-green-50 border-green-200';
    el.className = `mb-3 px-3 py-2 rounded border text-sm ${colorClass}`;
    el.innerText = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
};

window.showGlobalMessage = function(msg) {
    var el = document.getElementById('global_message_container');
    if (el) {
        el.innerHTML = `<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-fade-in">${msg}</div>`;
        setTimeout(() => { el.innerHTML = ''; }, 4000); 
    }
};

window.initSortable = function(key) {
    var list = document.getElementById('list_' + key);
    if (!list || typeof Sortable === 'undefined') return;
    new Sortable(list, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'bg-blue-50',
        onEnd: function() {
            window.updateHiddenInput(key);
            var items = list.querySelectorAll('li[data-id]');
            collections[key].clear();
            items.forEach(item => collections[key].add(parseInt(item.dataset.id)));
        }
    });
};

(function() {
    window.initComboBox('best_selling');
    window.initComboBox('trending');
    window.initSortable('best_selling');
    window.initSortable('trending');
    
    var form = document.getElementById('settingsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var submitBtn = document.querySelector('button[form="settingsForm"]');
            
            fetch(`${BASE_URL}/admin/homepage_products_settings.php?ajax=1`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) window.showGlobalMessage(data.message);
                else throw new Error(data.message);
            })
            .catch(err => {
                var el = document.getElementById('global_message_container');
                if (el) {
                    el.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">Error: ${err.message}</div>`;
                    setTimeout(() => { el.innerHTML = ''; }, 5000);
                }
            })
            .finally(() => {
                if (submitBtn && window.setBtnLoading) window.setBtnLoading(submitBtn, false);
            });
        });
    }
})();

// Hide PHP message if present
// Hide PHP message if present and clean URL
document.addEventListener('DOMContentLoaded', function() {
    const phpMsg = document.querySelector('#global_message_container .bg-green-100');
    if (phpMsg) { 
        setTimeout(() => { 
            phpMsg.style.transition = "opacity 0.5s ease";
            phpMsg.style.opacity = "0";
            setTimeout(() => phpMsg.remove(), 500);
        }, 3000); 
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
