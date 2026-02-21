<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();
$success = '';
$error = '';

// Determine Store ID early for all actions
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// 1. Fetch all pages & products for the sidebar and dropdowns
$allPages = $db->fetchAll("SELECT id, name, slug FROM landing_pages WHERE store_id = ? ORDER BY id ASC", [$storeId]);
$products = $db->fetchAll("SELECT id, product_id, name, price, currency FROM products WHERE store_id = ? AND status = 'active' ORDER BY name ASC", [$storeId]);

// 2. Determine which page to edit
$selectedSlug = $_GET['page'] ?? null;

// If no page selected, default to the first available page
if (!$selectedSlug && !empty($allPages)) {
    $selectedSlug = $allPages[0]['slug'];
}

// 3. Fetch the Landing Page record
$lp = null;
if ($selectedSlug) {
    $lp = $db->fetchOne("SELECT * FROM landing_pages WHERE slug = ? AND store_id = ?", [$selectedSlug, $storeId]);
}

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
                // Default styles to ensure new pages aren't black and all sections are enabled
                $defaultOrder = ['secOtherPlatforms', 'secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews'];
                $defaultPageConfig = json_encode(['theme_color' => '#5F8D76', 'body_bg_color' => '#ffffff', 'body_text_color' => '#000000', 'section_order' => $defaultOrder]);
                
                $enabledJson = json_encode(['show' => 1, 'items' => []]);
                // Determine Store ID
                $storeId = $_SESSION['store_id'] ?? null;
                if (!$storeId && isset($_SESSION['user_email'])) {
                     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                     $storeId = $storeUser['store_id'] ?? null;
                }
                
                $db->insert("INSERT INTO landing_pages (name, slug, product_id, page_config, banner_data, stats_data, why_data, about_data, testimonials_data, newsletter_data, platforms_data, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                    [$newName, $newSlug, $productId, $defaultPageConfig, $enabledJson, $enabledJson, $enabledJson, $enabledJson, $enabledJson, $enabledJson, $enabledJson, $storeId]);
                $_SESSION['flash_success'] = "New landing page created!";
                header("Location: " . url('admin/special-page?page=' . urlencode($newSlug)));
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
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }
        // Delete Page logic without default restriction
        $check = $db->fetchOne("SELECT slug FROM landing_pages WHERE id = ? AND store_id = ?", [$delId, $storeId]);
        if ($check) {
            $db->execute("DELETE FROM landing_pages WHERE id = ? AND store_id = ?", [$delId, $storeId]);
            $_SESSION['flash_success'] = "Page deleted successfully.";
            header("Location: " . url('admin/special-page'));
            exit;
        } else {
            $error = "Page not found.";
        }
    } elseif ($action === 'save_settings') {
        // Update existing page
        $id = $_POST['page_id'];
        // Update $selectedSlug from input if redirecting? No, keep query param or what the form had.
        // We redirect to the SAME page slug we are editing.
        
        // General Data
        $productId = $_POST['product_id'];
        $pageName = $_POST['page_name'] ?? '';
        $heroTitle = $_POST['hero_title'];
        $heroSubtitle = $_POST['hero_subtitle'];
        $heroDesc = $_POST['hero_description'];
        $themeColor = $_POST['theme_color'] ?? '#5F8D76';
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
        $aboutBg = $_POST['about_bg_color'] ?? '';
        $aboutTextColor = $_POST['about_text_color'] ?? '#333333';
        $testiBg = $_POST['testimonials_bg_color'] ?? '';
        $testiText = $_POST['testimonials_text_color'] ?? '';
        $newsBg = $_POST['newsletter_bg_color'] ?? '';
        $newsTextColor = $_POST['newsletter_text_color'] ?? '#333333';
        
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
                    'btn_link' => preg_replace('/\.php(\?|$)/', '$1', $item['btn_link'] ?? '')
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
        
        // Handle Other Platforms Items (Dynamic)
        $platformItems = [];
        if (isset($_POST['platform_items']) && is_array($_POST['platform_items'])) {
            foreach ($_POST['platform_items'] as $index => $pItem) {
                $pImgUrl = $pItem['image'] ?? '';
                
                // Handle File Upload for this platform
                if (isset($_FILES['platform_items']['name'][$index]['image_file']) && $_FILES['platform_items']['error'][$index]['image_file'] === UPLOAD_ERR_OK) {
                    $fname = time() . '_plat_' . $index . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['platform_items']['name'][$index]['image_file']));
                    $target = $uploadDir . $fname;
                    if (move_uploaded_file($_FILES['platform_items']['tmp_name'][$index]['image_file'], $target)) {
                        $pImgUrl = 'assets/images/banner/' . $fname;
                    }
                }

                if (trim($pItem['name']) !== '' || trim($pItem['link']) !== '') {
                    $platformItems[] = [
                        'name' => $pItem['name'] ?? '',
                        'link' => preg_replace('/\.php(\?|$)/', '$1', $pItem['link'] ?? ''),
                        'image' => $pImgUrl,
                        'bg' => $pItem['bg'] ?? '#ffffff',
                        'text' => $pItem['text'] ?? '#111827'
                    ];
                }
            }
        }
        $otherPlatformJson = json_encode($platformItems);

        // Single fields for backward compatibility (optional, using first item)
        $firstBan = $bannerItems[0] ?? [];
        $finalBannerImg = $firstBan['image'] ?? ($_POST['banner_image'] ?? '');
        $finalBannerMobImg = $firstBan['mobile_image'] ?? ($_POST['banner_mobile_image'] ?? '');

        // Prepare Grouped Data for all 10 Sections (Array Storage)
        
        // 1. Header Data
        $headerGrouped = json_encode([
            'nav_links' => json_decode($_POST['nav_links_json'] ?? '[]', true)
        ]);

        // 2. Hero Data
        $heroGrouped = json_encode([
            'title' => $heroTitle,
            'subtitle' => $heroSubtitle,
            'description' => $heroDesc,
            'bg_color' => $heroBg,
            'text_color' => $heroText,
            'image' => $lp['hero_image'] ?? ''
        ]);

        // 3. Banner Data
        $bannerGrouped = json_encode([
            'show' => $showBanner,
            'bg_color' => $bannerBg,
            'text_color' => $bannerText,
            'items' => json_decode($bannerSectionsJson, true)
        ]);

        // 4. Stats Data
        $statsGrouped = json_encode([
            'show' => $showStats,
            'bg_color' => $statsBg,
            'text_color' => $statsText,
            'items' => json_decode($statsDataJson, true)
        ]);

        // 5. Why Data
        $whyGrouped = json_encode([
            'show' => $showWhy,
            'title' => $whyTitle,
            'bg_color' => $whyBg,
            'text_color' => $whyText,
            'items' => json_decode($whyDataJson, true)
        ]);

        // 6. About Data
        $aboutGrouped = json_encode([
            'show' => $showAbout,
            'title' => $aboutTitle,
            'text' => $aboutText,
            'image' => $_POST['about_image'] ?? '',
            'bg_color' => $aboutBg,
            'text_color' => $aboutTextColor
        ]);

        // 7. Testimonials Data
        $testiGrouped = json_encode([
            'show' => $showTestimonials,
            'title' => $testiTitle,
            'bg_color' => $testiBg,
            'text_color' => $testiText,
            'items' => json_decode($testimonialsDataJson, true)
        ]);

        // 8. Newsletter Data
        $newsGrouped = json_encode([
            'show' => $showNewsletter,
            'title' => $newsTitle,
            'text' => $newsText,
            'bg_color' => $newsBg,
            'text_color' => $newsTextColor
        ]);

        // 9. Platforms Data
        $platformsGrouped = json_encode([
            'show' => isset($_POST['show_other_platforms']) ? 1 : 0,
            'items' => json_decode($otherPlatformJson, true)
        ]);

        // 10. Footer Data
        $footerGrouped = json_encode([
            'show_extra' => isset($_POST['show_footer_extra']) ? 1 : 0,
            'extra_content' => $_POST['footer_extra_content'] ?? '',
            'extra_bg' => $_POST['footer_extra_bg'] ?? '#f8f9fa',
            'extra_text' => $_POST['footer_extra_text'] ?? '#333333',
            'copyright' => $_POST['copyright_text'] ?? ''
        ]);

        // Global Config
        $pageConfigGrouped = json_encode([
            'theme_color' => $themeColor,
            'body_bg_color' => $bodyBg,
            'body_text_color' => $bodyText,
            'section_order' => json_decode($_POST['section_order_json'] ?? '[]', true)
        ]);

        // SEO Data
        $seoGrouped = json_encode([
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'custom_schema' => $_POST['custom_schema'] ?? ''
        ]);

        try {
            if (!empty($id)) {
                $db->execute(
                    "UPDATE landing_pages SET 
                        product_id=?, name=?, slug=?,
                        header_data=?, hero_data=?, banner_data=?, stats_data=?, why_data=?, about_data=?, 
                        testimonials_data=?, newsletter_data=?, platforms_data=?, footer_data=?,
                        page_config=?, seo_data=?
                    WHERE id=? AND store_id=?",
                    [
                        $productId, $pageName, $selectedSlug,
                        $headerGrouped, $heroGrouped, $bannerGrouped, $statsGrouped, $whyGrouped, $aboutGrouped,
                        $testiGrouped, $newsGrouped, $platformsGrouped, $footerGrouped,
                        $pageConfigGrouped, $seoGrouped,
                        $id, $storeId
                    ]
                );
            } else {
                // Insert new Default Page if ID is missing (fallback scenario)
                $db->insert(
                    "INSERT INTO landing_pages (
                        product_id, name, slug, 
                        header_data, hero_data, banner_data, stats_data, why_data, about_data, 
                        testimonials_data, newsletter_data, platforms_data, footer_data, 
                        page_config, seo_data, store_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $productId, $pageName, $selectedSlug ?: 'default',
                        $headerGrouped, $heroGrouped, $bannerGrouped, $statsGrouped, $whyGrouped, $aboutGrouped,
                        $testiGrouped, $newsGrouped, $platformsGrouped, $footerGrouped,
                        $pageConfigGrouped, $seoGrouped, $storeId
                    ]
                );
            }

            $_SESSION['flash_success'] = "Page settings saved successfully!";
            session_write_close(); // Ensure session is saved before redirect
            header("Location: " . url('admin/special-page?page=' . urlencode($selectedSlug)));
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

