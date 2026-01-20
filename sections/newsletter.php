<?php
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

// Fetch settings
$data = $db->fetchOne("SELECT * FROM section_newsletter LIMIT 1");

if (!$data) return;

$bgImage = $data['background_image'] ? getBaseUrl() . '/' . $data['background_image'] : '';
$heading = $data['heading'] ?? 'Join our family';
$subheading = $data['subheading'] ?? 'Promotions, new products and sales. Directly to your inbox.';
$btnText = $data['button_text'] ?? 'Subscribe';
$footer = $data['footer_content'] ?? '';

// Determine Background Style
$bgStyle = $bgImage ? "background-image: url('{$bgImage}');" : "background-color: #f3f4f6;";
?>

<section class="py-20 md:py-24 bg-cover bg-center bg-no-repeat relative flex items-center justify-center min-h-[400px]" style="<?php echo $bgStyle; ?>">
    <!-- Overlay if image is used -->
    <?php if($bgImage): ?>
    <div class="absolute inset-0 bg-black bg-opacity-10 backdrop-blur-[2px]"></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-2xl p-8 md:p-10 max-w-2xl w-full mx-4 relative z-10 text-center">
        <h2 class="text-3xl font-bold mb-3 text-gray-900"><?php echo htmlspecialchars($heading); ?></h2>
        
        <?php if($subheading): ?>
        <p class="text-gray-600 mb-8"><?php echo htmlspecialchars($subheading); ?></p>
        <?php endif; ?>

        <form id="globalNewsletterForm" class="flex flex-col md:flex-row gap-3 mb-6">
            <input type="email" name="email" placeholder="Your email address..." 
                class="flex-grow border border-gray-300 rounded px-4 py-3 focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent" 
                required>
            <button type="submit" 
                class="bg-black text-white font-bold px-8 py-3 rounded hover:bg-gray-800 transition transform hover:scale-105">
                <?php echo htmlspecialchars($btnText); ?>
            </button>
        </form>

        <div id="globalNewsletterMessage" class="hidden text-sm mb-4"></div>

        <?php if($footer): ?>
        <div class="text-xs text-gray-500 leading-relaxed max-w-lg mx-auto">
            <?php echo $footer; // Allow HTML for links ?>
        </div>
        <?php endif; ?>
    </div>
</section>
