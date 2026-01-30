<?php
// admin/logout.php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->logout();

// Redirect to login page
header('Location: ' . getBaseUrl() . '/admin/index.php');
exit;
