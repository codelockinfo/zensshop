<section class="py-16 md:py-24 bg-gray-100 relative overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=1200');"></div>
    
    <div class="container mx-auto px-4 relative z-10">
        <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl p-8 md:p-12">
            <div class="text-center mb-8">
                <h2 class="text-3xl md:text-4xl font-heading font-bold mb-4">Join our family</h2>
                <p class="text-gray-600 text-lg">Promotions, new products and sales. Directly to your inbox.</p>
            </div>
            
            <form id="newsletterForm" class="space-y-4">
                <div>
                    <input type="email" 
                           name="email" 
                           placeholder="Your email address" 
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <button type="submit" 
                        class="w-full bg-black text-white py-3 rounded-lg hover:bg-gray-800 transition font-semibold">
                    Subscribe
                </button>
                
                <p class="text-xs text-gray-500 text-center">
                    By subscribing, you agree to our <a href="/oecom/privacy.php" class="underline">Privacy Policy</a> and consent to receive updates from our company.
                </p>
            </form>
        </div>
    </div>
</section>

<script>
document.getElementById('newsletterForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = this.querySelector('input[name="email"]').value;
    
    try {
        const response = await fetch('/oecom/api/newsletter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Thank you for subscribing!');
            this.reset();
        } else {
            alert(data.message || 'Something went wrong. Please try again.');
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
    }
});
</script>

