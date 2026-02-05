<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }
        $db->execute("DELETE FROM banners WHERE id = ? AND store_id = ?", [$id, $storeId]);
        $_SESSION['flash_success'] = "Banner deleted successfully!";
        header("Location: " . url('admin/banner'));
        exit;
    }
    elseif ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $heading = $_POST['heading'] ?? '';
        $subheading = $_POST['subheading'] ?? '';
        $link = preg_replace('/\.php(\?|$)/', '$1', $_POST['link'] ?? '');
        $link_mobile = preg_replace('/\.php(\?|$)/', '$1', $_POST['link_mobile'] ?? '');
        $button_text = $_POST['button_text'] ?? 'Shop Now';
        $image_desktop = '';
        $image_mobile = '';
        $display_order = (int)($_POST['display_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Handle Desktop Image Upload
        if (!empty($_FILES['image_desktop']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/banners/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = 'desktop_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image_desktop']['name']);
            if (move_uploaded_file($_FILES['image_desktop']['tmp_name'], $uploadDir . $filename)) {
                $image_desktop = 'assets/images/banners/' . $filename;
            } else {
                $error = "Failed to upload desktop image.";
            }
        }
        
        // Handle Mobile Image Upload
        if (!empty($_FILES['image_mobile']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/banners/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = 'mobile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image_mobile']['name']);
            if (move_uploaded_file($_FILES['image_mobile']['tmp_name'], $uploadDir . $filename)) {
                $image_mobile = 'assets/images/banners/' . $filename;
            } else {
                $error = "Failed to upload mobile image.";
            }
        }
        
        if (empty($error)) {
            if ($id) {
                // Update existing
                $sql = "UPDATE banners SET heading = ?, subheading = ?, link = ?, link_mobile = ?, button_text = ?, display_order = ?, active = ?";
                $params = [$heading, $subheading, $link, $link_mobile, $button_text, $display_order, $active];
                
                if ($image_desktop) {
                    $sql .= ", image_desktop = ?";
                    $params[] = $image_desktop;
                }
                if ($image_mobile) {
                    $sql .= ", image_mobile = ?";
                    $params[] = $image_mobile;
                }
                
                $sql .= " WHERE id = ? AND store_id = ?";
                $params[] = $id;
                $params[] = $_SESSION['store_id'] ?? null;
                
                $db->execute($sql, $params);
                $_SESSION['flash_success'] = "Banner updated successfully!";
            } else {
                // Insert new
                if (empty($image_desktop) && empty($_FILES['image_desktop']['name'])) {
                    $error = "Desktop image is required for new banners.";
                } else {
                    // Determine Store ID
                    $storeId = $_SESSION['store_id'] ?? null;
                    if (!$storeId && isset($_SESSION['user_email'])) {
                     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                     $storeId = $storeUser['store_id'] ?? null;
                }

                    $db->execute(
                        "INSERT INTO banners (heading, subheading, link, link_mobile, button_text, image_desktop, image_mobile, display_order, active, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$heading, $subheading, $link, $link_mobile, $button_text, $image_desktop, $image_mobile, $display_order, $active, $storeId]
                    );
                    $_SESSION['flash_success'] = "Banner added successfully!";
                }
            }
            
            if (empty($error)) {
                header("Location: " . url('admin/banner'));
                exit;
            }
        }
    }
}

// Check Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$pageTitle = 'Banner Settings';
require_once __DIR__ . '/../includes/admin-header.php';

