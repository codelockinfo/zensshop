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

// Messages
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle Delete
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

if (isset($_POST['delete_id'])) {
    try {
        $db->execute("DELETE FROM section_features WHERE id = ? AND store_id = ?", [$_POST['delete_id'], $storeId]);
        $_SESSION['flash_success'] = "Feature removed successfully.";
        header("Location: " . $baseUrl . '/admin/features');
        exit;
    } catch (Exception $e) {
        $error = "Error removing feature: " . $e->getMessage();
    }
}

// Handle Section Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_section') {
    try {
        $section_bg = trim($_POST['section_bg'] ?? '#ffffff');
        $section_text = trim($_POST['section_text'] ?? '#000000');
        $section_visibility = isset($_POST['section_visibility']) ? '1' : '0';
        
        // Save to settings table
        require_once __DIR__ . '/../classes/Settings.php';
        $settings = new Settings();
        $settings->set('features_section_bg', $section_bg);
        $settings->set('features_section_text', $section_text);
        $settings->set('features_section_visibility', $section_visibility);
        
        $_SESSION['flash_success'] = "Section settings saved successfully.";
        header("Location: " . $baseUrl . '/admin/features');
        exit;
    } catch (Exception $e) {
        $error = "Error saving section settings: " . $e->getMessage();
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    try {
        $id = $_POST['feature_id'] ?? '';
        $icon = $_POST['icon'] ?? ''; // Allow raw content
        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $bg_color = trim($_POST['bg_color'] ?? '#ffffff');
        $text_color = trim($_POST['text_color'] ?? '#000000');
        $heading_color = trim($_POST['heading_color'] ?? '#000000');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        // Auto-Migration: Ensure columns exist
        try {
            $db->execute("ALTER TABLE section_features ADD COLUMN bg_color VARCHAR(7) DEFAULT '#ffffff'");
        } catch (Exception $e) {}
        try {
            $db->execute("ALTER TABLE section_features ADD COLUMN text_color VARCHAR(7) DEFAULT '#000000'");
        } catch (Exception $e) {}

        if ($id) {
            // Update
            $sql = "UPDATE section_features SET icon = ?, heading = ?, content = ?, bg_color = ?, text_color = ?, heading_color = ?, sort_order = ? WHERE id = ? AND store_id = ?";
            $db->execute($sql, [$icon, $heading, $content, $bg_color, $text_color, $heading_color, $sort_order, $id, $storeId]);
            $_SESSION['flash_success'] = "Feature updated successfully.";
        } else {
            // Insert - Check Limit first
            $countResult = $db->fetchOne("SELECT COUNT(*) as c FROM section_features WHERE store_id = ?", [$storeId]);
            $count = $countResult['c'];
            if ($count >= 3) {
                 throw new Exception("Maximum 3 features allowed.");
            }
            
            $sql = "INSERT INTO section_features (icon, heading, content, bg_color, text_color, heading_color, sort_order, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [$icon, $heading, $content, $bg_color, $text_color, $heading_color, $sort_order, $storeId]);
            $_SESSION['flash_success'] = "Feature added successfully.";
        }
        
        header("Location: " . $baseUrl . '/admin/features');
        exit;

    } catch (Exception $e) {
        $error = "Error saving: " . $e->getMessage();
    }
}

