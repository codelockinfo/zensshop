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
        // Checkout Page Styling (Consolidated)
        $checkoutStyling = [];
        $checkoutKeys = [
            'checkout_progress_active_bg', 'checkout_progress_active_text',
            'checkout_progress_inactive_bg', 'checkout_progress_inactive_text',
            'checkout_welcome_bg', 'checkout_welcome_text', 'checkout_welcome_border',
            'checkout_heading_color', 'checkout_label_color',
            'checkout_input_border', 'checkout_input_focus', 'checkout_input_text_color',
            'checkout_summary_bg', 'checkout_summary_border', 'checkout_summary_text',
            'checkout_pay_btn_bg', 'checkout_pay_btn_text', 'checkout_pay_btn_hover_bg'
        ];

        foreach ($checkoutKeys as $key) {
            $checkoutStyling[$key] = $_POST["setting_$key"] ?? '';
        }
        $settingsObj->set('checkout_page_styling', json_encode($checkoutStyling), 'checkout_styling');

        // Order Success Page Styling (Consolidated)
        $successStyling = [];
        $successKeys = [
            'success_icon_color', 
            'success_text_color', 'success_secondary_text_color',
            'success_step1_color', 'success_step2_color', 'success_step3_color', 'success_step4_color',
            'success_btn1_bg', 'success_btn1_text', 'success_btn1_hover_bg', 'success_btn1_hover_text',
            'success_btn2_bg', 'success_btn2_text', 'success_btn2_hover_bg', 'success_btn2_hover_text',
            'success_btn3_bg', 'success_btn3_text', 'success_btn3_hover_bg', 'success_btn3_hover_text', 'success_btn3_border'
        ];

        foreach ($successKeys as $key) {
            $successStyling[$key] = $_POST["setting_$key"] ?? '';
        }
        $settingsObj->set('success_page_styling', json_encode($successStyling), 'success_styling');

        $_SESSION['flash_success'] = "Checkout & Success page settings updated!";
        header("Location: " . $baseUrl . '/admin/checkout_settings.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load Consolidated Settings
$checkoutStylingJson = $settingsObj->get('checkout_page_styling', '');
$checkoutStyling = !empty($checkoutStylingJson) ? json_decode($checkoutStylingJson, true) : [];

$successStylingJson = $settingsObj->get('success_page_styling', '');
$successStyling = !empty($successStylingJson) ? json_decode($successStylingJson, true) : [];

// Migration Helpers
function getCheckoutStyle($key, $default, $settingsObj, $checkoutStyling) {
    if (isset($checkoutStyling[$key])) return $checkoutStyling[$key];
    return $settingsObj->get($key, $default);
}
function getSuccessStyle($key, $default, $settingsObj, $successStyling) {
    if (isset($successStyling[$key])) return $successStyling[$key];
    return $settingsObj->get($key, $default);
}

$pageTitle = 'Checkout Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <form method="POST">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 sticky top-0 z-20 bg-gray-100 py-4 -mx-6 px-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Checkout Design Settings</h1>
                <p class="text-sm text-gray-500 mt-1">Customize the appearance of Checkout and Order Success pages.</p>
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
                
                <!-- Checkout Page Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-credit-card"></i> Checkout Page Design
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        
                        <h3 class="text-sm font-bold text-gray-700 mb-2">Progress Bar</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Active Step Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_progress_active_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_active_bg', '#3b82f6', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_prog_active_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_active_bg', '#3b82f6', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Active Step Text</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_progress_active_text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_active_text', '#ffffff', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_prog_active_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_active_text', '#ffffff', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Inactive Step Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_progress_inactive_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_inactive_bg', '#e5e7eb', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_prog_inactive_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_inactive_bg', '#e5e7eb', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Inactive Step Text</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_progress_inactive_text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_inactive_text', '#374151', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_prog_inactive_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_progress_inactive_text', '#374151', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Welcome Message Box</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_welcome_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_bg', '#eff6ff', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_welcome_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_bg', '#eff6ff', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_welcome_text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_text', '#1e40af', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_welcome_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_text', '#1e40af', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Border Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_welcome_border" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_border', '#dbeafe', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_welcome_border">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_welcome_border', '#dbeafe', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">General Styling</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Headings Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_heading_color" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_heading_color', '#111827', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_heading_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_heading_color', '#111827', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Labels Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_label_color" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_label_color', '#374151', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_label_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_label_color', '#374151', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Input Border Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_input_border" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_border', '#d1d5db', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_input_border">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_border', '#d1d5db', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Input Focus Ring</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_input_focus" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_focus', '#3b82f6', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_input_focus">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_focus', '#3b82f6', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Input Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_input_text_color" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_text_color', '#111827', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_input_text_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_input_text_color', '#111827', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Order Summary (Checkout)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_summary_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_bg', '#ffffff', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_summary_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_bg', '#ffffff', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Border</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_summary_border" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_border', '#ffffff', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_summary_border">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_border', '#ffffff', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_summary_text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_text', '#111827', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_summary_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_summary_text', '#111827', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Pay Button</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_pay_btn_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_bg', '#2563eb', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_pay_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_bg', '#2563eb', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_pay_btn_text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_text', '#ffffff', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_ch_pay_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_text', '#ffffff', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateCheckoutPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_checkout_pay_btn_hover_bg" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_hover_bg', '#1d4ed8', $settingsObj, $checkoutStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0">
                                    <input type="text" value="<?php echo htmlspecialchars(getCheckoutStyle('checkout_pay_btn_hover_bg', '#1d4ed8', $settingsObj, $checkoutStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Order Success Page Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-check-circle"></i> Order Success Page
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        
                         <h3 class="text-sm font-bold text-gray-700 mb-2">Success Banner & Texts</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Success Icon Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_icon_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_icon_color', '#8b5cf6', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_icon_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_icon_color', '#8b5cf6', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Main Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_text_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_text_color', '#111827', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_text_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_text_color', '#111827', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Secondary Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_secondary_text_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_secondary_text_color', '#374151', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_sec_text_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_secondary_text_color', '#374151', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Order Tracking Colors</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Step 1: Confirmed</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_step1_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_step1_color', '#10b981', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_step1_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_step1_color', '#10b981', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Step 2: On its way</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_step2_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_step2_color', '#8b5cf6', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_step2_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_step2_color', '#8b5cf6', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Step 3: Out for delivery</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_step3_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_step3_color', '#f59e0b', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_step3_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_step3_color', '#f59e0b', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Step 4: Delivered</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_step4_color" value="<?php echo htmlspecialchars(getSuccessStyle('success_step4_color', '#10b981', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_step4_color">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_step4_color', '#10b981', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Button 1: Download Invoice</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn1_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_bg', '#dc2626', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn1_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_bg', '#dc2626', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn1_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_text', '#ffffff', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn1_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_text', '#ffffff', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn1_hover_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_hover_bg', '#b91c1c', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn1_hover_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_hover_bg', '#b91c1c', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn1_hover_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_hover_text', '#ffffff', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn1_hover_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn1_hover_text', '#ffffff', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                         <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Button 2: Continue Shopping</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn2_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_bg', '#000000', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn2_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_bg', '#000000', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn2_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_text', '#ffffff', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn2_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_text', '#ffffff', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn2_hover_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_hover_bg', '#1f2937', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn2_hover_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_hover_bg', '#1f2937', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn2_hover_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_hover_text', '#ffffff', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn2_hover_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn2_hover_text', '#ffffff', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-gray-700 mb-2 mt-4">Button 3: Browse Products</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn3_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_bg', '#ffffff', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn3_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_bg', '#ffffff', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn3_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_text', '#111827', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn3_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_text', '#111827', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Background</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn3_hover_bg" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_hover_bg', '#f9fafb', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn3_hover_bg">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_hover_bg', '#f9fafb', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Hover Text</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn3_hover_text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_hover_text', '#111827', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn3_hover_text">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_hover_text', '#111827', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                            <div>
                                 <label class="block text-xs font-semibold text-gray-500 mb-1">Border</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="setting_success_btn3_border" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_border', '#d1d5db', $settingsObj, $successStyling)); ?>" class="h-8 w-8 rounded cursor-pointer border-0 p-0" id="input_succ_btn3_border">
                                    <input type="text" value="<?php echo htmlspecialchars(getSuccessStyle('success_btn3_border', '#d1d5db', $settingsObj, $successStyling)); ?>" oninput="this.previousElementSibling.value = this.value; updateSuccessPreview();" class="flex-1 text-xs border rounded px-2 py-1 uppercase">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

             <!-- PREVIEW COLUMN (Sticky) -->
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-6">
                    
                     <!-- CHECKOUT PAGE PREVIEW -->
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase">Checkout Preview</h3>
                        </div>
                         <div id="preview-checkout" class="p-4 transition-colors duration-200 bg-gray-50">
                             
                             <!-- Progress Bar -->
                            <div class="flex items-center justify-center space-x-2 mb-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold preview-ch-step-inactive">1</div>
                                <div class="w-8 h-0.5 bg-gray-300"></div>
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold preview-ch-step-active">2</div>
                            </div>
                            
                            <!-- Welcome Box -->
                            <div class="rounded-lg p-3 mb-4 flex items-center space-x-2 preview-ch-welcome border">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="preview-ch-welcome-text text-sm">Welcome back, User!</div>
                            </div>

                            <!-- Heading -->
                            <h2 class="text-lg font-bold mb-2 preview-ch-heading">Shipping Info</h2>

                            <!-- Inputs -->
                            <div class="space-y-2 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold mb-1 preview-ch-label">Full Name</label>
                                    <input type="text" class="w-full px-3 py-2 border rounded text-sm preview-ch-input" placeholder="John Doe">
                                </div>
                            </div>

                            <!-- Summary Box -->
                            <div class="rounded-lg p-3 mb-4 preview-ch-summary border">
                                <h3 class="font-bold text-sm mb-2 preview-ch-summary-text">Order Summary</h3>
                                <div class="flex justify-between text-xs mb-1 preview-ch-summary-text">
                                    <span>Subtotal</span>
                                    <span>$129.00</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold border-t pt-1 mt-1 preview-ch-summary-text">
                                    <span>Total</span>
                                    <span>$129.00</span>
                                </div>
                            </div>

                            <!-- Pay Button -->
                            <button class="w-full py-2 rounded font-bold text-sm mb-2 preview-ch-pay-btn">Pay Now</button>

                         </div>
                    </div>

                    <!-- SUCCESS PAGE PREVIEW -->
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-bold text-gray-700 uppercase">Order Success Preview</h3>
                        </div>
                         <div class="p-6 transition-colors duration-200 bg-white min-h-[300px]" id="preview-success">
                             
                            <div class="flex items-start space-x-4 mb-6">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-lg preview-succ-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <h1 class="text-lg font-bold preview-succ-text">Order #12345</h1>
                                    <p class="text-sm preview-succ-sec-text">Thank you User!</p>
                                </div>
                            </div>

                            <!-- Timeline -->
                            <div class="border rounded p-4 mb-6">
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="fas fa-check-circle preview-succ-step1"></i>
                                    <span class="text-sm font-medium">Confirmed</span>
                                </div>
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="fas fa-truck preview-succ-step2"></i>
                                    <span class="text-sm font-medium">On its way</span>
                                </div>
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="fas fa-shipping-fast preview-succ-step3"></i>
                                    <span class="text-sm font-medium">Out for delivery</span>
                                </div>
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="fas fa-home preview-succ-step4"></i>
                                    <span class="text-sm font-medium">Delivered</span>
                                </div>
                            </div>

                            <button class="block w-full text-center py-2 rounded-lg font-medium mb-2 preview-succ-btn1">Download Invoice</button>
                            <button class="block w-full text-center py-2 rounded-lg font-medium mb-2 preview-succ-btn2">Continue Shopping</button>
                            <button class="block w-full text-center py-2 rounded-lg font-medium border preview-succ-btn3">Browse Products</button>

                         </div>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>

<script>
(function() {
    const container = document.getElementById('ajax-content-inner') || document;

    // Live Preview Logic for Checkout Page
    function updateCheckoutPreview() {
        const preview = document.getElementById('preview-checkout');
        if (!preview) return;
        
        // Progress Bar
        const progActiveBg = document.getElementById('input_ch_prog_active_bg');
        const progActiveText = document.getElementById('input_ch_prog_active_text');
        const progStepActive = preview.querySelector('.preview-ch-step-active');
        if (progStepActive) {
             if(progActiveBg) progStepActive.style.backgroundColor = progActiveBg.value;
             if(progActiveText) progStepActive.style.color = progActiveText.value;
        }

        const progInactiveBg = document.getElementById('input_ch_prog_inactive_bg');
        const progInactiveText = document.getElementById('input_ch_prog_inactive_text');
        const progStepInactive = preview.querySelector('.preview-ch-step-inactive');
        if(progStepInactive) {
            if(progInactiveBg) progStepInactive.style.backgroundColor = progInactiveBg.value;
            if(progInactiveText) progStepInactive.style.color = progInactiveText.value;
        }

        // Welcome Box
        const welcomeBg = document.getElementById('input_ch_welcome_bg');
        const welcomeText = document.getElementById('input_ch_welcome_text');
        const welcomeBorder = document.getElementById('input_ch_welcome_border');
        const welcomeBox = preview.querySelector('.preview-ch-welcome');
        const welcomeTxt = preview.querySelector('.preview-ch-welcome-text');
        
        if (welcomeBox) {
            if(welcomeBg) welcomeBox.style.backgroundColor = welcomeBg.value;
            if(welcomeBorder) welcomeBox.style.borderColor = welcomeBorder.value;
        }
        if (welcomeTxt && welcomeText) welcomeTxt.style.color = welcomeText.value;

        // Heading
        const headingColor = document.getElementById('input_ch_heading_color');
        const heading = preview.querySelector('.preview-ch-heading');
        if (heading && headingColor) heading.style.color = headingColor.value;

        // Label
        const labelColor = document.getElementById('input_ch_label_color');
        const label = preview.querySelector('.preview-ch-label');
        if (label && labelColor) label.style.color = labelColor.value;

        // Input
        const inputBorder = document.getElementById('input_ch_input_border');
        const inputTextColor = document.getElementById('input_ch_input_text_color');
        const input = preview.querySelector('.preview-ch-input');
        if (input) {
            if (inputBorder) input.style.borderColor = inputBorder.value;
            if (inputTextColor) input.style.color = inputTextColor.value;
        }

        // Summary
        const sumBg = document.getElementById('input_ch_summary_bg');
        const sumBorder = document.getElementById('input_ch_summary_border');
        const sumText = document.getElementById('input_ch_summary_text');
        const sumBox = preview.querySelector('.preview-ch-summary');
        const sumTexts = preview.querySelectorAll('.preview-ch-summary-text');
        
        if (sumBox) {
            if(sumBg) sumBox.style.backgroundColor = sumBg.value;
            if(sumBorder) sumBox.style.borderColor = sumBorder.value;
        }
        if(sumText) {
            sumTexts.forEach(el => el.style.color = sumText.value);
        }

        // Pay Button
        const payBg = document.getElementById('input_ch_pay_bg');
        const payText = document.getElementById('input_ch_pay_text');
        const payBtn = preview.querySelector('.preview-ch-pay-btn');
        if (payBtn) {
            if(payBg) payBtn.style.backgroundColor = payBg.value;
            if(payText) payBtn.style.color = payText.value;
        }
    }

    // Live Preview Logic for Success Page
    function updateSuccessPreview() {
        const preview = document.getElementById('preview-success');
        if (!preview) return;

        // Icon
        const iconColor = document.getElementById('input_succ_icon_color');
        const icon = preview.querySelector('.preview-succ-icon');
        if(icon && iconColor) {
             icon.style.background = iconColor.value;
        }

        // Main Text
        const textColor = document.getElementById('input_succ_text_color');
        const text = preview.querySelector('.preview-succ-text');
        if(text && textColor) text.style.color = textColor.value;

        // Secondary Text
        const secTextColor = document.getElementById('input_succ_sec_text_color');
        const secText = preview.querySelector('.preview-succ-sec-text');
        if(secText && secTextColor) secText.style.color = secTextColor.value;

        // Timeline Steps
        const step1Color = document.getElementById('input_succ_step1_color');
        const step1Icon = preview.querySelector('.preview-succ-step1');
        if(step1Icon && step1Color) step1Icon.style.color = step1Color.value;

        const step2Color = document.getElementById('input_succ_step2_color');
        const step2Icon = preview.querySelector('.preview-succ-step2');
        if(step2Icon && step2Color) step2Icon.style.color = step2Color.value;

        const step3Color = document.getElementById('input_succ_step3_color');
        const step3Icon = preview.querySelector('.preview-succ-step3');
        if(step3Icon && step3Color) step3Icon.style.color = step3Color.value;

        const step4Color = document.getElementById('input_succ_step4_color');
        const step4Icon = preview.querySelector('.preview-succ-step4');
        if(step4Icon && step4Color) step4Icon.style.color = step4Color.value;

        // Button 1
        const btn1Bg = document.getElementById('input_succ_btn1_bg');
        const btn1Text = document.getElementById('input_succ_btn1_text');
        const btn1 = preview.querySelector('.preview-succ-btn1');
        if(btn1) {
            if(btn1Bg) btn1.style.backgroundColor = btn1Bg.value;
            if(btn1Text) btn1.style.color = btn1Text.value;
        }

        // Button 2
        const btn2Bg = document.getElementById('input_succ_btn2_bg');
        const btn2Text = document.getElementById('input_succ_btn2_text');
        const btn2 = preview.querySelector('.preview-succ-btn2');
        if(btn2) {
            if(btn2Bg) btn2.style.backgroundColor = btn2Bg.value;
            if(btn2Text) btn2.style.color = btn2Text.value;
        }

        // Button 3
        const btn3Bg = document.getElementById('input_succ_btn3_bg');
        const btn3Text = document.getElementById('input_succ_btn3_text');
        const btn3Border = document.getElementById('input_succ_btn3_border');
        const btn3 = preview.querySelector('.preview-succ-btn3');
        if(btn3) {
            if(btn3Bg) btn3.style.backgroundColor = btn3Bg.value;
            if(btn3Text) btn3.style.color = btn3Text.value;
            if(btn3Border) btn3.style.borderColor = btn3Border.value;
        }
    }

    // Attach Listeners
    updateCheckoutPreview();
    updateSuccessPreview();

    const checkoutInputs = [
        'input_ch_prog_active_bg', 'input_ch_prog_active_text',
        'input_ch_prog_inactive_bg', 'input_ch_prog_inactive_text',
        'input_ch_welcome_bg', 'input_ch_welcome_text', 'input_ch_welcome_border',
        'input_ch_heading_color', 'input_ch_label_color',
        'input_ch_input_border', 'input_ch_input_focus', 'input_ch_input_text_color',
        'input_ch_summary_bg', 'input_ch_summary_border', 'input_ch_summary_text',
        'input_ch_pay_bg', 'input_ch_pay_text'
    ];

    checkoutInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateCheckoutPreview);
            const nextSibling = el.nextElementSibling;
            if(nextSibling && nextSibling.tagName === 'INPUT') {
                nextSibling.addEventListener('input', updateCheckoutPreview);
            }
        }
    });

    const successInputs = [
        'input_succ_icon_color', 'input_succ_text_color', 'input_succ_sec_text_color',
        'input_succ_step1_color', 'input_succ_step2_color', 'input_succ_step3_color', 'input_succ_step4_color',
        'input_succ_btn1_bg', 'input_succ_btn1_text',
        'input_succ_btn2_bg', 'input_succ_btn2_text',
        'input_succ_btn3_bg', 'input_succ_btn3_text', 'input_succ_btn3_border'
    ];

    successInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateSuccessPreview);
            const nextSibling = el.nextElementSibling;
            if(nextSibling && nextSibling.tagName === 'INPUT') {
                nextSibling.addEventListener('input', updateSuccessPreview);
            }
        }
    });
})();
</script>
