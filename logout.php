<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CustomerAuth.php';

$auth = new CustomerAuth();
$auth->logout();

// Clear cookies to prevent data leakage between users
if (isset($_COOKIE['wishlist_items'])) {
    setcookie('wishlist_items', '', time() - 3600, '/');
}
if (isset($_COOKIE['cart_items'])) {
    setcookie('cart_items', '', time() - 3600, '/');
}

header('Location: ' . url('login.php'));
exit;
