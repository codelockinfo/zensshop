<?php
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();
$footerFeatures = $db->fetchAll("SELECT * FROM footer_features WHERE (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC", [CURRENT_STORE_ID]);

if (empty($footerFeatures)) return;
require_once __DIR__ . '/../classes/Settings.php';
$settingsObj = new Settings();
$section_bg = $settingsObj->get('footer_features_section_bg', '#ffffff');
$section_text = $settingsObj->get('footer_features_section_text', '#000000');

$count = count($footerFeatures);
?>
<section class="py-8 md:py-14" style="background-color: <?php echo htmlspecialchars($section_bg); ?>; color: <?php echo htmlspecialchars($section_text); ?>;">
    <div class="container mx-auto px-4 md:px-6">
                <div class="bg-white rounded-3xl border border-gray-200/70 shadow-2xl p-4 md:p-8 max-w-7xl mx-auto relative overflow-hidden">
                        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
                <svg class="w-full h-full min-w-[1000px] opacity-80" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="none">
                    <path d="M0,160 C320,300 420,40 720,160 C1020,280 1120,60 1440,160 L1440,320 L0,320 Z" fill="url(#wave3d-grad)"/>                    <path d="M0,160 C320,300 420,40 720,160 C1020,280 1120,60 1440,160" stroke="rgba(255,255,255,0.9)" stroke-width="4"/>
                    <path d="M0,162 C320,302 420,42 720,162 C1020,282 1120,62 1440,162" stroke="rgba(0,0,0,0.05)" stroke-width="2"/>
                    <defs>
                        <linearGradient id="wave3d-grad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#f1f5f9" stop-opacity="0.9"/>
                            <stop offset="60%" stop-color="#f8fafc" stop-opacity="0.6"/>
                            <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-gray-200/90 relative z-10">
                <?php foreach ($footerFeatures as $index => $f): 
                    $headingLower = strtolower($f['heading']);
                ?>
                <div class="group flex flex-col items-center text-center p-3 md:px-6 md:py-4 transition-all duration-300">
                    
                    <div class="relative mb-3 md:mb-6 select-none group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-gray-300/40 rounded-full blur-lg scale-110 translate-y-2 pointer-events-none"></div>
                        <div class="relative w-14 h-14 md:w-24 md:h-24 rounded-full bg-gradient-to-b from-white via-gray-50 to-gray-100 flex items-center justify-center border border-white/80 shadow-[0_12px_24px_-4px_rgba(0,0,0,0.12),_inset_0_3px_6px_rgba(255,255,255,1),_inset_0_-3px_6px_rgba(0,0,0,0.04)] overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-white/90 to-transparent rounded-t-full pointer-events-none"></div>
                            
                            <div class="w-7 h-7 md:w-12 md:h-12 flex items-center justify-center text-gray-900 relative z-10">
                                <?php if (strpos($headingLower, 'ship') !== false || strpos($headingLower, 'delivery') !== false || $index == 0): ?>
                                    <svg viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 md:w-12 md:h-12 text-gray-900">
                                        <line x1="0" y1="9" x2="6" y2="9" stroke-width="2" />
                                        <line x1="2" y1="13" x2="8" y2="13" stroke-width="2" />
                                        <line x1="4" y1="17" x2="9" y2="17" stroke-width="2" />
                                        <rect x="11" y="4" width="13" height="11" rx="1.5" />
                                        <path d="M24 8h4l3 4v3h-7V8z" />
                                        <circle cx="15" cy="18" r="2.2" fill="currentColor" />
                                        <circle cx="26" cy="18" r="2.2" fill="currentColor" />
                                    </svg>
                                <?php elseif (strpos($headingLower, 'return') !== false || strpos($headingLower, 'easy') !== false || $index == 1): ?>
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 md:w-12 md:h-12 text-gray-900">
                                        <path d="M12 12L3 7.5L12 3L21 7.5L12 12Z"/>
                                        <path d="M3 7.5V17.5L12 22V12"/>
                                        <path d="M21 7.5V17.5L12 22"/>
                                        <path d="M7 6.5C8.5 3.5 12 2 15.5 3C19 4 21.5 7.5 21 11.5"/>
                                        <path d="M5 6.5H8.5V3"/>
                                    </svg>
                                <?php elseif (strpos($headingLower, 'checkout') !== false || strpos($headingLower, 'secure') !== false || $index == 2): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 md:w-12 md:h-12 text-gray-900">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        <path d="M9 12l2 2 4-4" stroke-width="2.5"/>
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 md:w-12 md:h-12 text-gray-900 transform -rotate-12">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7a2 2 0 0 1 1.72 2z"/>
                                        <path d="M15 3a6 6 0 0 1 6 6" stroke-width="2.2"/>
                                        <path d="M15 7a2.5 2.5 0 0 1 2.5 2.5" stroke-width="2.2"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <h3 class="text-[11px] md:text-lg font-bold text-gray-900 tracking-tight mb-1 md:mb-2" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? '#111827'); ?>;">
                        <?php echo htmlspecialchars(ucwords(strtolower($f['heading']))); ?>
                    </h3>
                    <div class="w-5 md:w-7 h-[2px] bg-gray-900 mx-auto mb-1 md:mb-3"></div>
                    <p class="hidden md:block text-xs md:text-sm text-gray-500 leading-relaxed max-w-xs mx-auto font-normal opacity-90">
                        <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>