<?php
require_once __DIR__ . '/../includes/functions.php';
$baseUrl = getBaseUrl();

$offers = [
    [
        'title' => 'Limited Time Deals',
        'image' => 'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=600&h=400&fit=crop',
        'link' => url('shop.php?filter=deals')
    ],
    [
        'title' => 'Glamorous Essence',
        'image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&h=400&fit=crop',
        'link' => url('shop.php?filter=glamorous')
    ],
    [
        'title' => 'Ethereal Beauty',
        'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&h=400&fit=crop',
        'link' => url('shop.php?filter=ethereal')
    ],
    [
        'title' => 'Delicate Sparkle',
        'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&h=400&fit=crop',
        'link' => url('shop.php?filter=sparkle')
    ]
];
?>

<section class="py-16 md:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($offers as $offer): ?>
            <div class="relative group overflow-hidden rounded-lg">
                <img src="<?php echo $offer['image']; ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>" 
                     class="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500">
                <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-white p-6">
                    <h3 class="text-2xl font-heading font-bold mb-4"><?php echo htmlspecialchars($offer['title']); ?></h3>
                    <a href="<?php echo $offer['link']; ?>" 
                       class="bg-white text-black px-6 py-2 rounded hover:bg-gray-100 transition">
                        Shop Now
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


