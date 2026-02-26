<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Order List';
require_once __DIR__ . '/../../includes/admin-header.php';

$order = new Order();
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = Database::getInstance()->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}
$filters = [
    'order_status' => $_GET['status'] ?? null,
    'payment_status' => $_GET['payment'] ?? null,
    'search' => $_GET['search'] ?? '',
    'store_id' => $storeId
];
$orders = $order->getAll($filters);
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold pt-4 pl-2">Order List</h1>
    <p class="text-gray-600 text-sm md:text-base pl-2">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="hover:text-blue-600">Dashboard</a> > Order List
    </p>
</div>

<div class="admin-card mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
        <form method="GET" action="" class="w-full md:w-auto flex flex-col md:flex-row gap-3 items-center">
            
            <!-- Status Filter -->
            <div class="relative">
                <select name="status" onchange="this.form.submit()" 
                        class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white cursor-pointer h-10 min-w-[150px]">
                    <option value="">All Status</option>
                    <?php 
                    $statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
                    foreach($statuses as $val => $label): 
                        $selected = (isset($_GET['status']) && $_GET['status'] === $val) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $val; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Input -->
            <div class="relative">
                <input type="text" 
                       id="searchInput"
                       name="search"
                       placeholder="Search order..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>"
                       class="border border-gray-300 rounded px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64 h-10"
                       onkeypress="if(event.key === 'Enter'){event.preventDefault(); return false;}">
            </div>
        </form>
        <div class="flex items-center gap-2 mt-4 md:mt-0 w-full md:w-auto overflow-x-auto print:hidden">
            <button type="button" onclick="bulkPrintQR()" class="bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition text-sm flex items-center hidden whitespace-nowrap" id="bulkPrintBtn">
                <i class="fas fa-print mr-1"></i> Print QR
            </button>
            <button type="button" onclick="bulkDownloadQR()" class="bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition text-sm flex items-center hidden whitespace-nowrap" id="bulkDownloadBtn">
                <i class="fas fa-download mr-1"></i> Download QR
            </button>
            <a href="<?php echo url('admin/orders/export_csv.php'); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition flex items-center whitespace-nowrap">
                <i class="fas fa-file-export mr-2"></i>Export all
            </a>
        </div>
    </div>
</div>

