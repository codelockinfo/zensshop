<?php
ob_start(); // Ensure no output before headers
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// PRG: Fetch Flash Messages
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Define Available Locations
$locations = [
    'header_main' => 'Header',
    'footer_main' => 'Footer'
];

// Fetch active categories for menu link selection
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
    $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
    $storeId = $storeUser['store_id'] ?? null;
}
$activeCategories = $db->fetchAll("SELECT name, slug FROM categories WHERE status='active' AND store_id = ? ORDER BY name ASC", [$storeId]);

// Fetch Linkable Resources for the Picker
$linkResources = [
    'system' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Shop All', 'url' => 'shop'],
        ['label' => 'My Account', 'url' => 'account'],
        ['label' => 'Wishlist', 'url' => 'wishlist'],
        ['label' => 'Shopping Cart', 'url' => 'cart'],
        ['label' => 'Checkout', 'url' => 'checkout'],
        ['label' => 'About Us', 'url' => 'about'], // Keeping here if not in DB
        ['label' => 'Contact Us', 'url' => 'support']
    ],
    'pages' => [],
    'products' => [],
    'categories' => [],
    'blogs' => [],
    'landing_pages' => []
];

// 1. Fetch CMS Pages (DB Only)
try {
    $dbPages = $db->fetchAll("SELECT title as label, slug FROM pages WHERE status='active' OR status='published'"); 
    if ($dbPages) {
        foreach($dbPages as $p) {
             $p['url'] = 'page?slug=' . $p['slug']; 
             $linkResources['pages'][] = $p;
        }
    }
} catch(Exception $e) {}

// 2. Products
try {
    $linkResources['products'] = $db->fetchAll("SELECT id, name as label, slug FROM products WHERE status='active' OR status IS NULL ORDER BY name ASC");
} catch(Exception $e) {}

// 3. Categories
try {
    $linkResources['categories'] = $db->fetchAll("SELECT id, name as label, slug FROM categories WHERE status='active' AND store_id = ? ORDER BY name ASC", [$storeId]);
} catch(Exception $e) {}

// 4. Blogs
try {
    $linkResources['blogs'] = $db->fetchAll("SELECT id, title as label, slug FROM blogs WHERE status='published' AND store_id = ? ORDER BY title ASC", [$storeId]);
} catch(Exception $e) {}

// 5. Landing Pages
try {
     // Table uses 'name' and has no 'status'/'store_id' columns by default
    $linkResources['landing_pages'] = $db->fetchAll("SELECT id, name as label, slug FROM landing_pages ORDER BY name ASC");
} catch(Exception $e) {}

foreach ($linkResources as $type => &$items) {
    foreach ($items as &$item) {
        $item['full_label'] = $item['label']; // Store full label for input filling
        if (isset($item['label']) && strlen($item['label']) > 45) { // Truncate for UI dropdown
            $item['label'] = substr($item['label'], 0, 45) . '...';
        }
    }
}
unset($items); // Break reference

// Helper to pass data to JS
$linkResourcesJson = json_encode($linkResources);


// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baseUrl = getBaseUrl();
    $action = $_POST['action'] ?? '';
    // Determine Store ID
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }

    $redirectUrl = $baseUrl . '/admin/menu'; // Use clean URL

    // 1. Create Menu
    if ($action === 'create_menu') {
        $name = trim($_POST['menu_name']);
        $location = !empty($_POST['location']) ? $_POST['location'] : null;
        if ($name) {
            try {
                if ($location) {
                    $db->execute("UPDATE menus SET location = NULL WHERE location = ? AND store_id = ?", [$location, $storeId]);
                }
                $db->insert("INSERT INTO menus (name, store_id, location) VALUES (?, ?, ?)", [$name, $storeId, $location]);
                $newId = $db->lastInsertId();
                $_SESSION['flash_success'] = "Menu created and assigned successfully.";
                $redirectUrl .= "?menu_id=" . $newId;
            } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); }
        }
    }
    
    // 2. Delete Menu
    elseif ($action === 'delete_menu') {
        $menuId = intval($_POST['menu_id_delete']);
        if($menuId) {
             $db->execute("DELETE FROM menus WHERE id = ? AND store_id = ?", [$menuId, $storeId]);
             $db->execute("DELETE FROM menu_items WHERE menu_id = ? AND store_id = ?", [$menuId, $storeId]); 
             $_SESSION['flash_success'] = "Menu deleted successfully.";
             // Redirect to base URL (will load first menu)
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
                    $db->execute("UPDATE menus SET location = NULL WHERE location = ? AND store_id = ?", [$location, $storeId]);
                }
                $db->execute("UPDATE menus SET name = ?, location = ? WHERE id = ? AND store_id = ?", [$newName, $location, $menuId, $storeId]);
                $_SESSION['flash_success'] = "Menu updated successfully.";
                $redirectUrl .= "?menu_id=" . $menuId;
            } catch (Exception $e) { $_SESSION['flash_error'] = $e->getMessage(); $redirectUrl .= "?menu_id=" . $menuId; }
        }
    }

    // 3. Add Single Item
    elseif ($action === 'add_menu_item') {
        $menuId = intval($_POST['menu_id']);
        $label = trim($_POST['label']);
        $url = preg_replace('/\.php(\?|$)/', '$1', trim($_POST['url']));
        $sortOrder = intval($_POST['sort_order'] ?? 0);
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
            $db->insert("INSERT INTO menu_items (menu_id, label, url, sort_order, parent_id, image_path, store_id) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                [$menuId, $label, $url, $sortOrder, $parentId, $imagePath, $storeId]);
            $_SESSION['flash_success'] = "Item added successfully.";
            $redirectUrl .= "?menu_id=" . $menuId;
        }
    }

    // 4. Update Item
    elseif ($action === 'update_menu_item') {
        $itemId = intval($_POST['item_id']);
        $menuId = intval($_POST['menu_id']);
        $label = trim($_POST['label']);
        $url = preg_replace('/\.php(\?|$)/', '$1', trim($_POST['url']));
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $badgeText = trim($_POST['badge_text'] ?? '');
        $customClasses = trim($_POST['custom_classes'] ?? '');
        $isMegaMenu = isset($_POST['is_mega_menu']) && $_POST['is_mega_menu'] == '1' ? 1 : 0;
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
        
        $imageSql = "";
        $params = [$label, $url, $sortOrder, $parentId, $badgeText, $customClasses, $isMegaMenu];
        
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

        $db->execute("UPDATE menu_items SET label=?, url=?, sort_order=?, parent_id=?, badge_text=?, custom_classes=?, is_mega_menu=? $imageSql WHERE id=?", $params);
        $_SESSION['flash_success'] = "Item updated successfully.";
        $redirectUrl .= "?menu_id=" . $menuId;
    }

    // 5. Delete Item
    elseif ($action === 'delete_menu_item') {
        $itemId = intval($_POST['item_id']);
        $menuId = intval($_POST['menu_id']); // Ensure we pass this in form
        $db->execute("DELETE FROM menu_items WHERE id = ? AND store_id = ?", [$itemId, $storeId]);
        $_SESSION['flash_success'] = "Item deleted successfully.";
        $redirectUrl .= "?menu_id=" . $menuId;
    }

    // 7. Toggle Handle (AJAX) - No redirect needed, just exit
    elseif ($action === 'toggle_handle') {
         // ... (Logic kept SAME, but added JSON header)
         ob_end_clean();
         header('Content-Type: application/json');
         $menuId = intval($_POST['menu_id']);
         $target = $_POST['handle'];
         try {
             // First, remove this location from any other menu of THIS STORE
             $db->execute("UPDATE menus SET location = NULL WHERE location = ? AND store_id = ?", [$target, $storeId]); 
             // Then set it for the requested menu
             $db->execute("UPDATE menus SET location = ? WHERE id = ? AND store_id = ?", [$target, $menuId, $storeId]); // set new
             echo json_encode(['status'=>'success']); 
         } catch(Exception $e) { 
             echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); 
         }
         exit;
    }

    // 6. Bulk Add Subitems
    elseif ($action === 'add_bulk_menu_items') {
        $menuId = intval($_POST['menu_id']);
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $items = $_POST['items'] ?? [];
        
        $cnt = 0;
        foreach ($items as $idx => $item) {
            $lbl = trim($item['label']);
            $lnk = preg_replace('/\.php(\?|$)/', '$1', trim($item['url']));
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

            $db->insert("INSERT INTO menu_items (menu_id, label, url, parent_id, image_path, sort_order, badge_text, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 
                [$menuId, $lbl, $lnk, $parentId, $imgPath, $order, $badge, $storeId]);
            $cnt++;
        }
        if($cnt > 0) $_SESSION['flash_success'] = "$cnt items added successfully.";
        $redirectUrl .= "?menu_id=" . $menuId;
    }

    // 8. Reorder Items (Legacy) - AJAX
    elseif ($action === 'reorder_items') {
        ob_end_clean();
        header('Content-Type: application/json');
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
        }
        exit;
    }
    
    // 9. Reorder Items (Tree) - AJAX
    elseif ($action === 'reorder_items_tree') {
        ob_end_clean();
        header('Content-Type: application/json');
         $treeData = json_decode($_POST['tree_data'] ?? '[]', true);
        if (!empty($treeData)) {
            try {
                $db->beginTransaction();
                foreach ($treeData as $id => $data) {
                    $order = intval($data['sort_order']);
                    $parentId = intval($data['parent_id']);
                    if ($parentId === 0) $parentId = null;
                    $db->execute("UPDATE menu_items SET sort_order = ?, parent_id = ? WHERE id = ?", [$order, $parentId, intval($id)]);
                }
                $db->commit();
                echo json_encode(['status'=>'success']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
            }
        }
        exit;
    }
    
    // Perform Redirect for standard POSTs
    header("Location: " . $redirectUrl);
    exit;
}

