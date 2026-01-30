<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

$success = $_SESSION['header_success'] ?? '';
$error = $_SESSION['header_error'] ?? '';

// Clear session messages
unset($_SESSION['header_success']);
unset($_SESSION['header_error']);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Determine Store ID
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }

    // Update Logo
    if ($action === 'update_logo') {
        $logoType = $_POST['logo_type'] ?? 'image';
        $logoText = trim($_POST['logo_text'] ?? '');
        
        // Save logo type
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('site_logo_type', ?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?", [$logoType, $storeId, $logoType]);
        
        // Save logo text if type is text
        if ($logoType === 'text' && !empty($logoText)) {
            $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('site_logo_text', ?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?", [$logoText, $storeId, $logoText]);
            $_SESSION['header_success'] = "Logo text updated successfully!";
        }
        // Handle image upload if type is image
        elseif ($logoType === 'image' && !empty($_FILES['logo']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = 'logo_' . $storeId . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                // Save logo path to database
                $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('site_logo', ?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?", [$filename, $storeId, $filename]);
                
                $_SESSION['header_success'] = "Logo image updated successfully!";
            } else {
                $_SESSION['header_error'] = "Failed to upload logo.";
            }
        } else {
            $_SESSION['header_success'] = "Logo settings updated!";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Update Header Icons Visibility
    if ($action === 'update_icons') {
        $iconSettings = [
            'header_icon_search' => isset($_POST['icon_search']) ? '1' : '0',
            'header_icon_user' => isset($_POST['icon_user']) ? '1' : '0',
            'header_icon_wishlist' => isset($_POST['icon_wishlist']) ? '1' : '0',
            'header_icon_cart' => isset($_POST['icon_cart']) ? '1' : '0',
        ];
        
        foreach ($iconSettings as $key => $value) {
            $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $storeId, $value]);
        }
        
        $_SESSION['header_success'] = "Header icons updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Update Top Bar Settings
    if ($action === 'update_topbar') {
        // Handle announcement slides
        $slides = [];
        if (isset($_POST['slide_texts'])) {
            $slideTexts = $_POST['slide_texts'];
            $slideLinks = $_POST['slide_links'] ?? [];
            
            for ($i = 0; $i < count($slideTexts); $i++) {
                if (!empty(trim($slideTexts[$i]))) {
                    $slides[] = [
                        'text' => trim($slideTexts[$i]),
                        'link' => preg_replace('/\.php(\?|$)/', '$1', trim($slideLinks[$i] ?? '')),
                        'link_text' => trim($_POST['slide_link_texts'][$i] ?? '')
                    ];
                }
            }
        }
        $slidesValue = json_encode($slides);
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('topbar_slides', ?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?", [$slidesValue, $storeId, $slidesValue]);
        
        // Handle top bar links
        $links = [];
        if (isset($_POST['link_labels'])) {
            $linkLabels = $_POST['link_labels'];
            $linkUrls = $_POST['link_urls'] ?? [];
            
            for ($i = 0; $i < count($linkLabels); $i++) {
                if (!empty(trim($linkLabels[$i]))) {
                    $links[] = [
                        'label' => trim($linkLabels[$i]),
                        'url' => preg_replace('/\.php(\?|$)/', '$1', trim($linkUrls[$i] ?? '#'))
                    ];
                }
            }
        }
        $linksValue = json_encode($links);
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('topbar_links', ?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?", [$linksValue, $storeId, $linksValue]);
        
        $_SESSION['header_success'] = "Top bar settings updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Fetch Current Settings
$logoType = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_type' AND store_id = ?", [$storeId])['setting_value'] ?? 'image';
$logoText = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo_text' AND store_id = ?", [$storeId])['setting_value'] ?? 'milano';
$logoPath = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo' AND store_id = ?", [$storeId])['setting_value'] ?? 'logo.png';
$iconSearch = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_search' AND store_id = ?", [$storeId])['setting_value'] ?? '1';
$iconUser = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_user' AND store_id = ?", [$storeId])['setting_value'] ?? '1';
$iconWishlist = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_wishlist' AND store_id = ?", [$storeId])['setting_value'] ?? '1';
$iconCart = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'header_icon_cart' AND store_id = ?", [$storeId])['setting_value'] ?? '1';

// Fetch Top Bar Settings
$topbarSlidesRow = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'topbar_slides' AND store_id = ?", [$storeId]);
$topbarSlidesJson = $topbarSlidesRow['setting_value'] ?? '[]';
$topbarSlides = json_decode($topbarSlidesJson, true) ?: [];

$topbarLinksRow = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'topbar_links' AND store_id = ?", [$storeId]);
$topbarLinksJson = $topbarLinksRow['setting_value'] ?? '[]';
$topbarLinks = json_decode($topbarLinksJson, true) ?: [];

$pageTitle = 'Header Information';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Header Information</h1>
    </div>

    <!-- Messages -->
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

    <!-- Main Form -->
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_logo">
        
        <!-- Site Logo Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Site Logo</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Logo Type -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Logo Type</label>
                    <select name="logo_type" 
                            class="w-full border p-2 rounded" 
                            onchange="toggleLogoFields(this.value)">
                        <option value="text" <?php echo $logoType === 'text' ? 'selected' : ''; ?>>Text Logo</option>
                        <option value="image" <?php echo $logoType === 'image' ? 'selected' : ''; ?>>Image Logo</option>
                    </select>
                </div>
                
                <!-- Text Logo Field -->
                <div id="textLogoField" class="<?php echo $logoType === 'text' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold mb-2">Logo Text</label>
                    <input type="text" 
                           name="logo_text" 
                           value="<?php echo htmlspecialchars($logoText); ?>" 
                           class="w-full border p-2 rounded"
                           placeholder="milano">
                </div>
                
                <!-- Image Logo Field -->
                <div id="imageLogoField" class="<?php echo $logoType === 'image' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold mb-2">Logo Image</label>
                    
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[120px] hover:bg-gray-100 transition" onclick="document.getElementById('logoInput').click()">
                        
                        <img id="logoPreview" 
                             src="<?php echo !empty($logoPath) ? '../assets/images/' . $logoPath : ''; ?>" 
                             class="max-h-20 object-contain <?php echo !empty($logoPath) ? '' : 'hidden'; ?>">
                             
                        <div id="logoPlaceholder" class="<?php echo !empty($logoPath) ? 'hidden' : ''; ?> text-center">
                             <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                             <p class="text-sm text-gray-500">Click to upload logo</p>
                        </div>
                        
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 flex items-center justify-center transition-all rounded-lg"></div>
                    </div>

                    <input type="file" id="logoInput" name="logo" accept="image/*" class="hidden" onchange="previewLogo(this)">
                    <p class="text-xs text-gray-500 mt-2">Recommended: PNG or SVG format, transparent background</p>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" 
                        class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    <!-- Header Icons Section -->
    <form method="POST">
        <input type="hidden" name="action" value="update_icons">
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-icons mr-2 text-purple-600"></i>
                Header Icons Visibility
            </h2>
            
            <p class="text-sm text-gray-600 mb-4">Enable or disable header icons. Disabled icons will be hidden from the frontend.</p>
            
            <div class="space-y-3 mb-6">
                
                <!-- Search Icon -->
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center">
                        <i class="fas fa-search text-xl text-gray-600 mr-4 w-6 text-center"></i>
                        <div>
                            <span class="font-semibold text-gray-800">Search Icon</span>
                            <p class="text-xs text-gray-500">Search functionality</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="icon_search" 
                               value="1" 
                               <?php echo $iconSearch == '1' ? 'checked' : ''; ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                <!-- User Icon -->
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center">
                        <i class="fas fa-user text-xl text-gray-600 mr-4 w-6 text-center"></i>
                        <div>
                            <span class="font-semibold text-gray-800">User Icon</span>
                            <p class="text-xs text-gray-500">Account/Login</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="icon_user" 
                               value="1" 
                               <?php echo $iconUser == '1' ? 'checked' : ''; ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                <!-- Wishlist Icon -->
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center">
                        <i class="fas fa-heart text-xl text-gray-600 mr-4 w-6 text-center"></i>
                        <div>
                            <span class="font-semibold text-gray-800">Wishlist Icon</span>
                            <p class="text-xs text-gray-500">Favorites/Wishlist</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="icon_wishlist" 
                               value="1" 
                               <?php echo $iconWishlist == '1' ? 'checked' : ''; ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                <!-- Cart Icon -->
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <div class="flex items-center">
                        <i class="fas fa-shopping-cart text-xl text-gray-600 mr-4 w-6 text-center"></i>
                        <div>
                            <span class="font-semibold text-gray-800">Cart Icon</span>
                            <p class="text-xs text-gray-500">Shopping cart</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="icon_cart" 
                               value="1" 
                               <?php echo $iconCart == '1' ? 'checked' : ''; ?>
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
            </div>
            
            <button type="submit" 
                    class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition shadow-sm w-full md:w-auto">
                <i class="fas fa-save mr-2"></i>Save Icon Settings
            </button>
        </div>
    </form>

    <!-- Top Bar Settings Section -->
    <form method="POST">
        <input type="hidden" name="action" value="update_topbar">
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Top Bar Settings</h2>
            
            <!-- Announcement Slides -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-md">Announcement Slides</h3>
                    <button type="button" onclick="addSlide()" class="text-white bg-green-600 hover:bg-green-700 text-sm px-3 py-1 rounded">
                        <i class="fas fa-plus"></i> Add Slide
                    </button>
                </div>
                
                <div id="slidesContainer" class="space-y-3">
                    <!-- Slides will be added here by JS -->
                </div>
            </div>
            
            <!-- Top Bar Links -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-md">Top Bar Links</h3>
                    <button type="button" onclick="addLink()" class="text-white bg-green-600 hover:bg-green-700 text-sm px-3 py-1 rounded">
                        <i class="fas fa-plus"></i> Add Link
                    </button>
                </div>
                
                <div id="linksContainer" class="space-y-3">
                    <!-- Links will be added here by JS -->
                </div>
            </div>
            
            <button type="submit" 
                    class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition">
                Save Top Bar Settings
            </button>
        </div>
    </form>

</div>
<script>
// Toggle between text and image logo fields
function toggleLogoFields(type) {
    const textField = document.getElementById('textLogoField');
    const imageField = document.getElementById('imageLogoField');
    
    if (type === 'text') {
        textField.classList.remove('hidden');
        imageField.classList.add('hidden');
    } else {
        textField.classList.add('hidden');
        imageField.classList.remove('hidden');
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('logoPreview');
            const placeholder = document.getElementById('logoPlaceholder');
            
            if (img) {
                img.src = e.target.result;
                img.classList.remove('hidden');
            }
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Top Bar Slides Management
const slidesData = <?php echo json_encode($topbarSlides); ?>;

function addSlide(data = null) {
    const container = document.getElementById('slidesContainer');
    const slideDiv = document.createElement('div');
    slideDiv.className = 'flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded slide-row';
    
    slideDiv.innerHTML = `
        <div class="flex-1">
            <input type="text" name="slide_texts[]" value="${data ? data.text : ''}" 
                   class="w-full border p-2 rounded text-sm" placeholder="Text (e.g., 100% secure...)" required>
        </div>
        <div class="flex-1">
            <input type="text" name="slide_links[]" value="${data ? data.link : ''}" 
                   class="w-full border p-2 rounded text-sm" placeholder="Link URL (optional)">
        </div>
        <div class="w-32">
            <input type="text" name="slide_link_texts[]" value="${data ? (data.link_text || '') : ''}" 
                   class="w-full border p-2 rounded text-sm" placeholder="Link Text">
        </div>
        <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700 p-2">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    container.appendChild(slideDiv);
}

// Top Bar Links Management
const linksData = <?php echo json_encode($topbarLinks); ?>;

function addLink(data = null) {
    const container = document.getElementById('linksContainer');
    const linkDiv = document.createElement('div');
    linkDiv.className = 'flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded link-row';
    
    linkDiv.innerHTML = `
        <div class="flex-1">
            <input type="text" name="link_labels[]" value="${data ? data.label : ''}" 
                   class="w-full border p-2 rounded text-sm" placeholder="Link label (e.g., Contact Us)" required>
        </div>
        <div class="flex-1">
            <input type="text" name="link_urls[]" value="${data ? data.url : ''}" 
                   class="w-full border p-2 rounded text-sm" placeholder="Link URL (e.g., /contact)" required>
        </div>
        <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700 p-2">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    container.appendChild(linkDiv);
}

function removeRow(btn) {
    btn.closest('.slide-row, .link-row').remove();
}

// Initialize existing data
document.addEventListener('DOMContentLoaded', function() {
    // Load existing slides
    if (slidesData && slidesData.length > 0) {
        slidesData.forEach(slide => addSlide(slide));
    } else {
        addSlide(); // Add one empty slide by default
    }
    
    // Load existing links
    if (linksData && linksData.length > 0) {
        linksData.forEach(link => addLink(link));
    } else {
        addLink(); // Add one empty link by default
    }
});

</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
