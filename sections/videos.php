<?php
// Ensure dependencies are loaded for standalone access
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();

// Fetch videos from database
$videos = $db->fetchAll("SELECT * FROM section_videos ORDER BY sort_order ASC");

// Fallback if empty
if (empty($videos)) {
    $videos = [
        [
            'title' => 'Limited Time Deals',
            'subtitle' => 'SPECIAL 50% OFF',
            'link_url' => 'shop.php?filter=deals',
            'poster_url' => 'https://demo-milano.myshopify.com/cdn/shop/files/jew2_1.webp?v=1739185331&width=480'
        ]
        // ...
    ];
}
// Get Section Heading/Subheading from the first item
$sectionHeading = $videos[0]['heading'] ?? 'Video Reels';
$sectionSubheading = $videos[0]['subheading'] ?? 'Watch our latest stories';
?>

<section class="bg-white py-12 relative group/section" id="uniqueVideoSection">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="text-center mb-10">
            <?php if (!empty($sectionHeading)): ?>
                <h2 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-3"><?php echo htmlspecialchars($sectionHeading); ?></h2>
            <?php endif; ?>
            <?php if (!empty($sectionSubheading)): ?>
                <p class="text-gray-600 text-base md:text-lg"><?php echo htmlspecialchars($sectionSubheading); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="relative">
            <!-- Navigation Buttons -->
            <button id="videoSectionPrev" type="button" class="absolute left-6 top-1/2 -translate-y-1/2 z-50 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition focus:outline-none backdrop-blur-sm cursor-pointer border border-gray-100 hidden md:flex">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="videoSectionNext" type="button" class="absolute right-6 top-1/2 -translate-y-1/2 z-50 bg-white shadow-lg rounded-full w-12 h-12 flex items-center justify-center text-gray-800 hover:text-primary hover:bg-gray-50 transition focus:outline-none backdrop-blur-sm cursor-pointer border border-gray-100 hidden md:flex">
                <i class="fas fa-chevron-right"></i>
            </button>

            <!-- Video Section Slider Wrapper (Overflow Hidden) -->
            <div class="video-section-slider overflow-hidden w-full relative z-10">
                <!-- Track -->
                <div id="videoSectionSlider" class="flex transition-transform duration-500 will-change-transform" style="gap: 24px;">
                    <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $index => $video): 
                        $title = $video['title'] ?? '';
                        $subtitle = $video['subtitle'] ?? '';
                        
                        $rawLink = $video['link_url'] ?? '';
                        $link = '#';
                        if (!empty($rawLink)) {
                            if (preg_match('/^https?:\/\//', $rawLink)) {
                                $link = $rawLink;
                            } else {
                                $link = url(ltrim($rawLink, '/')); 
                            }
                        }

                        $poster = $video['poster_url'] ?? '';
                        if (!empty($poster) && !preg_match('/^https?:\/\//', $poster)) {
                             $poster = $baseUrl . '/' . ltrim($poster, '/');
                        }

                        $embed = $video['embed_code'] ?? '';
                        $videoSrc = $video['video_url'] ?? '';
                        $isPlayable = false;

                        if (!empty($videoSrc)) {
                            $isPlayable = true; 
                            if (!preg_match('/^https?:\/\//', $videoSrc)) {
                                $videoSrc = $baseUrl . '/' . ltrim($videoSrc, '/');
                            }
                        }
                    ?>
                    
                    <!-- Slide Item -->
                    <div class="video-slide flex-shrink-0 w-[280px] md:w-[23.5%] h-[400px] md:h-[600px] video-card">
                        <div class="relative w-full h-full rounded-lg overflow-hidden border border-gray-100 shadow-sm hover:shadow-md bg-black transition-all duration-300">
                        
                            <?php if (!empty($embed)): ?>
                                <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-black">
                                     <div class="w-full h-full pointer-events-auto">
                                        <?php echo $embed; ?>
                                     </div>
                                </div>
                            <?php elseif ($isPlayable): ?>
                                <video 
                                    class="absolute inset-0 w-full h-full object-cover z-0"
                                    autoplay loop muted playsinline
                                    poster="<?php echo htmlspecialchars($poster); ?>">
                                    <?php if($videoSrc): ?>
                                    <source src="<?php echo htmlspecialchars($videoSrc); ?>" type="video/mp4">
                                    <?php endif; ?>
                                    <?php if($poster): ?>
                                    <img src="<?php echo htmlspecialchars($poster); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </video>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent opacity-60 pointer-events-none z-10"></div>
                                <!-- No Link on Card Overlay -->
                                
                            <?php else: ?>
                                <!-- Image Card: No Link on Card itself -->
                                <div class="absolute inset-0 block w-full h-full">
                                    <?php if($poster): ?>
                                        <img src="<?php echo htmlspecialchars($poster); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                            <i class="fas fa-play text-4xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60 pointer-events-none"></div>
                                </div>
                            <?php endif; ?>

                            <!-- Text Content -->
                            <?php if (empty($embed)): ?>
                            <div class="absolute inset-x-0 bottom-0 p-8 text-center text-white z-20 pointer-events-none">
                                <?php if($subtitle): ?>
                                <p class="text-xs uppercase tracking-widest mb-2 opacity-100 font-semibold text-white drop-shadow-md"><?php echo htmlspecialchars($subtitle); ?></p>
                                <?php endif; ?>
                                <?php if($title): ?>
                                <h3 class="text-2xl font-bold mb-4 font-heading leading-tight text-white drop-shadow-lg"><?php echo htmlspecialchars($title); ?></h3>
                                <?php endif; ?>
                                
                                <?php if($link !== '#' && !empty($rawLink)): ?>
                                <!-- Button: Has the Link. Pointer Events Auto to allow click. -->
                                <a href="<?php echo htmlspecialchars($link); ?>" class="inline-block bg-white text-black text-sm px-6 py-3 rounded-full font-bold hover:bg-gray-100 transition shadow-lg pointer-events-auto">
                                    Shop Now
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
