<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Email.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();
date_default_timezone_set('Asia/Kolkata');

$db = Database::getInstance();

// Handle reply action
if (isset($_POST['action']) && $_POST['action'] === 'reply' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $reply = trim($_POST['reply'] ?? '');
    
    if (!empty($reply)) {
        $message = $db->fetchOne("SELECT * FROM support_messages WHERE id = ?", [$id]);
        
        if ($message) {
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId && isset($_SESSION['user_email'])) {
                 $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                 $storeId = $storeUser['store_id'] ?? null;
            }
            // Update message
            $db->execute(
                "UPDATE support_messages SET admin_reply = ?, status = 'replied', replied_at = NOW(), replied_by = ? WHERE id = ? AND store_id = ?",
                [$reply, $auth->getCurrentUser()['id'], $id, $storeId]
            );
            
            // Try to send email to customer
            $emailSent = false;
            try {
                $email = new Email();
                $emailSent = $email->sendSupportReply(
                    $message['customer_email'],
                    $message['customer_name'],
                    $message['subject'],
                    $reply
                );
            } catch (Exception $e) {
                error_log("Support reply email error: " . $e->getMessage());
            }
            
            // Set appropriate message
            if ($emailSent) {
                $_SESSION['success'] = "Reply sent successfully! Customer will receive an email.";
            } else {
                $_SESSION['success'] = "Reply saved successfully! (Email not sent - configure SMTP in System Settings)";
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle status change
if (isset($_POST['action']) && $_POST['action'] === 'change_status' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'] ?? 'open';
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }
    $db->execute("UPDATE support_messages SET status = ? WHERE id = ? AND store_id = ?", [$status, $id, $storeId]);
    $_SESSION['success'] = "Status updated successfully.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }
    $db->execute("DELETE FROM support_messages WHERE id = ? AND store_id = ?", [$id, $storeId]);
    $_SESSION['success'] = "Message deleted successfully.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$filter = $_GET['filter'] ?? 'all';
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$whereClause = "WHERE store_id = ?";
$params = [$storeId];

if ($filter === 'open') {
    $whereClause .= " AND status = 'open'";
} elseif ($filter === 'replied') {
    $whereClause .= " AND status = 'replied'";
} elseif ($filter === 'closed') {
    $whereClause .= " AND status = 'closed'";
}

// Get total count
$totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM support_messages $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get messages
$messages = $db->fetchAll(
    "SELECT * FROM support_messages $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Get counts
$openCount = $db->fetchOne("SELECT COUNT(*) as count FROM support_messages WHERE store_id = ? AND status = 'open'", [$storeId])['count'];
$repliedCount = $db->fetchOne("SELECT COUNT(*) as count FROM support_messages WHERE store_id = ? AND status = 'replied'", [$storeId])['count'];
$closedCount = $db->fetchOne("SELECT COUNT(*) as count FROM support_messages WHERE store_id = ? AND status = 'closed'", [$storeId])['count'];

$pageTitle = 'Support Messages';
require_once __DIR__ . '/../includes/admin-header.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Customer Messages</h1>
            <p class="text-gray-600 text-sm mt-1"><?php echo $openCount; ?> open message<?php echo $openCount !== 1 ? 's' : ''; ?></p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Total Messages</div>
            <div class="text-2xl font-bold"><?php echo number_format($totalCount); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Open</div>
            <div class="text-2xl font-bold text-red-600"><?php echo number_format($openCount); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Replied</div>
            <div class="text-2xl font-bold text-blue-600"><?php echo number_format($repliedCount); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Closed</div>
            <div class="text-2xl font-bold text-green-600"><?php echo number_format($closedCount); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex gap-2">
        <a href="?filter=all" class="px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            All
        </a>
        <a href="?filter=open" class="px-4 py-2 rounded <?php echo $filter === 'open' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-exclamation-circle mr-1"></i> Open
        </a>
        <a href="?filter=replied" class="px-4 py-2 rounded <?php echo $filter === 'replied' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-reply mr-1"></i> Replied
        </a>
        <a href="?filter=closed" class="px-4 py-2 rounded <?php echo $filter === 'closed' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
            <i class="fas fa-check-circle mr-1"></i> Closed
        </a>
    </div>

    <!-- Messages List -->
    <div class="bg-white rounded shadow overflow-hidden">
        <?php if (empty($messages)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                <p>No messages found.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($messages as $msg): ?>
                    <div class="p-6 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($msg['subject']); ?></h3>
                                    <span class="px-2 py-1 text-xs rounded <?php 
                                        echo $msg['status'] === 'open' ? 'bg-red-100 text-red-800' : 
                                            ($msg['status'] === 'replied' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); 
                                    ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mb-2">
                                    <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($msg['customer_name']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($msg['customer_email']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="far fa-clock mr-1"></i> <?php echo date('M d, Y H:i', strtotime($msg['created_at'] . ' UTC')); ?>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded mb-4">
                            <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($msg['message']); ?></p>
                        </div>

                        <?php if ($msg['admin_reply']): ?>
                            <div class="bg-blue-50 p-4 rounded mb-4 border-l-4 border-blue-500">
                                <div class="text-sm text-gray-600 mb-2">
                                    <i class="fas fa-reply mr-1"></i> Your Reply 
                                    <?php if ($msg['replied_at']): ?>
                                        <span class="mx-2">•</span>
                                        <?php echo date('M d, Y H:i', strtotime($msg['replied_at'] . ' UTC')); ?>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($msg['admin_reply']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="flex gap-2">
                            <?php if ($msg['status'] !== 'replied'): ?>
                                <button onclick="showReplyForm(<?php echo $msg['id']; ?>)" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                    <i class="fas fa-reply mr-1"></i> Reply
                                </button>
                            <?php endif; ?>

                            <?php if ($msg['status'] !== 'closed'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                    <input type="hidden" name="status" value="closed">
                                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
                                        <i class="fas fa-check mr-1"></i> Close
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm btn-loading">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </form>
                        </div>

                        <!-- Reply Form (Hidden by default) -->
                        <div id="replyForm<?php echo $msg['id']; ?>" class="hidden mt-4">
                            <form method="POST" class="bg-white p-4 rounded border">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your Reply</label>
                                <textarea name="reply" rows="4" required 
                                          class="w-full border rounded px-3 py-2 mb-3"
                                          placeholder="Type your response here..."></textarea>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 btn-loading">
                                        Send Reply
                                    </button>
                                    <button type="button" onclick="hideReplyForm(<?php echo $msg['id']; ?>)" 
                                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </form>
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
                       class="px-4 py-2 border rounded hover:bg-gray-50">Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" 
                       class="px-4 py-2 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" 
                       class="px-4 py-2 border rounded hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
function showReplyForm(id) {
    document.getElementById('replyForm' + id).classList.remove('hidden');
}

function hideReplyForm(id) {
    document.getElementById('replyForm' + id).classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
