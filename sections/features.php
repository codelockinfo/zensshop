<?php
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

// Fetch active features
$features = $db->fetchAll("SELECT * FROM section_features ORDER BY sort_order ASC LIMIT 3");

if (empty($features)) return;
?>

<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <!-- Render as Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center max-w-full mx-auto divide-y md:divide-y-0 md:divide-x divide-gray-100">
            <?php foreach ($features as $f): ?>
            <div class="flex flex-col items-center p-6">
                <!-- Icon Container -->
                <div class="mb-6 text-gray-800"> <!-- Default Color -->
                    <?php echo $f['icon']; ?>
                </div>
                
                <h3 class="text-xl font-bold mb-3 uppercase tracking-wide"><?php echo htmlspecialchars($f['heading']); ?></h3>
                
                <p class="text-gray-500 leading-relaxed max-w-sm">
                    <?php echo nl2br(htmlspecialchars($f['content'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
