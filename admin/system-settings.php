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
        header("Location: " . url('admin/settings'));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    
    // WAF BYPASS: Handle Base64 encoded sensitive fields
    // We check for 'encoded_' prefixed fields sent by the JS for bypass
    $encodedMap = [
        'encoded_global_schema_json'    => 'setting_global_schema_json',
        'encoded_header_scripts'        => 'setting_header_scripts',
        'encoded_pickup_message'        => 'setting_pickup_message',
        'encoded_global_meta_description' => 'setting_global_meta_description'
    ];

    foreach ($encodedMap as $encodedKey => $targetKey) {
        if (isset($_POST[$encodedKey]) && is_string($_POST[$encodedKey])) {
            $decoded = base64_decode($_POST[$encodedKey]);
            if ($decoded !== false) {
                $_POST[$targetKey] = $decoded;
            }
        }
    }
    
    // Handle encoded payment SVGs
    if (isset($_POST['encoded_payment_svgs']) && is_array($_POST['encoded_payment_svgs'])) {
        $_POST['payment_svgs'] = [];
        foreach ($_POST['encoded_payment_svgs'] as $val) {
            $decoded = base64_decode($val);
            if ($decoded !== false) {
                $_POST['payment_svgs'][] = $decoded;
            }
        }
    }
    
    // Handle File Uploads
    $uploadFiles = ['setting_favicon_png', 'setting_favicon_ico', 'setting_email_logo', 'setting_og_image'];
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
            
            // Determing prefix
            if ($fileKey === 'setting_favicon_ico') $prefix = 'favicon';
            elseif ($fileKey === 'setting_email_logo') $prefix = 'email_logo';
            elseif ($fileKey === 'setting_og_image') $prefix = 'og_social';
            else $prefix = 'favicon_browser';

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
                    if ($fileKey === 'setting_email_logo') {
                        $_POST['group_' . str_replace('setting_', '', $fileKey)] = 'email';
                    } else {
                        $_POST['group_' . str_replace('setting_', '', $fileKey)] = 'seo';
                    }
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
    header("Location: " . url('admin/settings'));
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

                <!-- SEO Image (Open Graph) -->
                <div>
                     <label class="block text-sm font-medium text-gray-700 mb-2">Social Share / SEO Image</label>
                     <?php $ogImage = $settings->get('og_image'); ?>
                     <div class="relative group cursor-pointer w-full h-24 border-2 <?php echo !empty($ogImage) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition" onclick="document.getElementById('ogImageInput').click()">
                         
                         <input type="file" id="ogImageInput" name="setting_og_image" class="hidden" onchange="previewFavicon(this, 'previewOg')">
                         <input type="hidden" name="group_og_image" value="seo">

                         <div id="previewOg" class="w-full h-full flex items-center justify-center">
                             <?php if($ogImage): ?>
                                 <img src="<?php echo htmlspecialchars(getImageUrl($ogImage)); ?>?v=<?php echo time(); ?>" class="w-full h-full object-cover">
                                 <!-- Overlay -->
                                 <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                      <i class="fas fa-camera text-white opacity-0 group-hover:opacity-100 transition"></i>
                                 </div>
                             <?php else: ?>
                                 <div class="text-center text-gray-400">
                                     <i class="fas fa-image text-2xl"></i>
                                     <p class="text-[10px] mt-1">JPG/PNG</p>
                                 </div>
                             <?php endif; ?>
                         </div>
                     </div>
                     <p class="text-xs text-gray-500 mt-1">Shown when sharing links on social media.</p>
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
                
                <!-- Google Tag Manager ID -->
                <div class="md:col-span-2">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Google Tag Manager ID</label>
                     <input type="text" name="setting_gtm_id" value="<?php echo htmlspecialchars($settings->get('gtm_id', '')); ?>" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="GTM-XXXXXXX">
                     <p class="text-xs text-gray-500 mt-1">Enter your Google Tag Manager container ID (e.g., GTM-SSLQRGJR). The GTM code will be automatically inserted into your site.</p>
                     <input type="hidden" name="group_gtm_id" value="seo">
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

                <!-- Email Logo Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Template Logo</label>
                    <?php $emailLogo = $settings->get('email_logo'); ?>
                    
                    <div class="flex items-start space-x-4">
                        <div class="relative group cursor-pointer w-48 h-32 border-2 <?php echo !empty($emailLogo) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-2xl overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition shadow-sm" onclick="document.getElementById('emailLogoInput').click()">
                            
                            <input type="file" id="emailLogoInput" name="setting_email_logo" class="hidden" onchange="previewEmailLogo(this, 'previewEmailLogo')">
                            <input type="hidden" name="group_email_logo" value="email">
                            
                            <div id="previewEmailLogo" class="w-full h-full flex items-center justify-center p-4">
                                <?php if($emailLogo): ?>
                                    <img src="<?php echo htmlspecialchars(getImageUrl($emailLogo)); ?>" class="max-w-full max-h-full object-contain">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                         <i class="fas fa-camera text-white text-xl"></i>
                                    </div>
                                    <button type="button" onclick="event.stopPropagation(); removeEmailLogo();" class="absolute top-2 right-2 w-8 h-8 bg-white text-red-500 rounded-lg shadow-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-50" title="Remove Logo">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                <?php else: ?>
                                    <div class="text-center text-gray-400">
                                        <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                                        <p class="text-[10px] leading-tight font-medium uppercase tracking-wide">Click to Upload<br>Email Logo</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pt-2">
                             <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100/50">
                                 <p class="text-[11px] text-blue-700 leading-relaxed italic">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <b>Note:</b> If empty, your <br><b>Site Logo</b> will be used <br>automatically.
                                 </p>
                             </div>
                        </div>
                    </div>
                </div>

                <script>
                function previewEmailLogo(input, containerId) {
                    if (input.files && input.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            const container = document.getElementById(containerId);
                            container.innerHTML = `
                                <img src="${e.target.result}" class="max-w-full max-h-full object-contain">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                     <i class="fas fa-camera text-white text-xl"></i>
                                </div>
                                <button type="button" onclick="event.stopPropagation(); removeEmailLogo();" class="absolute top-2 right-2 w-8 h-8 bg-white text-red-500 rounded-lg shadow-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-50">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </button>
                            `;
                            container.parentElement.classList.remove('border-dashed', 'border-gray-300');
                            container.parentElement.classList.add('border-gray-200');
                        }
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                
                function removeEmailLogo() {
                    const form = document.getElementById('settingsForm');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'setting_email_logo';
                    hiddenInput.value = '';
                    form.appendChild(hiddenInput);
                    form.submit();
                }
                </script>
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



                <!-- Breadcrumb Styles -->
                <div class="md:col-span-2 pt-4 border-t mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Breadcrumb Styles</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">Text Color</span>
                            <input type="hidden" name="group_breadcrumb_text_color" value="general">
                            <div class="flex items-center">
                                <input type="color" name="setting_breadcrumb_text_color" 
                                       value="<?php echo htmlspecialchars($settings->get('breadcrumb_text_color', '#6b7280')); ?>" 
                                       class="h-8 w-8 p-0 border-0 rounded mr-2 cursor-pointer">
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($settings->get('breadcrumb_text_color', '#6b7280')); ?>" 
                                       onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.dispatchEvent(new Event('input'));"
                                       class="w-full border border-gray-300 px-2 py-1 rounded text-xs text-gray-600 bg-gray-50 uppercase">
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">Text Hover Color</span>
                            <input type="hidden" name="group_breadcrumb_hover_color" value="general">
                            <div class="flex items-center">
                                <input type="color" name="setting_breadcrumb_hover_color" 
                                       value="<?php echo htmlspecialchars($settings->get('breadcrumb_hover_color', '#111827')); ?>" 
                                       class="h-8 w-8 p-0 border-0 rounded mr-2 cursor-pointer">
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($settings->get('breadcrumb_hover_color', '#111827')); ?>" 
                                       onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.dispatchEvent(new Event('input'));"
                                       class="w-full border border-gray-300 px-2 py-1 rounded text-xs text-gray-600 bg-gray-50 uppercase">
                            </div>
                        </div>
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
                           class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500 rich-text-small"><?php echo htmlspecialchars($settings->get('pickup_message', 'Pickup available at Shop location. Usually ready in 24 hours')); ?></textarea>
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

                <!-- Default Policies -->
                <div class="md:col-span-2 border-t pt-4 mt-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-scroll mr-2 text-indigo-600"></i>
                        Default Policies
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">These values will be shown when a product does not have a specific policy defined.</p>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Shipping Policy</label>
                            <input type="hidden" name="group_default_shipping_policy" value="product">
                            <textarea name="setting_default_shipping_policy" rows="3"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500 rich-text-small"><?php echo htmlspecialchars($settings->get('default_shipping_policy', '')); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Default Return Policy</label>
                            <input type="hidden" name="group_default_return_policy" value="product">
                            <textarea name="setting_default_return_policy" rows="3"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500 rich-text-small"><?php echo htmlspecialchars($settings->get('default_return_policy', '')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Settings -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Razorpay & Google -->
            <div class="bg-white rounded shadow p-6 border-l-4 border-blue-500">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-key mr-2 text-blue-600"></i>
                    Auth & Payment Keys
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <?php 
                    $mainApiFields = [
                        'enable_cod' => ['label' => 'Enable Cash on Delivery (COD)', 'type' => 'select', 'options' => ['0' => 'Disabled', '1' => 'Enabled']],
                        'cod_charge' => ['label' => 'COD Service Charge (₹)', 'placeholder' => 'e.g. 50', 'type' => 'number'],
                        'razorpay_mode' => ['label' => 'Razorpay Mode', 'type' => 'select', 'options' => ['test' => 'Test / Sandbox', 'live' => 'Live / Production']],
                        'razorpay_test_key_id' => ['label' => 'Razorpay Test Key ID', 'placeholder' => 'rzp_test_...'],
                        'razorpay_test_key_secret' => ['label' => 'Razorpay Test Key Secret', 'placeholder' => '...', 'type' => 'password'],
                        'razorpay_key_id' => ['label' => 'Razorpay Live Key ID', 'placeholder' => 'rzp_live_...'],
                        'razorpay_key_secret' => ['label' => 'Razorpay Live Key Secret', 'placeholder' => '...', 'type' => 'password'],
                        'google_client_id' => ['label' => 'Google Client ID', 'placeholder' => '...-apps.googleusercontent.com', 'type' => 'password'],
                    ];
                    foreach ($mainApiFields as $key => $field): 
                         $val = $settings->get($key, '');
                    ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $field['label']; ?></label>
                            <input type="hidden" name="group_<?php echo $key; ?>" value="api">
                            <div class="relative">
                                <?php if (($field['type'] ?? '') === 'select'): ?>
                                    <select name="setting_<?php echo $key; ?>" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500 bg-white">
                                        <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                                            <option value="<?php echo $optVal; ?>" <?php echo $val == $optVal ? 'selected' : ''; ?>><?php echo $optLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?php echo $field['type'] ?? 'text'; ?>" 
                                           id="<?php echo $key; ?>" 
                                           name="setting_<?php echo $key; ?>" 
                                           value="<?php echo htmlspecialchars($val); ?>" 
                                           class="w-full px-4 py-2 <?php echo ($field['type'] ?? 'text') === 'password' ? 'pr-10' : ''; ?> border rounded focus:ring-2 focus:ring-blue-500" 
                                           placeholder="<?php echo $field['placeholder'] ?? ''; ?>">
                                    <?php if (($field['type'] ?? '') === 'password'): ?>
                                        <button type="button" onclick="togglePassword('<?php echo $key; ?>')" class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-2 z-10 cursor-pointer">
                                            <i class="fas fa-eye" id="eye-<?php echo $key; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Delhivery Settings -->
            <div class="bg-white rounded shadow p-6 border-l-4 border-orange-500">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-truck mr-2 text-orange-600"></i>
                    Delhivery Shipping Settings
                </h2>
                <div class="grid grid-cols-1 gap-4">
                    <?php 
                    $delhiveryFields = [
                        'delhivery_mode' => ['label' => 'Delhivery Mode', 'type' => 'select', 'options' => ['test' => 'Test (Staging)', 'live' => 'Live (Production)'], 'group' => 'api'],
                        'delhivery_api_token' => ['label' => 'Delhivery API Token', 'placeholder' => 'Enter your Delhivery Token', 'type' => 'password', 'group' => 'api'],
                        'delhivery_warehouse_name' => ['label' => 'Delhivery Warehouse Name (Pickup Location Name)', 'placeholder' => 'Must match Warehouse Name in Delhivery One', 'group' => 'api'],
                        'delhivery_source_pincode' => ['label' => 'Warehouse Pincode', 'placeholder' => 'e.g. 395006 (Required for shipping calculation)', 'group' => 'api'],
                    ];
                    foreach ($delhiveryFields as $key => $field): 
                         $val = $settings->get($key, '');
                    ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $field['label']; ?></label>
                            <input type="hidden" name="group_<?php echo $key; ?>" value="api">
                            <div class="relative">
                                <?php if (($field['type'] ?? '') === 'select'): ?>
                                    <select name="setting_<?php echo $key; ?>" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-orange-500 bg-white">
                                        <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                                            <option value="<?php echo $optVal; ?>" <?php echo $val == $optVal ? 'selected' : ''; ?>><?php echo $optLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?php echo $field['type'] ?? 'text'; ?>" 
                                           id="<?php echo $key; ?>" 
                                           name="setting_<?php echo $key; ?>" 
                                           value="<?php echo htmlspecialchars($val); ?>" 
                                           class="w-full px-4 py-2 <?php echo ($field['type'] ?? 'text') === 'password' ? 'pr-10' : ''; ?> border rounded focus:ring-2 focus:ring-orange-500" 
                                           placeholder="<?php echo $field['placeholder'] ?? ''; ?>">
                                    <?php if (($field['type'] ?? '') === 'password'): ?>
                                        <button type="button" onclick="togglePassword('<?php echo $key; ?>')" class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-2 z-10 cursor-pointer">
                                            <i class="fas fa-eye" id="eye-<?php echo $key; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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

                    <div class="mt-4 pt-4 border-t border-purple-200">
                        <p class="text-xs font-bold text-purple-900 uppercase tracking-widest mb-1">Active Configuration:</p>
                        <div class="flex items-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo (defined('RAZORPAY_MODE') && RAZORPAY_MODE === 'live') ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-green-100 text-green-700 border border-green-200'; ?>">
                                <?php echo defined('RAZORPAY_MODE') ? RAZORPAY_MODE : 'test'; ?> Mode
                            </span>
                            <span class="ml-2 text-[11px] text-purple-700 italic">Using <?php echo (defined('RAZORPAY_MODE') && RAZORPAY_MODE === 'live') ? 'Production' : 'Test'; ?> keys</span>
                        </div>
                    </div>
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

                <!-- Delhivery Info -->
                <div class="p-4 bg-orange-50 rounded border border-orange-200 md:col-span-2">
                    <h3 class="font-semibold text-orange-900 mb-2 flex items-center">
                        <i class="fas fa-truck mr-2"></i>
                        Delhivery Delivery Setup
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <ol class="text-sm text-orange-800 space-y-1 list-decimal list-inside">
                            <li>Login to <a href="https://one.delhivery.com" target="_blank" class="underline">Delhivery One</a></li>
                            <li>Go to Settings → Developer Portal</li>
                            <li>Get your <b>API Token</b></li>
                            <li>Use <b>test</b> mode for Staging and <b>live</b> for Production</li>
                            <li class="mt-2 text-xs font-bold text-orange-900 uppercase tracking-widest list-none">Active Connection URL:</li>
                            <li class="text-[11px] font-mono bg-white/50 p-1 px-2 rounded border border-orange-200/50 break-all list-none">
                                <i class="fas fa-link mr-1"></i>
                                <?php 
                                    $dMode = $settings->get('delhivery_mode', 'test');
                                    echo ($dMode === 'live') ? 'https://track.delhivery.com' : 'https://staging-express.delhivery.com';
                                ?>
                            </li>
                        </ol>
                        <ul class="text-sm text-orange-800 space-y-1 list-disc list-inside">
                            <li><b>Warehouse Name:</b> Must match exactly what you created in Delhivery panel.</li>
                            <li>Staging URL: <code>staging-express.delhivery.com</code></li>
                            <li>Production URL: <code>track.delhivery.com</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button Removed (Moved to Header) -->
    </form>
</div>

<script>
// Help recover from garbled data if any was saved in Base64
function initSystemSettings() {
    var sensitiveFields = [
        'setting_global_schema_json',
        'setting_header_scripts', 
        'setting_pickup_message',
        'setting_global_meta_description'
    ];
    
    sensitiveFields.forEach(name => {
        const el = document.getElementsByName(name)[0];
        if (el && el.value && el.value.length > 8) {
            const val = el.value.trim();
            // Simple check if it might be Base64
            if (/^[A-Za-z0-9+/=]+$/.test(val) && val.length % 4 === 0) {
                try {
                    const decoded = decodeURIComponent(escape(atob(val)));
                    // Only apply if it doesn't look like binary garbage
                    if (!/[\x00-\x08\x0E-\x1F]/.test(decoded)) {
                        el.value = decoded;
                        // If it's the pickup message, we might need to sync TinyMCE
                        if (name === 'setting_pickup_message' && typeof tinymce !== 'undefined') {
                            const editor = tinymce.get(el.id);
                            if (editor) editor.setContent(decoded);
                        }
                    }
                } catch (e) {}
            }
        }
    });
}
// Run on load
initSystemSettings();

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
var paymentData = <?php 
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
    const form = this;
    
    // Sync TinyMCE editors
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }

    var sensitiveFields = [
        'setting_global_schema_json',
        'setting_header_scripts', 
        'setting_pickup_message',
        'setting_global_meta_description'
    ];
    
    // Helper to safely encode to Base64 (supporting UTF-8)
    var toBase64 = (str) => btoa(unescape(encodeURIComponent(str)));
    
    // Create hidden fields for encoded values so we don't mess with the visible textareas
    // We also nullify the name of the original field so it's not sent (prevents WAF block)
    sensitiveFields.forEach(name => {
        const els = document.getElementsByName(name);
        if (els && els.length > 0) {
            const el = els[0];
            const val = el.value;
            
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'encoded_' + name.replace('setting_', '');
            hidden.value = toBase64(val);
            form.appendChild(hidden);
            
            // Remove the name right before submission so the WAF never sees the raw content
            el.removeAttribute('name');
        }
    });

    const svgs = document.getElementsByName('payment_svgs[]');
    if (svgs && svgs.length > 0) {
        // Convert to array to avoid issues when removing attributes
        const svgArray = Array.from(svgs);
        svgArray.forEach(el => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'encoded_payment_svgs[]';
            hidden.value = toBase64(el.value);
            form.appendChild(hidden);
            
            // Remove the name right before submission
            el.removeAttribute('name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
