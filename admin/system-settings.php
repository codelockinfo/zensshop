<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$settings = new Settings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && strpos($key, 'setting_') === 0) {
            $settingKey = str_replace('setting_', '', $key);
            $settings->set($settingKey, $value, $_POST['group_' . $settingKey] ?? 'general');
        }
    }
    $_SESSION['success'] = "Settings updated successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get all settings grouped
$emailSettings = $settings->getByGroup('email');
$generalSettings = $settings->getByGroup('general');
$apiSettings = $settings->getByGroup('api');

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

    <form method="POST" class="space-y-6">
        <input type="hidden" name="action" value="update_settings">

        <!-- Email Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-envelope mr-2 text-blue-600"></i>
                Email / SMTP Settings
            </h2>
            <p class="text-sm text-gray-600 mb-6">Configure email sending for order confirmations, support messages, and newsletters</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($emailSettings as $setting): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $setting['setting_key']; ?>" value="email">
                        <?php if ($setting['setting_key'] === 'smtp_password'): ?>
                            <div class="relative">
                                <input type="password" 
                                       id="<?php echo $setting['setting_key']; ?>"
                                       name="setting_<?php echo $setting['setting_key']; ?>" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="w-full px-4 py-2 pr-10 border rounded focus:ring-2 focus:ring-blue-500"
                                       placeholder="Enter SMTP password">
                                <button type="button" 
                                        onclick="togglePassword('<?php echo $setting['setting_key']; ?>')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="eye-<?php echo $setting['setting_key']; ?>"></i>
                                </button>
                            </div>
                        <?php elseif ($setting['setting_key'] === 'smtp_port'): ?>
                            <input type="number" 
                                   name="setting_<?php echo $setting['setting_key']; ?>" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="587">
                        <?php else: ?>
                            <input type="text" 
                                   name="setting_<?php echo $setting['setting_key']; ?>" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter value">
                        <?php endif; ?>
                        <?php if ($setting['setting_key'] === 'smtp_password'): ?>
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
                <?php foreach ($generalSettings as $setting): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $setting['setting_key']; ?>" value="general">
                        <?php if ($setting['setting_key'] === 'otp_expiry_minutes'): ?>
                            <input type="number" 
                                   name="setting_<?php echo $setting['setting_key']; ?>" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="10">
                            <p class="text-xs text-gray-500 mt-1">OTP validity in minutes</p>
                        <?php else: ?>
                            <input type="text" 
                                   name="setting_<?php echo $setting['setting_key']; ?>" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter value">
                        <?php endif; ?>
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
                <?php foreach ($apiSettings as $setting): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                        </label>
                        <input type="hidden" name="group_<?php echo $setting['setting_key']; ?>" value="api">
                        <?php if (strpos($setting['setting_key'], 'secret') !== false || strpos($setting['setting_key'], 'key') !== false): ?>
                            <div class="relative">
                                <input type="password" 
                                       id="<?php echo $setting['setting_key']; ?>"
                                       name="setting_<?php echo $setting['setting_key']; ?>" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="w-full px-4 py-2 pr-10 border rounded focus:ring-2 focus:ring-purple-500 font-mono text-sm"
                                       placeholder="Enter API key">
                                <button type="button" 
                                        onclick="togglePassword('<?php echo $setting['setting_key']; ?>')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="eye-<?php echo $setting['setting_key']; ?>"></i>
                                </button>
                            </div>
                        <?php elseif ($setting['setting_key'] === 'razorpay_mode'): ?>
                            <select name="setting_<?php echo $setting['setting_key']; ?>" 
                                    class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500">
                                <option value="test" <?php echo $setting['setting_value'] === 'test' ? 'selected' : ''; ?>>Test Mode</option>
                                <option value="live" <?php echo $setting['setting_value'] === 'live' ? 'selected' : ''; ?>>Live Mode</option>
                            </select>
                        <?php else: ?>
                            <input type="text" 
                                   name="setting_<?php echo $setting['setting_key']; ?>" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500 font-mono text-sm"
                                   placeholder="Enter value">
                        <?php endif; ?>
                        
                        <?php if ($setting['setting_key'] === 'razorpay_key_id'): ?>
                            <p class="text-xs text-gray-500 mt-1">Get from Razorpay Dashboard → Settings → API Keys</p>
                        <?php elseif ($setting['setting_key'] === 'google_client_id'): ?>
                            <p class="text-xs text-gray-500 mt-1">Get from Google Cloud Console → APIs & Services → Credentials</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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
