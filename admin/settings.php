<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$success = '';
$error = '';

// Determine which page to edit (defaults to 'default')
$selectedSlug = $_GET['page'] ?? 'default';

// Initialize landing page variable
$lp = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_page') {
        // Create new page
        $newName = trim($_POST['new_page_name']);
        $newSlug = trim($_POST['new_page_slug']);
        $productId = intval($_POST['product_id']);
        
        if ($newName && $newSlug && $productId) {
            try {
                $db->insert("INSERT INTO landing_pages (name, slug, product_id) VALUES (?, ?, ?)", [$newName, $newSlug, $productId]);
                $_SESSION['flash_success'] = "New landing page created!";
                header("Location: settings.php?page=" . urlencode($newSlug));
                exit;
            } catch (Exception $e) {
                $error = "Error creating page: " . $e->getMessage();
            }
        } else {
            $error = "Name, Slug, and Product are required.";
        }
    } elseif ($action === 'delete_page') {
        // Delete Page
        $delId = intval($_POST['page_id']);
        // Prevent deleting 'default' if you want to keep one master page
        $check = $db->fetchOne("SELECT slug FROM landing_pages WHERE id = ?", [$delId]);
        if ($check && $check['slug'] !== 'default') {
            $db->execute("DELETE FROM landing_pages WHERE id = ?", [$delId]);
            $_SESSION['flash_success'] = "Page deleted successfully.";
            header("Location: settings.php?page=default");
            exit;
        } else {
            $error = "Cannot delete the default page.";
        }
    } elseif ($action === 'save_settings') {
        // Update existing page
        $id = $_POST['page_id'];
        // Update $selectedSlug from input if redirecting? No, keep query param or what the form had.
        // We redirect to the SAME page slug we are editing.
        
        // General Data
        $productId = $_POST['product_id'];
        $heroTitle = $_POST['hero_title'];
        $heroSubtitle = $_POST['hero_subtitle'];
        $heroDesc = $_POST['hero_description'];
        $themeColor = $_POST['theme_color'];
        $bodyBg = $_POST['body_bg_color'] ?? '#ffffff';
        $bodyText = $_POST['body_text_color'] ?? '#000000';
        
        // Colors
        $heroBg = $_POST['hero_bg_color'] ?? '#E8F0E9';
        $heroText = $_POST['hero_text_color'] ?? '#4A4A4A';
        $bannerBg = $_POST['banner_bg_color'] ?? '#FFFFFF';
        $bannerText = $_POST['banner_text_color'] ?? '#000000';
        $statsBg = $_POST['stats_bg_color'] ?? '';
        $statsText = $_POST['stats_text_color'] ?? '';
        $whyBg = $_POST['why_bg_color'];
        $whyText = $_POST['why_text_color'];
        $aboutBg = $_POST['about_bg_color'];
        $aboutText = $_POST['about_text_color'];
        $testiBg = $_POST['testimonials_bg_color'];
        $testiText = $_POST['testimonials_text_color'];
        $newsBg = $_POST['newsletter_bg_color'];
        $newsText = $_POST['newsletter_text_color'];
        
        // Toggles
        $showStats = isset($_POST['show_stats']) ? 1 : 0;
        $showWhy = isset($_POST['show_why']) ? 1 : 0;
        $showAbout = isset($_POST['show_about']) ? 1 : 0;
        $showTestimonials = isset($_POST['show_testimonials']) ? 1 : 0;
        $showNewsletter = isset($_POST['show_newsletter']) ? 1 : 0;
        $showBanner = isset($_POST['show_banner']) ? 1 : 0;
        
        // Content
        $whyTitle = $_POST['why_title'];
        $aboutTitle = $_POST['about_title'];
        $aboutText = $_POST['about_text'];
        $testiTitle = $_POST['testimonials_title'];
        $newsTitle = $_POST['newsletter_title'];
        $newsText = $_POST['newsletter_text'];
        
        // File Upload Logic
        $uploadDir = __DIR__ . '/../assets/images/banner/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        // Function to handle single file upload
        $handleUpload = function($fileKey) use ($uploadDir) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES[$fileKey]['name']));
                $target = $uploadDir . $name;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
                    return 'assets/images/banner/' . $name;
                }
            }
            return null;
        };

        // Detect Multiple Banners Submission
        $bannerItems = [];
        if (isset($_POST['banner_items']) && is_array($_POST['banner_items'])) {
            foreach ($_POST['banner_items'] as $index => $item) {
                // Handle File Uploads for this index
                $imgUrl = $item['image_url'] ?? '';
                $mobImgUrl = $item['mobile_image_url'] ?? '';

                // Check for new upload: Desktop
                if (isset($_FILES['banner_items']['name'][$index]['image_file'])) {
                     $uErr = $_FILES['banner_items']['error'][$index]['image_file'];
                     if ($uErr === UPLOAD_ERR_OK) {
                         $fname = time() . '_' . $index . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['banner_items']['name'][$index]['image_file']));
                         $target = $uploadDir . $fname;
                         if (move_uploaded_file($_FILES['banner_items']['tmp_name'][$index]['image_file'], $target)) {
                             $imgUrl = 'assets/images/banner/' . $fname;
                         }
                     } elseif ($uErr !== UPLOAD_ERR_NO_FILE) {
                         $error .= "Banner ".($index+1)." Desktop Upload Failed (Code $uErr). Check upload_max_filesize. ";
                     }
                }
                
                // Check for new upload: Mobile
                if (isset($_FILES['banner_items']['name'][$index]['mobile_image_file'])) {
                     $uErr = $_FILES['banner_items']['error'][$index]['mobile_image_file'];
                     if ($uErr === UPLOAD_ERR_OK) {
                         $fname = time() . '_mob_' . $index . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['banner_items']['name'][$index]['mobile_image_file']));
                         $target = $uploadDir . $fname;
                         if (move_uploaded_file($_FILES['banner_items']['tmp_name'][$index]['mobile_image_file'], $target)) {
                             $mobImgUrl = 'assets/images/banner/' . $fname;
                         }
                     } elseif ($uErr !== UPLOAD_ERR_NO_FILE) {
                         $error .= "Banner ".($index+1)." Mobile Upload Failed (Code $uErr). Check upload_max_filesize. ";
                     }
                }

                $bannerItems[] = [
                    'image' => $imgUrl,
                    'mobile_image' => $mobImgUrl,
                    'video_url' => $item['video_url'] ?? '',
                    'mobile_video_url' => $item['mobile_video_url'] ?? '',
                    'heading' => $item['heading'] ?? '',
                    'text' => $item['text'] ?? '',
                    'btn_text' => $item['btn_text'] ?? '',
                    'btn_link' => $item['btn_link'] ?? ''
                ];
            }
        }
        
        // Encode for JSON storage
        $bannerSectionsJson = json_encode($bannerItems);
        
        // Handle Stats Items
        $statsItems = [];
        if (isset($_POST['stats_items']) && is_array($_POST['stats_items'])) {
            // Re-index simple array
            foreach ($_POST['stats_items'] as $stat) {
                 if (trim($stat['value']) !== '' || trim($stat['label']) !== '') {
                     $statsItems[] = [
                         'value' => $stat['value'] ?? '',
                         'label' => $stat['label'] ?? ''
                     ];
                 }
            }
        }
        $statsDataJson = json_encode($statsItems);

        // Handle Why Items (Dynamic)
        $whyItems = [];
        if (isset($_POST['why_items']) && is_array($_POST['why_items'])) {
             foreach ($_POST['why_items'] as $whyItem) {
                 if (trim($whyItem['title']) !== '') { // Only require title to save
                     $whyItems[] = [
                         'icon' => $whyItem['icon'] ?? '',
                         'title' => $whyItem['title'] ?? '',
                         'desc' => $whyItem['desc'] ?? ''
                     ];
                 }
            }
        }
        $whyDataJson = json_encode($whyItems);

        // Handle Testimonials Items (Dynamic)
        $testimonialsItems = [];
        if (isset($_POST['testimonials_items']) && is_array($_POST['testimonials_items'])) {
            foreach ($_POST['testimonials_items'] as $index => $tItem) {
                // Initialize variables
                 $tImgUrl = $tItem['image'] ?? '';
                 
                 // Handle File Upload for this testimonial
                 if (isset($_FILES['testimonials_items']['name'][$index]['image_file']) && $_FILES['testimonials_items']['error'][$index]['image_file'] === UPLOAD_ERR_OK) {
                     $fname = time() . '_testi_' . $index . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['testimonials_items']['name'][$index]['image_file']));
                     $target = $uploadDir . $fname;
                     if (move_uploaded_file($_FILES['testimonials_items']['tmp_name'][$index]['image_file'], $target)) {
                         $tImgUrl = 'assets/images/banner/' . $fname; // Keeping in same assets folder for simplicity
                     }
                 }

                 if (trim($tItem['name']) !== '' || trim($tItem['comment']) !== '') {
                     $testimonialsItems[] = [
                         'name' => $tItem['name'] ?? '',
                         'comment' => $tItem['comment'] ?? '',
                         'image' => $tImgUrl
                     ];
                 }
            }
        }
        $testimonialsDataJson = json_encode($testimonialsItems);
        
        // Single fields for backward compatibility (optional, using first item)
        $firstBan = $bannerItems[0] ?? [];
        $finalBannerImg = $firstBan['image'] ?? ($_POST['banner_image'] ?? '');
        $finalBannerMobImg = $firstBan['mobile_image'] ?? ($_POST['banner_mobile_image'] ?? '');

        try {
            $db->execute(
                "UPDATE landing_pages SET 
                    product_id=?, hero_title=?, hero_subtitle=?, hero_description=?, theme_color=?,
                    body_bg_color=?, body_text_color=?,
                    hero_bg_color=?, hero_text_color=?,
                    banner_bg_color=?, banner_text_color=?,
                    stats_bg_color=?, stats_text_color=?,
                    why_bg_color=?, why_text_color=?,
                    about_bg_color=?, about_text_color=?,
                    testimonials_bg_color=?, testimonials_text_color=?,
                    newsletter_bg_color=?, newsletter_text_color=?,
                    show_stats=?, show_why=?, show_about=?, show_testimonials=?, show_newsletter=?, show_banner=?,
                    why_title=?, about_title=?, about_text=?, testimonials_title=?, newsletter_title=?, newsletter_text=?,
                    about_image=?,
                    banner_image=?, banner_mobile_image=?, banner_heading=?, banner_text=?, banner_btn_text=?, banner_btn_link=?, banner_sections_json=?, section_order=?, stats_data=?, why_data=?, testimonials_data=?,
                    meta_title=?, meta_description=?, custom_schema=?,
                    footer_extra_content=?, show_footer_extra=?,
                    footer_extra_bg=?, footer_extra_text=?
                WHERE id=?",
                [
                    $productId, $heroTitle, $heroSubtitle, $heroDesc, $themeColor,
                    $bodyBg, $bodyText,
                    $heroBg, $heroText, 
                    $bannerBg, $bannerText,
                    $statsBg, $statsText, $whyBg, $whyText, $aboutBg, $aboutText,
                    $testiBg, $testiText, $newsBg, $newsText,
                    $showStats, $showWhy, $showAbout, $showTestimonials, $showNewsletter, $showBanner,
                    $whyTitle, $aboutTitle, $aboutText, $testiTitle, $newsTitle, $newsText,
                    $_POST['about_image'] ?? '',
                    $finalBannerImg,
                    $finalBannerMobImg,
                    $firstBan['heading'] ?? ($_POST['banner_heading'] ?? ''),
                    $firstBan['text'] ?? ($_POST['banner_text'] ?? ''),
                    $firstBan['btn_text'] ?? ($_POST['banner_btn_text'] ?? ''),
                    $firstBan['btn_link'] ?? ($_POST['banner_btn_link'] ?? ''),
                    $bannerSectionsJson,
                    $_POST['section_order_json'] ?? '[]',
                    $statsDataJson,
                    $whyDataJson,
                    $testimonialsDataJson,
                    $_POST['meta_title'] ?? '',
                    $_POST['meta_description'] ?? '',
                    $_POST['custom_schema'] ?? '',
                    $_POST['footer_extra_content'] ?? '',
                    isset($_POST['show_footer_extra']) ? 1 : 0,
                    $_POST['footer_extra_bg'] ?? '#f8f9fa',
                    $_POST['footer_extra_text'] ?? '#333333',
                    $id
                ]
            );
            $_SESSION['flash_success'] = "Page settings saved successfully!";
            session_write_close(); // Ensure session is saved before redirect
            header("Location: settings.php?page=" . urlencode($selectedSlug));
            exit;
        } catch (Exception $e) {
            $error = "Error saving settings: " . $e->getMessage();
        }
    }
}

