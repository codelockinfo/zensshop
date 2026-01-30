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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    
    // Handle File Uploads
    $uploadFiles = ['setting_favicon_png', 'setting_favicon_ico'];
    foreach ($uploadFiles as $fileKey) {
        if (!empty($_FILES[$fileKey]['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/';
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $prefix = ($fileKey === 'setting_favicon_ico') ? 'favicon' : 'favicon_browser';
            $fileName = $prefix . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fileName)) {
                $_POST[$fileKey] = $fileName;
                $_POST['group_' . str_replace('setting_', '', $fileKey)] = 'seo';
            }
        }
    }

    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && strpos($key, 'setting_') === 0) {
            $settingKey = str_replace('setting_', '', $key);
            $settings->set($settingKey, $value, $_POST['group_' . $settingKey] ?? 'general');
        }
    }
    $_SESSION['success'] = "Settings updated successfully!";
    header("Location: " . $baseUrl . '/admin/settings');
    exit;
}

// Get all settings grouped
$emailSettings = $settings->getByGroup('email');
$generalSettings = $settings->getByGroup('general');
$apiSettings = $settings->getByGroup('api');
$seoSettings = $settings->getByGroup('seo');

$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/admin-header.php';

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">System Settings</h1>
        <p class="text-gray-600 text-sm mt-1">Manage your application configuration</p>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="action" value="update_settings">

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
                     <input type="text" name="setting_site_title_suffix" value="<?php echo htmlspecialchars($settings->get('site_title_suffix', 'Milano - Elegant Jewelry Store')); ?>" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="e.g. My Awesome Shop">
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
                                 <img src="<?php echo getBaseUrl() . '/assets/images/' . $favPng; ?>" class="w-16 h-16 object-contain">
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
                                 <img src="<?php echo getBaseUrl() . '/assets/images/' . $favIco; ?>" class="w-16 h-16 object-contain">
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
                    'smtp_from_name' => ['label' => 'From Name', 'placeholder' => 'Milano Store', 'type' => 'text'],
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
                    'site_name' => ['label' => 'Site Name', 'placeholder' => 'Milano'],
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

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-semibold hover:bg-blue-700 transition shadow-lg">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
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
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
