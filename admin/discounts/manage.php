<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Discounts';
require_once __DIR__ . '/../../includes/admin-header.php';

$db = Database::getInstance();
$id = $_GET['id'] ?? null;
$discount = null;
$error = '';
$success = '';

if ($id) {
    $discount = $db->fetchOne("SELECT * FROM discounts WHERE id = ?", [$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $type = $_POST['type'] ?? 'percentage';
    $value = $_POST['value'] ?? 0;
    $minPurchase = $_POST['min_purchase_amount'] ?? null;
    $maxDiscount = $_POST['max_discount_amount'] ?? null;
    $usageLimit = $_POST['usage_limit'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    try {
        if ($id && $discount) {
            $db->execute(
                "UPDATE discounts SET code = ?, name = ?, description = ?, type = ?, value = ?, 
                 min_purchase_amount = ?, max_discount_amount = ?, usage_limit = ?, 
                 start_date = ?, end_date = ?, status = ? WHERE id = ?",
                [$code, $name, $description, $type, $value, $minPurchase, $maxDiscount, 
                 $usageLimit, $startDate, $endDate, $status, $id]
            );
            $success = 'Discount updated successfully!';
        } else {
            $db->insert(
                "INSERT INTO discounts (code, name, description, type, value, min_purchase_amount, 
                 max_discount_amount, usage_limit, start_date, end_date, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$code, $name, $description, $type, $value, $minPurchase, $maxDiscount, 
                 $usageLimit, $startDate, $endDate, $status]
            );
            $success = 'Discount created successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$discounts = $db->fetchAll("SELECT * FROM discounts ORDER BY created_at DESC");
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Discount Management</h1>
    <p class="text-gray-600 text-sm md:text-base">Dashboard > Discounts</p>
</div>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error mb-4">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form -->
    <div class="lg:col-span-2">
        <div class="admin-card">
            <h2 class="text-lg md:text-xl font-bold mb-4"><?php echo $id ? 'Edit' : 'Add'; ?> Discount</h2>
            <form method="POST" action="">
                <div class="admin-form-group">
                    <label class="admin-form-label">Discount Code *</label>
                    <input type="text" 
                           name="code" 
                           required
                           value="<?php echo htmlspecialchars($discount['code'] ?? ''); ?>"
                           class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Name *</label>
                    <input type="text" 
                           name="name" 
                           required
                           value="<?php echo htmlspecialchars($discount['name'] ?? ''); ?>"
                           class="admin-form-input">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label">Description</label>
                    <textarea name="description" 
                              class="admin-form-input admin-form-textarea"><?php echo htmlspecialchars($discount['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Type *</label>
                        <select name="type" required class="admin-form-select">
                            <option value="percentage" <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                            <option value="fixed" <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Value *</label>
                        <input type="number" 
                               name="value" 
                               step="0.01"
                               required
                               value="<?php echo $discount['value'] ?? 0; ?>"
                               class="admin-form-input">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Min Purchase Amount</label>
                        <input type="number" 
                               name="min_purchase_amount" 
                               step="0.01"
                               value="<?php echo $discount['min_purchase_amount'] ?? ''; ?>"
                               class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Max Discount Amount</label>
                        <input type="number" 
                               name="max_discount_amount" 
                               step="0.01"
                               value="<?php echo $discount['max_discount_amount'] ?? ''; ?>"
                               class="admin-form-input">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Usage Limit</label>
                        <input type="number" 
                               name="usage_limit" 
                               value="<?php echo $discount['usage_limit'] ?? ''; ?>"
                               class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Status *</label>
                        <select name="status" required class="admin-form-select">
                            <option value="active" <?php echo (empty($discount) || (!empty($discount) && ($discount['status'] ?? '') === 'active')) ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (!empty($discount) && ($discount['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Start Date</label>
                        <input type="datetime-local" 
                               name="start_date" 
                               value="<?php echo (!empty($discount) && !empty($discount['start_date'])) ? date('Y-m-d\TH:i', strtotime($discount['start_date'])) : ''; ?>"
                               class="admin-form-input">
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">End Date</label>
                        <input type="datetime-local" 
                               name="end_date" 
                               value="<?php echo (!empty($discount) && !empty($discount['end_date'])) ? date('Y-m-d\TH:i', strtotime($discount['end_date'])) : ''; ?>"
                               class="admin-form-input">
                    </div>
                </div>
                
                <button type="submit" class="admin-btn admin-btn-primary">
                    <?php echo $id ? 'Update' : 'Create'; ?> Discount
                </button>
            </form>
        </div>
    </div>
    
    <!-- List -->
    <div>
        <div class="admin-card">
            <h2 class="text-lg md:text-xl font-bold mb-4">All Discounts</h2>
            <div class="space-y-2">
                <?php foreach ($discounts as $disc): ?>
                <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50">
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($disc['code']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($disc['name']); ?></p>
                    </div>
                    <a href="?id=<?php echo $disc['id']; ?>" class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

