<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';

$db = Database::getInstance();
$settingsObj = new Settings();

// Fetch active features (Store Specific)
$features = $db->fetchAll("SELECT * FROM section_features WHERE (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC LIMIT 3", [CURRENT_STORE_ID]);

if (empty($features)) return;

$section_bg = $settingsObj->get('features_section_bg', '#ffffff');
$section_text = $settingsObj->get('features_section_text', '#000000');
?>

<section class="py-16" style="background-color: <?php echo htmlspecialchars($section_bg); ?>; color: <?php echo htmlspecialchars($section_text); ?>;">
    <div class="container mx-auto px-4">
        <!-- Render as Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center max-w-full mx-auto">
            <?php foreach ($features as $f): ?>
            <div class="group flex flex-col items-center p-6 rounded shadow-sm h-full transition-transform hover:-translate-y-1 duration-300" 
                 style="background-color: <?php echo htmlspecialchars($f['bg_color'] ?? '#ffffff'); ?>; color: <?php echo htmlspecialchars($f['text_color'] ?? '#000000'); ?>;">
                <!-- Icon Container -->
                <div class="mb-6 transition-transform duration-500 group-hover:scale-x-[-1]" style="color: inherit;"> 
                    <?php echo $f['icon']; ?>
                </div>
                
                <h3 class="text-xl font-bold mb-3 uppercase tracking-wide" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? $f['text_color']); ?>;">
                    <?php echo htmlspecialchars($f['heading']); ?>
                </h3>
                
                <p class="leading-relaxed max-w-sm opacity-90">
                    <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
