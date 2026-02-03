<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$baseUrl = getBaseUrl();
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['id']);
    try {
        $db->execute("DELETE FROM pages WHERE id = ? AND store_id = ?", [$id, $storeId]);
        $_SESSION['flash_success'] = "Page deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error deleting page: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$pageTitle = 'Manage Custom Pages';
require_once __DIR__ . '/../includes/admin-header.php';

$pages = $db->fetchAll("SELECT * FROM pages WHERE store_id = ? ORDER BY created_at DESC", [$storeId]);
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold">Custom Pages</h1>
        <p class="text-gray-600">Create and manage content pages like About Us, FAQ, etc.</p>
    </div>
    <a href="page-edit.php" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
        <i class="fas fa-plus mr-2"></i> Create New Page
    </a>
</div>



<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full leading-normal">
        <thead>
            <tr>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    Title / Slug
                </th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    Status
                </th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    Last Updated
                </th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    Actions
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pages)): ?>
                <tr>
                    <td colspan="4" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">
                        No pages found. Click "Create New Page" to get started.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pages as $page): 
                    // Copy just the slug as requested
                    $pageUrl = $page['slug'];
                ?>
                <tr>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <div class="flex items-center">
                            <div class="ml-3">
                                <p class="text-gray-900 whitespace-no-wrap font-bold">
                                    <?php echo htmlspecialchars($page['title']); ?>
                                </p>
                                
                                <!-- Clickable Slug/URL with Copy Functionality -->
                                <div class="flex items-center gap-2 mt-1 cursor-pointer group" onclick="copyToClipboard('<?php echo htmlspecialchars($pageUrl); ?>')">
                                    <p class="text-gray-500 whitespace-no-wrap text-xs group-hover:text-blue-600 transition-colors" title="Click to copy slug">
                                        /<?php echo htmlspecialchars($page['slug']); ?>
                                    </p>
                                     <i class="fas fa-copy text-[10px] text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <span class="relative inline-block px-3 py-1 font-semibold leading-tight <?php echo $page['status'] === 'active' ? 'text-green-900' : 'text-red-900'; ?>">
                            <span aria-hidden="true" class="absolute inset-0 <?php echo $page['status'] === 'active' ? 'bg-green-200' : 'bg-red-200'; ?> opacity-50 rounded-full"></span>
                            <span class="relative"><?php echo ucfirst($page['status']); ?></span>
                        </span>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">
                            <?php echo date('M d, Y', strtotime($page['updated_at'])); ?>
                        </p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                        <a href="<?php echo $baseUrl; ?>/<?php echo $page['slug']; ?>" target="_blank" class="text-gray-600 hover:text-gray-900 mr-4">View Page</a>
                        <a href="page-edit.php?page_id=<?php echo $page['page_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">Edit</a>
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Copy to Clipboard Script -->
<script>
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        // Secure context (HTTPS)
        navigator.clipboard.writeText(text).then(() => {
            showToast('Link copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            fallbackCopyTextToClipboard(text);
        });
    } else {
        // Fallback
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    
    // Ensure it's not visible but part of DOM
    textArea.style.position = "fixed";
    textArea.style.left = "-9999px";
    textArea.style.top = "0";
    document.body.appendChild(textArea);
    
    textArea.focus();
    textArea.select();

    try {
        var successful = document.execCommand('copy');
        var msg = successful ? 'Link copied to clipboard!' : 'Failed to copy link';
        showToast(msg);
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
        showToast('Unable to copy link automatically');
    }

    document.body.removeChild(textArea);
}

function showToast(message) {
    // Check if existing toast
    const existing = document.getElementById('toast-notification');
    if (existing) existing.remove();

    const el = document.createElement('div');
    el.id = 'toast-notification';
    el.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded shadow-lg text-sm z-50 animate-bounce flex items-center gap-2';
    el.innerHTML = '<i class="fas fa-check-circle text-green-400"></i> ' + message;
    
    document.body.appendChild(el);
    
    // Remove after 3 seconds
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
