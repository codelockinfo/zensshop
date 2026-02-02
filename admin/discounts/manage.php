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
    $storeId = $_SESSION['store_id'] ?? null;
    $discount = $db->fetchOne("SELECT * FROM discounts WHERE id = ? AND store_id = ?", [$id, $storeId]);
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    try {
        $deleteId = (int)$_GET['delete_id'];
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
             $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
             $storeId = $storeUser['store_id'] ?? null;
        }

        $db->execute("DELETE FROM discounts WHERE id = ? AND store_id = ?", [$deleteId, $storeId]);
        $success = 'Discount deleted successfully!';
        
        // If we were editing this discount, clear the state
        if ($id == $deleteId) {
            $id = null;
            $discount = null;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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
    $usageLimitPerCustomer = $_POST['usage_limit_per_customer'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    // Determine Store ID
    $storeId = $_SESSION['store_id'] ?? null;
    if (!$storeId && isset($_SESSION['user_email'])) {
         $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
         $storeId = $storeUser['store_id'] ?? null;
    }

    try {
        if ($id && $discount) {
            $db->execute(
                "UPDATE discounts SET code = ?, name = ?, description = ?, type = ?, value = ?, 
                 min_purchase_amount = ?, max_discount_amount = ?, usage_limit = ?, 
                 usage_limit_per_customer = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?",
                [$code, $name, $description, $type, $value, $minPurchase, $maxDiscount, 
                 $usageLimit, $usageLimitPerCustomer, $startDate, $endDate, $status, $id]
            );
            $success = 'Discount updated successfully!';
        } else {
            $db->insert(
                "INSERT INTO discounts (code, name, description, type, value, min_purchase_amount, 
                 max_discount_amount, usage_limit, usage_limit_per_customer, start_date, end_date, status, store_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$code, $name, $description, $type, $value, $minPurchase, $maxDiscount, 
                 $usageLimit, $usageLimitPerCustomer, $startDate, $endDate, $status, $storeId]
            );
            $success = 'Discount created successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$storeId = $_SESSION['store_id'] ?? null;
$discounts = $db->fetchAll("SELECT * FROM discounts WHERE store_id = ? ORDER BY created_at DESC", [$storeId]);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Discount Management</h1>
    <p class="text-gray-600 text-sm md:text-base">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > Discounts
    </p>
</div>


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
                        <select name="type" id="discountTypeSelect" required class="admin-form-select">
                            <option value="percentage" <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                            <option value="fixed" <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>
                    </div>
                    
                    <div class="admin-form-group">
                        <label class="admin-form-label">Value *</label>
                        <div class="relative">
                            <span id="valuePrefix" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'percentage') ? 'hidden' : ''; ?>">₹</span>
                            <input type="number" 
                                   name="value" 
                                   id="discountValueInput"
                                   step="0.01"
                                   required
                                   value="<?php echo $discount['value'] ?? 0; ?>"
                                   class="admin-form-input <?php echo (!empty($discount) && ($discount['type'] ?? '') === 'percentage') ? 'pr-8' : 'pl-8'; ?>">
                            <span id="valueSuffix" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 <?php echo (empty($discount) || ($discount['type'] ?? '') === 'fixed') ? 'hidden' : ''; ?>">%</span>
                        </div>
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
                        <label class="admin-form-label">Usage Limit Per Customer</label>
                        <input type="number" 
                               name="usage_limit_per_customer" 
                               placeholder="e.g. 1"
                               value="<?php echo $discount['usage_limit_per_customer'] ?? ''; ?>"
                               class="admin-form-input">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
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
                
                <div class="flex items-center gap-3">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <?php echo $id ? 'Update' : 'Create'; ?> Discount
                    </button>
                    
                    <?php if ($id): ?>
                    <button type="button" 
                            onclick="confirmDelete(<?php echo $id; ?>)" 
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition font-semibold">
                        Delete
                    </button>
                    <?php endif; ?>
                </div>
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
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-blue-600"><?php echo htmlspecialchars($disc['code']); ?></p>
                            <button type="button" 
                                    class="text-gray-400 hover:text-blue-500 transition" 
                                    onclick="copyToClipboard('<?php echo htmlspecialchars($disc['code']); ?>', this)" 
                                    title="Copy Code">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($disc['name']); ?></p>
                        <div class="flex gap-3 mt-1">
                            <p class="text-[11px] font-medium px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                                Used: <?php echo $disc['used_count'] ?? 0; ?> / <?php echo $disc['usage_limit'] ?: '∞'; ?>
                            </p>
                            <?php if ($disc['usage_limit_per_customer']): ?>
                            <p class="text-[11px] font-medium px-2 py-0.5 rounded bg-blue-50 text-blue-600" title="Limit per customer">
                                Per Cust: <?php echo $disc['usage_limit_per_customer']; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="?id=<?php echo $disc['id']; ?>" class="text-blue-500 hover:text-blue-700 p-2 rounded-full hover:bg-blue-50" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" 
                                onclick="confirmDelete(<?php echo $disc['id']; ?>)" 
                                class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50" 
                                title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<script>
function copyToClipboard(text, button) {
    // Navigator clipboard API
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showCopiedFeedback(button);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            // Fallback for older browsers
            fallbackCopyTextToClipboard(text, button);
        });
    } else {
        fallbackCopyTextToClipboard(text, button);
    }
}

function fallbackCopyTextToClipboard(text, button) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    
    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showCopiedFeedback(button);
        }
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
    }

    document.body.removeChild(textArea);
}

function showCopiedFeedback(button) {
    const icon = button.querySelector('i');
    
    // Change icon to check
    icon.className = 'fas fa-check text-green-500';
    
    // Revert back after 2 seconds
    setTimeout(() => {
        icon.className = 'far fa-copy';
    }, 2000);
}

function confirmDelete(id) {
    window.location.href = '?delete_id=' + id;
}

// Discount Type Toggle Logic
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('discountTypeSelect');
    const valueInput = document.getElementById('discountValueInput');
    const prefix = document.getElementById('valuePrefix');
    const suffix = document.getElementById('valueSuffix');

    if (typeSelect && valueInput && prefix && suffix) {
        typeSelect.addEventListener('change', function() {
            if (this.value === 'percentage') {
                prefix.classList.add('hidden');
                suffix.classList.remove('hidden');
                valueInput.classList.remove('pl-8');
                valueInput.classList.add('pr-8');
            } else {
                prefix.classList.remove('hidden');
                suffix.classList.add('hidden');
                valueInput.classList.add('pl-8');
                valueInput.classList.remove('pr-8');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