<div class="admin-card overflow-x-auto admin-card-list">
    <table class="admin-table">
        <thead class="list-header">
            <tr>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="row_number">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="selectAllOrders" onchange="toggleAllOrders(this)" class="cursor-pointer rounded border-gray-300" onclick="event.stopPropagation()">
                        <span>NO</span>
                        <div class="flex flex-col">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th>Product</th>
                <th>Customer</th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="order_number">
                    <div class="flex items-center justify-between">
                        <span>Order ID</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="total_amount">
                    <div class="flex items-center justify-between">
                        <span>Price</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="total_quantity">
                    <div class="flex items-center justify-between">
                        <span>Quantity</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="payment_status">
                    <div class="flex items-center justify-between">
                        <span>Payment</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th class="sortable cursor-pointer hover:bg-gray-100" data-column="order_status">
                    <div class="flex items-center justify-between">
                        <span>Status</span>
                        <div class="flex flex-col ml-1">
                            <i class="fas fa-caret-up text-gray-400 -mb-1" style="font-size: 0.75rem;"></i>
                            <i class="fas fa-caret-down text-gray-400" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </th>
                <th>Tracking</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $index => $item): 
                // Get product image with fallback using helper function
                $productImage = !empty($item['product_image']) ? getImageUrl($item['product_image']) : 'https://placehold.co/50';
                
                // Construct QR Code Text Data
                $addr = is_array($item['shipping_address']) ? $item['shipping_address'] : json_decode($item['shipping_address'] ?? '{}', true);
                
                // Compact data to avoid QR overflow
                $line1Parts = array_unique(array_filter([
                    $addr['street'] ?? '',
                    $addr['address_line1'] ?? $addr['address'] ?? '',
                    $addr['address_line2'] ?? ''
                ]));
                $line1 = implode(', ', $line1Parts);
                
                $zip = $addr['zip'] ?? $addr['postal_code'] ?? $addr['pincode'] ?? '';
                $line2Parts = array_unique(array_filter([$addr['city'] ?? '', $addr['state'] ?? '']));
                $line2 = implode(', ', $line2Parts) . ' ' . $zip;
                
                $line3 = trim($addr['country'] ?? '');
                $formattedAddr = mb_strimwidth(implode("\n", array_filter([$line1, $line2, $line3])), 0, 250, "..");
                
                $qrText = "Order ID: " . $item['order_number'] . "\n" .
                          "Customer: " . $item['customer_name'] . "\n" .
                          "Mobile: " . ($item['customer_phone'] ?? 'N/A') . "\n" .
                          "Payment: " . strtoupper($item['payment_method'] ?? 'COD') . " (" . strtoupper($item['payment_status'] ?? 'PENDING') . ")\n" .
                          "Amount: Rs." . number_format($item['total_amount'] ?? 0, 2) . "\n\n" .
                          "Shipping Address:\n" . $formattedAddr;
            ?>
            <tr data-row-number="<?php echo $index + 1; ?>"
                data-customer-email="<?php echo htmlspecialchars($item['customer_email'] ?? ''); ?>"
                data-customer-id="<?php echo htmlspecialchars($item['public_customer_id'] ?? ''); ?>">
                <td>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" class="order-checkbox cursor-pointer rounded border-gray-300" onchange="toggleOrderCheckbox()" value="<?php echo htmlspecialchars($item['order_number']); ?>" data-qrtext="<?php echo htmlspecialchars(base64_encode($qrText), ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo $index + 1; ?></span>
                    </div>
                </td>
                <td>
                    <img src="<?php echo htmlspecialchars($productImage); ?>" 
                         alt="Product" 
                         class="w-12 h-12 object-cover rounded"
                         onerror="this.src='https://placehold.co/50'">
                </td>
                <td>
                    <span class="font-medium"><?php echo htmlspecialchars($item['customer_name']); ?></span>
                </td>
                <td><?php echo htmlspecialchars($item['order_number']); ?></td>
                <td><?php echo format_currency($item['total_amount'], 2, $item['currency'] ?? 'INR'); ?></td>
                <td><?php echo $item['total_quantity'] ?? 0; ?></td>
                <td>
                    <?php 
                    $payStatus = $item['payment_status'] ?? 'pending';
                    $isPaid = ($payStatus === 'paid');
                    $isPayPending = ($payStatus === 'pending');
                    $payBg = $isPaid ? '#d1fae5' : ($isPayPending ? '#fef3c7' : '#fee2e2');
                    $payText = $isPaid ? '#065f46' : ($isPayPending ? '#92400e' : '#991b1b');
                    ?>
                    <span class="px-2 py-1 rounded shadow-sm" style="background-color: <?php echo $payBg; ?>; color: <?php echo $payText; ?>; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                        <?php echo ucfirst($payStatus); ?>
                    </span>
                </td>
                <td>
                    <?php 
                    $orderStatus = $item['order_status'] ?? 'pending';
                    
                    // define colors for each status
                    $statusColors = [
                        'pending'    => ['bg' => '#fef3c7', 'text' => '#92400e'], // Yellow
                        'processing' => ['bg' => '#dbeafe', 'text' => '#1e40af'], // Blue
                        'shipped'    => ['bg' => '#f3e8ff', 'text' => '#6b21a8'], // Purple
                        'delivered'  => ['bg' => '#d1fae5', 'text' => '#065f46'], // Green
                        'success'    => ['bg' => '#d1fae5', 'text' => '#065f46'], // Green (alias)
                        'cancelled'  => ['bg' => '#fee2e2', 'text' => '#991b1b'], // Red
                        'cancel'     => ['bg' => '#fee2e2', 'text' => '#991b1b'], // Red (alias)
                    ];
                    
                    $colors = $statusColors[$orderStatus] ?? ['bg' => '#f3f4f6', 'text' => '#374151']; // Default Gray
                    $selectBg = $colors['bg'];
                    $selectText = $colors['text'];
                    ?>
                    <select
                        class="order-status-select px-2 py-1 rounded border shadow-sm"
                        style="background-color: <?php echo $selectBg; ?>; color: <?php echo $selectText; ?>; font-size: 0.75rem; font-weight: 600;"
                        data-order-id="<?php echo $item['id']; ?>"
                    >
                        <?php
                        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                        foreach ($statuses as $status) {
                            $selected = ($orderStatus === $status) ? 'selected' : '';
                            echo "<option value='{$status}' {$selected}>" . ucfirst($status) . "</option>";
                        }
                        ?>
                    </select>
                </td>

                <td>
                    <button class="text-blue-500 hover:text-blue-700">Tracking</button>
                </td>
                <td>
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo url('admin/orders/detail.php?order_number=' . urlencode($item['order_number'])); ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo url('admin/orders/edit.php?order_number=' . urlencode($item['order_number'])); ?>" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" onclick="openQRModal(this.dataset.qrtext, '<?php echo htmlspecialchars($item['order_number']); ?>')" data-qrtext="<?php echo htmlspecialchars(base64_encode($qrText), ENT_QUOTES, 'UTF-8'); ?>" class="text-purple-500 hover:text-purple-700" title="QR Code Options">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="noDataMessage" class="hidden">
                <td colspan="10" class="text-center py-8 text-gray-500">
                    <i class="fas fa-search mb-2 text-2xl block"></i>
                    No data match
                </td>
            </tr>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>

<script>
// BASE_URL is already declared in admin-header.php, so check if it exists first
if (typeof window.BASE_URL === 'undefined') {
    window.BASE_URL = '<?php echo $baseUrl; ?>';
}

window.deleteOrder = function(id) {
    showConfirmModal('Are you sure you want to delete this order? This action cannot be undone.', function() {
        fetch(window.BASE_URL + '/admin/api/orders.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showConfirmModal(data.message || 'Error deleting order', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Error' });
            }
        })
        .catch(error => {
            showConfirmModal('An error occurred while deleting the order.', function() {
                closeConfirmModal();
            }, { isError: true, title: 'Error' });
        });
    });
};

