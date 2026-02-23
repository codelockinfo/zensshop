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
                          "Amount: " . format_currency($item['total_amount'] ?? 0, 2, $item['currency'] ?? 'INR') . "\n\n" .
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

<script>
// BASE_URL is already declared in admin-header.php, so check if it exists first
if (typeof BASE_URL === 'undefined') {
    const BASE_URL = '<?php echo $baseUrl; ?>';
}
function deleteOrder(id) {
    showConfirmModal('Are you sure you want to delete this order? This action cannot be undone.', function() {
        fetch(BASE_URL + '/admin/api/orders.php', {
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
}

// Select toggling
function toggleAllOrders(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = checkbox.checked;
        }
    });
    toggleOrderCheckbox();
}

function toggleOrderCheckbox() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const bulkPrintBtn = document.getElementById('bulkPrintBtn');
    const bulkDownloadBtn = document.getElementById('bulkDownloadBtn');
    if (checkboxes.length > 0) {
        bulkPrintBtn.classList.remove('hidden');
        bulkDownloadBtn.classList.remove('hidden');
    } else {
        bulkPrintBtn.classList.add('hidden');
        bulkDownloadBtn.classList.add('hidden');
    }
}

// Single QR Option
function openQRModal(qrtextBase64, orderNum) {
    let qrtext = "";
    try {
        // More robust UTF-8 to Latin1 conversion for qrcode.js compatibility
        qrtext = decodeURIComponent(escape(atob(qrtextBase64)));
    } catch(e) {
        console.error("Decoding error:", e);
        // Fallback
        qrtext = atob(qrtextBase64);
    }
    const modal = document.getElementById('qrModal');
    const qrContainer = document.getElementById('qrCodeContainer');
    const title = document.getElementById('qrModalTitle');
    
    title.innerText = "Order: " + orderNum;
    qrContainer.innerHTML = '';
    
    // Generate QR using qrcode.js
    try {
        const qrcode = new QRCode(qrContainer, {
            text: qrtext,
            width: 280,
            height: 280,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
    } catch (e) {
        console.error('QRCode Error:', e);
        qrContainer.innerHTML = '<p class="text-red-500 text-xs">Error generating QR: ' + e.message + ' (Length: ' + qrtext.length + ')</p>';
    }
    
    // Store variables for download/print
    modal.dataset.orderNum = orderNum;
    modal.dataset.qrtext = qrtextBase64;
    modal.classList.remove('hidden');
}

function closeQRModal() {
    document.getElementById('qrModal').classList.add('hidden');
}

function downloadSingleQR() {
    const modal = document.getElementById('qrModal');
    const canvas = document.querySelector('#qrCodeContainer canvas');
    if (!canvas) return;
    const a = document.createElement('a');
    a.href = canvas.toDataURL("image/png");
    a.download = `QR_Order_${modal.dataset.orderNum}.png`;
    a.click();
}

function printSingleQR() {
    const qrContainerHTML = document.getElementById('qrCodeContainer').innerHTML;
    const orderNum = document.getElementById('qrModal').dataset.orderNum;
    
    const printWindow = window.open('', '_blank');
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
}

// Bulk QR functions
function generateBulkQRHTML(callback) {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if(checked.length === 0) return;
    
    const hiddenDiv = document.createElement('div');
    hiddenDiv.style.display = 'none';
    document.body.appendChild(hiddenDiv);
    
    let htmlContent = '';
    let processed = 0;
    
    checked.forEach(cb => {
        const tempDiv = document.createElement('div');
        hiddenDiv.appendChild(tempDiv); // Important: must be in DOM for some QR libs
        try {
            // Bulk decoding
            const bulkText = decodeURIComponent(escape(atob(cb.dataset.qrtext)));

            new QRCode(tempDiv, {
                text: bulkText,
                width: 300, height: 300,
                correctLevel: QRCode.CorrectLevel.L
            });
        } catch (e) {
            console.error('Bulk QR Error for ' + cb.value, e);
        }
        
        setTimeout(() => {
            const canvas = tempDiv.querySelector('canvas');
            if(canvas) {
                const dataUrl = canvas.toDataURL("image/png");
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
        }, 500); // Increased timeout for better reliability
    });
}

function bulkPrintQR() {
    generateBulkQRHTML(function(htmlContent) {
        const printWindow = window.open('', '_blank');
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
}

function bulkDownloadQR() {
    generateBulkQRHTML(function(htmlContent) {
        const element = document.createElement('div');
        element.style.width = '210mm'; // A4 width
        element.innerHTML = `
            <style>
                .qr-page { 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    min-height: 290mm; /* Slightly less than A4 to prevent blank pages */
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
        
        const opt = {
            margin:       0,
            filename:     'Bulk_Orders_QR.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Use html2pdf
        html2pdf().set(opt).from(element).save();
    });
}

</script>

<!-- Add qrcode.js and html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- QR Modal HTML -->
<div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-96 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold" id="qrModalTitle">Order QR</h3>
            <button onclick="closeQRModal()" class="text-gray-500 hover:text-red-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="flex justify-center mb-6" id="qrCodeContainer"></div>
        
        <div class="flex gap-4">
            <button onclick="downloadSingleQR()" class="flex-1 bg-green-500 text-white py-2 rounded shadow hover:bg-green-600 transition flex items-center justify-center">
                <i class="fas fa-download mr-2"></i> Download
            </button>
            <button onclick="printSingleQR()" class="flex-1 bg-blue-500 text-white py-2 rounded shadow hover:bg-blue-600 transition flex items-center justify-center">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('change', function(e){
        if(!e.target.classList.contains('order-status-select')) return;

        const select = e.target;
        const orderId = select.dataset.orderId;
        const newStatus = select.value;

        // Visual feedback: Update styling immediately
        const statusColors = {
            'pending':    { bg: '#fef3c7', text: '#92400e' },
            'processing': { bg: '#dbeafe', text: '#1e40af' },
            'shipped':    { bg: '#f3e8ff', text: '#6b21a8' },
            'delivered':  { bg: '#d1fae5', text: '#065f46' },
            'success':    { bg: '#d1fae5', text: '#065f46' },
            'cancelled':  { bg: '#fee2e2', text: '#991b1b' },
            'cancel':     { bg: '#fee2e2', text: '#991b1b' }
        };

        const color = statusColors[newStatus] || { bg: '#f3f4f6', text: '#374151' };
        
        // Remove old classes that might conflict
        select.classList.remove('bg-green-100', 'text-green-800', 'bg-orange-100', 'text-orange-800', 'bg-gray-100', 'text-gray-800');
        
        // Apply new styles
        select.style.backgroundColor = color.bg;
        select.style.color = color.text;

        fetch(BASE_URL + '/admin/api/orders.php', {
            method: 'PUT',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                id: orderId,
                order_status: newStatus
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                console.log('Order status updated successfully');
                // Optional: Show a small toast notification
            } else {
                console.log('Failed to update order status: ' + (data.message || 'Unknown error'));
                // Revert selection if needed, but for now simple alert is enough
            }
        })
        .catch(err => {
            console.error('Something went wrong:', err);
            console.log('System error while updating status');
        });
    })

// Table sorting functionality
window.initOrderSort = function() {
    const table = document.querySelector('.admin-table');
    if (!table) return;
    const headers = table.querySelectorAll('th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.column;
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle direction
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
                currentSort.column = column;
            }
            
            // Reset all arrows
            headers.forEach(h => {
                const upArrow = h.querySelector('.fa-caret-up');
                const downArrow = h.querySelector('.fa-caret-down');
                if (upArrow) upArrow.classList.remove('text-blue-600');
                if (upArrow) upArrow.classList.add('text-gray-400');
                if (downArrow) downArrow.classList.remove('text-blue-600');
                if (downArrow) downArrow.classList.add('text-gray-400');
            });
            
            // Highlight active arrow
            const upArrow = this.querySelector('.fa-caret-up');
            const downArrow = this.querySelector('.fa-caret-down');
            if (currentSort.direction === 'asc') {
                if (upArrow) {
                    upArrow.classList.remove('text-gray-400');
                    upArrow.classList.add('text-blue-600');
                }
            } else {
                if (downArrow) {
                    downArrow.classList.remove('text-gray-400');
                    downArrow.classList.add('text-blue-600');
                }
            }
            
            // Sort rows
            rows.sort((a, b) => {
                let aVal, bVal;
                
                // Get cell index
                const cellIndex = Array.from(this.parentElement.children).indexOf(this);
                const aCell = a.children[cellIndex];
                const bCell = b.children[cellIndex];
                
                if (column === 'row_number') {
                    // Row number sort
                    aVal = parseInt(a.dataset.rowNumber) || 0;
                    bVal = parseInt(b.dataset.rowNumber) || 0;
                } else if (column === 'total_amount' || column === 'total_quantity') {
                    // Numeric sort
                    aVal = parseFloat(aCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                    bVal = parseFloat(bCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
                } else if (column === 'order_status') {
                    // Status column - get value from select dropdown
                    const aSelect = aCell.querySelector('select');
                    const bSelect = bCell.querySelector('select');
                    aVal = aSelect ? aSelect.value.toLowerCase() : '';
                    bVal = bSelect ? bSelect.value.toLowerCase() : '';
                } else {
                    // Text sort
                    aVal = aCell.textContent.trim().toLowerCase();
                    bVal = bCell.textContent.trim().toLowerCase();
                }
                
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Re-append sorted rows
            rows.forEach(row => {
                if (row.id !== 'noDataMessage') {
                    tbody.appendChild(row);
                }
            });
            
            // Ensure noDataMessage is always at the bottom
            const noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) tbody.appendChild(noDataMessage);
        });
    });
};

document.addEventListener('DOMContentLoaded', window.initOrderSort);
document.addEventListener('adminPageLoaded', window.initOrderSort);
</script>


<script>
window.initOrderSearch = function() {
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('tbody tr');

    // Make search work as user types
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                if (row.id === 'noDataMessage') return;
                
                // Search in all visible text plus hidden attributes
                const text = row.innerText.toLowerCase();
                const email = (row.dataset.customerEmail || '').toLowerCase();
                const customerId = (row.dataset.customerId || '').toLowerCase();
                
                if (query === '' || text.includes(query) || email.includes(query) || customerId.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide "No data match" message
            const noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) {
                if (visibleCount === 0) {
                    noDataMessage.classList.remove('hidden');
                } else {
                    noDataMessage.classList.add('hidden');
                }
            }
            
            // Update URL without reload for bookmarks/refresh
            const newUrl = new URL(window.location);
            if (query) {
                newUrl.searchParams.set('search', query);
            } else {
                newUrl.searchParams.delete('search');
            }
            window.history.replaceState({}, '', newUrl);
        });
    }
};

document.addEventListener('DOMContentLoaded', window.initOrderSearch);
document.addEventListener('adminPageLoaded', window.initOrderSearch);
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

