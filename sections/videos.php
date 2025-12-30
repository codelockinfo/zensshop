<?php
require_once __DIR__ . '/../includes/functions.php';
$baseUrl = getBaseUrl();

// Video section with auto-playing videos
// Note: Replace video URLs with your actual video files
// For production, upload videos to your server or use a CDN
$videos = [
    [
        'title' => 'Limited Time Deals',
        'subtitle' => 'SPECIAL 50% OFF',
        'video' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', // Dummy video - replace with actual
        'poster' => 'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=600&h=800&fit=crop',
        'link' => $baseUrl . '/shop.php?filter=deals'
    ],
    [
        'title' => 'Glamorous Essence',
        'subtitle' => 'EXCLUSIVE DESIGNS',
        'video' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', // Dummy video - replace with actual
        'poster' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=800&fit=crop',
        'link' => $baseUrl . '/shop.php?filter=glamorous'
    ],
    [
        'title' => 'Ethereal Beauty',
        'subtitle' => 'HANDCRAFTED PERFECTION',
        'video' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', // Dummy video - replace with actual
        'poster' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&h=800&fit=crop',
        'link' => $baseUrl . '/shop.php?filter=ethereal'
    ],
    [
        'title' => 'Delicate Sparkle',
        'subtitle' => 'GRACEFUL BEAUTY',
        'video' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4', // Dummy video - replace with actual
        'poster' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=800&fit=crop',
        'link' => $baseUrl . '/shop.php?filter=sparkle'
    ]
];
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($videos as $index => $video): ?>
            <div class="relative group overflow-hidden rounded-lg video-card" style="height: 500px;">
                <!-- Video Element -->
                <video 
                    class="w-full h-full object-cover"
                    autoplay
                    loop
                    muted
                    playsinline
                    poster="<?php echo htmlspecialchars($video['poster']); ?>"
                    id="video-<?php echo $index; ?>">
                    <source src="<?php echo htmlspecialchars($video['video']); ?>" type="video/mp4">
                    <!-- Fallback image if video doesn't load -->
                    <img src="<?php echo htmlspecialchars($video['poster']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" class="w-full h-full object-cover">
                </video>
                
                <!-- Overlay Gradient -->
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent opacity-70 group-hover:opacity-80 transition-opacity"></div>
                
                <!-- Content Overlay -->
                <div class="absolute inset-0 flex flex-col justify-end text-white p-6 z-10">
                    <p class="text-xs uppercase tracking-wider mb-2 opacity-90"><?php echo htmlspecialchars($video['subtitle']); ?></p>
                    <h3 class="text-2xl md:text-3xl font-heading font-bold mb-6"><?php echo htmlspecialchars($video['title']); ?></h3>
                    <a href="<?php echo htmlspecialchars($video['link']); ?>" 
                       class="inline-block bg-white text-black px-6 py-3 rounded-lg hover:bg-gray-100 transition font-medium text-center">
                        Shop Now
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


