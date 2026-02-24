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

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $customer_id = $auth->register($name, $email, $password);
        $auth->login($email, $password);
        
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
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

$pageTitle = 'Register';
?>


<div class="relative overflow-hidden w-full">

    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all hover:scale-[1.01]">
            <div class="p-8">
                <div class="text-center mb-5">
                    <h1 class="text-3xl font-bold text-gray-900">Create Account</h1>
                    <p class="text-gray-500 mt-2">Join us for a better experience</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 text-sm">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

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
                <div class="mb-6 flex justify-center">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signup_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-width="280">
                    </div>
                </div>

                <div class="relative flex items-center gap-4 mb-6">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">Or with email</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <form method="POST" class="space-y-6" id="registerForm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="name" required 
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                               placeholder="Alex John">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                               placeholder="alexjohn@gmail.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="passwordInput" required 
                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-black focus:border-transparent outline-none transition pr-10"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('passwordInput', this)" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none">
                                <i class="far fa-eye"></i>
                            </button>

                        </div>
                    </div>

                    <button type="submit" id="registerBtn"
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition transform active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
                        Create Account
                    </button>
                </form>

                <script>
                document.getElementById('registerForm').addEventListener('submit', function() {
                    const btn = document.getElementById('registerBtn');
                    const originalText = btn.innerText;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating Account...';
                });
                </script>

                <div class="mt-4 text-center">
                    <p class="text-gray-600 text-sm">
                        Already have an account? 
                        <a href="?login" class="text-black font-bold hover:underline">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>