// Init Data
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
// Sort by ID ASC ensures created order (Header first, then Footer)
$menus = $db->fetchAll("SELECT * FROM menus WHERE store_id = ? ORDER BY id ASC", [$storeId]);
$selectedMenuId = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : ($menus[0]['id'] ?? 0);
// If ID not found, default to first?
if (!in_array($selectedMenuId, array_column($menus, 'id')) && !empty($menus)) {
    $selectedMenuId = $menus[0]['id'];
}

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
    <div class="w-64 bg-white border-r border-gray-200 flex flex-col z-10 hidden md:flex">
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
                 <form method="POST" class="inline-block" id="deleteMenuForm">
                     <input type="hidden" name="action" value="delete_menu">
                     <input type="hidden" name="menu_id_delete" value="<?php echo $selectedMenuId; ?>">
                     <button type="button" class="text-red-500 hover:text-red-700 text-sm font-medium" onclick="showConfirmModal('Are you sure you want to delete this menu and all its items?', function(){ 
                         const fm = document.getElementById('deleteMenuForm');
                         if (typeof fm.requestSubmit === 'function') {
                             fm.requestSubmit();
                         } else {
                             fm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                         }
                     })">Delete Menu</button>
                 </form>
                 <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-8 relative">

            <!-- Flash Messages -->
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
        </main>
    </div>
</div>

