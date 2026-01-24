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
if (isset($_POST['delete_id'])) {
    try {
        $db->execute("DELETE FROM section_features WHERE id = ?", [$_POST['delete_id']]);
        $_SESSION['flash_success'] = "Feature removed successfully.";
        header("Location: " . $baseUrl . '/admin/features');
        exit;
    } catch (Exception $e) {
        $error = "Error removing feature: " . $e->getMessage();
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    try {
        $id = $_POST['feature_id'] ?? '';
        $icon = $_POST['icon'] ?? ''; // Allow raw content
        $heading = trim($_POST['heading'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($id) {
            // Update
            $sql = "UPDATE section_features SET icon = ?, heading = ?, content = ?, sort_order = ? WHERE id = ?";
            $db->execute($sql, [$icon, $heading, $content, $sort_order, $id]);
            $_SESSION['flash_success'] = "Feature updated successfully.";
        } else {
            // Insert - Check Limit first
            $count = $db->fetchOne("SELECT COUNT(*) as c FROM section_features")['c'];
            if ($count >= 3) {
                 throw new Exception("Maximum 3 features allowed.");
            }
            
            $sql = "INSERT INTO section_features (icon, heading, content, sort_order) VALUES (?, ?, ?, ?)";
            $db->execute($sql, [$icon, $heading, $content, $sort_order]);
            $_SESSION['flash_success'] = "Feature added successfully.";
        }
        
        header("Location: " . $baseUrl . '/admin/features');
        exit;

    } catch (Exception $e) {
        $error = "Error saving: " . $e->getMessage();
    }
}

// Fetch Data
$features = $db->fetchAll("SELECT * FROM section_features ORDER BY sort_order ASC");
$count = count($features);

$pageTitle = 'Features Section Manager';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Features Section</h1>
            <p class="text-gray-600">Manage the 3-column feature cards (Icon, Heading, Text).</p>
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

    <!-- List -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php foreach ($features as $f): ?>
            <div class="bg-white p-4 rounded shadow border border-gray-200 relative group">
                <div class="flex justify-end mb-2">
                    <button onclick="editFeature(<?php echo htmlspecialchars(json_encode($f)); ?>)" class="text-blue-600 hover:text-blue-800 mr-2" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST">
                        <input type="hidden" name="delete_id" value="<?php echo $f['id']; ?>">
                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                
                <div class="text-center mb-4 text-gray-700 text-4xl h-12 flex items-center justify-center">
                    <!-- Echoing Raw SVG -->
                    <?php echo $f['icon']; ?>
                </div>
                <h3 class="font-bold text-lg text-center mb-2"><?php echo htmlspecialchars($f['title'] ?? ''); ?></h3>
                <p class="text-gray-600 text-center text-sm"><?php echo nl2br(htmlspecialchars($f['content'] ?? '')); ?></p>
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
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
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
            
            <div class="text-right pt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('featureModal');
const title = modal.querySelector('.modal-title');
const inpId = document.getElementById('inpId');
const inpIcon = document.getElementById('inpIcon');
const inpHeading = document.getElementById('inpHeading');
const inpContent = document.getElementById('inpContent');

function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Reset form
    title.textContent = 'Add Feature';
    inpId.value = '';
    inpIcon.value = '';
    inpHeading.value = '';
    inpContent.value = '';
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
    document.getElementById('inpSort').value = data.sort_order;
}

// Close on outside click
modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