// Select toggling
window.toggleAllOrders = function(checkbox) {
    var checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = checkbox.checked;
        }
    });
    window.toggleOrderButtonsVisibility();
};

window.toggleOrderButtonsVisibility = function() {
    var checkboxes = document.querySelectorAll('.order-checkbox:checked');
    var bulkPrintBtn = document.getElementById('bulkPrintBtn');
    var bulkDownloadBtn = document.getElementById('bulkDownloadBtn');
    if (checkboxes.length > 0) {
        if (bulkPrintBtn) bulkPrintBtn.classList.remove('hidden');
        if (bulkDownloadBtn) bulkDownloadBtn.classList.remove('hidden');
    } else {
        if (bulkPrintBtn) bulkPrintBtn.classList.add('hidden');
        if (bulkDownloadBtn) bulkDownloadBtn.classList.add('hidden');
    }
};

// Single QR Option
window.openQRModal = function(qrtextBase64, orderNum) {
    var qrtext = "";
    try {
        // More robust UTF-8 to Latin1 conversion for qrcode.js compatibility
        qrtext = decodeURIComponent(escape(atob(qrtextBase64)));
    } catch(e) {
        console.error("Decoding error:", e);
        // Fallback
        qrtext = atob(qrtextBase64);
    }
    var modal = document.getElementById('qrModal');
    var qrContainer = document.getElementById('qrCodeContainer');
    var title = document.getElementById('qrModalTitle');
    
    if (title) title.innerText = "Order: " + orderNum;
    if (qrContainer) qrContainer.innerHTML = '';
    
    // Generate QR using qrcode.js
    try {
        if (typeof QRCode === 'undefined' && typeof window.QRCode === 'undefined') {
            throw new Error('QRCode library not loaded. Please refresh the page or check your internet connection.');
        }
        var QRCodeClass = typeof QRCode !== 'undefined' ? QRCode : window.QRCode;
        var qrcode = new QRCodeClass(qrContainer, {
            text: qrtext,
            width: 280,
            height: 280,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCodeClass.CorrectLevel.M
        });
    } catch (e) {
        console.error('QRCode Error:', e);
        if (qrContainer) qrContainer.innerHTML = '<p class="text-red-500 text-xs text-center py-4">Error generating QR: ' + e.message + '</p>';
    }
    
    // Store variables for download/print
    if (modal) {
        modal.dataset.orderNum = orderNum;
        modal.dataset.qrtext = qrtextBase64;
        modal.classList.remove('hidden');
    }
};

window.closeQRModal = function() {
    var modal = document.getElementById('qrModal');
    if (modal) modal.classList.add('hidden');
};

window.downloadSingleQR = function() {
    var modal = document.getElementById('qrModal');
    var canvas = document.querySelector('#qrCodeContainer canvas');
    if (!canvas || !modal) return;
    var a = document.createElement('a');
    a.href = canvas.toDataURL("image/png");
    a.download = `QR_Order_${modal.dataset.orderNum}.png`;
    a.click();
};