// Flash Messages
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
    // Prevent browser caching of the page with the success message
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

$pageTitle = 'Landing Page Settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<!-- CKEditor 5 -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<style>
/* CKEditor content styling fix for Tailwind */
.ck-editor__editable { min-height: 250px; }
.ck-content h2 { font-size: 1.5em; font-weight: bold; margin-bottom: 0.5em; }
.ck-content h3 { font-size: 1.25em; font-weight: bold; margin-bottom: 0.5em; }
.ck-content p { margin-bottom: 1em; }
.ck-content ul { list-style-type: disc !important; padding-left: 1.5em !important; margin-bottom: 1em; }
.ck-content ol { list-style-type: decimal !important; padding-left: 1.5em !important; margin-bottom: 1em; }
.ck-content a { color: blue; text-decoration: underline; }
</style>
<script>
  document.addEventListener("DOMContentLoaded", function() {
      const editors = document.querySelectorAll('.rich-text-editor');
      editors.forEach(el => {
          ClassicEditor
              .create(el, {
                  toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', '|', 'undo', 'redo'],
                  heading: {
                      options: [
                          { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                          { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                          { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                      ]
                  }
              })
              .catch(error => { console.error(error); });
      });
  });
</script>
<?php

// Fetch all pages for selector
$allPages = $db->fetchAll("SELECT id, name, slug FROM landing_pages ORDER BY name ASC");

// Determine which page to edit
$selectedSlug = $_GET['page'] ?? '';

// If no slug provided, or if provided slug is invalid, fallback to the first available page
if (empty($selectedSlug) && !empty($allPages)) {
    $selectedSlug = $allPages[0]['slug'];
}

// Fetch active products for dropdown (Adding back)
$products = $db->fetchAll("SELECT id, name, price, currency FROM products WHERE status = 'active' ORDER BY name ASC");

// Fetch Current Page Data
$lp = $db->fetchOne("SELECT * FROM landing_pages WHERE slug = ?", [$selectedSlug]);

// If still no page found (e.g. invalid slug provided and not caught above), try first page again
if (!$lp && !empty($allPages)) {
    $selectedSlug = $allPages[0]['slug'];
    $lp = $db->fetchOne("SELECT * FROM landing_pages WHERE slug = ?", [$selectedSlug]);
}
?>

<div class="mb-6 flex justify-between items-center sticky top-0 bg-[#f7f8fc] pb-5 z-50">
    <div>
        <h1 class="text-3xl font-bold">Landing Page Manager</h1>
        <p class="text-gray-600">Dashboard > Settings > Landing Pages</p>
    </div>
    <div class="mt-8 pt-4 border-t flex justify-end gap-4">
    <button type="submit" form="landingPageForm" class="bg-blue-600 text-white font-bold py-3 px-8 rounded shadow-lg transition transform hover:-translate-y-0.5">
        Save All Settings
    </button>
    <a href="<?php echo $baseUrl; ?>/<?php echo $selectedSlug; ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
        <i class="fas fa-external-link-alt mr-2"></i> View Live Page
    </a>
    </div>
</div>


<script>
    function copyLink(text) {
        if (!navigator.clipboard) {
            // Fallback for non-secure contexts or older browsers
            var textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";  // Avoid scrolling to bottom
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showToast('Link copied!');
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }
            document.body.removeChild(textArea);
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            showToast('Link copied!');
        });
    }

    function showToast(message) {
        const el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded shadow-lg text-sm z-50 animate-bounce';
        el.innerText = message;
        document.body.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(() => el.remove(), 500);
        }, 2000);
    }
