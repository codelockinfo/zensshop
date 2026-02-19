<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';

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
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .shadow-lg { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">

    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden invoice-container">
        
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
                <button onclick="window.print()" class="no-print bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Invoice
                </button>
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
                    foreach ($items as $item): 
                        // Calculate Unit Tax
                         $taxRate = $item['gst_percent'] ?? 0;
                         $unitPrice = $item['price'];
                         // Assuming price is unit price.
                         // Calculate Unit Tax
                         $unitTax = ($unitPrice * $taxRate) / 100;
                         $unitTotal = $unitPrice + $unitTax;
                         
                         $lineTotal = $item['line_total'] ?? ($unitTotal * $item['quantity']);
                    ?>
                    <tr>
                        <td class="py-4 px-4 text-gray-500"><?php echo $i++; ?></td>
                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <img src="<?php echo getProductImage($item); ?>" class="w-10 h-10 object-cover rounded mr-3 border">
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
                                // Display Total Tax for this line
                                $totalLineTax = $unitTax * $item['quantity'];
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
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span><?php echo formatCurrency($order['subtotal']); ?></span>
                    </div>
                    
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-green-600">
                        <span>Discount</span>
                        <span>- <?php echo formatCurrency($order['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['shipping_amount'] > 0): ?>
                    <div class="flex justify-between text-gray-600">
                        <span>Shipping/Delivery</span>
                        <span><?php echo formatCurrency($order['shipping_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['tax_amount'] > 0): ?>
                    <div class="flex justify-between text-gray-600">
                        <span>Total Tax</span>
                        <span><?php echo formatCurrency($order['tax_amount']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (($order['cod_charge'] ?? 0) > 0): ?>
                    <div class="flex justify-between text-blue-600">
                        <span>COD Service Charge</span>
                        <span><?php echo formatCurrency($order['cod_charge']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between border-t border-gray-300 pt-3 text-xl font-bold text-gray-800">
                        <span>Grand Total</span>
                        <span class="text-green-600"><?php echo formatCurrency($order['grand_total']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-xs text-gray-400">
                <p>This is a computer-generated invoice.</p>
                <p><?php echo htmlspecialchars($storeAddress); ?></p>
            </div>
        </div>

    </div>

</body>
</html>
