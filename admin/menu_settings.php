<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();


$success = $_SESSION['menu_success'] ?? '';
$error = $_SESSION['menu_error'] ?? '';

// Clear session messages after reading
unset($_SESSION['menu_success']);
unset($_SESSION['menu_error']);

// Define Available Locations
$locations = [
    'header_main' => 'Header - Main Menu',
    'footer_company' => 'Footer - Our Company',
    'footer_quick_links' => 'Footer - Quick Links',
    'footer_categories' => 'Footer - Shop Categories',
    'footer_social' => 'Footer - Follow Us'
];

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Menu
    if ($action === 'create_menu') {
        $name = trim($_POST['menu_name']);
        if ($name) {
            try {
                $db->insert("INSERT INTO menus (name) VALUES (?)", [$name]);
                $success = "Menu created.";
            } catch (Exception $e) { $error = $e->getMessage(); }
        }
    }
    
    // 2. Delete Menu
    elseif ($action === 'delete_menu') {
        $menuId = intval($_POST['menu_id_delete']);
        if($menuId) {
             $db->execute("DELETE FROM menus WHERE id = ?", [$menuId]);
             $db->execute("DELETE FROM menu_items WHERE menu_id = ?", [$menuId]); 
             $success = "Menu deleted.";
        }
    }
    // 7. Rename Menu (and Location)
    elseif ($action === 'rename_menu') {
        $menuId = intval($_POST['menu_id']);
        $newName = trim($_POST['menu_name']);
        $location = !empty($_POST['location']) ? $_POST['location'] : null;
        
        if ($menuId && $newName) {
            try {
                if ($location) {
                    $db->execute("UPDATE menus SET location = NULL WHERE location = ?", [$location]);
                }
                $db->execute("UPDATE menus SET name = ?, location = ? WHERE id = ?", [$newName, $location, $menuId]);
                $success = "Menu updated.";
            } catch (Exception $e) { $error = $e->getMessage(); }
        }
    }

    // 3. Add Single Item
    elseif ($action === 'add_menu_item') {
        $menuId = intval($_POST['menu_id']);
        $label = trim($_POST['label']);
        $url = trim($_POST['url']);
        $sortOrder = intval($_POST['sort_order']);
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/uploads/menus/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = 'menus/' . $filename;
            }
        }

        if ($menuId && $label) {
            $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id, image_path) VALUES (?, ?, ?, ?, ?, ?)", 
                [$menuId, $label, $url, $sortOrder, $parentId, $imagePath]);
            $success = "Item added.";
        }
    }

    // 4. Update Item
    elseif ($action === 'update_menu_item') {
        $itemId = intval($_POST['item_id']);
        $label = trim($_POST['label']);
        $url = trim($_POST['url']);
        $sortOrder = intval($_POST['sort_order']);
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $badgeText = trim($_POST['badge_text'] ?? '');
        $customClasses = trim($_POST['custom_classes'] ?? '');
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
        
        $imageSql = "";
        $params = [$label, $url, $sortOrder, $parentId, $badgeText, $customClasses];
        
        // Handle image removal
        if ($removeImage) {
            $imageSql = ", image_path = NULL";
        }
        // Handle new image upload
        elseif (!empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/uploads/menus/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imageSql = ", image_path = ?";
                $params[] = 'menus/' . $filename;
            }
        }
        $params[] = $itemId;

        $db->execute("UPDATE menu_items SET label=?, url=?, sort_order=?, parent_id=?, badge_text=?, custom_classes=? $imageSql WHERE id=?", $params);
        
        // Store success message in session and redirect to prevent form resubmission
        $_SESSION['menu_success'] = "Item updated.";
        $menuId = intval($_POST['menu_id'] ?? 0);
        header("Location: " . $_SERVER['PHP_SELF'] . "?menu_id=" . $menuId);
        exit;
    }

    // 5. Delete Item
    elseif ($action === 'delete_menu_item') {
        $itemId = intval($_POST['item_id']);
        $db->execute("DELETE FROM menu_items WHERE id = ?", [$itemId]);
        $success = "Item deleted.";
    }

    // 7. Toggle Menu Handle (Simple implementation)
    elseif ($action === 'toggle_handle') {
         $menuId = intval($_POST['menu_id']);
         $target = $_POST['handle'];
         
         // If "assign", set handle. If "unassign", nullify. 
         // But we ensure only 1 menu has this handle.
         try {
             $db->execute("UPDATE menus SET location = NULL WHERE location = ?", [$target]); // clear old
             $db->execute("UPDATE menus SET location = ? WHERE id = ?", [$target, $menuId]); // set new
             echo json_encode(['status'=>'success']); 
             exit;
         } catch(Exception $e) { 
             echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); 
             exit; 
         }
    }

    // 6. Bulk Add Subitems
    elseif ($action === 'add_bulk_menu_items') {
        $menuId = intval($_POST['menu_id']);
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $items = $_POST['items'] ?? [];
        
        $cnt = 0;
        foreach ($items as $idx => $item) {
            $lbl = trim($item['label']);
            $lnk = trim($item['url']);
            $badge = trim($item['badge'] ?? '');
            $order = intval($item['order'] ?? 0);
            
            if(empty($lbl)) continue;

            $imgPath = null;
            if (!empty($_FILES['items']['name'][$idx]['image'])) {
                $uploadDir = __DIR__ . '/../assets/images/uploads/menus/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fname = $_FILES['items']['name'][$idx]['image'];
                $tmp = $_FILES['items']['tmp_name'][$idx]['image'];
                $target = time() . '_' . $idx . '_' . basename($fname);
                if(move_uploaded_file($tmp, $uploadDir . $target)) {
                    $imgPath = 'menus/' . $target;
                }
            }

            $db->insert("INSERT INTO menu_items (menu_id, label, url, parent_id, image_path, sort_order, badge_text) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                [$menuId, $lbl, $lnk, $parentId, $imgPath, $order, $badge]);
            $cnt++;
        }
        if($cnt > 0) $success = "$cnt items added.";
    }

    // 8. Reorder Items (Legacy)
    elseif ($action === 'reorder_items') {
        $orderMap = $_POST['order'] ?? []; 
        if (!empty($orderMap)) {
            try {
                $db->beginTransaction();
                foreach ($orderMap as $id => $order) {
                    $db->execute("UPDATE menu_items SET sort_order = ? WHERE id = ?", [intval($order), intval($id)]);
                }
                $db->commit();
                echo json_encode(['status'=>'success']);
            } catch (Exception $e) { $db->rollBack(); echo json_encode(['status'=>'error']); }
            exit;
        }
    }
    
    // 9. Reorder Items (Tree)
    elseif ($action === 'reorder_items_tree') {
        $treeData = json_decode($_POST['tree_data'] ?? '[]', true); // {itemId: {sort_order, parent_id}}
        
        if (!empty($treeData)) {
            try {
                $db->beginTransaction();
                foreach ($treeData as $id => $data) {
                    $order = intval($data['sort_order']);
                    $parentId = intval($data['parent_id']);
                    // If parentId is 0, make it NULL or 0 depending on DB. Assuming 0 is root or NULL.
                    // If DB uses NULL for root, check schema. Assuming 0 based on previous code.
                    $db->execute("UPDATE menu_items SET sort_order = ?, parent_id = ? WHERE id = ?", [$order, $parentId, intval($id)]);
                }
                $db->commit();
                echo json_encode(['status'=>'success']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
            }
            exit;
        }
    }
}

// Init Data
// Sort by ID ASC ensures created order (Header first, then Footer)
$menus = $db->fetchAll("SELECT * FROM menus ORDER BY id ASC");
$selectedMenuId = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : ($menus[0]['id'] ?? 0);
if(isset($_POST['menu_id']) && !isset($_GET['menu_id'])) $selectedMenuId = intval($_POST['menu_id']);

// Flattened Options
$allItemsFlat = [];
if($selectedMenuId) $allItemsFlat = $db->fetchAll("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC", [$selectedMenuId]);

// Tree
$menuTree = [];
if($selectedMenuId) $menuTree = buildMenuTree($allItemsFlat);

$pageTitle = 'Menu Management';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="flex h-full bg-gray-100 font-sans">
    
    <!-- Sidebar -->
    <div class="w-64 bg-white border-r border-gray-200 flex flex-col z-10">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h2 class="font-bold text-gray-700">Menus</h2>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <nav class="space-y-1">
                <?php foreach($menus as $m): ?>
                <div class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-md <?php echo $selectedMenuId == $m['id'] ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <a href="?menu_id=<?php echo $m['id']; ?>" class="truncate flex-1 block">
                        <?php echo htmlspecialchars($m['name']); ?>
                    </a>
                    <button onclick="openRenameModal(<?php echo $m['id']; ?>, '<?php echo addslashes($m['name']); ?>', '<?php echo $m['location'] ?? ''; ?>')" class="ml-2 text-gray-400 hover:text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity p-1" title="Edit Menu">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="p-4 border-t border-gray-200 bg-gray-50">
             <button onclick="document.getElementById('createMenuModal').classList.remove('hidden')" class="w-full bg-white border border-gray-300 text-gray-700 font-bold py-2 px-4 rounded shadow-sm hover:bg-gray-50 text-sm flex items-center justify-center">
                 <i class="fas fa-plus mr-2 text-xs"></i> Create New Menu
             </button>
        </div>

        </div>
    
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white shadow-sm border-b px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-800">
                <?php 
                    $currName = 'Settings';
                    foreach($menus as $m) if($m['id'] == $selectedMenuId) $currName = $m['name'];
                    echo htmlspecialchars($currName);
                ?>
            </h1>
            <div>
                 <?php if($selectedMenuId): ?>
                 <form method="POST" class="inline-block">
                     <input type="hidden" name="action" value="delete_menu">
                     <input type="hidden" name="menu_id_delete" value="<?php echo $selectedMenuId; ?>">
                     <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-medium">Delete Menu</button>
                 </form>
                 <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-8">

            <?php if ($selectedMenuId): ?>
            <div class="bg-white rounded shadow-sm border border-gray-200">
                <div class="flex justify-between items-center p-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-bold text-gray-700">Items in: <?php echo htmlspecialchars($currName); ?></h3>
                    <button onclick="openAddModal(null, '<?php echo addslashes($currName); ?>')" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold shadow hover:bg-blue-700 transition">+ Add Item</button>
                </div>
                
                <!-- Headers -->
                <div class="grid grid-cols-12 gap-4 px-4 py-3 bg-gray-50 text-xs font-semibold text-gray-500 uppercase border rounded-t-lg">
                    <div class="col-span-6">Label / Image</div>
                    <div class="col-span-4">URL</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>

                <!-- Tree Container -->
                <div id="menu-root" class="sortable-list border-l border-r border-b rounded-b-lg bg-white">
                     <?php 
                     if (empty($menuTree)) {
                         echo '<div class="p-8 text-center text-gray-500 italic">No items found. Add one!</div>';
                     } else {
                         renderMenuItems($menuTree);
                     }
                     ?>
                </div>
            </div>
            <?php else: ?>
                <div class="text-center text-gray-500 mt-20">Select a menu from the sidebar or create a new one.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Menu Modal -->
<div id="createMenuModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="font-bold text-lg mb-4">Create New Menu</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_menu">
            <input type="text" name="menu_name" placeholder="Menu Name" class="w-full border p-2 rounded mb-4" required>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('createMenuModal').classList.add('hidden')" class="px-4 py-2 text-gray-500">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-bold">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editItemModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg">
        <h3 class="font-bold text-lg mb-4">Edit Item</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_menu_item">
            <input type="hidden" name="item_id" id="edit_item_id">
            <input type="hidden" name="menu_id" value="<?php echo $selectedMenuId; ?>">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2"><label class="block text-xs font-bold mb-1">Label</label><input type="text" name="label" id="edit_label" class="w-full border p-2 rounded" required></div>
                <div class="col-span-2"><label class="block text-xs font-bold mb-1">URL</label><input type="text" name="url" id="edit_url" class="w-full border p-2 rounded"></div>
                <!-- <div><label class="block text-xs font-bold mb-1">Order</label><input type="number" name="sort_order" id="edit_sort_order" class="w-full border p-2 rounded"></div> -->
                <div><label class="block text-xs font-bold mb-1">Parent Item</label>
                    <select name="parent_id" id="edit_parent_id" class="w-full border p-2 rounded">
                        <option value="">(None)</option>
                        <?php foreach($allItemsFlat as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-xs font-bold mb-1">Badge (Hot/New)</label><input type="text" name="badge_text" id="edit_badge_text" class="w-full border p-2 rounded" placeholder="HOT"></div>
                <div><label class="block text-xs font-bold mb-1">Custom CSS Classes</label><textarea name="custom_classes" id="edit_custom_classes" class="w-full border p-2 rounded text-xs" rows="2" placeholder="text-black hover:text-red-700 transition font-sans"></textarea></div>
                
                <!-- Current Image Display -->
                <div class="col-span-2">
                    <label class="block text-xs font-bold mb-1">Image</label>
                    <div id="current_image_container" class="hidden mb-2 p-3 border border-gray-200 rounded bg-gray-50">
                        <div class="flex items-center gap-3">
                            <img id="current_image_preview" src="" class="w-16 h-16 object-cover rounded border border-gray-300">
                            <div class="flex-1">
                                <p class="text-xs text-gray-600 mb-1">Current Image</p>
                                <button type="button" onclick="removeCurrentImage()" class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                    <i class="fas fa-trash mr-1"></i> Remove Image
                                </button>
                            </div>
                        </div>
                    </div>
                    <input type="file" name="image" id="edit_image_input" class="w-full text-xs" accept="image/*" onchange="previewNewImage(this)">
                    <input type="hidden" name="remove_image" id="remove_image_flag" value="0">
                    <div id="new_image_preview_container" class="hidden mt-2">
                        <img id="new_image_preview" src="" class="w-20 h-20 object-cover rounded border border-blue-300">
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeModal('editItemModal')" class="px-4 py-2 text-gray-500">Cancel</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="bulkAddModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto relative">
         <button onclick="closeModal('bulkAddModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
         <h3 class="text-xl font-bold mb-1">Add Submenu Items</h3>
         <p class="text-sm text-gray-500 mb-6">Adding items to: <span id="bulk_parent_name" class="font-bold text-blue-600">Root</span></p>
         <form method="POST" enctype="multipart/form-data">
             <input type="hidden" name="action" value="add_bulk_menu_items">
             <input type="hidden" name="menu_id" value="<?php echo $selectedMenuId; ?>">
             <input type="hidden" name="parent_id" id="bulk_parent_id" value="">
             <div id="bulk_rows_container" class="space-y-3"></div>
             <div class="mt-4"><button type="button" onclick="addBulkRow()" class="text-blue-600 font-semibold text-sm hover:underline flex items-center"><i class="fas fa-plus-circle mr-2"></i> Add another line</button></div>
             <div class="mt-8 flex justify-end gap-3 border-t pt-4">
                 <button type="button" onclick="closeModal('bulkAddModal')" class="px-5 py-2 text-gray-600 font-medium hover:bg-gray-100 rounded">Cancel</button>
                 <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:bg-blue-700 shadow">Save Items</button>
             </div>
         </form>
    </div>
</div>

<!-- SortableJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script>
function addBulkRow() {
    const container = document.getElementById('bulk_rows_container');
    const index = container.children.length;
    const row = document.createElement('div');
    row.className = "flex gap-3 items-start bg-gray-50 p-3 rounded border border-gray-100 group relative";
    row.innerHTML = `
        <div class="flex-1">
            <label class="block text-xs font-bold text-gray-400 mb-1">Label</label>
            <input type="text" name="items[${index}][label]" placeholder="Name" class="w-full border p-2 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
        </div>
        <div class="flex-1">
            <label class="block text-xs font-bold text-gray-400 mb-1">Link</label>
            <input type="text" name="items[${index}][url]" placeholder="#" class="w-full border p-2 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div class="w-20">
             <label class="block text-xs font-bold text-gray-400 mb-1">Badge</label>
             <input type="text" name="items[${index}][badge]" placeholder="HOT" class="w-full border p-2 rounded text-sm">
        </div>
        <!-- <div class="w-16   ">
             <label class="block text-xs font-bold text-gray-400 mb-1">Order</label>
             <input type="number" name="items[${index}][order]" value="0" class="w-full border p-2 rounded text-sm">
        </div> -->
        <div class="flex-none pt-6">
             <label class="cursor-pointer text-gray-400 hover:text-blue-600 tooltip" title="Upload Icon/Image">
                 <i class="fas fa-image text-lg"></i>
                 <input type="file" name="items[${index}][image]" class="hidden">
             </label>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow text-gray-300 hover:text-red-500 hover:shadow-md transition"><i class="fas fa-times-circle"></i></button>
    `;
    container.appendChild(row);
}
function openAddModal(parentId, parentName) {
    document.getElementById('bulk_parent_id').value = parentId || '';
    document.getElementById('bulk_parent_name').innerText = parentName || 'Top Level';
    document.getElementById('bulk_rows_container').innerHTML = '';
    addBulkRow(); 
    document.getElementById('bulkAddModal').classList.remove('hidden');
}
function editItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_label').value = item.label;
    document.getElementById('edit_url').value = item.url;
    // document.getElementById('edit_sort_order').value = item.sort_order;
    document.getElementById('edit_parent_id').value = item.parent_id || '';
    document.getElementById('edit_badge_text').value = item.badge_text || '';
    document.getElementById('edit_custom_classes').value = item.custom_classes || '';
    
    // Handle current image display
    const currentImageContainer = document.getElementById('current_image_container');
    const currentImagePreview = document.getElementById('current_image_preview');
    const removeImageFlag = document.getElementById('remove_image_flag');
    const editImageInput = document.getElementById('edit_image_input');
    const newImagePreviewContainer = document.getElementById('new_image_preview_container');
    
    // Reset states
    removeImageFlag.value = '0';
    editImageInput.value = '';
    newImagePreviewContainer.classList.add('hidden');
    
    if (item.image_path) {
        // Show current image
        currentImagePreview.src = '<?php echo getBaseUrl(); ?>/assets/images/uploads/' + item.image_path;
        currentImageContainer.classList.remove('hidden');
    } else {
        // Hide current image container
        currentImageContainer.classList.add('hidden');
    }
    
    document.getElementById('editItemModal').classList.remove('hidden');
}

function removeCurrentImage() {
    document.getElementById('remove_image_flag').value = '1';
    document.getElementById('current_image_container').classList.add('hidden');
}

function previewNewImage(input) {
    const preview = document.getElementById('new_image_preview');
    const container = document.getElementById('new_image_preview_container');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
        
        // Hide current image when new one is selected
        document.getElementById('current_image_container').classList.add('hidden');
    }
}

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// Drag and Drop Logic for Hierarchical List
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Sortable on all text-lists (nested)
    const nestedSortables = [].slice.call(document.querySelectorAll('.sortable-list'));

    nestedSortables.forEach(function (el) {
        new Sortable(el, {
            group: 'nested', // allow dragging between lists
            handle: '.drag-handle',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: function (evt) {
                saveOrder();
            }
        });
    });
});

