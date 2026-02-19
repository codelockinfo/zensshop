<?php
ob_start(); // Buffer output
session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Settings.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();
$success = '';
$error = '';

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_heading = $_POST['section_heading'] ?? 'Shop By Category';
    $section_subheading = $_POST['section_subheading'] ?? 'Express your style with our standout collection—fashion meets sophistication.';
    $titles = $_POST['title'] ?? [];
    $links = $_POST['link'] ?? [];
    $orders = $_POST['sort_order'] ?? [];
    $ids = $_POST['id'] ?? [];
    $delete_ids = $_POST['delete_id'] ?? [];
    
    // Save Config to JSON for persistent storage (helps when table is empty)
    $show_section = isset($_POST['show_section']) ? true : false;
    $categoryConfig = [
        'heading' => $section_heading,
        'subheading' => $section_subheading,
        'show_section' => $show_section
    ];
    file_put_contents(__DIR__ . '/category_config.json', json_encode($categoryConfig));

    // Save Color Settings (Single Key)
    $settingsObj = new Settings();
    $styles = [
        'bg_color' => $_POST['bg_color'] ?? '#ffffff',
        'heading_color' => $_POST['heading_color'] ?? '#1f2937',
        'subheading_color' => $_POST['subheading_color'] ?? '#4b5563',
        'text_color' => $_POST['text_color'] ?? '#1f2937',
        'button_bg_color' => $_POST['button_bg_color'] ?? '#000000',
        'button_text_color' => $_POST['button_text_color'] ?? '#ffffff'
    ];
    $settingsObj->set('homepage_categories_styles', json_encode($styles), 'homepage');

    // Update all existing rows with the global heading/subheading if titles are empty but heading changed
    if (empty($titles)) {
        $db->execute("UPDATE section_categories SET heading = ?, subheading = ? WHERE store_id = ?", [$section_heading, $section_subheading, $storeId]);
    }
    
    // Handle Deletions
    if (!empty($delete_ids)) {
        $placeholders = str_repeat('?,', count($delete_ids) - 1) . '?';
        $db->execute("DELETE FROM section_categories WHERE id IN ($placeholders) AND store_id = ?", array_merge($delete_ids, [$storeId]));
    }
    
    // Check for POST size limit
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "Form submission failed. The sent data exceeds the server's limit.";
    }

    $insertedCount = 0;
    $updatedCount = 0;

    // Handle Updates and Inserts
    try {
        if (!$error) {
            for ($i = 0; $i < count($titles); $i++) {
                $id = $ids[$i] ?? null;
                $title = trim((string)$titles[$i]);
                $link = preg_replace('/\.php(\?|$)/', '$1', trim((string)($links[$i] ?? '')));
                $order = (int)($orders[$i] ?? 0);
                
                // Skip empty rows
                if (empty($title)) continue;
                
                $imagePath = '';
                if (isset($_FILES['image']['name'][$i]) && !empty($_FILES['image']['name'][$i])) {
                    $uploadDir = __DIR__ . '/../assets/images/categories/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $filename = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image']['name'][$i]);
                    if (move_uploaded_file($_FILES['image']['tmp_name'][$i], $uploadDir . $filename)) {
                        $imagePath = 'assets/images/categories/' . $filename;
                    }
                } else {
                    if ($id) {
                        $existing = $db->fetchOne("SELECT image FROM section_categories WHERE id = ?", [$id]);
                        $imagePath = $existing['image'] ?? '';
                    }
                }

                $exists = false;
                if (!empty($id)) {
                    $exists = $db->fetchOne("SELECT id FROM section_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
                }

                if ($exists) {
                    $sql = "UPDATE section_categories SET title = ?, link = ?, sort_order = ?, heading = ?, subheading = ?";
                    $params = [$title, $link, $order, $section_heading, $section_subheading];
                    if ($imagePath) {
                        $sql .= ", image = ?";
                        $params[] = $imagePath;
                    }
                    $sql .= " WHERE id = ? AND store_id = ?";
                    $params[] = $id;
                    $params[] = $storeId;
                    $db->execute($sql, $params);
                    $updatedCount++;
                } else {
                    $imgToSave = $imagePath ? $imagePath : ''; 
                    $db->execute(
                        "INSERT INTO section_categories (title, link, sort_order, image, heading, subheading, store_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$title, $link, $order, $imgToSave, $section_heading, $section_subheading, $storeId]
                    );
                    $insertedCount++;
                }
            }
            
            $_SESSION['flash_success'] = "Categories updated successfully!";
            header("Location: " . $baseUrl . '/admin/category');
            exit;
        }

    } catch (Exception $e) {
        $error = "Detailed Error: " . $e->getMessage();
    }
}

// Check Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$pageTitle = 'Homepage Categories';
require_once __DIR__ . '/../includes/admin-header.php';

