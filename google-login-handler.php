<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';

// This file handles the POST request from Google Identity Services
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $idToken = $_POST['credential'];
    
    // In a real production app, you MUST verify the ID token using Google's library or an API call.
    // For this demonstration/setup, we will parse the payload (which is base64 encoded JSON in the middle part).
    // jwt = [header].[payload].[signature]
    $parts = explode('.', $idToken);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode($parts[1]), true);
        
        if ($payload && isset($payload['sub'])) {
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Google User';
            $avatar = $payload['picture'] ?? null;
            
            $auth = new CustomerAuth();
            try {
                $customer = $auth->loginWithGoogle($googleId, $email, $name, $avatar);
                
                // Sync cart after login
                require_once __DIR__ . '/classes/Cart.php';
                $cart = new Cart();
                $cart->syncCartAfterLogin($customer['customer_id']);
                
                // Sync wishlist after login
                require_once __DIR__ . '/classes/Wishlist.php';
                $wishlist = new Wishlist();
                $wishlist->syncWishlistAfterLogin($customer['customer_id']);
                
                $redirect = $_SESSION['login_redirect'] ?? '';
                $target = ($redirect === 'checkout') ? url('checkout.php') : url('account.php');
                unset($_SESSION['login_redirect']);
                
                header('Location: ' . $target);
                exit;
            } catch (Exception $e) {
                // Handle error
                header('Location: ' . url('login.php?error=' . urlencode($e->getMessage())));
                exit;
            }
        }
    }
}

header('Location: ' . url('login.php'));
exit;
