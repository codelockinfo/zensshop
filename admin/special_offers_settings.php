<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        // Save Heading/Subheading to JSON
        $heading = $_POST['heading'] ?? '';
        $subheading = $_POST['subheading'] ?? '';
        $show_section = isset($_POST['show_section']) ? true : false;
        
        $offersConfig = [
            'heading' => $heading,
            'subheading' => $subheading,
            'show_section' => $show_section
        ];
        file_put_contents(__DIR__ . '/special_offers_config.json', json_encode($offersConfig));
        
        // Also update existing rows in DB for backward compatibility
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }
        $db->execute("UPDATE special_offers SET heading = ?, subheading = ? WHERE store_id = ?", [$heading, $subheading, $storeId]);
        
        $_SESSION['flash_success'] = "Section settings updated!";
        header("Location: " . $baseUrl . '/admin/offers');
        exit;
    }
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }
        $db->execute("DELETE FROM special_offers WHERE id = ? AND store_id = ?", [$id, $storeId]);
        $_SESSION['flash_success'] = "Offer deleted successfully!";
        header("Location: " . $baseUrl . '/admin/offers');
        exit;
    }
    elseif ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $title = $_POST['title'] ?? '';
        $link = preg_replace('/\.php(\?|$)/', '$1', $_POST['link'] ?? '');
        $button_text = $_POST['button_text'] ?? 'Shop Now';
        $image = '';
        $display_order = (int)($_POST['display_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Handle Image Upload
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/images/special_offers/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $image = 'assets/images/special_offers/' . $filename;
            } else {
                $error = "Failed to upload image.";
            }
        }
        
        if (empty($error)) {
            if ($id) {
                // Fetch current heading/subheading to preserve them
                $storeId = $_SESSION['store_id'] ?? null;
                if (!$storeId && isset($_SESSION['user_email'])) {
                     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                     $storeId = $storeUser['store_id'] ?? null;
                }
                $current = $db->fetchOne("SELECT heading, subheading FROM special_offers WHERE store_id = ? LIMIT 1", [$storeId]);
                $h = $current['heading'] ?? null;
                $s = $current['subheading'] ?? null;

                // Update existing
                $sql = "UPDATE special_offers SET title = ?, link = ?, button_text = ?, display_order = ?, active = ?";
                $params = [$title, $link, $button_text, $display_order, $active];
                
                if ($image) {
                    $sql .= ", image = ?";
                    $params[] = $image;
                }
                
                // Preserve heading
                if ($h !== null) {
                    $sql .= ", heading = ?, subheading = ?";
                    $params[] = $h;
                    $params[] = $s;
                }

                $sql .= " WHERE id = ? AND store_id = ?";
                $params[] = $id;
                $params[] = $storeId;
                
                $db->execute($sql, $params);
                $_SESSION['flash_success'] = "Offer updated successfully!";
            } else {
                // Insert new
                if (empty($image) && empty($_FILES['image']['name'])) {
                    $error = "Image is required for new offers.";
                } else {
                    // Fetch existing settings to apply to new row
                    if (!$storeId && isset($_SESSION['user_email'])) {
                         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                         $storeId = $storeUser['store_id'] ?? null;
                    }
                    $current = $db->fetchOne("SELECT heading, subheading FROM special_offers WHERE store_id = ? LIMIT 1", [$storeId]);
                    $h = $current['heading'] ?? 'Special Offers';
                    $s = $current['subheading'] ?? 'Grab limited-time deals on our best products.';

                    // Determine Store ID
                    if (!$storeId && isset($_SESSION['user_email'])) {
                         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                         $storeId = $storeUser['store_id'] ?? null;
                    }

                    $db->execute(
                        "INSERT INTO special_offers (title, link, button_text, image, display_order, active, heading, subheading, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$title, $link, $button_text, $image, $display_order, $active, $h, $s, $storeId]
                    );
                    $_SESSION['flash_success'] = "Offer added successfully!";
                }
            }
            
            if (empty($error)) {
                header("Location: " . $baseUrl . '/admin/offers');
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

$pageTitle = 'Special Offers Settings';
require_once __DIR__ . '/../includes/admin-header.php';

// Fetch Section Settings
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Prepare values (JSON is master)
$savedHeading = 'Special Offers';
$savedSubheading = 'Grab limited-time deals on our best products.';

$offersConfigPath = __DIR__ . '/special_offers_config.json';
$savedConfig = null;
$showSection = true;
if (file_exists($offersConfigPath)) {
    $savedConfig = json_decode(file_get_contents($offersConfigPath), true);
    $showSection = isset($savedConfig['show_section']) ? $savedConfig['show_section'] : true;
}

$sectionSettings = $db->fetchOne("SELECT heading, subheading FROM special_offers WHERE store_id = ? LIMIT 1", [$storeId]);

if ($savedConfig !== null) {
    $current_heading = $savedConfig['heading'] ?? ($sectionSettings['heading'] ?? $savedHeading);
    $current_subheading = $savedConfig['subheading'] ?? ($sectionSettings['subheading'] ?? $savedSubheading);
} else {
    $current_heading = $sectionSettings['heading'] ?? $savedHeading;
    $current_subheading = $sectionSettings['subheading'] ?? $savedSubheading;
}

$sectionSettings = [
    'heading' => $current_heading,
    'subheading' => $current_subheading
];

// Fetch Offers
$offers = $db->fetchAll("SELECT * FROM special_offers WHERE store_id = ? ORDER BY display_order ASC, created_at DESC", [$storeId]);
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Special Offers Settings</h1>
        <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i> Add New Offer
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

    <!-- Section Settings Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8 transform transition hover:shadow-md">
        <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Section Configuration</h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <input type="hidden" name="action" value="save_settings">
            
            <div class="col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-2">Section Heading</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-heading"></i>
                    </span>
                    <input type="text" name="heading" value="<?php echo htmlspecialchars($sectionSettings['heading'] ?? ''); ?>" 
                           class="w-full pl-10 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" 
                           placeholder="e.g. Special Offers">
                </div>
            </div>
            
            <div class="col-span-1">
                <label class="block text-sm font-bold text-gray-700 mb-2">Section Subheading</label>
                 <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-align-left"></i>
                    </span>
                    <input type="text" name="subheading" value="<?php echo htmlspecialchars($sectionSettings['subheading'] ?? ''); ?>" 
                           class="w-full pl-10 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" 
                           placeholder="e.g. Exclusive deals just for you">
                </div>
            </div>
            
            <div class="col-span-1 md:col-span-2 border-t pt-4 mt-2">
                 <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><i class="fas fa-eye"></i></div>
                        <div>
                            <h3 class="font-bold text-gray-700">Show Special Offers Section</h3>
                            <p class="text-xs text-gray-500">Toggle visibility of this section on the homepage.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="show_section" class="sr-only peer" <?php echo $showSection ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
            
            <div class="col-span-1 md:col-span-2 flex justify-end mt-2">
                <button type="submit" class="bg-gray-800 text-white px-6 py-2.5 rounded-lg hover:bg-black transition flex items-center shadow-lg btn-loading">
                    <i class="fas fa-save mr-2"></i> Update Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Offers List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($offers as $index => $offer): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <img src="<?php echo preg_match('/^https?:\/\//', $offer['image']) ? $offer['image'] : $baseUrl . '/' . $offer['image']; ?>" class="h-16 w-32 object-cover rounded" alt="Offer">
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($offer['title']); ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo htmlspecialchars($offer['link']); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo $offer['display_order']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $offer['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $offer['active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick='editOffer(allOffers[<?php echo $index; ?>])' class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $offer['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($offers)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No offers found. Add one to get started.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="offerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold" id="modalTitle">Add New Offer</h3>
            <button onclick="closeModal()" class="text-gray-600 hover:text-gray-800">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="offerForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="offerId">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Title</label>
                    <input type="text" name="title" id="offerTitle" class="w-full border p-2 rounded">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Button Text</label>
                    <input type="text" name="button_text" id="offerButtonText" value="Shop Now" class="w-full border p-2 rounded">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Link</label>
                    <input type="text" name="link" id="offerLink" class="w-full border p-2 rounded" placeholder="e.g. /shop or https://google.com">
                </div>
                
                <div>
                    <label class="block text-sm font-bold mb-2">Display Order</label>
                    <input type="number" name="display_order" id="offerOrder" value="0" class="w-full border p-2 rounded">
                </div>
                
                <div class="flex items-center mt-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="active" id="offerActive" value="1" checked class="form-checkbox h-5 w-5 text-blue-600">
                        <span class="ml-2 text-gray-700">Active</span>
                    </label>
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-bold mb-2">Image (Required)</label>
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[160px] hover:bg-gray-100 transition" onclick="document.getElementById('imageInput').click()">
                        <img id="previewImg" src="" class="max-h-32 w-auto object-contain hidden" alt="Preview">
                        <div id="placeholderImg" class="text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500 font-semibold">Click to upload Image</p>
                            <p class="text-xs text-gray-400 mt-1">Recommended: 600x400px</p>
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 rounded-lg transition-all"></div>
                    </div>
                    <input type="file" name="image" id="imageInput" accept="image/*" class="hidden" onchange="previewImage(this, 'previewImg', 'placeholderImg')">
                </div>
            </div>
            
            <div class="flex justify-end gap-2 text-right">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 btn-loading">Save Offer</button>
            </div>
        </form>
    </div>
</div>

<script>
const allOffers = <?php echo json_encode($offers); ?>;
const baseUrl = '<?php echo $baseUrl; ?>';

function openModal() {
    document.getElementById('offerForm').reset();
    document.getElementById('offerId').value = '';
    document.getElementById('modalTitle').innerText = 'Add New Offer';
    
    // Reset Preview
    document.getElementById('previewImg').src = '';
    document.getElementById('previewImg').classList.add('hidden');
    document.getElementById('placeholderImg').classList.remove('hidden');
    
    document.getElementById('offerModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('offerModal').classList.add('hidden');
}

function editOffer(offer) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Offer';
    document.getElementById('offerId').value = offer.id;
    document.getElementById('offerTitle').value = offer.title;
    document.getElementById('offerButtonText').value = offer.button_text;
    document.getElementById('offerLink').value = offer.link;
    document.getElementById('offerOrder').value = offer.display_order;
    document.getElementById('offerActive').checked = offer.active == 1;
    
    if (offer.image) {
        const img = document.getElementById('previewImg');
        const placeholder = document.getElementById('placeholderImg');
        
        let src = offer.image;
        if (!src.startsWith('http')) {
            src = baseUrl + '/' + src;
        }
        
        img.src = src;
        img.classList.remove('hidden');
        placeholder.classList.add('hidden');
    }
}

function previewImage(input, imgId, placeholderId) {
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
