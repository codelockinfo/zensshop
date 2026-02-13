<?php
// Dynamic Image Configuration
// You can change this URL to your own image
$devPopupImage = getBaseUrl() . '/pop-up/pop-up.png'; 
?>

<!-- Development Popup Modal -->
<div id="dev-popup" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-70 hidden transition-opacity duration-300 opacity-0 backdrop-blur-sm">
    <div class="bg-white rounded-lg shadow-2xl overflow-hidden max-w-4xl w-full mx-4 flex flex-col md:flex-row transform scale-95 transition-transform duration-300" id="dev-popup-content">
        
        <!-- Image Side -->
        <div class="hidden md:flex w-1/2 bg-white relative items-center justify-center p-4" style="aspect-ratio: 3/4;">
             <img src="<?php echo $devPopupImage; ?>" alt="Under Construction" class="w-full h-full object-cover" style="border-radius: 10px;">
        </div>

        <!-- Content Side -->
        <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center relative">
             <button onclick="closeDevPopup()" class="absolute top-4 right-4 text-gray-400 hover:text-black transition-colors">
                 <i class="fas fa-times text-xl"></i>
             </button>
             
             <div class="mb-6">
                 <span class="inline-block px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold tracking-wide uppercase mb-3">
                     Coming Soon
                 </span>
                 <h2 class="text-3xl font-bold mb-2 text-gray-900 leading-tight">This site is under construction</h2>
                 <p class="text-gray-600 text-lg">
                     We're building something amazing. Subscribe to get notified as soon as we launch!
                 </p>
             </div>
             
             <form id="dev-popup-form" onsubmit="handleDevSubscribe(event)" class="mt-2">
                 <div class="relative">
                     <label class="block text-sm font-medium text-gray-700 mb-1 sr-only">Email Address</label>
                     <div class="flex shadow-sm">
                         <input type="email" name="email" required 
                                class="flex-1 w-full border border-gray-300 rounded-l-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                                placeholder="Enter your email address">
                         <button type="submit" class="bg-black text-white px-6 py-3 rounded-r-lg font-bold hover:bg-gray-800 transition-all transform hover:translate-x-1">
                             Notify Me
                         </button>
                     </div>
                 </div>
                 <p class="text-xs text-gray-400 mt-3 flex items-center">
                     <i class="fas fa-lock mr-1"></i> We respect your privacy. No spam.
                 </p>
             </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const closedKey = 'dev_popup_closed';
    const subscribedKey = 'dev_popup_subscribed';
    
    // Check if subscribed
    if (getCookie(subscribedKey)) {
        return;
    }
    
    // Check if closed recently
    if (getCookie(closedKey)) {
        return;
    }
    
    // Show after 10 seconds
    setTimeout(showDevPopup, 10000);
});

function showDevPopup() {
    const popup = document.getElementById('dev-popup');
    const content = document.getElementById('dev-popup-content');
    if(popup) {
        popup.classList.remove('hidden');
        // Animate in
        requestAnimationFrame(() => {
            popup.classList.remove('opacity-0');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        });
    }
}

function closeDevPopup() {
    const popup = document.getElementById('dev-popup');
    const content = document.getElementById('dev-popup-content');
    if(popup) {
        popup.classList.add('opacity-0');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        
        setTimeout(() => {
            popup.classList.add('hidden');
        }, 300);
        
        // Save closed cookie (7 days)
        setCookie('dev_popup_closed', 'true', 7);
    }
}

// Cookie Helper Functions
function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for(let i=0;i < ca.length;i++) {
        let c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

async function handleDevSubscribe(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button');
    const input = form.querySelector('input');
    const originalText = btn.innerHTML;
    
    // Lock button width to prevent resizing
    btn.style.width = btn.offsetWidth + 'px';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch('<?php echo getBaseUrl(); ?>/api/newsletter.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: input.value,
                type: 'development' // Pass type
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mark as subscribed (cookie for 365 days)
            setCookie('dev_popup_subscribed', 'true', 365);
            
            // Replaced content with success message
            form.innerHTML = `
                <div class="text-center py-4 px-2 bg-green-50 rounded-lg border border-green-100 animate-fade-in">
                    <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">You're on the list!</h3>
                    <p class="text-sm text-gray-600">We'll notify you when we launch.</p>
                </div>
            `;
            setTimeout(() => {
                const popup = document.getElementById('dev-popup');
                const content = document.getElementById('dev-popup-content');
                if(popup) {
                     popup.classList.add('opacity-0');
                     content.classList.remove('scale-100');
                     content.classList.add('scale-95');
                     setTimeout(() => { popup.classList.add('hidden'); }, 300);
                }
            }, 3000);
        } else {
             if (data.message.includes('already subscribed')) {
                 alert('You are already on our notification list!');
                 // Treat as subscribed to avoid annoying them?
                 // Maybe yes. User said "if user subscribe then do not show again".
                 // Assuming existing subscription counts.
                 setCookie('dev_popup_subscribed', 'true', 365);
             } else {
                 alert(data.message || 'Something went wrong');
             }
             btn.disabled = false;
             btn.innerHTML = originalText;
             btn.style.width = ''; // Reset width
        }
    } catch (err) {
        console.error(err);
        alert('Error submitting form. Please try again.');
        alert('Error submitting form. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.style.width = '';
    }
}
</script>