window.printSingleQR = function() {
    var qrContainer = document.getElementById('qrCodeContainer');
    var modal = document.getElementById('qrModal');
    if (!qrContainer || !modal) return;
    var qrContainerHTML = qrContainer.innerHTML;
    var orderNum = modal.dataset.orderNum;
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html><head><title>Print QR</title>
        <style>body{text-align:center; font-family:sans-serif; margin-top:50px;}</style>
        </head><body>
        <h2>Order: ${orderNum}</h2>
        <div style="display:inline-block; border:1px solid #ccc; padding:20px;">
        ${qrContainerHTML}
        </div>
        <script>setTimeout(() => { window.print(); window.close(); }, 500);<\/script>
        </body></html>
    `);
    printWindow.document.close();
};

// Bulk QR functions
window.generateBulkQRHTML = function(callback) {
    var checked = document.querySelectorAll('.order-checkbox:checked');
    if(checked.length === 0) return;
    
    var hiddenDiv = document.createElement('div');
    hiddenDiv.style.display = 'none';
    document.body.appendChild(hiddenDiv);
    
    var htmlContent = '';
    var processed = 0;
    
    checked.forEach(cb => {
        var tempDiv = document.createElement('div');
        hiddenDiv.appendChild(tempDiv);
        try {
            var bulkText = decodeURIComponent(escape(atob(cb.dataset.qrtext)));
            var QRCodeClass = typeof QRCode !== 'undefined' ? QRCode : window.QRCode;
            new QRCodeClass(tempDiv, {
                text: bulkText,
                width: 300, height: 300,
                correctLevel: QRCodeClass.CorrectLevel.L
            });
        } catch (e) {
            console.error('Bulk QR Error for ' + cb.value, e);
        }
        
        setTimeout(() => {
            var canvas = tempDiv.querySelector('canvas');
            if(canvas) {
                var dataUrl = canvas.toDataURL("image/png");
                htmlContent += `
                <div class="qr-page">
                    <h2>Order: ${cb.value}</h2>
                    <img src="${dataUrl}" />
                </div>`;
            }
            processed++;
            if(processed === checked.length) {
                setTimeout(() => {
                    document.body.removeChild(hiddenDiv);
                    callback(htmlContent);
                }, 100);
            }
        }, 500);
    });
};

window.bulkPrintQR = function() {
    window.generateBulkQRHTML(function(htmlContent) {
        var printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html><head><title>Bulk Print QR</title>
            <style>
                @page { size: auto; margin: 0mm; }
                body { margin: 0; padding: 0; font-family: sans-serif; }
                .qr-page { 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    height: 100vh;
                    page-break-after: always;
                    box-sizing: border-box;
                    padding: 40px;
                }
                .qr-page:last-child { 
                    page-break-after: auto; 
                }
                img { width: 300px; height: 300px; margin-top: 10px; }
                h2 { font-size: 24px; margin: 0; text-align: center; }
            </style>
            </head><body>
            ${htmlContent.replace(/inline-block/g, 'flex').replace(/margin:20px; border:1px solid #ddd; padding:15px;/g, '')}
            <script>setTimeout(() => { window.print(); window.close(); }, 500);<\/script>
            </body></html>
        `);
        printWindow.document.close();
    });
};

window.bulkDownloadQR = function() {
    window.generateBulkQRHTML(function(htmlContent) {
        var element = document.createElement('div');
        element.style.width = '210mm';
        element.innerHTML = `
            <style>
                .qr-page { 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    min-height: 290mm;
                    page-break-after: always;
                    background: white;
                    width: 100%;
                }
                .qr-page:last-child {
                    page-break-after: avoid;
                }
                img { width: 400px; height: 400px; margin-top: 20px; }
                h2 { font-size: 36px; margin-bottom: 20px; font-family: sans-serif; text-align:center; }
            </style>
            ${htmlContent.replace(/inline-block/g, 'flex').replace(/margin:20px; border:1px solid #ddd; padding:15px;/g, '')}
        `;
        
        var opt = {
            margin:       0,
            filename:     'Bulk_Orders_QR.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        if (typeof html2pdf !== 'undefined') {
            html2pdf().set(opt).from(element).save();
        } else {
            console.error('html2pdf library not loaded');
        }
    });
};

