<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';

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

<?php if (!empty($offers)): 
    // Fetch Styles
    $settingsObj = new Settings();
    $stylesJson = $settingsObj->get('special_offers_styles', '{"bg_color":"#ffffff","heading_color":"#111827","subheading_color":"#4b5563","card_overlay_opacity":"40","card_text_color":"#ffffff","button_text_color":"#ffffff","button_border_color":"#ffffff","button_hover_bg":"#ffffff","button_hover_text":"#000000"}');
    $styles = json_decode($stylesJson, true);
    
    // Defaults to ensure no broken styles
    $styles['card_overlay_opacity'] = $styles['card_overlay_opacity'] ?? '40';
    $styles['bg_color'] = $styles['bg_color'] ?? '#ffffff';
    $styles['heading_color'] = $styles['heading_color'] ?? '#111827';
    $styles['subheading_color'] = $styles['subheading_color'] ?? '#4b5563';
    
    $sectionId = 'offers-section-' . rand(1000, 9999);
?>

<style>
    #<?php echo $sectionId; ?> {
        background-color: <?php echo $styles['bg_color']; ?>;
    }
    #<?php echo $sectionId; ?> .offer-heading {
        color: <?php echo $styles['heading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .offer-subheading {
        color: <?php echo $styles['subheading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .offer-overlay {
        background-color: rgba(0, 0, 0, <?php echo $styles['card_overlay_opacity'] / 100; ?>);
    }
    #<?php echo $sectionId; ?> .offer-title {
        color: <?php echo $styles['card_text_color']; ?>;
    }
    #<?php echo $sectionId; ?> .offer-btn {
        color: <?php echo $styles['button_text_color']; ?>;
        border-color: <?php echo $styles['button_border_color']; ?>;
        border-radius: 50px;
    }
    #<?php echo $sectionId; ?> .offer-btn:hover {
        background-color: <?php echo $styles['button_hover_bg']; ?>;
        color: <?php echo $styles['button_hover_text']; ?>;
        border-color: <?php echo $styles['button_hover_bg']; ?>;
    }
</style>

<section id="<?php echo $sectionId; ?>" class="py-5 md:py-14">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="text-center mb-10">
            <?php if (!empty($sectionHeading)): ?>
                <h2 class="text-3xl md:text-4xl font-bold font-heading mb-3 offer-heading"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <?php endif; ?>
            <?php if (!empty($sectionSubheading)): ?>
                <p class="text-base md:text-lg offer-subheading"><?php echo htmlspecialchars($sectionSubheading); ?></p>
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
                <div class="absolute inset-0 transition offer-overlay"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-white p-6">
                    <h3 class="text-xl md:text-2xl font-heading font-bold mb-4 text-center offer-title"><?php echo htmlspecialchars($offer['title']); ?></h3>
                    <a href="<?php echo htmlspecialchars($link); ?>" 
                       class="inline-block border px-8 py-3 transition offer-btn">
                        <?php echo htmlspecialchars($offer['button_text']); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
