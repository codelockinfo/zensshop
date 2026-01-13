<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $db->execute("DELETE FROM subscribers WHERE id = ?", [$id]);
    $_SESSION['success'] = "Subscriber deleted successfully.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle export action
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $subscribers = $db->fetchAll("SELECT * FROM subscribers ORDER BY created_at DESC");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Email', 'User ID', 'Subscribed At']);
    
    foreach ($subscribers as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['email'],
            $sub['user_id'] ?? 'Guest',
            $sub['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
$params = [];

if ($search) {
    $whereClause = "WHERE email LIKE ?";
    $params[] = "%$search%";
}

// Get total count
$totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM subscribers $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get subscribers with customer information
$subscribers = $db->fetchAll(
    "SELECT s.*, c.name as customer_name 
     FROM subscribers s 
     LEFT JOIN customers c ON s.user_id = c.id 
     $whereClause 
     ORDER BY s.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

$pageTitle = 'Subscriber List';
require_once __DIR__ . '/../../includes/admin-header.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Newsletter Subscribers</h1>
        <div class="flex gap-2">
            <a href="?export=csv" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
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

    <!-- Search -->
    <div class="mb-4">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by email..." 
                   class="flex-1 border rounded px-4 py-2">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
                <a href="?" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Total Subscribers</div>
            <div class="text-2xl font-bold"><?php echo number_format($totalCount); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Logged-in Users</div>
            <div class="text-2xl font-bold">
                <?php 
                $loggedInCount = $db->fetchOne("SELECT COUNT(*) as count FROM subscribers WHERE user_id IS NOT NULL")['count'];
                echo number_format($loggedInCount); 
                ?>
            </div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-gray-500 text-sm">Guest Subscribers</div>
            <div class="text-2xl font-bold">
                <?php 
                $guestCount = $db->fetchOne("SELECT COUNT(*) as count FROM subscribers WHERE user_id IS NULL")['count'];
                echo number_format($guestCount); 
                ?>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscribed At</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            No subscribers found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $sub): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm"><?php echo $sub['id']; ?></td>
                            <td class="px-6 py-4 text-sm font-medium"><?php echo htmlspecialchars($sub['email']); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($sub['user_id'] && $sub['customer_name']): ?>
                                    <a href="<?php echo $baseUrl; ?>/admin/customers/view.php?id=<?php echo $sub['user_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($sub['customer_name']); ?>
                                    </a>
                                <?php elseif ($sub['user_id']): ?>
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <i class="fas fa-user"></i> Customer #<?php echo $sub['user_id']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                        <i class="fas fa-user-slash"></i> Guest
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this subscriber?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-4 py-2 border rounded hover:bg-gray-50">Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-4 py-2 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-4 py-2 border rounded hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