$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
// Fetch Banners
$banners = $db->fetchAll("SELECT * FROM banners WHERE store_id = ? ORDER BY display_order ASC, created_at DESC", [$storeId]);
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Banner Settings</h1>
        <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i> Add New Banner
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Banners List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($banners as $index => $banner): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <img src="<?php echo $baseUrl . '/' . $banner['image_desktop']; ?>" class="h-16 w-32 object-cover rounded" alt="Banner">
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($banner['heading']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($banner['subheading']); ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo htmlspecialchars($banner['link']); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo $banner['display_order']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $banner['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $banner['active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick='editBanner(allBanners[<?php echo $index; ?>])' class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $banner['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($banners)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No banners found. Add one to get started.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="bannerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold" id="modalTitle">Add New Banner</h3>
            <button onclick="closeModal()" class="text-gray-600 hover:text-gray-800">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="bannerForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="bannerId">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Heading</label>
                    <input type="text" name="heading" id="bannerHeading" class="w-full border p-2 rounded">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Subheading</label>
                    <input type="text" name="subheading" id="bannerSubheading" class="w-full border p-2 rounded">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Button Text</label>
                    <input type="text" name="button_text" id="bannerButtonText" value="Shop Now" class="w-full border p-2 rounded">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Desktop Link (Button/Banner)</label>
                    <input type="text" name="link" id="bannerLink" class="w-full border p-2 rounded" placeholder="e.g. /shop or https://google.com">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Mobile Link (Optional)</label>
                    <input type="text" name="link_mobile" id="bannerLinkMobile" class="w-full border p-2 rounded" placeholder="Leave empty to use Desktop link">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Display Order</label>
                    <input type="number" name="display_order" id="bannerOrder" value="0" class="w-full border p-2 rounded">
                </div>
                
                <div class="flex items-center mt-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="active" id="bannerActive" value="1" checked class="form-checkbox h-5 w-5 text-blue-600">
                        <span class="ml-2 text-gray-700">Active</span>
                    </label>
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Desktop Image (Required)</label>
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[160px] hover:bg-gray-100 transition" onclick="document.getElementById('desktopInput').click()">
                        <img id="previewDesktopImg" src="" class="max-h-32 w-auto object-contain hidden" alt="Desktop Preview">
                        <div id="placeholderDesktop" class="text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500 font-semibold">Click to upload Desktop Image</p>
                            <p class="text-xs text-gray-400 mt-1">Recommended: 1920x800px</p>
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 rounded-lg transition-all"></div>
                    </div>
                    <input type="file" name="image_desktop" id="desktopInput" accept="image/*" class="hidden" onchange="previewBannerImage(this, 'previewDesktopImg', 'placeholderDesktop')">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Mobile Image (Optional)</label>
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[160px] hover:bg-gray-100 transition" onclick="document.getElementById('mobileInput').click()">
                        <img id="previewMobileImg" src="" class="max-h-32 w-auto object-contain hidden" alt="Mobile Preview">
                        <div id="placeholderMobile" class="text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500 font-semibold">Click to upload Mobile Image</p>
                            <p class="text-xs text-gray-400 mt-1">Recommended: 800x800px</p>
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 rounded-lg transition-all"></div>
                    </div>
                    <input type="file" name="image_mobile" id="mobileInput" accept="image/*" class="hidden" onchange="previewBannerImage(this, 'previewMobileImg', 'placeholderMobile')">
                </div>
            </div>
            
            <div class="flex justify-end gap-2 text-right">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 btn-loading">Save Banner</button>
            </div>
        </form>
    </div>
</div>

<script>
const allBanners = <?php echo json_encode($banners); ?>;

function openModal() {
    document.getElementById('bannerForm').reset();
    document.getElementById('bannerId').value = '';
    document.getElementById('modalTitle').innerText = 'Add New Banner';
    
    // Reset Desktop Preview
    document.getElementById('previewDesktopImg').src = '';
    document.getElementById('previewDesktopImg').classList.add('hidden');
    document.getElementById('placeholderDesktop').classList.remove('hidden');
    
    // Reset Mobile Preview
    document.getElementById('previewMobileImg').src = '';
    document.getElementById('previewMobileImg').classList.add('hidden');
    document.getElementById('placeholderMobile').classList.remove('hidden');
    
    document.getElementById('bannerModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('bannerModal').classList.add('hidden');
}

function editBanner(banner) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Banner';
    document.getElementById('bannerId').value = banner.id;
    document.getElementById('bannerHeading').value = banner.heading;
    document.getElementById('bannerSubheading').value = banner.subheading;
    document.getElementById('bannerButtonText').value = banner.button_text;
    document.getElementById('bannerLink').value = banner.link;
    document.getElementById('bannerLinkMobile').value = banner.link_mobile || '';
    document.getElementById('bannerOrder').value = banner.display_order;
    document.getElementById('bannerActive').checked = banner.active == 1;
    
    if (banner.image_desktop) {
        const img = document.getElementById('previewDesktopImg');
        const placeholder = document.getElementById('placeholderDesktop');
        img.src = '<?php echo $baseUrl; ?>/' + banner.image_desktop;
        img.classList.remove('hidden');
        placeholder.classList.add('hidden');
    }
    
    if (banner.image_mobile) {
        const img = document.getElementById('previewMobileImg');
        const placeholder = document.getElementById('placeholderMobile');
        img.src = '<?php echo $baseUrl; ?>/' + banner.image_mobile;
        img.classList.remove('hidden');
        placeholder.classList.add('hidden');
    }
}

function previewBannerImage(input, imgId, placeholderId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            
            img.src = e.target.result;
            img.classList.remove('hidden');
            placeholder.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
