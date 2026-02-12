<?php
ob_start(); // Buffer output
session_start();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();

// PRG: Fetch Flash Messages
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// --- Store ID Filtering ---
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Fetch Current Settings
$videosData = $db->fetchAll("SELECT * FROM section_videos WHERE store_id = ? ORDER BY sort_order ASC", [$storeId]);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // INTEGRITY CHECK: Did we receive the expected structural data?
    if (!isset($_POST['check_submit'])) {
        $error = "Critical Error: Submission failed. The data was lost, likely because the total upload size exceeds the server's limit (" . ini_get('post_max_size') . "). Please upload smaller files or use external URLs.";
    } 
    else {
        try {
            $heading = $_POST['heading'] ?? 'Video Reels';
            $subheading = $_POST['subheading'] ?? 'Watch our latest stories';
            $h = $heading;
            $s = $subheading;

            // Save Arrow & Heading Config
            $videoConfig = [
                'show_arrows' => isset($_POST['show_arrows']),
                'heading' => $heading,
                'subheading' => $subheading
            ];
            file_put_contents(__DIR__ . '/video_config.json', json_encode($videoConfig));

            $uploadDir = __DIR__ . '/../assets/uploads/videos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $imgUploadDir = __DIR__ . '/../assets/uploads/posters/';
            if (!is_dir($imgUploadDir)) mkdir($imgUploadDir, 0777, true);

            // Input Arrays
            $titles = $_POST['title'] ?? [];
            $subtitles = $_POST['subtitle'] ?? [];
            $links = $_POST['link'] ?? [];
            $video_urls = $_POST['video_url'] ?? [];
            $poster_urls = $_POST['poster_url'] ?? [];
            $embed_codes = $_POST['embed_code'] ?? [];
            $existing_video_paths = $_POST['existing_video_path'] ?? [];
            $existing_poster_paths = $_POST['existing_poster_path'] ?? [];
            
            $newRows = [];
            
            // Iterate through posted items
            for ($i = 0; $i < count($titles); $i++) {
                $videoPath = $existing_video_paths[$i] ?? '';
                $posterPath = $existing_poster_paths[$i] ?? '';
                
                // Handle Video File Upload
                if (!empty($_FILES['video_file']['name'][$i])) {
                    $fError = $_FILES['video_file']['error'][$i];
                    
                    if ($fError !== UPLOAD_ERR_OK) {
                         if ($fError == UPLOAD_ERR_INI_SIZE || $fError == UPLOAD_ERR_FORM_SIZE) {
                             throw new Exception("File '" . $_FILES['video_file']['name'][$i] . "' exceeds the maximum allowed size (" . ini_get('upload_max_filesize') . ").");
                         }
                         if ($fError != UPLOAD_ERR_NO_FILE) {
                             throw new Exception("Upload error code $fError for file " . $_FILES['video_file']['name'][$i]);
                         }
                    } else {
                        $vName = time() . "_v{$i}_" . basename($_FILES['video_file']['name'][$i]);
                        $vTmp = $_FILES['video_file']['tmp_name'][$i];
                        if (move_uploaded_file($vTmp, $uploadDir . $vName)) {
                            $videoPath = 'assets/uploads/videos/' . $vName;
                        } else {
                            throw new Exception("Failed to move uploaded file: " . $_FILES['video_file']['name'][$i]);
                        }
                    }
                }
                
                // Handle Poster File Upload
                if (!empty($_FILES['poster_file']['name'][$i])) {
                     if ($_FILES['poster_file']['error'][$i] === UPLOAD_ERR_OK) {
                        $pName = time() . "_p{$i}_" . basename($_FILES['poster_file']['name'][$i]);
                        $pTmp = $_FILES['poster_file']['tmp_name'][$i];
                        if (move_uploaded_file($pTmp, $imgUploadDir . $pName)) {
                            $posterPath = 'assets/uploads/posters/' . $pName;
                        }
                     }
                }
                
                $newItem = [
                    'title' => $titles[$i] ?? '',
                    'subtitle' => $subtitles[$i] ?? '',
                    'link_url' => preg_replace('/\.php(\?|$)/', '$1', $links[$i] ?? ''),
                    'embed_code' => $embed_codes[$i] ?? '',
                    'video_url' => $videoPath ?: ($video_urls[$i] ?? ''),
                    'poster_url' => $posterPath ?: ($poster_urls[$i] ?? ''),
                    'sort_order' => $i
                ];
                
                $newRows[] = $newItem;
            }
            
            // Transaction: Replace All for this store
            $db->beginTransaction(); 
            $db->execute("DELETE FROM section_videos WHERE store_id = ?", [$storeId]);
            
            foreach ($newRows as $row) {
                // Incorporating the section heading/subheading into each row
                $sql = "INSERT INTO section_videos (title, subtitle, video_url, poster_url, link_url, embed_code, sort_order, heading, subheading, store_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->execute($sql, [
                    $row['title'],
                    $row['subtitle'],
                    $row['video_url'],
                    $row['poster_url'],
                    $row['link_url'],
                    $row['embed_code'],
                    $row['sort_order'],
                    $h, // Apply section heading to all rows
                    $s,  // Apply section subheading to all rows
                    $storeId
                ]);
            }
            $db->commit();
            
            $_SESSION['flash_success'] = "Videos section updated successfully!";
            header("Location: " . $baseUrl . '/admin/shorts');
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollback();
            $error = "Error saving: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Homepage Videos';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6 sticky top-0 z-50 bg-[#f7f8fc] p-4 ">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Homepage Video Reels</h1>
            <p class="text-gray-600">Manage video reels (Slider enabled).</p>
        </div>
        <div class="flex gap-3">
             <button type="button" onclick="addVideoRow()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-plus"></i> Add Video
            </button>
             <button type="submit" form="videoForm" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 transition btn-loading">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700 font-bold">About Video Uploads</p>
                <p class="text-sm text-blue-600">
                    For the best experience, use MP4 videos. 
                    If your video is large, consider using an external URL or Embed Code.
                </p>
                <p class="text-xs text-blue-800 mt-1">
                    <strong>Server Limits:</strong> Max Upload Size: <?php echo ini_get('upload_max_filesize'); ?>, Max Post Size: <?php echo ini_get('post_max_size'); ?>.
                </p>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars((string)$success); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars((string)$error); ?>
        </div>
    <?php endif; ?>

    <?php
    // Load Config
    $videoConfigPath = __DIR__ . '/video_config.json';
    $showVideoArrows = true;
    $savedConfig = null;
    
    if (file_exists($videoConfigPath)) {
        $savedConfig = json_decode(file_get_contents($videoConfigPath), true);
        $showVideoArrows = isset($savedConfig['show_arrows']) ? $savedConfig['show_arrows'] : true;
    }

    // Fetch Section Settings - Prioritize JSON for Admin Form persistence
    $sectionSettings = $db->fetchOne("SELECT heading, subheading FROM section_videos WHERE store_id = ? LIMIT 1", [$storeId]);
    
    if ($savedConfig !== null) {
        // JSON is master for persistence
        $section_heading = $savedConfig['heading'] ?? ($sectionSettings['heading'] ?? 'Video Reels');
        $section_subheading = $savedConfig['subheading'] ?? ($sectionSettings['subheading'] ?? 'Watch our latest stories.');
    } else {
        $section_heading = $sectionSettings['heading'] ?? 'Video Reels';
        $section_subheading = $sectionSettings['subheading'] ?? 'Watch our latest stories.';
    }
    
    // For the form fields below
    $sectionSettings = [
        'heading' => $section_heading,
        'subheading' => $section_subheading
    ];
    ?>

    <form method="POST" enctype="multipart/form-data" id="videoForm" class="space-y-6">
        <input type="hidden" name="check_submit" value="1">

        <!-- Section Settings Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8 transform transition hover:shadow-md">
            <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Section Configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Section Heading</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <i class="fas fa-heading"></i>
                        </span>
                        <input type="text" name="heading" value="<?php echo htmlspecialchars((string)($sectionSettings['heading'] ?? '')); ?>" 
                               class="w-full pl-10 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" 
                               placeholder="e.g. Video Reels">
                    </div>
                </div>
                
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Section Subheading</label>
                     <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <i class="fas fa-align-left"></i>
                        </span>
                        <input type="text" name="subheading" value="<?php echo htmlspecialchars((string)($sectionSettings['subheading'] ?? '')); ?>" 
                               class="w-full pl-10 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" 
                               placeholder="e.g. Watch our latest stories">
                    </div>
                </div>
                
                <div class="col-span-1 md:col-span-2 border-t pt-4 mt-2">
                     <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-red-50 rounded-lg text-red-600"><i class="fas fa-arrows-alt-h"></i></div>
                            <div>
                                <h3 class="font-bold text-gray-700">Slider Navigation Arrows</h3>
                                <p class="text-xs text-gray-500">Show/Hide left and right arrows on the video slider.</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_arrows" class="sr-only peer" <?php echo $showVideoArrows ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div id="videoContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($videosData as $index => $v): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 video-card relative group">
                    <button type="button" onclick="removeCard(this)" class="absolute top-2 right-2 text-red-500 hover:bg-red-50 p-2 rounded-full transition z-10">
                        <i class="fas fa-trash"></i>
                    </button>
                    
                    <h3 class="font-bold text-lg mb-4 text-gray-700 border-b pb-2 drag-handle cursor-move"><i class="fas fa-grip-vertical mr-2 text-gray-400"></i> Item <?php echo $index + 1; ?></h3>
                    
                    <input type="hidden" name="existing_video_path[]" value="<?php echo htmlspecialchars((string)($v['video_url'] ?? '')); ?>">
                    <input type="hidden" name="existing_poster_path[]" value="<?php echo htmlspecialchars((string)($v['poster_url'] ?? '')); ?>">

                    <!-- Text Fields -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold mb-1">Title</label>
                            <input type="text" name="title[]" value="<?php echo htmlspecialchars((string)($v['title'] ?? '')); ?>" class="w-full border p-2 rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">Subtitle</label>
                            <input type="text" name="subtitle[]" value="<?php echo htmlspecialchars((string)($v['subtitle'] ?? '')); ?>" class="w-full border p-2 rounded text-sm">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold mb-1">Link URL (Shop Now)</label>
                            <input type="text" name="link[]" value="<?php echo htmlspecialchars((string)($v['link_url'] ?? '')); ?>" class="w-full border p-2 rounded text-sm" placeholder="e.g. /shop">
                        </div>
                    </div>

                    <!-- Video Source -->
                    <div class="mb-4 bg-gray-50 p-3 rounded">
                        <label class="block text-sm font-bold mb-2">Video (Internal MP4)</label>
                        <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:bg-gray-50 transition" onclick="triggerFileClick(this, event)">
                            
                            <!-- Preview Container -->
                            <?php 
                                $hasVideo = !empty($v['video_url']) && !preg_match('/^https?:\/\//', $v['video_url']);
                                $vidUrl = $hasVideo ? $baseUrl . '/' . $v['video_url'] : '';
                            ?>
                            <video src="<?php echo $vidUrl; ?>" class="max-h-32 w-full object-cover rounded mb-2 <?php echo $hasVideo ? '' : 'hidden'; ?> preview-video" autoplay loop muted playsinline></video>
                            
                            <!-- Placeholder -->
                            <div class="text-center placeholder-box <?php echo $hasVideo ? 'hidden' : ''; ?>">
                                <i class="fas fa-video text-3xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-500 font-semibold selection-text">All Uploaded Video</p>
                                <p class="text-xs text-gray-400 mt-1">Click to upload MP4</p>
                            </div>
                            
                            <input type="file" name="video_file[]" accept="video/mp4" class="hidden" onclick="event.stopPropagation()" onchange="previewVideoFile(this)">
                        </div>
                        
                        <div class="text-center text-xs text-gray-400 my-2">- OR -</div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">External Video URL</label>
                            <input type="text" name="video_url[]" value="<?php echo htmlspecialchars((string)($v['video_url'] ?? '')); ?>" class="w-full border p-2 rounded text-xs" placeholder="https://...">
                        </div>
                    </div>

                    <!-- Poster Image -->
                    <div class="mb-4 bg-gray-50 p-3 rounded">
                        <label class="block text-sm font-bold mb-2">Poster Image</label>
                        <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:bg-gray-50 transition" onclick="triggerFileClick(this, event)">
                            
                            <!-- Preview Container -->
                            <?php 
                                $hasPoster = !empty($v['poster_url']);
                                $posterPath = $v['poster_url'];
                                if ($hasPoster) {
                                    if (preg_match('/^https?:\/\//', $posterPath)) {
                                        $posterUrl = $posterPath;
                                    } else {
                                        $posterUrl = $baseUrl . '/' . ltrim($posterPath, '/');
                                    }
                                } else {
                                    $posterUrl = '';
                                }
                            ?>
                            <img src="<?php echo $posterUrl; ?>" class="max-h-32 w-auto object-contain rounded mb-2 <?php echo $hasPoster ? '' : 'hidden'; ?> preview-img">
                            
                            <!-- Placeholder -->
                            <div class="text-center placeholder-box <?php echo $hasPoster ? 'hidden' : ''; ?>">
                                <i class="fas fa-image text-3xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-500 font-semibold">Click to upload Image</p>
                            </div>
                            
                            <input type="file" name="poster_file[]" accept="image/*" class="hidden" onclick="event.stopPropagation()" onchange="previewImageFile(this)">
                        </div>
                        
                        <div class="text-center text-xs text-gray-400 my-2">- OR -</div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Image URL</label>
                            <input type="text" name="poster_url[]" value="<?php echo htmlspecialchars((string)($v['poster_url'] ?? '')); ?>" class="w-full border p-2 rounded text-xs">
                        </div>
                    </div>
                    
                    <!-- Instagram Embed option -->
                    <div class="mt-4 pt-4 border-t">
                         <label class="block text-xs font-bold mb-1">Instagram Embed Code</label>
                          <textarea name="embed_code[]" rows="2" class="w-full border p-2 rounded text-xs font-mono" placeholder="<iframe>..."><?php echo htmlspecialchars((string)($v['embed_code'] ?? '')); ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if(empty($videosData)): ?>
            <div id="emptyMsg" class="text-center py-12 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
                No videos yet. Click "Add Video" to start.
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Template for New Row -->
<template id="rowTemplate">
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 video-card relative group animate-fade-in">
        <button type="button" onclick="removeCard(this)" class="absolute top-2 right-2 text-red-500 hover:bg-red-50 p-2 rounded-full transition z-10">
            <i class="fas fa-trash"></i>
        </button>
        
        <h3 class="font-bold text-lg mb-4 text-gray-700 border-b pb-2 drag-handle cursor-move"><i class="fas fa-grip-vertical mr-2 text-gray-400"></i> New Item</h3>
        
        <input type="hidden" name="existing_video_path[]" value="">
        <input type="hidden" name="existing_poster_path[]" value="">

        <!-- Text Fields -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-bold mb-1">Title</label>
                <input type="text" name="title[]" value="" class="w-full border p-2 rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">Subtitle</label>
                <input type="text" name="subtitle[]" value="" class="w-full border p-2 rounded text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-bold mb-1">Link URL (Shop Now)</label>
                <input type="text" name="link[]" value="" class="w-full border p-2 rounded text-sm" placeholder="e.g. /shop">
            </div>
        </div>

        <!-- Video Source -->
        <div class="mb-4 bg-gray-50 p-3 rounded">
            <label class="block text-sm font-bold mb-2">Video (Internal MP4)</label>
            <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:bg-gray-50 transition" onclick="triggerFileClick(this, event)">
                
                <video src="" class="max-h-32 w-full object-cover rounded mb-2 hidden preview-video" autoplay loop muted playsinline></video>
                
                <div class="text-center placeholder-box">
                    <i class="fas fa-video text-3xl text-gray-400 mb-2"></i>
                     <p class="text-sm text-gray-500 font-semibold selection-text">All Uploaded Video</p>
                    <p class="text-xs text-gray-400 mt-1">Click to upload MP4</p>
                </div>
                
                <input type="file" name="video_file[]" accept="video/mp4" class="hidden" onclick="event.stopPropagation()" onchange="previewVideoFile(this)">
            </div>
            
            <div class="text-center text-xs text-gray-400 my-2">- OR -</div>
            
            <div>
                <label class="block text-xs text-gray-500 mb-1">External Video URL</label>
                <input type="text" name="video_url[]" value="" class="w-full border p-2 rounded text-xs" placeholder="https://...">
            </div>
        </div>

        <!-- Poster Image -->
        <div class="mb-4 bg-gray-50 p-3 rounded">
            <label class="block text-sm font-bold mb-2">Poster Image</label>
            <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:bg-gray-50 transition" onclick="triggerFileClick(this, event)">
                
                <img src="" class="max-h-32 w-auto object-contain rounded mb-2 hidden preview-img">
                
                <div class="text-center placeholder-box">
                    <i class="fas fa-image text-3xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-500 font-semibold">Click to upload Image</p>
                </div>
                
                <input type="file" name="poster_file[]" accept="image/*" class="hidden" onclick="event.stopPropagation()" onchange="previewImageFile(this)">
            </div>
            
            <div class="text-center text-xs text-gray-400 my-2">- OR -</div>
            
            <div>
                <label class="block text-xs text-gray-500 mb-1">Image URL</label>
                <input type="text" name="poster_url[]" value="" class="w-full border p-2 rounded text-xs">
            </div>
        </div>
        
        <div class="mt-4 pt-4 border-t">
                <label class="block text-xs font-bold mb-1">Instagram Embed Code</label>
                <textarea name="embed_code[]" rows="2" class="w-full border p-2 rounded text-xs font-mono" placeholder="<iframe>..."></textarea>
        </div>
    </div>
