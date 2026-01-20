<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

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
        $db->execute("DELETE FROM special_offers WHERE id = ?", [$id]);
        $_SESSION['flash_success'] = "Offer deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    elseif ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $title = $_POST['title'] ?? '';
        $link = $_POST['link'] ?? '';
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
                // Update existing
                $sql = "UPDATE special_offers SET title = ?, link = ?, button_text = ?, display_order = ?, active = ?";
                $params = [$title, $link, $button_text, $display_order, $active];
                
                if ($image) {
                    $sql .= ", image = ?";
                    $params[] = $image;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $db->execute($sql, $params);
                $_SESSION['flash_success'] = "Offer updated successfully!";
            } else {
                // Insert new
                if (empty($image) && empty($_FILES['image']['name'])) {
                    $error = "Image is required for new offers.";
                } else {
                    $db->execute(
                        "INSERT INTO special_offers (title, link, button_text, image, display_order, active) VALUES (?, ?, ?, ?, ?, ?)",
                        [$title, $link, $button_text, $image, $display_order, $active]
                    );
                    $_SESSION['flash_success'] = "Offer added successfully!";
                }
            }
            
            if (empty($error)) {
                header("Location: " . $_SERVER['PHP_SELF']);
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

// Fetch Offers
$offers = $db->fetchAll("SELECT * FROM special_offers ORDER BY display_order ASC, created_at DESC");
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
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Offer</button>
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
