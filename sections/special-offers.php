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

// Get Section Heading/Subheading (JSON is master)
$sectionHeading = 'Special Offers';
$sectionSubheading = 'Grab limited-time deals on our best products.';

$offersConfigPath = __DIR__ . '/../admin/special_offers_config.json';
if (file_exists($offersConfigPath)) {
    $conf = json_decode(file_get_contents($offersConfigPath), true);
    $sectionHeading = $conf['heading'] ?? $sectionHeading;
    $sectionSubheading = $conf['subheading'] ?? $sectionSubheading;
} elseif (!empty($offers)) {
    // Fallback to denormalized data in first row
    $sectionHeading = $offers[0]['heading'] ?? $sectionHeading;
    $sectionSubheading = $offers[0]['subheading'] ?? $sectionSubheading;
}
?>

<?php if (!empty($offers)): ?>
<section class="py-5 md:py-14 bg-white">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="text-center mb-10">
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
				$imgSrc = getImageUrl($offer['image'] ?? '');
				
				// Resolve Link URL
				$link = $offer['link'];
				if (!empty($link) && !preg_match('/^https?:\/\//', $link)) {
					$link = $baseUrl . '/' . ltrim($link, '/');
				}
			?>
            <div class="relative group overflow-hidden rounded-lg">
                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>" 
                     class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                     onerror="this.src='https://placehold.co/600x600?text=Offer+Image'">
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
<?php endif; ?>
