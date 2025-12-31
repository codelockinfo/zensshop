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
        'video' => '', // Dummy video - replace with actual
        'poster' => 'https://demo-milano.myshopify.com/cdn/shop/files/jew2_1.webp?v=1739185331&width=480',
        'link' => url('shop.php?filter=deals')
    ],
    [
        'title' => 'Glamorous Essence',
        'subtitle' => 'EXCLUSIVE DESIGNS',
        'video' => 'https://demo-milano.myshopify.com/cdn/shop/videos/c/vp/6cae339d4a154d37a6ff2daf5d28d3a4/6cae339d4a154d37a6ff2daf5d28d3a4.HD-720p-2.1Mbps-42378036.mp4?v=0', // Dummy video - replace with actual
        'poster' => 'https://demo-milano.myshopify.com/cdn/shop/files/preview_images/6cae339d4a154d37a6ff2daf5d28d3a4.thumbnail.0000000000_small.jpg?v=1739186184',
        'link' => url('shop.php?filter=glamorous')
    ],
    [
        'title' => 'Ethereal Beauty',
        'subtitle' => 'HANDCRAFTED PERFECTION',
        'video' => '', // Dummy video - replace with actual
        'poster' => 'https://demo-milano.myshopify.com/cdn/shop/files/jew2_2.webp?v=1739185348&width=480',
        'link' => url('shop.php?filter=ethereal')
    ],
    [
        'title' => 'Delicate Sparkle',
        'subtitle' => 'GRACEFUL BEAUTY',
        'video' => '', // Dummy video - replace with actual
        'poster' => 'https://demo-milano.myshopify.com/cdn/shop/files/jew2_3.webp?v=1739185348&width=480',
        'link' => url('shop.php?filter=sparkle')
    ]
];
?>

<section class="bg-white">
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
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent opacity-30 transition-opacity"></div>
                
                <!-- Content Overlay -->
                <div class="absolute inset-0 flex flex-col justify-end text-center text-white p-6 z-10">
                    <p class="text-xs uppercase tracking-wider mb-2 opacity-90"><?php echo htmlspecialchars($video['subtitle']); ?></p>
                    <h3 class="text-2xl md:text-3xl font-heading font-bold mb-6 text-white"><?php echo htmlspecialchars($video['title']); ?></h3>
                    <a href="<?php echo htmlspecialchars($video['link']); ?>" 
                       class="inline-block bg-white text-black px-6 py-3 hover:bg-gray-100 transition font-medium text-center video-btn">
                        Shop Now
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


