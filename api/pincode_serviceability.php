<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Delhivery.php';

$pincode = $_GET['pincode'] ?? '';

if (empty($pincode)) {
    echo json_encode(['success' => false, 'message' => 'Pincode is required']);
    exit;
}

$delhivery = new Delhivery();
$result = $delhivery->checkPincode($pincode);

echo json_encode($result);
