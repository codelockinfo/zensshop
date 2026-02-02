<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();

// Fetch offers from DB
try {
    $offers = $db->fetchAll("SELECT * FROM special_offers WHERE active = 1 AND (store_id = ? OR store_id IS NULL) ORDER BY display_order ASC", [CURRENT_STORE_ID]);
} catch (Exception $e) {
    $offers = [];
}

// Fallback if empty (should not happen due to seeding, but safe)
if (empty($offers)) {
    // Optional: Keep hardcoded fallback or verify it's just empty
}

// Get Section Heading/Subheading from the first item (since it's denormalized)
$sectionHeading = $offers[0]['heading'] ?? 'Special Offers';
$sectionSubheading = $offers[0]['subheading'] ?? 'Grab limited-time deals on our best products.';
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="text-center mb-12">
            <?php if (!empty($sectionHeading)): ?>
                <h2 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-3"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <?php endif; ?>
            <?php if (!empty($sectionSubheading)): ?>
                <p class="text-gray-600 text-base md:text-lg"><?php echo htmlspecialchars($sectionSubheading); ?></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($offers as $offer): ?>
            <?php
				// Resolve Image URL
				$imgSrc = $offer['image'];
				if (!preg_match('/^https?:\/\//', $imgSrc)) {
					$imgSrc = $baseUrl . '/' . ltrim($imgSrc, '/');
				}
				
				// Resolve Link URL
				$link = $offer['link'];
				if (!empty($link) && !preg_match('/^https?:\/\//', $link)) {
					$link = $baseUrl . '/' . ltrim($link, '/');
				}
			?>
            <div class="relative group overflow-hidden rounded-lg">
                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>" 
                     class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-white p-6">
                    <h3 class="text-xl md:text-2xl font-heading font-bold text-white mb-4 text-center"><?php echo htmlspecialchars($offer['title']); ?></h3>
                    <a href="<?php echo htmlspecialchars($link); ?>" 
                       class="inline-block border border-white px-8 py-3 hover:bg-white hover:text-black transition" style="border-radius: 50px;">
                        <?php echo htmlspecialchars($offer['button_text']); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
