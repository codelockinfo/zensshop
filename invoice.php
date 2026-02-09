<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CustomerAuth.php';
require_once __DIR__ . '/classes/Order.php';

// Auth check
$auth = new CustomerAuth();
if (!$auth->isLoggedIn()) {
    $redirectUrl = url('login?redirect=invoice&order_number=' . ($_GET['order_number'] ?? ''));
    if (isset($_GET['id'])) $redirectUrl .= '&id=' . $_GET['id'];
    header('Location: ' . $redirectUrl);
    exit;
}

$currentCustomer = $auth->getCurrentCustomer();
if (!$currentCustomer) {
    die("Customer not found.");
}

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

// Security Check: Ensure the order belongs to the logged-in customer
if ($order['user_id'] != $currentCustomer['customer_id']) {
    die("Access Denied: This order does not belong to you.");
}

// Fetch Settings
$logo = getSetting('footer_logo_image');
$logoText = getSetting('footer_logo_text', 'ZENSSHOP');
$logoType = getSetting('footer_logo_type', 'text');
$storeAddress = getSetting('footer_address', 'Store Address Not Set');
$storePhone = getSetting('footer_phone', '');
$storeEmail = getSetting('footer_email', '');

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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .shadow-lg { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">

    <div id="invoice-content" class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden invoice-container">
        
        <!-- Header / Logo -->
        <div class="p-8 border-b border-gray-200 flex justify-between items-start">
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
                <h2 class="text-2xl font-bold text-green-600 mb-2">INVOICE</h2>
                <div class="flex flex-col gap-2 items-end no-print">
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
        <div class="p-8 grid grid-cols-2 gap-8">
            <!-- Order Details -->
            <div class="space-y-3">
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Order No:</span>
                    <span class="font-bold text-gray-800">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Order Date:</span>
                    <span class="font-bold text-gray-800"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <!-- Delivery Status -->
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500 font-medium">Delivery Status:</span>
                    <span class="font-bold text-gray-800 capitalize">
                        <?php echo !empty($order['tracking_number']) ? 'Shipped' : $order['order_status']; ?>
                    </span>
                </div>
            </div>

            <!-- Addresses -->
            <div class="space-y-4 text-sm">
                <!-- Supplier -->
                <div>
                    <h3 class="text-gray-500 font-bold uppercase text-xs mb-1">Items Sold By:</h3>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars(getSetting('site_name', 'Store Name')); ?></p>
                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($storeAddress)); ?></p>
                    <?php if($storePhone): ?><p class="text-gray-600">Ph: <?php echo htmlspecialchars($storePhone); ?></p><?php endif; ?>
                    <?php if($storeEmail): ?><p class="text-gray-600">Email: <?php echo htmlspecialchars($storeEmail); ?></p><?php endif; ?>
                </div>
                
                <!-- Customer -->
                <div>
                    <h3 class="text-gray-500 font-bold uppercase text-xs mb-1">Billed To:</h3>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="text-gray-600"><?php echo htmlspecialchars($customerAddressStr); ?></p>
                    <?php if($order['customer_phone']): ?><p class="text-gray-600">Ph: <?php echo htmlspecialchars($order['customer_phone']); ?></p><?php endif; ?>
                    <?php if($order['customer_email']): ?><p class="text-gray-600">Email: <?php echo htmlspecialchars($order['customer_email']); ?></p><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="px-8 py-4">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Order Items</h3>
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">No.</th>
                        <th class="py-3 px-4">Item Name</th>
                        <th class="py-3 px-4 text-right">Price</th>
                        <th class="py-3 px-4 text-center">Qty</th>
                        <th class="py-3 px-4 text-center">Tax %</th>
                        <th class="py-3 px-4 text-right">Tax Amt.</th>
                        <th class="py-3 px-4 text-right">Total</th>
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
                        <td class="py-4 px-4 text-gray-500"><?php echo $i++; ?></td>
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="<?php echo getImageUrl($item['product_image']); ?>" class="w-10 h-10 object-cover rounded mr-3 border">
                                <?php endif; ?>
                                <div>
                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <?php if (!empty($item['product_sku'])): ?>
                                        <p class="text-xs text-gray-500">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-right text-gray-600"><?php echo formatCurrency($unitPrice); ?></td>
                        <td class="py-4 px-4 text-center text-gray-600"><?php echo $item['quantity']; ?></td>
                        <td class="py-4 px-4 text-center text-gray-600">
                            <?php echo $taxRate > 0 ? number_format($taxRate, 2) . '%' : '-'; ?>
                        </td>
                        <td class="py-4 px-4 text-right text-gray-600">
                            <?php 
                                echo $totalLineTax > 0 ? formatCurrency($totalLineTax) : '-'; 
                            ?>
                        </td>
                        <td class="py-4 px-4 text-right font-bold text-gray-800">
                            <?php echo formatCurrency($lineTotal); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="p-8 bg-gray-50 border-t border-gray-200">
            <div class="flex justify-end">
                <div class="w-1/2 md:w-1/3 space-y-3">
                    <?php
                        // Use DB values if present, else calculated
                        $subtotal = ($order['subtotal'] > 0) ? $order['subtotal'] : $calculatedSubtotal;
                        $taxAmount = ($order['tax_amount'] > 0) ? $order['tax_amount'] : $calculatedTaxTotal;
                        $shipping = $order['shipping_amount'];
                        $discount = $order['discount_amount'];
                        
                        // Recalculate grand total if needed (fallback)
                        $grandTotal = ($order['grand_total'] > 0) 
                                      ? $order['grand_total'] 
                                      : ($subtotal + $taxAmount + $shipping - $discount);
                        
                        // If tax is included in price logic vs excluded? 
                        // Our earlier logic: Line Total = (Price + Tax) * Qty. 
                        // So Subtotal usually excludes tax in typical scenarios, but if Price includes tax, it gets complicated.
                        // Assuming simple "Price is ex-tax" or "Price is inc-tax" based on setup.
                        // Current logic: LineTotal = (Price + Tax) * Qty. So Grand Total is Sum of Line Totals + Shipping - Discount.
                        
                        // Let's ensure Grand Total matches the sum of line totals + shipping - discount if logic fails
                        if ($grandTotal == 0 && $lineTotal > 0) {
                             $grandTotal = $calculatedSubtotal + $calculatedTaxTotal + $shipping - $discount;
                        }
                    ?>
                
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal (Excl. Tax)</span>
                        <span><?php echo formatCurrency($subtotal); ?></span>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                    <div class="flex justify-between text-green-600">
                        <span>Discount</span>
                        <span>- <?php echo formatCurrency($discount); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($shipping > 0): ?>
                    <div class="flex justify-between text-gray-600">
                        <span>Shipping/Delivery</span>
                        <span><?php echo formatCurrency($shipping); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($taxAmount > 0): ?>
                    <div class="flex justify-between text-gray-600">
                        <span>Total Tax</span>
                        <span><?php echo formatCurrency($taxAmount); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between border-t border-gray-300 pt-3 text-xl font-bold text-gray-800">
                        <span>Grand Total</span>
                        <span class="text-green-600"><?php echo formatCurrency($grandTotal); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-xs text-gray-400">
                <p>This is a computer-generated invoice.</p>
                <p><?php echo htmlspecialchars($storeAddress); ?></p>
            </div>
        </div>

    </div>

    <script>
        function downloadPDF() {
            var element = document.getElementById('invoice-content');
            var opt = {
                margin:       [10, 10, 10, 10], // top, left, bottom, right
                filename:     'invoice_<?php echo $order['order_number']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // New Promise-based usage:
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
