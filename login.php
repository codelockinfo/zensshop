<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';

$auth = new CustomerAuth();
$error = null;
$redirect = $_GET['redirect'] ?? $_SESSION['login_redirect'] ?? '';

if ($redirect) {
    $_SESSION['login_redirect'] = $redirect;
}

if ($auth->isLoggedIn()) {
    $target = ($redirect === 'checkout') ? url('checkout') : url('account');
    unset($_SESSION['login_redirect']);
    header('Location: ' . $target);
    exit;
}

$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || 
          (isset($_POST['ajax']) && $_POST['ajax'] == '1') || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!$isAjax && !$auth->isLoggedIn() && !defined('IS_ACCOUNT_PAGE')) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $redirectUrl = url('account') . ($queryString ? '?' . $queryString : '');
    header('Location: ' . $redirectUrl);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $customer = $auth->login($email, $password);
        
        if ($isAjax) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => ($redirect === 'checkout') ? url('checkout') : url('account')]);
            exit;
        }


        $target = ($redirect === 'checkout') ? url('checkout') : url('account');
        unset($_SESSION['login_redirect']);
        header('Location: ' . $target);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
        if ($isAjax) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }

    }
}

$pageTitle = 'Login';
?>


<style>
.login-3d-card {
    perspective: 1000px;
    transform-style: preserve-3d;
}
.login-inner-card {
    transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
/* Removed hover tilt to prevent layout shifts during interaction */
.floating-element {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    z-index: -1;
    opacity: 0.4;
}
</style>

<div class="relative overflow-hidden w-full">

    <!-- Background Decor -->
    <div class="floating-element bg-purple-400 w-96 h-96 -top-20 -left-20 animate-pulse"></div>
    <div class="floating-element bg-blue-400 w-96 h-96 -bottom-20 -right-20 animate-pulse" style="animation-delay: 1s;"></div>

    <div class="max-w-md w-full login-3d-card">
        <div class="bg-white rounded login-inner-card p-1">
            <div class="bg-white rounded-[22px] p-8 md:p-10">
                <div class="text-center mb-5">
                    <h1 class="text-3xl font-bold text-gray-900">Welcome Back</h1>
                    <p class="text-gray-500 mt-2">Sign in to your account</p>
                </div>

                <?php if (!empty($_GET['message'])): ?>
                    <div class="bg-green-50 text-green-600 p-4 rounded-xl mb-6 text-sm flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($_GET['message']); ?>
                    </div>
                <?php endif; ?>

                <div id="loginAlert" style="display:none; transition: opacity 0.4s ease;"></div>

                <!-- Google Login Library -->
                <script src="https://accounts.google.com/gsi/client" async defer></script>
                
                <!-- Google Login Configuration -->
                <div id="g_id_onload"
                     data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-login_uri="<?php echo SITE_URL; ?>/google-login-handler.php"
                     data-auto_prompt="false">
                </div>

                <!-- Custom styled wrapper for Google Button -->
                <div class="mb-4 flex justify-center">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-width="280"> <!-- Fixed width to ensure full clickability in all browsers -->
                    </div>
                </div>

                <div class="relative flex items-center gap-4 mb-4">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">Or with email</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <form method="POST" class="space-y-6" id="loginForm">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="email" name="email" required 
                                   class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"
                                   placeholder="alexjohn@gmail.com">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Password</label>
                            <a href="?forgot-password" class="text-xs font-bold text-blue-600 hover:underline">Forgot?</a>
                        </div>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="password" id="password" required 
                                   class="w-full pl-12 pr-12 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="loginBtn"
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition translate-z-10 shadow-lg shadow-gray-200 disabled:opacity-70 disabled:cursor-not-allowed">
                        Sign In
                    </button>
                </form>

                <script>
                (function(){
                    var alertTimer = null;
                    function showLoginAlert(msg, isError) {
                        var el = document.getElementById('loginAlert');
                        el.innerHTML = '<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;border:1px solid ' +
                            (isError ? '#fecaca;background:#fef2f2;color:#b91c1c' : '#bbf7d0;background:#f0fdf4;color:#15803d') +
                            '"><i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i><span>' + msg + '</span></div>';
                        el.style.opacity = '1';
                        el.style.display = 'block';
                        el.style.marginBottom = '20px';
                        if (alertTimer) clearTimeout(alertTimer);
                        alertTimer = setTimeout(function() {
                            el.style.opacity = '0';
                            setTimeout(function(){ el.style.display = 'none'; }, 420);
                        }, 4000);
                    }

                    document.getElementById('loginForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        const email = formData.get('email').toLowerCase().trim();
                        
                        // Basic email validation
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            showLoginAlert('Please enter a valid email address.', true);
                            return;
                        }

                        // Catch common typos like gm3ail.com (from user screenshot)
                        const commonTypos = ['gm3ail', 'gmaill', 'gmal', 'yahool', 'yaho', 'hotmal', 'outlok'];
                        const domainPart = email.split('@')[1] ? email.split('@')[1].split('.')[0] : '';
                        
                        if (commonTypos.includes(domainPart)) {
                            showLoginAlert('It looks like there might be a typo in your email domain (e.g., ' + domainPart + '). Please check it again.', true);
                            return;
                        }

                        // Check for numbers in what look like common providers
                        if (domainPart.match(/^[a-z]+[0-9]+[a-z]*$/)) {
                            const commonBases = ['gmail', 'yahoo', 'hotmail', 'outlook', 'icloud', 'protonmail'];
                            const pureBase = domainPart.replace(/[0-9]/g, '');
                            if (commonBases.includes(pureBase)) {
                                showLoginAlert('Suspicious character detected in email domain: "' + domainPart + '". Please ensure your email is correct.', true);
                                return;
                            }
                        }

                        const btn = document.getElementById('loginBtn');
                        const orig = btn.innerHTML;
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing In...';

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showLoginAlert('Login successful! Redirecting...', false);
                                setTimeout(() => { window.location.href = data.redirect; }, 1000);
                            } else {
                                showLoginAlert(data.message || 'Login failed', true);
                                btn.disabled = false;
                                btn.innerHTML = orig;
                            }
                        })
                        .catch(() => {
                            showLoginAlert('Network error, please try again.', true);
                            btn.disabled = false;
                            btn.innerHTML = orig;
                        });
                    });
                })();
                </script>

                <div class="mt-4 text-center">
                    <p class="text-gray-500 text-sm">
                        Don't have an account? 
                        <a href="?register" class="text-black font-bold hover:underline">Sign Up</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>