// Fetch Data
// Auto-Migration check on fetch as well to avoid errors if logic runs before post
try {
    $features = $db->fetchAll("SELECT * FROM section_features WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);
} catch (Exception $e) {
    // If fetch fails, try to add columns and fetch again
    try {
        $db->execute("ALTER TABLE section_features ADD COLUMN bg_color VARCHAR(7) DEFAULT '#ffffff'");
    } catch (Exception $e2) {}
    try {
        $db->execute("ALTER TABLE section_features ADD COLUMN text_color VARCHAR(7) DEFAULT '#000000'");
    } catch (Exception $e2) {}
    $features = $db->fetchAll("SELECT * FROM section_features WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);
}

$count = count($features);

// Fetch Section Settings
require_once __DIR__ . '/../classes/Settings.php';
$settingsObj = new Settings();
$section_bg = $settingsObj->get('features_section_bg', '#ffffff');
$section_text = $settingsObj->get('features_section_text', '#000000');
$section_visibility = $settingsObj->get('features_section_visibility', '1');

$pageTitle = 'Features Section Manager';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Features Section</h1>
            <p class="text-gray-600">Manage the 3-column feature cards (Icon, Heading, Text, Colors).</p>
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

    <!-- Section Settings -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-200">
        <h2 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-palette mr-2"></i>Section Appearance
        </h2>
        <form method="POST">
             <!-- Visibility Toggle -->
            <div class="flex items-center justify-between mt-4 mb-10 border-t pt-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><i class="fas fa-eye"></i></div>
                    <div>
                        <h3 class="font-bold text-gray-700">Show Features Section</h3>
                        <p class="text-xs text-gray-500">Toggle visibility of this section on the homepage.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="section_visibility" class="sr-only peer" <?php echo ($section_visibility == '1') ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <input type="hidden" name="action" value="save_section">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold mb-2">Section Background Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="section_bg" id="sectionBgPicker" class="h-10 border p-1 rounded w-16" value="<?php echo htmlspecialchars($section_bg); ?>">
                        <input type="text" id="sectionBgText" class="flex-1 h-10 border px-3 rounded font-mono text-sm" value="<?php echo htmlspecialchars($section_bg); ?>" placeholder="#FFFFFF" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Section Text Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="section_text" id="sectionTextPicker" class="h-10 border p-1 rounded w-16" value="<?php echo htmlspecialchars($section_text); ?>">
                        <input type="text" id="sectionTextText" class="flex-1 h-10 border px-3 rounded font-mono text-sm" value="<?php echo htmlspecialchars($section_text); ?>" placeholder="#000000" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    These colors apply to the entire section background. Individual feature cards have their own color settings below.
                </p>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-bold">
                    <i class="fas fa-save mr-1"></i> Save Colors
                </button>
            </div>
        </form>
    </div>

    <!-- Feature Cards List -->
    <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
        <h2 class="text-lg font-bold text-gray-800 mb-1">
            <i class="fas fa-th-large mr-2"></i>Feature Cards
        </h2>
        <p class="text-sm text-gray-600">Add and manage individual feature cards with custom icons and colors.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php foreach ($features as $f): ?>
            <div class="p-4 rounded shadow border border-gray-200 relative group" style="background-color: <?php echo htmlspecialchars($f['bg_color'] ?? '#ffffff'); ?>; color: <?php echo htmlspecialchars($f['text_color'] ?? '#000000'); ?>;">
                <div class="flex justify-end mb-2 absolute top-2 right-2">
                    <button onclick='editFeature(<?php echo json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-blue-600 hover:text-blue-800 mr-2 bg-white rounded-full p-1" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="delete_id" value="<?php echo $f['id']; ?>">
                        <button type="submit" class="text-red-600 hover:text-red-800 bg-white rounded-full p-1" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                
                <div class="text-center mb-4 text-4xl h-12 flex items-center justify-center mt-6">
                    <!-- Echoing Raw SVG -->
                    <?php echo $f['icon']; ?>
                </div>
                <h3 class="font-bold text-lg text-center mb-2"><?php echo htmlspecialchars($f['heading'] ?? ''); ?></h3>
                <p class="text-center text-sm opacity-90"><?php echo nl2br(htmlspecialchars($f['content'] ?? '')); ?></p>
            </div>
        <?php endforeach; ?>

        <?php if ($count < 3): ?>
            <button onclick="openModal()" class="flex flex-col items-center justify-center bg-gray-50 border-2 border-dashed border-gray-300 p-6 rounded hover:bg-gray-100 transition min-h-[200px] text-gray-500">
                <i class="fas fa-plus-circle text-3xl mb-2"></i>
                <span class="font-bold">Add Feature Card</span>
                <span class="text-xs mt-1">(<?php echo $count; ?>/3 used)</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="featureModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800 modal-title">Add Feature</h3>
            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="p-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="feature_id" id="inpId">
            <input type="hidden" name="sort_order" id="inpSort" value="<?php echo $count; ?>">
            
            <div class="mb-4">
                 <label class="block text-sm font-bold mb-2">SVG Icon Code</label>
                 <textarea name="icon" id="inpIcon" rows="3" class="w-full border p-2 rounded text-xs font-mono" placeholder='<svg...>...</svg>' required></textarea>
                 <p class="text-xs text-gray-500 mt-1">Paste your SVG code here. Ensure it has width/height classes (e.g. w-12 h-12).</p>
            </div>
            
            <div class="mb-4">
                 <label class="block text-sm font-bold mb-2">Heading</label>
                 <input type="text" name="heading" id="inpHeading" class="w-full border p-2 rounded" required>
            </div>
            
            <div class="mb-4">
                 <label class="block text-sm font-bold mb-2">Content</label>
                 <textarea name="content" id="inpContent" rows="3" class="w-full border p-2 rounded" required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold mb-2">Background Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="bg_color" id="inpBgColor" class="h-10 border p-1 rounded w-16" value="#ffffff">
                        <input type="text" id="inpBgColorText" class="flex-1 h-10 border px-3 rounded font-mono text-sm" value="#ffffff" placeholder="#FFFFFF" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">Heading Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="heading_color" id="inpHeadingColor" class="h-10 border p-1 rounded w-16" value="#000000">
                        <input type="text" id="inpHeadingColorText" class="flex-1 h-10 border px-3 rounded font-mono text-sm" value="#000000" placeholder="#000000" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">Text Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="text_color" id="inpTextColor" class="h-10 border p-1 rounded w-16" value="#000000">
                        <input type="text" id="inpTextColorText" class="flex-1 h-10 border px-3 rounded font-mono text-sm" value="#000000" placeholder="#000000" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                </div>
            </div>
            
            <div class="text-right pt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-bold btn-loading">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
// Section Color Sync
const sectionBgPicker = document.getElementById('sectionBgPicker');
const sectionBgText = document.getElementById('sectionBgText');
const sectionTextPicker = document.getElementById('sectionTextPicker');
const sectionTextText = document.getElementById('sectionTextText');

// Sync section background color
sectionBgPicker.addEventListener('input', (e) => {
    sectionBgText.value = e.target.value.toUpperCase();
});
sectionBgText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        sectionBgPicker.value = e.target.value;
    }
});

