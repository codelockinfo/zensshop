<?php
ob_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$settingsObj = new Settings();
$baseUrl = getBaseUrl();
$success = '';

// Fetch Current Settings
$heading = $settingsObj->get('collections_heading', 'Collections Lists');
$subheading = $settingsObj->get('collections_description', 'Explore our thoughtfully curated collections');

$stylesJson = $settingsObj->get('collections_styles', '[]');
$styles = json_decode($stylesJson, true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // General Settings
        $settingsObj->set('collections_heading', $_POST['heading'] ?? 'Collections Lists');
        $settingsObj->set('collections_description', $_POST['subheading'] ?? 'Explore our thoughtfully curated collections');

        // Visual Styles
        $newStyles = [
            'page_bg_color' => $_POST['page_bg_color'] ?? '#ffffff',
            'heading_color' => $_POST['heading_color'] ?? '#1f2937',
            'subheading_color' => $_POST['subheading_color'] ?? '#4b5563',
            'btn_bg_color' => $_POST['btn_bg_color'] ?? '#ffffff',
            'btn_text_color' => $_POST['btn_text_color'] ?? '#111827',
            'btn_hover_bg_color' => $_POST['btn_hover_bg_color'] ?? '#000000',
            'btn_hover_text_color' => $_POST['btn_hover_text_color'] ?? '#ffffff',
        ];
        
        $settingsObj->set('collections_styles', json_encode($newStyles), 'collections');
        
        $_SESSION['flash_success'] = "Collections settings updated successfully!";
        header("Location: " . $baseUrl . '/admin/collection_settings.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check for success message
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Defaults
$s_page_bg_color = $styles['page_bg_color'] ?? '#ffffff';
$s_heading_color = $styles['heading_color'] ?? '#1f2937';
$s_subheading_color = $styles['subheading_color'] ?? '#4b5563';
$s_btn_bg_color = $styles['btn_bg_color'] ?? '#ffffff';
$s_btn_text_color = $styles['btn_text_color'] ?? '#111827';
$s_btn_hover_bg_color = $styles['btn_hover_bg_color'] ?? '#000000';
$s_btn_hover_text_color = $styles['btn_hover_text_color'] ?? '#ffffff';

$pageTitle = 'Collections Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Collections Settings</h1>
            <p class="text-sm text-gray-500 mt-1">Customize the appearance of the collections list page.</p>
        </div>
        <div>
             <a href="<?php echo $baseUrl; ?>/collections" target="_blank" class="mr-3 text-gray-600 hover:text-blue-600 font-bold text-sm">
                 <i class="fas fa-external-link-alt mr-1"></i> View Page
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Content Settings -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Page Content</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Heading Text</label>
                            <input type="text" name="heading" value="<?php echo htmlspecialchars($heading); ?>" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Subheading Text</label>
                            <textarea name="subheading" rows="3" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none"><?php echo htmlspecialchars($subheading); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Visual Styles</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Page Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="page_bg_color" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_page_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
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
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800">Card Button Style</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_bg_color" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Button Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_text_color" value="<?php echo htmlspecialchars($s_btn_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Background</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_hover_bg_color" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_bg_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Hover Text Color</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="btn_hover_text_color" value="<?php echo htmlspecialchars($s_btn_hover_text_color); ?>" class="h-10 w-16 border rounded cursor-pointer p-0.5" oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" value="<?php echo htmlspecialchars($s_btn_hover_text_color); ?>" class="flex-1 border rounded p-2 text-sm uppercase" oninput="this.previousElementSibling.value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Side -->
            <div>
                 <div class="sticky top-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-eye text-blue-600"></i>
                        Live Preview
                    </h2>
                    
                    <style id="previewHoverStyles">
                        .preview-col-card:hover .preview-col-btn {
                            background-color: <?php echo $s_btn_hover_bg_color; ?> !important;
                            color: <?php echo $s_btn_hover_text_color; ?> !important;
                        }
                    </style>

                    <div id="collectionsPreview" class="rounded-xl border border-gray-200 overflow-hidden shadow-lg p-8 transition-all duration-300" style="background-color: <?php echo $s_page_bg_color; ?>;">
                        <div class="text-center mb-8">
                            <h1 id="prevHeading" class="text-2xl font-bold mb-2" style="color: <?php echo $s_heading_color; ?>;">
                                <?php echo htmlspecialchars($heading); ?>
                            </h1>
                            <p id="prevSubheading" class="text-xs max-w-xs mx-auto" style="color: <?php echo $s_subheading_color; ?>;">
                                <?php echo htmlspecialchars($subheading); ?>
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Sample Collection Card -->
                            <div class="preview-col-card relative overflow-hidden rounded-lg bg-gray-100 aspect-[3/4] flex flex-col justify-end group">
                                <div class="absolute inset-0 flex items-center justify-center opacity-20">
                                    <i class="fas fa-layer-group text-4xl"></i>
                                </div>
                                <div class="p-3">
                                    <div id="prevBtn" class="preview-col-btn py-2 px-4 w-full shadow-sm text-center text-xs font-bold transition-all duration-300" style="background-color: <?php echo $s_btn_bg_color; ?>; color: <?php echo $s_btn_text_color; ?>; border-radius: 50px;">
                                        Collection Name
                                    </div>
                                </div>
                            </div>
                            <div class="preview-col-card relative overflow-hidden rounded-lg bg-gray-100 aspect-[3/4] flex flex-col justify-end group">
                                <div class="absolute inset-0 flex items-center justify-center opacity-20">
                                    <i class="fas fa-layer-group text-4xl"></i>
                                </div>
                                <div class="p-3">
                                    <div class="preview-col-btn py-2 px-4 w-full shadow-sm text-center text-xs font-bold transition-all duration-300" style="background-color: <?php echo $s_btn_bg_color; ?>; color: <?php echo $s_btn_text_color; ?>; border-radius: 50px;">
                                        Sample Item
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-4 text-center italic">Hover over the cards to see hover effects</p>
                    </div>
                 </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = {
        heading: document.querySelector('input[name="heading"]'),
        subheading: document.querySelector('textarea[name="subheading"]'),
        headingColor: document.querySelector('input[name="heading_color"]'),
        subheadingColor: document.querySelector('input[name="subheading_color"]'),
        pageBg: document.querySelector('input[name="page_bg_color"]'),
        btnBg: document.querySelector('input[name="btn_bg_color"]'),
        btnText: document.querySelector('input[name="btn_text_color"]'),
        btnHoverBg: document.querySelector('input[name="btn_hover_bg_color"]'),
        btnHoverText: document.querySelector('input[name="btn_hover_text_color"]')
    };

    const preview = {
        heading: document.getElementById('prevHeading'),
        subheading: document.getElementById('prevSubheading'),
        container: document.getElementById('collectionsPreview'),
        btns: document.querySelectorAll('.preview-col-btn'),
        hoverStyles: document.getElementById('previewHoverStyles')
    };

    function updatePreview() {
        preview.heading.textContent = inputs.heading.value;
        preview.subheading.textContent = inputs.subheading.value;
        preview.heading.style.color = inputs.headingColor.value;
        preview.subheading.style.color = inputs.subheadingColor.value;
        preview.container.style.backgroundColor = inputs.pageBg.value;
        
        preview.btns.forEach(btn => {
            btn.style.backgroundColor = inputs.btnBg.value;
            btn.style.color = inputs.btnText.value;
        });

        preview.hoverStyles.innerHTML = `
            .preview-col-card:hover .preview-col-btn {
                background-color: ${inputs.btnHoverBg.value} !important;
                color: ${inputs.btnHoverText.value} !important;
            }
        `;
    }

    Object.values(inputs).forEach(input => {
        if (input) {
            input.addEventListener('input', updatePreview);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
