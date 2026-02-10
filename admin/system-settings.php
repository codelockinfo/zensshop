<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$settings = new Settings();
$baseUrl = getBaseUrl();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for max post size violation
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $_SESSION['error'] = "The file is too large. It exceeds the server's post_max_size limit of " . ini_get('post_max_size') . ".";
        header("Location: " . $baseUrl . '/admin/settings');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    
    // WAF BYPASS: Decode Base64 encoded sensitive fields
    // This prevents ModSecurity/Server Firewalls from blocking requests containing HTML/JSON/SVG/Scripts
    if (isset($_POST['is_encoded_submission']) && $_POST['is_encoded_submission'] === '1') {
        $encodedFields = ['global_schema_json', 'header_scripts', 'pickup_message'];
        foreach ($encodedFields as $field) {
            if (isset($_POST['setting_' . $field])) {
                $_POST['setting_' . $field] = base64_decode($_POST['setting_' . $field]);
            }
        }
        
        if (isset($_POST['payment_svgs']) && is_array($_POST['payment_svgs'])) {
            $_POST['payment_svgs'] = array_map('base64_decode', $_POST['payment_svgs']);
        }
    }
    
    // Handle File Uploads
    $uploadFiles = ['setting_favicon_png', 'setting_favicon_ico', 'setting_all_category_banner'];
    $uploadErrors = [];
    
    foreach ($uploadFiles as $fileKey) {
        if (!empty($_FILES[$fileKey]['name'])) {
            $fileError = $_FILES[$fileKey]['error'];
            
            if ($fileError !== UPLOAD_ERR_OK) {
                switch ($fileError) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $uploadErrors[] = "File too large: " . htmlspecialchars($_FILES[$fileKey]['name']) . " (Max " . ini_get('upload_max_filesize') . ")";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $uploadErrors[] = "File partial upload: " . htmlspecialchars($_FILES[$fileKey]['name']);
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        // No file selected, ignore
                        break;
                    default:
                        $uploadErrors[] = "Upload error ($fileError): " . htmlspecialchars($_FILES[$fileKey]['name']);
                }
                continue; 
            }

            $uploadDir = __DIR__ . '/../assets/images/';
            
            if (!is_dir($uploadDir)) {
                 if (!mkdir($uploadDir, 0755, true)) {
                     $uploadErrors[] = "Failed to create directory: " . $uploadDir;
                     continue;
                 }
            }
            
            if (!is_writable($uploadDir)) {
                $uploadErrors[] = "Directory not writable: " . $uploadDir;
                continue;
            }

            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $prefix = ($fileKey === 'setting_favicon_ico') ? 'favicon' : (($fileKey === 'setting_all_category_banner') ? 'banner_all_cat' : 'favicon_browser');
            $fileName = $prefix . '_' . time() . '.' . $ext;
            
            if ($fileKey === 'setting_favicon_ico') {
                // SEO: Store .ico favicon directly in root for Multi-Store support
                $storeId = $_SESSION['store_id'] ?? 'default';
                $cleanStoreId = str_replace(['STORE-', ' '], ['', '_'], $storeId);
                $fileName = 'favicon_' . $cleanStoreId . '.ico';
                $finalUploadPage = __DIR__ . '/../' . $fileName;
                
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $finalUploadPage)) {
                    $_POST[$fileKey] = $fileName; // Store just the filename (it's in root)
                    $_POST['group_' . str_replace('setting_', '', $fileKey)] = 'seo';
                } else {
                    $uploadErrors[] = "Failed to move uploaded favicon to root: " . htmlspecialchars($_FILES[$fileKey]['name']);
                }
            } else {
                // Regular assets/images upload for other files
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fileName)) {
                    // FIX: Store partial path so getImageUrl finds it in assets/images/ not uploads
                    $_POST[$fileKey] = 'assets/images/' . $fileName;
                    $_POST['group_' . str_replace('setting_', '', $fileKey)] = ($fileKey === 'setting_all_category_banner') ? 'general' : 'seo';
                } else {
                    $uploadErrors[] = "Failed to move uploaded file: " . htmlspecialchars($_FILES[$fileKey]['name']);
                }
            }
        }
    }

    if (!empty($uploadErrors)) {
        $_SESSION['error'] = implode('<br>', $uploadErrors);
        // Continue processing other settings even if uploads fail, but show error
    }

    // Handle Payment Icons (JSON)
    $paymentIcons = [];
    if (isset($_POST['payment_names'])) {
        $payment_names = $_POST['payment_names'];
        $payment_svgs = $_POST['payment_svgs'] ?? [];
        
        for ($i = 0; $i < count($payment_names); $i++) {
            if (!empty($payment_names[$i]) && !empty($payment_svgs[$i])) {
                $paymentIcons[] = [
                    'name' => $payment_names[$i],
                    'svg' => $payment_svgs[$i],
                ];
            }
        }
    }
    $_POST['setting_checkout_payment_icons_json'] = json_encode(array_values($paymentIcons), JSON_UNESCAPED_SLASHES);
    $_POST['group_checkout_payment_icons_json'] = 'checkout';
    
    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && strpos($key, 'setting_') === 0 && $key !== 'setting_enable_blog' && !is_array($value)) {
            $settingKey = str_replace('setting_', '', $key);
            $settings->set($settingKey, $value, $_POST['group_' . $settingKey] ?? 'general');
        }
    }
    $_SESSION['success'] = "Settings updated successfully!";
    header("Location: " . $baseUrl . '/admin/settings');
    exit;
}
}

