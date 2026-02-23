<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Settings.php';

$settings = new Settings();

// Auth check
$auth = new Auth();
$auth->requireLogin();

// Get Order ID
$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    die("Invalid Order ID");
}

$orderModel = new Order();
$order = $orderModel->getById($orderId);

if (!$order) {
    die("Order not found");
}

// Fetch Settings (Dynamic based on Order's Store)
$storeId = $order['store_id'];
$logo = getSetting('footer_logo_image', null, $storeId);
$logoText = getStoreName($storeId);
$logoType = getSetting('footer_logo_type', 'text', $storeId);
$storeAddress = $settings->get('footer_address', 'Store Address Not Set', $storeId);
$storePhone = $settings->get('footer_phone', '', $storeId);
$storeEmail = $settings->get('footer_email', '', $storeId);

// Helpers
// Helpers
function getCurrencySymbol($currencyCode) {
    $map = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];
    return $map[$currencyCode] ?? $currencyCode;
}

function formatCurrency($amount, $currencyCode = 'INR') {
    return getCurrencySymbol($currencyCode) . number_format($amount, 2);
}

// Extract currency
$currencyCode = 'INR';
if (!empty($order['items']) && !empty($order['items'][0]['currency'])) {
    $currencyCode = $order['items'][0]['currency'];
}
$currency = $currencyCode; // For backward compatibility in the file

