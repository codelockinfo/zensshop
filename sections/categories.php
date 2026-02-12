<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

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

<section class="py-16 md:py-14 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($heading); ?></h2>
            <p class="text-gray-600 text-sm md:text-md max-w-2xl mx-auto"><?php echo htmlspecialchars($subheading); ?></p>
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
                <h3 class="text-sm md:text-md font-semibold text-gray-800 group-hover:text-primary transition"><?php echo htmlspecialchars($category['title']); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalCategories > 6): ?>
        <div class="text-center mt-10">
            <a href="<?php echo $baseUrl; ?>/collections" class="inline-block bg-black text-white px-8 py-3 rounded-full hover:bg-gray-800 hover:text-white transition font-semibold text-sm">
                View More Collections
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>


