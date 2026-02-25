<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Settings.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$success = '';
$error = '';

// Detect Store ID once at the top
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for max post size violation
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "The file is too large. It exceeds the server's post_max_size limit of " . ini_get('post_max_size') . ".";
    } else {
        $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->execute("DELETE FROM banners WHERE id = ? AND store_id = ?", [$id, $storeId]);
        $_SESSION['flash_success'] = "Banner deleted successfully!";
        header("Location: " . url('admin/banner'));
        exit;
    }
    elseif ($action === 'update_order') {
        $orders = $_POST['order'] ?? [];
        foreach ($orders as $id => $order) {
            $db->execute("UPDATE banners SET display_order = ? WHERE id = ? AND store_id = ?", [(int)$order, (int)$id, $storeId]);
        }

        $_SESSION['flash_success'] = "Display orders updated successfully!";
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
        $remove_desktop = isset($_POST['remove_desktop']) && $_POST['remove_desktop'] == '1';
        $remove_mobile = isset($_POST['remove_mobile']) && $_POST['remove_mobile'] == '1';
        $display_order = (int)($_POST['display_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Handle Desktop Image Upload
        if (!empty($_FILES['image_desktop']['name'])) {
            if ($_FILES['image_desktop']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/images/banners/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $filename = 'desktop_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image_desktop']['name']);
                if (move_uploaded_file($_FILES['image_desktop']['tmp_name'], $uploadDir . $filename)) {
                    $image_desktop = 'assets/images/banners/' . $filename;
                } else {
                    $error = "Failed to move uploaded desktop image.";
                }
            } elseif ($_FILES['image_desktop']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['image_desktop']['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = "Desktop image is too large (Max " . ini_get('upload_max_filesize') . ").";
            } else {
                $error = "Desktop image upload error code: " . $_FILES['image_desktop']['error'];
            }
        }
        
        // Handle Mobile Image Upload
        if (!empty($_FILES['image_mobile']['name'])) {
            if ($_FILES['image_mobile']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/images/banners/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $filename = 'mobile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image_mobile']['name']);
                if (move_uploaded_file($_FILES['image_mobile']['tmp_name'], $uploadDir . $filename)) {
                    $image_mobile = 'assets/images/banners/' . $filename;
                } else {
                    $error = "Failed to move uploaded mobile image.";
                }
            } elseif ($_FILES['image_mobile']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['image_mobile']['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = "Mobile image is too large (Max " . ini_get('upload_max_filesize') . ").";
            } else {
                $error = "Mobile image upload error code: " . $_FILES['image_mobile']['error'];
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
                } elseif ($remove_desktop) {
                    $sql .= ", image_desktop = ''";
                }
                
                if ($image_mobile) {
                    $sql .= ", image_mobile = ?";
                    $params[] = $image_mobile;
                } elseif ($remove_mobile) {
                    $sql .= ", image_mobile = ''";
                }
                
                $sql .= " WHERE id = ? AND store_id = ?";
                $params[] = $id;
                $params[] = $storeId;
                
                $db->execute($sql, $params);
                $_SESSION['flash_success'] = "Banner updated successfully!";
            } else {
                // Insert new
                if (empty($image_desktop) && empty($_FILES['image_desktop']['name'])) {
                    $error = "Desktop image is required for new banners.";
                } else {
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
    elseif ($action === 'update_settings') {
        $show_arrows = isset($_POST['show_arrows']) ? true : false;
        $show_section = isset($_POST['show_section']) ? true : false;
        $hide_text_mobile = isset($_POST['hide_text_mobile']) ? true : false;
        $adaptive_mobile_height = isset($_POST['adaptive_mobile_height']) ? true : false;
        $alignment = $_POST['alignment'] ?? 'left';
        $alignment_mobile = $_POST['alignment_mobile'] ?? 'center';
        $content_width = $_POST['content_width'] ?? '100';
        
        $config = [
            'show_arrows' => $show_arrows,
            'show_section' => $show_section,
            'hide_text_mobile' => $hide_text_mobile,
            'adaptive_mobile_height' => $adaptive_mobile_height,
            'alignment' => $alignment,
            'alignment_mobile' => $alignment_mobile,
            'content_width' => $content_width
        ];
        file_put_contents(__DIR__ . '/banner_config.json', json_encode($config));

        // Save Visual Styles
        $settingsObj = new Settings();
        $banner_styles = [
            'heading_color' => $_POST['heading_color'] ?? '#ffffff',
            'subheading_color' => $_POST['subheading_color'] ?? '#f3f4f6',
            'button_bg_color' => $_POST['button_bg_color'] ?? '#ffffff',
            'button_text_color' => $_POST['button_text_color'] ?? '#000000',
            'arrow_bg_color' => $_POST['arrow_bg_color'] ?? '#ffffff',
            'arrow_icon_color' => $_POST['arrow_icon_color'] ?? '#1f2937'
        ];
        $settingsObj->set('banner_styles', json_encode($banner_styles), 'homepage');

        $_SESSION['flash_success'] = "Banner settings and styles updated successfully!";
        header("Location: " . url('admin/banner'));
        exit;
    }
    } // End of else block for post_max_size check
}

// Check Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$pageTitle = 'Banner Settings';
require_once __DIR__ . '/../includes/admin-header.php';

// $storeId is already detected at the top
// Fetch Banners
$banners = $db->fetchAll("SELECT * FROM banners WHERE store_id = ? ORDER BY display_order ASC, created_at DESC", [$storeId]);
?>

<?php
// Load Config
$bannerConfigPath = __DIR__ . '/banner_config.json';
$showArrows = true;
$showSection = true;
if (file_exists($bannerConfigPath)) {
    $config = json_decode(file_get_contents($bannerConfigPath), true);
    $showArrows = isset($config['show_arrows']) ? $config['show_arrows'] : true;
    $showSection = isset($config['show_section']) ? $config['show_section'] : true;
    $hideTextMobile = isset($config['hide_text_mobile']) ? $config['hide_text_mobile'] : false;
    $adaptiveMobileHeight = isset($config['adaptive_mobile_height']) ? $config['adaptive_mobile_height'] : false;
    $contentAlignment = isset($config['alignment']) ? $config['alignment'] : 'left';
    $contentAlignmentMobile = isset($config['alignment_mobile']) ? $config['alignment_mobile'] : 'center';
    $contentWidth = isset($config['content_width']) ? $config['content_width'] : '100';
} else {
    $hideTextMobile = false; // Default
    $adaptiveMobileHeight = false;
    $contentAlignment = 'left';
    $contentAlignmentMobile = 'center';
    $contentWidth = '100';
}

// Fetch Style Settings
$settingsObj = new Settings();
$savedStylesJson = $settingsObj->get('banner_styles', '{"heading_color":"#ffffff","subheading_color":"#f3f4f6","button_bg_color":"#ffffff","button_text_color":"#000000","arrow_bg_color":"#ffffff","arrow_icon_color":"#1f2937"}');
$savedStyles = json_decode($savedStylesJson, true);

$s_heading_color = $savedStyles['heading_color'] ?? '#ffffff';
$s_subheading_color = $savedStyles['subheading_color'] ?? '#f3f4f6';
$s_btn_bg = $savedStyles['button_bg_color'] ?? '#ffffff';
$s_btn_text = $savedStyles['button_text_color'] ?? '#000000';
$s_arrow_bg = $savedStyles['arrow_bg_color'] ?? '#ffffff';
$s_arrow_icon = $savedStyles['arrow_icon_color'] ?? '#1f2937';
?>

<div class="container mx-auto p-6">
    <!-- Sticky Header -->
    <div class="sticky top-0 z-40 bg-gray-50/80 backdrop-blur-sm -mx-6 px-6 py-4 mb-6 border-b border-gray-200 flex justify-between items-center shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Banner Manager</h1>
            <p class="text-gray-600 text-sm">Manage homepage banners and slider settings.</p>
        </div>
        <div class="flex gap-3">
            <button type="submit" form="settingsForm" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition shadow-md font-bold flex items-center gap-2">
                <i class="fas fa-save"></i> Save All Settings
            </button>
            <button onclick="openModal()" class="bg-white text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg hover:bg-gray-50 transition shadow-sm font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Banner
            </button>
        </div>
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

    <!-- Visibility Settings Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Slider Visibility</h2>
            <p class="text-sm text-gray-500">Control the visibility of navigation arrows on sliders.</p>
        </div>
        <div class="p-6">
            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="update_settings">
                <!-- Banner Arrows Toggle -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-indigo-50 rounded-lg text-indigo-600">
                            <i class="fas fa-images text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Banner Arrows</h3>
                            <p class="text-sm text-gray-500">Show navigation arrows for the main banner slider</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="show_arrows" class="sr-only peer" <?php echo $showArrows ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
                
                <!-- Hero Section Visibility Toggle -->
                <div class="flex items-center justify-between border-t border-gray-100 pt-4 mb-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                            <i class="fas fa-eye text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Show Hero Section</h3>
                            <p class="text-sm text-gray-500">Toggle the visibility of the entire hero banner section</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="show_section" class="sr-only peer" <?php echo $showSection ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                <!-- Mobile Text Visibility Toggle -->
                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-purple-50 rounded-lg text-purple-600">
                            <i class="fas fa-mobile-alt text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Hide Text on Mobile</h3>
                            <p class="text-sm text-gray-500">Hide heading and subheading on mobile devices</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="hide_text_mobile" class="sr-only peer" <?php echo $hideTextMobile ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
                
                <!-- Adaptive Mobile Height -->
                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-pink-50 rounded-lg text-pink-600">
                            <i class="fas fa-expand-arrows-alt text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Adaptive Mobile Height</h3>
                            <p class="text-sm text-gray-500">Banner height adjusts based on content height on mobile</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="adaptive_mobile_height" class="sr-only peer" <?php echo $adaptiveMobileHeight ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></div>
                    </label>
                </div>

                <!-- Content Alignment -->
                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600">
                            <i class="fas fa-align-center text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Content Alignment</h3>
                            <p class="text-sm text-gray-500">Align heading, subheading and button</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-gray-500 mb-1 uppercase">Desktop Alignment</label>
                            <select name="alignment" class="border rounded-md px-3 py-1.5 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="left" <?php echo $contentAlignment === 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="center" <?php echo $contentAlignment === 'center' ? 'selected' : ''; ?>>Center</option>
                                <option value="right" <?php echo $contentAlignment === 'right' ? 'selected' : ''; ?>>Right</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs font-bold text-gray-500 mb-1 uppercase">Mobile Alignment</label>
                            <select name="alignment_mobile" class="border rounded-md px-3 py-1.5 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="left" <?php echo $contentAlignmentMobile === 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="center" <?php echo $contentAlignmentMobile === 'center' ? 'selected' : ''; ?>>Center</option>
                                <option value="right" <?php echo $contentAlignmentMobile === 'right' ? 'selected' : ''; ?>>Right</option>
                            </select>
                        </div>
                   </div>
                </div>

                <!-- Content Width -->
                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-orange-50 rounded-lg text-orange-600">
                            <i class="fas fa-arrows-alt-h text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Content Base Width</h3>
                            <p class="text-sm text-gray-500">How much space the text takes up on Desktop</p>
                        </div>
                    </div>
                    <select name="content_width" class="border rounded-md px-3 py-1.5 text-sm font-medium focus:ring-2 focus:ring-orange-500 outline-none">
                        <option value="100" <?php echo $contentWidth === '100' ? 'selected' : ''; ?>>Full Width (100%)</option>
                        <option value="50" <?php echo $contentWidth === '50' ? 'selected' : ''; ?>>Half Width (50-50)</option>
                        <option value="40" <?php echo $contentWidth === '40' ? 'selected' : ''; ?>>Narrow (40%)</option>
                    </select>
                </div>
                
                <!-- Visual Styles -->
                <div class="mt-8 border-t border-gray-200 pt-6">
                     <h3 class="font-bold text-gray-800 mb-4">Visual Styling</h3>
                     <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold mb-2">Heading Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="heading_color" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-2">Subheading Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="subheading_color" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-2">Button Background</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="button_bg_color" value="<?php echo htmlspecialchars($s_btn_bg); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_bg); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-2">Button Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="button_text_color" value="<?php echo htmlspecialchars($s_btn_text); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_btn_text); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                         <div>
                            <label class="block text-sm font-bold mb-2">Arrow Background</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="arrow_bg_color" value="<?php echo htmlspecialchars($s_arrow_bg); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_arrow_bg); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                         <div>
                            <label class="block text-sm font-bold mb-2">Arrow Icon Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="arrow_icon_color" value="<?php echo htmlspecialchars($s_arrow_icon); ?>" class="h-10 w-16 cursor-pointer border rounded" oninput="this.nextElementSibling.value = this.value">
                                <input type="text" value="<?php echo htmlspecialchars($s_arrow_icon); ?>" class="flex-1 border p-2 rounded text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                     </div>
                </div>

            </form>
        </div>
    </div>



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
                    <tr data-banner='<?php echo htmlspecialchars(json_encode($banner), ENT_QUOTES, 'UTF-8'); ?>'>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img src="<?php echo getImageUrl($banner['image_desktop']); ?>" class="h-16 w-32 object-cover rounded" alt="Banner">
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($banner['heading'])): ?>
                                <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($banner['heading']); ?></div>
                            <?php else: ?>
                                <div class="text-sm italic text-gray-400">No Heading</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($banner['subheading'])): ?>
                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($banner['subheading']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="max-w-[150px] truncate" title="<?php echo htmlspecialchars($banner['link']); ?>">
                                <?php echo htmlspecialchars($banner['link'] ?: 'None'); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-bold">
                            #<?php echo (int)($banner['display_order'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $banner['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $banner['active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button type="button" onclick="editBanner(this)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <button type="button" onclick="if(confirm('Delete this banner?')) { deleteBanner(<?php echo $banner['id']; ?>); }" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($banners)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No banners found. Add one to get started.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Separate Delete Form for safety -->
        <form id="deleteForm" method="POST" class="hidden">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
        </form>
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
            <input type="hidden" name="remove_desktop" id="removeDesktopFlag" value="0">
            <input type="hidden" name="remove_mobile" id="removeMobileFlag" value="0">
            
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
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[160px] hover:bg-gray-100 transition">
                        <div class="relative" id="desktopPreviewWrapper" onclick="document.getElementById('desktopInput').click()">
                            <img id="previewDesktopImg" src="" class="max-h-32 w-auto object-contain hidden" alt="Desktop Preview">
                            <button type="button" onclick="event.stopPropagation(); clearBannerImage('desktop')" id="removeDesktopBtn" class="absolute -top-2 -right-2 bg-red-500 text-white w-6 h-6 rounded-full hidden items-center justify-center hover:bg-red-600 transition shadow-sm z-10">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                        <div id="placeholderDesktop" class="text-center" onclick="document.getElementById('desktopInput').click()">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500 font-semibold">Click to upload Desktop Image</p>
                            <p class="text-xs text-gray-400 mt-1">Recommended: 1920x800px</p>
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 rounded-lg transition-all pointer-events-none"></div>
                    </div>
                    <input type="file" name="image_desktop" id="desktopInput" accept="image/*" class="hidden" onchange="previewBannerImage(this, 'previewDesktopImg', 'placeholderDesktop', 'removeDesktopBtn')">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Mobile Image (Optional)</label>
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[160px] hover:bg-gray-100 transition">
                        <div class="relative" id="mobilePreviewWrapper" onclick="document.getElementById('mobileInput').click()">
                            <img id="previewMobileImg" src="" class="max-h-32 w-auto object-contain hidden" alt="Mobile Preview">
                            <button type="button" onclick="event.stopPropagation(); clearBannerImage('mobile')" id="removeMobileBtn" class="absolute -top-2 -right-2 bg-red-500 text-white w-6 h-6 rounded-full hidden items-center justify-center hover:bg-red-600 transition shadow-sm z-10">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                        <div id="placeholderMobile" class="text-center" onclick="document.getElementById('mobileInput').click()">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500 font-semibold">Click to upload Mobile Image</p>
                            <p class="text-xs text-gray-400 mt-1">Recommended: 800x800px</p>
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 rounded-lg transition-all pointer-events-none"></div>
                    </div>
                    <input type="file" name="image_mobile" id="mobileInput" accept="image/*" class="hidden" onchange="previewBannerImage(this, 'previewMobileImg', 'placeholderMobile', 'removeMobileBtn')">
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
window.allBanners = <?php echo json_encode($banners); ?>;

window.getFullImageUrl = function(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path;
    
    // Use the global BASE_URL if available, otherwise fallback
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '<?php echo $baseUrl; ?>';
    let cleanBase = baseUrl.replace(/\/+$/, '');
    let cleanPath = path.replace(/^\/+/, '');
    
    return cleanBase + '/' + cleanPath;
};

window.openModal = function() {
    document.getElementById('bannerForm').reset();
    document.getElementById('bannerId').value = '';
    document.getElementById('modalTitle').innerText = 'Add New Banner';
    
    // Reset Removal Flags
    document.getElementById('removeDesktopFlag').value = '0';
    document.getElementById('removeMobileFlag').value = '0';
    
    // Reset Previews and Placeholders
    const previews = ['previewDesktopImg', 'previewMobileImg'];
    const placeholders = ['placeholderDesktop', 'placeholderMobile'];
    const removeBtns = ['removeDesktopBtn', 'removeMobileBtn'];

    previews.forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.src = ''; el.classList.add('hidden'); }
    });
    placeholders.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    });
    removeBtns.forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.add('hidden'); el.style.display = 'none'; }
    });
    
    document.getElementById('bannerModal').classList.remove('hidden');
};

