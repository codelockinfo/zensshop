<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();

// Fetch settings (Store Specific)
$philosophy = $db->fetchOne("SELECT * FROM philosophy_section WHERE (store_id = ? OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1", [CURRENT_STORE_ID]);

// If not active or no data, return empty (hide section)
if (!$philosophy || (isset($philosophy['active']) && $philosophy['active'] == 0)) {
    return;
}

$heading = $philosophy['heading'] ?? '';
$content = $philosophy['content'] ?? '';
$linkText = $philosophy['link_text'] ?? '';
$linkUrl = $philosophy['link_url'] ?? '#';
$bgColor = $philosophy['background_color'] ?? '#384135';
$textColor = $philosophy['text_color'] ?? '#eee4d3';

// Resolve Link URL
if ($linkUrl && !preg_match('/^https?:\/\//', $linkUrl) && strpos($linkUrl, '#') !== 0) {
    // If it's a relative path, ensure it has base url
    $linkUrl = $baseUrl . '/' . ltrim($linkUrl, '/');
} elseif ($linkUrl && strpos($linkUrl, '#') === 0) {
    // Anchor link, prepend base url if needed or keep as is? 
    // Usually anchor links like '#' or '#section' work on current page, but if this section is loaded via AJAX on home, it's fine.
    // But if it's '#', let's just make it base url + #
    $linkUrl = $baseUrl . $linkUrl;
}
?>

<section class="py-16 md:py-24" style="background-color: <?php echo htmlspecialchars($bgColor); ?>; color: <?php echo htmlspecialchars($textColor); ?>;">
    <div class="container mx-auto px-4 text-center">
        <?php if ($heading): ?>
            <h2 class="text-3xl md:text-4xl font-heading mb-6 tracking-wide" style="color: <?php echo htmlspecialchars($textColor); ?>;"><?php echo htmlspecialchars($heading); ?></h2>
        <?php endif; ?>

        <?php if ($content): ?>
            <div class="philosophy-content text-2xl md:text-3xl max-w-6xl mx-auto mb-8 leading-relaxed font-light" style="line-height: 1.6; color: <?php echo htmlspecialchars($textColor); ?>;">
                <?php echo $content; ?>
            </div>
        <?php endif; ?>

        <?php if ($linkText): ?>
            <a href="<?php echo htmlspecialchars($linkUrl); ?>" class="philosophy-link text-sm md:text-md uppercase hover:opacity-80 transition tracking-wider" style="color: <?php echo htmlspecialchars($textColor); ?>; border-bottom: 1px solid <?php echo htmlspecialchars($textColor); ?>; padding-bottom: 2px;">
                <?php echo htmlspecialchars($linkText); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