// Fetch Categories
$categories = $db->fetchAll("SELECT * FROM section_categories WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);

// Load saved config for headers
$categoryConfigPath = __DIR__ . '/category_config.json';
$savedConfig = null;
$showSection = true;
if (file_exists($categoryConfigPath)) {
    $savedConfig = json_decode(file_get_contents($categoryConfigPath), true);
    $showSection = isset($savedConfig['show_section']) ? $savedConfig['show_section'] : true;
}

// Fetch Section Settings - Prioritize JSON for Admin Form persistence
$section_heading = 'Shop By Category';
$section_subheading = 'Express your style with our standout collection—fashion meets sophistication.';

if ($savedConfig !== null) {
    $section_heading = $savedConfig['heading'] ?? $section_heading;
    $section_subheading = $savedConfig['subheading'] ?? $section_subheading;
} elseif (!empty($categories)) {
    $section_heading = $categories[0]['heading'] ?? $section_heading;
    $section_subheading = $categories[0]['subheading'] ?? $section_subheading;
    $section_subheading = $categories[0]['subheading'] ?? $section_subheading;
}

// Fetch Style Settings
$settingsObj = new Settings();
$savedStylesJson = $settingsObj->get('homepage_categories_styles', '{"bg_color":"#ffffff","heading_color":"#1f2937","subheading_color":"#4b5563","text_color":"#1f2937","button_bg_color":"#000000","button_text_color":"#ffffff"}');
$savedStyles = json_decode($savedStylesJson, true);

// Defaults
$s_bg_color = $savedStyles['bg_color'] ?? '#ffffff';
$s_heading_color = $savedStyles['heading_color'] ?? '#1f2937';
$s_subheading_color = $savedStyles['subheading_color'] ?? '#4b5563';
$s_text_color = $savedStyles['text_color'] ?? '#1f2937';
$s_button_bg_color = $savedStyles['button_bg_color'] ?? '#000000';
$s_button_text_color = $savedStyles['button_text_color'] ?? '#ffffff';

// Fetch valid categories for the dropdown
$validCategories = $db->fetchAll("SELECT id, name, slug FROM categories WHERE status = 'active' AND store_id = ? ORDER BY name ASC", [$storeId]);
?>

<div class="p-6 pl-0">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="check_submit" value="1">
        
        <!-- Top Action Bar -->
        <div class="flex justify-between items-center mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200 sticky top-0 z-10">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Homepage Categories</h1>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition flex items-center shadow-sm btn-loading">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
            <button type="button" onclick="addRow()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                <i class="fas fa-plus mr-2"></i> Add New Category
            </button>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 admin-alert">
                <?php echo htmlspecialchars((string)$success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 admin-alert">
                <?php echo htmlspecialchars((string)$error); ?>
            </div>
        <?php endif; ?>

        <!-- Visibility Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Section Visibility</h2>
                <p class="text-sm text-gray-500">Control the visibility of this section on the homepage.</p>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                            <i class="fas fa-eye text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Show Categories Section</h3>
                            <p class="text-sm text-gray-500">Toggle the visibility of the categories section</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="show_section" class="sr-only peer" <?php echo $showSection ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>

        </div>

        <!-- Visual Style Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Visual Style</h2>
                <p class="text-sm text-gray-500">Customize the colors and appearance of this section.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Background Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="bg_color" value="<?php echo htmlspecialchars($s_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="heading_color" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_heading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="subheading_color" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_subheading_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Item Title/Text Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="text_color" value="<?php echo htmlspecialchars($s_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Button Background</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="button_bg_color" value="<?php echo htmlspecialchars($s_button_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_button_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Button Text Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="button_text_color" value="<?php echo htmlspecialchars($s_button_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                        <input type="text" value="<?php echo htmlspecialchars($s_button_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Headers -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Section Header Settings</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Section Heading</label>
                    <input type="text" name="section_heading" value="<?php echo htmlspecialchars((string)$section_heading); ?>" 
                           class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Shop By Category">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Section Subheading</label>
                    <textarea name="section_subheading" rows="1" 
                              class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Enter subheading text..."><?php echo htmlspecialchars((string)$section_subheading); ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200" id="categoriesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Sort</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Image</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                    <?php foreach ($categories as $index => $cat): ?>
                    <tr class="category-row">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" name="sort_order[]" value="<?php echo $cat['sort_order']; ?>" class="w-16 border rounded p-1 text-center">
                            <input type="hidden" name="id[]" value="<?php echo $cat['id']; ?>">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="relative cursor-pointer group w-12 h-12" onclick="this.querySelector('input').click()">
                                <img src="<?php echo $baseUrl . '/' . $cat['image']; ?>" class="h-12 w-12 object-cover rounded-full border border-gray-200 bg-gray-50">
                                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-0 group-hover:bg-opacity-40 rounded-full transition-all duration-200">
                                    <i class="fas fa-pen text-white opacity-0 group-hover:opacity-100 text-xs"></i>
                                </div>
                                <input type="file" name="image[]" accept="image/*" class="hidden" onchange="previewImage(this)">
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <input type="text" name="title[]" value="<?php echo htmlspecialchars((string)$cat['title']); ?>" class="w-full border rounded p-2" required>
                        </td>
                        <td class="px-6 py-4">
                            <select onchange="updateLink(this)" class="w-full border rounded p-2 text-sm">
                                <option value="">-- Select --</option>
                                <?php foreach ($validCategories as $vCat): ?>
                                    <?php 
                                    $isSelected = false;
                                    if (strpos($cat['link'], 'category=' . $vCat['slug']) !== false) {
                                        $isSelected = true;
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars((string)$vCat['slug']); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$vCat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-6 py-4">
                            <input type="text" name="link[]" value="<?php echo htmlspecialchars((string)$cat['link']); ?>" class="w-full border rounded p-2 bg-gray-50" placeholder="e.g., shop?category=rings">
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button type="button" onclick="markForDeletion(this, <?php echo $cat['id']; ?>)" class="text-red-500 hover:text-red-700 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($categories)): ?>
                <div id="emptyMessage" class="p-8 text-center text-gray-500">
                    No categories found. Click "Add New Category" to start.
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<template id="rowTemplate">
    <tr class="category-row bg-yellow-50 animate-fade-in">
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="sort_order[]" value="0" class="w-16 border rounded p-1 text-center">
            <input type="hidden" name="id[]" value="">
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="relative cursor-pointer group w-12 h-12" onclick="this.querySelector('input').click()">
                <img src="" class="h-12 w-12 object-cover rounded-full border border-gray-200 bg-gray-50 hidden preview-img">
                <div class="h-12 w-12 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 bg-gray-50 hover:bg-gray-100 transition-colors upload-placeholder">
                    <i class="fas fa-plus text-xs"></i>
                </div>
                <input type="file" name="image[]" accept="image/*" class="hidden" onchange="previewImage(this)">
            </div>
        </td>
        <td class="px-6 py-4">
            <input type="text" name="title[]" value="" class="w-full border rounded p-2" placeholder="Category Name" required>
        </td>
        <td class="px-6 py-4">
            <select onchange="updateLink(this)" class="w-full border rounded p-2 text-sm">
                <option value="">-- Select --</option>
                <?php foreach ($validCategories as $vCat): ?>
                    <option value="<?php echo htmlspecialchars((string)$vCat['slug']); ?>">
                        <?php echo htmlspecialchars((string)$vCat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-6 py-4">
            <input type="text" name="link[]" value="" class="w-full border rounded p-2 bg-gray-50" placeholder="Link URL">
        </td>
        <td class="px-6 py-4 text-right">
            <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700 transition">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<script>
function addRow() {
    const template = document.getElementById('rowTemplate');
    const tbody = document.getElementById('tableBody');
    const emptyMsg = document.getElementById('emptyMessage');
    
    if (emptyMsg) emptyMsg.style.display = 'none';
    
    const clone = template.content.cloneNode(true);
    tbody.appendChild(clone);
}

function removeRow(btn) {
    btn.closest('tr').remove();
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const wrapper = input.parentElement;
            const img = wrapper.querySelector('img');
            const placeholder = wrapper.querySelector('.upload-placeholder');
            
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

function updateLink(select) {
    const slug = select.value;
    if (slug) {
        const row = select.closest('tr');
        const linkInput = row.querySelector('input[name="link[]"]');
        if (linkInput) {
            linkInput.value = 'shop?category=' + slug;
        }
        const titleInput = row.querySelector('input[name="title[]"]');
        const selectedText = select.options[select.selectedIndex].text;
        if (titleInput) {
            titleInput.value = selectedText.trim();
        }
    }
}

function markForDeletion(btn, id) {
    const row = btn.closest('tr');
    row.style.opacity = '0.5';
    row.style.backgroundColor = '#fee2e2';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_id[]';
    input.value = id;
    row.appendChild(input);
    const inputs = row.querySelectorAll('input:not([type="hidden"][name="delete_id[]"])');
    inputs.forEach(input => input.disabled = true);
    btn.innerHTML = '<i class="fas fa-undo"></i>';
    btn.onclick = function() { undoDeletion(this, id) };
    btn.className = 'text-green-500 hover:text-green-700 transition';
}

function undoDeletion(btn, id) {
    const row = btn.closest('tr');
    row.style.opacity = '1';
    row.style.backgroundColor = '';
    const delInput = row.querySelector('input[name="delete_id[]"]');
    if (delInput) delInput.remove();
    const inputs = row.querySelectorAll('input');
    inputs.forEach(input => input.disabled = false);
    btn.innerHTML = '<i class="fas fa-trash"></i>';
    btn.onclick = function() { markForDeletion(this, id) };
    btn.className = 'text-red-500 hover:text-red-700 transition';
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
