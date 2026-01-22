<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$error = '';
$success = '';
$step = 'verify'; // verify or reset

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $email = $_POST['email'] ?? '';
    $otp = $_POST['otp'] ?? '';
    
    try {
        $auth->verifyOTP($email, $otp);
        $step = 'reset';
        $success = 'OTP verified successfully. Please set your new password.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            $auth->resetPassword($email, $newPassword);
            $success = 'Password reset successfully! You can now login.';
            $step = 'success';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Milano</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-500 rounded-lg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">R</div>
            <h1 class="text-2xl font-bold">
                <?php echo $step === 'verify' ? 'Verify OTP' : ($step === 'success' ? 'Success' : 'Reset Password'); ?>
            </h1>
        </div>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($step === 'success'): ?>
        <div class="text-center">
            <a href="<?php echo $baseUrl; ?>/admin/index.php" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition">
                Go to Login
            </a>
        </div>
        <?php elseif ($step === 'reset'): ?>
        <form method="POST" action="">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">
                    New Password
                </label>
                <div class="relative">
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           required
                           minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10">
                    <button type="button" onclick="togglePassword('new_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                    Confirm Password
                </label>
                <div class="relative">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           required
                           minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10">
                    <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition font-semibold">
                Reset Password
            </button>
        </form>
        <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? $_POST['email'] ?? ''); ?>">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="otp">
                    Enter OTP
                </label>
                <input type="text" 
                       id="otp" 
                       name="otp" 
                       required
                       maxlength="6"
                       pattern="[0-9]{6}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-2xl tracking-widest">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition font-semibold">
                Verify OTP
            </button>
        </form>
        <?php endif; ?>
        
        <div class="mt-6 text-center">
            <a href="<?php echo $baseUrl; ?>/admin/forgot-password.php" class="text-blue-500 hover:text-blue-700">
                ← Back
            </a>
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

    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 3000);
        });
    });
    </script>
</body>
</html>


