<?php
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/admin-header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Settings saved successfully!';
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Settings</h1>
    <p class="text-gray-600">Dashboard > Settings</p>
</div>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- General Settings -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">General Settings</h2>
        
        <form method="POST" action="">
            <div class="admin-form-group">
                <label class="admin-form-label">Site Name</label>
                <input type="text" 
                       value="Milano E-commerce" 
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Site Email</label>
                <input type="email" 
                       value="info@milano.com" 
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Timezone</label>
                <select class="admin-form-select">
                    <option>UTC</option>
                    <option>America/New_York</option>
                    <option>Europe/London</option>
                    <option>Asia/Dubai</option>
                </select>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary">
                Save Settings
            </button>
        </form>
    </div>
    
    <!-- Notification Settings -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Notification Settings</h2>
        
        <form method="POST" action="">
            <div class="admin-form-group">
                <label class="flex items-center">
                    <input type="checkbox" checked class="mr-2">
                    <span>Email notifications for new orders</span>
                </label>
            </div>
            
            <div class="admin-form-group">
                <label class="flex items-center">
                    <input type="checkbox" checked class="mr-2">
                    <span>Email notifications for low stock</span>
                </label>
            </div>
            
            <div class="admin-form-group">
                <label class="flex items-center">
                    <input type="checkbox" class="mr-2">
                    <span>Email notifications for customer inquiries</span>
                </label>
            </div>
            
            <div class="admin-form-group">
                <label class="flex items-center">
                    <input type="checkbox" checked class="mr-2">
                    <span>Push notifications</span>
                </label>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary">
                Save Settings
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


