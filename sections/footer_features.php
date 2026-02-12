<?php
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

// Fetch active features (Store Specific)
// No limit on fetch to allow slider calculation
$footerFeatures = $db->fetchAll("SELECT * FROM footer_features WHERE (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC", [CURRENT_STORE_ID]);

if (empty($footerFeatures)) return;

// Fetch Section Colors
require_once __DIR__ . '/../classes/Settings.php';
$settingsObj = new Settings();
$section_bg = $settingsObj->get('footer_features_section_bg', '#ffffff');
$section_text = $settingsObj->get('footer_features_section_text', '#000000');

$count = count($footerFeatures);
?>

<section class="py-12 border-t border-gray-100" style="background-color: <?php echo htmlspecialchars($section_bg); ?>; color: <?php echo htmlspecialchars($section_text); ?>;">
    <div class="container mx-auto px-4">
        
        <?php if ($count > 4): ?>
            <!-- Slider Layout (Infinite Loop) -->
            <div class="swiper footer-features-slider overflow-hidden relative" style="padding: 20px 0;">
                <div class="swiper-wrapper">
                    <?php foreach ($footerFeatures as $f): ?>
                    <div class="swiper-slide h-auto">
                        <div class="group flex flex-col items-center justify-center text-center p-6 h-full rounded transition-transform hover:scale-105 duration-300" 
                             style="background-color: <?php echo htmlspecialchars($f['bg_color']); ?>; color: <?php echo htmlspecialchars($f['text_color']); ?>;">
                            
                            <div class="mb-4 text-4xl transition-transform duration-500 group-hover:scale-x-[-1]">
                                <?php echo $f['icon']; ?> 
                            </div>
                            
                            <h3 class="text-lg font-bold mb-2" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? $f['text_color']); ?>;"><?php echo htmlspecialchars($f['heading']); ?></h3>
                            <p class="text-sm opacity-90 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Swiper('.footer-features-slider', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    loop: true,
                    autoplay: {
                        delay: 3000,
                        disableOnInteraction: false,
                    },
                    speed: 800,
                    breakpoints: {
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 20,
                        },
                        768: {
                            slidesPerView: 3,
                            spaceBetween: 30,
                        },
                        1024: {
                            slidesPerView: 4,
                            spaceBetween: 30,
                        },
                    }
                });
            });
            </script>

        <?php else: ?>
            <!-- Grid Layout (Fixed Width Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 text-center">
                <?php foreach ($footerFeatures as $f): ?>
                <div class="group flex flex-col items-center justify-center p-6 rounded transition-transform hover:-translate-y-1 duration-300" 
                     style="background-color: <?php echo htmlspecialchars($f['bg_color']); ?>; color: <?php echo htmlspecialchars($f['text_color']); ?>;">
                    
                    <div class="mb-4 text-4xl transition-transform duration-500 group-hover:scale-x-[-1]">
                        <?php echo $f['icon']; ?>
                    </div>
                    
                            <h3 class="text-lg font-bold mb-2" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? $f['text_color']); ?>;"><?php echo htmlspecialchars($f['heading']); ?></h3>
                    <p class="text-sm opacity-90 leading-relaxed max-w-xs mx-auto">
                        <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</section>
