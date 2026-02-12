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
        $db->execute("DELETE FROM footer_features WHERE id = ? AND store_id = ?", [$_POST['delete_id'], $storeId]);
        $_SESSION['flash_success'] = "Feature removed successfully.";
        header("Location: " . $baseUrl . '/admin/footer_features.php');
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
        
        // Save to settings table
        require_once __DIR__ . '/../classes/Settings.php';
        $settings = new Settings();
        $settings->set('footer_features_section_bg', $section_bg);
        $settings->set('footer_features_section_text', $section_text);
        
        $_SESSION['flash_success'] = "Section settings saved successfully.";
        header("Location: " . $baseUrl . '/admin/footer_features.php');
        exit;
    } catch (Exception $e) {
        $error = "Error saving section settings: " . $e->getMessage();
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    try {
        $id = $_POST['feature_id'] ?? '';
        $icon = $_POST['icon'] ?? ''; 
        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $bg_color = trim($_POST['bg_color'] ?? '#ffffff');
        $text_color = trim($_POST['text_color'] ?? '#000000');
        $heading_color = trim($_POST['heading_color'] ?? '#000000');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($id) {
            // Update
            $sql = "UPDATE footer_features SET icon = ?, heading = ?, content = ?, bg_color = ?, text_color = ?, heading_color = ?, sort_order = ? WHERE id = ? AND store_id = ?";
            $db->execute($sql, [$icon, $heading, $content, $bg_color, $text_color, $heading_color, $sort_order, $id, $storeId]);
            $_SESSION['flash_success'] = "Feature updated successfully.";
        } else {
            // Insert
            $countResult = $db->fetchOne("SELECT COUNT(*) as c FROM footer_features WHERE store_id = ?", [$storeId]);
            $count = $countResult['c'];
            if ($count >= 4) {
                $_SESSION['flash_error'] = "Maximum 4 features allowed.";
                header("Location: " . $baseUrl . '/admin/footer_features.php');
                exit;
            }
            
            // Generate 10-digit random ID
            $featureId = mt_rand(1000000000, 9999999999);
            
            $sql = "INSERT INTO footer_features (feature_id, icon, heading, content, bg_color, text_color, heading_color, sort_order, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [$featureId, $icon, $heading, $content, $bg_color, $text_color, $heading_color, $sort_order, $storeId]);
            $_SESSION['flash_success'] = "Feature added successfully.";
        }
        
        header("Location: " . $baseUrl . '/admin/footer_features.php');
        exit;

    } catch (Exception $e) {
        $error = "Error saving: " . $e->getMessage();
    }
}

// Fetch Data
$features = $db->fetchAll("SELECT * FROM footer_features WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);
$count = count($features);

// Fetch Section Settings
require_once __DIR__ . '/../classes/Settings.php';
$settingsObj = new Settings();
$section_bg = $settingsObj->get('footer_features_section_bg', '#ffffff');
$section_text = $settingsObj->get('footer_features_section_text', '#000000');

$pageTitle = 'Footer Features Manager';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Footer Features Section</h1>
            <p class="text-gray-600">Manage the features displayed before the footer (Icon, Heading, Text, Colors).</p>
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
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <?php foreach ($features as $f): ?>
            <div id="feature-card-<?php echo $f['id']; ?>" class="p-4 rounded shadow border border-gray-200 relative group flex flex-col justify-between" style="background-color: <?php echo htmlspecialchars($f['bg_color']); ?>; color: <?php echo htmlspecialchars($f['text_color']); ?>;">
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
                
                <div class="feature-icon-preview text-center mb-4 text-4xl h-12 flex items-center justify-center mt-6">
                    <!-- Echoing Raw SVG -->
                    <?php echo $f['icon']; ?>
                </div>
                <h3 class="feature-heading-preview font-bold text-lg text-center mb-2" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? $f['text_color']); ?>;"><?php echo htmlspecialchars($f['heading'] ?? ''); ?></h3>
                <p class="feature-content-preview text-center text-sm opacity-90"><?php echo nl2br(htmlspecialchars($f['content'] ?? '')); ?></p>
            </div>
        <?php endforeach; ?>

        <?php if ($count < 4): ?>
        <button onclick="openModal()" class="flex flex-col items-center justify-center bg-gray-50 border-2 border-dashed border-gray-300 p-6 rounded hover:bg-gray-100 transition min-h-[200px] text-gray-500">
            <i class="fas fa-plus-circle text-3xl mb-2"></i>
            <span class="font-bold">Add Footer Feature</span>
            <span class="text-xs mt-1">(<?php echo $count; ?>/4 items)</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="featureModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800 modal-title">Add Footer Feature</h3>
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
const inpHeadingColor = document.getElementById('inpHeadingColor');
const inpHeadingColorText = document.getElementById('inpHeadingColorText');