<!-- Create Menu Modal -->
<div id="createMenuModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="font-bold text-lg mb-4">Create New Menu</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_menu">
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Menu Name</label>
                <input type="text" name="menu_name" placeholder="e.g. Main Header" class="w-full border p-3 rounded-xl bg-gray-50 focus:bg-white outline-none" required>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Assign to Location (Optional)</label>
                <select name="location" class="w-full border p-3 rounded-xl bg-gray-50 focus:bg-white outline-none">
                    <option value="">None (Hidden)</option>
                    <?php foreach($locations as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('createMenuModal').classList.add('hidden')" class="px-4 py-2 text-gray-500 font-medium">Cancel</button>
                <button type="submit" class="bg-black text-white px-6 py-2 rounded-xl font-bold shadow-lg">Create Menu</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Menu Modal -->
<div id="renameMenuModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-96 shadow-2xl">
        <h3 class="font-bold text-xl mb-4 text-gray-800">Menu Settings</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename_menu">
            <input type="hidden" name="menu_id" id="rename_menu_id">
            
            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Menu Name</label>
                <input type="text" name="menu_name" id="rename_menu_name" placeholder="Menu Name" 
                       class="w-full border border-gray-100 bg-gray-50 p-3 rounded-xl focus:bg-white focus:ring-2 focus:ring-black outline-none transition" required>
            </div>
            
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Display Location</label>
                <select name="location" id="rename_location" 
                        class="w-full border border-gray-100 bg-gray-50 p-3 rounded-xl focus:bg-white focus:ring-2 focus:ring-black outline-none transition">
                    <option value="">(Not Assigned - Will not show on site)</option>
                    <?php foreach($locations as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-400 mt-2 italic flex items-start gap-1">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <span>This menu will appear in the selected location. Assigning a location will unassign any other menu currently in that spot.</span>
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeModal('renameMenuModal')" class="px-5 py-2.5 text-gray-500 font-medium hover:bg-gray-50 rounded-xl transition">Cancel</button>
                <button type="submit" class="bg-black text-white px-8 py-2.5 rounded-xl font-bold shadow-lg shadow-gray-200 hover:scale-105 active:scale-95 transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editItemModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] flex flex-col shadow-xl">
        
        <!-- Header -->
        <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
            <h3 class="font-bold text-lg text-gray-800">Edit Item</h3>
            <button onclick="closeModal('editItemModal')" class="text-gray-400 hover:text-gray-600 transition p-1">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <form id="editItemForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_menu_item">
                <input type="hidden" name="item_id" id="edit_item_id">
                <input type="hidden" name="menu_id" value="<?php echo $selectedMenuId; ?>">
                
                <div class="grid grid-cols-2 gap-4">
                    <!-- Resource Picker -->
                    <div class="col-span-2 bg-blue-50 p-3 rounded border border-blue-100">
                        <label class="block text-[10px] font-bold mb-2 text-blue-800 uppercase tracking-widest">Link Destination</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Type</label>
                                <select id="edit_link_type" onchange="updateLinkPicker(this, 'edit_link_item', 'edit_url', 'edit_label')" class="w-full border p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white">
                                    <option value="">-- Select Type --</option>
                                    <option value="system">System Link</option>
                                    <option value="pages">Store Page</option>
                                    <option value="products">Product</option>
                                    <option value="categories">Category</option>
                                    <option value="blogs">Blog Post</option>
                                    <option value="landing_pages">Special Page</option>
                                    <option value="custom">Custom URL</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Item</label>
                                <select id="edit_link_item" onchange="applyLinkSelection(this, 'edit_url', 'edit_label')" class="w-full border p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white disabled:bg-gray-100 disabled:text-gray-400" disabled>
                                    <option value="">-- Select Reference --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-bold mb-1 text-gray-600">Label</label>
                        <input type="text" name="label" id="edit_label" class="w-full border p-2 rounded text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none" required>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-bold mb-1 text-gray-600">URL</label>
                        <input type="text" name="url" id="edit_url" class="w-full border p-2 rounded text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>

                    <div class="col-span-1">
                        <label class="block text-xs font-bold mb-1 text-gray-600">Badge (e.g. HOT)</label>
                        <input type="text" name="badge_text" id="edit_badge_text" class="w-full border p-2 rounded text-sm hover:border-gray-400 focus:border-blue-500 outline-none" placeholder="New, Hot, etc.">
                    </div>

                    <div class="col-span-1">
                        <label class="block text-xs font-bold mb-1 text-gray-600">Parent Item</label>
                        <select name="parent_id" id="edit_parent_id" class="w-full border p-2 rounded text-sm bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="">(None - Top Level)</option>
                            <?php foreach($allItemsFlat as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-bold mb-1 text-gray-600">Custom CSS Classes</label>
                        <input type="text" name="custom_classes" id="edit_custom_classes" class="w-full border p-2 rounded text-sm font-mono text-xs focus:border-blue-500 outline-none" placeholder="text-red-500 font-bold">
                    </div>
                    
                    <div class="col-span-2 pt-2 pb-2">
                         <label class="flex items-center gap-3 cursor-pointer group">
                             <input type="checkbox" name="is_mega_menu" id="edit_is_mega_menu" value="1" class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500" onchange="toggleImageSection(this.checked)">
                             <div>
                                 <span class="text-sm font-bold text-gray-700 group-hover:text-blue-600 transition">Enable Mega Menu</span>
                                 <p class="text-[10px] text-gray-500">Show sub-items with images in a grid layout.</p>
                             </div>
                         </label>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="col-span-2 hidden" id="edit_image_section">
                        <label class="block text-xs font-bold mb-2 text-gray-600">Menu Image (Optional)</label>
                        <div class="relative w-full h-32 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-400 hover:bg-blue-50 transition cursor-pointer flex flex-col items-center justify-center overflow-hidden group"
                             onclick="document.getElementById('edit_image_input').click()">
                            
                            <input type="file" name="image" id="edit_image_input" class="hidden" accept="image/*" onchange="previewNewImage(this)">
                            <input type="hidden" name="remove_image" id="remove_image_flag" value="0">
                            
                            <!-- Preview Image -->
                            <img id="image_preview_box" src="" class="absolute inset-0 w-full h-full object-contain bg-gray-50 hidden p-2">
                            
                            <!-- Placeholder -->
                            <div id="image_upload_placeholder" class="text-center p-4">
                                <i class="fas fa-image text-2xl text-gray-400 mb-2 group-hover:text-blue-400 transition"></i>
                                <p class="text-xs text-gray-500 group-hover:text-blue-600">Click to upload image</p>
                            </div>
                            
                            <!-- Edit Overlay -->
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition pointer-events-none"></div>

                            <!-- Remove Button -->
                            <button type="button" id="btn_remove_image" onclick="removeCurrentImage(event)" class="hidden absolute top-2 right-2 bg-white text-red-500 rounded-full w-8 h-8 shadow hover:bg-red-50 hover:text-red-700 z-20 flex items-center justify-center transition" title="Remove Image">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t bg-gray-50 rounded-b-lg flex justify-end gap-3">
            <button type="button" onclick="closeModal('editItemModal')" class="px-5 py-2 text-gray-600 font-medium hover:bg-gray-200 rounded-lg transition text-sm">Cancel</button>
            <button type="submit" form="editItemForm" class="bg-black text-white px-6 py-2 rounded-lg font-bold shadow hover:bg-gray-800 transition text-sm btn-loading">Update Item</button>
        </div>
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
                 <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:bg-blue-700 shadow btn-loading">Save Items</button>
             </div>
         </form>
    </div>
</div>

<!-- SortableJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script>
window.initMenuSettings = function() {
    var LINK_RESOURCES = <?php echo $linkResourcesJson; ?>;

    window.getResourceUrl = function(type, item) {
        if (!item) return '';
        if (type === 'system') return item.url;
        if (type === 'pages') return item.url;
        if (type === 'products') return 'product?slug=' + item.slug;
        if (type === 'categories') return 'shop?category=' + item.slug;
        if (type === 'blogs') return 'blog?slug=' + item.slug;
        if (type === 'landing_pages') return 'landing?slug=' + item.slug;
        return '';
    };

    window.updateLinkPicker = function(typeSelect, itemSelectId, urlInputId, labelInputId) {
        var type = typeSelect.value;
        var itemSelect = document.getElementById(itemSelectId);
        if (!itemSelect) return;
        
        itemSelect.innerHTML = '<option value="">-- Select Item --</option>';
        itemSelect.disabled = true;
        
        if (type === 'custom') return;

        if (LINK_RESOURCES[type] && LINK_RESOURCES[type].length > 0) {
            LINK_RESOURCES[type].forEach(item => {
                var opt = document.createElement('option');
                opt.value = JSON.stringify(item);
                opt.textContent = item.label;
                itemSelect.appendChild(opt);
            });
            itemSelect.disabled = false;
        } else if (type) {
            itemSelect.innerHTML = '<option value="">No items found</option>';
        }
    };

    window.applyLinkSelection = function(itemSelect, urlInputId, labelInputId) {
        if (!itemSelect.value) return;
        var typeSelectId = itemSelect.id.replace('item', 'type');
        var typeSelect = document.getElementById(typeSelectId);
        var type = typeSelect ? typeSelect.value : '';
        
        try {
            var item = JSON.parse(itemSelect.value);
            var url = window.getResourceUrl(type, item);
            if (url) document.getElementById(urlInputId).value = url;
            if (labelInputId) document.getElementById(labelInputId).value = item.full_label || item.label;
        } catch(e) { console.error("Error parsing selection", e); }
    };

    window.addBulkRow = function() {
        var container = document.getElementById('bulk_rows_container');
        if (!container) return;
        var index = container.children.length;
        var row = document.createElement('div');
        row.className = "flex gap-3 items-start bg-gray-50 p-3 rounded border border-gray-100 group relative";
        row.innerHTML = `
            <div class="flex-none w-64">
                <label class="block text-xs font-bold text-gray-400 mb-1">Quick Link</label>
                <div class="grid grid-cols-2 gap-1">
                    <select id="bulk_type_${index}" onchange="window.updateLinkPicker(this, 'bulk_item_${index}', 'bulk_url_${index}', 'bulk_label_${index}')" class="w-full border p-1 rounded text-xs outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Type</option>
                        <option value="system">System Link</option>
                        <option value="pages">Store Page</option>
                        <option value="products">Product</option>
                        <option value="categories">Category</option>
                        <option value="blogs">Blog</option>
                        <option value="landing_pages">Landing Page</option>
                        <option value="custom">URL</option>
                    </select>
                    <select id="bulk_item_${index}" onchange="window.applyLinkSelection(this, 'bulk_url_${index}', 'bulk_label_${index}')" class="w-full border p-1 rounded text-xs outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-100" disabled>
                        <option value="">Item</option>
                    </select>
                </div>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-400 mb-1">Label</label>
                <input type="text" name="items[${index}][label]" id="bulk_label_${index}" placeholder="Name" class="w-full border p-2 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-400 mb-1">Link</label>
                <input type="text" name="items[${index}][url]" id="bulk_url_${index}" placeholder="#" class="w-full border p-2 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div class="w-20">
                 <label class="block text-xs font-bold text-gray-400 mb-1">Badge</label>
                 <input type="text" name="items[${index}][badge]" placeholder="HOT" class="w-full border p-2 rounded text-sm">
            </div>
            <div class="flex-none pt-6">
                 <label class="cursor-pointer text-gray-400 hover:text-blue-600 tooltip" title="Upload Icon/Image">
                     <i class="fas fa-image text-lg"></i>
                     <input type="file" name="items[${index}][image]" class="hidden">
                 </label>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow text-gray-300 hover:text-red-500 hover:shadow-md transition"><i class="fas fa-times-circle"></i></button>
        `;
        container.appendChild(row);
    };

    window.openAddModal = function(parentId, parentName) {
        document.getElementById('bulk_parent_id').value = parentId || '';
        document.getElementById('bulk_parent_name').innerText = parentName || 'Top Level';
        document.getElementById('bulk_rows_container').innerHTML = '';
        window.addBulkRow(); 
        document.getElementById('bulkAddModal').classList.remove('hidden');
    };

    window.editItem = function(item) {
        document.getElementById('edit_item_id').value = item.id;
        document.getElementById('edit_label').value = item.label;
        document.getElementById('edit_url').value = item.url;
        document.getElementById('edit_parent_id').value = item.parent_id || '';
        document.getElementById('edit_badge_text').value = item.badge_text || '';
        document.getElementById('edit_custom_classes').value = item.custom_classes || '';
        document.getElementById('edit_is_mega_menu').checked = (item.is_mega_menu == 1);
        window.toggleImageSection(item.is_mega_menu == 1);
        
        var previewBox = document.getElementById('image_preview_box');
        var placeholder = document.getElementById('image_upload_placeholder');
        var removeBtn = document.getElementById('btn_remove_image');
        var removeImageFlag = document.getElementById('remove_image_flag');
        var editImageInput = document.getElementById('edit_image_input');
        
        if (removeImageFlag) removeImageFlag.value = '0';
        if (editImageInput) editImageInput.value = '';
        
        if (item.image_path) {
            if (previewBox) {
                previewBox.src = '<?php echo getBaseUrl(); ?>/assets/images/uploads/' + item.image_path;
                previewBox.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');
            if (removeBtn) removeBtn.classList.remove('hidden');
        } else {
            if (previewBox) {
                previewBox.src = '';
                previewBox.classList.add('hidden');
            }
            if (placeholder) placeholder.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.add('hidden');
        }
        document.getElementById('editItemModal').classList.remove('hidden');
    };

    window.toggleImageSection = function(show) {
        var section = document.getElementById('edit_image_section');
        if (section) {
            if (show) section.classList.remove('hidden');
            else section.classList.add('hidden');
        }
    };

    window.removeCurrentImage = function(e) {
        if(e) e.stopPropagation();
        document.getElementById('remove_image_flag').value = '1';
        document.getElementById('image_preview_box').src = '';
        document.getElementById('image_preview_box').classList.add('hidden');
        document.getElementById('image_upload_placeholder').classList.remove('hidden');
        document.getElementById('btn_remove_image').classList.add('hidden');
        document.getElementById('edit_image_input').value = '';
    };

    window.previewNewImage = function(input) {
        var preview = document.getElementById('image_preview_box');
        var placeholder = document.getElementById('image_upload_placeholder');
        var removeBtn = document.getElementById('btn_remove_image');
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                if (placeholder) placeholder.classList.add('hidden');
                if (removeBtn) removeBtn.classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
            document.getElementById('remove_image_flag').value = '0';
        }
    };

    window.closeModal = function(id) { 
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('hidden'); 
    };

    window.initMenuSortables = function() {
        var root = document.getElementById('menu-root');
        if (!root || root.hasAttribute('data-sortable-initialized')) return;
        var nestedSortables = [].slice.call(document.querySelectorAll('.sortable-list'));
        if (typeof Sortable !== 'undefined') {
            nestedSortables.forEach(function (el) {
                new Sortable(el, {
                    group: 'nested', 
                    handle: '.drag-handle',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    onEnd: function (evt) {
                        window.saveMenuOrder();
                    }
                });
            });
            root.setAttribute('data-sortable-initialized', 'true');
        }
    };

    window.saveMenuOrder = function() {
        var root = document.getElementById('menu-root');
        if (!root) return;
        var updates = {};
        function walk(container, parentId) {
            var index = 0;
            for (var child of container.children) {
                if (child.classList.contains('menu-item-container')) {
                    var itemId = child.getAttribute('data-id');
                    index++;
                    updates[itemId] = { sort_order: index, parent_id: parentId };
                    var subContainer = child.querySelector('.sortable-list');
                    if (subContainer) {
                        walk(subContainer, itemId);
                    }
                }
            }
        }
        walk(root, 0); 
        var formData = new FormData();
        formData.append('action', 'reorder_items_tree');
        formData.append('tree_data', JSON.stringify(updates));
        fetch('', { method: 'POST', body: formData });
    };

    window.openRenameModal = function(id, name, location) {
        document.getElementById('rename_menu_id').value = id;
        document.getElementById('rename_menu_name').value = name;
        document.getElementById('rename_location').value = location || '';
        document.getElementById('renameMenuModal').classList.remove('hidden');
    };

    window.toggleChildren = function(event, btn) {
        if (event) event.stopPropagation();
        var row = btn.closest('.menu-item-row');
        var container = row ? row.nextElementSibling : null;
        var menuItemContainer = btn.closest('.menu-item-container');
        var itemId = menuItemContainer ? menuItemContainer.getAttribute('data-id') : null;
        
        if (container && container.classList.contains('children-container')) {
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                btn.style.transform = 'rotate(0deg)';
                if (itemId) window.setMenuCookie('menu_item_' + itemId, 'expanded', 30);
            } else {
                container.classList.add('hidden');
                btn.style.transform = 'rotate(-90deg)';
                if (itemId) window.setMenuCookie('menu_item_' + itemId, 'collapsed', 30);
            }
        }
    };

    window.setMenuCookie = function(name, value, days) {
        var expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    };

    window.getMenuCookie = function(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    };

    window.initMenuStates = function() {
        var containers = document.querySelectorAll('.menu-item-container');
        containers.forEach(function(item) {
            if (item.hasAttribute('data-state-initialized')) return;
            var itemId = item.getAttribute('data-id');
            var state = window.getMenuCookie('menu_item_' + itemId);
            if (state === 'expanded') {
                var container = item.querySelector('.children-container');
                var toggleBtn = item.querySelector('.toggle-btn');
                if (container && toggleBtn) {
                    container.classList.remove('hidden');
                    toggleBtn.style.transform = 'rotate(0deg)';
                }
            }
            item.setAttribute('data-state-initialized', 'true');
        });
    };

    window.initMenuSortables();
    window.initMenuStates();
};

window.initMenuSettings();
</script>

<?php
function renderMenuItems($items) {
    // ... Function body same as before, but ensure delete/edit forms include needed hidden fields?
    // Edit passes item object to js editItem(), which uses form. Form HAS menu_id.
    // Delete form (Line 626 of previous) needs menu_id.
    // I can get menu_id from the Item's data or pass it in.
    // Actually, `renderMenuItems` can access global $selectedMenuId? No, scope issues.
    // But `delete_menu_item` action handling (Line 137 in new code) fetches `menuId` from POST.
    // So the delete form MUST include it.
    foreach ($items as $item) {
        $hasImage = !empty($item['image_path']);
        ?>
        <div class="menu-item-container" data-id="<?php echo $item['id']; ?>">
            <div class="menu-item-row grid grid-cols-12 gap-4 px-4 py-3 border-b border-gray-100 hover:bg-gray-50 items-center group bg-white relative z-10 transition">
                <div class="col-span-6 flex items-center">
                    <span class="drag-handle cursor-move text-gray-400 hover:text-gray-600 mr-3 px-1"><i class="fas fa-grip-vertical"></i></span>
                    <?php if($hasImage): ?>
                        <img src="<?php echo getImageUrl($item['image_path']); ?>" class="w-8 h-8 rounded object-cover mr-3 border border-gray-200">
                    <?php endif; ?>
                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($item['label']); ?></span>
                    <?php if (!empty($item['children'])): ?>
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
                            <input type="hidden" name="menu_id" value="<?php echo $item['menu_id']; ?>"> 
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1 bg-red-50 rounded">Delete</button>
                         </form>
                    </div>
                </div>
            </div>
            
            <div class="children-container hidden sortable-list pl-8 border-l border-gray-100 ml-4 mb-2 <?php echo empty($item['children']) ? 'min-h-[5px]' : ''; ?>">
                <?php if (!empty($item['children'])) renderMenuItems($item['children']); ?>
            </div>
        </div>
        <?php
    }
}
?>
<!-- Other Modals (Rename/Edit) kept as is in JS section above -->

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
