<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Email.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$action = $data['action'] ?? '';
$awb = trim($data['awb'] ?? '');

if (empty($action) || empty($awb)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$db = Database::getInstance();

// Look up order by tracking_number OR order_number
$order = $db->fetchOne("SELECT * FROM orders WHERE tracking_number = ? OR order_number = ? LIMIT 1", [$awb, $awb]);
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'No order found with this tracking number.']);
    exit;
}

$actualAwb = $order['tracking_number'] ?: $order['order_number'];

if ($action === 'send_otp') {
    $phone = trim($data['phone'] ?? '');
    $enteredClean = substr(preg_replace('/[^0-9]/', '', $phone), -10);

    if (strlen($enteredClean) < 10) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.']);
        exit;
    }

    // Verify phone against order customer_phone or shipping_address phone
    $orderCleanPhone = substr(preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? ''), -10);
    $shippingPhone = '';
    if (!empty($order['shipping_address'])) {
        $addr = json_decode($order['shipping_address'], true);
        if (!empty($addr['phone'])) {
            $shippingPhone = substr(preg_replace('/[^0-9]/', '', $addr['phone']), -10);
        }
    }

    $isMatch = ($enteredClean === $orderCleanPhone) || ($enteredClean === $shippingPhone);

    if (!$isMatch) {
        echo json_encode([
            'success' => false, 
            'message' => 'The mobile number does not match the details registered on this order.'
        ]);
        exit;
    }

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $_SESSION['tracking_otp_' . $actualAwb] = [
        'otp' => $otp,
        'expires' => time() + 600, // 10 minutes
        'phone' => $enteredClean
    ];

    // Send email to order customer_email
    $recipientEmail = trim($order['customer_email'] ?? '');
    if (empty($recipientEmail)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No email address found for this order to send verification code.'
        ]);
        exit;
    }

    try {
        $emailService = new Email($order['store_id'] ?? null);
        $sent = $emailService->sendTrackingOTP($recipientEmail, $otp, $actualAwb, $order['order_number']);
        
        if (!$sent) {
            // Still log if SMTP has issues
            error_log("Tracking OTP email failed to send to $recipientEmail");
        }
    } catch (Exception $e) {
        error_log("Tracking OTP exception: " . $e->getMessage());
    }

    // Mask the email address for privacy (e.g. d****k@gmail.com)
    $emailParts = explode('@', $recipientEmail);
    $userPart = $emailParts[0];
    $domainPart = $emailParts[1] ?? '';
    if (strlen($userPart) <= 2) {
        $maskedUser = substr($userPart, 0, 1) . '***';
    } else {
        $maskedUser = substr($userPart, 0, 1) . str_repeat('*', max(3, strlen($userPart) - 2)) . substr($userPart, -1);
    }
    $maskedEmail = $maskedUser . '@' . $domainPart;

    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent successfully.',
        'masked_email' => $maskedEmail
    ]);
    exit;

} elseif ($action === 'verify_otp') {
    $enteredOtp = trim($data['otp'] ?? '');

    if (empty($enteredOtp)) {
        echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit OTP.']);
        exit;
    }

    $otpData = $_SESSION['tracking_otp_' . $actualAwb] ?? null;

    if (!$otpData) {
        echo json_encode(['success' => false, 'message' => 'OTP expired or not requested. Please request a new code.']);
        exit;
    }

    if (time() > $otpData['expires']) {
        unset($_SESSION['tracking_otp_' . $actualAwb]);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new code.']);
        exit;
    }

    if ($otpData['otp'] !== $enteredOtp) {
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP code. Please check your email and try again.']);
        exit;
    }

    // Successful verification!
    $_SESSION['verified_tracking_' . $actualAwb] = true;
    unset($_SESSION['tracking_otp_' . $actualAwb]);

    echo json_encode([
        'success' => true,
        'message' => 'Verification successful!'
    ]);
    exit;

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
