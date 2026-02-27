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

// Fetch Combined Settings
$allStylesJson = $settingsObj->get('product_page_styles', '[]');
$allStyles = json_decode($allStylesJson, true);
$qvStyles = $allStyles['quickview'] ?? [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $qvStyles = [
            'modal_bg_color' => $_POST['modal_bg_color'] ?? '#ffffff',
            'overlay_color' => $_POST['overlay_color'] ?? '#6b7280bf',
            'atc_btn_color' => $_POST['atc_btn_color'] ?? '#000000',
            'atc_btn_text_color' => $_POST['atc_btn_text_color'] ?? '#ffffff',
            'atc_hover_bg_color' => $_POST['atc_hover_bg_color'] ?? '#374151',
            'atc_hover_text_color' => $_POST['atc_hover_text_color'] ?? '#ffffff',
            'buy_now_btn_color' => $_POST['buy_now_btn_color'] ?? '#b91c1c',
            'buy_now_btn_text_color' => $_POST['buy_now_btn_text_color'] ?? '#ffffff',
            'buy_now_hover_bg_color' => $_POST['buy_now_hover_bg_color'] ?? '#991b1b',
            'buy_now_hover_text_color' => $_POST['buy_now_hover_text_color'] ?? '#ffffff',
            'price_color' => $_POST['price_color'] ?? '#1a3d32',
            'variant_bg_color' => $_POST['variant_bg_color'] ?? '#154D35',
            'variant_text_color' => $_POST['variant_text_color'] ?? '#ffffff',
            'qty_border_color' => $_POST['qty_border_color'] ?? '#000000',
            'title_color' => $_POST['title_color'] ?? '#111827',
            'desc_color' => $_POST['desc_color'] ?? '#4b5563',
            'stock_color' => $_POST['stock_color'] ?? '#1a3d32',
            'actions_color' => $_POST['actions_color'] ?? '#6b7280',
            'policy_color' => $_POST['policy_color'] ?? '#374151',
        ];
        
        $allStyles['quickview'] = $qvStyles;
        $settingsObj->set('product_page_styles', json_encode($allStyles), 'product');
        
        $_SESSION['flash_success'] = "Quick View settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/quickview_settings.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Defaults
$s_modal_bg = $qvStyles['modal_bg_color'] ?? '#ffffff';
$s_overlay = $qvStyles['overlay_color'] ?? '#6b7280bf';
$s_atc_bg = $qvStyles['atc_btn_color'] ?? '#000000';
$s_atc_text = $qvStyles['atc_btn_text_color'] ?? '#ffffff';
$s_atc_hover_bg = $qvStyles['atc_hover_bg_color'] ?? '#374151';
$s_atc_hover_text = $qvStyles['atc_hover_text_color'] ?? '#ffffff';
$s_buy_bg = $qvStyles['buy_now_btn_color'] ?? '#b91c1c';
$s_buy_text = $qvStyles['buy_now_btn_text_color'] ?? '#ffffff';
$s_buy_hover_bg = $qvStyles['buy_now_hover_bg_color'] ?? '#991b1b';
$s_buy_hover_text = $qvStyles['buy_now_hover_text_color'] ?? '#ffffff';
$s_price_color = $qvStyles['price_color'] ?? '#1a3d32';
$s_variant_bg = $qvStyles['variant_bg_color'] ?? '#154D35';
$s_variant_text = $qvStyles['variant_text_color'] ?? '#ffffff';
$s_qty_border = $qvStyles['qty_border_color'] ?? '#000000';
$s_title_color = $qvStyles['title_color'] ?? '#111827';
$s_desc_color = $qvStyles['desc_color'] ?? '#4b5563';
$s_stock_color = $qvStyles['stock_color'] ?? '#1a3d32';
$s_actions_color = $qvStyles['actions_color'] ?? '#6b7280';
$s_policy_color = $qvStyles['policy_color'] ?? '#374151';

$pageTitle = 'Quick View Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <form method="POST">
        <!-- Sticky Header -->
        <div class="sticky top-0 z-[100] bg-white border-b border-gray-200 -mx-6 px-6 py-4 mb-8 shadow-sm">
            <div class="container mx-auto flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Quick View Settings</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Customize the appearance of the Quick View modal.</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            <div class="space-y-6">
                <!-- Modal Styles -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 text-lg font-bold">Modal & Overlay</div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Modal Background</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="modal_bg_color" value="<?php echo htmlspecialchars($s_modal_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_modal_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Overlay (Backdrop)</label>
                            <div class="flex items-center gap-3">
                                <input type="text" name="overlay_color" value="<?php echo htmlspecialchars($s_overlay); ?>" class="flex-1 border rounded p-2 text-sm">
                            </div>
                            <span class="text-[10px] text-gray-400">Supports RGBA/Hex</span>
                        </div>
                    </div>
                </div>

                <!-- Price & Variants -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 text-lg font-bold text-gray-800">Price & Variants</div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Price Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="price_color" value="<?php echo htmlspecialchars($s_price_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_price_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity Border</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="qty_border_color" value="<?php echo htmlspecialchars($s_qty_border); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_qty_border); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Active Variant BG</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="variant_bg_color" value="<?php echo htmlspecialchars($s_variant_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_variant_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Active Variant Text</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="variant_text_color" value="<?php echo htmlspecialchars($s_variant_text); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_variant_text); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Text Colors -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 text-lg font-bold text-gray-800">Content Colors</div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Title Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="title_color" value="<?php echo htmlspecialchars($s_title_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_title_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="desc_color" value="<?php echo htmlspecialchars($s_desc_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_desc_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Text Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="stock_color" value="<?php echo htmlspecialchars($s_stock_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_stock_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Action Links Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="actions_color" value="<?php echo htmlspecialchars($s_actions_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_actions_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Policy/Info Box Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="policy_color" value="<?php echo htmlspecialchars($s_policy_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_policy_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add to Cart Button -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 text-lg font-bold text-gray-800">Add to Cart Button</div>
                    <div class="p-6 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Normal BG</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="atc_btn_color" value="<?php echo htmlspecialchars($s_atc_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_atc_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Normal Text</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="atc_btn_text_color" value="<?php echo htmlspecialchars($s_atc_text); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_atc_text); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Hover BG</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="atc_hover_bg_color" value="<?php echo htmlspecialchars($s_atc_hover_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_atc_hover_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Text</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="atc_hover_text_color" value="<?php echo htmlspecialchars($s_atc_hover_text); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_atc_hover_text); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buy Now Button -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 text-lg font-bold text-gray-800">Buy Now Button</div>
                    <div class="p-6 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Normal BG</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="buy_now_btn_color" value="<?php echo htmlspecialchars($s_buy_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_buy_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Normal Text</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="buy_now_btn_text_color" value="<?php echo htmlspecialchars($s_buy_text); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_buy_text); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Hover BG</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="buy_now_hover_bg_color" value="<?php echo htmlspecialchars($s_buy_hover_bg); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_buy_hover_bg); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Text</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="buy_now_hover_text_color" value="<?php echo htmlspecialchars($s_buy_hover_text); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_buy_hover_text); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Side -->
            <div class="sticky top-32 z-40">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <i class="fas fa-eye text-blue-600"></i> Preview
                </h2>
                <div id="qvPreviewOverlay" class="p-8 rounded-xl border flex items-center justify-center bg-gray-50 min-h-[400px]" style="background-color: <?php echo $s_overlay; ?>;">
                    <div id="qvPreviewModal" class="bg-white rounded-lg shadow-2xl w-full max-w-sm overflow-hidden" style="background-color: <?php echo $s_modal_bg; ?>;">
                        <div class="p-6 flex gap-4">
                            <div class="w-24 h-24 bg-gray-100 rounded flex-shrink-0"></div>
                            <div class="flex-1 space-y-2 min-w-0">
                                <div id="qvPrevTitle" class="text-xs font-bold truncate-3-lines" style="color: <?php echo $s_title_color; ?>;">Multipurpose Chopper, Fruits & Vegetable Cutters, Grater Peeler Chipser</div>
                                <div id="qvPrevPrice" class="text-lg font-bold" style="color: <?php echo $s_price_color; ?>;">₹400.00</div>
                                <div id="qvPrevDesc" class="text-[10px] line-clamp-2" style="color: <?php echo $s_desc_color; ?>;">Explore our premium kitchen tools designed for efficiency.</div>
                                <div class="flex gap-1 pt-1">
                                    <div class="px-2 py-1 rounded text-[8px] font-bold" style="background-color: <?php echo $s_variant_bg; ?>; color: <?php echo $s_variant_text; ?>;">S</div>
                                    <div class="px-2 py-1 rounded text-[8px] font-bold border border-gray-200">M</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 pt-0 space-y-3 min-w-0">
                            <div class="flex gap-2">
                                <div id="qvPrevQty" class="border rounded-full w-20 h-9 flex items-center justify-center text-xs" style="border-color: <?php echo $s_qty_border; ?>;">- 1 +</div>
                                <div id="qvPrevAtc" class="flex-1 h-9 rounded-full flex items-center justify-center font-bold text-[10px]" style="background-color: <?php echo $s_atc_bg; ?>; color: <?php echo $s_atc_text; ?>;">ADD TO CART</div>
                            </div>
                            <div id="qvPrevBuy" class="w-full h-9 rounded-full flex items-center justify-center font-bold text-[10px]" style="background-color: <?php echo $s_buy_bg; ?>; color: <?php echo $s_buy_text; ?>;">BUY IT NOW</div>
                            <div id="qvPrevStock" class="text-[10px] font-medium flex items-center gap-1" style="color: <?php echo $s_stock_color; ?>;">
                                <i class="fas fa-check-circle"></i> In Stock
                            </div>
                            <div id="qvPrevActions" class="flex gap-3 text-[9px] font-bold border-t pt-2" style="color: <?php echo $s_actions_color; ?>; border-color: <?php echo $s_qty_border; ?>33;">
                                <span><i class="far fa-heart"></i> Wishlist</span>
                                <span><i class="fas fa-share-alt"></i> Share</span>
                            </div>
                            <div id="qvPrevPolicy" class="p-2 rounded bg-gray-50 text-[9px] border border-gray-100" style="color: <?php echo $s_policy_color; ?>;">
                                <div class="font-bold mb-1">Pickup available</div>
                                <div>Sku: JAR-44144</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <style id="qvPreviewStyles">
                    #qvPrevAtc:hover { background-color: <?php echo $s_atc_hover_bg; ?> !important; color: <?php echo $s_atc_hover_text; ?> !important; }
                    #qvPrevBuy:hover { background-color: <?php echo $s_buy_hover_bg; ?> !important; color: <?php echo $s_buy_hover_text; ?> !important; }
                    .truncate-3-lines {
                        display: -webkit-box;
                        -webkit-line-clamp: 3;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }
                </style>
            </div>
        </div>
    </form>
</div>

<script>
window.initQuickViewSettings = function() {
    var container = document.getElementById('ajax-content-inner') || document;
    var inputs = container.querySelectorAll('input');
    
    window.updateQuickViewPreview = function() {
        var d = {};
        inputs.forEach(i => d[i.name] = i.value);
        
        var overlay = document.getElementById('qvPreviewOverlay');
        var modal = document.getElementById('qvPreviewModal');
        if (!overlay || !modal) return;

        overlay.style.backgroundColor = d.overlay_color;
        modal.style.backgroundColor = d.modal_bg_color;
        
        var price = document.getElementById('qvPrevPrice');
        if (price) price.style.color = d.price_color;
        
        var qty = document.getElementById('qvPrevQty');
        if (qty) qty.style.borderColor = d.qty_border_color;
        
        var title = document.getElementById('qvPrevTitle');
        if (title) title.style.color = d.title_color;
        
        var desc = document.getElementById('qvPrevDesc');
        if (desc) desc.style.color = d.desc_color;
        
        var stock = document.getElementById('qvPrevStock');
        if (stock) stock.style.color = d.stock_color;
        
        var actions = document.getElementById('qvPrevActions');
        if (actions) {
            actions.style.color = d.actions_color;
            actions.style.borderColor = d.qty_border_color + '33';
        }
        
        var policy = document.getElementById('qvPrevPolicy');
        if (policy) policy.style.color = d.policy_color;
        
        var atc = document.getElementById('qvPrevAtc');
        if (atc) {
            atc.style.backgroundColor = d.atc_btn_color;
            atc.style.color = d.atc_btn_text_color;
        }
        
        var buy = document.getElementById('qvPrevBuy');
        if (buy) {
            buy.style.backgroundColor = d.buy_now_btn_color;
            buy.style.color = d.buy_now_btn_text_color;
        }
        
        var style = document.getElementById('qvPreviewStyles');
        if (style) {
            style.innerHTML = `
                #qvPrevAtc:hover { background-color: ${d.atc_hover_bg_color} !important; color: ${d.atc_hover_text_color} !important; }
                #qvPrevBuy:hover { background-color: ${d.buy_now_hover_bg_color} !important; color: ${d.buy_now_hover_text_color} !important; }
            `;
        }
    };

    inputs.forEach(i => {
        i.addEventListener('input', window.updateQuickViewPreview);
        i.addEventListener('change', window.updateQuickViewPreview);
    });

    // Color sync
    container.querySelectorAll('input[type="color"]').forEach(colorInput => {
        var textInput = colorInput.nextElementSibling;
        colorInput.addEventListener('input', () => {
            if (textInput) textInput.value = colorInput.value.toUpperCase();
        });
        if (textInput) {
            textInput.addEventListener('input', () => {
                var val = textInput.value;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    colorInput.value = val;
                }
            });
        }
    });

    window.updateQuickViewPreview();
};

window.initQuickViewSettings();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
