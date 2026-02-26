<?php
ob_start();
session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();

// Fetch Messages
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $link_text = trim($_POST['link_text'] ?? '');
        $link_url = preg_replace('/\.php(\?|$)/', '$1', trim($_POST['link_url'] ?? ''));
        $text_color = trim($_POST['text_color'] ?? '#eee4d3');
        $active = isset($_POST['active']) ? 1 : 0;

        // Determine Store ID
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }

        // Ensure we always have one row
        $check = $db->fetchOne("SELECT id FROM philosophy_section WHERE store_id = ? LIMIT 1", [$storeId]);
        
        $bg_color = trim($_POST['background_color'] ?? '#384135');

        if ($check) {
            $sql = "UPDATE philosophy_section SET heading = ?, content = ?, link_text = ?, link_url = ?, background_color = ?, text_color = ?, active = ? WHERE id = ? AND store_id = ?";
            $db->execute($sql, [$heading, $content, $link_text, $link_url, $bg_color, $text_color, $active, $check['id'], $storeId]);
        } else {
            $sql = "INSERT INTO philosophy_section (heading, content, link_text, link_url, background_color, text_color, active, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [$heading, $content, $link_text, $link_url, $bg_color, $text_color, $active, $storeId]);
        }

        $_SESSION['flash_success'] = "Philosophy section updated successfully!";
        header("Location: " . $baseUrl . '/admin/philosophy');
        exit;

    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch Current Data
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$data = $db->fetchOne("SELECT * FROM philosophy_section WHERE store_id = ? LIMIT 1", [$storeId]);
if (!$data) {
    // defaults just in case
    $data = [
        'heading' => '',
        'content' => '',
        'link_text' => 'OUR PHILOSOPHY',
        'link_url' => '#',
        'background_color' => '#384135',
        'text_color' => '#eee4d3',
        'active' => 1
    ];
}

$pageTitle = 'Philosophy Section Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Philosophy Section Manager</h1>
            <p class="text-gray-600">Edit the content and style of the philosophy section on the homepage.</p>
        </div>
        <div>
             <a href="<?php echo $baseUrl; ?>" target="_blank" class="mr-3 text-gray-600 hover:text-blue-600 font-bold text-sm">
                <i class="fas fa-external-link-alt mr-1"></i> View Site
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

    <form method="POST" class="bg-white p-6 rounded shadow-md w-full max-w-full">
        
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <h3 class="font-bold text-xl text-gray-700">Content Settings</h3>
            
            <div class="flex items-center gap-3">
                <span class="text-sm font-bold text-gray-700">Show Section</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="active" class="sr-only peer" <?php echo ($data['active'] == 1) ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            
            <!-- Colors -->
            <div class="grid grid-cols-2 gap-6 bg-gray-50 p-4 rounded">
                <div>
                    <label class="block text-sm font-bold mb-2">Background Color</label>
                    <div class="flex items-center">
                        <input type="color" name="background_color" value="<?php echo htmlspecialchars($data['background_color'] ?? '#384135'); ?>" class="h-10 w-12 p-1 border rounded mr-2 cursor-pointer" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($data['background_color'] ?? '#384135'); ?>" class="w-full border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                     <label class="block text-sm font-bold mb-2">Text Color</label>
                    <div class="flex items-center">
                        <input type="color" name="text_color" value="<?php echo htmlspecialchars($data['text_color'] ?? '#eee4d3'); ?>" class="h-10 w-12 p-1 border rounded mr-2 cursor-pointer" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($data['text_color'] ?? '#eee4d3'); ?>" class="w-full border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div>
                <label class="block text-sm font-bold mb-2">Heading (Optional)</label>
                <input type="text" name="heading" value="<?php echo htmlspecialchars($data['heading'] ?? ''); ?>" class="w-full border p-2 rounded" placeholder="E.g. Our Philosophy">
                <p class="text-xs text-gray-500 mt-1">Leave empty to hide heading.</p>
            </div>

            <div>
                <label class="block text-sm font-bold mb-2">Main Content (Context)</label>
                <textarea name="content" id="editor" rows="4" class="rich-text-editor w-full border p-2 rounded"><?php echo htmlspecialchars($data['content'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold mb-2">Link Label</label>
                    <input type="text" name="link_text" value="<?php echo htmlspecialchars($data['link_text'] ?? ''); ?>" class="w-full border p-2 rounded" placeholder="READ MORE">
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">Link URL</label>
                    <input type="text" name="link_url" value="<?php echo htmlspecialchars($data['link_url'] ?? ''); ?>" class="w-full border p-2 rounded" placeholder="page">
                </div>
            </div>

        </div>

        <div class="mt-8 pt-4 border-t text-right">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded hover:bg-blue-700 transition font-bold shadow-lg transform hover:-translate-y-0.5 btn-loading">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
        </div>

    </form>
</div>



<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