</script>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    
    <!-- Sidebar: Page Selector & Creator -->
    <!-- Sidebar: Page Selector & Creator -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white p-4 rounded shadow">
            <h3 class="font-bold text-lg mb-4 border-b pb-2">Select Page</h3>
            <ul class="space-y-2">
                <?php foreach ($allPages as $p): ?>
                <li class="min-w-0">
                    <div class="flex items-center group w-full">
                        <div class="flex-1 block px-3 py-2 rounded min-w-0 <?php echo $selectedSlug == $p['slug'] ? 'bg-blue-50 border-l-4 border-blue-600' : 'hover:bg-gray-50'; ?>">
                           <a href="?page=<?php echo $p['slug']; ?>" class="block truncate <?php echo $selectedSlug == $p['slug'] ? 'text-blue-600 font-bold' : 'text-gray-800 font-medium'; ?>">
                               <?php echo htmlspecialchars($p['name']); ?>
                           </a>
                            <div class="flex items-center justify-between mt-1 group-hover/link min-w-0">
                               <a href="<?php echo $baseUrl; ?>/<?php echo $p['slug']; ?>" target="_blank" class="text-xs text-gray-400 font-normal hover:text-blue-600 hover:underline truncate mr-2 block flex-1 min-w-0" title="<?php echo $baseUrl; ?>/<?php echo $p['slug']; ?>">
                                   <?php echo $baseUrl; ?>/<?php echo $p['slug']; ?> <i class="fas fa-external-link-alt text-[10px] ml-1"></i>
                               </a>
                               <button type="button" onclick="copyLink('<?php echo $baseUrl; ?>/<?php echo $p['slug']; ?>')" class="text-xs text-gray-400 hover:text-blue-600 p-1 flex-shrink-0" title="Copy Link">
                                   <i class="fas fa-copy"></i>
                               </button>
                           </div>
                        </div>
                        <?php if($p['slug'] !== 'default'): ?>
                        <form method="POST" class="ml-2 flex-shrink-0">
                            <input type="hidden" name="action" value="delete_page">
                            <input type="hidden" name="page_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-600 p-2" title="Delete Page">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="bg-white p-4 rounded shadow">
            <h3 class="font-bold text-lg mb-4 border-b pb-2">Create New Page</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_page">
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Product</label>
                    <select name="product_id" id="newProductSelect" class="w-full border p-2 rounded text-sm" required>
                        <option value="">Select Product...</option>
                        <?php foreach ($products as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">Page Name</label>
                    <input type="text" name="new_page_name" id="newPageName" placeholder="e.g. Summer Sale" class="w-full border p-2 rounded text-sm" required>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-bold mb-1">URL Slug</label>
                    <input type="text" name="new_page_slug" id="newPageSlug" placeholder="e.g. summer-sale" class="w-full border p-2 rounded text-sm" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white text-sm py-2 rounded hover:bg-blue-700">Create Page</button>
            </form>
        </div>
    </div>

    <!-- Main Content: Editor -->
    <?php if ($lp): ?>
    <div class="lg:col-span-3">
        <form id="landingPageForm" method="POST" class="bg-white p-6 rounded shadow" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="page_id" value="<?php echo $lp['id']; ?>">
            
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-xl font-bold">Editing: <?php echo htmlspecialchars($lp['name']); ?></h2>
                <div class="flex items-center gap-6">
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Theme Color:</label>
                         <input type="color" name="theme_color" value="<?php echo htmlspecialchars(($lp['theme_color'] ?? '') ?: '#5F8D76'); ?>" class="h-8 w-12 p-0 border-0">
                     </div>
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Body Bg:</label>
                         <input type="color" name="body_bg_color" value="<?php echo htmlspecialchars($lp['body_bg_color'] ?? '#ffffff'); ?>" class="h-8 w-12 p-0 border-0">
                     </div>
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Body Text:</label>
                         <input type="color" name="body_text_color" value="<?php echo htmlspecialchars($lp['body_text_color'] ?? '#000000'); ?>" class="h-8 w-12 p-0 border-0">
                     </div>
                </div>
            </div>

            <!-- SEO Settings -->
            

            <!-- 1. Hero / Main -->
            <div class="mb-8 border border-gray-200 rounded overflow-hidden">
                <div class="bg-gray-50 p-3 flex justify-between items-center border-b">
                     <h3 class="font-bold text-lg text-blue-600 m-0">Hero Section</h3>
                     <button type="button" onclick="toggleSection('secHero', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-up text-gray-500"></i></button>
                </div>
                <div id="secHero" class="p-4 bg-white">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-bold mb-1">Linked Product</label>
                            <select name="product_id" class="w-full border p-2 rounded">
                                 <?php foreach ($products as $pr): ?>
                                <option value="<?php echo $pr['id']; ?>" <?php echo $pr['id'] == $lp['product_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pr['name']); ?> - <?php echo format_price($pr['price'], $pr['currency'] ?? 'INR'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Hero Title</label>
                            <input type="text" name="hero_title" value="<?php echo htmlspecialchars($lp['hero_title'] ?? ''); ?>" class="w-full border p-2 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Subtitle</label>
                            <input type="text" name="hero_subtitle" value="<?php echo htmlspecialchars($lp['hero_subtitle'] ?? ''); ?>" class="w-full border p-2 rounded">
                        </div>
                         <div class="col-span-2">
                            <label class="block text-sm font-bold mb-1">Description</label>
                            <textarea name="hero_description" class="w-full border p-2 rounded" rows="3"><?php echo htmlspecialchars($lp['hero_description'] ?? ''); ?></textarea>
                        </div>
                     </div>
                     <!-- Added Hero Colors -->
                     <div class="grid grid-cols-2 gap-4 mt-4 border-t pt-4">
                         <div>
                            <label class="block text-xs font-bold mb-1">Hero Bg Color</label>
                            <input type="color" name="hero_bg_color" value="<?php echo htmlspecialchars($lp['hero_bg_color'] ?? '#E8F0E9'); ?>" class="w-full h-8">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">Hero Text Color</label>
                            <input type="color" name="hero_text_color" value="<?php echo htmlspecialchars($lp['hero_text_color'] ?? '#4A4A4A'); ?>" class="w-full h-8">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reorderable Sections Container -->
            <input type="hidden" name="section_order_json" id="sectionOrderJson" value="<?php echo htmlspecialchars($lp['section_order'] ?? '[]'); ?>">
            
            <div id="draggableSections" class="space-y-4">
            <?php
                // 1. Capture All Sections HTML
                $sections = [];

                // --- HEADER LINKS ---
                ob_start(); ?>
                <!-- <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secHeaders">
                     <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-grip-vertical text-gray-400"></i>
                            <h3 class="font-bold text-gray-700">Header Links</h3>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="toggleSection('secHeaders')" class="text-xs text-blue-600 font-bold uppercase">Edit Content</button>
                        </div>
                    </div>
                    
                    <div id="secHeaders" class="p-4 hidden bg-white">
                        <input type="hidden" name="nav_links_json" id="navLinksJson" value="<?php echo htmlspecialchars($lp['nav_links'] ?? '[]'); ?>">
                        <div id="navLinksContainer" class="space-y-3 mb-3"></div>
                        <button type="button" onclick="addNavLink()" class="text-sm text-blue-600 font-bold hover:underline">+ Add New Link</button>
                    </div>
                </div> -->
                <?php $sections['secHeaders'] = ob_get_clean();

                // --- BANNER SECTION ---
                ob_start(); ?>
                <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secBanner">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Banner Section</span>
                        </div>
                        <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_banner" class="mr-2" <?php echo ($lp['show_banner'] ?? 0) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secBanner', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secBanner" class="p-4 hidden bg-white">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="banner_bg_color" value="<?php echo htmlspecialchars($lp['banner_bg_color'] ?? '#ffffff'); ?>" class="w-full h-8"></div>
                             <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="banner_text_color" value="<?php echo htmlspecialchars($lp['banner_text_color'] ?? '#000000'); ?>" class="w-full h-8"></div>
                        </div>
                        <div id="bannerSectionsContainer" class="space-y-6"></div>
                        <button type="button" onclick="addBannerSection()" class="mt-4 text-blue-600 font-bold hover:underline text-sm">+ Add Another Banner Section</button>
                        <input type="hidden" id="initialBannerData" value="<?php echo htmlspecialchars($lp['banner_sections_json'] ?? '[]'); ?>">
                    </div>
                </div>
                <?php $sections['secBanner'] = ob_get_clean();

                // --- STATS SECTION ---
                ob_start(); ?>
                 <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secStats">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Stats Section</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_stats" class="mr-2" <?php echo $lp['show_stats'] ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secStats', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secStats" class="p-4 hidden bg-white">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="stats_bg_color" value="<?php echo htmlspecialchars($lp['stats_bg_color'] ?? ''); ?>" class="w-full h-8"></div>
                             <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="stats_text_color" value="<?php echo htmlspecialchars($lp['stats_text_color'] ?? ''); ?>" class="w-full h-8"></div>
                        </div>
                        
                        <div id="statsItemsContainer" class="space-y-3"></div>
                        <button type="button" onclick="addStatItem()" class="mt-2 text-blue-600 font-bold hover:underline text-sm">+ Add Stat Item</button>
                        <input type="hidden" id="initialStatsData" value="<?php echo htmlspecialchars($lp['stats_data'] ?? '[]'); ?>">
                    </div>
                </div>
                <?php $sections['secStats'] = ob_get_clean();

                // --- WHY US SECTION ---
                ob_start(); ?>
                <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secWhy">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Why Us Section</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_why" class="mr-2" <?php echo $lp['show_why'] ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secWhy', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secWhy" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="why_title" value="<?php echo htmlspecialchars($lp['why_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="why_bg_color" value="<?php echo htmlspecialchars($lp['why_bg_color'] ?? ''); ?>" class="w-full h-8"></div>
                            <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="why_text_color" value="<?php echo htmlspecialchars($lp['why_text_color'] ?? ''); ?>" class="w-full h-8"></div>
                        </div>
                        
                        <div id="whyItemsContainer" class="space-y-4"></div>
                        <button type="button" onclick="addWhyItem()" class="mt-2 text-blue-600 font-bold hover:underline text-sm">+ Add Feature</button>
                        <input type="hidden" id="initialWhyData" value="<?php echo htmlspecialchars($lp['why_data'] ?? '[]'); ?>">
                    </div>
                </div>
                <?php $sections['secWhy'] = ob_get_clean();

                // --- ABOUT SECTION ---
                ob_start(); ?>
                <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secAbout">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">About Section</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_about" class="mr-2" <?php echo $lp['show_about'] ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secAbout', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secAbout" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="about_title" value="<?php echo htmlspecialchars($lp['about_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="mb-4"><label class="block text-sm font-bold mb-1">Text Content</label><textarea name="about_text" class="w-full border p-2 rounded" rows="3"><?php echo htmlspecialchars($lp['about_text'] ?? ''); ?></textarea></div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold mb-1">About Image</label>
                            
                            <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[200px] hover:border-blue-500 hover:bg-blue-50 transition" onclick="this.querySelector('input[type=file]').click()">
                                
                                <?php 
                                $aboutImageValue = $lp['about_image'] ?? '';
                                $hasAboutImage = !empty($aboutImageValue);
                                // Helper for display - adjust path logic as per other sections
                                $displayUrl = $hasAboutImage ? (strpos($aboutImageValue, 'http') === 0 ? $aboutImageValue : '../' . $aboutImageValue) : '';
                                ?>

                                <img src="<?php echo htmlspecialchars($displayUrl); ?>" class="preview-img <?php echo $hasAboutImage ? '' : 'hidden'; ?> max-h-48 w-auto object-contain rounded mb-2 shadow-sm">

                                <div class="text-center placeholder-box <?php echo $hasAboutImage ? 'hidden' : ''; ?>">
                                    <i class="fas fa-image text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-500 font-semibold">Click to upload image</p>
                                    <p class="text-xs text-gray-400 mt-1">Recommended: 800x600px</p>
                                </div>

                                <input type="file" class="hidden" onchange="handleBannerUpload(this)">
                                <input type="hidden" name="about_image" value="<?php echo htmlspecialchars($aboutImageValue); ?>">
                                
                                <!-- Progress Bar Area -->
                                <div class="upload-progress-container w-full absolute bottom-2 left-0 px-4"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="about_bg_color" value="<?php echo htmlspecialchars($lp['about_bg_color'] ?? ''); ?>" class="w-full h-8"></div>
                            <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="about_text_color" value="<?php echo htmlspecialchars($lp['about_text_color'] ?? ''); ?>" class="w-full h-8"></div>
                        </div>
                    </div>
                </div>
                <?php $sections['secAbout'] = ob_get_clean();

                // --- TESTIMONIALS SECTION ---
                ob_start(); ?>
                 <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secTesti">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Testimonials Section</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_testimonials" class="mr-2" <?php echo $lp['show_testimonials'] ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secTesti', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                     <div id="secTesti" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="testimonials_title" value="<?php echo htmlspecialchars($lp['testimonials_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="testimonials_bg_color" value="<?php echo htmlspecialchars($lp['testimonials_bg_color'] ?? ''); ?>" class="w-full h-8"></div>
                            <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="testimonials_text_color" value="<?php echo htmlspecialchars($lp['testimonials_text_color'] ?? ''); ?>" class="w-full h-8"></div>
                        </div>
                        
                        <div id="testimonialsItemsContainer" class="space-y-6"></div>
                        <button type="button" onclick="addTestimonialsItem()" class="mt-2 text-blue-600 font-bold hover:underline text-sm">+ Add Testimonial</button>
                        <input type="hidden" id="initialTestimonialsData" value="<?php echo htmlspecialchars($lp['testimonials_data'] ?? '[]'); ?>">
                    </div>
                </div>
                <?php $sections['secTesti'] = ob_get_clean();

                // --- NEWSLETTER SECTION ---
                 ob_start(); ?>
                <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secNews">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Newsletter Section</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_newsletter" class="mr-2" <?php echo $lp['show_newsletter'] ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secNews', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secNews" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="newsletter_title" value="<?php echo htmlspecialchars($lp['newsletter_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="mb-4"><label class="block text-sm font-bold mb-1">Text Content</label><input type="text" name="newsletter_text" value="<?php echo htmlspecialchars($lp['newsletter_text'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4">
                             <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="newsletter_bg_color" value="<?php echo htmlspecialchars($lp['newsletter_bg_color'] ?? ''); ?>" class="w-full h-8"></div>
                            <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="newsletter_text_color" value="<?php echo htmlspecialchars($lp['newsletter_text_color'] ?? ''); ?>" class="w-full h-8"></div>
                        </div>
                    </div>
                </div>
                <?php $sections['secNews'] = ob_get_clean();

                // 2. Define Default Order
                $defaultOrder = ['secHeaders', 'secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews'];
                
                // 3. Get Saved Order
                $savedOrder = [];
                if (!empty($lp['section_order'])) {
                    $savedOrder = json_decode($lp['section_order'], true);
                }
                if (!is_array($savedOrder)) $savedOrder = [];

                // 4. Merge Orders (Saved + New Defaults)
                $finalOrder = $savedOrder;
                foreach ($defaultOrder as $k) {
                    if (!in_array($k, $finalOrder)) $finalOrder[] = $k;
                }

                // 5. Render Sections in Order
                foreach ($finalOrder as $secId) {
                    if (isset($sections[$secId])) {
                        echo $sections[$secId];
                    }
                }
            ?>

            </div>

            <!-- FOOTER -->
            <!-- <div class="mb-4 border border-gray-200 rounded overflow-hidden">
                <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-pointer" onclick="document.getElementById('secFooter').classList.toggle('hidden')">
                       <span class="font-bold text-gray-700">Footer Settings</span>
                        <label class="flex items-center cursor-pointer">Edit</label>
                   </div>
                   <div id="secFooter" class="p-4 hidden">
                       <input type="hidden" name="footer_data_json" id="footerDataJson" value="<?php echo htmlspecialchars($lp['footer_data'] ?? '{}'); ?>">
                       <div class="mb-3">
                           <label class="block text-sm font-bold mb-1">Copyright Text</label>
                           <input type="text" id="footerCopyright" class="w-full border p-2 rounded" onchange="updateFooterData()">
                       </div>
                   </div>
            </div> -->
            <div class="mb-6 mt-6 border border-gray-200 rounded p-4 bg-gray-50">
                <div class="flex justify-between items-center cursor-pointer select-none" onclick="toggleSection('seoSettings', this)">
                    <h3 class="font-bold text-lg text-gray-700">SEO & Metadata</h3>
                    <i class="fas fa-chevron-down text-gray-500"></i>
                </div>
                <div id="seoSettings" class="hidden mt-4 pt-4 border-t border-gray-200">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold mb-1">Meta Title</label>
                            <input type="text" name="meta_title" value="<?php echo htmlspecialchars($lp['meta_title'] ?? ''); ?>" placeholder="Page Title (overrides default)" class="w-full border p-2 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Meta Description</label>
                            <textarea name="meta_description" class="w-full border p-2 rounded h-20" placeholder="Brief description for search engines..."><?php echo htmlspecialchars($lp['meta_description'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Custom JSON-LD Schema</label>
                            <p class="text-xs text-gray-500 mb-2">Paste your custom schema script here. It will be injected as <code>&lt;script type="application/ld+json"&gt;...&lt;/script&gt;</code>.</p>
                            <textarea name="custom_schema" class="w-full border p-2 rounded font-mono text-xs h-40 bg-gray-900 text-green-400" placeholder='{ "@context": "https://schema.org", ... }'><?php echo htmlspecialchars($lp['custom_schema'] ?? ''); ?></textarea>
                        </div>
                        <div class="pt-2 text-right">
                             <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700 transition font-bold text-sm">
                                <i class="fas fa-save mr-2"></i>Save SEO Settings
                             </button>
                        </div>
                    </div>
                </div>
            </div>

            
            <!-- Footer Extra Content -->
            <div class="mb-6 mt-6 border border-gray-200 rounded p-4 bg-white shadow-sm">
                <div class="flex justify-between items-center mb-4 cursor-pointer" onclick="toggleSection('footerExtraSettings', this)">
                     <h3 class="font-bold text-lg text-gray-800">Footer Extra Content (Rich Text)</h3>
                     <i class="fas fa-chevron-down text-gray-500"></i>
                </div>
                
                <div id="footerExtraSettings" class="hidden">
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="show_footer_extra" class="form-checkbox h-5 w-5 text-blue-600" <?php echo ($lp['show_footer_extra'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700 font-bold">Show Extra Content Section</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                         <div><label class="block text-xs font-bold mb-1">Bg Color</label><input type="color" name="footer_extra_bg" value="<?php echo htmlspecialchars($lp['footer_extra_bg'] ?? '#f8f9fa'); ?>" class="w-full h-8 cursor-pointer"></div>
                        <div><label class="block text-xs font-bold mb-1">Text Color</label><input type="color" name="footer_extra_text" value="<?php echo htmlspecialchars($lp['footer_extra_text'] ?? '#333333'); ?>" class="w-full h-8 cursor-pointer"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold mb-2">Rich Text Content</label>
                        <textarea name="footer_extra_content" class="rich-text-editor w-full border p-2 rounded h-64"><?php echo htmlspecialchars($lp['footer_extra_content'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Use this editor to add text, links, images, tables, and style them as needed. This content appears after the footer.</p>
                    </div>

                    <div class="pt-2 text-right">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700 transition font-bold text-sm">
                        <i class="fas fa-save mr-2"></i>Save Footer Content
                        </button>
                    </div>
                </div>
            </div>
            
        </form>
    </div>
    <?php endif; ?>
</div>



<script>
// Parse initial links from hidden input
// Parse initial links from hidden input
let navLinks = [];
const navLinksJsonEl = document.getElementById('navLinksJson');
if (navLinksJsonEl) {
    try {
        navLinks = JSON.parse(navLinksJsonEl.value);
        if (!Array.isArray(navLinks)) navLinks = [];
    } catch(e) {
        navLinks = [];
    }
}

function renderNavLinks() {
    if (!navLinksJsonEl) return;
    const container = document.getElementById('navLinksContainer');
    if (!container) return;
    container.innerHTML = '';
    
    navLinks.forEach((link, index) => {
        const div = document.createElement('div');
        div.className = 'flex gap-2 items-center';
        div.innerHTML = `
            <input type="text" placeholder="Label" value="${link.label}" onchange="updateNavLink(${index}, 'label', this.value)" class="border p-2 rounded w-1/3">
            <input type="text" placeholder="URL (#section)" value="${link.url}" onchange="updateNavLink(${index}, 'url', this.value)" class="border p-2 rounded w-1/2">
            <button type="button" onclick="removeNavLink(${index})" class="text-red-500 hover:text-red-700">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);
    });
    
    document.getElementById('navLinksJson').value = JSON.stringify(navLinks);
}

// Footer Data Logic
let footerData = {};
const footerDataJsonEl = document.getElementById('footerDataJson');
const footerCopyrightEl = document.getElementById('footerCopyright');

if (footerDataJsonEl) {
    try {
        footerData = JSON.parse(footerDataJsonEl.value);
        if (typeof footerData !== 'object' || footerData === null) footerData = {};
    } catch(e) { footerData = {}; }
    
    if (footerCopyrightEl) {
        footerCopyrightEl.value = footerData.copyright || '';
    }
}

function updateFooterData() {
    footerData.copyright = document.getElementById('footerCopyright').value;
    document.getElementById('footerDataJson').value = JSON.stringify(footerData);
}

function addNavLink() {
    navLinks.push({label: 'New Link', url: '#'});
    renderNavLinks();
}

function removeNavLink(index) {
    navLinks.splice(index, 1);
    renderNavLinks();
}

function updateNavLink(index, key, value) {
    navLinks[index][key] = value;
    document.getElementById('navLinksJson').value = JSON.stringify(navLinks);
}

// Exclusive Accordion Logic
function toggleSection(id) {
    const allSections = ['secHeaders', 'secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews', 'secFooter'];
    
    // Toggle the selected section
    const current = document.getElementById(id);
    const isHidden = current.classList.contains('hidden');
    
    // First hide ALL sections
    allSections.forEach(secId => {
        const el = document.getElementById(secId);
        if(el) el.classList.add('hidden');
    });

    // If it was hidden, open it. If it was open, it's now closed (default state of loop)
    if (isHidden) {
        current.classList.remove('hidden');
    }
}

// Initial render
renderNavLinks();

// Auto-generate slug from page name
// Shared Slug Generator
function updateSlug(text) {
    const slug = text.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '') // remove invalid chars
        .trim()
        .replace(/\s+/g, '-')         // replace spaces with -
        .replace(/-+/g, '-');         // collapse dashes
    document.getElementById('newPageSlug').value = slug;
}

// 1. Auto-generate slug when typing in Page Name
// 1. Auto-generate slug when typing in Page Name
const newPageNameEl = document.getElementById('newPageName');
if (newPageNameEl) {
    newPageNameEl.addEventListener('input', function() {
        updateSlug(this.value);
    });
}

// 2. Auto-fill Page Name & Slug when Product is selected
const newProductSelectEl = document.getElementById('newProductSelect');
if (newProductSelectEl) {
    newProductSelectEl.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value && newPageNameEl) {
            // Get product name
            const rawName = selectedOption.text.trim();
            
            // Update Page Name field
            newPageNameEl.value = rawName;
            
            // Directly update Slug (bypassing event listeners to be safe)
            updateSlug(rawName);
        }
    });
}
const bannerContainer = document.getElementById('bannerSectionsContainer');
let bannerCount = 0;
let bannerData = [];

// Initialize Banners
try {
    const rawData = document.getElementById('initialBannerData').value;
    bannerData = JSON.parse(rawData);
    if (!Array.isArray(bannerData)) bannerData = [];
} catch(e) { bannerData = []; }

if (bannerData.length === 0) {
    // Add one empty section by default
    addBannerSection({}); 
} else {
    bannerData.forEach(data => addBannerSection(data));
}

// --- Initialize Stats ---
const initialStatsData = document.getElementById('initialStatsData').value;
const statsContainer = document.getElementById('statsItemsContainer');
let statsCount = 0;

function addStatItem(data = {}) {
    const index = statsCount++;
    const div = document.createElement('div');
    div.className = 'grid grid-cols-2 gap-4 items-end bg-gray-50 p-3 rounded relative';
    div.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600 shadow-sm z-10" title="Remove">×</button>
        <div>
             <label class="block text-xs font-bold mb-1">Value (e.g. 500+)</label>
            <input type="text" name="stats_items[${index}][value]" value="${data.value || ''}" class="w-full border p-2 rounded text-sm" placeholder="10K+">
        </div>
         <div>
             <label class="block text-xs font-bold mb-1">Label (e.g. Clients)</label>
            <input type="text" name="stats_items[${index}][label]" value="${data.label || ''}" class="w-full border p-2 rounded text-sm" placeholder="Customers">
        </div>
    `;
    statsContainer.appendChild(div);
}

if (initialStatsData) {
    try {
        const stats = JSON.parse(initialStatsData);
        if (Array.isArray(stats) && stats.length > 0) {
            stats.forEach(s => addStatItem(s));
        } else {
            // Add 4 default empty ones if none exist
            for(let i=0; i<4; i++) addStatItem();
        }
    } catch(e) {  
        for(let i=0; i<4; i++) addStatItem(); 
    }
}

// --- Initialize Why Items ---
const initialWhyData = document.getElementById('initialWhyData').value;
const whyContainer = document.getElementById('whyItemsContainer');
let whyCount = 0;

function addWhyItem(data = {}) {
    const index = whyCount++;
    const div = document.createElement('div');
    div.className = 'border border-gray-200 p-3 rounded bg-gray-50 relative';
    div.innerHTML = `
         <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold" title="Remove">X</button>
         <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                 <label class="block text-xs font-bold mb-1">Icon Class (FontAwesome)</label>
                <input type="text" name="why_items[${index}][icon]" value="${data.icon || 'fas fa-check'}" class="w-full border p-2 rounded text-sm mb-1" placeholder="fas fa-check">
                <a href="https://fontawesome.com/v5/search?m=free" target="_blank" class="text-xs text-blue-500 hover:underline">Pick Icon</a>
            </div>
            <div class="md:col-span-2">
                 <label class="block text-xs font-bold mb-1">Title</label>
                <input type="text" name="why_items[${index}][title]" value="${data.title || ''}" class="w-full border p-2 rounded text-sm" placeholder="Feature Title">
            </div>
            <div class="md:col-span-3">
                 <label class="block text-xs font-bold mb-1">Description</label>
                <textarea name="why_items[${index}][desc]" class="w-full border p-2 rounded text-sm" rows="2" placeholder="Short description...">${data.desc || ''}</textarea>
            </div>
         </div>
    `;
    whyContainer.appendChild(div);
}

if (initialWhyData) {
    try {
        const items = JSON.parse(initialWhyData);
        if (Array.isArray(items) && items.length > 0) {
            items.forEach(item => addWhyItem(item));
        } else {
            for(let i=0; i<3; i++) addWhyItem();
        }
    } catch(e) { 
        for(let i=0; i<3; i++) addWhyItem(); 
    }
}

// --- Initialize Testimonials Items ---
const initialTestimonialsData = document.getElementById('initialTestimonialsData').value;
const testimonialsContainer = document.getElementById('testimonialsItemsContainer');
let reviewCount = 0;

function addTestimonialsItem(data = {}) {
    const index = reviewCount++;
    const div = document.createElement('div');
    div.className = 'border border-gray-200 p-4 rounded bg-gray-50 relative animate-fade-in';
    div.innerHTML = `
         <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold bg-white rounded-full w-6 h-6 flex items-center justify-center shadow-sm" title="Remove">
            <i class="fas fa-times text-xs"></i>
         </button>
         <span class="text-xs font-bold text-gray-400 mb-2 block uppercase tracking-wider">Review ${index+1}</span>
         <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-1">
                 <label class="block text-xs font-bold mb-1">User Photo</label>
                 
                 <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-2 bg-white flex flex-col items-center justify-center h-32 hover:border-blue-500 hover:bg-blue-50 transition" onclick="this.querySelector('input[type=file]').click()">
                    
                    ${data.image ? `
                        <img src="${data.image.startsWith('http') ? data.image : '../'+data.image}" class="preview-img h-20 w-20 object-cover rounded-full mb-1 border border-gray-200 shadow-sm">
                    ` : `
                        <img class="preview-img hidden h-20 w-20 object-cover rounded-full mb-1 border border-gray-200 shadow-sm">
                    `}
                    
                    <div class="text-center placeholder-box ${data.image ? 'hidden' : ''}">
                        <i class="fas fa-camera text-xl text-gray-400 mb-1"></i>
                        <p class="text-[10px] text-gray-500 font-semibold leading-tight">Click to<br>upload</p>
                    </div>

                    <input type="file" class="hidden" onchange="handleBannerUpload(this)">
                    <input type="hidden" name="testimonials_items[${index}][image]" value="${data.image || ''}">
                    <!-- Progress Bar Area -->
                    <div class="upload-progress-container w-full absolute bottom-1 left-0 px-2"></div>
                </div>
            </div>
            <div class="md:col-span-3 space-y-3">
                <div>
                     <label class="block text-xs font-bold mb-1">User Name</label>
                    <input type="text" name="testimonials_items[${index}][name]" value="${data.name || ''}" class="w-full border p-2 rounded text-sm" placeholder="John Doe">
                </div>
                <div>
                     <label class="block text-xs font-bold mb-1">Review Comment</label>
                    <textarea name="testimonials_items[${index}][comment]" class="w-full border p-2 rounded text-sm min-h-[80px]" rows="2" placeholder="Great product, highly recommended!">${data.comment || ''}</textarea>
                </div>
            </div>
         </div>
    `;
    testimonialsContainer.appendChild(div);
}

if (initialTestimonialsData) {
    try {
        const reviews = JSON.parse(initialTestimonialsData);
        if (Array.isArray(reviews) && reviews.length > 0) {
            reviews.forEach(r => addTestimonialsItem(r));
        } else {
             // Add one minimal example
             addTestimonialsItem();
        }
    } catch(e) { addTestimonialsItem(); }
}

// Toggle Section Content Visibility
// Toggle Section Content Visibility
window.toggleSection = function(id, btn) {
    const el = document.getElementById(id);
    if(el) el.classList.toggle('hidden');
    
    // Toggle Icon if button passed
    if(btn) {
        let icon = btn.querySelector('i');
        // If btn itself is the icon element
        if (!icon && (btn.classList.contains('fas') || btn.tagName === 'I')) icon = btn;
        
        if(icon) {
            if(el.classList.contains('hidden')) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
    }
};

function addBannerSection(data = {}) {
    const index = bannerCount++;
    const div = document.createElement('div');
    div.className = 'border border-gray-200 p-4 rounded bg-gray-50 relative animate-fade-in';
    div.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold bg-white rounded-full w-6 h-6 flex items-center justify-center shadow-sm" title="Remove Section">
            <i class="fas fa-times text-xs"></i>
        </button>
        <span class="text-xs font-bold text-gray-400 mb-2 block uppercase tracking-wider">Banner Set ${index+1}</span>
        
        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Desktop Media -->
            <div>
                <label class="block text-xs font-bold mb-1">Desktop Media (Img/Video)</label>
                <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:border-blue-500 hover:bg-blue-50 transition" onclick="this.querySelector('input[type=file]').click()">
                    
                    ${data.image ? `
                        ${data.image.match(/\.(mp4|webm)$/i) 
                        ? `<video src="${data.image.startsWith('http') ? data.image : '../'+data.image}" class="preview-video max-h-32 w-full object-cover rounded mb-2" muted playsinline loop hover></video>`
                        : `<img src="${data.image.startsWith('http') ? data.image : '../'+data.image}" class="preview-img max-h-32 w-auto object-contain rounded mb-2">`
                        }
                    ` : `
                        <img class="preview-img hidden max-h-32 w-auto object-contain rounded mb-2">
                        <video class="preview-video hidden max-h-32 w-full object-cover rounded mb-2" muted playsinline loop></video>
                    `}
                    
                    <div class="text-center placeholder-box ${data.image ? 'hidden' : ''}">
                        <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-1"></i>
                        <p class="text-xs text-gray-500 font-semibold">Click to upload</p>
                    </div>

                    <input type="file" class="hidden" onchange="handleBannerUpload(this)">
                    <input type="hidden" name="banner_items[${index}][image_url]" value="${data.image || ''}">
                     <!-- Progress Bar Area -->
                    <div class="upload-progress-container w-full"></div>
                </div>
            </div>

            <!-- Mobile Media -->
            <div>
                <label class="block text-xs font-bold mb-1">Mobile Media (Img/Video)</label>
                <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-4 bg-white flex flex-col items-center justify-center min-h-[120px] hover:border-blue-500 hover:bg-blue-50 transition" onclick="this.querySelector('input[type=file]').click()">
                    
                     ${data.mobile_image ? `
                        ${data.mobile_image.match(/\.(mp4|webm)$/i) 
                        ? `<video src="${data.mobile_image.startsWith('http') ? data.mobile_image : '../'+data.mobile_image}" class="preview-video max-h-32 w-full object-cover rounded mb-2" muted playsinline loop hover></video>`
                        : `<img src="${data.mobile_image.startsWith('http') ? data.mobile_image : '../'+data.mobile_image}" class="preview-img max-h-32 w-auto object-contain rounded mb-2">`
                        }
                    ` : `
                        <img class="preview-img hidden max-h-32 w-auto object-contain rounded mb-2">
                        <video class="preview-video hidden max-h-32 w-full object-cover rounded mb-2" muted playsinline loop></video>
                    `}

                     <div class="text-center placeholder-box ${data.mobile_image ? 'hidden' : ''}">
                        <i class="fas fa-mobile-alt text-2xl text-gray-400 mb-1"></i>
                        <p class="text-xs text-gray-500 font-semibold">Click to upload</p>
                    </div>

                    <input type="file" class="hidden" onchange="handleBannerUpload(this)">
                    <input type="hidden" name="banner_items[${index}][mobile_image_url]" value="${data.mobile_image || ''}">
                    <div class="upload-progress-container w-full"></div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                 <label class="block text-xs font-bold mb-1">Video URL (Desktop)</label>
                <input type="text" name="banner_items[${index}][video_url]" value="${data.video_url || ''}" placeholder="https://... or assets/video.mp4" class="w-full border p-2 rounded text-sm">
                 <p class="text-[10px] text-gray-500">Use this if file upload fails due to size limit.</p>
            </div>
             <div>
                 <label class="block text-xs font-bold mb-1">Video URL (Mobile)</label>
                <input type="text" name="banner_items[${index}][mobile_video_url]" value="${data.mobile_video_url || ''}" placeholder="https://... or assets/mob_video.mp4" class="w-full border p-2 rounded text-sm">
            </div>
        </div>

         <div class="mb-2">
            <label class="block text-xs font-bold mb-1">Heading</label>
            <input type="text" name="banner_items[${index}][heading]" value="${data.heading || ''}" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="mb-2">
            <label class="block text-xs font-bold mb-1">Text</label>
            <textarea name="banner_items[${index}][text]" class="w-full border p-2 rounded text-sm" rows="2">${data.text || ''}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                 <label class="block text-xs font-bold mb-1">Btn Label</label>
                <input type="text" name="banner_items[${index}][btn_text]" value="${data.btn_text || ''}" class="w-full border p-2 rounded text-sm">
            </div>
             <div>
                 <label class="block text-xs font-bold mb-1">Btn Link</label>
                <input type="text" name="banner_items[${index}][btn_link]" value="${data.btn_link || ''}" class="w-full border p-2 rounded text-sm">
            </div>
        </div>
    `;
    bannerContainer.appendChild(div);
}

// Wrapper to handle upload and UI toggling (placeholder vs preview)
async function handleBannerUpload(input) {
    const container = input.parentElement;
    const placeholder = container.querySelector('.placeholder-box');
    
    // Hide placeholder immediately on select
    if(placeholder) placeholder.classList.add('hidden');
    
    // Call existing chunkUpload logic
    await chunkUpload(input);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const container = input.parentElement;
            const img = container.querySelector('.preview-img');
            const video = container.querySelector('.preview-video');
            
            if (file.type.startsWith('video/') && video) {
                video.src = e.target.result;
                video.classList.remove('hidden');
                if(img) img.classList.add('hidden');
            } else if (img) {
                img.src = e.target.result;
                img.classList.remove('hidden');
                if(video) video.classList.add('hidden');
            }
        }
        reader.readAsDataURL(file);
    }
}

async function chunkUpload(input) {
    if (!input.files || !input.files[0]) return;
    
    // 1. Preview first (before we clear input)
    previewImage(input); 
    
    const file = input.files[0];
    const container = input.parentElement;
    let progress = container.querySelector('.upload-progress');
    if (!progress) {
        progress = document.createElement('div');
        progress.className = 'upload-progress text-xs text-blue-600 mt-1 font-bold';
        container.appendChild(progress);
    }
    
    const CHUNK_SIZE = 1024 * 1024; // 1MB
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId = Date.now().toString(36) + Math.random().toString(36).substr(2);
    
    progress.innerText = 'Starting Upload...';
    progress.className = 'upload-progress text-xs text-blue-600 mt-1 font-bold';
    input.classList.add('opacity-50', 'cursor-not-allowed');
    // input.disabled = true; // Don't disable completely or form might be weird
    
    try {
        for (let i = 0; i < totalChunks; i++) {
            const start = i * CHUNK_SIZE;
            const end = Math.min(file.size, start + CHUNK_SIZE);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append('chunk', chunk);
            formData.append('file_name', file.name);
            formData.append('chunk_index', i);
            formData.append('total_chunks', totalChunks);
            formData.append('upload_id', uploadId);
            
            const req = await fetch('upload_chunk.php', { method: 'POST', body: formData });
            
            if (!req.ok) throw new Error('Network error: ' + req.status);
            
            const res = await req.json();
            if (res.error) throw new Error(res.error);
            
            if (res.status === 'done') {
                progress.innerText = 'Upload Complete!';
                progress.classList.replace('text-blue-600', 'text-green-600');
                
                // Update Hidden Input with Path
                const hiddenUrl = container.querySelector('input[type="hidden"]');
                if (hiddenUrl) hiddenUrl.value = res.path;
                
                // Clear File Input to Avoid Post Size Error
                input.value = ''; 
                input.classList.remove('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            const percent = Math.round(((i + 1) / totalChunks) * 100);
            progress.innerText = `Uploading ${percent}%...`;
        }
    } catch (e) {
        console.error(e);
        progress.innerText = 'Error: ' + e.message;
        progress.classList.replace('text-blue-600', 'text-red-600');
        input.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

// DRAG AND DROP LOGIC FOR ADMIN SECTIONS
const dragList = document.getElementById('draggableSections');
const orderInput = document.getElementById('sectionOrderJson');
let draggedItem = null;

if (dragList) {
    const items = dragList.querySelectorAll('[data-section-id]');
    
    // Initial Order Save
    updateSectionOrder();

    items.forEach(item => {
        const handle = item.querySelector('.cursor-move');
        if(!handle) return; // safety

        handle.addEventListener('dragstart', function(e) {
            draggedItem = item;
            e.dataTransfer.effectAllowed = 'move';
            item.classList.add('opacity-50');
            // Allow drag from handle bubble up to item logic if needed, 
            // but usually we drag the item itself. 
            // Actually, HTML5 DnD requires the *draggable element* to be dragged.
            // We set draggable="true" on the handle parent (the header), but we want to move the whole SECTION DIV.
            // Let's adjust: We should set draggable="true" on the SECTION DIV, but only allow dragging if target is handle.
            // For simplicity in this native implementation, let's keep draggable on header but move the parent.
            // Wait, standard practice: Draggable on the ITEM. Handle is visual.
        });
        
        // Revised DnD: Put listener on the PARENT div (item)
        item.setAttribute('draggable', 'true');
        item.addEventListener('dragstart', function(e) {
            draggedItem = item;
            item.classList.add('opacity-50', 'border-blue-500', 'border-2');
        });

        item.addEventListener('dragend', function() {
            draggedItem = null;
            item.classList.remove('opacity-50', 'border-blue-500', 'border-2');
            updateSectionOrder();
        });

        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (this === draggedItem) return;
            
            const bounding = this.getBoundingClientRect();
            const offset = bounding.y + (bounding.height / 2);
            
            if (e.clientY - offset > 0) {
                this.style['border-bottom'] = '2px solid #3b82f6';
                this.style['border-top'] = '';
            } else {
                this.style['border-top'] = '2px solid #3b82f6';
                this.style['border-bottom'] = '';
            }
        });
        
        item.addEventListener('dragleave', function() {
            this.style['border-top'] = '';
            this.style['border-bottom'] = '';
        });

        item.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style['border-top'] = '';
            this.style['border-bottom'] = '';
            
            if (this === draggedItem) return;

            const bounding = this.getBoundingClientRect();
            const offset = bounding.y + (bounding.height / 2);
            
            if (e.clientY - offset > 0) {
                this.parentNode.insertBefore(draggedItem, this.nextSibling);
            } else {
                this.parentNode.insertBefore(draggedItem, this);
            }
        });
    });
}

function updateSectionOrder() {
    if(!dragList) return;
    const currentItems = dragList.querySelectorAll('[data-section-id]');
    const order = Array.from(currentItems).map(item => item.getAttribute('data-section-id'));
    orderInput.value = JSON.stringify(order);
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
