<?php
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

// Fetch settings
$data = $db->fetchOne("SELECT * FROM section_newsletter LIMIT 1");

if (!$data) return;

// Fetch Styles
require_once __DIR__ . '/../classes/Settings.php';
$settingsObj = new Settings();
$stylesJson = $settingsObj->get('newsletter_styles', '{"bg_overlay_opacity":"10","heading_color":"#111827","subheading_color":"#4b5563","button_bg_color":"#000000","button_text_color":"#ffffff"}');
$styles = json_decode($stylesJson, true);
$sectionId = 'newsletter-' . rand(1000, 9999);

$bgImage = $data['background_image'] ? getBaseUrl() . '/' . $data['background_image'] : '';
$heading = $data['heading'] ?? 'Join our family';
$subheading = $data['subheading'] ?? 'Promotions, new products and sales. Directly to your inbox.';
$btnText = $data['button_text'] ?? 'Subscribe';
$footer = $data['footer_content'] ?? '';

// Determine Background Style
$bgStyle = $bgImage ? "background-image: url('{$bgImage}');" : "background-color: #f3f4f6;";
?>

<style>
    #<?php echo $sectionId; ?> .newsletter-overlay {
        background-color: rgba(0, 0, 0, <?php echo ($styles['bg_overlay_opacity'] / 100); ?>);
    }
    #<?php echo $sectionId; ?> .newsletter-heading {
        color: <?php echo $styles['heading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .newsletter-subheading {
        color: <?php echo $styles['subheading_color']; ?>;
    }
    #<?php echo $sectionId; ?> .newsletter-btn {
        background-color: <?php echo $styles['button_bg_color']; ?> !important;
        color: <?php echo $styles['button_text_color']; ?> !important;
    }
    /* Hover state for button: slightly lighten or darken */
    #<?php echo $sectionId; ?> .newsletter-btn:hover {
        opacity: 0.9;
    }
    #<?php echo $sectionId; ?> .newsletter-card {
        background-color: <?php echo $styles['card_bg_color'] ?? '#ffffff'; ?>;
    }
    #<?php echo $sectionId; ?> .newsletter-input {
        background-color: <?php echo $styles['input_bg_color'] ?? '#ffffff'; ?> !important;
    }
</style>

<section id="<?php echo $sectionId; ?>" class="py-20 md:py-24 bg-cover bg-center bg-no-repeat relative flex items-center justify-center min-h-[400px]" style="<?php echo $bgStyle; ?>">
    <!-- Overlay if image is used -->
    <?php if($bgImage): ?>
    <div class="absolute inset-0 newsletter-overlay backdrop-blur-[2px]"></div>
    <?php endif; ?>

    <div class="newsletter-card rounded-xl shadow-2xl p-8 md:p-10 max-w-2xl w-full mx-4 relative z-10 text-center">
        <h2 class="text-3xl font-bold mb-3 newsletter-heading"><?php echo htmlspecialchars($heading); ?></h2>
        
        <?php if($subheading): ?>
        <p class="mb-8 newsletter-subheading"><?php echo htmlspecialchars($subheading); ?></p>
        <?php endif; ?>

        <form id="globalNewsletterForm" method="POST" class="flex flex-col md:flex-row gap-3 mb-6">
            <input type="email" name="email" id="newsletterEmail" placeholder="Your email address..." 
                class="newsletter-input flex-grow border border-gray-300 rounded px-4 py-3 focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent" 
                required>
            <button type="submit" id="newsletterSubmit"
                class="font-bold px-8 py-3 rounded transform hover:scale-105 transition newsletter-btn">
                <?php echo htmlspecialchars($btnText); ?>
            </button>
        </form>

        <div id="globalNewsletterMessage" class="hidden text-sm mb-4 p-3 rounded"></div>

        <?php if($footer): ?>
        <div class="text-xs text-gray-500 leading-relaxed max-w-lg mx-auto">
            <?php echo $footer; // Allow HTML for links ?>
        </div>
        <?php endif; ?>
    </div>
</section>