<?php
// --- DATA RECONCILIATION & BACKWARD COMPATIBILITY ---
// Map JSON grouped data back to individual keys for the form UI to avoid undefined index warnings.
if ($lp) {
    $headerGrp = json_decode($lp['header_data'] ?? '{}', true) ?: [];
    $heroGrp = json_decode($lp['hero_data'] ?? '{}', true) ?: [];
    $bannerGrp = json_decode($lp['banner_data'] ?? '{}', true) ?: [];
    $statsGrp = json_decode($lp['stats_data'] ?? '{}', true) ?: [];
    $whyGrp = json_decode($lp['why_data'] ?? '{}', true) ?: [];
    $aboutGrp = json_decode($lp['about_data'] ?? '{}', true) ?: [];
    $testiGrp = json_decode($lp['testimonials_data'] ?? '{}', true) ?: [];
    $newsGrp = json_decode($lp['newsletter_data'] ?? '{}', true) ?: [];
    $platformsGrp = json_decode($lp['platforms_data'] ?? '{}', true) ?: [];
    $footerGrp = json_decode($lp['footer_data'] ?? '{}', true) ?: [];
    $configGrp = json_decode($lp['page_config'] ?? '{}', true) ?: [];
    $seoGrp = json_decode($lp['seo_data'] ?? '{}', true) ?: [];

    // Map Hero
    $lp['hero_title'] = $heroGrp['title'] ?? '';
    $lp['hero_subtitle'] = $heroGrp['subtitle'] ?? '';
    $lp['hero_description'] = $heroGrp['description'] ?? '';
    $lp['hero_bg_color'] = $heroGrp['bg_color'] ?? '';
    $lp['hero_text_color'] = $heroGrp['text_color'] ?? '';
    $lp['hero_image'] = $heroGrp['image'] ?? '';

    // Map Stats
    $lp['show_stats'] = $statsGrp['show'] ?? 0;
    $lp['stats_bg_color'] = $statsGrp['bg_color'] ?? '';
    $lp['stats_text_color'] = $statsGrp['text_color'] ?? '';
    $lp['stats_data'] = json_encode($statsGrp['items'] ?? []);

    // Map Why
    $lp['show_why'] = $whyGrp['show'] ?? 0;
    $lp['why_title'] = $whyGrp['title'] ?? '';
    $lp['why_bg_color'] = $whyGrp['bg_color'] ?? '';
    $lp['why_text_color'] = $whyGrp['text_color'] ?? '';
    $lp['why_data'] = json_encode($whyGrp['items'] ?? []);

    // Map About
    $lp['show_about'] = $aboutGrp['show'] ?? 0;
    $lp['about_title'] = $aboutGrp['title'] ?? '';
    $lp['about_text'] = $aboutGrp['text'] ?? '';
    $lp['about_image'] = $aboutGrp['image'] ?? '';
    $lp['about_bg_color'] = $aboutGrp['bg_color'] ?? '';
    $lp['about_text_color'] = $aboutGrp['text_color'] ?? '';

    // Map Testimonials
    $lp['show_testimonials'] = $testiGrp['show'] ?? 0;
    $lp['testimonials_title'] = $testiGrp['title'] ?? '';
    $lp['testimonials_bg_color'] = $testiGrp['bg_color'] ?? '';
    $lp['testimonials_text_color'] = $testiGrp['text_color'] ?? '';
    $lp['testimonials_data'] = json_encode($testiGrp['items'] ?? []);

    // Map Newsletter
    $lp['show_newsletter'] = $newsGrp['show'] ?? 0;
    $lp['newsletter_title'] = $newsGrp['title'] ?? '';
    $lp['newsletter_text'] = $newsGrp['text'] ?? '';
    $lp['newsletter_bg_color'] = $newsGrp['bg_color'] ?? '';
    $lp['newsletter_text_color'] = $newsGrp['text_color'] ?? '';

    // Map Banner
    $lp['show_banner'] = $bannerGrp['show'] ?? 0;
    $lp['banner_bg_color'] = $bannerGrp['bg_color'] ?? '';
    $lp['banner_text_color'] = $bannerGrp['text_color'] ?? '';
    $lp['banner_sections_json'] = json_encode($bannerGrp['items'] ?? []);

    // Map Platforms
    $lp['show_other_platforms'] = $platformsGrp['show'] ?? 0;
    $lp['Other_platform'] = json_encode($platformsGrp['items'] ?? []);

    // Map Footer & Config
    $lp['theme_color'] = $configGrp['theme_color'] ?? '';
    $lp['body_bg_color'] = $configGrp['body_bg_color'] ?? '';
    $lp['body_text_color'] = $configGrp['body_text_color'] ?? '';
    $lp['nav_links'] = json_encode($headerGrp['nav_links'] ?? []);
    $lp['section_order'] = json_encode($configGrp['section_order'] ?? []);

    // Map Footer Extra (aligned with grouped save logic)
    $lp['show_footer_extra'] = $footerGrp['show_extra'] ?? 0;
    $lp['footer_extra_content'] = $footerGrp['extra_content'] ?? '';
    $lp['footer_extra_bg'] = $footerGrp['extra_bg'] ?? '#f8f9fa';
    $lp['footer_extra_text'] = $footerGrp['extra_text'] ?? '#333333';
    $lp['copyright_text'] = $footerGrp['copyright'] ?? '';

    // Map SEO
    $lp['meta_title'] = $seoGrp['meta_title'] ?? '';
    $lp['meta_description'] = $seoGrp['meta_description'] ?? '';
    $lp['custom_schema'] = $seoGrp['custom_schema'] ?? '';
}
?>

