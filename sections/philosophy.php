<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
$baseUrl = getBaseUrl();
$db = Database::getInstance();
$philosophy = $db->fetchOne("SELECT * FROM philosophy_section WHERE (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [CURRENT_STORE_ID]);
if (!$philosophy || (isset($philosophy['active']) && $philosophy['active'] == 0)) {
    return;
}
$heading = $philosophy['heading'] ?? '';
$content = $philosophy['content'] ?? '';
$linkText = $philosophy['link_text'] ?? '';
$linkUrl = $philosophy['link_url'] ?? '#';
$bgColor = $philosophy['background_color'] ?? '#384135';
$textColor = $philosophy['text_color'] ?? '#eee4d3';
if (empty($heading) && empty($content)) {
    return;
}
if ($linkUrl && !preg_match('/^https?:\/\//', $linkUrl) && strpos($linkUrl, '#') !== 0) { 
    $linkUrl = $baseUrl . '/' . ltrim($linkUrl, '/');
} elseif ($linkUrl && strpos($linkUrl, '#') === 0) {
    $linkUrl = $baseUrl . $linkUrl;
}
$bgImgUrl = $baseUrl . '/assets/images/philosophy-bg.png'; 
?>
<section class="py-6 md:py-16 lg:py-20 relative overflow-hidden bg-[size:100%_100%] bg-center bg-no-repeat min-h-[300px] md:min-h-[440px] flex items-center justify-center" style="background-color: #000000; background-image: url('<?php echo $bgImgUrl; ?>'); color: <?php echo htmlspecialchars($textColor); ?>;">
    <div class="absolute inset-0 bg-black/40 md:bg-transparent pointer-events-none"></div>
    <div class="container mx-auto px-4 text-center relative z-10">
        <?php if ($heading): ?>
            <div class="inline-flex items-center justify-center gap-3 mb-4 md:mb-6">
                <span class="w-8 md:w-16 h-[2px] bg-gradient-to-r from-transparent to-yellow-500"></span>
                <h2 class="text-xs md:text-sm font-bold tracking-[0.25em] text-yellow-400 uppercase"><?php echo htmlspecialchars($heading); ?></h2>
                <span class="w-8 md:w-16 h-[2px] bg-gradient-to-l from-transparent to-yellow-500"></span>
            </div>
        <?php endif; ?>
        <?php if ($content): ?>
            <style>
                .philosophy-content p:empty,
                .philosophy-content p:has(> span:empty),
                .philosophy-content p:has(> br:only-child) {
                    display: none !important;
                }
                .philosophy-content p {
                    margin-bottom: 0.5rem;
                }
            </style>
            <div class="philosophy-content text-sm md:text-base lg:text-lg max-w-4xl mx-auto leading-relaxed md:leading-loose font-normal opacity-90 mb-6 md:mb-8" style="color: <?php echo htmlspecialchars($textColor); ?>;">
                <?php echo str_replace('&nbsp;', '', $content); ?>
            </div>
        <?php endif; ?>
        <?php if ($linkText): ?>
            <div class="mt-4 md:mt-6 flex items-center justify-center gap-2">
                <div class="flex gap-1.5 transform -skew-x-[20deg] select-none">
                    <span class="w-2 md:w-2.5 h-10 md:h-11 bg-gradient-to-b from-yellow-300 via-yellow-400 to-yellow-600 rounded-sm shadow-md"></span>
                    <span class="w-2 md:w-2.5 h-10 md:h-11 bg-gradient-to-b from-yellow-300 via-yellow-400 to-yellow-600 rounded-sm shadow-md"></span>
                </div>
                <a href="<?php echo htmlspecialchars($linkUrl); ?>" 
                   class="group relative inline-flex items-center justify-center px-8 md:px-12 py-2.5 md:py-3 transform -skew-x-[20deg] bg-gradient-to-r from-yellow-400 via-amber-400 to-yellow-500 text-black font-extrabold tracking-wider text-sm md:text-base rounded-sm shadow-lg hover:shadow-yellow-500/40 hover:brightness-110 active:scale-95 transition-all duration-300">
                    <span class="transform skew-x-[20deg] flex items-center gap-2">
                        <span><?php echo htmlspecialchars($linkText); ?></span>
                        <i class="fas fa-arrow-right text-xs md:text-sm group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>