function saveOrder() {
    // Traverse the entire tree to build the order map
    // We need to support parent_id changing if moved to a child list
    const root = document.getElementById('menu-root');
    if (!root) return;
    
    const updates = {};
    
    // Recursive function to walk DOM
    function walk(container, parentId) {
        let index = 0;
        for (let child of container.children) {
            if (child.classList.contains('menu-item-container')) {
                const itemId = child.getAttribute('data-id');
                index++;
                
                updates[itemId] = {
                    sort_order: index,
                    parent_id: parentId
                };
                
                // Check for children container
                const subContainer = child.querySelector('.sortable-list');
                if (subContainer) {
                    walk(subContainer, itemId);
                }
            }
        }
    }
    
    walk(root, 0); // 0 = root parent
    
    // Send to PHP
    const formData = new FormData();
    formData.append('action', 'reorder_items_tree');
    formData.append('tree_data', JSON.stringify(updates));
    
    fetch('', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.status !== 'success') console.error('Save failed', data);
    })
    .catch(err => console.error(err));
}
</script>

<?php
function renderMenuItems($items) {
    foreach ($items as $item) {
        $hasImage = !empty($item['image_path']);
        ?>
        <div class="menu-item-container" data-id="<?php echo $item['id']; ?>">
            <!-- Item Row -->
            <div class="menu-item-row grid grid-cols-12 gap-4 px-4 py-3 border-b border-gray-100 hover:bg-gray-50 items-center group bg-white relative z-10 transition">
                <div class="col-span-6 flex items-center">
                    <span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 mr-3 px-1"><i class="fas fa-grip-vertical"></i></span>
                    <?php if($hasImage): ?>
                        <img src="<?php echo getImageUrl($item['image_path']); ?>" class="w-8 h-8 rounded object-cover mr-3 border border-gray-200">
                    <?php endif; ?>
                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($item['label']); ?></span>
                    
                     <?php 
                    $hasChildren = !empty($item['children']);
                    if ($hasChildren): 
                    ?>
                    <button class="ml-2 w-6 h-6 flex items-center justify-center text-gray-500 hover:text-black focus:outline-none transition-transform duration-200 toggle-btn" onclick="toggleChildren(event, this)" style="transform: rotate(-90deg);">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="col-span-4 text-gray-500 truncate text-sm">
                    <?php echo htmlspecialchars($item['url']); ?>
                </div>
                <div class="col-span-2 text-right">
                     <div class="flex justify-end gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                         <button onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="text-blue-600 hover:text-blue-800 text-xs font-semibold px-2 py-1 bg-blue-50 rounded">Edit</button>
                         <button onclick="openAddModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['label']); ?>')" class="text-green-600 hover:text-green-800 text-xs font-semibold px-2 py-1 bg-green-50 rounded whitespace-nowrap"><i class="fas fa-plus mr-1"></i> Add Sub</button>
                         <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="delete_menu_item">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1 bg-red-50 rounded">Delete</button>
                         </form>
                    </div>
                </div>
            </div>
            
            <!-- Children Container (Nested) -->
            <div class="children-container hidden sortable-list pl-8 border-l border-gray-100 ml-4 mb-2 <?php echo empty($item['children']) ? 'min-h-[5px]' : ''; ?>">
                <?php 
                if (!empty($item['children'])) {
                    renderMenuItems($item['children']);
                }
                ?>
            </div>
        </div>
        <?php
    }
}
?>
<!-- Rename/Edit Menu Modal -->
<div id="renameMenuModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="font-bold text-lg mb-4">Edit Menu</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename_menu">
            <input type="hidden" name="menu_id" id="rename_menu_id">
            
            <label class="block text-xs font-bold mb-1">Menu Name</label>
            <input type="text" name="menu_name" id="rename_menu_name" class="w-full border p-2 rounded mb-4 focus:ring-1 focus:ring-blue-500 outline-none" required>
            
            <label class="block text-xs font-bold mb-1">Assigned Location</label>
            <select name="location" id="rename_location" class="w-full border p-2 rounded mb-4 bg-white text-sm">
                <option value="">(None)</option>
                <?php foreach($locations as $key => $label): ?>
                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('renameMenuModal').classList.add('hidden')" class="px-4 py-2 text-gray-500">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRenameModal(id, name, location) {
    document.getElementById('rename_menu_id').value = id;
    document.getElementById('rename_menu_name').value = name;
    document.getElementById('rename_location').value = location || '';
    document.getElementById('renameMenuModal').classList.remove('hidden');
}