window.closeModal = function() {
    document.getElementById('bannerModal').classList.add('hidden');
};

window.editBanner = function(btn) {
    const row = btn.closest('tr');
    if (!row) return;
    const bannerData = row.getAttribute('data-banner');
    if (!bannerData) return;
    
    const banner = JSON.parse(bannerData);
    if (!banner) return;

    window.openModal(); // This already handles UI reset
    
    document.getElementById('modalTitle').innerText = 'Edit Banner';
    document.getElementById('bannerId').value = banner.id || '';
    document.getElementById('bannerHeading').value = banner.heading || '';
    document.getElementById('bannerSubheading').value = banner.subheading || '';
    document.getElementById('bannerButtonText').value = banner.button_text || '';
    document.getElementById('bannerLink').value = banner.link || '';
    document.getElementById('bannerLinkMobile').value = banner.link_mobile || '';
    document.getElementById('bannerOrder').value = banner.display_order || 0;
    document.getElementById('bannerActive').checked = parseInt(banner.active) === 1;
    
    if (banner.image_desktop) {
        const img = document.getElementById('previewDesktopImg');
        const placeholder = document.getElementById('placeholderDesktop');
        const btn = document.getElementById('removeDesktopBtn');
        if (img && placeholder) {
            img.src = window.getFullImageUrl(banner.image_desktop);
            img.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (btn) {
                btn.classList.remove('hidden');
                btn.style.display = 'flex';
            }
        }
    }
    
    if (banner.image_mobile) {
        const img = document.getElementById('previewMobileImg');
        const placeholder = document.getElementById('placeholderMobile');
        const btn = document.getElementById('removeMobileBtn');
        if (img && placeholder) {
            img.src = window.getFullImageUrl(banner.image_mobile);
            img.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (btn) {
                btn.classList.remove('hidden');
                btn.style.display = 'flex';
            }
        }
    }
};

