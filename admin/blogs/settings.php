<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Settings.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$baseUrl = getBaseUrl();

// Store ID Logic
$storeId = getCurrentStoreId();

// Handle Form Submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        
        // Blog Appearance Settings
        $settings->set('blog_bg_color', $_POST['blog_bg_color'] ?? '#f9fafb', 'blog');
        $settings->set('blog_text_color', $_POST['blog_text_color'] ?? '#1f2937', 'blog');
        $settings->set('blog_heading_color', $_POST['blog_heading_color'] ?? '#111827', 'blog');
        $settings->set('blog_accent_color', $_POST['blog_accent_color'] ?? '#2563eb', 'blog');
        
        // Blog Status
        $settings->set('enable_blog', isset($_POST['enable_blog']) ? '1' : '0', 'general');

        $_SESSION['flash_success'] = "Blog settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/blogs/settings');
        exit;
    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}

// Check flash message
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$pageTitle = 'Blog Settings';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<div class="mb-6 flex justify-between items-center sticky top-0 bg-[#f7f8fc] pb-5 z-20">
    <div>
        <h1 class="text-3xl font-bold">Blog Settings</h1>
        <p class="text-gray-600">Settings > Blog Configuration</p>
    </div>
    <div class="flex gap-4">
        <a href="<?php echo $baseUrl; ?>/admin/blogs/manage" class="bg-gray-600 text-white font-bold py-3 px-6 rounded shadow hover:bg-gray-700 transition">
            <i class="fas fa-list mr-2"></i> Manage Posts
        </a>
        <button type="submit" form="blogSettingsForm" class="bg-blue-600 text-white font-bold py-3 px-8 rounded shadow-lg transition transform hover:-translate-y-0.5">
            Save Settings
        </button>
        <a href="<?php echo $baseUrl; ?>/blog.php" target="_blank" class="bg-green-600 text-white px-4 py-3 rounded hover:bg-green-700 font-bold shadow">
            <i class="fas fa-external-link-alt mr-2"></i> View Live
        </a>
    </div>
</div>

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

<form id="blogSettingsForm" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Status & Preview -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Status Card -->
        <div class="bg-white p-6 rounded shadow border-t-4 border-blue-500">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Status</h3>
            <label class="flex items-center space-x-3 cursor-pointer p-3 border rounded hover:bg-gray-50 transition">
                <input type="checkbox" name="enable_blog" value="1" 
                       <?php echo $settings->get('enable_blog', '1') == '1' ? 'checked' : ''; ?> 
                       class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                <div>
                    <span class="font-bold text-gray-700 block">Enable Blog Feature</span>
                    <span class="text-xs text-gray-500">Show blog on frontend</span>
                </div>
            </label>
        </div>

        <!-- Quick Tips or Stats -->
        <div class="bg-blue-50 p-6 rounded shadow border border-blue-100">
             <h3 class="font-bold text-lg mb-2 text-blue-800"><i class="fas fa-info-circle mr-2"></i> Tips</h3>
             <p class="text-sm text-blue-700 leading-relaxed">
                 Use the settings on the right to customize your main blog page appearance. 
                 Individual blog posts will inherit these styles where applicable unless overridden.
             </p>
        </div>
    </div>

    <!-- Right Column: Settings -->
    <div class="lg:col-span-2 space-y-6">
        


        <!-- Appearance Settings -->
        <div class="bg-white p-6 rounded shadow">
            <div class="flex items-center mb-4 pb-2 border-b">
                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 mr-3">
                    <i class="fas fa-palette"></i>
                </div>
                <h3 class="font-bold text-xl text-gray-800">Theme & Colors</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Background Color</label>
                    <div class="flex items-center">
                        <input type="color" name="blog_bg_color" 
                               value="<?php echo htmlspecialchars($settings->get('blog_bg_color', '#f9fafb')); ?>" 
                               class="h-10 w-16 p-1 border border-gray-300 rounded mr-3 cursor-pointer">
                        <input type="text" value="<?php echo htmlspecialchars($settings->get('blog_bg_color', '#f9fafb')); ?>" 
                               class="flex-1 border border-gray-300 p-2 rounded text-sm text-gray-600 bg-gray-50" readonly>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Main page background</p>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Heading Color</label>
                    <div class="flex items-center">
                        <input type="color" name="blog_heading_color" 
                               value="<?php echo htmlspecialchars($settings->get('blog_heading_color', '#111827')); ?>" 
                               class="h-10 w-16 p-1 border border-gray-300 rounded mr-3 cursor-pointer">
                        <input type="text" value="<?php echo htmlspecialchars($settings->get('blog_heading_color', '#111827')); ?>" 
                               class="flex-1 border border-gray-300 p-2 rounded text-sm text-gray-600 bg-gray-50" readonly>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Main title text color</p>
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2 text-gray-700">Text Color</label>
                    <div class="flex items-center">
                        <input type="color" name="blog_text_color" 
                               value="<?php echo htmlspecialchars($settings->get('blog_text_color', '#1f2937')); ?>" 
                               class="h-10 w-16 p-1 border border-gray-300 rounded mr-3 cursor-pointer">
                        <input type="text" value="<?php echo htmlspecialchars($settings->get('blog_text_color', '#1f2937')); ?>" 
                               class="flex-1 border border-gray-300 p-2 rounded text-sm text-gray-600 bg-gray-50" readonly>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Description text color</p>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700">Accent Color</label>
                    <div class="flex items-center">
                        <input type="color" name="blog_accent_color" 
                               value="<?php echo htmlspecialchars($settings->get('blog_accent_color', '#2563eb')); ?>" 
                               class="h-10 w-16 p-1 border border-gray-300 rounded mr-3 cursor-pointer">
                         <input type="text" value="<?php echo htmlspecialchars($settings->get('blog_accent_color', '#2563eb')); ?>" 
                               class="flex-1 border border-gray-300 p-2 rounded text-sm text-gray-600 bg-gray-50" readonly>
                    </div>
                     <p class="text-xs text-gray-500 mt-1">Links and buttons</p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
