<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

// Get messages for this store
$messages = $db->fetchAll(
    "SELECT * FROM support_messages WHERE store_id = ? ORDER BY created_at DESC LIMIT 50",
    [$storeId]
);

$unreadCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM support_messages WHERE store_id = ? AND status = 'open'",
    [$storeId]
)['count'];

$pageTitle = 'Inbox';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Inbox</h1>
    <p class="text-gray-600">Dashboard > Inbox</p>
</div>

<div class="admin-card">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
        <h2 class="text-xl font-bold">Recent Support Messages</h2>
        <span class="bg-red-500 text-white text-xs px-3 py-1 rounded-full"><?php echo $unreadCount; ?> unread</span>
    </div>
    
    <div class="space-y-0">
        <?php if (empty($messages)): ?>
            <div class="text-center py-12">
                <i class="fas fa-inbox text-5xl text-gray-200 mb-4"></i>
                <p class="text-gray-500">No messages found for your store.</p>
                <a href="<?php echo url('admin/support.php'); ?>" class="text-blue-600 hover:underline mt-2 inline-block">Manage Support</a>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="border-b border-gray-100 last:border-0 py-4 hover:bg-gray-50 transition px-2 rounded cursor-pointer" onclick="window.location.href='support.php'">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 <?php echo $msg['status'] === 'open' ? 'bg-blue-500' : 'bg-gray-400'; ?> rounded-full flex items-center justify-center text-white font-bold shrink-0">
                            <?php 
                                $initials = '';
                                $names = explode(' ', $msg['customer_name']);
                                foreach($names as $n) $initials .= strtoupper(substr($n, 0, 1));
                                echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($msg['customer_name']); ?></h3>
                                <span class="text-xs text-gray-500"><?php echo time_elapsed_string($msg['created_at']); ?></span>
                            </div>
                            <p class="text-sm font-medium text-gray-700 truncate"><?php echo htmlspecialchars($msg['subject']); ?></p>
                            <p class="text-sm text-gray-500 mt-1 line-clamp-1"><?php echo htmlspecialchars($msg['message']); ?></p>
                        </div>
                        <?php if ($msg['status'] === 'open'): ?>
                            <span class="w-2.5 h-2.5 bg-blue-500 rounded-full mt-2"></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="mt-6 text-center pt-4 border-t">
                <a href="<?php echo url('admin/support.php'); ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                    View All Support Messages <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