window.deleteBanner = function(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
};

window.previewBannerImage = function(input, imgId, placeholderId, btnId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            const btn = document.getElementById(btnId);
            
            img.src = e.target.result;
            img.classList.remove('hidden');
            placeholder.classList.add('hidden');
            if (btn) {
                btn.classList.remove('hidden');
                btn.style.display = 'flex';
            }
            
            // Re-enable removal flag if a new image is chosen
            const flagId = (imgId === 'previewDesktopImg') ? 'removeDesktopFlag' : 'removeMobileFlag';
            document.getElementById(flagId).value = '0';
        }
        reader.readAsDataURL(input.files[0]);
    }
};

window.clearBannerImage = function(type) {
    const imgId = type === 'desktop' ? 'previewDesktopImg' : 'previewMobileImg';
    const placeholderId = type === 'desktop' ? 'placeholderDesktop' : 'placeholderMobile';
    const inputId = type === 'desktop' ? 'desktopInput' : 'mobileInput';
    const btnId = type === 'desktop' ? 'removeDesktopBtn' : 'removeMobileBtn';
    const flagId = type === 'desktop' ? 'removeDesktopFlag' : 'removeMobileFlag';
    
    const img = document.getElementById(imgId);
    const placeholder = document.getElementById(placeholderId);
    const input = document.getElementById(inputId);
    const btn = document.getElementById(btnId);
    
    img.src = '';
    img.classList.add('hidden');
    placeholder.classList.remove('hidden');
    input.value = ''; // Clear file input
    btn.classList.add('hidden');
    btn.style.display = 'none';
    
    // Set flag to remove from DB if we are editing an existing banner
    if (document.getElementById('bannerId').value) {
        document.getElementById(flagId).value = '1';
    }
};

</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
