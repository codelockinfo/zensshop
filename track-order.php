<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Order.php';
require_once __DIR__ . '/classes/Settings.php';

$settings = new Settings();

// Get Order ID or Number
$orderId = $_GET['id'] ?? null;
$orderNumber = $_GET['order_number'] ?? null;

if (!$orderId && !$orderNumber) {
    die("Invalid Order Identifier");
}

$orderModel = new Order();
$order = null;

if ($orderNumber) {
    $order = $orderModel->getByOrderNumber($orderNumber);
} elseif ($orderId) {
    $order = $orderModel->getById($orderId);
}

if (!$order) {
    die("Order not found");
}

// Handle Courier Updates
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['courier_update'])) {
    $newStatus = $_POST['order_status'] ?? '';
    if (in_array($newStatus, ['processing', 'shipped', 'delivered'])) {
        $db = Database::getInstance();
        $db->execute("UPDATE orders SET order_status = ? WHERE order_number = ?", [$newStatus, $order['order_number']]);
        $order['order_status'] = $newStatus; 
        $message = "Order status updated successfully!";
    }
}

// Removed owner auth for public qr link

// Fetch Settings (Dynamic based on Order's Store)
$storeId = $order['store_id'];
$logo = getSetting('footer_logo_image', null, $storeId);
$logoText = getStoreName($storeId);
$logoType = getSetting('footer_logo_type', 'text', $storeId);
$storeAddress = $settings->get('footer_address', 'Store Address Not Set', $storeId);
$storePhone = $settings->get('footer_phone', '', $storeId);
$storeEmail = $settings->get('footer_email', '', $storeId);