window.updateOrderStatus = function(e) {
    if(!e.target.classList.contains('order-status-select')) return;

    var select = e.target;
    var orderId = select.dataset.orderId;
    var newStatus = select.value;

    var statusColors = {
        'pending':    { bg: '#fef3c7', text: '#92400e' },
        'processing': { bg: '#dbeafe', text: '#1e40af' },
        'shipped':    { bg: '#f3e8ff', text: '#6b21a8' },
        'delivered':  { bg: '#d1fae5', text: '#065f46' },
        'success':    { bg: '#d1fae5', text: '#065f46' },
        'cancelled':  { bg: '#fee2e2', text: '#991b1b' },
        'cancel':     { bg: '#fee2e2', text: '#991b1b' }
    };

    var color = statusColors[newStatus] || { bg: '#f3f4f6', text: '#374151' };
    
    select.classList.remove('bg-green-100', 'text-green-800', 'bg-orange-100', 'text-orange-800', 'bg-gray-100', 'text-gray-800');
    select.style.backgroundColor = color.bg;
    select.style.color = color.text;

    fetch(window.BASE_URL + '/admin/api/orders.php', {
        method: 'PUT',
        headers:{ 'Content-Type':'application/json' },
        body:JSON.stringify({ id: orderId, order_status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            console.log('Order status updated successfully');
        } else {
            console.log('Failed to update order status: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Something went wrong:', err);
    });
};

document.addEventListener('change', window.updateOrderStatus);

window.initOrderSort = function() {
    var table = document.querySelector('.admin-table');
    if (!table || table.dataset.sortInitialized) return;
    table.dataset.sortInitialized = 'true';

    var thead = table.querySelector('thead');
    if (!thead) return;

    thead.addEventListener('click', function(e) {
        var header = e.target.closest('th.sortable');
        if (!header) return;

        var column = header.dataset.column;
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr:not(#noDataMessage)'));
        if (rows.length <= 1) return;

        var currentDir = header.dataset.direction || 'none';
        var direction = currentDir === 'asc' ? 'desc' : 'asc';

        table.querySelectorAll('th.sortable').forEach(h => {
            h.dataset.direction = '';
            h.querySelectorAll('i').forEach(icon => {
                icon.classList.remove('text-blue-600');
                icon.classList.add('text-gray-400');
            });
        });

        header.dataset.direction = direction;
        var upArrow = header.querySelector('.fa-caret-up');
        var downArrow = header.querySelector('.fa-caret-down');
        if (direction === 'asc' && upArrow) {
            upArrow.classList.replace('text-gray-400', 'text-blue-600');
        } else if (direction === 'desc' && downArrow) {
            downArrow.classList.replace('text-gray-400', 'text-blue-600');
        }

        var cellIndex = Array.from(header.parentElement.children).indexOf(header);
        var collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        
        rows.sort((a, b) => {
            var aVal, bVal;
            var aCell = a.children[cellIndex];
            var bCell = b.children[cellIndex];

            if (column === 'row_number') {
                aVal = parseInt(a.dataset.rowNumber) || 0;
                bVal = parseInt(b.dataset.rowNumber) || 0;
            } else if (column === 'total_amount' || column === 'total_quantity') {
                aVal = parseFloat(aCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                bVal = parseFloat(bCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            } else if (column === 'order_status') {
                aVal = (aCell.querySelector('select')?.value || '').toLowerCase();
                bVal = (bCell.querySelector('select')?.value || '').toLowerCase();
            } else {
                aVal = aCell.textContent.trim();
                bVal = bCell.textContent.trim();
                return direction === 'asc' ? collator.compare(aVal, bVal) : collator.compare(bVal, aVal);
            }

            if (aVal < bVal) return direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        var fragment = document.createDocumentFragment();
        rows.forEach(row => fragment.appendChild(row));
        
        var noData = document.getElementById('noDataMessage');
        if (noData) fragment.appendChild(noData);
        
        tbody.appendChild(fragment);
    });
};

window.initOrderSearch = function() {
    var searchInput = document.getElementById('searchInput');
    var tableRows = document.querySelectorAll('tbody tr');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            var query = e.target.value.toLowerCase().trim();
            var visibleCount = 0;
            
            tableRows.forEach(row => {
                if (row.id === 'noDataMessage') return;
                
                var text = row.innerText.toLowerCase();
                var email = (row.dataset.customerEmail || '').toLowerCase();
                var customerId = (row.dataset.customerId || '').toLowerCase();
                
                if (query === '' || text.includes(query) || email.includes(query) || customerId.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            var noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) {
                if (visibleCount === 0) {
                    noDataMessage.classList.remove('hidden');
                } else {
                    noDataMessage.classList.add('hidden');
                }
            }
            
            var newUrl = new URL(window.location);
            if (query) {
                newUrl.searchParams.set('search', query);
            } else {
                newUrl.searchParams.delete('search');
            }
            window.history.replaceState({}, '', newUrl);
        });
    }
};

window.initOrderList = function() {
    window.initOrderSort();
    window.initOrderSearch();
};

window.initOrderList();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