function toggleChildren(event, btn) {
    event.stopPropagation(); // Prevent drag/click interference
    // Find closest container
    const row = btn.closest('.menu-item-row');
    const container = row.nextElementSibling; // The children container
    const menuItemContainer = btn.closest('.menu-item-container');
    const itemId = menuItemContainer.getAttribute('data-id');
    
    if (container && container.classList.contains('children-container')) {
        const icon = btn.querySelector('i');
        // Toggle logic
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            // Rotate icon back to normal (down)
             btn.style.transform = 'rotate(0deg)';
             // Save expanded state to cookie (expires in 30 days)
             setCookie('menu_item_' + itemId, 'expanded', 30);
        } else {
            container.classList.add('hidden');
             // Rotate icon to side (e.g. -90deg)
            btn.style.transform = 'rotate(-90deg)';
            // Save collapsed state to cookie
            setCookie('menu_item_' + itemId, 'collapsed', 30);
        }
    }
}

// Cookie helper functions
function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
}

function getCookie(name) {
    const nameEQ = name + '=';
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

// Restore menu item states on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.menu-item-container').forEach(function(item) {
        const itemId = item.getAttribute('data-id');
        const state = getCookie('menu_item_' + itemId);
        
        if (state === 'expanded') {
            const container = item.querySelector('.children-container');
            const toggleBtn = item.querySelector('.toggle-btn');
            
            if (container && toggleBtn) {
                container.classList.remove('hidden');
                toggleBtn.style.transform = 'rotate(0deg)';
            }
        }
        // If state is 'collapsed' or null, keep default hidden state
    });
});
</script>
