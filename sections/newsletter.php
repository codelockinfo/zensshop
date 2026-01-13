<?php
if (!isset($baseUrl)) {
    require_once __DIR__ . '/../includes/functions.php';
    $baseUrl = getBaseUrl();
}
?>
<section class="py-16 md:py-24 relative overflow-hidden" style="background: linear-gradient(to right, #8B7355 0%, #D4C5B9 100%);">
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=1200'); filter: blur(8px); opacity: 0.6;"></div>
    
    <div class="container mx-auto px-4 relative z-10">
        <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-2xl p-8 md:p-12">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4 text-black">Join our family</h2>
                <p class="text-gray-700 text-sm md:text-md">Promotions, new products and sales. Directly to your inbox.</p>
            </div>
            
            <form id="globalNewsletterForm" class="space-y-4">
                <div class="flex flex-col md:flex-row gap-3">
                    <input type="email" 
                        name="email" 
                        placeholder="Your email address..." 
                        required
                        class="flex-1 px-4 py-3 border"
                        style="border-radius: 50px;">
                    
                    <button type="submit" 
                            class="bg-black text-white px-8 py-3 hover:bg-gray-800 transition font-medium whitespace-nowrap"
                            style="border-radius: 50px;">
                        Subscribe
                    </button>
                </div>
                
                <!-- Message Container -->
                <div id="globalNewsletterMessage" class="hidden text-center text-sm"></div>
                
                <p class="text-xs md:text-sm text-gray-600 text-center mt-4">
                    Your personal data will be used to support your experience throughout this website, and for other purposes described in our <a href="<?php echo url('privacy.php'); ?>" class="underline hover:text-gray-900">Privacy Policy</a>.
                </p>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('globalNewsletterForm');
    if(form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('Global Newsletter Form Submitted');
            
            const btn = this.querySelector('button[type="submit"]');
            const origText = btn.innerText;
            btn.innerText = 'Subscribing...';
            btn.disabled = true;

            const email = this.querySelector('input[name="email"]').value;
            
            try {
                // Determine base URL dynamically if PHP var is empty, usually passed from server
                let baseUrl = '<?php echo isset($baseUrl) ? $baseUrl : ""; ?>';
                if(!baseUrl) {
                    baseUrl = window.location.pathname.substring(0, window.location.pathname.indexOf('/zensshop') + 9);
                    if(baseUrl.indexOf('zensshop') === -1) baseUrl = '/zensshop'; 
                }

                const response = await fetch(baseUrl + '/api/subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    this.reset();
                } else {
                    alert(data.message || 'Something went wrong. Please try again.');
                }
            } catch (error) {
                console.error(error);
                alert('An error occurred. Please try again.');
            } finally {
                btn.innerText = origText;
                btn.disabled = false;
            }
        });
    }
});
</script>
