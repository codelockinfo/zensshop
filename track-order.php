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
$logo = $settings->get('footer_logo_image', null, $storeId);
$logoText = $settings->get('footer_logo_text', 'homeprox.in', $storeId);
$logoType = $settings->get('footer_logo_type', 'text', $storeId);
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
    <title>Track Order #<?php echo htmlspecialchars($order['order_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* PDF Generation Support - Force Desktop Layout */
        .generating-pdf #invoice-content {
            width: 1200px !important;
            max-width: 1200px !important;
            margin: 0 !important;
            padding: 15mm !important;
            box-shadow: none !important;
            background: white !important;
        }
        .generating-pdf table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important; /* Force fixed to respect our widths */
            font-size: 10pt !important;
        }
        .generating-pdf th, .generating-pdf td {
            padding: 4mm 2mm !important;
            word-wrap: break-word !important;
        }
        .generating-pdf .item-name-col {
            width: auto !important; /* Let it take remaining space */
        }
        /* Specific widths for price columns to prevent overlap */
        .generating-pdf .price-col { width: 120px !important; text-align: right !important; }
        .generating-pdf .qty-col { width: 60px !important; text-align: center !important; }
        .generating-pdf .tax-col { width: 80px !important; text-align: center !important; }
        .generating-pdf .total-col { width: 140px !important; text-align: right !important; }

        .generating-pdf .no-print {
            display: none !important;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0 !important; margin: 0 !important; -webkit-print-color-adjust: exact; }
            #invoice-content { 
                width: 100% !important; 
                max-width: 100% !important; 
                margin: 0 !important; 
                box-shadow: none !important;
                padding: 10mm !important;
            }
            table { font-size: 9pt !important; width: 100% !important; table-layout: auto !important; }
            th, td { padding: 3mm 1mm !important; border-bottom: 0.1pt solid #eee !important; }
            .item-name-col { white-space: normal !important; }
            .print-nowrap { white-space: nowrap !important; }
            img { max-width: 10mm !important; }
        }
        /* Custom scrollbar for mobile visibility */
        .overflow-x-auto::-webkit-scrollbar {
            height: 6px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }
        .overflow-x-auto {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f1f1;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">

    <div id="invoice-content" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden invoice-container">
        
        <!-- Header / Logo -->
        <div class="md:p-8 p-4 border-b border-gray-200 flex justify-between items-start">
            <div class="w-1/2">
                <?php if ($logoType === 'image' && !empty($logo)): ?>
                    <img src="<?php echo getImageUrl($logo); ?>" alt="Logo" class="h-16 object-contain mb-4">
                <?php else: ?>
                    <h1 class="text-3xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($logoText); ?></h1>
                <?php endif; ?>
                
                <p class="text-gray-600">
                    Hi <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>,<br>
                    Thank you for your order. Here are your order details.
                </p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-green-600 mb-2">ORDER DETAILS</h2>
                <div class="flex flex-col gap-2 items-end no-print" data-html2canvas-ignore="true">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm w-32">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                    <button onclick="downloadPDF()" class="bg-red-600 text-white px-4 py-2 rounded shadow hover:bg-red-700 transition text-sm w-32">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                         Download
                    </button>
                </div>
            </div>
        </div>

        <!-- Order & Addresses -->
        <div class="p-4 md:p-8 grid grid-cols-2 gap-8">
            <!-- Order Details -->
            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Order No:</span>
                    <span class="font-bold text-gray-800">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Order Date:</span>
                    <span class="text-gray-800"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Delivery Status:</span>
                    <span class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs font-bold uppercase"><?php echo str_replace('_', ' ', $order['order_status']); ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Payment Method:</span>
                    <span class="text-gray-800 font-medium"><?php 
                        $pm = strtoupper($order['payment_method'] ?? 'ONLINE');
                        echo ($pm === 'COD' || $pm === 'CASH_ON_DELIVERY') ? 'Cash on Delivery' : 'Online Payment'; 
                    ?></span>
                </div>
                <?php if (!empty($order['delivery_date'])): ?>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Delivery Date:</span>
                    <span class="text-gray-800"><?php echo date('d M Y', strtotime($order['delivery_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Addresses -->
            <div class="grid grid-cols-1 gap-6 text-sm">
                <div>
                    <h3 class="font-bold text-gray-500 uppercase text-xs mb-2">Items Sold By:</h3>
                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($logoText); ?></p>
                    <p class="text-gray-600">
                        <?php echo nl2br(htmlspecialchars($settings->get('store_address', 'Ashapuri Society, Ashwin society -2, Khodiyar nagar road, Varachha main road, surat', $order['store_id']))); ?><br>
                        Ph: <?php echo htmlspecialchars($settings->get('store_phone', '+91 7383841408', $order['store_id'])); ?><br>
                        Email: <?php echo htmlspecialchars($settings->get('store_email', 'zens.shop07@gmail.com', $order['store_id'])); ?>
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-gray-500 uppercase text-xs mb-2">Billed To:</h3>
                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="text-gray-600">
                        <?php 
                            $addr = is_array($order['shipping_address']) ? $order['shipping_address'] : json_decode($order['shipping_address'], true);
                            
                            $line1 = array_filter([$addr['street'] ?? '', $addr['address'] ?? $addr['address_line1'] ?? '', $addr['address_line2'] ?? '']);
                            $line2Parts = array_filter([$addr['city'] ?? '', $addr['state'] ?? '']);
                            $line2 = implode(', ', $line2Parts) . ' ' . ($addr['zip'] ?? $addr['pincode'] ?? $addr['postal_code'] ?? '');
                            $line3 = $addr['country'] ?? 'India';
                            
                            echo htmlspecialchars(implode(', ', $line1)) . '<br>';
                            echo htmlspecialchars(trim($line2)) . '<br>';
                            echo htmlspecialchars($line3);
                        ?><br>
                        Ph: <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?><br>
                        Email: <?php echo htmlspecialchars($order['customer_email']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="px-4 md:px-8 py-4 print:overflow-visible">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Order Items</h3>
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-400 w-full pb-2" style="-webkit-overflow-scrolling: touch;">
                <table class="w-full text-xs md:text-sm text-left min-w-[700px] print:min-w-full print:table-auto border-collapse">
                <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-200">
                    <tr>
                        <th class="py-2 px-1 md:py-3 md:px-4 w-10 text-center text-[10px] md:text-sm">No.</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-[10px] md:text-sm item-name-col">Item Name</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-right text-[10px] md:text-sm price-col">Price</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-center text-[10px] md:text-sm qty-col">Qty</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-center text-[10px] md:text-sm tax-col">Tax %</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-right text-[10px] md:text-sm tax-col">Tax Amt.</th>
                        <th class="py-2 px-1 md:py-3 md:px-4 text-right text-[10px] md:text-sm total-col">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
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
                        <td class="py-2 px-1 md:py-4 md:px-4 text-gray-500 text-center border-b border-gray-50"><?php echo $i++; ?></td>
                        <td class="py-2 px-1 md:py-4 md:px-4 border-b border-gray-50 item-name-col">
                            <div class="flex items-center">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="<?php echo getImageUrl($item['product_image']); ?>" class="w-8 h-8 md:w-12 md:h-12 object-cover rounded mr-2 md:mr-3 border shrink-0">
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-800 break-words leading-tight"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <?php if (!empty($item['product_sku'])): ?>
                                        <p class="text-[10px] md:text-xs text-gray-500">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="py-2 px-1 md:py-4 md:px-4 text-right text-gray-600 border-b border-gray-50 price-col"><?php echo formatCurrency($unitPrice); ?></td>
                        <td class="py-2 px-1 md:py-4 md:px-4 text-center text-gray-600 border-b border-gray-50 qty-col"><?php echo $item['quantity']; ?></td>
                        <td class="py-2 px-1 md:py-4 md:px-4 text-center text-gray-600 border-b border-gray-50 tax-col">
                            <?php echo $taxRate > 0 ? (float)$taxRate . '%' : '-'; ?>
                        </td>
                        <td class="py-2 px-1 md:py-4 md:px-4 text-right text-gray-600 border-b border-gray-50 tax-col">
                            <?php 
                                $rowTax = (!empty($item['tax_amount']) && $item['tax_amount'] > 0) ? $item['tax_amount'] : $totalLineTax;
                                echo formatCurrency($rowTax); 
                            ?>
                        </td>
                        <td class="py-2 px-1 md:py-4 md:px-4 text-right font-bold text-gray-800 border-b border-gray-50 total-col">
                            <?php echo formatCurrency($lineTotal); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Totals -->
        <div class="md:p-8 p-4 bg-gray-50 border-t border-gray-200 print:bg-white print:pt-4 break-inside-avoid">
            <div class="flex justify-end">
                <div class="w-full md:w-1/3 space-y-3">
                    <?php
                        $subtotal = ($order['subtotal'] > 0) ? $order['subtotal'] : $calculatedSubtotal;
                        $taxTotal = ($order['tax_amount'] > 0) ? $order['tax_amount'] : $calculatedTaxTotal;
                        $shipping = $order['shipping_amount'] ?? 0;
                        $codCharge = $order['cod_charge'] ?? 0;
                        $discount = $order['discount_amount'] ?? 0;
                        $grandTotal = ($order['grand_total'] > 0) ? $order['grand_total'] : ($subtotal + $taxTotal + $shipping + $codCharge - $discount);
                    ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal (Excl. Tax)</span>
                        <span class="text-gray-800"><?php echo formatCurrency($subtotal); ?></span>
                    </div>
                    <?php if ($shipping > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Shipping/Delivery</span>
                        <span class="text-gray-800"><?php echo formatCurrency($shipping); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($codCharge > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">COD Charges</span>
                        <span class="text-gray-800"><?php echo formatCurrency($codCharge); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>Discount</span>
                        <span>-<?php echo formatCurrency($discount); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total Tax</span>
                        <span class="text-gray-800"><?php echo formatCurrency($taxTotal); ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-gray-200 mt-2">
                        <span class="text-lg font-bold text-gray-800">Grand Total</span>
                        <span class="text-xl font-bold text-green-600"><?php echo formatCurrency($grandTotal); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Notes / Footer -->
            <div class="mt-8 pt-8 border-t border-gray-200 text-center text-gray-500 text-xs">
                <p class="mb-1">This is a computer-generated invoice.</p>
                <?php 
                    $storeAddress = htmlspecialchars($logoText) . ' Pvt. Ltd.<br>' .
                                    htmlspecialchars($settings->get('store_address', 'Ashapuri Society, Ashwin society -2, Khodiyar nagar road, Varachha main road, surat', $order['store_id'])) . '<br>' .
                                    'Ph: ' . htmlspecialchars($settings->get('store_phone', '+91 7383841408', $order['store_id'])) . '<br>' .
                                    'Email: ' . htmlspecialchars($settings->get('store_email', 'zens.shop07@gmail.com', $order['store_id']));
                ?>
                <p><?php echo $storeAddress; ?></p>
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
            const body = document.body;
            body.classList.add('generating-pdf');
            
            var element = document.getElementById('invoice-content');
            var opt = {
                margin:       [5, 5, 5, 5],
                filename:     'invoice_<?php echo $order['order_number']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    width: 1200,      // Capture at desktop width
                    windowWidth: 1200 // Force desktop viewport
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                body.classList.remove('generating-pdf');
            });
        }
    </script>
</body>
</html>
