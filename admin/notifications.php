<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$notification = new Notification();

// Handle mark as read action
if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['id'])) {
    $storeId = $_SESSION['store_id'] ?? null;
    $notification->markAsRead(intval($_POST['id']), $storeId);
    if (!isset($_POST['ajax'])) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle mark all as read
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $storeId = $_SESSION['store_id'] ?? null;
    $notification->markAllAsRead($storeId);
    if (!isset($_POST['ajax'])) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $storeId = $_SESSION['store_id'] ?? null;
    $db->execute("DELETE FROM admin_notifications WHERE id = ? AND store_id = ?", [$id, $storeId]);
    $_SESSION['success'] = "Notification deleted successfully.";
    if (!isset($_POST['ajax'])) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$storeId = $_SESSION['store_id'] ?? null;
$whereClause = "WHERE store_id = ?";
$params = [$storeId];

if ($filter === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filter === 'cancel') {
    $whereClause .= " AND (type = 'cancel' OR title LIKE '%Cancellation%')";
} elseif ($filter === 'refund') {
    $whereClause .= " AND (type = 'refund' OR title LIKE '%Refund%')";
} elseif ($filter !== 'all') {
    $whereClause .= " AND type = ?";
    $params[] = $filter;
}

// Get total count
$totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_notifications $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get notifications
$notifications = $db->fetchAll(
    "SELECT * FROM admin_notifications $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Get unread count
$unreadCount = $notification->getUnreadCount($storeId);

// Ajax Handler
if ((isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_POST['ajax']) && $_POST['ajax'] == '1')) {
    ob_start();
    renderNotificationsHtml($notifications, $totalPages, $page, $filter);
    $html = ob_get_clean();
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $_SESSION['success'] ?? 'Action completed',
            'html' => $html,
            'unreadCount' => $unreadCount
        ]);
        unset($_SESSION['success']);
    } else {
        echo $html;
    }
    exit;
}

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/admin-header.php';

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Notifications</h1>
            <p class="text-gray-600 text-sm mt-1" id="unread-count-display">
                <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>
            </p>
        </div>
        <div class="flex gap-2" id="mark-all-read-container">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" class="inline" onsubmit="return handleAjaxForm(event, this)">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                        <i class="fas fa-check-double mr-2"></i>Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="mb-4 flex gap-2">
        <a href="?filter=all" data-filter="all" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            All
        </a>
        <a href="?filter=unread" data-filter="unread" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            Unread
        </a>
        <a href="?filter=order" data-filter="order" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'order' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-shopping-cart mr-1"></i> Orders
        </a>
        <a href="?filter=customer" data-filter="customer" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'customer' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-user-plus mr-1"></i> Customers
        </a>
        <a href="?filter=subscriber" data-filter="subscriber" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'subscriber' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-envelope mr-1"></i> Subscribers
        </a>
        <a href="?filter=cancel" data-filter="cancel" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'cancel' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-ban mr-1"></i> Cancel
        </a>
        <a href="?filter=refund" data-filter="refund" class="filter-btn ajax-link px-4 py-2 rounded <?php echo $filter === 'refund' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-undo mr-1"></i> Refund
        </a>
    </div>

    <div id="notifications-container">
        <?php renderNotificationsHtml($notifications, $totalPages, $page, $filter); ?>
    </div>
</div>

