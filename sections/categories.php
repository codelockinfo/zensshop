<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE status = 'active' AND store_id = ? ORDER BY sort_order ASC LIMIT 6",
    [$storeId]
);
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <?php 
                // Fetch section headers from first row
                $sectionData = $db->fetchOne("SELECT heading, subheading FROM section_categories WHERE store_id = ? LIMIT 1", [$storeId]);
                $heading = !empty($sectionData['heading']) ? $sectionData['heading'] : 'Shop By Category';
                $subheading = !empty($sectionData['subheading']) ? $sectionData['subheading'] : 'Express your style with our standout collection—fashion meets sophistication.';
            ?>
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4"><?php echo htmlspecialchars($heading); ?></h2>
            <p class="text-gray-600 text-sm md:text-md max-w-2xl mx-auto"><?php echo htmlspecialchars($subheading); ?></p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            <?php 
            // Fetch from homepage_categories table
            $homeCategories = $db->fetchAll("SELECT * FROM section_categories WHERE active = 1 AND store_id = ? ORDER BY sort_order ASC LIMIT 6", [$storeId]);
            
            // Fallback for demonstration if table is empty
            if (empty($homeCategories)) {
                $categoryImages = [
                    'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=300&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=300&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=300&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=300&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=300&h=300&fit=crop',
                    'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=300&h=300&fit=crop'
                ];
                
                // Fetch default categories if no custom ones
                $homeCategories = [];
                $dbCategories = $db->fetchAll("SELECT * FROM categories WHERE status = 'active' AND store_id = ? ORDER BY sort_order ASC LIMIT 6", [$storeId]);
                foreach ($dbCategories as $index => $cat) {
                    $homeCategories[] = [
                        'title' => $cat['name'],
                        'link' => 'category.php?slug=' . $cat['slug'],
                        'image' => $categoryImages[$index % count($categoryImages)]
                    ];
                }
            }
            
            foreach ($homeCategories as $category): 
                $image = $category['image'];
                if (!preg_match('/^https?:\/\//', $image)) {
                    $image = $baseUrl . '/' . ltrim($image, '/');
                }
                
                $link = $category['link'];
                if (!preg_match('/^https?:\/\//', $link) && strpos($link, $baseUrl) === false) {
                     $link = $baseUrl . '/' . ltrim($link, '/');
                }
            ?>
            <a href="<?php echo htmlspecialchars($link); ?>" class="group text-center">
                <div class="relative mb-4 overflow-hidden rounded-full aspect-square">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($category['title']); ?>" 
                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                </div>
                <h3 class="text-sm md:text-md font-semibold text-gray-800 group-hover:text-primary transition"><?php echo htmlspecialchars($category['title']); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>


