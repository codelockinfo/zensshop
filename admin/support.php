<?php
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Support';
require_once __DIR__ . '/../includes/admin-header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Support ticket submitted successfully!';
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Support</h1>
    <p class="text-gray-600">Dashboard > Support</p>
</div>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Contact Support -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Contact Support</h2>
        
        <form method="POST" action="">
            <div class="admin-form-group">
                <label class="admin-form-label">Subject</label>
                <input type="text" 
                       name="subject"
                       required
                       placeholder="What can we help you with?"
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Category</label>
                <select name="category" required class="admin-form-select">
                    <option value="">Select category</option>
                    <option value="technical">Technical Issue</option>
                    <option value="billing">Billing Question</option>
                    <option value="feature">Feature Request</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Message</label>
                <textarea name="message" 
                          required
                          rows="6"
                          placeholder="Describe your issue or question..."
                          class="admin-form-input admin-form-textarea"></textarea>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fas fa-paper-plane mr-2"></i> Submit Ticket
            </button>
        </form>
    </div>
    
    <!-- Help & Resources -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Help & Resources</h2>
        
        <div class="space-y-4">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="font-semibold mb-2">Documentation</h3>
                <p class="text-sm text-gray-600 mb-3">Browse our comprehensive documentation to find answers to common questions.</p>
                <a href="#" class="text-blue-500 hover:text-blue-700 text-sm">
                    View Documentation <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <div class="border-b border-gray-200 pb-4">
                <h3 class="font-semibold mb-2">Video Tutorials</h3>
                <p class="text-sm text-gray-600 mb-3">Watch step-by-step video guides to learn how to use the admin panel.</p>
                <a href="#" class="text-blue-500 hover:text-blue-700 text-sm">
                    Watch Videos <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <div class="pb-4">
                <h3 class="font-semibold mb-2">Quick Links</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="text-blue-500 hover:text-blue-700">Getting Started Guide</a></li>
                    <li><a href="#" class="text-blue-500 hover:text-blue-700">FAQ</a></li>
                    <li><a href="#" class="text-blue-500 hover:text-blue-700">System Status</a></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <div class="flex items-start space-x-3">
                <i class="fas fa-headset text-blue-500 text-2xl"></i>
                <div>
                    <h3 class="font-semibold mb-1">Need Immediate Help?</h3>
                    <p class="text-sm text-gray-600 mb-2">Our support team is available 24/7</p>
                    <p class="text-sm font-semibold">codelock2021@gmail.com</p>
                    <p class="text-sm font-semibold">+91 9876543210</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


