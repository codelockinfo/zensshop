<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';

$db = Database::getInstance();
$settingsObj = new Settings();

// Fetch active features (Store Specific)
$features = $db->fetchAll("SELECT * FROM section_features WHERE (store_id = ? OR store_id IS NULL) ORDER BY sort_order ASC LIMIT 3", [CURRENT_STORE_ID]);

if (empty($features)) return;

$section_bg = $settingsObj->get('features_section_bg', '#ffffff');
$section_text = $settingsObj->get('features_section_text', '#000000');
?>

<section class="py-8 md:py-14" style="background-color: <?php echo htmlspecialchars($section_bg); ?>; color: <?php echo htmlspecialchars($section_text); ?>;">
    <div class="container mx-auto px-4 md:px-6">
        <!-- Main White Outer Card with 3D Wave Graphic Background -->
        <div class="bg-white rounded-3xl border border-gray-200/70 shadow-2xl p-4 md:p-8 max-w-7xl mx-auto relative overflow-hidden">
            
            <!-- 3D Wave SVG Graphic Background -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
                <svg class="w-full h-full min-w-[1000px] opacity-80" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="none">
                    <!-- Smooth 3D Wave Gradient Fill -->
                    <path d="M0,160 C320,300 420,40 720,160 C1020,280 1120,60 1440,160 L1440,320 L0,320 Z" fill="url(#wave3d-grad-feat)"/>
                    <!-- 3D Wave Top Highlight Line -->
                    <path d="M0,160 C320,300 420,40 720,160 C1020,280 1120,60 1440,160" stroke="rgba(255,255,255,0.9)" stroke-width="4"/>
                    <path d="M0,162 C320,302 420,42 720,162 C1020,282 1120,62 1440,162" stroke="rgba(0,0,0,0.05)" stroke-width="2"/>
                    <defs>
                        <linearGradient id="wave3d-grad-feat" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#f1f5f9" stop-opacity="0.9"/>
                            <stop offset="60%" stop-color="#f8fafc" stop-opacity="0.6"/>
                            <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- 3 Columns with Vertical Line Dividers on Desktop -->
            <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-200/90 relative z-10">
                <?php foreach ($features as $index => $f): 
                    $headingLower = strtolower($f['heading']);
                ?>
                <div class="group flex flex-col items-center text-center p-6 md:px-8 md:py-4 transition-all duration-300">
                    
                    <!-- 3D Spherical Glass Icon Badge Container -->
                    <div class="relative mb-6 select-none group-hover:scale-105 transition-transform duration-300">
                        <!-- Soft Ambient Glow & 3D Shadow behind circle -->
                        <div class="absolute inset-0 bg-gray-300/40 rounded-full blur-lg scale-110 translate-y-2 pointer-events-none"></div>

                        <!-- 3D Spherical White Glass Circle Badge -->
                        <div class="relative w-20 h-20 md:w-24 md:h-24 rounded-full bg-gradient-to-b from-white via-gray-50 to-gray-100 flex items-center justify-center border border-white/80 shadow-[0_12px_24px_-4px_rgba(0,0,0,0.12),_inset_0_3px_6px_rgba(255,255,255,1),_inset_0_-3px_6px_rgba(0,0,0,0.04)] overflow-hidden">
                            <!-- Gloss Reflection Arc on Top Half -->
                            <div class="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-white/90 to-transparent rounded-t-full pointer-events-none"></div>
                            
                            <div class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center text-gray-900 relative z-10">
                                <?php if ($index == 0 || strpos($headingLower, 'quality') !== false || strpos($headingLower, 'premium') !== false): ?>
                                    <!-- Shield Checkmark Icon -->
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 md:w-12 md:h-12 text-gray-900">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        <path d="M9 12l2 2 4-4"/>
                                    </svg>
                                <?php elseif ($index == 1 || strpos($headingLower, 'contact') !== false || strpos($headingLower, 'phone') !== false): ?>
                                    <!-- Phone Handset with 24/7 Badge -->
                                    <div class="relative flex items-center justify-center">
                                        <svg viewBox="0 0 24 24" fill="currentColor" class="w-9 h-9 md:w-11 md:h-11 text-gray-900 transform -rotate-12">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7a2 2 0 0 1 1.72 2z"/>
                                        </svg>
                                        <span class="absolute -top-4 -right-4 bg-black text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-md tracking-tighter">24/7</span>
                                    </div>
                                <?php else: ?>
                                    <!-- House with Heart Icon -->
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 md:w-12 md:h-12 text-gray-900">
                                        <path d="M3 10.5L12 3l9 7.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9.5z"/>
                                        <path d="M14.5 13a2.25 2.25 0 0 0-3.18 0L11 13.32 10.68 13a2.25 2.25 0 0 0-3.18 3.18L11 19.7l3.5-3.52a2.25 2.25 0 0 0 0-3.18z" fill="currentColor" stroke="none"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Radial Spark Accent Lines on Top-Right -->
                        <div class="absolute -top-2 -right-3 text-gray-900 pointer-events-none">
                            <svg class="w-6 h-6 md:w-7 md:h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="12" y1="2" x2="12" y2="6" />
                                <line x1="18.5" y1="4.5" x2="15.5" y2="7.5" />
                                <line x1="22" y1="11" x2="18" y2="11" />
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Title -->
                    <h3 class="text-base md:text-xl font-bold text-gray-900 tracking-tight mb-2" style="color: <?php echo htmlspecialchars($f['heading_color'] ?? '#111827'); ?>;">
                        <?php echo htmlspecialchars(ucwords(strtolower($f['heading']))); ?>
                    </h3>

                    <!-- Short Underline -->
                    <div class="w-7 h-[2px] bg-gray-900 mx-auto mb-3"></div>

                    <!-- Description -->
                    <p class="text-xs md:text-sm text-gray-500 max-w-xs mx-auto leading-relaxed font-normal">
                        <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                    </p>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
