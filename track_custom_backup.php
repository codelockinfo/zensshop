<?php
/**
 * Customer Order Tracking Page
 * Branded live tracking powered by Delhivery API & local database
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Delhivery.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Settings.php';

$baseUrl = getBaseUrl();
$db = Database::getInstance();
$settingsObj = new Settings();
$auth = new CustomerAuth();

$awbParam = trim($_GET['awb'] ?? $_GET['waybill'] ?? $_GET['tracking'] ?? $_GET['order'] ?? '');

$orderData = null;
$delhiveryData = null;
$error = '';
$isVerified = false;

// If AWB is provided, locate order in DB
if (!empty($awbParam)) {
    $orderObj = new Order();
    
    // Check by tracking_number first, then order_number
    $orderData = $db->fetchOne("SELECT * FROM orders WHERE tracking_number = ? LIMIT 1", [$awbParam]);
    if (!$orderData) {
        $orderData = $db->fetchOne("SELECT * FROM orders WHERE order_number = ? LIMIT 1", [$awbParam]);
    }

    if ($orderData) {
        $orderData['items'] = $orderObj->getOrderItems($orderData['order_number'], $orderData['store_id'] ?? null);
        $waybill = $orderData['tracking_number'] ?: $awbParam;

        // Check if customer is already logged in and owns this order
        $currentCustomer = $auth->getCurrentCustomer();
        if ($currentCustomer && !empty($orderData['user_id']) && (string)$currentCustomer['customer_id'] === (string)$orderData['user_id']) {
            $isVerified = true;
            $_SESSION['verified_tracking_' . $waybill] = true;
        }

        // Check if previously verified in this session
        if (!empty($_SESSION['verified_tracking_' . $waybill])) {
            $isVerified = true;
        }

        // Fetch live scans from Delhivery API
        if (!empty($orderData['tracking_number'])) {
            try {
                $delhivery = new Delhivery(null, $orderData['store_id'] ?? null);
                $trackResult = $delhivery->track($orderData['tracking_number']);
                if (!empty($trackResult['success']) && !empty($trackResult['ShipmentData'][0]['Shipment'])) {
                    $delhiveryData = $trackResult['ShipmentData'][0]['Shipment'];
                }
            } catch (Exception $e) {
                error_log("Tracking API fetch error: " . $e->getMessage());
            }
        }
    } else {
        $error = "No shipment record found for reference: " . htmlspecialchars($awbParam);
    }
}

// Store branding
$storeId = $orderData['store_id'] ?? null;
$siteName = $settingsObj->get('site_name', 'ZENS ENTERPRISE', $storeId);
$siteLogo = $settingsObj->get('site_logo', '', $storeId);
$siteLogoText = $settingsObj->get('site_logo_text', $siteName, $storeId);
$sellerCity = 'Surat';

$sellerAddressJson = $settingsObj->get('seller_address_data', '{}', $storeId);
$sellerData = json_decode($sellerAddressJson, true) ?: [];
if (!empty($sellerData['city'])) {
    $sellerCity = $sellerData['city'];
}

// Calculate Milestones & Live Status from Delhivery Data or Fallback Order Data
$currentStatusText = 'Order Placed';
$statusBadge = 'Order Placed';
$statusBadgeColor = 'blue';
$etaText = 'Arriving Soon';
$currentLocationText = $sellerCity;
$latestInstruction = 'Your order is being processed and prepared for shipping.';
$pickupDateFormatted = '';
$scansList = [];

if ($delhiveryData) {
    // Current Status
    $delStatus = $delhiveryData['Status']['Status'] ?? '';
    $delLocation = $delhiveryData['Status']['StatusLocation'] ?? '';
    $delInstructions = $delhiveryData['Status']['Instructions'] ?? '';
    $pickupDate = $delhiveryData['PickUpDate'] ?? '';

    if (!empty($delStatus)) {
        $currentStatusText = $delStatus;
    }
    if (!empty($delLocation)) {
        $currentLocationText = $delLocation;
    }
    if (!empty($delInstructions)) {
        $latestInstruction = $delInstructions;
    }
    if (!empty($pickupDate)) {
        $pickupDateFormatted = date('d M', strtotime($pickupDate));
    }

    // Scans history
    if (!empty($delhiveryData['Scans']) && is_array($delhiveryData['Scans'])) {
        foreach ($delhiveryData['Scans'] as $s) {
            if (!empty($s['ScanDetail'])) {
                $scansList[] = $s['ScanDetail'];
            }
        }
    }

    // Estimate Arrival / ETA
    if (!empty($delhiveryData['ExpectedDeliveryDate'])) {
        $etaTimestamp = strtotime($delhiveryData['ExpectedDeliveryDate']);
        $daysDiff = round(($etaTimestamp - time()) / 86400);
        if ($daysDiff == 0) {
            $etaText = 'Arriving Today';
        } elseif ($daysDiff == 1) {
            $etaText = 'Arriving by Tomorrow';
        } else {
            $etaText = 'Arriving by ' . date('D, d M', $etaTimestamp);
        }
    } elseif (!empty($orderData['delivery_date'])) {
        $etaTimestamp = strtotime($orderData['delivery_date']);
        $daysDiff = round(($etaTimestamp - time()) / 86400);
        if ($daysDiff == 0) {
            $etaText = 'Arriving Today';
        } elseif ($daysDiff == 1) {
            $etaText = 'Arriving by Tomorrow';
        } else {
            $etaText = 'Arriving by ' . date('D, d M', $etaTimestamp);
        }
    }
} else {
    // Fallback if API response is not yet populated
    if ($orderData) {
        $status = strtolower($orderData['order_status']);
        if ($status === 'delivered') {
            $currentStatusText = 'Delivered';
            $etaText = 'Delivered';
        } elseif ($status === 'shipped') {
            $currentStatusText = 'On the Way';
            $etaText = 'Arriving by Tomorrow';
            $latestInstruction = 'Package loaded and moving to a facility near you | ' . $sellerCity;
        } elseif ($status === 'ready_for_pickup' || $status === 'processing') {
            $currentStatusText = 'Ready For Pickup';
            $etaText = 'Expected in 2-3 Days';
            $latestInstruction = 'Shipment manifested. Awaiting courier pickup.';
        }
    }
}

// Milestone state determinations (1: Placed, 2: Picked Up, 3: On the Way, 4: Out for Delivery, 5: Delivered)
$normalizedStatus = strtolower($currentStatusText);
$stepIndex = 1;

if (strpos($normalizedStatus, 'delivered') !== false) {
    $stepIndex = 5;
    $statusBadge = 'Delivered';
    $statusBadgeColor = 'green';
    $etaText = 'Delivered';
} elseif (strpos($normalizedStatus, 'out for delivery') !== false || strpos($normalizedStatus, 'dispatched') !== false) {
    $stepIndex = 4;
    $statusBadge = 'Out for Delivery';
    $statusBadgeColor = 'purple';
    $etaText = 'Arriving Today';
} elseif (strpos($normalizedStatus, 'in transit') !== false || strpos($normalizedStatus, 'way') !== false || strpos($normalizedStatus, 'facility') !== false || strpos($normalizedStatus, 'reached') !== false || strpos($normalizedStatus, 'left') !== false) {
    $stepIndex = 3;
    $statusBadge = !empty($pickupDateFormatted) ? "Order Picked up on $pickupDateFormatted" : "In Transit";
    $statusBadgeColor = 'blue';
} elseif (strpos($normalizedStatus, 'picked') !== false || strpos($normalizedStatus, 'manifest') !== false) {
    $stepIndex = 2;
    $statusBadge = "Order Picked up" . (!empty($pickupDateFormatted) ? " on $pickupDateFormatted" : "");
    $statusBadgeColor = 'blue';
} else {
    $stepIndex = 1;
    $statusBadge = "Order Placed";
    $statusBadgeColor = 'gray';
}

// Shipping address parsing
$shippingAddr = [];
if (!empty($orderData['shipping_address'])) {
    $shippingAddr = json_decode($orderData['shipping_address'], true) ?: [];
}
$destCity = $shippingAddr['city'] ?? $orderData['customer_city'] ?? 'Destination';
$destState = $shippingAddr['state'] ?? $orderData['customer_state'] ?? '';
$destPin = $shippingAddr['zip'] ?? $shippingAddr['pincode'] ?? '';

// Coordinates dictionary for Indian Hubs/Cities (for Leaflet route rendering)
$cityCoords = [
    'surat' => [21.1702, 72.8311],
    'ahmedabad' => [23.0225, 72.5714],
    'vadodara' => [22.3072, 73.1812],
    'rajkot' => [22.3039, 70.8022],
    'mumbai' => [19.0760, 72.8777],
    'pune' => [18.5204, 73.8567],
    'delhi' => [28.6139, 77.2090],
    'new delhi' => [28.6139, 77.2090],
    'gurugram' => [28.4595, 77.0266],
    'noida' => [28.5355, 77.3910],
    'jaipur' => [26.9124, 75.7873],
    'indore' => [22.7196, 75.8577],
    'bhopal' => [23.2599, 77.4126],
    'bengaluru' => [12.9716, 77.5946],
    'bangalore' => [12.9716, 77.5946],
    'hyderabad' => [17.3850, 78.4867],
    'chennai' => [13.0827, 80.2707],
    'kolkata' => [22.5726, 88.3639],
    'lucknow' => [26.8467, 80.9462],
    'kanpur' => [26.4499, 80.3319],
    'patna' => [25.5941, 85.1376],
    'chandigarh' => [30.7333, 76.7794],
    'nagpur' => [21.1458, 79.0882],
    'coimbatore' => [11.0168, 76.9558],
    'kochi' => [9.9312, 76.2673]
];

function getCoordsForCity($cityName, $coordsMap, $defaultLat = 21.1702, $defaultLng = 72.8311) {
    $clean = strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $cityName)));
    foreach ($coordsMap as $name => $latlng) {
        if (strpos($clean, $name) !== false) {
            return $latlng;
        }
    }
    return [$defaultLat, $defaultLng];
}

$pickupCoords = getCoordsForCity($sellerCity, $cityCoords, 21.1702, 72.8311);
$currentCoords = getCoordsForCity($currentLocationText, $cityCoords, $pickupCoords[0], $pickupCoords[1]);
$dropCoords = getCoordsForCity($destCity, $cityCoords, 21.1702 + 0.1, 72.8311 + 0.1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order <?php echo $orderData ? '#' . htmlspecialchars($orderData['order_number']) : ''; ?> | <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/fontawesome-custom.css">
    
    <!-- Leaflet.js for Interactive Route Map (No API Key Required) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        .status-card {
            border: 2px solid #ef4444; /* Clean Delhivery red accent border */
            border-radius: 1.25rem;
            background: #ffffff;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }

        #trackingMap {
            height: 280px;
            width: 100%;
            border-radius: 1.25rem;
            z-index: 10;
        }

        .tab-pill {
            transition: all 0.2s ease-in-out;
        }
        .tab-pill.active {
            background-color: #000000;
            color: #ffffff;
        }
        .tab-pill:not(.active) {
            background-color: transparent;
            color: #64748b;
        }
        .tab-pill:not(.active):hover {
            color: #000000;
        }

        /* Timeline Connector Lines */
        .timeline-line {
            width: 2px;
            background-color: #e2e8f0;
            position: absolute;
            top: 24px;
            bottom: 0;
            left: 11px;
            z-index: 1;
        }
        .timeline-line.active {
            background-color: #10b981;
        }

        /* Pulse animation for active courier pin */
        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 0.3; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }
        .pulse-marker {
            animation: pulse-ring 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800 antialiased flex flex-col justify-between">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="<?php echo $baseUrl; ?>/" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition" title="Back to Store">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <a href="<?php echo $baseUrl; ?>/" class="font-bold text-lg tracking-tight text-slate-900 flex items-center gap-2">
                    <?php if (!empty($siteLogo)): ?>
                        <img src="<?php echo $baseUrl; ?>/assets/images/<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="h-8 max-w-[140px] object-contain">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars($siteName); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo $baseUrl; ?>/account" class="text-xs md:text-sm font-semibold text-slate-600 hover:text-black flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
                    <i class="fas fa-user-circle text-base"></i>
                    <span class="hidden sm:inline">My Orders</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-4 py-6 md:py-8 space-y-6">

        <?php if (empty($awbParam)): ?>
            <!-- Fallback if no AWB parameter passed (No open public search) -->
            <div class="bg-white rounded-2xl p-10 text-center border border-slate-200 shadow-sm max-w-lg mx-auto my-12">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fas fa-box-open"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mb-2">Shipment Tracking</h1>
                <p class="text-slate-600 text-sm mb-6 leading-relaxed">
                    To track your parcel, please click the secure tracking link provided in your order confirmation email, SMS, or order history.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?php echo $baseUrl; ?>/account" class="bg-black text-white px-6 py-2.5 rounded-xl font-semibold text-sm hover:bg-slate-800 transition">
                        View My Orders
                    </a>
                    <a href="<?php echo $baseUrl; ?>/" class="bg-slate-100 text-slate-700 px-6 py-2.5 rounded-xl font-semibold text-sm hover:bg-slate-200 transition">
                        Return to Store
                    </a>
                </div>
            </div>

        <?php elseif (!empty($error)): ?>
            <!-- Error message if AWB not found in DB -->
            <div class="bg-white rounded-2xl p-8 text-center border border-red-200 shadow-sm max-w-lg mx-auto my-12">
                <div class="w-14 h-14 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-900 mb-1">Shipment Not Found</h2>
                <p class="text-slate-600 text-sm mb-6"><?php echo $error; ?></p>
                <a href="<?php echo $baseUrl; ?>/" class="inline-block bg-black text-white px-6 py-2.5 rounded-xl font-semibold text-sm hover:bg-slate-800 transition">
                    Return to Homepage
                </a>
            </div>

        <?php else: ?>

            <!-- MAP SECTION (Shown when verified, or blur-previewed when unverified) -->
            <div class="relative bg-white rounded-2xl p-2 border border-slate-200 shadow-sm overflow-hidden">
                <div id="trackingMap"></div>
                <?php if (!$isVerified): ?>
                    <!-- Locked Map Overlay -->
                    <div class="absolute inset-0 bg-slate-900/10 backdrop-blur-[2px] z-20 flex items-center justify-center p-4">
                        <div class="bg-white/95 px-5 py-3 rounded-xl shadow-lg border border-slate-200 flex items-center gap-3">
                            <i class="fas fa-lock text-orange-500 text-base"></i>
                            <span class="text-xs sm:text-sm font-semibold text-slate-800">Verify phone number below to unlock live route map</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MAIN STATUS CARD (Matches Screenshot 1 & 2) -->
            <div class="status-card p-6 md:p-8 relative">
                <!-- Status Pill Top Left -->
                <div class="mb-4">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i class="fas fa-check-circle text-emerald-500"></i>
                        <span><?php echo htmlspecialchars($statusBadge); ?></span>
                    </span>
                </div>

                <!-- Big Headline (ETA) -->
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight mb-4">
                    <?php echo htmlspecialchars($etaText); ?>
                </h1>

                <!-- Divider -->
                <div class="h-px bg-slate-100 my-4"></div>

                <!-- Seller Name & Product Summary -->
                <div class="space-y-1 mb-4">
                    <div class="text-xs uppercase tracking-wider font-bold text-slate-400">
                        <?php echo htmlspecialchars($siteName); ?>
                    </div>
                    <?php if (!empty($orderData['items'])): ?>
                        <div class="text-sm font-medium text-slate-700 line-clamp-1">
                            Order : <?php echo htmlspecialchars($orderData['items'][0]['product_name'] ?? 'Ordered Items'); ?>
                            <?php if (count($orderData['items']) > 1): ?>
                                <span class="text-slate-400 text-xs">+<?php echo count($orderData['items']) - 1; ?> more</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- AWB Number + Copy Button -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <div class="flex items-center gap-2 text-sm text-slate-600 font-medium">
                        <span>AWB #<span id="awbText"><?php echo htmlspecialchars($orderData['tracking_number'] ?: $awbParam); ?></span></span>
                        <button type="button" onclick="copyAwbNumber()" class="p-1 hover:text-black transition" title="Copy AWB">
                            <i class="far fa-copy text-sm" id="copyIcon"></i>
                        </button>
                        <span id="copyFeedback" class="text-xs text-emerald-600 font-semibold hidden">Copied!</span>
                    </div>

                    <!-- Payment Mode & Total -->
                    <div class="text-sm font-bold text-slate-900">
                        <?php 
                        $isCOD = (stripos($orderData['payment_method'] ?? '', 'cod') !== false || stripos($orderData['payment_method'] ?? '', 'cash') !== false);
                        echo $isCOD ? 'COD' : 'Prepaid';
                        ?> • ₹ <?php echo number_format($orderData['grand_total'] ?: $orderData['total_amount'], 2); ?>
                    </div>
                </div>
            </div>

            <!-- TABS SWITCHER (Screenshot 1 & 3: [ Tracking ] | [ Order Details ]) -->
            <div class="bg-slate-200/75 p-1.5 rounded-full flex max-w-md mx-auto">
                <button type="button" onclick="switchTab('tracking')" id="btnTabTracking" class="tab-pill active flex-1 py-2 rounded-full font-bold text-sm transition text-center">
                    Tracking
                </button>
                <button type="button" onclick="switchTab('details')" id="btnTabDetails" class="tab-pill flex-1 py-2 rounded-full font-bold text-sm transition text-center">
                    Order Details
                </button>
            </div>

            <!-- TAB 1: TRACKING TIMELINE -->
            <div id="tabContentTracking" class="space-y-6">

                <?php if (!$isVerified): ?>
                    <!-- VERIFICATION BOX (Matches Screenshot 1 login prompt) -->
                    <div id="verificationBox" class="bg-white rounded-2xl p-6 md:p-8 border border-slate-200 shadow-sm">
                        <div class="max-w-md mx-auto text-center">
                            <div class="w-12 h-12 bg-slate-100 text-slate-700 rounded-full flex items-center justify-center mx-auto mb-3 text-lg">
                                <i class="fas fa-shield-alt text-orange-600"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mb-1">Verify to View Complete Tracking</h3>
                            <p class="text-slate-500 text-xs sm:text-sm mb-6">
                                For your privacy, enter the registered 10-digit mobile number for this order to unlock detailed scans and route history.
                            </p>

                            <!-- Step 1: Mobile Input -->
                            <div id="otpPhoneStep" class="space-y-3">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-semibold text-sm">
                                        +91
                                    </div>
                                    <input type="tel" id="verifyPhoneInput" maxlength="10" placeholder="10-digit Mobile Number" class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-black focus:border-black outline-none text-sm font-semibold tracking-wider">
                                </div>
                                <div id="verifyPhoneError" class="text-xs text-red-600 font-medium hidden text-left"></div>
                                <button type="button" onclick="requestTrackingOtp()" id="sendOtpBtn" class="w-full bg-black text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition flex items-center justify-center gap-2">
                                    <span>Get Verification Code</span>
                                </button>
                            </div>

                            <!-- Step 2: OTP Input (Hidden initially) -->
                            <div id="otpVerifyStep" class="space-y-3 hidden">
                                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-800">
                                    <i class="fas fa-envelope-open-text mr-1"></i> OTP sent to your registered email: <strong id="maskedEmailDisplay"></strong>
                                </div>
                                <input type="text" id="verifyOtpInput" maxlength="6" placeholder="Enter 6-digit OTP" class="w-full px-4 py-3 text-center tracking-[0.5em] text-lg font-mono rounded-xl border border-slate-300 focus:ring-2 focus:ring-black focus:border-black outline-none font-bold">
                                <div id="verifyOtpError" class="text-xs text-red-600 font-medium hidden"></div>
                                <button type="button" onclick="submitTrackingOtp()" id="submitOtpBtn" class="w-full bg-emerald-600 text-white py-3 rounded-xl font-bold text-sm hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                                    <span>Verify & Unlock Tracking</span>
                                </button>
                                <div class="pt-2 text-xs text-slate-500">
                                    Didn't get code? <button type="button" onclick="requestTrackingOtp()" class="text-black font-bold underline">Resend OTP</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TIMELINE CONTAINER -->
                <div class="bg-white rounded-2xl p-6 md:p-8 border border-slate-200 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-route text-slate-400"></i>
                        Live Tracking History
                    </h3>

                    <div class="relative pl-6 space-y-8">

                        <!-- Milestone 1: Order Placed -->
                        <div class="relative flex items-start gap-4">
                            <div class="timeline-line <?php echo $stepIndex >= 2 ? 'active' : ''; ?>"></div>
                            <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs flex-shrink-0 z-10">
                                <i class="fas fa-check text-[10px]"></i>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900">
                                    Order Placed
                                    <span class="font-normal text-xs text-slate-500 ml-2">
                                        <?php echo date('D, d M, h:i A', strtotime($orderData['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">Order confirmed with <?php echo htmlspecialchars($siteName); ?></div>
                            </div>
                        </div>

                        <!-- Milestone 2: Picked Up -->
                        <div class="relative flex items-start gap-4">
                            <div class="timeline-line <?php echo $stepIndex >= 3 ? 'active' : ''; ?>"></div>
                            <div class="w-6 h-6 rounded-full <?php echo $stepIndex >= 2 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'; ?> flex items-center justify-center text-xs flex-shrink-0 z-10">
                                <?php if ($stepIndex >= 2): ?>
                                    <i class="fas fa-check text-[10px]"></i>
                                <?php else: ?>
                                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-sm font-bold <?php echo $stepIndex >= 2 ? 'text-slate-900' : 'text-slate-400'; ?>">
                                    Picked Up
                                    <?php if (!empty($pickupDateFormatted)): ?>
                                        <span class="font-normal text-xs text-slate-500 ml-2"><?php echo htmlspecialchars($pickupDateFormatted); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">Package picked up by Delhivery Logistics from <?php echo htmlspecialchars($sellerCity); ?> warehouse</div>
                            </div>
                        </div>

                        <!-- Milestone 3: On the Way -->
                        <div class="relative flex items-start gap-4">
                            <div class="timeline-line <?php echo $stepIndex >= 4 ? 'active' : ''; ?>"></div>
                            <div class="w-6 h-6 rounded-full <?php echo $stepIndex >= 3 ? 'bg-emerald-500 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-400'; ?> flex items-center justify-center text-xs flex-shrink-0 z-10">
                                <?php if ($stepIndex > 3): ?>
                                    <i class="fas fa-check text-[10px]"></i>
                                <?php elseif ($stepIndex == 3): ?>
                                    <i class="fas fa-truck text-[10px]"></i>
                                <?php else: ?>
                                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <div class="text-sm font-bold <?php echo $stepIndex >= 3 ? 'text-slate-900' : 'text-slate-400'; ?>">
                                    On the Way
                                    <span class="font-normal text-xs text-slate-500 ml-2">
                                        <?php echo !empty($delhiveryData['Status']['StatusDateTime']) ? date('D, d M, h:i A', strtotime($delhiveryData['Status']['StatusDateTime'])) : ''; ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-600 mt-1 leading-relaxed">
                                    <?php echo htmlspecialchars($latestInstruction); ?>
                                </div>

                                <?php if ($isVerified && !empty($scansList)): ?>
                                    <!-- Highlighted Latest Activity Pill (Screenshot 3 style) -->
                                    <div class="mt-3 p-3 bg-emerald-50/70 rounded-xl border border-emerald-200/70 text-xs space-y-1">
                                        <div class="font-bold text-emerald-900">
                                            <?php echo htmlspecialchars($scansList[0]['Instructions'] ?? $scansList[0]['Scan'] ?? 'Package moving in transit'); ?>
                                        </div>
                                        <div class="text-emerald-700 text-[11px]">
                                            <?php echo !empty($scansList[0]['ScanDateTime']) ? date('D, d M, h:i A', strtotime($scansList[0]['ScanDateTime'])) : ''; ?>
                                            <?php if (!empty($scansList[0]['ScannedLocation'])): ?>
                                                • <?php echo htmlspecialchars($scansList[0]['ScannedLocation']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Collapsible Full Activity Scans -->
                                    <?php if (count($scansList) > 1): ?>
                                        <div class="mt-3">
                                            <button type="button" onclick="document.getElementById('allScansDrawer').classList.toggle('hidden')" class="text-xs font-bold text-slate-700 hover:text-black underline flex items-center gap-1">
                                                <span>View all <?php echo count($scansList); ?> scan checkpoints</span>
                                                <i class="fas fa-chevron-down text-[10px]"></i>
                                            </button>
                                            <div id="allScansDrawer" class="hidden mt-3 space-y-2 border-l-2 border-slate-200 pl-3">
                                                <?php foreach (array_slice($scansList, 1) as $scan): ?>
                                                    <div class="text-xs text-slate-600">
                                                        <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($scan['Instructions'] ?: $scan['Scan']); ?></div>
                                                        <div class="text-[11px] text-slate-400">
                                                            <?php echo !empty($scan['ScanDateTime']) ? date('d M, h:i A', strtotime($scan['ScanDateTime'])) : ''; ?>
                                                            <?php if (!empty($scan['ScannedLocation'])): ?>
                                                                • <?php echo htmlspecialchars($scan['ScannedLocation']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Milestone 4: Out for Delivery -->
                        <div class="relative flex items-start gap-4">
                            <div class="timeline-line <?php echo $stepIndex >= 5 ? 'active' : ''; ?>"></div>
                            <div class="w-6 h-6 rounded-full <?php echo $stepIndex >= 4 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'; ?> flex items-center justify-center text-xs flex-shrink-0 z-10">
                                <?php if ($stepIndex >= 5): ?>
                                    <i class="fas fa-check text-[10px]"></i>
                                <?php elseif ($stepIndex == 4): ?>
                                    <i class="fas fa-shipping-fast text-[10px]"></i>
                                <?php else: ?>
                                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-sm font-bold <?php echo $stepIndex >= 4 ? 'text-slate-900' : 'text-slate-400'; ?>">
                                    Out for Delivery
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">Package assigned to local delivery courier for doorstep delivery</div>
                            </div>
                        </div>

                        <!-- Milestone 5: Delivered -->
                        <div class="relative flex items-start gap-4">
                            <div class="w-6 h-6 rounded-full <?php echo $stepIndex >= 5 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400'; ?> flex items-center justify-center text-xs flex-shrink-0 z-10">
                                <?php if ($stepIndex >= 5): ?>
                                    <i class="fas fa-check text-[10px]"></i>
                                <?php else: ?>
                                    <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-sm font-bold <?php echo $stepIndex >= 5 ? 'text-slate-900' : 'text-slate-400'; ?>">
                                    Delivered
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">Package delivered to recipient</div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- TAB 2: ORDER DETAILS -->
            <div id="tabContentDetails" class="hidden space-y-6">

                <?php if (!$isVerified): ?>
                    <!-- Locked Order Details notice -->
                    <div class="bg-white rounded-2xl p-8 text-center border border-slate-200 shadow-sm">
                        <i class="fas fa-lock text-3xl text-slate-300 mb-3"></i>
                        <h4 class="text-base font-bold text-slate-800 mb-1">Order Details are Private</h4>
                        <p class="text-xs text-slate-500 mb-4">Please verify your mobile number under the Tracking tab to view complete items and recipient address.</p>
                        <button type="button" onclick="switchTab('tracking')" class="bg-black text-white px-5 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition">
                            Go to Verification
                        </button>
                    </div>
                <?php else: ?>

                    <!-- Ordered Products List -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                        <h3 class="text-base font-bold text-slate-900 mb-4">Items in this Package</h3>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($orderData['items'] as $item): ?>
                                <div class="py-3 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-14 h-14 bg-slate-50 rounded-xl overflow-hidden border border-slate-200 flex-shrink-0 flex items-center justify-center">
                                            <?php if (!empty($item['product_image'])): ?>
                                                <img src="<?php echo getImageUrl($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-box text-slate-300 text-xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-sm text-slate-800 line-clamp-1"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div class="text-xs text-slate-400 mt-0.5">Qty: <?php echo (int)$item['quantity']; ?></div>
                                        </div>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 flex-shrink-0">
                                        ₹ <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="mt-4 pt-4 border-t border-slate-100 text-xs space-y-1.5 text-slate-600">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span>₹ <?php echo number_format($orderData['subtotal'], 2); ?></span>
                            </div>
                            <?php if ($orderData['discount_amount'] > 0): ?>
                                <div class="flex justify-between text-emerald-600">
                                    <span>Discount</span>
                                    <span>-₹ <?php echo number_format($orderData['discount_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span>Shipping Fee</span>
                                <span>₹ <?php echo number_format($orderData['shipping_amount'], 2); ?></span>
                            </div>
                            <div class="flex justify-between font-bold text-slate-900 text-sm pt-2 border-t border-slate-100">
                                <span>Total Paid</span>
                                <span>₹ <?php echo number_format($orderData['grand_total'] ?: $orderData['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Address & Recipient -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                        <h3 class="text-base font-bold text-slate-900 mb-3">Delivery Information</h3>
                        <div class="text-sm text-slate-700 space-y-1">
                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($orderData['customer_name']); ?></div>
                            <?php if (!empty($shippingAddr['street'])): ?>
                                <div><?php echo htmlspecialchars($shippingAddr['street']); ?></div>
                            <?php endif; ?>
                            <div>
                                <?php echo htmlspecialchars($destCity); ?><?php echo !empty($destState) ? ', ' . htmlspecialchars($destState) : ''; ?> - <?php echo htmlspecialchars($destPin); ?>
                            </div>
                            <div class="text-xs text-slate-500 pt-2">
                                <i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($orderData['customer_phone']); ?>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-8">
        <div>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</div>
        <div class="mt-1">Logistics powered by Delhivery Express</div>
    </footer>

    <!-- Interactive Map Script (Leaflet) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var mapContainer = document.getElementById('trackingMap');
        if (!mapContainer) return;

        var originLat = <?php echo (float)$pickupCoords[0]; ?>;
        var originLng = <?php echo (float)$pickupCoords[1]; ?>;
        var currentLat = <?php echo (float)$currentCoords[0]; ?>;
        var currentLng = <?php echo (float)$currentCoords[1]; ?>;
        var dropLat = <?php echo (float)$dropCoords[0]; ?>;
        var dropLng = <?php echo (float)$dropCoords[1]; ?>;

        // Initialize Map centered between origin and destination
        var centerLat = (originLat + dropLat) / 2;
        var centerLng = (originLng + dropLng) / 2;
        var map = L.map('trackingMap', {
            zoomControl: false,
            attributionControl: false
        }).setView([centerLat, centerLng], 8);

        // Clean free OpenStreetMap tiles (no API key required)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            subdomains: ['a', 'b', 'c']
        }).addTo(map);

        // Custom Markers
        var warehouseIcon = L.divIcon({
            className: 'custom-pin',
            html: '<div style="background:#0f172a;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);font-size:12px;"><i class="fas fa-warehouse"></i></div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });

        var dropIcon = L.divIcon({
            className: 'custom-pin',
            html: '<div style="background:#10b981;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);font-size:12px;"><i class="fas fa-map-marker-alt"></i></div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });

        var truckIcon = L.divIcon({
            className: 'custom-pin',
            html: '<div class="pulse-marker" style="background:#ef4444;color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 4px 10px rgba(239,68,68,0.5);font-size:13px;"><i class="fas fa-truck"></i></div>',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        // Add markers
        L.marker([originLat, originLng], { icon: warehouseIcon }).addTo(map).bindPopup('<b>Pickup Warehouse</b><br><?php echo addslashes($sellerCity); ?>');
        L.marker([dropLat, dropLng], { icon: dropIcon }).addTo(map).bindPopup('<b>Destination Drop</b><br><?php echo addslashes($destCity); ?>');
        L.marker([currentLat, currentLng], { icon: truckIcon }).addTo(map).bindPopup('<b>Current Facility:</b><br><?php echo addslashes($currentLocationText); ?>');

        // Draw Route Line
        var routePoints = [
            [originLat, originLng],
            [currentLat, currentLng],
            [dropLat, dropLng]
        ];
        var polyline = L.polyline(routePoints, {
            color: '#10b981',
            weight: 3,
            opacity: 0.8,
            dashArray: '6, 8'
        }).addTo(map);

        // Fit map bounds to view all points comfortably
        map.fitBounds(polyline.getBounds(), { padding: [40, 40] });
    });

    // Tab Switcher
    function switchTab(tab) {
        var btnTracking = document.getElementById('btnTabTracking');
        var btnDetails = document.getElementById('btnTabDetails');
        var contentTracking = document.getElementById('tabContentTracking');
        var contentDetails = document.getElementById('tabContentDetails');

        if (tab === 'tracking') {
            btnTracking.classList.add('active');
            btnDetails.classList.remove('active');
            contentTracking.classList.remove('hidden');
            contentDetails.classList.add('hidden');
        } else {
            btnDetails.classList.add('active');
            btnTracking.classList.remove('active');
            contentDetails.classList.remove('hidden');
            contentTracking.classList.add('hidden');
        }
    }

    // Copy AWB Function
    function copyAwbNumber() {
        var awb = document.getElementById('awbText').innerText.trim();
        navigator.clipboard.writeText(awb).then(function() {
            var feedback = document.getElementById('copyFeedback');
            var icon = document.getElementById('copyIcon');
            feedback.classList.remove('hidden');
            icon.className = 'fas fa-check text-emerald-600';
            setTimeout(function() {
                feedback.classList.add('hidden');
                icon.className = 'far fa-copy text-sm';
            }, 2500);
        });
    }

    // Request Tracking OTP via AJAX
    function requestTrackingOtp() {
        var phone = document.getElementById('verifyPhoneInput').value.trim();
        var phoneErr = document.getElementById('verifyPhoneError');
        var sendBtn = document.getElementById('sendOtpBtn');
        var awb = '<?php echo addslashes($orderData['tracking_number'] ?? $awbParam); ?>';

        phoneErr.classList.add('hidden');

        if (!phone || phone.length < 10) {
            phoneErr.innerText = 'Please enter a valid 10-digit mobile number.';
            phoneErr.classList.remove('hidden');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';

        fetch('<?php echo $baseUrl; ?>/api/tracking_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_otp',
                awb: awb,
                phone: phone
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = 'Get Verification Code';

            if (data.success) {
                document.getElementById('otpPhoneStep').classList.add('hidden');
                document.getElementById('otpVerifyStep').classList.remove('hidden');
                document.getElementById('maskedEmailDisplay').innerText = data.masked_email || 'your email';
            } else {
                phoneErr.innerText = data.message || 'Verification request failed.';
                phoneErr.classList.remove('hidden');
            }
        })
        .catch(function(err) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = 'Get Verification Code';
            phoneErr.innerText = 'Network error. Please try again.';
            phoneErr.classList.remove('hidden');
        });
    }

    // Submit and Verify OTP
    function submitTrackingOtp() {
        var otp = document.getElementById('verifyOtpInput').value.trim();
        var otpErr = document.getElementById('verifyOtpError');
        var submitBtn = document.getElementById('submitOtpBtn');
        var awb = '<?php echo addslashes($orderData['tracking_number'] ?? $awbParam); ?>';

        otpErr.classList.add('hidden');

        if (!otp || otp.length < 6) {
            otpErr.innerText = 'Please enter the 6-digit OTP code.';
            otpErr.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        fetch('<?php echo $baseUrl; ?>/api/tracking_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'verify_otp',
                awb: awb,
                otp: otp
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Verify & Unlock Tracking';

            if (data.success) {
                // Reload page to display full unlocked tracking & map
                window.location.reload();
            } else {
                otpErr.innerText = data.message || 'Incorrect verification code.';
                otpErr.classList.remove('hidden');
            }
        })
        .catch(function(err) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Verify & Unlock Tracking';
            otpErr.innerText = 'Network error. Please try again.';
            otpErr.classList.remove('hidden');
        });
    }
    </script>

</body>
</html>