<?php
function renderNotificationsHtml($notifications, $totalPages, $page, $filter) {
    ?>
    <!-- Notifications List -->
    <div class="bg-white rounded shadow overflow-hidden">
        <?php if (empty($notifications)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-bell-slash text-4xl mb-3 text-gray-300"></i>
                <p>No notifications found.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($notifications as $notif): ?>
                    <?php
                    // Determine display type (handle legacy 'order' type for cancels/refunds)
                    $displayType = $notif['type'];
                    if ($notif['type'] === 'order') {
                        if (stripos($notif['title'], 'Cancellation') !== false) {
                            $displayType = 'cancel';
                        } elseif (stripos($notif['title'], 'Refund') !== false) {
                            $displayType = 'refund';
                        }
                    }

                    $iconClass = [
                        'order' => 'fas fa-shopping-cart',
                        'customer' => 'fas fa-user-plus',
                        'subscriber' => 'fas fa-envelope',
                        'cancel' => 'fas fa-ban',
                        'refund' => 'fas fa-undo',
                    ][$displayType] ?? 'fas fa-bell';
                    
                    $colorClass = [
                        'order' => 'bg-green-500',
                        'customer' => 'bg-blue-500',
                        'subscriber' => 'bg-purple-500',
                        'cancel' => 'bg-red-500',
                        'refund' => 'bg-orange-500',
                    ][$displayType] ?? 'bg-gray-500';
                    
                    $isUnread = $notif['is_read'] == 0;
                    ?>
                    <div class="p-4 hover:bg-gray-50 transition <?php echo $isUnread ? 'bg-blue-50' : ''; ?>">
                        <div class="flex items-start gap-4">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-full <?php echo $colorClass; ?> flex items-center justify-center">
                                    <i class="<?php echo $iconClass; ?> text-white"></i>
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-sm font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                            <?php if ($isUnread): ?>
                                                <span class="ml-2 inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <p class="text-xs text-gray-400 mt-2">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php 
                                            $time = strtotime($notif['created_at']);
                                            $diff = time() - $time;
                                            if ($diff < 60) echo 'Just now';
                                            elseif ($diff < 3600) echo floor($diff / 60) . ' minutes ago';
                                            elseif ($diff < 86400) echo floor($diff / 3600) . ' hours ago';
                                            elseif ($diff < 604800) echo floor($diff / 86400) . ' days ago';
                                            else echo date('M d, Y', $time);
                                            ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center gap-10 ml-4">
                                        <?php 
                                        if ($notif['link']): 
                                            $finalLink = $notif['link'];
                                            if ($notif['type'] === 'order' && strpos($finalLink, '/admin/orders/list.php?search=') !== false) {
                                                $orderNum = explode('search=', $finalLink)[1];
                                                $finalLink = '/admin/orders/detail.php?order_number=' . $orderNum;
                                            }
                                        ?>
                                            <a href="<?php echo url($finalLink); ?>" 
                                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                View
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($isUnread): ?>
                                            <form method="POST" class="inline" onsubmit="return handleAjaxForm(event, this)">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                                <button type="submit" class="text-gray-600 hover:text-gray-800" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="inline" onsubmit="return handleAjaxForm(event, this)">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" 
                       class="pagination-link ajax-link px-4 py-2 border rounded hover:bg-gray-50">Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" 
                       class="pagination-link ajax-link px-4 py-2 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" 
                       class="pagination-link ajax-link px-4 py-2 border rounded hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
    <?php
}
?>

<script>
window.initNotifications = function() {
    var container = document.getElementById('notifications-container');
    if (!container) return;

    // Remove old listeners to prevent duplicates
    if (window.notificationsClickHandler) {
        document.body.removeEventListener('click', window.notificationsClickHandler);
    }

    window.notificationsClickHandler = function(e) {
        var link = e.target.closest('a.ajax-link');
        if (link && link.classList.contains('pagination-link')) {
            e.preventDefault();
            window.loadNotifications(link.href);
        }
    };
    document.body.addEventListener('click', window.notificationsClickHandler);

    window.loadNotifications = function(url) {
        var fetchUrl = new URL(url);
        fetchUrl.searchParams.set('ajax', '1');
        
        var notifyContainer = document.getElementById('notifications-container');
        if (notifyContainer) notifyContainer.style.opacity = '0.5';
        
        fetch(fetchUrl)
            .then(response => response.text())
            .then(html => {
                var newContainer = document.getElementById('notifications-container');
                if (newContainer) {
                    newContainer.innerHTML = html;
                    newContainer.style.opacity = '1';
                }
                
                window.history.pushState({}, '', url);
                window.updateNotificationFilterStyles(new URL(url).searchParams.get('filter') || 'all');
            })
            .catch(err => {
                console.error('Error:', err);
                var errContainer = document.getElementById('notifications-container');
                if (errContainer) errContainer.style.opacity = '1';
            });
    };

    window.updateNotificationFilterStyles = function(activeFilter) {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.dataset.filter === activeFilter) {
                btn.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50');
                btn.classList.add('bg-blue-600', 'text-white');
            } else {
                 btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-50');
                 btn.classList.remove('bg-blue-600', 'text-white');
            }
        });
    };
};

window.handleAjaxForm = async function(e, form) {
    if (e) e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var originalText = '';
    
    if (btn) {
        originalText = btn.innerHTML;
        if (window.setBtnLoading) window.setBtnLoading(btn, true);
        else btn.disabled = true;
    }
    
    var formData = new FormData(form);
    formData.append('ajax', '1');
    
    try {
        var response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        var data = await response.json();
        
        if (data.success) {
            var contentContainer = document.getElementById('notifications-container');
            if (contentContainer) contentContainer.innerHTML = data.html;
            
            // Update Unread Count
            var unreadDisplay = document.getElementById('unread-count-display');
            if (unreadDisplay) {
                unreadDisplay.innerText = data.unreadCount + " unread notification" + (data.unreadCount !== 1 ? 's' : '');
            }
            
            // Hide Mark All button if 0
            var markAllContainer = document.getElementById('mark-all-read-container');
            if (markAllContainer && data.unreadCount === 0) {
                markAllContainer.style.display = 'none';
            }
            
            // Show notification if helper exists
            if (window.showToast) window.showToast(data.message, 'success');
        }
    } catch (err) {
        console.error(err);
    } finally {
        if (btn) {
           if (window.setBtnLoading) window.setBtnLoading(btn, false);
           else btn.disabled = false;
        }
    }
    return false;
};

// Initialize
window.initNotifications();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
