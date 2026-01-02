<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC LIMIT 6"
);
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4">Shop By Category</h2>
            <p class="text-gray-600 text-sm md:text-md max-w-2xl mx-auto">Express your style with our standout collection—fashion meets sophistication.</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            <?php 
            $categoryImages = [
                'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=300&h=300&fit=crop',
                'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=300&h=300&fit=crop',
                'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=300&h=300&fit=crop',
                'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=300&h=300&fit=crop',
                'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=300&h=300&fit=crop',
                'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=300&h=300&fit=crop'
            ];
            
            foreach ($categories as $index => $category): 
                $image = $categoryImages[$index % count($categoryImages)];
            ?>
            <a href="<?php echo $baseUrl; ?>/category.php?slug=<?php echo $category['slug']; ?>" class="group text-center">
                <div class="relative mb-4 overflow-hidden rounded-full aspect-square">
                    <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" 
                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                </div>
                <h3 class="text-sm md:text-md font-semibold text-gray-800 group-hover:text-primary transition"><?php echo htmlspecialchars($category['name']); ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>