// Customer Address
$shippingAddress = json_decode($order['shipping_address'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Invoice #<?php echo htmlspecialchars($order['order_number']); ?></title>
    <?php 
    // Fetch Favicon Settings
    $faviconPng = $settings->get('favicon_png', '', $storeId);
    $faviconIco = $settings->get('favicon_ico', '', $storeId);
    $baseUrl = getBaseUrl();
    $faviconUrl = '';
    
    if (!empty($faviconIco)) {
        if (file_exists(BASE_PATH . '/' . $faviconIco)) {
            $faviconUrl = $baseUrl . '/' . $faviconIco;
        } else {
            $faviconUrl = getImageUrl($faviconIco);
        }
    } elseif (!empty($faviconPng)) {
        $faviconUrl = getImageUrl($faviconPng);
    } else {
        $faviconUrl = $baseUrl . '/admin/Images/Favicon.png';
    }
    $favType = (strpos($faviconUrl, '.png') !== false) ? 'image/png' : 'image/x-icon';
    ?>
    <link rel="icon" type="<?php echo $favType; ?>" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Ultra-Premium Design System - Admin Sync */
        :root {
            --invoice-primary: #064e3b; /* Emerald 900 */
            --invoice-accent: #10b981;  /* Emerald 500 */
            --invoice-slate: #1e293b;   /* Slate 800 */
            --invoice-muted: #64748b;   /* Slate 500 */
            --invoice-border: #f1f5f9;
            --invoice-bg: #ffffff;
        }

        #invoice-content {
            font-family: 'Inter', -apple-system, sans-serif !important;
            color: var(--invoice-slate) !important;
            line-height: 1.5;
        }

        .premium-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-delivered { background-color: #d1fae5; color: #065f46; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }

        .summary-item label {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--invoice-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .summary-item p {
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--invoice-slate);
        }

        .invoice-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
        .invoice-table thead th {
            background: var(--invoice-slate);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem;
            text-align: left;
        }
        .invoice-table thead th:first-child { border-top-left-radius: 6px; }
        .invoice-table thead th:last-child { border-top-right-radius: 6px; text-align: right; }
        
        .invoice-table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--invoice-border);
            font-size: 0.875rem;
        }
        
        .grand-total-container {
            background: var(--invoice-primary);
            color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; }
            #invoice-content { border: none !important; box-shadow: none !important; width: 100% !important; padding: 0 !important; }
            .grand-total-container { background: #064e3b !important; -webkit-print-color-adjust: exact; }
            .invoice-table { min-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">

    <div id="invoice-content" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        
        <!-- Header / Logo -->
        <div class="md:p-8 p-6 flex flex-col md:flex-row justify-between items-center md:items-start gap-4">
            <div class="flex flex-col gap-6">
                <div class="bg-white p-2 rounded shadow-sm inline-block w-fit">
                    <?php if ($logoType === 'image' && !empty($logo)): ?>
                        <div class="logo-container">
                             <img src="<?php echo getImageUrl($logo); ?>" alt="Logo" class="h-20 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                             <h1 class="text-4xl font-bold text-emerald-950 italic tracking-tighter" style="display:none;">
                                <?php echo htmlspecialchars(getStoreName($storeId)); ?>
                             </h1>
                        </div>
                    <?php else: ?>
                        <h1 class="text-4xl font-bold text-emerald-950 italic tracking-tighter">
                            <?php echo htmlspecialchars(getStoreName($storeId)); ?>
                        </h1>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Customer</h3>
                    <p class="text-3xl font-bold text-slate-900 leading-none"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="text-slate-500 text-sm italic border-l-2 border-emerald-500 pl-3">Order Summary</p>
                </div>
            </div>

            <div class="text-center md:text-right w-full md:w-auto">
                <div class="inline-block px-6 py-2 bg-emerald-900 text-white rounded-md mb-4 shadow-lg">
                    <h2 class="text-2xl font-bold tracking-widest">INVOICE</h2>
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase">Order Identity</p>
                    <p class="text-xl font-bold text-slate-900 leading-none">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>

                <!-- Control Buttons -->
                <div class="flex flex-wrap gap-3 justify-center md:justify-end mt-8 no-print">
                    <button onclick="window.print()" class="group flex items-center gap-2 bg-slate-800 text-white px-5 py-2.5 rounded-lg shadow-xl hover:bg-black transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span class="text-xs font-bold uppercase tracking-widest">Print</span>
                    </button>
                    <!-- Link to root download-invoice.php since it handles order number -->
                    <a href="../../download-invoice.php?order_number=<?php echo urlencode($order['order_number']); ?>" class="group flex items-center gap-2 bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow-xl hover:bg-emerald-700 transition-all font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        DOWNLOAD
                    </a>
                </div>
            </div>
        </div>

        <div class="px-6 md:px-12 pb-8">
            <!-- Professional Summary Bar -->
            <div class="summary-grid mb-6">
                <div class="summary-item">
                    <label>Order Date</label>
                    <p><?php echo date('D, d M Y', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="summary-item">
                    <label>Payment Mode</label>
                    <p><?php 
                        $pm = strtoupper($order['payment_method'] ?? 'ONLINE');
                        echo ($pm === 'COD' || $pm === 'CASH_ON_DELIVERY') ? 'Cash on Delivery' : 'Online Secure'; 
                    ?></p>
                </div>
                <div class="summary-item">
                    <label>Status</label>
                    <p>
                        <?php 
                            $statusClass = (strtolower($order['order_status']) === 'delivered') ? 'badge-delivered' : 'badge-pending';
                            echo '<span class="premium-badge '.$statusClass.'">'.str_replace('_', ' ', $order['order_status']).'</span>';
                        ?>
                    </p>
                </div>
                <div class="summary-item">
                    <label>Dispatch Date</label>
                    <p><?php echo (!empty($order['delivery_date'])) ? date('d M Y', strtotime($order['delivery_date'])) : 'In Progress'; ?></p>
                </div>
            </div>

            <!-- Address Blocks -->
            <div class="grid grid-cols-2 gap-16 mb-12">
                <div>
                    <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-widest mb-4 border-b border-emerald-50 pb-2">Business Address</h3>
                    <p class="font-bold text-slate-800 text-sm mb-2"><?php echo htmlspecialchars($logoText); ?></p>
                    <div class="text-slate-500 text-sm leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($storeAddress)); ?><br>
                        <span class="font-bold">Contact:</span> <?php echo htmlspecialchars($storePhone); ?><br>
                        <span class="font-bold">Email:</span> <?php echo htmlspecialchars($storeEmail); ?>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-widest mb-4 border-b border-emerald-50 pb-2">Shipping To</h3>
                    <p class="font-bold text-slate-800 text-sm mb-2"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <div class="text-slate-500 text-sm leading-relaxed">
                        <?php 
                            $addr = $shippingAddress;
                            $line1 = array_filter([$addr['street'] ?? '', $addr['address'] ?? $addr['address_line1'] ?? '', $addr['address_line2'] ?? '']);
                            $line2Parts = array_filter([$addr['city'] ?? '', $addr['state'] ?? '']);
                            $line2 = implode(', ', $line2Parts) . ' ' . ($addr['zip'] ?? $addr['pincode'] ?? $addr['postal_code'] ?? '');
                            $line3 = $addr['country'] ?? 'India';
                            
                            echo htmlspecialchars(implode(', ', $line1)) . '<br>';
                            echo htmlspecialchars(trim($line2)) . '<br>';
                            echo htmlspecialchars($line3);
                        ?><br>
                        <span class="font-bold">Ph:</span> <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="mb-6 overflow-x-auto">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th class="w-16">No.</th>
                            <th>Item Description</th>
                            <th class="text-right">Price</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">GST %</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $items = $order['items'];
                    $i = 1;
                    foreach ($items as $item): 
                         $taxRate = $item['gst_percent'] ?? 0;
                         $unitPrice = $item['price']; 
                         $unitTax = ($unitPrice * $taxRate) / 100;
                         $lineTotal = (!empty($item['line_total']) && $item['line_total'] > 0) 
                                      ? $item['line_total'] 
                                      : (($unitPrice + $unitTax) * $item['quantity']);
                    ?>
                    <tr>
                        <td class="text-center text-slate-400 font-bold"><?php echo $i++; ?></td>
                        <td>
                            <div class="flex items-center gap-4">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="<?php echo getImageUrl($item['product_image']); ?>" class="w-12 h-12 object-cover rounded shadow-sm border border-slate-100 shrink-0 bg-white">
                                <?php endif; ?>
                                <div>
                                    <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <?php if (!empty($item['product_sku'])): ?>
                                        <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold tracking-widest">Article: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-right font-medium text-slate-600 whitespace-nowrap"><?php echo formatCurrency($unitPrice, $currency); ?></td>
                        <td class="text-center text-slate-800 font-bold"><?php echo $item['quantity']; ?></td>
                        <td class="text-center text-slate-500">
                            <?php echo $taxRate > 0 ? (float)$taxRate . '%' : '-'; ?>
                        </td>
                        <td class="text-right font-bold text-slate-900 whitespace-nowrap">
                            <?php echo formatCurrency($lineTotal, $currency); ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
                </table>
            </div>

            <!-- Totals Area -->
            <div class="flex justify-between items-end gap-12">
                <div class="max-w-[300px]">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Internal Admin Note</h4>
                    <p class="text-[10px] text-slate-400 leading-relaxed italic">
                        Verified by Admin. This invoice summary reflects the latest transaction state for Order #<?php echo htmlspecialchars($order['order_number']); ?>.
                    </p>
                </div>

                <div class="w-full md:w-1/2 space-y-4">
                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Base Amount</span>
                            <span class="text-slate-900 font-bold whitespace-nowrap"><?php echo formatCurrency($order['subtotal'], $currency); ?></span>
                        </div>
                        <?php if ($order['shipping_amount'] > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Shipping Fee</span>
                            <span class="text-slate-900 font-bold whitespace-nowrap"><?php echo formatCurrency($order['shipping_amount'], $currency); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Tax (GST)</span>
                            <span class="text-slate-900 font-bold whitespace-nowrap"><?php echo formatCurrency($order['tax_amount'], $currency); ?></span>
                        </div>
                        <?php if ($order['discount_amount'] > 0): ?>
                        <div class="flex justify-between text-sm text-rose-600">
                            <span class="font-bold uppercase text-[10px] tracking-widest">Applied Discount</span>
                            <span class="font-bold whitespace-nowrap">-<?php echo formatCurrency($order['discount_amount'], $currency); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grand-total-container shadow-2xl">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] opacity-60">Grand Total</span>
                            <span class="text-3xl font-bold leading-none whitespace-nowrap"><?php echo formatCurrency($order['grand_total'], $currency); ?></span>
                        </div>
                        <div class="h-12 w-12 border-2 border-white/20 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