let currentEditingId = null;

function updateLivePreview() {
    if (!currentEditingId) return;
    const card = document.getElementById(`feature-card-${currentEditingId}`);
    if (!card) return;

    const iconPreview = card.querySelector('.feature-icon-preview');
    const headingPreview = card.querySelector('.feature-heading-preview');
    const contentPreview = card.querySelector('.feature-content-preview');

    // Update Content
    iconPreview.innerHTML = inpIcon.value;
    headingPreview.textContent = inpHeading.value;
    contentPreview.textContent = inpContent.value;

    // Update Colors
    card.style.backgroundColor = inpBgColor.value;
    card.style.color = inpTextColor.value;
    headingPreview.style.color = inpHeadingColor.value || inpTextColor.value;
}

// Add input listeners for all fields
[inpIcon, inpHeading, inpContent, inpBgColor, inpTextColor, inpHeadingColor].forEach(el => {
    el.addEventListener('input', updateLivePreview);
});

// Sync modal background color
inpBgColor.addEventListener('input', (e) => {
    inpBgColorText.value = e.target.value.toUpperCase();
    updateLivePreview();
});
inpBgColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpBgColor.value = e.target.value;
        updateLivePreview();
    }
});

// Sync modal text color
inpTextColor.addEventListener('input', (e) => {
    inpTextColorText.value = e.target.value.toUpperCase();
    updateLivePreview();
});
inpTextColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpTextColor.value = e.target.value;
        updateLivePreview();
    }
});

// Sync modal heading color
inpHeadingColor.addEventListener('input', (e) => {
    inpHeadingColorText.value = e.target.value.toUpperCase();
    updateLivePreview();
});
inpHeadingColorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        inpHeadingColor.value = e.target.value;
        updateLivePreview();
    }
});


function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Reset form
    title.textContent = 'Add Footer Feature';
    inpId.value = '';
    inpIcon.value = '';
    inpHeading.value = '';
    inpContent.value = '';
    inpBgColor.value = '#ffffff';
    inpBgColorText.value = '#FFFFFF';
    inpHeadingColor.value = '#000000';
    inpHeadingColorText.value = '#000000';
    inpTextColor.value = '#000000';
    inpTextColorText.value = '#000000';
    currentEditingId = null;
}

function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    // We don't reload on cancel because the live preview modified the UI
    // If user cancels, we should actually reload to discard live changes
    location.reload(); 
}

function editFeature(data) {
    currentEditingId = data.id;
    openModal();
    title.textContent = 'Edit Footer Feature';
    inpId.value = data.id;
    inpIcon.value = data.icon;
    inpHeading.value = data.heading;
    inpContent.value = data.content;
    inpBgColor.value = data.bg_color || '#ffffff';
    inpBgColorText.value = (data.bg_color || '#ffffff').toUpperCase();
    inpHeadingColor.value = data.heading_color || '#000000';
    inpHeadingColorText.value = (data.heading_color || '#000000').toUpperCase();
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
