<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Settings.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();

// Fetch section headers for this store
$heading = 'Shop By Category';
$subheading = 'Express your style with our standout collection—fashion meets sophistication.';

$categoryConfigPath = __DIR__ . '/../admin/category_config.json';
if (file_exists($categoryConfigPath)) {
    $conf = json_decode(file_get_contents($categoryConfigPath), true);
    $heading = $conf['heading'] ?? $heading;
    $subheading = $conf['subheading'] ?? $subheading;
} else {
    $sectionData = $db->fetchOne("SELECT heading, subheading FROM section_categories WHERE (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [CURRENT_STORE_ID]);
    if ($sectionData) {
        $heading = $sectionData['heading'] ?? $heading;
        $subheading = $sectionData['subheading'] ?? $subheading;
    }
}

// Fetch from homepage_categories table (Store Specific)
$homeCategories = $db->fetchAll("SELECT * FROM section_categories WHERE active = 1 AND (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC", [CURRENT_STORE_ID]);

// Fallback logic removed per user request

if (empty($homeCategories)) {
    return;
}

// Set total count for "View More" logic
$totalCategories = count($homeCategories);

// Limit to 6 for display
$displayCategories = array_slice($homeCategories, 0, 6);
?>

<?php
// Fetch Styles
$settingsObj = new Settings();
$stylesJson = $settingsObj->get('homepage_categories_styles', '{"bg_color":"#ffffff","heading_color":"#1f2937","subheading_color":"#4b5563","text_color":"#1f2937","button_bg_color":"#000000","button_text_color":"#ffffff"}');
$styles = json_decode($stylesJson, true);
$sectionId = 'cat-section-' . rand(1000, 9999);
?>

<style>
    #<?php echo $sectionId; ?> {
        background-color: <?php echo $styles['bg_color']; ?>;
    }
    #<?php echo $sectionId; ?> .cat-heading {
        color: <?php echo $styles['heading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .cat-subheading {
        color: <?php echo $styles['subheading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .cat-item-title {
        color: <?php echo $styles['text_color']; ?>;
    }
    #<?php echo $sectionId; ?> .cat-button {
        background-color: <?php echo $styles['button_bg_color']; ?>;
        color: <?php echo $styles['button_text_color']; ?>;
    }
    #<?php echo $sectionId; ?> .cat-button:hover {
        opacity: 0.9;
    }
</style>

<section id="<?php echo $sectionId; ?>" class="py-16 md:py-14">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4 cat-heading"><?php echo htmlspecialchars($heading); ?></h2>
            <p class="text-sm md:text-md max-w-2xl mx-auto cat-subheading"><?php echo htmlspecialchars($subheading); ?></p>
        </div>
        
        <div class="flex flex-wrap justify-center gap-6">
            
            <?php foreach ($displayCategories as $category): 
                $image = getImageUrl($category['image'] ?? '');
                
                $link = $category['link'];
                if (!preg_match('/^https?:\/\//', $link) && strpos($link, $baseUrl) === false) {
                     $link = $baseUrl . '/' . ltrim($link, '/');
                }
            ?>
            <a href="<?php echo htmlspecialchars($link); ?>" class="group text-center w-[45%] md:w-[30%] lg:w-[14%] flex-shrink-0">
                <div class="relative mb-4 overflow-hidden rounded-full aspect-square">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($category['title']); ?>" 
                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                         onerror="this.src='https://placehold.co/600x600?text=Category+Image'">
                </div>
                <h3 class="text-sm md:text-md font-semibold group-hover:text-primary transition cat-item-title"><?php echo htmlspecialchars($category['title']); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalCategories > 6): ?>
        <div class="text-center mt-10">
            <a href="<?php echo $baseUrl; ?>/collections" class="inline-block px-8 py-3 rounded-full hover:bg-gray-800 transition font-semibold text-sm cat-button">
                View More Collections
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>


