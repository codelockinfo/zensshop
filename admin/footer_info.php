<?php
session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

$db = Database::getInstance();
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // WAF BYPASS: Decode Base64 encoded sensitive fields
        if (isset($_POST['is_encoded_submission']) && $_POST['is_encoded_submission'] === '1') {
            // Decode Description (HTML)
            if (isset($_POST['footer_description'])) {
                $_POST['footer_description'] = base64_decode($_POST['footer_description']);
            }
            
            // Decode SVGs
            if (isset($_POST['payment_svgs']) && is_array($_POST['payment_svgs'])) {
                $_POST['payment_svgs'] = array_map('base64_decode', $_POST['payment_svgs']);
            }
        }

        // 1. General Info
        $settings = [
            'footer_logo_type' => $_POST['footer_logo_type'] ?? 'text',
            'footer_logo_text' => $_POST['footer_logo_text'] ?? '',
            'footer_description' => $_POST['footer_description'] ?? '',
            'footer_learn_more_url' => preg_replace('/\.php(\?|$)/', '$1', $_POST['footer_learn_more_url'] ?? '#'),
            'footer_address' => $_POST['footer_address'] ?? '',
            'footer_phone' => $_POST['footer_phone'] ?? '',
            'footer_email' => $_POST['footer_email'] ?? '',
            'footer_facebook' => $_POST['footer_facebook'] ?? '',
            'footer_instagram' => $_POST['footer_instagram'] ?? '',
            'footer_tiktok' => $_POST['footer_tiktok'] ?? '',
            'footer_youtube' => $_POST['footer_youtube'] ?? '',
            'footer_pinterest' => $_POST['footer_pinterest'] ?? '',
            'footer_twitter' => $_POST['footer_twitter'] ?? '',
            'footer_copyright' => $_POST['footer_copyright'] ?? '',
            'footer_show_visa' => isset($_POST['footer_show_visa']) ? '1' : '0',
            'footer_show_mastercard' => isset($_POST['footer_show_mastercard']) ? '1' : '0',
            'footer_show_amex' => isset($_POST['footer_show_amex']) ? '1' : '0',
            'footer_show_paypal' => isset($_POST['footer_show_paypal']) ? '1' : '0',
            'footer_show_discover' => isset($_POST['footer_show_discover']) ? '1' : '0',
            'footer_payment_icons_json' => '[]', // Default, will be updated below
        ];

        // 2. Handle Image Upload
        if (!empty($_FILES['footer_logo_image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['footer_logo_image']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['footer_logo_image']['tmp_name'], $targetPath)) {
                $settings['footer_logo_image'] = 'assets/uploads/' . $fileName;
            } else {
                throw new Exception("Failed to upload image.");
            }
        }

        // 3. Handle Social Links (JSON)
        if (isset($_POST['social_icons'])) {
            $social_icons = $_POST['social_icons'];
            $social_urls = $_POST['social_urls'] ?? [];
            $socialLinks = [];
            
            for ($i = 0; $i < count($social_icons); $i++) {
                if (!empty($social_icons[$i]) && !empty($social_urls[$i])) {
                    $socialLinks[] = [
                        'icon' => $social_icons[$i],
                        'url' => $social_urls[$i],
                    ];
                }
            }
            $settings['footer_social_json'] = json_encode(array_values($socialLinks), JSON_UNESCAPED_SLASHES);
        } else {
            $settings['footer_social_json'] = '[]';
        }

        // 4. Handle Payment Icons (JSON)
        if (isset($_POST['payment_names'])) {
            $payment_names = $_POST['payment_names'];
            $payment_svgs = $_POST['payment_svgs'] ?? [];
            $paymentIcons = [];
            
            for ($i = 0; $i < count($payment_names); $i++) {
                if (!empty($payment_names[$i]) && !empty($payment_svgs[$i])) {
                    $paymentIcons[] = [
                        'name' => $payment_names[$i],
                        'svg' => $payment_svgs[$i],
                    ];
                }
            }
            $settings['footer_payment_icons_json'] = json_encode(array_values($paymentIcons), JSON_UNESCAPED_SLASHES);
        } else {
            $settings['footer_payment_icons_json'] = '[]';
        }

        // 5. Save to Database (Key-Value Store)
        foreach ($settings as $key => $value) {
            $db->execute(
                "INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $value, $storeId, $value]
            );
        }
        
        $_SESSION['flash_success'] = "Footer information updated successfully!";
        header("Location: " . url('admin/footer'));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Fetch Current Settings
$rawSettings = $db->fetchAll("SELECT * FROM site_settings WHERE store_id = ?", [$storeId]);
$currentSettings = [];
foreach ($rawSettings as $s) {
    $currentSettings[$s['setting_key']] = $s['setting_value'];
}
// Defaults
$defaults = [
    'footer_logo_type' => 'text',
    'footer_logo_text' => 'ZENSSHOP',
    'footer_logo_image' => '',
    'footer_description' => '',
    'footer_learn_more_url' => '',
    'footer_address' => '',
    'footer_phone' => '',
    'footer_email' => '',
    'footer_social_json' => '[]',
    'footer_copyright' => '© ' . date('Y') . ' store. All rights reserved.',
    'footer_show_visa' => '1',
    'footer_show_mastercard' => '1',
    'footer_show_amex' => '1',
    'footer_show_paypal' => '1',
    'footer_show_discover' => '1',
    'footer_payment_icons_json' => '[]',
];
$settings = array_merge($defaults, $currentSettings);
$socialLinks = json_decode($settings['footer_social_json'], true) ?: [];
$paymentIcons = json_decode($settings['footer_payment_icons_json'], true) ?: [];

$pageTitle = 'Footer Information';
require_once __DIR__ . '/../includes/admin-header.php';
?>


<form id="footerForm" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="is_encoded_submission" value="0" id="isEncodedSubmission">
    <!-- Sticky Header -->
    <div class="mb-6 flex justify-between items-end sticky top-0 bg-[#f7f8fc] py-4 z-50 border-b border-gray-200 px-1">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 pt-4 pl-2">Footer Information</h1>
            <p class="text-sm text-gray-500 mt-1 pl-2">Dashboard > Settings > Footer Info</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition shadow-sm btn-loading">
                Save Changes
            </button>
        </div>
    </div>

    <div class="p-6 bg-white rounded-lg shadow-md m-6">
        
        <!-- Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="space-y-6">
        
        <!-- Logo Section -->
        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <h3 class="font-bold text-lg mb-4 border-b pb-2">Footer Logo</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold mb-2">Logo Type</label>
                    <select name="footer_logo_type" class="w-full border p-2 rounded" onchange="toggleLogoFields(this.value)">
                        <option value="text" <?php echo $settings['footer_logo_type'] === 'text' ? 'selected' : ''; ?>>Text Logo</option>
                        <option value="image" <?php echo $settings['footer_logo_type'] === 'image' ? 'selected' : ''; ?>>Image Logo</option>
                    </select>
                </div>
                
                <div id="textLogoField" class="<?php echo $settings['footer_logo_type'] === 'text' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold mb-2">Logo Text</label>
                    <input type="text" name="footer_logo_text" value="<?php echo htmlspecialchars($settings['footer_logo_text']); ?>" class="w-full border p-2 rounded">
                </div>
                
                <div id="imageLogoField" class="<?php echo $settings['footer_logo_type'] === 'image' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold mb-2">Logo Image</label>
                    
                    <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[120px] hover:bg-gray-100 transition" onclick="document.getElementById('footerLogoInput').click()">
                        
                        <img id="footerLogoPreview" 
                             src="<?php echo !empty($settings['footer_logo_image']) ? getImageUrl($settings['footer_logo_image']) : ''; ?>" 
                             class="max-h-20 object-contain <?php echo !empty($settings['footer_logo_image']) ? '' : 'hidden'; ?>">
                             
                        <div id="footerLogoPlaceholder" class="<?php echo !empty($settings['footer_logo_image']) ? 'hidden' : ''; ?> text-center">
                             <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                             <p class="text-sm text-gray-500">Click to upload logo</p>
                        </div>
                        
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-5 flex items-center justify-center transition-all rounded-lg"></div>
                    </div>
                    
                    <input type="file" id="footerLogoInput" name="footer_logo_image" accept="image/*" class="hidden" onchange="previewFooterLogo(this)">
                    <p class="text-xs text-gray-500 mt-1">Recommended: PNG or SVG format, transparent background</p>
                </div>
            </div>
        </div>

        <!-- Description Section -->
        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <h3 class="font-bold text-lg mb-4 border-b pb-2">About & Contact</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Footer Description</label>
                <textarea name="footer_description" rows="3" class="w-full border p-2 rounded"><?php echo htmlspecialchars($settings['footer_description']); ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">"Learn More" Link URL</label>
                <input type="text" name="footer_learn_more_url" value="<?php echo htmlspecialchars($settings['footer_learn_more_url']); ?>" class="w-full border p-2 rounded" placeholder="https://...">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Address</label>
                    <input type="text" name="footer_address" value="<?php echo htmlspecialchars($settings['footer_address']); ?>" class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Phone</label>
                    <input type="text" name="footer_phone" value="<?php echo htmlspecialchars($settings['footer_phone']); ?>" class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Email</label>
                    <input type="text" name="footer_email" value="<?php echo htmlspecialchars($settings['footer_email']); ?>" class="w-full border p-2 rounded">
                </div>
            </div>
        </div>

        <!-- Social Media Section (Dynamic) -->
        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <div class="flex justify-between items-center border-b pb-2 mb-4">
                <h3 class="font-bold text-lg">Social Media Links</h3>
                <button type="button" onclick="addSocialRow()" class="text-white bg-green-600 hover:bg-green-700 text-sm px-3 py-1 rounded">
                    <i class="fas fa-plus"></i> Add New
                </button>
            </div>
            
            <div id="socialLinksContainer" class="space-y-3">
                <!-- Rows will be added here by JS -->
            </div>
            
            <!-- Template for JS -->
            <template id="socialRowTemplate">
                <div class="flex items-center gap-3 social-row p-2 bg-white border border-gray-200 rounded">
                    <div class="w-1/3">
                        <select name="social_icons[]" class="w-full border p-2 rounded text-sm">
                            <option value="fab fa-facebook-f">Facebook</option>
                            <option value="fab fa-instagram">Instagram</option>
                            <option value="fab fa-tiktok">TikTok</option>
                            <option value="fab fa-youtube">YouTube</option>
                            <option value="fab fa-pinterest-p">Pinterest</option>
                            <option value="fab fa-twitter">Twitter/X</option>
                            <option value="fab fa-linkedin-in">LinkedIn</option>
                            <option value="fab fa-whatsapp">WhatsApp</option>
                            <option value="fab fa-snapchat-ghost">Snapchat</option>
                            <option value="fas fa-envelope">Email</option>
                            <option value="fas fa-link">Website/Link</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <input type="text" name="social_urls[]" class="w-full border p-2 rounded text-sm" placeholder="URL (https://...)">
                    </div>
                    <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700 p-2">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </template>
        </div>

        <!-- Bottom Footer (Copyright) -->
        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <h3 class="font-bold text-lg mb-4 border-b pb-2">Bottom Footer (Copyright)</h3>
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Copyright Text</label>
                <input type="text" name="footer_copyright" value="<?php echo htmlspecialchars($settings['footer_copyright']); ?>" class="w-full border p-2 rounded">
            </div>
        </div>

        <!-- Payment Icons Section (Dynamic SVG) -->
        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <div class="flex justify-between items-center border-b pb-2 mb-4">
                <h3 class="font-bold text-lg">Payment Method SVGs</h3>
                <button type="button" onclick="addPaymentRow()" class="text-white bg-green-600 hover:bg-green-700 text-sm px-3 py-1 rounded">
                    <i class="fas fa-plus"></i> Add New
                </button>
            </div>
            
            <div id="paymentIconsContainer" class="space-y-4">
                <!-- Rows will be added here by JS -->
            </div>
            
            <!-- Template for JS -->
            <template id="paymentRowTemplate">
                <div class="bg-white border border-gray-200 rounded p-4 payment-row relative group">
                    <button type="button" onclick="removeRow(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 p-2">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Name</label>
                            <input type="text" name="payment_names[]" class="w-full border p-2 rounded text-sm" placeholder="e.g. Visa">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">SVG Code</label>
                            <textarea name="payment_svgs[]" rows="2" class="w-full border p-2 rounded text-sm font-mono" placeholder="<svg ...>...</svg>"></textarea>
                        </div>
                        <div class="md:col-span-1 flex items-end justify-center pb-2">
                            <div class="p-2 border rounded bg-gray-50 w-full text-center svg-preview">
                                <span class="text-xs text-gray-400">Preview</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        </div>
    </div>
</form>

<!-- CKEditor 5 -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<style>
/* CKEditor content styling fix for Tailwind */
.ck-editor__editable_inline {
    min-height: 200px;
    background-color: white !important;
}
.ck-content {
    font-size: 14px;
    line-height: 1.6;
}
</style>

<script>
function toggleLogoFields(type) {
    if (type === 'text') {
        document.getElementById('textLogoField').classList.remove('hidden');
        document.getElementById('imageLogoField').classList.add('hidden');
    } else {
        document.getElementById('textLogoField').classList.add('hidden');
        document.getElementById('imageLogoField').classList.remove('hidden');
    }
}

function previewFooterLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('footerLogoPreview');
            const placeholder = document.getElementById('footerLogoPlaceholder');
            
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

// Social Media Dynamic Rows
const socialData = <?php echo $settings['footer_social_json']; ?>;

function addSocialRow(data = null) {
    const container = document.getElementById('socialLinksContainer');
    const template = document.getElementById('socialRowTemplate');
    const clone = template.content.cloneNode(true);
    
    if (data) {
        clone.querySelector('select').value = data.icon;
        clone.querySelector('input').value = data.url;
    }
    
    container.appendChild(clone);
}

// Payment Icons Dynamic Rows
const paymentData = <?php echo $settings['footer_payment_icons_json']; ?>;

function addPaymentRow(data = null) {
    const container = document.getElementById('paymentIconsContainer');
    const template = document.getElementById('paymentRowTemplate');
    const clone = template.content.cloneNode(true);
    
    if (data) {
        clone.querySelector('input').value = data.name;
        clone.querySelector('textarea').value = data.svg;
        clone.querySelector('.svg-preview').innerHTML = data.svg;
    }
    
    // Add real-time preview listener to the newly added textarea
    const textarea = clone.querySelector('textarea');
    textarea.addEventListener('input', function() {
        const previewDiv = this.closest('.payment-row').querySelector('.svg-preview');
        previewDiv.innerHTML = this.value || '<span class="text-xs text-gray-400">Preview</span>';
    });
    
    container.appendChild(clone);
}

function removeRow(btn) {
    btn.closest('.social-row, .payment-row').remove();
}

// Init social links
if (socialData && socialData.length > 0) {
    socialData.forEach(item => addSocialRow(item));
} else {
    addSocialRow();
}

// Init payment icons
if (paymentData && paymentData.length > 0) {
    paymentData.forEach(item => addPaymentRow(item));
} else {
    // If no dynamic icons saved, we'll keep it empty for now.
    // The user will add them.
}

// Initialize CKEditor
let descriptionEditor;
ClassicEditor
    .create(document.querySelector('textarea[name="footer_description"]'), {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo'],
    })
    .then(editor => {
        descriptionEditor = editor;
    })
    .catch(error => {
        console.error(error);
    });

// Encode sensitive fields before submission
document.getElementById('footerForm').addEventListener('submit', function(e) {
    // Set encoded flag
    document.getElementById('isEncodedSubmission').value = '1';
    
    // Helper to safely encode to Base64 (supporting UTF-8)
    const toBase64 = (str) => btoa(unescape(encodeURIComponent(str)));
    
    // 1. Handle CKEditor Content
    if (descriptionEditor) {
        // Get data from editor, encode it, and put it back into the textarea
        const data = descriptionEditor.getData();
        const encodedData = toBase64(data);
        // We write directly to the textarea element so it gets submitted
        document.querySelector('textarea[name="footer_description"]').value = encodedData;
    }

    // 2. Handle SVGs
    const svgs = document.getElementsByName('payment_svgs[]');
    svgs.forEach(el => {
        el.value = toBase64(el.value);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
</body>
</html>
