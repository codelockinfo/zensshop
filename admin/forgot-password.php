<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$error = '';
$success = '';

// Handle OTP request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'] ?? '';
    
    try {
        $auth->generateOTP($email);
        $success = 'OTP has been sent to your email address.';
        header('Location: ' . $baseUrl . '/admin/verify-otp.php?email=' . urlencode($email));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Milano</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-500 rounded-lg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">R</div>
            <h1 class="text-2xl font-bold">Forgot Password</h1>
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
        <div class="text-center">
            <a href="<?php echo $baseUrl; ?>/admin/verify-otp.php" class="text-blue-500 hover:text-blue-700">
                Go to OTP Verification →
            </a>
        </div>
        <?php else: ?>
        
        <form method="POST" action="">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                    Email Address
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition font-semibold">
                Send OTP
            </button>
        </form>
        
        <?php endif; ?>
        
        <div class="mt-6 text-center">
            <a href="<?php echo $baseUrl; ?>/admin/index.php" class="text-blue-500 hover:text-blue-700">
                ← Back to Login
            </a>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
        
        if (form) {
            form.addEventListener('submit', function() {
                if (form.checkValidity()) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending OTP...';
                }
            });
        }

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