<div class="mb-6 flex justify-between items-end sticky top-0 bg-[#f7f8fc] py-4 z-50 border-b border-gray-200 px-1">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 pt-4 pl-2">Landing Page Manager</h1>
        <p class="text-sm text-gray-500 mt-1 pl-2">Dashboard > Settings > Landing Pages</p>
    </div>
    <div class="flex items-center gap-3">
        <?php if (!empty($selectedSlug)): ?>
        <a href="<?php echo url($selectedSlug); ?>" target="_blank" class="bg-white text-gray-700 border border-gray-300 px-4 py-2.5 rounded shadow-sm hover:bg-gray-50 flex items-center font-bold text-sm transition">
            <i class="fas fa-external-link-alt mr-2 text-gray-400"></i> View Live Page
        </a>
        <?php endif; ?>
        <button type="submit" form="landingPageForm" class="bg-blue-600 text-white px-6 py-2.5 rounded shadow hover:bg-blue-700 transition transform hover:-translate-y-0.5 font-bold text-sm flex items-center btn-loading">
            <i class="fas fa-save mr-2"></i> Save All Settings
        </button>
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
                               <a href="<?php echo url($p['slug']); ?>" target="_blank" class="text-xs text-gray-400 font-normal hover:text-blue-600 hover:underline truncate mr-2 block flex-1 min-w-0" title="<?php echo url($p['slug']); ?>">
                                   <?php echo url($p['slug']); ?> <i class="fas fa-external-link-alt text-[10px] ml-1"></i>
                               </a>
                               <button type="button" onclick="copyLink('<?php echo url($p['slug']); ?>')" class="text-xs text-gray-400 hover:text-blue-600 p-1 flex-shrink-0" title="Copy Link">
                                   <i class="fas fa-copy"></i>
                               </button>
                           </div>
                        </div>
                        <form method="POST" class="ml-2 flex-shrink-0" onsubmit="confirmDeletePage(event, this)">
                            <input type="hidden" name="action" value="delete_page">
                            <input type="hidden" name="page_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-600 p-2" title="Delete Page">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
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
                        <option value="<?php echo $pr['product_id']; ?>"><?php echo htmlspecialchars($pr['name']); ?></option>
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
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-bold">Editing:</h2>
                    <input type="text" name="page_name" value="<?php echo htmlspecialchars($lp['name']); ?>" class="border p-2 rounded font-bold text-xl" placeholder="Landing Page Name">
                </div>
                <div class="flex items-center gap-6">
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Theme Color:</label>
                         <div class="flex items-center gap-1">
                            <input type="color" name="theme_color" id="theme_color" value="<?php echo htmlspecialchars(($lp['theme_color'] ?? '') ?: '#5F8D76'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('theme_color_text').value = this.value">
                            <input type="text" id="theme_color_text" value="<?php echo htmlspecialchars(($lp['theme_color'] ?? '') ?: '#5F8D76'); ?>" class="w-24 border p-1 rounded text-sm uppercase" oninput="document.getElementById('theme_color').value = this.value">
                         </div>
                     </div>
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Body Background Color:</label>
                         <div class="flex items-center gap-1">
                            <input type="color" name="body_bg_color" id="body_bg_color" value="<?php echo htmlspecialchars(($lp['body_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('body_bg_color_text').value = this.value">
                            <input type="text" id="body_bg_color_text" value="<?php echo htmlspecialchars(($lp['body_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-24 border p-1 rounded text-sm uppercase" oninput="document.getElementById('body_bg_color').value = this.value">
                         </div>
                     </div>
                     <div class="flex items-center gap-2">
                         <label class="text-sm font-bold">Body Text Color:</label>
                         <div class="flex items-center gap-1">
                            <input type="color" name="body_text_color" id="body_text_color" value="<?php echo htmlspecialchars(($lp['body_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('body_text_color_text').value = this.value">
                            <input type="text" id="body_text_color_text" value="<?php echo htmlspecialchars(($lp['body_text_color'] ?? '') ?: '#000000'); ?>" class="w-24 border p-1 rounded text-sm uppercase" oninput="document.getElementById('body_text_color').value = this.value">
                         </div>
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
                                <option value="<?php echo $pr['product_id']; ?>" <?php echo $pr['product_id'] == $lp['product_id'] ? 'selected' : ''; ?>>
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
                            <textarea name="hero_description" class="w-full border p-2 rounded" rows="3" placeholder="Leave empty to use main product description"><?php echo htmlspecialchars($lp['hero_description'] ?? ''); ?></textarea>
                        </div>
                     </div>
                     <!-- Added Hero Colors -->
                     <div class="grid grid-cols-2 gap-4 mt-4 border-t pt-4">
                         <div>
                            <label class="block text-xs font-bold mb-1">Hero Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="hero_bg_color" id="hero_bg_color" value="<?php echo htmlspecialchars(($lp['hero_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('hero_bg_color_text').value = this.value">
                                <input type="text" id="hero_bg_color_text" value="<?php echo htmlspecialchars(($lp['hero_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('hero_bg_color').value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">Hero Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="hero_text_color" id="hero_text_color" value="<?php echo htmlspecialchars(($lp['hero_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('hero_text_color_text').value = this.value">
                                <input type="text" id="hero_text_color_text" value="<?php echo htmlspecialchars(($lp['hero_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('hero_text_color').value = this.value">
                            </div>
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
                                <input type="checkbox" name="show_banner" class="mr-2" <?php echo ($lp['show_banner'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secBanner', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secBanner" class="p-4 hidden bg-white">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="banner_bg_color" id="banner_bg_color" value="<?php echo htmlspecialchars(($lp['banner_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('banner_bg_color_text').value = this.value">
                                    <input type="text" id="banner_bg_color_text" value="<?php echo htmlspecialchars(($lp['banner_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('banner_bg_color').value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="banner_text_color" id="banner_text_color" value="<?php echo htmlspecialchars(($lp['banner_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('banner_text_color_text').value = this.value">
                                    <input type="text" id="banner_text_color_text" value="<?php echo htmlspecialchars(($lp['banner_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('banner_text_color').value = this.value">
                                </div>
                            </div>
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
                                <input type="checkbox" name="show_stats" class="mr-2" <?php echo ($lp['show_stats'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secStats', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secStats" class="p-4 hidden bg-white">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="stats_bg_color" id="stats_bg_color" value="<?php echo htmlspecialchars(($lp['stats_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('stats_bg_color_text').value = this.value">
                                    <input type="text" id="stats_bg_color_text" value="<?php echo htmlspecialchars(($lp['stats_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('stats_bg_color').value = this.value">
                                </div>
                            </div>
                             <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="stats_text_color" id="stats_text_color" value="<?php echo htmlspecialchars(($lp['stats_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('stats_text_color_text').value = this.value">
                                    <input type="text" id="stats_text_color_text" value="<?php echo htmlspecialchars(($lp['stats_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('stats_text_color').value = this.value">
                                </div>
                            </div>
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
                                <input type="checkbox" name="show_why" class="mr-2" <?php echo ($lp['show_why'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secWhy', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secWhy" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="why_title" value="<?php echo htmlspecialchars($lp['why_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="why_bg_color" id="why_bg_color" value="<?php echo htmlspecialchars(($lp['why_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('why_bg_color_text').value = this.value">
                                    <input type="text" id="why_bg_color_text" value="<?php echo htmlspecialchars(($lp['why_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('why_bg_color').value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="why_text_color" id="why_text_color" value="<?php echo htmlspecialchars(($lp['why_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('why_text_color_text').value = this.value">
                                    <input type="text" id="why_text_color_text" value="<?php echo htmlspecialchars(($lp['why_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('why_text_color').value = this.value">
                                </div>
                            </div>
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
                                <input type="checkbox" name="show_about" class="mr-2" <?php echo ($lp['show_about'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secAbout', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secAbout" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="about_title" value="<?php echo htmlspecialchars($lp['about_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="mb-4"><label class="block text-sm font-bold mb-1">Text Content</label><textarea name="about_text" class="w-full border p-2 rounded" rows="3" placeholder="Leave empty to use main product description"><?php echo htmlspecialchars($lp['about_text'] ?? ''); ?></textarea></div>
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
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="about_bg_color" id="about_bg_color" value="<?php echo htmlspecialchars(($lp['about_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('about_bg_color_text').value = this.value">
                                    <input type="text" id="about_bg_color_text" value="<?php echo htmlspecialchars(($lp['about_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('about_bg_color').value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="about_text_color" id="about_text_color" value="<?php echo htmlspecialchars(($lp['about_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('about_text_color_text').value = this.value">
                                    <input type="text" id="about_text_color_text" value="<?php echo htmlspecialchars(($lp['about_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('about_text_color').value = this.value">
                                </div>
                            </div>
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
                                <input type="checkbox" name="show_testimonials" class="mr-2" <?php echo ($lp['show_testimonials'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secTesti', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                     <div id="secTesti" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="testimonials_title" value="<?php echo htmlspecialchars($lp['testimonials_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="testimonials_bg_color" id="testimonials_bg_color" value="<?php echo htmlspecialchars(($lp['testimonials_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('testimonials_bg_color_text').value = this.value">
                                    <input type="text" id="testimonials_bg_color_text" value="<?php echo htmlspecialchars(($lp['testimonials_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('testimonials_bg_color').value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="testimonials_text_color" id="testimonials_text_color" value="<?php echo htmlspecialchars(($lp['testimonials_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('testimonials_text_color_text').value = this.value">
                                    <input type="text" id="testimonials_text_color_text" value="<?php echo htmlspecialchars(($lp['testimonials_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('testimonials_text_color').value = this.value">
                                </div>
                            </div>
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
                                <input type="checkbox" name="show_newsletter" class="mr-2" <?php echo ($lp['show_newsletter'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secNews', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                    <div id="secNews" class="p-4 hidden bg-white">
                         <div class="mb-4"><label class="block text-sm font-bold mb-1">Section Title</label><input type="text" name="newsletter_title" value="<?php echo htmlspecialchars($lp['newsletter_title'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="mb-4"><label class="block text-sm font-bold mb-1">Text Content</label><input type="text" name="newsletter_text" value="<?php echo htmlspecialchars($lp['newsletter_text'] ?? ''); ?>" class="w-full border p-2 rounded"></div>
                        <div class="grid grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-bold mb-1">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="newsletter_bg_color" id="newsletter_bg_color" value="<?php echo htmlspecialchars(($lp['newsletter_bg_color'] ?? '') ?: '#ffffff'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('newsletter_bg_color_text').value = this.value">
                                    <input type="text" id="newsletter_bg_color_text" value="<?php echo htmlspecialchars(($lp['newsletter_bg_color'] ?? '') ?: '#ffffff'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('newsletter_bg_color').value = this.value">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="newsletter_text_color" id="newsletter_text_color" value="<?php echo htmlspecialchars(($lp['newsletter_text_color'] ?? '') ?: '#000000'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('newsletter_text_color_text').value = this.value">
                                    <input type="text" id="newsletter_text_color_text" value="<?php echo htmlspecialchars(($lp['newsletter_text_color'] ?? '') ?: '#000000'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('newsletter_text_color').value = this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $sections['secNews'] = ob_get_clean();

                // --- OTHER PLATFORMS SECTION ---
                ob_start(); ?>
                 <div class="border border-gray-200 rounded overflow-hidden" data-section-id="secOtherPlatforms">
                    <div class="bg-gray-50 p-3 flex justify-between items-center border-b cursor-move" draggable="true">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-grip-vertical text-gray-400"></i>
                             <span class="font-bold text-gray-700">Other Platforms (Marketplaces)</span>
                        </div>
                         <div class="flex items-center gap-3">
                             <label class="flex items-center cursor-pointer text-sm">
                                <input type="checkbox" name="show_other_platforms" class="mr-2" <?php echo ($lp['show_other_platforms'] ?? 1) ? 'checked' : ''; ?>> Enable
                            </label>
                            <button type="button" onclick="toggleSection('secOtherPlatforms', this)" class="p-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chevron-down text-gray-500"></i></button>
                        </div>
                    </div>
                     <div id="secOtherPlatforms" class="p-4 hidden bg-white">
                        <p class="text-xs text-gray-500 mb-4">Add direct links to your product on other platforms like Amazon, Flipkart, etc. to build trust.</p>
                        <div id="platformItemsContainer" class="space-y-6"></div>
                        <button type="button" onclick="addPlatformItem()" class="mt-2 text-blue-600 font-bold hover:underline text-sm">+ Add Marketplace Platform</button>
                        <input type="hidden" id="initialPlatformData" value="<?php echo htmlspecialchars($lp['Other_platform'] ?? '[]'); ?>">
                    </div>
                </div>
                <?php $sections['secOtherPlatforms'] = ob_get_clean();

                // 2. Define Default Order
                $defaultOrder = ['secOtherPlatforms', 'secHeaders', 'secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews'];
                
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
                         <div>
                            <label class="block text-xs font-bold mb-1">Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="footer_extra_bg" id="footer_extra_bg" value="<?php echo htmlspecialchars($lp['footer_extra_bg'] ?? '#f8f9fa'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('footer_extra_bg_text').value = this.value">
                                <input type="text" id="footer_extra_bg_text" value="<?php echo htmlspecialchars($lp['footer_extra_bg'] ?? '#f8f9fa'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('footer_extra_bg').value = this.value">
                             </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="footer_extra_text" id="footer_extra_text" value="<?php echo htmlspecialchars($lp['footer_extra_text'] ?? '#333333'); ?>" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('footer_extra_text_text').value = this.value">
                                <input type="text" id="footer_extra_text_text" value="<?php echo htmlspecialchars($lp['footer_extra_text'] ?? '#333333'); ?>" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('footer_extra_text').value = this.value">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold mb-2">Rich Text Content</label>
                        <textarea name="footer_extra_content" class="rich-text-editor w-full border p-2 rounded h-64"><?php echo htmlspecialchars($lp['footer_extra_content'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Use this editor to add text, links, images, tables, and style them as needed. This content appears after the footer.</p>
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
var navLinks = [];
var navLinksJsonEl = document.getElementById('navLinksJson');
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
var footerData = {};
var footerDataJsonEl = document.getElementById('footerDataJson');
var footerCopyrightEl = document.getElementById('footerCopyright');

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
window.toggleSection = function(id, btn) {
    var allSections = ['secHeaders', 'secBanner', 'secStats', 'secWhy', 'secAbout', 'secTesti', 'secNews', 'secOtherPlatforms', 'secFooter'];
    var current = document.getElementById(id);
    var isHidden = current.classList.contains('hidden');
    
    // First hide ALL sections
    allSections.forEach(secId => {
        var el = document.getElementById(secId);
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
    var slug = text.toLowerCase()
        .replace(/[^\w\s-]/g, '') // remove invalid chars
        .replace(/\s+/g, '-')         // replace spaces with -
        .replace(/-+/g, '-')         // collapse dashes
        .trim();
    document.getElementById('newPageSlug').value = slug;
}

// 1. Auto-generate slug when typing in Page Name
// 1. Auto-generate slug when typing in Page Name
var newPageNameEl = document.getElementById('newPageName');
if (newPageNameEl) {
    newPageNameEl.addEventListener('input', function() {
        updateSlug(this.value);
    });
}

// 2. Auto-fill Page Name & Slug when Product is selected
var newProductSelectEl = document.getElementById('newProductSelect');
if (newProductSelectEl) {
    newProductSelectEl.addEventListener('change', function() {
        var selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value && newPageNameEl) {
            // Get product name
            var rawName = selectedOption.text.trim();
            
            // Update Page Name field
            newPageNameEl.value = rawName;
            
            // Directly update Slug (bypassing event listeners to be safe)
            updateSlug(rawName);
        }
    });
}
var bannerContainer = document.getElementById('bannerSectionsContainer');
var bannerCount = 0;
var bannerData = [];

// Initialize Banners
try {
    var rawData = document.getElementById('initialBannerData').value;
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
var initialStatsData = document.getElementById('initialStatsData') ? document.getElementById('initialStatsData').value : '[]';
var statsContainer = document.getElementById('statsItemsContainer');
var statsCount = 0;

function addStatItem(data = {}) {
    var index = statsCount++;
    var div = document.createElement('div');
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
        var stats = JSON.parse(initialStatsData);
        if (Array.isArray(stats) && stats.length > 0) {
            stats.forEach(s => addStatItem(s));
        } else {
            // Add 4 default empty ones if none exist
            for(var i=0; i<4; i++) addStatItem();
        }
    } catch(e) {  
        for(var i=0; i<4; i++) addStatItem(); 
    }
}

// --- Initialize Why Items ---
var initialWhyData = document.getElementById('initialWhyData') ? document.getElementById('initialWhyData').value : '[]';
var whyContainer = document.getElementById('whyItemsContainer');
var whyCount = 0;

function addWhyItem(data = {}) {
    var index = whyCount++;
    var div = document.createElement('div');
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
        var items = JSON.parse(initialWhyData);
        if (Array.isArray(items) && items.length > 0) {
            items.forEach(item => addWhyItem(item));
        } else {
            for(var i=0; i<3; i++) addWhyItem();
        }
    } catch(e) { 
        for(var i=0; i<3; i++) addWhyItem(); 
    }
}

// --- Initialize Testimonials Items ---
var initialTestimonialsData = document.getElementById('initialTestimonialsData') ? document.getElementById('initialTestimonialsData').value : '[]';
var testimonialsContainer = document.getElementById('testimonialsItemsContainer');
var reviewCount = 0;

function addTestimonialsItem(data = {}) {
    var index = reviewCount++;
    var div = document.createElement('div');
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
        var reviews = JSON.parse(initialTestimonialsData);
        if (Array.isArray(reviews) && reviews.length > 0) {
            reviews.forEach(r => addTestimonialsItem(r));
        } else {
             // Add one minimal example
             addTestimonialsItem();
        }
    } catch(e) { addTestimonialsItem(); }
}

// --- Initialize Other Platforms ---
var initialPlatformData = document.getElementById('initialPlatformData') ? document.getElementById('initialPlatformData').value : '[]';
var platformContainer = document.getElementById('platformItemsContainer');
var platformItemCount = 0;

function addPlatformItem(data = {}) {
    var index = platformItemCount++;
    var div = document.createElement('div');
    div.className = 'border border-gray-200 p-4 rounded bg-gray-50 relative animate-fade-in mb-4';
    div.innerHTML = `
         <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold bg-white rounded-full w-6 h-6 flex items-center justify-center shadow-sm" title="Remove">
            <i class="fas fa-times text-xs"></i>
         </button>
         <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-1">
                 <label class="block text-xs font-bold mb-1">Platform Logo</label>
                 <div class="relative group cursor-pointer border-2 border-dashed border-gray-300 rounded-lg p-2 bg-white flex flex-col items-center justify-center h-32 hover:border-blue-500 hover:bg-blue-50 transition" onclick="this.querySelector('input[type=file]').click()">
                    
                    ${data.image ? `
                        <img src="${data.image.startsWith('http') ? data.image : '../'+data.image}" class="preview-img max-h-24 w-auto object-contain rounded mb-1">
                    ` : `
                        <img class="preview-img hidden max-h-24 w-auto object-contain rounded mb-1">
                    `}
                    
                    <div class="text-center placeholder-box ${data.image ? 'hidden' : ''}">
                        <i class="fas fa-store text-xl text-gray-400 mb-1"></i>
                        <p class="text-[10px] text-gray-500 font-semibold leading-tight">Platform logo<br>(SVG/PNG)</p>
                    </div>

                    <input type="file" class="hidden" onchange="handleBannerUpload(this)">
                    <input type="hidden" name="platform_items[${index}][image]" value="${data.image || ''}">
                    <div class="upload-progress-container w-full absolute bottom-1 left-0 px-2"></div>
                </div>
            </div>
            <div class="md:col-span-3 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                         <label class="block text-xs font-bold mb-1">Title</label>
                         <input type="text" name="platform_items[${index}][name]" value="${data.name || ''}" class="w-full border p-2 rounded text-sm" placeholder="Amazon, Flipkart, etc.">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                             <label class="block text-xs font-bold mb-1">Background Color</label>
                             <div class="flex items-center gap-2">
                                <input type="color" id="platform_bg_${index}" name="platform_items[${index}][bg]" value="${data.bg || '#ffffff'}" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('platform_bg_text_${index}').value = this.value">
                                <input type="text" id="platform_bg_text_${index}" value="${data.bg || '#ffffff'}" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('platform_bg_${index}').value = this.value">
                             </div>
                        </div>
                        <div>
                             <label class="block text-xs font-bold mb-1">Text Color</label>
                             <div class="flex items-center gap-2">
                                <input type="color" id="platform_text_${index}" name="platform_items[${index}][text]" value="${data.text || '#111827'}" class="h-8 w-8 p-0 border border-gray-300 rounded cursor-pointer" oninput="document.getElementById('platform_text_text_${index}').value = this.value">
                                <input type="text" id="platform_text_text_${index}" value="${data.text || '#111827'}" class="w-full border p-1 rounded text-sm uppercase" oninput="document.getElementById('platform_text_${index}').value = this.value">
                             </div>
                        </div>
                    </div>
                </div>
                <div>
                     <label class="block text-xs font-bold mb-1">Product Link (Full URL)</label>
                    <input type="text" name="platform_items[${index}][link]" value="${data.link || ''}" class="w-full border p-2 rounded text-sm" placeholder="https://amazon.in/dp/...">
                </div>
            </div>
         </div>
    `;
    if (platformContainer) platformContainer.appendChild(div);
}

if (initialPlatformData && platformContainer) {
    try {
        var platforms = JSON.parse(initialPlatformData);
        if (Array.isArray(platforms) && platforms.length > 0) {
            platforms.forEach(p => addPlatformItem(p));
        } else {
             // addPlatformItem();
        }
    } catch(e) { console.error("Error parsing platform data", e); }
}

// Toggle Section Content Visibility
window.toggleSection = function(id, btn) {
    var el = document.getElementById(id);
    if(el) el.classList.toggle('hidden');
    
    // Toggle Icon if button passed
    if(btn) {
        var icon = btn.querySelector('i');
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
    var index = bannerCount++;
    var div = document.createElement('div');
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
                <input type="text" name="banner_items[${index}][btn_link]" value="${data.btn_link || ''}" class="w-full border p-2 rounded text-sm" placeholder="e.g. /shop">
            </div>
        </div>
    `;
    bannerContainer.appendChild(div);
}

// Wrapper to handle upload and UI toggling (placeholder vs preview)
async function handleBannerUpload(input) {
    var container = input.parentElement;
    var placeholder = container.querySelector('.placeholder-box');
    
    // Hide placeholder immediately on select
    if(placeholder) placeholder.classList.add('hidden');
    
    // Call existing chunkUpload logic
    await chunkUpload(input);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            var container = input.parentElement;
            var img = container.querySelector('.preview-img');
            var video = container.querySelector('.preview-video');
            
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
    
    var file = input.files[0];
    var container = input.parentElement;
    var progress = container.querySelector('.upload-progress');
    if (!progress) {
        progress = document.createElement('div');
        progress.className = 'upload-progress text-xs text-blue-600 mt-1 font-bold';
        container.appendChild(progress);
    }
    
    var CHUNK_SIZE = 1024 * 1024; // 1MB
    var totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    var uploadId = Date.now().toString(36) + Math.random().toString(36).substr(2);
    
    progress.innerText = 'Starting Upload...';
    progress.className = 'upload-progress text-xs text-blue-600 mt-1 font-bold';
    input.classList.add('opacity-50', 'cursor-not-allowed');
    // input.disabled = true; // Don't disable completely or form might be weird
    
    try {
        for (var i = 0; i < totalChunks; i++) {
            var start = i * CHUNK_SIZE;
            var end = Math.min(file.size, start + CHUNK_SIZE);
            var chunk = file.slice(start, end);
            
            var formData = new FormData();
            formData.append('chunk', chunk);
            formData.append('file_name', file.name);
            formData.append('chunk_index', i);
            formData.append('total_chunks', totalChunks);
            formData.append('upload_id', uploadId);
            
            var req = await fetch('upload_chunk.php', { method: 'POST', body: formData });
            
            if (!req.ok) throw new Error('Network error: ' + req.status);
            
            var res = await req.json();
            if (res.error) throw new Error(res.error);
            
            if (res.status === 'done') {
                progress.innerText = 'Upload Complete!';
                progress.classList.replace('text-blue-600', 'text-green-600');
                
                // Update Hidden Input with Path
                var hiddenUrl = container.querySelector('input[type="hidden"]');
                if (hiddenUrl) hiddenUrl.value = res.path;
                
                // Clear File Input to Avoid Post Size Error
                input.value = ''; 
                input.classList.remove('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            var percent = Math.round(((i + 1) / totalChunks) * 100);
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
var dragList = document.getElementById('draggableSections');
var orderInput = document.getElementById('sectionOrderJson');
var draggedItem = null;

if (dragList) {
    var items = dragList.querySelectorAll('[data-section-id]');
    
    // Initial Order Save
    updateSectionOrder();

    items.forEach(item => {
        var handle = item.querySelector('.cursor-move');
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
            
            var bounding = this.getBoundingClientRect();
            var offset = bounding.y + (bounding.height / 2);
            
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

            var bounding = this.getBoundingClientRect();
            var offset = bounding.y + (bounding.height / 2);
            
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
    var currentItems = dragList.querySelectorAll('[data-section-id]');
    var order = Array.from(currentItems).map(item => item.getAttribute('data-section-id'));
    orderInput.value = JSON.stringify(order);
}

</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