// Helpers
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Customer Address
$shippingAddress = json_decode($order['shipping_address'], true) ?? [];
$customerAddressStr = implode(', ', array_filter([
    $shippingAddress['address_line1'] ?? '',
    $shippingAddress['address_line2'] ?? '',
    $shippingAddress['city'] ?? '',
    $shippingAddress['state'] ?? '',
    $shippingAddress['postal_code'] ?? '',
    $shippingAddress['country'] ?? ''
]));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($order['order_number']); ?></title>
    
    <?php
    // Fetch Favicon Settings using Store ID
    $faviconPng = $settings->get('favicon_png', '', $storeId);
    $faviconIco = $settings->get('favicon_ico', '', $storeId);
    $baseUrl = getBaseUrl();
    $faviconUrl = '';
    
    if (!empty($faviconIco)) {
        if (file_exists(__DIR__ . '/' . $faviconIco)) {
            $faviconUrl = $baseUrl . '/' . $faviconIco;
        } else {
            $faviconUrl = htmlspecialchars(getImageUrl($faviconIco));
        }
    } elseif (!empty($faviconPng)) {
        $faviconUrl = htmlspecialchars(getImageUrl($faviconPng));
    } else {
        $faviconUrl = $baseUrl . '/favicon.ico';
    }
    $favType = (strpos($faviconUrl, '.png') !== false) ? 'image/png' : 'image/x-icon';
    ?>
    <link rel="icon" type="<?php echo $favType; ?>" href="<?php echo $faviconUrl; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Ultra-Premium Design System */
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

        /* Standardized capture container for full-width PDF */
        .pdf-capture-view {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 800px !important;
            z-index: -9999 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            background: #fff !important;
        }

        .invoice-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--invoice-border);
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

        .invoice-table { width: 100%; border-collapse: separate; border-spacing: 0; }
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
        .invoice-table tbody tr:last-child td { border-bottom: none; }
        
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
            .invoice-card { border: none !important; box-shadow: none !important; }
            .grand-total-container { background: #064e3b !important; -webkit-print-color-adjust: exact; }
        }

        /* Mobile Adjustments */
        @media (max-width: 640px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">

    <div id="invoice-content" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden invoice-container">
        
        <!-- Header / Logo -->
        <div class="md:p-12 p-6 flex justify-between items-start">
            <div class="flex flex-col gap-6">
                <div class="bg-white p-2 rounded shadow-sm inline-block w-fit">
                    <?php if ($logoType === 'image' && !empty($logo)): ?>
                        <div class="logo-container">
                             <img src="<?php echo getImageUrl($logo); ?>" alt="Logo" class="h-20 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                             <h1 class="text-4xl font-black text-emerald-950 italic tracking-tighter" style="display:none;">
                                <?php echo htmlspecialchars(getStoreName($storeId)); ?>
                             </h1>
                        </div>
                    <?php else: ?>
                        <h1 class="text-4xl font-black text-emerald-950 italic tracking-tighter">
                            <?php echo htmlspecialchars(getStoreName($storeId)); ?>
                        </h1>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Customer</h3>
                    <p class="text-3xl font-black text-slate-900 leading-none"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="text-slate-500 text-sm">Thank you for choosing <?php echo htmlspecialchars(getStoreName($storeId)); ?>. Your order details are below.</p>
                </div>
            </div>

            <div class="text-right">
                <div class="inline-block px-6 py-2 bg-emerald-900 text-white rounded-md mb-4 shadow-lg">
                    <h2 class="text-2xl font-black tracking-widest">INVOICE</h2>
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase">Order Identity</p>
                    <p class="text-xl font-black text-slate-900 leading-none">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>

                <!-- Control Buttons -->
                <div class="flex gap-3 justify-end mt-8 no-print" data-html2canvas-ignore="true">
                    <button onclick="window.print()" class="group flex items-center gap-2 bg-slate-800 text-white px-5 py-2.5 rounded-lg shadow-xl hover:bg-black transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400 group-hover:scale-110 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span class="text-xs font-black uppercase tracking-widest">Print</span>
                    </button>
                    <a href="download-invoice.php?order_number=<?php echo urlencode($order['order_number']); ?>" class="group flex items-center gap-2 bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow-xl hover:bg-emerald-700 transition-all font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        DOWNLOAD
                    </a>
                </div>
            </div>
        </div>

        <div class="px-6 md:px-12 pb-12">
            <!-- Professional Summary Bar -->
            <div class="summary-grid mb-10">
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
                    <h3 class="text-xs font-black text-emerald-600 uppercase tracking-widest mb-4 border-b border-emerald-50 pb-2">Business Address</h3>
                    <p class="font-black text-slate-800 text-sm mb-2"><?php echo htmlspecialchars($logoText); ?></p>
                    <div class="text-slate-500 text-sm leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($settings->get('store_address', 'Ashapuri Society, Ashwin society -2, Khodiyar nagar road, Varachha main road, surat', $order['store_id']))); ?><br>
                        <span class="font-bold">Contact:</span> <?php echo htmlspecialchars($settings->get('store_phone', '+91 7383841408', $order['store_id'])); ?><br>
                        <span class="font-bold">Email:</span> <?php echo htmlspecialchars($settings->get('store_email', 'zens.shop07@gmail.com', $order['store_id'])); ?>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-black text-emerald-600 uppercase tracking-widest mb-4 border-b border-emerald-50 pb-2">Shipping To</h3>
                    <p class="font-black text-slate-800 text-sm mb-2"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <div class="text-slate-500 text-sm leading-relaxed">
                        <?php 
                            $addr = is_array($order['shipping_address']) ? $order['shipping_address'] : json_decode($order['shipping_address'] ?? '{}', true);
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

            <!-- Modern Table -->
            <div class="mb-12 overflow-hidden">
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
                    $calculatedSubtotal = 0;
                    $calculatedTaxTotal = 0;
                    
                    foreach ($items as $item): 
                        // Logic to ensure non-zero display if possible
                        
                         $taxRate = $item['gst_percent'] ?? 0;
                         $unitPrice = $item['price']; // effective price
                         
                         $unitTax = ($unitPrice * $taxRate) / 100;
                         
                         // Check if line_total exists and is non-zero, else calculate
                         $lineTotal = (!empty($item['line_total']) && $item['line_total'] > 0) 
                                      ? $item['line_total'] 
                                      : (($unitPrice + $unitTax) * $item['quantity']);

                         // Fallback for tax amount calculation if DB is empty
                         $totalLineTax = $unitTax * $item['quantity'];
                         
                         $calculatedSubtotal += $unitPrice * $item['quantity'];
                         $calculatedTaxTotal += $totalLineTax;
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
                                        <p class="text-[10px] text-slate-400 mt-1 uppercase font-black tracking-widest">Article: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-right font-medium text-slate-600"><?php echo formatCurrency($unitPrice); ?></td>
                        <td class="text-center text-slate-800 font-black"><?php echo $item['quantity']; ?></td>
                        <td class="text-center text-slate-500">
                            <?php echo $taxRate > 0 ? (float)$taxRate . '%' : '-'; ?>
                        </td>
                        <td class="text-right font-black text-slate-900">
                            <?php echo formatCurrency($lineTotal); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>

            <!-- Totals Area -->
            <div class="flex justify-between items-end gap-12">
                <div class="max-w-[300px]">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Terms & Conditions</h4>
                    <p class="text-[10px] text-slate-400 leading-relaxed italic">
                        All returns must accompany original invoice. Any claims for shortage or damage must be made within 24 hours of receipt. Goods once sold will not be taken back.
                    </p>
                </div>

                <div class="w-full md:w-1/2 space-y-4">
                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Base Amount</span>
                            <span class="text-slate-900 font-black"><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        <?php if ($shipping > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Shipping Fee</span>
                            <span class="text-slate-900 font-black"><?php echo formatCurrency($shipping); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Tax (GST)</span>
                            <span class="text-slate-900 font-black"><?php echo formatCurrency($taxTotal); ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div class="flex justify-between text-sm text-rose-600">
                            <span class="font-bold uppercase text-[10px] tracking-widest">Privilege Discount</span>
                            <span class="font-black">-<?php echo formatCurrency($discount); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grand-total-container shadow-2xl">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60">Payable Amount</span>
                            <span class="text-4xl font-black leading-none tracking-tighter"><?php echo formatCurrency($grandTotal); ?></span>
                        </div>
                        <div class="h-12 w-12 border-2 border-white/20 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High-End Footer -->
            <div class="mt-20 pt-10 border-t border-slate-100 text-center flex flex-col items-center gap-6">
                <div class="flex gap-12 font-black text-[10px] text-slate-300 uppercase tracking-[0.3em]">
                    <span>Secure</span>
                    <span>•</span>
                    <span>Fast Dispatch</span>
                    <span>•</span>
                    <span>Quality Assured</span>
                </div>
                <p class="text-[10px] text-slate-400 italic">This is a system-authenticated document generated from <?php echo htmlspecialchars(getStoreName($storeId)); ?>.</p>
            </div>
        </div>
    </div>

    <!-- Courier / Logistics Update Section -->
    <div class="max-w-4xl mx-auto mt-8 mb-12 no-print">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden border-t-4 border-green-500">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-green-100 p-2 rounded-full mr-3 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Shipment Update (For Courier)</h3>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded mb-6 border border-green-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="courier_update" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Update Status</label>
                            <select name="order_status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 border p-2">
                                <option value="shipped" <?php echo ($order['order_status'] == 'shipped' || $order['order_status'] == 'processing') ? 'selected' : ''; ?>>In Transit / Out for Delivery</option>
                                <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Successfully Delivered</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Name</label>
                            <input type="text" placeholder="Who received the package?" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 border p-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Notes</label>
                        <textarea rows="2" placeholder="Example: Left at front door, signature obtained, etc." class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 border p-2"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md font-bold hover:bg-green-700 transition shadow-md">
                            Update Order Tracking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const original = document.getElementById('invoice-content');
            const clone = original.cloneNode(true);
            
            // 1. Prepare clone: Fill 800px width (standard for A4 capture)
            clone.classList.remove('max-w-4xl', 'mx-auto', 'rounded-lg', 'shadow-lg', 'invoice-container');
            clone.style.width = '800px';
            clone.style.maxWidth = '800px';
            clone.style.margin = '0';
            clone.style.padding = '0'; // Use margins in opt instead
            clone.style.backgroundColor = '#ffffff';
            
            // Force all internal containers to expand and SHOW ALL DATA
            clone.querySelectorAll('.flex, .grid, table, div').forEach(el => {
                el.style.width = '100%';
            });

            const captureContainer = document.createElement('div');
            captureContainer.className = 'pdf-capture-view';
            captureContainer.appendChild(clone);
            document.body.appendChild(captureContainer);

            var opt = {
                margin:       [5, 5, 5, 5], // Small 5mm margins to make the invoice look BIGGER
                filename:     'invoice_<?php echo $order['order_number']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    width: 800,
                    windowWidth: 800,
                    scrollY: 0,
                    scrollX: 0
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // 2. Generate PDF
            html2pdf().set(opt).from(clone).save().then(() => {
                document.body.removeChild(captureContainer);
            }).catch(err => {
                console.error('PDF Generation Error:', err);
                if (captureContainer && captureContainer.parentNode) {
                    document.body.removeChild(captureContainer);
                }
            });
        }
    </script>
</body>
</html>
