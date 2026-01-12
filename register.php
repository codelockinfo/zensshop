<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';

$auth = new CustomerAuth();
$redirect = $_SESSION['login_redirect'] ?? '';

if ($auth->isLoggedIn()) {
    $target = ($redirect === 'checkout') ? url('checkout.php') : url('account.php');
    unset($_SESSION['login_redirect']);
    header('Location: ' . $target);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $customer_id = $auth->register($name, $email, $password);
        $auth->login($email, $password);
        
        // Sync cart after login
        require_once __DIR__ . '/classes/Cart.php';
        $cart = new Cart();
        $cart->syncCartAfterLogin($customer_id);
        
        // Sync wishlist after login
        require_once __DIR__ . '/classes/Wishlist.php';
        $wishlist = new Wishlist();
        $wishlist->syncWishlistAfterLogin($customer_id);
        
        $target = ($redirect === 'checkout') ? url('checkout.php') : url('account.php');
        unset($_SESSION['login_redirect']);
        header('Location: ' . $target);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen pt-32 pb-20 bg-gray-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all hover:scale-[1.01]">
            <div class="p-8">
                <div class="text-center mb-10">
                    <h1 class="text-3xl font-bold text-gray-900">Create Account</h1>
                    <p class="text-gray-500 mt-2">Join us for a better experience</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 text-sm">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
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
                        <input type="password" name="password" required 
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                               placeholder="••••••••">
                    </div>

                    <button type="submit" 
                            class="w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-900 transition transform active:scale-95">
                        Create Account
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-gray-600 text-sm">
                        Already have an account? 
                        <a href="<?php echo url('login.php'); ?>" class="text-black font-bold hover:underline">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
