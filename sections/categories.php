<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Settings.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();

// Fetch section headers for this store
$catLayoutType = 'grid';
$catMobileSize = '2'; // Default to 2 items per row

// Fetch from DB primary
$sectionData = $db->fetchOne("SELECT heading, subheading, layout_type, mobile_size FROM section_categories WHERE (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [CURRENT_STORE_ID]);
if ($sectionData) {
    if (!empty($sectionData['heading'])) $heading = $sectionData['heading'];
    if (!empty($sectionData['subheading'])) $subheading = $sectionData['subheading'];
    if (!empty($sectionData['layout_type'])) $catLayoutType = $sectionData['layout_type'];
    if (!empty($sectionData['mobile_size'])) $catMobileSize = $sectionData['mobile_size'];
} else {
    // fallback to json
    $categoryConfigPath = __DIR__ . '/../admin/category_config.json';
    if (file_exists($categoryConfigPath)) {
        $conf = json_decode(file_get_contents($categoryConfigPath), true);
        $heading = $conf['heading'] ?? $heading;
        $subheading = $conf['subheading'] ?? $subheading;
        $catLayoutType = $conf['layout_type'] ?? 'grid';
        $catMobileSize = $conf['mobile_size'] ?? '2';
    }
}

// Mobile Grid Class mappings based on size
$mobileGridClass = 'w-[calc(50%-12px)]'; // default 2
$mobileSliderClass = 'w-[calc(50%-8px)]';

if ($catMobileSize == '1' || $catMobileSize == '2') {
    $mobileGridClass = ($catMobileSize == '1') ? 'w-full' : 'w-[calc(50%-12px)]';
    // For slider, force a minimum of 2 columns on mobile so it doesn't look like a giant circle
    $mobileSliderClass = 'w-[calc(50%-8px)]';
}
if ($catMobileSize == '3') {
    $mobileGridClass = 'w-[calc(33.333%-16px)]';
    $mobileSliderClass = 'w-[calc(33.333%-10px)]';
}
if ($catMobileSize == '4') {
    $mobileGridClass = 'w-[calc(25%-18px)]';
    $mobileSliderClass = 'w-[calc(25%-12px)]';
}

// Fetch from homepage_categories table (Store Specific)
$homeCategories = $db->fetchAll("SELECT * FROM section_categories WHERE active = 1 AND (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC", [CURRENT_STORE_ID]);

// Fallback logic removed per user request

if (empty($homeCategories)) {
    return;
}

// Set total count for "View More" logic
$totalCategories = count($homeCategories);

// If slider, show all. If grid, limit to 6 for display.
$displayCategories = ($catLayoutType === 'slider') ? $homeCategories : array_slice($homeCategories, 0, 6);
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
    #<?php echo $sectionId; ?> .cat-img-container {
        width: 100%;
        max-width: 150px;
        margin-left: auto;
        margin-right: auto;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    @media (max-width: 768px) {
        #<?php echo $sectionId; ?> .cat-img-container {
            max-width: 120px;
        }
    }
    #<?php echo $sectionId; ?> .cat-arrow {
        position: absolute;
        top: 50%;
        width: 45px;
        height: 45px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        z-index: 20;
        cursor: pointer;
        transition: all 0.3s;
        transform: translateY(-50%);
        border: 1px solid #f1f1f1;
    }
    #<?php echo $sectionId; ?> .cat-arrow:hover {
        background: #000;
        color: #fff;
    }
    #<?php echo $sectionId; ?> .cat-arrow-prev { left: -20px; }
    #<?php echo $sectionId; ?> .cat-arrow-next { right: -20px; }
    @media (max-width: 1024px) {
        #<?php echo $sectionId; ?> .cat-arrow { display: none; }
    }
</style>

<section id="<?php echo $sectionId; ?>" class="pt-8 md:pt-14">
<?php
        $containerClass = 'container mx-auto px-4';
        if ($catLayoutType === 'slider') {
            $containerClass = 'container mx-auto px-0 md:px-4';
        }
        ?>
    <div class="<?php echo $containerClass; ?>">
        <div class="text-center mb-6 md:mb-12">
            <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4 cat-heading"><?php echo htmlspecialchars($heading); ?></h2>
            <p class="text-lg md:text-md max-w-2xl mx-auto cat-subheading"><?php echo htmlspecialchars($subheading); ?></p>
        </div>
        
        <?php if ($catLayoutType === 'slider'): ?>
            <!-- Slider Layout (Vanilla JS Custom Slider like Best Selling) -->
            <div class="relative px-2">
                <div class="categories-slider overflow-hidden">
                    <div class="flex gap-1 md:gap-6 w-fit mx-auto" id="categoriesSlider" style="will-change: transform;">
                        <?php foreach ($displayCategories as $category): 
                            $image = getImageUrl($category['image'] ?? '');
                            $link = $category['link'];
                            if (!preg_match('/^https?:\/\//', $link) && strpos($link, $baseUrl) === false) {
                                 $link = $baseUrl . '/' . ltrim($link, '/');
                            }
                        ?>
                        <div class="<?php echo $mobileSliderClass; ?> md:w-[180px] lg:w-[150px] my-2 text-center flex-shrink-0">
                            <a href="<?php echo htmlspecialchars($link); ?>" class="group block w-full px-1">
                                <div class="relative mb-4 overflow-hidden rounded-full aspect-square cat-img-container shadow-sm border border-gray-100">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($category['title']); ?>" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                         onerror="this.src='https://placehold.co/600x600?text=Category+Image'">
                                </div>
                                <h3 class="text-xs md:text-sm font-semibold group-hover:text-primary transition cat-item-title px-1 line-clamp-2"><?php echo htmlspecialchars($category['title']); ?></h3>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Navigation Arrows -->
                <?php if (count($displayCategories) > 1): ?>
                <button class="cat-arrow cat-arrow-prev" id="categoriesPrev" aria-label="Previous category">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <button class="cat-arrow cat-arrow-next" id="categoriesNext" aria-label="Next category">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Grid Layout -->
            <div class="flex flex-wrap justify-center gap-6">
                <?php foreach ($displayCategories as $category): 
                    $image = getImageUrl($category['image'] ?? '');
                    
                    $link = $category['link'];
                    if (!preg_match('/^https?:\/\//', $link) && strpos($link, $baseUrl) === false) {
                         $link = $baseUrl . '/' . ltrim($link, '/');
                    }
                ?>
                <a href="<?php echo htmlspecialchars($link); ?>" class="group text-center <?php echo $mobileGridClass; ?> md:w-[30%] lg:w-[14%] flex-shrink-0">
                    <div class="relative mb-4 overflow-hidden rounded-full aspect-square mx-auto">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($category['title']); ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                             onerror="this.src='https://placehold.co/600x600?text=Category+Image'">
                    </div>
                    <h3 class="text-sm md:text-md font-semibold group-hover:text-primary transition cat-item-title"><?php echo htmlspecialchars($category['title']); ?></h3>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($catLayoutType !== 'slider' && $totalCategories > 6): ?>
        <div class="text-center mt-10">
            <a href="<?php echo $baseUrl; ?>/collections" class="inline-block px-8 py-3 rounded-full hover:bg-gray-800 transition font-semibold text-sm cat-button">
                View More Collections
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>


