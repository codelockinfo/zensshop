<?php
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/includes/functions.php';

$customerAuth = new CustomerAuth();
$customer = $customerAuth->getCurrentCustomer();

$pageTitle = 'Customer Support';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Customer Support</h1>
            <p class="text-gray-600">Have a question? We're here to help!</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-envelope text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Email Us</h3>
                        <p class="text-sm text-gray-600">We'll respond within 24 hours</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Response Time</h3>
                        <p class="text-sm text-gray-600">Usually within 2-4 hours</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold mb-6">Send us a message</h2>
            
            <form id="supportForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your Name *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?php echo $customer ? htmlspecialchars($customer['name']) : ''; ?>"
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Your Email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo $customer ? htmlspecialchars($customer['email']) : ''; ?>"
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                    <input type="text" 
                           id="subject" 
                           name="subject" 
                           required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="How can we help you?">
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                    <textarea id="message" 
                              name="message" 
                              rows="6" 
                              required 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Please describe your issue or question in detail..."></textarea>
                </div>

                <div id="supportMessage" class="hidden"></div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-paper-plane mr-2"></i>Send Message
                </button>
            </form>
        </div>

        <!-- FAQ Section -->
        <div class="mt-12">
            <h2 class="text-2xl font-bold mb-6 text-center">Frequently Asked Questions</h2>
            <div class="space-y-4">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">How long does shipping take?</h3>
                    <p class="text-gray-600">Standard shipping typically takes 3-5 business days. Express shipping is available for 1-2 day delivery.</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">What is your return policy?</h3>
                    <p class="text-gray-600">We offer a 30-day return policy for most items. Products must be unused and in original packaging.</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">How can I track my order?</h3>
                    <p class="text-gray-600">Once your order ships, you'll receive a tracking number via email. You can also check your order status in your account.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('supportForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const origText = btn.innerHTML;
    const messageDiv = document.getElementById('supportMessage');
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    btn.disabled = true;
    messageDiv.classList.add('hidden');
    
    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        subject: document.getElementById('subject').value,
        message: document.getElementById('message').value
    };
    
    try {
        const response = await fetch('<?php echo $baseUrl; ?>/api/support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.reset();
            messageDiv.textContent = data.message;
            messageDiv.className = 'p-4 bg-green-50 text-green-700 rounded-lg border border-green-200';
            messageDiv.classList.remove('hidden');
            
            // Scroll to message
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            messageDiv.textContent = data.message || 'Something went wrong. Please try again.';
            messageDiv.className = 'p-4 bg-red-50 text-red-700 rounded-lg border border-red-200';
            messageDiv.classList.remove('hidden');
        }
    } catch (error) {
        console.error(error);
        messageDiv.textContent = 'An error occurred. Please try again.';
        messageDiv.className = 'p-4 bg-red-50 text-red-700 rounded-lg border border-red-200';
        messageDiv.classList.remove('hidden');
    } finally {
        btn.innerHTML = origText;
        btn.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
