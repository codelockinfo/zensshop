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

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $heading = trim($_POST['heading'] ?? '');
        $subheading = trim($_POST['subheading'] ?? '');
        $button_text = trim($_POST['button_text'] ?? '');
        $footer_content = trim($_POST['footer_content'] ?? '');
        
        // Handle File Upload
        $background_image = $_POST['existing_bg'] ?? '';
        if (!empty($_FILES['background_image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/newsletter/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['background_image']['name']);
            if (move_uploaded_file($_FILES['background_image']['tmp_name'], $uploadDir . $fileName)) {
                $background_image = 'assets/uploads/newsletter/' . $fileName;
            } else {
                throw new Exception("Failed to upload image.");
            }
        }

        // Determine Store ID
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }

        // Ensure we always have one row
        $check = $db->fetchOne("SELECT id FROM section_newsletter WHERE store_id = ? LIMIT 1", [$storeId]);
        
        if ($check) {
            $sql = "UPDATE section_newsletter SET heading = ?, subheading = ?, button_text = ?, footer_content = ?, background_image = ? WHERE id = ?";
            $db->execute($sql, [$heading, $subheading, $button_text, $footer_content, $background_image, $check['id']]);
        } else {
            // Determine Store ID
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId && isset($_SESSION['user_email'])) {
                 $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                 $storeId = $storeUser['store_id'] ?? null;
            }

            $sql = "INSERT INTO section_newsletter (heading, subheading, button_text, footer_content, background_image, store_id) VALUES (?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [$heading, $subheading, $button_text, $footer_content, $background_image, $storeId]);
        }

        // Save Config
        $show_section = isset($_POST['show_section']) ? true : false;
        $newsletterConfig = ['show_section' => $show_section];
        file_put_contents(__DIR__ . '/newsletter_config.json', json_encode($newsletterConfig));

        $_SESSION['flash_success'] = "Newsletter section updated successfully!";
        header("Location: " . $baseUrl . '/admin/newsletter');
        exit;

    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch Data
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$data = $db->fetchOne("SELECT * FROM section_newsletter WHERE store_id = ? LIMIT 1", [$storeId]);
if (!$data) {
    $data = [
        'heading' => 'Join our family',
        'subheading' => 'Promotions, new products and sales. Directly to your inbox.',
        'button_text' => 'Subscribe',
        'footer_content' => '',
        'background_image' => ''
    ];
}

// Load Config
$newsletterConfigPath = __DIR__ . '/newsletter_config.json';
$showSection = true;
if (file_exists($newsletterConfigPath)) {
    $config = json_decode(file_get_contents($newsletterConfigPath), true);
    $showSection = isset($config['show_section']) ? $config['show_section'] : true;
}

$pageTitle = 'Newsletter Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Newsletter Manager</h1>
            <p class="text-gray-600">Edit the homepage newsletter signup section.</p>
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

    <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow-md max-w-full">
        
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <h3 class="font-bold text-xl text-gray-700">Content Settings</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Left Column: Image -->
            <div>
                 <label class="block text-sm font-bold mb-2">Background Image</label>
                 
                 <div class="relative group cursor-pointer" onclick="document.getElementById('newsletterBgInput').click()">
                     <!-- Hidden Input -->
                     <input type="file" id="newsletterBgInput" name="background_image" class="hidden" onchange="previewNewsletterImage(this)">
                     <input type="hidden" name="existing_bg" value="<?php echo htmlspecialchars($data['background_image'] ?? ''); ?>">

                     <!-- Image Preview Area -->
                     <div id="imagePreviewContainer" class="w-full h-64 border-2 <?php echo !empty($data['background_image']) ? 'border-gray-200' : 'border-dashed border-gray-300'; ?> rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition relative">
                         
                         <?php if (!empty($data['background_image'])): ?>
                             <!-- Existing Image -->
                             <img src="<?php echo $baseUrl . '/' . $data['background_image']; ?>" class="w-full h-full object-cover">
                             
                             <!-- Hover Overlay -->
                             <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                 <span class="text-white font-bold opacity-0 group-hover:opacity-100 transition p-2 bg-black bg-opacity-50 rounded">
                                     <i class="fas fa-camera mr-2"></i> Change Image
                                 </span>
                             </div>
                         <?php else: ?>
                             <!-- Placeholder -->
                             <div class="text-center text-gray-500 p-6">
                                 <i class="fas fa-image text-4xl mb-3 text-gray-400"></i>
                                 <p class="font-bold text-gray-600">Click to Upload Image</p>
                                 <p class="text-xs mt-1 text-gray-400">Recommended: 1920x600px, WEBP/JPG</p>
                             </div>
                         <?php endif; ?>
                     </div>
                 </div>

                 <script>
                 function previewNewsletterImage(input) {
                     if (input.files && input.files[0]) {
                         var reader = new FileReader();
                         reader.onload = function(e) {
                             const container = document.getElementById('imagePreviewContainer');
                             // Remove dashed styling
                             container.classList.remove('border-dashed', 'border-gray-300', 'bg-gray-50');
                             container.classList.add('border-gray-200');
                             
                             container.innerHTML = `
                                 <img src="${e.target.result}" class="w-full h-full object-cover">
                                 <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                     <span class="text-white font-bold opacity-0 group-hover:opacity-100 transition p-2 bg-black bg-opacity-50 rounded">
                                         <i class="fas fa-camera mr-2"></i> Change Image
                                     </span>
                                 </div>
                             `;
                         }
                         reader.readAsDataURL(input.files[0]);
                     }
                 }
                 </script>
            </div>

            <!-- Right Column: Content -->
            <div class="space-y-4">
                 <div class="border-b pb-4 mb-6">
                     <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><i class="fas fa-eye"></i></div>
                            <div>
                                <h3 class="font-bold text-gray-700">Show Newsletter Section</h3>
                                <p class="text-xs text-gray-500">Toggle visibility of this section on the homepage.</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_section" class="sr-only peer" <?php echo $showSection ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
                <div>
                     <label class="block text-sm font-bold mb-2">Heading</label>
                     <input type="text" name="heading" value="<?php echo htmlspecialchars($data['heading'] ?? ''); ?>" class="w-full border p-2 rounded" placeholder="Join our family">
                </div>
                
                <div>
                     <label class="block text-sm font-bold mb-2">Subheading (Context)</label>
                     <textarea name="subheading" rows="2" class="w-full border p-2 rounded"><?php echo htmlspecialchars($data['subheading'] ?? ''); ?></textarea>
                </div>
                
                <div>
                     <label class="block text-sm font-bold mb-2">Button Text</label>
                     <input type="text" name="button_text" value="<?php echo htmlspecialchars($data['button_text'] ?? ''); ?>" class="w-full border p-2 rounded" placeholder="Subscribe">
                </div>

                <div>
                     <label class="block text-sm font-bold mb-2">Footer / Privacy Text (Rich Text)</label>
                     <textarea name="footer_content" class="rich-text-editor w-full border p-2 rounded h-64"><?php echo htmlspecialchars($data['footer_content'] ?? ''); ?></textarea>
                     <p class="text-xs text-gray-500 mt-1">Use this editor to add text, links, images, tables, and style them as needed. This content appears after the footer.</p>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-4 border-t text-right">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded hover:bg-blue-700 transition font-bold shadow-lg btn-loading">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
        </div>

    </form>
</div>



<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
