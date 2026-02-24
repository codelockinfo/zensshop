<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Email.php';


$pageTitle = 'Forgot Password';
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') || 
          (isset($_POST['ajax']) && $_POST['ajax'] == '1') || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!$isAjax) {
    require_once __DIR__ . '/classes/CustomerAuth.php';
    $auth = new CustomerAuth();
    if (!$auth->isLoggedIn() && !defined('IS_ACCOUNT_PAGE')) {
        header('Location: ' . url('account'));
        exit;
    }

}




$step = $_SESSION['reset_step'] ?? 1;
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $action = $_POST['action'] ?? '';
    $isAjax = isset($_POST['ajax']);
    $response = ['success' => false, 'message' => ''];

    if ($action === 'send_otp') {
        $email = $_POST['email'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if user exists
            $user = $db->fetchOne("SELECT id, store_id FROM customers WHERE email = ?", [$email]);
            if ($user) {
                // Generate secure persistent OTP
                $otp = sprintf("%06d", mt_rand(1, 999999));
                $storeId = $user['store_id'] ?? null;
                
                // Delete old OTPs
                $db->execute("DELETE FROM customer_reset_password WHERE email = ?", [$email]);
                $db->execute("INSERT INTO customer_reset_password (email, otp, store_id) VALUES (?, ?, ?)", [$email, $otp, $storeId]);
                
                // Send Email
                $emailService = new Email();
                $emailService->sendOTP($email, $otp);
                
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_step'] = 2;
                $step = 2;
                $response['success'] = true;
            } else {
                $error = "No account found with that email address.";
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp = $_POST['otp'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';
        
        $record = $db->fetchOne(
            "SELECT * FROM customer_reset_password WHERE email = ? AND otp = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)", 
            [$email, $otp]
        );
        
        if ($record) {
             $_SESSION['reset_step'] = 3;
             $step = 3;
             $response['success'] = true;
        } else {
            $error = "Invalid OTP. Please try again.";
        }
    } elseif ($action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else if ($email) {
             // Fetch customer details for notification
             $customer = $db->fetchOne("SELECT id, name FROM customers WHERE email = ?", [$email]);
             
             $hashed = password_hash($password, PASSWORD_DEFAULT);
             $db->execute("UPDATE customers SET password = ? WHERE email = ?", [$hashed, $email]);
             
             // Send Notification to Admin
             if ($customer) {
                 require_once __DIR__ . '/classes/Notification.php';
                 $notification = new Notification();
                 $notification->notifyPasswordChange($customer['name'], $customer['id'], $email);
             }

             // Cleanup - User requested to keep data
             // $db->execute("DELETE FROM customer_reset_password WHERE email = ?", [$email]);
             unset($_SESSION['reset_step']);
             unset($_SESSION['reset_email']);
             
             $response['success'] = true;
             $response['redirect'] = url('login?message=Password reset successfully. Please login with your new password.');
        } else {
            $error = "Session expired. Please start over.";
            $step = 1;
            $response['redirect'] = url('forgot-password');
        }
    } else if ($action === 'back') {
         unset($_SESSION['reset_step']);
         unset($_SESSION['reset_email']);
         $step = 1;
         $response['success'] = true;
    }

    if ($isAjax) {
        if ($error) {
            $response['success'] = false;
            $response['message'] = $error;
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
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
.floating-element {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    z-index: -1;
    opacity: 0.4;
}
/* Loader Overlay */
#loaderOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(2px);
    z-index: 9999;
    display: none; /* Hidden by default */
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #000;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Full Screen Loader -->
<div id="loaderOverlay">
    <div class="spinner"></div>
    <div class="text-gray-800 font-medium" id="loaderText">Processing...</div>
</div>

<div class="relative overflow-hidden w-full">

    <!-- Background Decor -->
    <div class="floating-element bg-purple-400 w-96 h-96 -top-20 -left-20 animate-pulse"></div>
    <div class="floating-element bg-blue-400 w-96 h-96 -bottom-20 -right-20 animate-pulse" style="animation-delay: 1s;"></div>

    <div class="max-w-md w-full login-3d-card">
        <div class="bg-white rounded login-inner-card p-1">
            <div class="bg-white rounded-[22px] p-8 md:p-10">
                
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Forgot Password</h1>
                    <p class="text-gray-500 mt-2">
                        <?php if($step == 1): ?>Enter your email to reset password
                        <?php elseif($step == 2): ?>Enter the OTP sent to your email
                        <?php elseif($step == 3): ?>Create a new password<?php endif; ?>
                    </p>
                </div>

                <!-- JS Error Container -->
                <div id="jsErrorContainer" class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm items-center hidden">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="jsErrorMessage"></span>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                <!-- Step 1: Email Form -->
                <form method="POST" class="space-y-6" data-ajax-form>
                    <input type="hidden" name="action" value="send_otp">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="email" name="email" required 
                                   class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"
                                   placeholder="alexjohn@gmail.com">
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition translate-z-10 shadow-lg shadow-gray-200 flex items-center justify-center">
                        <span>Send OTP</span>
                    </button>
                    
                    <div class="text-center">
                         <a href="<?php echo url('login'); ?>" class="text-sm text-gray-500 hover:text-black hover:underline">Back to Login</a>
                    </div>
                </form>

                <?php elseif ($step == 2): ?>
                <!-- Step 2: OTP Form -->
                <form method="POST" class="space-y-6" data-ajax-form>
                    <input type="hidden" name="action" value="verify_otp">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Enter OTP</label>
                        <div class="relative">
                            <i class="fas fa-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="otp" required maxlength="6"
                                   class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition tracking-widest text-lg"
                                   placeholder="123456">
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-right">Sent to <?php echo htmlspecialchars($_SESSION['reset_email']); ?></p>
                    </div>

                    <button type="submit" 
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition translate-z-10 shadow-lg shadow-gray-200">
                        Verify OTP
                    </button>
                    
                    <button type="button" onclick="submitBack()"
                            class="w-full bg-white text-gray-600 border border-gray-200 py-4 rounded-xl font-bold hover:bg-gray-50 transition mt-2">
                        Change Email
                    </button>
                </form>
                <!-- Hidden form for back action -->
                <form id="backForm" method="POST" style="display:none;" data-ajax-form>
                    <input type="hidden" name="action" value="back">
                </form>

                <?php elseif ($step == 3): ?>
                <!-- Step 3: New Password Form -->
                <form method="POST" class="space-y-6" data-ajax-form>
                    <input type="hidden" name="action" value="reset_password">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">New Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="password" id="newPassword" required minlength="6"
                                   class="w-full pl-12 pr-12 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('newPassword', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Confirm Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="confirm_password" id="confirmPassword" required minlength="6"
                                   class="w-full pl-12 pr-12 py-4 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('confirmPassword', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition translate-z-10 shadow-lg shadow-gray-200">
                        Reset Password
                    </button>
                </form>
                <?php endif; ?>

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

function submitBack() {
    const form = document.getElementById('backForm');
    if(form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[data-ajax-form]');
    const loader = document.getElementById('loaderOverlay');
    const loaderText = document.getElementById('loaderText');
    const errorContainer = document.getElementById('jsErrorContainer');
    const errorMessage = document.getElementById('jsErrorMessage');

    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Reset error
            if(errorContainer) errorContainer.classList.add('hidden');
            
            // Show loader
            if(loader) {
                loader.style.display = 'flex';
                // Customize loader text based on action
                const action = this.querySelector('input[name="action"]').value;
                if(action === 'send_otp') loaderText.textContent = 'Sending OTP...';
                else if(action === 'verify_otp') loaderText.textContent = 'Verifying...';
                else if(action === 'reset_password') loaderText.textContent = 'Updating Password...';
                else loaderText.textContent = 'Processing...';
            }

            const formData = new FormData(this);
            formData.append('ajax', '1');

            try {
                const response = await fetch('forgot-password', {
                    method: 'POST',
                    body: formData
                });
                
                // Handle non-JSON responses (fatal errors etc)
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    console.error('Server response not JSON:', text);
                    throw new Error('Server returned an unexpected response.');
                }

                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    // Hide loader
                    if(loader) loader.style.display = 'none';
                    
                    // Show error
                    if(errorContainer && errorMessage) {
                        errorMessage.textContent = data.message || 'An error occurred.';
                        errorContainer.classList.remove('hidden');
                        errorContainer.style.display = 'flex';
                    } else {
                        console.log(data.message || 'An error occurred.');
                    }
                }

            } catch (error) {
                console.error(error);
                if(loader) loader.style.display = 'none';
                if(errorContainer && errorMessage) {
                    errorMessage.textContent = 'Connection error. Please try again.';
                    errorContainer.classList.remove('hidden');
                    errorContainer.style.display = 'flex';
                } else {
                    console.log('Connection error. Please try again.');
                }
            }
        });
    });
});
</script>