</template>

<script>
    // --- Drag and Drop Logic ---
    let draggedVideo = null;

    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.video-card');
        cards.forEach(card => enableDragAndDrop(card));
    });

    function enableDragAndDrop(card) {
        card.setAttribute('draggable', 'true');
        const handle = card.querySelector('.drag-handle');
        
        card.addEventListener('dragstart', function(e) {
            draggedVideo = card;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', ''); // Firefox hack
            setTimeout(() => card.classList.add('opacity-50'), 0);
        });

        card.addEventListener('dragend', function(e) {
            card.classList.remove('opacity-50');
            draggedVideo = null;
        });

        card.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (draggedVideo === card) return;
        });

        card.addEventListener('drop', function(e) {
            e.preventDefault();
            if (!draggedVideo || draggedVideo === card) return;

            const container = document.getElementById('videoContainer');
            const allCards = Array.from(container.querySelectorAll('.video-card'));
            const fromIndex = allCards.indexOf(draggedVideo);
            const toIndex = allCards.indexOf(card);

            if (fromIndex < toIndex) {
                container.insertBefore(draggedVideo, card.nextSibling);
            } else {
                container.insertBefore(draggedVideo, card);
            }
            
            updateItemLabels();
        });
    }

    function updateItemLabels() {
        const cards = document.querySelectorAll('.video-card');
        cards.forEach((card, index) => {
            const title = card.querySelector('.drag-handle');
            if(title) {
                title.innerHTML = `<i class="fas fa-grip-vertical mr-2 text-gray-400"></i> Item ${index + 1}`;
            }
        });
    }

    function addVideoRow() {
        const container = document.getElementById('videoContainer');
        const template = document.getElementById('rowTemplate');
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
        
        const newCard = container.lastElementChild;
        enableDragAndDrop(newCard); 
        
        const emptyMsg = document.getElementById('emptyMsg');
        if(emptyMsg) emptyMsg.style.display = 'none';
        
        updateItemLabels();
        newCard.scrollIntoView({ behavior: 'smooth' });
    }

    function removeCard(btn) {
        const card = btn.closest('.video-card');
        const container = card.parentNode;
        card.remove();
        
        updateItemLabels();

        const emptyMsg = document.getElementById('emptyMsg');
        if (container.querySelectorAll('.video-card').length === 0 && emptyMsg) {
            emptyMsg.style.display = 'block';
        }
    }
    
    function triggerFileClick(div, event) {
        if (event.target.tagName === 'INPUT') return;
        const input = div.querySelector('input[type="file"]');
        if(input) input.click();
    }
    
    function previewImageFile(input) {
        if (input.files && input.files[0]) {
            const container = input.closest('div'); 
            const img = container.querySelector('.preview-img');
            const placeholder = container.querySelector('.placeholder-box');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                if (img) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                }
                if (placeholder) {
                    placeholder.classList.add('hidden');
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function previewVideoFile(input) {
        if (input.files && input.files[0]) {
            const container = input.closest('div');
            const video = container.querySelector('.preview-video');
            const placeholder = container.querySelector('.placeholder-box');
            
            const url = URL.createObjectURL(input.files[0]);
            
            if (video) {
                video.src = url;
                video.classList.remove('hidden');
                video.load();
            }
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