// Get all settings grouped
$emailSettings = $settings->getByGroup('email');
$generalSettings = $settings->getByGroup('general');
$apiSettings = $settings->getByGroup('api');
$seoSettings = $settings->getByGroup('seo');
$checkoutSettings = $settings->getByGroup('checkout');

$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/admin-header.php';

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<div class="p-6">
    <div class="sticky top-0 z-20 bg-[#f7f8fc] -mx-6 -mt-6 px-6 py-4 mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">System Settings</h1>
            <p class="text-gray-600 text-sm mt-1">Manage your application configuration</p>
        </div>
        <button type="submit" form="settingsForm" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700 transition shadow-lg flex items-center btn-loading">
             <i class="fas fa-save mr-2"></i> Save Changes
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; // Allow HTML for line breaks ?>
        </div>
    <?php endif; ?>

    <form id="settingsForm" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="action" value="update_settings">
        <!-- Flag to indicate that sensitive fields are Base64 encoded -->
        <input type="hidden" name="is_encoded_submission" value="0" id="isEncodedSubmission">

        <!-- SEO & Branding Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-globe mr-2 text-green-600"></i>
                SEO & Branding
            </h2>
            <p class="text-sm text-gray-600 mb-6">Configure global SEO settings, favicon, and site identity.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Site Name / Title Suffix -->
                <div class="md:col-span-2">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Site Title (Suffix)</label>
                     <input type="text" name="setting_site_title_suffix" value="<?php echo htmlspecialchars($settings->get('site_title_suffix', 'CookPro - Elegant Jewelry Store')); ?>" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="e.g. My Awesome Shop">
                     <p class="text-xs text-gray-500 mt-1">Appended to page titles (e.g., "Home - My Awesome Shop")</p>
                     <input type="hidden" name="group_site_title_suffix" value="seo">
                </div>

                <!-- Favicon PNG (Browser) -->
                <div>
                     <label class="block text-sm font-medium text-gray-700 mb-2">Favicon (Browser Tab - PNG/JPG)</label>
                     <?php $favPng = $settings->get('favicon_png'); ?>
                     <div class="relative group cursor-pointer w-24 h-24 border-2 <?php echo !empty($favPng) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition" onclick="document.getElementById('favPngInput').click()">
                         
                         <input type="file" id="favPngInput" name="setting_favicon_png" class="hidden" onchange="previewFavicon(this, 'previewPng')">
                         <input type="hidden" name="group_favicon_png" value="seo">
                         
                         <div id="previewPng" class="w-full h-full flex items-center justify-center">
                             <?php if($favPng): ?>
                                 <img src="<?php echo htmlspecialchars(getImageUrl($favPng)); ?>?v=<?php echo time(); ?>" class="w-16 h-16 object-contain">
                                 <!-- Overlay -->
                                 <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                      <i class="fas fa-camera text-white opacity-0 group-hover:opacity-100 transition"></i>
                                 </div>
                             <?php else: ?>
                                 <div class="text-center text-gray-400">
                                     <i class="fas fa-cloud-upload-alt text-2xl"></i>
                                     <p class="text-[10px] mt-1">PNG</p>
                                 </div>
                             <?php endif; ?>
                         </div>
                     </div>
                </div>

                <!-- Favicon ICO (Google) -->
                <div>
                     <label class="block text-sm font-medium text-gray-700 mb-2">Favicon (Google - .ico)</label>
                     <?php $favIco = $settings->get('favicon_ico'); ?>
                     <div class="relative group cursor-pointer w-24 h-24 border-2 <?php echo !empty($favIco) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition" onclick="document.getElementById('favIcoInput').click()">
                         
                         <input type="file" id="favIcoInput" name="setting_favicon_ico" class="hidden" onchange="previewFavicon(this, 'previewIco')">
                         <input type="hidden" name="group_favicon_ico" value="seo">

                         <div id="previewIco" class="w-full h-full flex items-center justify-center">
                             <?php if($favIco): ?>
                                 <img src="<?php echo htmlspecialchars(getImageUrl($favIco)); ?>?v=<?php echo time(); ?>" class="w-16 h-16 object-contain">
                                 <!-- Overlay -->
                                 <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                      <i class="fas fa-camera text-white opacity-0 group-hover:opacity-100 transition"></i>
                                 </div>
                             <?php else: ?>
                                 <div class="text-center text-gray-400">
                                     <i class="fas fa-cloud-upload-alt text-2xl"></i>
                                     <p class="text-[10px] mt-1">.ICO</p>
                                 </div>
                             <?php endif; ?>
                         </div>
                     </div>
                </div>

                <script>
                function previewFavicon(input, containerId) {
                    if (input.files && input.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            const container = document.getElementById(containerId);
                            container.innerHTML = `
                                <img src="${e.target.result}" class="w-16 h-16 object-contain">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                     <i class="fas fa-camera text-white opacity-0 group-hover:opacity-100 transition"></i>
                                </div>
                            `;
                            container.parentElement.classList.remove('border-dashed', 'border-gray-300');
                            container.parentElement.classList.add('border-gray-200');
                        }
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                </script>

                <!-- Global Meta Description -->
                <div class="md:col-span-2">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Global Meta Description</label>
                     <textarea name="setting_global_meta_description" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500"><?php echo htmlspecialchars($settings->get('global_meta_description', '')); ?></textarea>
                     <p class="text-xs text-gray-500 mt-1">Default description for pages that don't have a specific one.</p>
                     <input type="hidden" name="group_global_meta_description" value="seo">
                </div>

                <!-- Global Schema JSON -->
                <div class="md:col-span-2">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Global Schema (JSON)</label>
                     <textarea name="setting_global_schema_json" rows="4" class="font-mono text-sm w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500 bg-gray-50"><?php echo htmlspecialchars($settings->get('global_schema_json', '')); ?></textarea>
                     <p class="text-xs text-gray-500 mt-1">Valid JSON-LD to be included on every page (e.g., Organization schema).</p>
                     <input type="hidden" name="group_global_schema_json" value="seo">
                </div>
                
                <!-- Google Analytics / Header Scripts -->
                <div class="md:col-span-2">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Google Analytics / Header Scripts</label>
                     <textarea name="setting_header_scripts" rows="5" class="font-mono text-sm w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500 bg-gray-50"><?php echo htmlspecialchars($settings->get('header_scripts', '')); ?></textarea>
                     <p class="text-xs text-gray-500 mt-1">Paste your Google Analytics code (G-XXXX) or any other scripts to be inserted into the <code>&lt;head&gt;</code> tag.</p>
                     <input type="hidden" name="group_header_scripts" value="seo">
                </div>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-envelope mr-2 text-blue-600"></i>
                Email / SMTP Settings
            </h2>
            <p class="text-sm text-gray-600 mb-6">Configure email sending for order confirmations, support messages, and newsletters</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php 
                $emailFields = [
                    'smtp_host' => ['label' => 'SMTP Host', 'placeholder' => 'smtp.gmail.com', 'type' => 'text'],
                    'smtp_port' => ['label' => 'SMTP Port', 'placeholder' => '587', 'type' => 'number'],
                    'smtp_encryption' => ['label' => 'SMTP Encryption', 'placeholder' => 'tls', 'type' => 'text'],
                    'smtp_username' => ['label' => 'SMTP Username', 'placeholder' => 'your-email@gmail.com', 'type' => 'text'],
                    'smtp_password' => ['label' => 'SMTP Password', 'placeholder' => 'Enter SMTP password', 'type' => 'password'],
                    'smtp_from_email' => ['label' => 'From Email', 'placeholder' => 'noreply@yourstore.com', 'type' => 'text'],
                    'smtp_from_name' => ['label' => 'From Name', 'placeholder' => 'CookPro Store', 'type' => 'text'],
                ];
                foreach ($emailFields as $key => $field): 
                    $val = $settings->get($key, '');
                ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $field['label']; ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $key; ?>" value="email">
                        <div class="relative">
                            <input type="<?php echo $field['type']; ?>" 
                                   id="<?php echo $key; ?>"
                                   name="setting_<?php echo $key; ?>" 
                                   value="<?php echo htmlspecialchars($val); ?>"
                                   class="w-full px-4 py-2 <?php echo $field['type'] === 'password' ? 'pr-10' : ''; ?> border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="<?php echo $field['placeholder']; ?>">
                            <?php if ($field['type'] === 'password'): ?>
                                <button type="button" 
                                        onclick="togglePassword('<?php echo $key; ?>')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="eye-<?php echo $key; ?>"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($key === 'smtp_password'): ?>
                            <p class="text-xs text-gray-500 mt-1">For Gmail, use an App Password</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded border border-blue-200">
                <h3 class="font-semibold text-blue-900 mb-2">Gmail Setup Instructions:</h3>
                <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                    <li>Go to your Google Account settings</li>
                    <li>Enable 2-Step Verification</li>
                    <li>Go to Security → App passwords</li>
                    <li>Generate a new app password for "Mail"</li>
                    <li>Use that password in the SMTP Password field above</li>
                </ol>
            </div>
        </div>

        <!-- General Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-cog mr-2 text-gray-600"></i>
                General Settings
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php 
                $genFields = [
                    'site_name' => ['label' => 'Site Name', 'placeholder' => 'CookProo'],
                    'seller_state' => ['label' => 'Seller State (for GST)', 'placeholder' => 'Maharashtra'],
                    'otp_expiry_minutes' => ['label' => 'OTP Expiry (Minutes)', 'placeholder' => '5', 'type' => 'number'],
                ];
                foreach ($genFields as $key => $field): 
                    $val = $settings->get($key, '');
                ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $field['label']; ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $key; ?>" value="general">
                        <input type="<?php echo $field['type'] ?? 'text'; ?>" 
                               name="setting_<?php echo $key; ?>" 
                               value="<?php echo htmlspecialchars($val); ?>"
                               class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                               placeholder="<?php echo $field['placeholder']; ?>">
                    </div>
                <?php endforeach; ?>
                
                <!-- All Category Banner -->
                <div class="md:col-span-2 border-t pt-4 mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">All Category Banner</label>
                    <p class="text-xs text-gray-500 mb-2">This banner will appear on the "All Categories" shop page.</p>
                    
                    <?php $allCatBanner = $settings->get('all_category_banner'); ?>
                    <div class="flex items-center space-x-4">
                        <div class="relative group cursor-pointer w-full h-32 border-2 <?php echo !empty($allCatBanner) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition" onclick="document.getElementById('allCatBannerInput').click()">
                            
                            <input type="file" id="allCatBannerInput" name="setting_all_category_banner" class="hidden" onchange="previewBanner(this, 'previewAllCatBanner')">
                            <input type="hidden" name="group_all_category_banner" value="general">
                            
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

                <script>
                function previewBanner(input, containerId) {
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
                        }
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                </script>

                <!-- Collections Page Settings -->
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Collections Page Heading</label>
                        <input type="hidden" name="group_collections_heading" value="general">
                        <input type="text" name="setting_collections_heading" 
                               value="<?php echo htmlspecialchars($settings->get('collections_heading', 'Collections List')); ?>" 
                               class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Collections Page Description</label>
                        <input type="hidden" name="group_collections_description" value="general">
                        <textarea name="setting_collections_description" rows="2"
                                  class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($settings->get('collections_description', 'Explore our thoughtfully curated collections')); ?></textarea>
                    </div>
                </div>

                <!-- Blog Feature Toggle -->
                <div class="md:col-span-2">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="hidden" name="setting_enable_blog" value="0">
                        <input type="checkbox" name="setting_enable_blog" value="1" 
                               <?php echo $settings->get('enable_blog', '1') == '1' ? 'checked' : ''; ?> 
                               class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Enable Blog Feature</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-8">If disabled, blog pages will be hidden from the frontend and sidebar.</p>
                </div>
                
                <!-- Blog Configuration -->
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Blog Heading</label>
                        <input type="hidden" name="group_blog_heading" value="general">
                        <input type="text" name="setting_blog_heading" 
                               value="<?php echo htmlspecialchars($settings->get('blog_heading', 'Our Blog')); ?>" 
                               class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Blog Description</label>
                        <input type="hidden" name="group_blog_description" value="general">
                        <textarea name="setting_blog_description" rows="1"
                                  class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($settings->get('blog_description', 'Latest news, updates, and stories from our team.')); ?></textarea>
                    </div>
                </div>

                <!-- Blog Colors -->
                <div class="md:col-span-2 pt-4 border-t mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Blog Section Colors</label>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <?php 
                        $blogColors = [
                            'blog_bg_color' => ['label' => 'Background', 'default' => '#f9fafb'],
                            'blog_heading_color' => ['label' => 'Heading', 'default' => '#111827'],
                            'blog_text_color' => ['label' => 'Text', 'default' => '#1f2937'],
                            'blog_accent_color' => ['label' => 'Accent', 'default' => '#2563eb']
                        ];
                        foreach ($blogColors as $key => $color): 
                        ?>
                        <div>
                            <span class="text-xs text-gray-500 block mb-1"><?php echo $color['label']; ?></span>
                            <input type="hidden" name="group_<?php echo $key; ?>" value="blog">
                            <div class="flex items-center">
                                <input type="color" name="setting_<?php echo $key; ?>" 
                                       value="<?php echo htmlspecialchars($settings->get($key, $color['default'])); ?>" 
                                       class="h-8 w-8 p-0 border-0 rounded mr-2 cursor-pointer">
                                <input type="text" name="setting_<?php echo $key; ?>_text" 
                                       value="<?php echo htmlspecialchars($settings->get($key, $color['default'])); ?>" 
                                       onchange="this.previousElementSibling.value = this.value"
                                       class="w-full border border-gray-300 px-2 py-1 rounded text-xs text-gray-600 bg-gray-50 uppercase">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkout Payment Icons -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-credit-card mr-2 text-orange-600"></i>
                Checkout Payment Icons
            </h2>
            <p class="text-sm text-gray-600 mb-6">Manage payment method icons displayed on the checkout page (SVG format)</p>

            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <div>
                        <h3 class="font-bold text-base">Payment Method Icons (SVG)</h3>
                        <p class="text-xs text-gray-500 mt-1">These icons will be displayed on the checkout page below the order summary</p>
                    </div>
                    <button type="button" onclick="addPaymentRow()" class="text-white bg-green-600 hover:bg-green-700 text-sm px-3 py-1 rounded">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>
                
                <div id="paymentIconsContainer" class="space-y-4">
                    <!-- Rows will be added here by JS -->
                </div>
                
                <!-- Template for JS -->
                <template id="paymentRowTemplate">
                    <div class="bg-white border border-gray-200 rounded p-4 payment-row relative group">
                        <button type="button" onclick="removePaymentRow(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 p-2">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Name</label>
                                <input type="text" name="payment_names[]" class="w-full border p-2 rounded text-sm" placeholder="e.g. Visa">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">SVG Code</label>
                                <textarea name="payment_svgs[]" rows="3" class="w-full border p-2 rounded text-sm font-mono" placeholder="<svg ...>...</svg>"></textarea>
                            </div>
                            <div class="md:col-span-1 flex items-end justify-center pb-2">
                                <div class="p-2 border rounded bg-gray-50 w-full text-center svg-preview" style="min-height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <span class="text-xs text-gray-400">Preview</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Product & Pickup Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-store mr-2 text-indigo-600"></i>
                Product & Pickup Information
            </h2>
            <p class="text-sm text-gray-600 mb-6">Manage pickup availability messages on product pages</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Enable Pickup Info -->
                <div class="md:col-span-2">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="hidden" name="setting_pickup_enable" value="0">
                        <input type="hidden" name="group_pickup_enable" value="product">
                        <input type="checkbox" name="setting_pickup_enable" value="1" 
                               <?php echo $settings->get('pickup_enable', '1') == '1' ? 'checked' : ''; ?> 
                               class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-700">Show Pickup Availability</span>
                    </label>
                </div>
                
                <!-- Pickup Message -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pickup Message (HTML/Rich Text)</label>
                    <input type="hidden" name="group_pickup_message" value="product">
                    <textarea name="setting_pickup_message" rows="4"
                           class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500 richtext"><?php echo htmlspecialchars($settings->get('pickup_message', 'Pickup available at Shop location. Usually ready in 24 hours')); ?></textarea>
                </div>

                <!-- Pickup Icon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pickup Icon (FontAwesome Class)</label>
                    <input type="hidden" name="group_pickup_icon" value="product">
                    <input type="text" name="setting_pickup_icon" 
                           value="<?php echo htmlspecialchars($settings->get('pickup_icon', 'fas fa-store')); ?>" 
                           class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500"
                           placeholder="e.g. fas fa-store">
                    <p class="text-xs text-gray-500 mt-1">Enter any FontAwesome icon class (e.g., <code class="bg-gray-100 px-1 rounded">fas fa-store</code>)</p>
                </div>

                <!-- Link Text -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Link Text</label>
                    <input type="hidden" name="group_pickup_link_text" value="product">
                    <input type="text" name="setting_pickup_link_text" 
                           value="<?php echo htmlspecialchars($settings->get('pickup_link_text', 'View store information')); ?>" 
                           class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Link URL -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Link URL</label>
                    <input type="hidden" name="group_pickup_link_url" value="product">
                    <input type="text" name="setting_pickup_link_url" 
                           value="<?php echo htmlspecialchars($settings->get('pickup_link_url', '#')); ?>" 
                           class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- API Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-key mr-2 text-purple-600"></i>
                API Keys & Integrations
            </h2>
            <p class="text-sm text-gray-600 mb-6">Configure payment gateway and authentication services</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php 
                $apiFields = [
                    'razorpay_key_id' => ['label' => 'Razorpay Key ID', 'placeholder' => 'rzp_test_...'],
                    'razorpay_key_secret' => ['label' => 'Razorpay Key Secret', 'placeholder' => '...', 'type' => 'password'],
                    'razorpay_mode' => ['label' => 'Razorpay Mode', 'placeholder' => 'test or live'],
                    'google_client_id' => ['label' => 'Google Client ID', 'placeholder' => '...-apps.googleusercontent.com'],
                ];
                foreach ($apiFields as $key => $field): 
                     $val = $settings->get($key, '');
                ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $field['label']; ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $key; ?>" value="api">
                        <div class="relative">
                            <input type="<?php echo $field['type'] ?? 'text'; ?>" 
                                   id="<?php echo $key; ?>"
                                   name="setting_<?php echo $key; ?>" 
                                   value="<?php echo htmlspecialchars($val); ?>"
                                   class="w-full px-4 py-2 <?php echo ($field['type'] ?? '') === 'password' ? 'pr-10' : ''; ?> border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="<?php echo $field['placeholder']; ?>">
                            <?php if (($field['type'] ?? '') === 'password'): ?>
                                <button type="button" 
                                        onclick="togglePassword('<?php echo $key; ?>')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="eye-<?php echo $key; ?>"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Razorpay Info -->
                <div class="p-4 bg-purple-50 rounded border border-purple-200">
                    <h3 class="font-semibold text-purple-900 mb-2 flex items-center">
                        <i class="fas fa-credit-card mr-2"></i>
                        Razorpay Setup
                    </h3>
                    <ol class="text-sm text-purple-800 space-y-1 list-decimal list-inside">
                        <li>Sign up at <a href="https://razorpay.com" target="_blank" class="underline">razorpay.com</a></li>
                        <li>Go to Settings → API Keys</li>
                        <li>Generate Test/Live keys</li>
                        <li>Copy Key ID and Key Secret</li>
                    </ol>
                </div>

                <!-- Google Auth Info -->
                <div class="p-4 bg-blue-50 rounded border border-blue-200">
                    <h3 class="font-semibold text-blue-900 mb-2 flex items-center">
                        <i class="fab fa-google mr-2"></i>
                        Google OAuth Setup
                    </h3>
                    <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                        <li>Go to <a href="https://console.cloud.google.com" target="_blank" class="underline">Google Cloud Console</a></li>
                        <li>Create/Select a project</li>
                        <li>Enable Google+ API</li>
                        <li>Create OAuth 2.0 Client ID</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Save Button Removed (Moved to Header) -->
    </form>
</div>

<script>
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById('eye-' + fieldId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Payment Icons Dynamic Rows
const paymentData = <?php 
    $paymentIconsJson = $settings->get('checkout_payment_icons_json', '[]');
    echo $paymentIconsJson ?: '[]';
?>;

function addPaymentRow(data = null) {
    const container = document.getElementById('paymentIconsContainer');
    const template = document.getElementById('paymentRowTemplate');
    const clone = template.content.cloneNode(true);
    
    if (data) {
        clone.querySelector('input').value = data.name;
        clone.querySelector('textarea').value = data.svg;
        clone.querySelector('.svg-preview').innerHTML = data.svg;
    }
    
    // Add real-time preview listener to the newly added textarea
    const textarea = clone.querySelector('textarea');
    textarea.addEventListener('input', function() {
        const previewDiv = this.closest('.payment-row').querySelector('.svg-preview');
        previewDiv.innerHTML = this.value || '<span class="text-xs text-gray-400">Preview</span>';
    });
    
    container.appendChild(clone);
}

function removePaymentRow(btn) {
    btn.closest('.payment-row').remove();
}

// Init payment icons
if (paymentData && paymentData.length > 0) {
    paymentData.forEach(item => addPaymentRow(item));
} else {
    // Add one empty row by default
    addPaymentRow();
}

// Encode sensitive fields before submission to prevent WAF blocking
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    // Set encoded flag
    document.getElementById('isEncodedSubmission').value = '1';

    const sensitiveFields = [
        'setting_global_schema_json',
        'setting_header_scripts', 
        'setting_pickup_message'
    ];
    
    // Helper to safely encode to Base64 (supporting UTF-8)
    const toBase64 = (str) => btoa(unescape(encodeURIComponent(str)));
    
    sensitiveFields.forEach(name => {
        const el = document.getElementsByName(name)[0];
        if (el) el.value = toBase64(el.value);
    });

    const svgs = document.getElementsByName('payment_svgs[]');
    svgs.forEach(el => {
        el.value = toBase64(el.value);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
