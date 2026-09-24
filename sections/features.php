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

<section class="py-3 md:py-6" style="background-color: <?php echo htmlspecialchars($section_bg); ?>; color: <?php echo htmlspecialchars($section_text); ?>;">
    <div class="container mx-auto px-4">
        <!-- Mobile: Horizontal rows | Desktop: 3-column grid -->
        <div class="flex flex-col md:grid md:grid-cols-3 gap-3 md:gap-6 max-w-full mx-auto">
            <?php foreach ($features as $f): ?>
            <div class="group flex flex-row md:flex-col items-center md:items-center p-3 md:p-6 rounded-xl md:rounded transition-transform hover:-translate-y-1 duration-300" 
                 style="background-color: <?php echo htmlspecialchars($f['bg_color'] ?? $section_bg); ?>; color: <?php echo htmlspecialchars($f['text_color'] ?? '#000000'); ?>;">
                <!-- Icon Container -->
                <div class="flex-shrink-0 w-14 h-14 md:w-auto md:h-auto flex items-center justify-center rounded-full bg-gray-100 md:bg-transparent md:mb-3 mr-4 md:mr-0 transition-transform duration-500 group-hover:scale-x-[-1]" style="color: inherit;">
                    <?php echo $f['icon']; ?>
                </div>
                
                <!-- Text -->
                <div class="flex-1 md:text-center">
                    <h3 class="text-[15px] md:text-[18px] font-bold tracking-wide leading-tight" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? $f['text_color']); ?>;">
                        <?php echo htmlspecialchars(ucwords(strtolower($f['heading']))); ?>
                    </h3>
                    <p class="text-[13px] md:text-[14px] leading-relaxed opacity-75 mt-0.5">
                        <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
