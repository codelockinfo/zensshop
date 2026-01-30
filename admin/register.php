<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$error = '';
$success = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . $baseUrl . '/admin/dashboard.php');
    exit;
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            $auth->register($name, $email, $password);
            $success = 'Registration successful! Redirecting to login...';
            $shouldRedirect = true;
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
    <title>Admin Register - Milano</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-500 rounded-lg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">R</div>
            <h1 class="text-2xl font-bold">Admin Registration</h1>
            <p class="text-gray-600 mt-2">Create your admin account</p>
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
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                    Full Name
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                    Email
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                    Password
                </label>
                <div class="relative">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required
                           minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10">
                    <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-600 hover:text-gray-800 focus:outline-none">
                        <i class="fas fa-eye-slash" id="password-icon"></i>
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
                    <button type="button" onclick="togglePassword('confirm_password')" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-600 hover:text-gray-800 focus:outline-none">
                        <i class="fas fa-eye-slash" id="confirm_password-icon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition font-semibold">
                Register
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Already have an account? 
                <a href="<?php echo $baseUrl; ?>/admin/index.php" class="text-blue-500 hover:text-blue-700">
                    Login here
                </a>
            </p>
        </div>
    </div>
    <script>
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

    function togglePassword(fieldId) {
        const input = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
    </script>
    <?php if (isset($shouldRedirect) && $shouldRedirect): ?>
    <script>
    setTimeout(function() {
        window.location.href = '<?php echo $baseUrl; ?>/admin/index.php';
    }, 2000);
    </script>
    <?php endif; ?>
</body>
</html>