// Sync section text color
sectionTextPicker.addEventListener('input', (e) => {
    sectionTextText.value = e.target.value.toUpperCase();
});
sectionTextText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        sectionTextPicker.value = e.target.value;
    }
});

// Modal elements
const modal = document.getElementById('featureModal');
const title = modal.querySelector('.modal-title');
const inpId = document.getElementById('inpId');
const inpIcon = document.getElementById('inpIcon');
const inpHeading = document.getElementById('inpHeading');
const inpContent = document.getElementById('inpContent');
const inpBgColor = document.getElementById('inpBgColor');
const inpBgColorText = document.getElementById('inpBgColorText');
const inpTextColor = document.getElementById('inpTextColor');
const inpTextColorText = document.getElementById('inpTextColorText');

// Sync modal background color
inpBgColor.addEventListener('input', (e) => {
    inpBgColorText.value = e.target.value.toUpperCase();
});
inpBgColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpBgColor.value = e.target.value;
    }
});

// Sync modal text color
inpTextColor.addEventListener('input', (e) => {
    inpTextColorText.value = e.target.value.toUpperCase();
});
inpTextColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpTextColor.value = e.target.value;
    }
});

// Sync modal heading color
const inpHeadingColor = document.getElementById('inpHeadingColor');
const inpHeadingColorText = document.getElementById('inpHeadingColorText');

inpHeadingColor.addEventListener('input', (e) => {
    inpHeadingColorText.value = e.target.value.toUpperCase();
});
inpHeadingColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpHeadingColor.value = e.target.value;
    }
});

function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Reset form
    title.textContent = 'Add Feature';
    inpId.value = '';
    inpIcon.value = '';
    inpHeading.value = '';
    inpContent.value = '';
    inpBgColor.value = '#ffffff';
    inpBgColorText.value = '#FFFFFF';
    document.getElementById('inpHeadingColor').value = '#000000';
    document.getElementById('inpHeadingColorText').value = '#000000';
    inpTextColor.value = '#000000';
    inpTextColorText.value = '#000000';
}

function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function editFeature(data) {
    openModal();
    title.textContent = 'Edit Feature';
    inpId.value = data.id;
    inpIcon.value = data.icon;
    inpHeading.value = data.heading;
    inpContent.value = data.content;
    inpBgColor.value = data.bg_color || '#ffffff';
    inpBgColorText.value = (data.bg_color || '#ffffff').toUpperCase();
    document.getElementById('inpHeadingColor').value = data.heading_color || '#000000';
    document.getElementById('inpHeadingColorText').value = (data.heading_color || '#000000').toUpperCase();
    inpTextColor.value = data.text_color || '#000000';
    inpTextColorText.value = (data.text_color || '#000000').toUpperCase();
    document.getElementById('inpSort').value = data.sort_order;
}

// Close on outside click
modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
