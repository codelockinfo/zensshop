<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Delhivery.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!function_exists('url')) {
    function url($path = '') {
        $baseUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/');
        $baseUrl = str_replace('/admin/orders', '', $baseUrl);
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$storeId = $_SESSION['store_id'] ?? null;

// Handle AJAX Request for Tab Data (MUST BE BEFORE ANY HTML OUTPUT)
if (isset($_GET['ajax_tab'])) {
    $tab = $_GET['ajax_tab'];
    $orders = [];
    $conditions = "store_id = ?";
    $params = [$storeId];

    switch ($tab) {
        case 'pending_awb':
            $conditions .= " AND tracking_number IS NULL AND order_status = 'confirmed'";
            break;
        case 'ready_to_ship':
            $conditions .= " AND tracking_number IS NOT NULL AND order_status = 'processing'";
            break;
        case 'ready_for_pickup':
            $conditions .= " AND order_status = 'ready_for_pickup'";
            break;
        case 'in_transit':
            $conditions .= " AND order_status = 'shipped'";
            break;
        case 'rto':
            $conditions .= " AND (order_status = 'returned' OR order_status = 'rto')";
            break;
        case 'delivered':
            $conditions .= " AND order_status = 'delivered'";
            break;
        case 'all_shipments':
            $conditions .= " AND tracking_number IS NOT NULL";
            break;
        default:
            $conditions .= " AND order_status = 'processing' AND tracking_number IS NOT NULL";
            break;
    }

    $orders = $db->fetchAll("SELECT * FROM orders WHERE $conditions ORDER BY updated_at DESC", $params);
    
    // Output HTML for table body
    if (empty($orders)) {
        echo '<tr><td colspan="9" class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center"><i class="fas fa-box-open text-3xl text-gray-300"></i></div><p class="font-medium">No shipments found here.</p></div></td></tr>';
    } else {
        foreach ($orders as $order) {
            $shipping = json_decode($order['shipping_address'], true) ?: [];
            $shippingStr = !empty($shipping) ? ($shipping['city'] . ', ' . ($shipping['state'] ?? '')) : ($order['shipping_address_str'] ?? 'N/A');
            $awb = $order['tracking_number'];
            $detailUrl = url('admin/orders/detail.php?order_number=' . urlencode($order['order_number']));
            ?>
            <tr class="hover:bg-gray-50 transition-colors" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo $order['order_number']; ?>" data-awb="<?php echo $awb; ?>">
                <td class="p-4"><input type="checkbox" class="order-checkbox w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer"></td>
                <td class="p-4">
                    <div class="flex flex-col">
                        <a href="<?php echo $detailUrl; ?>" class="font-bold text-gray-900 hover:text-blue-600 text-sm"><?php echo $order['order_number']; ?></a>
                        <span class="text-[11px] font-mono text-gray-700 mt-1"><?php echo $awb ?: '<span class="text-orange-500 italic">No AWB yet</span>'; ?></span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="text-[11px] text-gray-600">
                        <?php echo date('d M, Y', strtotime($order['created_at'])); ?><br>
                        <span class="text-gray-400"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="tracking-status-cell" id="status-<?php echo $awb; ?>">
                        <?php 
                        $statusText = $awb ? 'Manifested' : 'Pending AWB';
                        $statusClass = 'status-manifest';
                        if (!$awb) $statusClass = 'bg-orange-50 text-orange-600 border border-orange-100';
                        ?>
                        <span class="status-pill text-[10px] <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-warehouse text-[10px] text-gray-400"></i>
                            <span class="text-[11px] font-medium text-gray-700">ZENSENTERPRISE (394210)</span>
                        </div>
                        <div class="w-px h-2 bg-gray-300 ml-1.5"></div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-[10px] text-gray-400"></i>
                            <span class="text-[11px] text-gray-600 truncate max-w-[150px]"><?php echo htmlspecialchars($shippingStr); ?></span>
                        </div>
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex items-center gap-2" id="mode-<?php echo $awb; ?>">
                        <i class="fas fa-truck text-xs text-blue-500"></i>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold text-gray-500">SURFACE</span>
                            <span class="text-[9px] text-gray-400">ZONE S4</span>
                        </div>
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex flex-col" id="update-<?php echo $awb; ?>">
                        <span class="text-[11px] text-gray-800 font-medium">--</span>
                        <span class="text-[9px] text-gray-500 italic"><?php echo $awb ? 'Fetch pending...' : 'N/A'; ?></span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex flex-col" id="charge-<?php echo $order['id']; ?>">
                        <span class="text-[11px] text-gray-800 font-medium">--</span>
                        <span class="text-[9px] text-gray-400">Estimating...</span>
                    </div>
                </td>
                <td class="p-4">
                    <span class="text-[10px] font-bold text-gray-500"><?php echo strtoupper($order['payment_method']); ?></span>
                </td>
                <td class="p-4 text-right">
                    <div class="flex justify-end gap-1">
                        <?php if (!$awb): ?>
                            <button onclick="window.location='<?php echo $detailUrl; ?>'" class="px-3 py-1 bg-blue-600 text-white text-[10px] font-bold rounded hover:bg-blue-700">CREATE SHIPMENT</button>
                        <?php else: ?>
                            <button onclick="window.open('<?php echo url('admin/api/delhivery_actions?action=download_label&order_number='.$order['order_number']); ?>')" class="w-8 h-8 flex items-center justify-center text-green-600 hover:bg-green-50 rounded-full transition-colors" title="Print Label"><i class="fas fa-print"></i></button>
                            <button onclick="window.open('https://www.delhivery.com/track/package/<?php echo $awb; ?>')" class="w-8 h-8 flex items-center justify-center text-blue-600 hover:bg-blue-50 rounded-full transition-colors" title="Live Track"><i class="fas fa-search-location"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php
        }
    }
    exit;
}

$pageTitle = 'Logistics Management';
require_once __DIR__ . '/../../includes/admin-header.php';

$orderObj = new Order();
$delhivery = new Delhivery();

$tab = $_GET['tab'] ?? 'ready_to_ship';

// Counts for badges (Main Page Load)
$countPending = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE store_id = ? AND tracking_number IS NULL AND order_status = 'confirmed'", [$storeId])['count'];
$countReady = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE store_id = ? AND tracking_number IS NOT NULL AND order_status = 'processing'", [$storeId])['count'];
$countPickup = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE store_id = ? AND order_status = 'ready_for_pickup'", [$storeId])['count'];
$countAll = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE store_id = ? AND tracking_number IS NOT NULL", [$storeId])['count'];
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
             <i class="fas fa-truck text-orange-600 text-xl"></i>
             <h1 class="text-2xl font-bold">Logistics Dashboard</h1>
        </div>
        <p class="text-gray-600 text-sm pl-7">Manage your Delhivery shipments and pickups</p>
    </div>
    <div class="flex gap-3">
        <button onclick="location.reload()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 text-sm font-semibold">
            <i class="fas fa-sync-alt"></i> Sync Status
        </button>
    </div>
</div>

<!-- Tabs -->
<div class="flex border-b border-gray-200 mb-6 overflow-x-auto" id="logisticsTabs">
    <a href="javascript:void(0)" onclick="loadTabData('pending_awb')" data-tab="pending_awb" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap <?php echo $tab === 'pending_awb' ? 'active border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
        Pending AWB
        <?php if ($countPending > 0): ?>
            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-600 text-[10px] rounded-full font-bold"><?php echo $countPending; ?></span>
        <?php endif; ?>
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('ready_to_ship')" data-tab="ready_to_ship" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap <?php echo $tab === 'ready_to_ship' ? 'active border-orange-600 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
        Ready To Ship
        <?php if ($countReady > 0): ?>
            <span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-600 text-[10px] rounded-full font-bold"><?php echo $countReady; ?></span>
        <?php endif; ?>
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('ready_for_pickup')" data-tab="ready_for_pickup" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap <?php echo $tab === 'ready_for_pickup' ? 'active border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
        Ready For Pickup
        <?php if ($countPickup > 0): ?>
            <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-600 text-[10px] rounded-full font-bold"><?php echo $countPickup; ?></span>
        <?php endif; ?>
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('in_transit')" data-tab="in_transit" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
        In Transit
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('rto')" data-tab="rto" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
        RTO In-Transit
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('delivered')" data-tab="delivered" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
        Delivered
    </a>
    <a href="javascript:void(0)" onclick="loadTabData('all_shipments')" data-tab="all_shipments" class="tab-btn px-6 py-3 border-b-2 font-medium text-sm whitespace-nowrap <?php echo $tab === 'all_shipments' ? 'active border-gray-800 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
        All Shipments
        <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] rounded-full font-bold"><?php echo $countAll; ?></span>
    </a>
</div>

<div class="admin-card overflow-hidden">
    <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
        <div class="flex items-center gap-4">
            <input type="checkbox" id="selectAllOrders" class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer">
            <span class="text-sm font-medium text-gray-700" id="selectedCountText">0 items selected</span>
        </div>
        <div class="flex gap-2" id="bulkActions" style="display: none;">
            <?php if ($tab === 'ready_to_ship'): ?>
            <button onclick="handleBulkAction('request_pickup')" class="bg-orange-600 text-white px-3 py-1.5 rounded text-xs font-semibold hover:bg-orange-700 flex items-center gap-2">
                <i class="fas fa-truck"></i> Request Pickup
            </button>
            <?php endif; ?>
            <button onclick="handleBulkAction('print_labels')" class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-semibold hover:bg-blue-700 flex items-center gap-2">
                <i class="fas fa-print"></i> Print Labels
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left" id="logisticsTable">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-4 w-4"></th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Order ID and AWB</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Manifested Date</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Status</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Pickup and Delivery Address</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Transport Mode</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Last Update</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Est. Charge</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase">Payment</th>
                    <th class="p-4 text-[10px] font-bold text-gray-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200" id="logisticsTableBody">
                <tr id="loadingRow">
                    <td colspan="9" class="p-12 text-center text-gray-500">
                        <div class="flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin text-xl text-blue-500"></i>
                            <span class="font-medium">Loading Dashboard Data...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Initial Load
    const initialTab = '<?php echo $tab; ?>';
    loadTabData(initialTab);
});

async function loadTabData(tab) {
    const tableBody = document.getElementById('logisticsTableBody');
    const tabs = document.querySelectorAll('.tab-btn');
    
    // Update Tab UI
    tabs.forEach(t => {
        t.classList.remove('active', 'border-blue-600', 'text-blue-600', 'border-orange-600', 'text-orange-600', 'border-purple-600', 'text-purple-600', 'border-gray-800', 'text-gray-800');
        t.classList.add('border-transparent', 'text-gray-500');
        
        if (t.dataset.tab === tab) {
            t.classList.add('active');
            t.classList.remove('border-transparent', 'text-gray-500');
            let colorClass = 'border-blue-600 text-blue-600';
            if (tab === 'ready_to_ship') colorClass = 'border-orange-600 text-orange-600';
            if (tab === 'ready_for_pickup') colorClass = 'border-purple-600 text-purple-600';
            if (tab === 'all_shipments') colorClass = 'border-gray-800 text-gray-800';
            
            colorClass.split(' ').forEach(c => t.classList.add(c));
        }
    });

    tableBody.innerHTML = `<tr><td colspan="10" class="p-12 text-center text-gray-500"><div class="flex items-center justify-center gap-2"><i class="fas fa-spinner fa-spin text-xl text-blue-500"></i><span class="font-medium">Loading ${tab.replace(/_/g, ' ')}...</span></div></td></tr>`;

    try {
        // Use window.location.pathname to handle clean URLs correctly
        const baseUrl = window.location.pathname;
        const response = await fetch(`${baseUrl}?ajax_tab=${tab}`);
        if (!response.ok) throw new Error(`Server returned ${response.status}`);
        const html = await response.text();
        
        if (html.trim() === '') throw new Error('Empty response from server');
        
        tableBody.innerHTML = html;
        
        initTableActions();
        startTrackingFetch();
    } catch (e) {
        console.error('Tab loading error:', e);
        tableBody.innerHTML = `<tr><td colspan="10" class="p-12 text-center text-red-500">
            <div class="flex flex-col items-center gap-2">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
                <span>Failed to load data: ${e.message}</span>
                <button onclick="loadTabData('${tab}')" class="mt-2 text-xs bg-gray-100 px-3 py-1 rounded border hover:bg-gray-200">Retry</button>
            </div>
        </td></tr>`;
    }
}

function initTableActions() {
    const selectAllHeader = document.getElementById('selectAllOrders');
    const checkboxes = document.querySelectorAll('.order-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCountText = document.getElementById('selectedCountText');

    if (selectAllHeader) {
        selectAllHeader.checked = false;
        selectAllHeader.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAllHeader.checked);
            updateBulkUI();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            if (!cb.checked) selectAllHeader.checked = false;
            updateBulkUI();
        });
    });

    function updateBulkUI() {
        const checked = document.querySelectorAll('.order-checkbox:checked').length;
        if (bulkActions) bulkActions.style.display = checked > 0 ? 'flex' : 'none';
        if (selectedCountText) selectedCountText.innerText = `${checked} items selected`;
    }
}

function startTrackingFetch() {
    const rows = document.querySelectorAll('tr[data-awb]');
    rows.forEach(async row => {
        const awb = row.dataset.awb;
        if (!awb || awb === 'NULL' || awb === '') return;
        
        const orderNum = row.dataset.orderNumber;
        const statusCell = document.getElementById(`status-${awb}`);
        const updateCell = document.getElementById(`update-${awb}`);
        const modeCell = document.getElementById(`mode-${awb}`);
        
        try {
            const response = await fetch('<?php echo url("admin/api/delhivery_actions"); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'track_shipment', order_number: orderNum })
            });
            const data = await response.json();
            if (data.success && data.data.ShipmentData && data.data.ShipmentData[0]) {
                const ship = data.data.ShipmentData[0].Shipment;
                const status = ship.Status.Status || 'Active';
                const lastScan = ship.Scans ? ship.Scans[0] : null;
                
                let statusClass = 'text-gray-500 bg-gray-100';
                if (status.includes('Delivered')) statusClass = 'status-delivered';
                else if (status.includes('Transit') || status.includes('Picked')) statusClass = 'status-transit';
                else if (status.includes('Pickup')) statusClass = 'status-pickup';
                else if (status.includes('Manifest') || status === 'Ready for pickup') statusClass = 'status-manifest';
                else if (status.includes('Cancel')) statusClass = 'status-cancelled';
                else if (status.includes('RTO') || status.includes('Return')) statusClass = 'status-rto';

                if (statusCell) statusCell.innerHTML = `<span class="status-pill text-[10px] ${statusClass}">${status}</span>`;
                
                // Fetch Estimated Charge
                fetchEstimatedCharge(row.dataset.orderId);

                if (lastScan && updateCell) {
                    const scanTime = new Date(lastScan.ScanDateTime);
                    updateCell.innerHTML = `
                        <span class="text-[11px] text-gray-800 font-medium">${lastScan.Scan}</span>
                        <span class="text-[9px] text-gray-500">${scanTime.toLocaleDateString('en-GB')} ${scanTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    `;
                }

                if (ship.Destination && modeCell) {
                    modeCell.innerHTML = `
                        <i class="fas fa-truck text-xs text-blue-500"></i>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold text-gray-500">${ship.ServiceType || 'SURFACE'}</span>
                            <span class="text-[9px] text-gray-400">${ship.Destination}</span>
                        </div>
                    `;
                }
            } else {
                if (statusCell) statusCell.innerHTML = `<span class="status-pill text-[10px] bg-gray-50 text-gray-400 border border-dashed">PENDING SCAN</span>`;
                if (updateCell) updateCell.innerHTML = `<span class="text-[10px] text-gray-400 italic">Waiting...</span>`;
            }
        } catch (e) {
            if (statusCell) statusCell.innerHTML = `<span class="text-[10px] text-red-500">Error</span>`;
        }
    });
}

async function fetchEstimatedCharge(orderId) {
    const chargeCell = document.getElementById(`charge-${orderId}`);
    if (!chargeCell) return;

    try {
        const response = await fetch('<?php echo url("admin/api/delhivery_actions"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_shipping_charge', order_id: orderId })
        });
        const result = await response.json();
        if (result.success && result.data) {
            // result.data is usually an array from Delhivery shipping_charge API
            const charge = Array.isArray(result.data) ? result.data[0] : result.data;
            const amount = charge.total_amount || charge.total_charge || '--';
            chargeCell.innerHTML = `
                <span class="text-[11px] text-green-700 font-bold">₹${amount}</span>
                <span class="text-[9px] text-gray-400">API Estimate</span>
            `;
        } else {
            chargeCell.innerHTML = `<span class="text-[10px] text-gray-400">N/A</span>`;
        }
    } catch (e) {
        chargeCell.innerHTML = `<span class="text-[10px] text-red-500">Error</span>`;
    }
}

window.handleBulkAction = async (action) => {
    const selectedIds = Array.from(document.querySelectorAll('.order-checkbox:checked')).map(cb => {
        return cb.closest('tr').dataset.orderId;
    });

    if (action === 'request_pickup') {
        if (!confirm(`Do you want to request Delhivery pickup for ${selectedIds.length} orders?`)) return;
        
        try {
            const response = await fetch('<?php echo url("admin/api/delhivery_actions"); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'request_pickup', order_id: selectedIds[0], bulk_ids: selectedIds })
            });
            const result = await response.json();
            if (result.success) {
                alert('Pickup requested successfully!');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            alert('Connection failure');
        }
    } else if (action === 'print_labels') {
        selectedIds.forEach((id, index) => {
            const row = document.querySelector(`tr[data-order-id="${id}"]`);
            if (row) {
                const orderNum = row.dataset.orderNumber;
                setTimeout(() => {
                    window.open(`<?php echo url('admin/api/delhivery_actions?action=download_label&order_number='); ?>${orderNum}`, '_blank');
                }, index * 500);
            }
        });
    }
};
</script>

<style>
.admin-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
}
.status-pill {
    padding: 2px 10px;
    border-radius: 9999px;
    font-weight: 700;
    white-space: nowrap;
    display: inline-block;
}
.status-manifest { color: #4a5568; background: #edf2f7; }
.status-pickup { color: #f6ad55; background: #fffaf0; border: 1px solid #fbd38d; }
.status-transit { color: #4299e1; background: #ebf8ff; }
.status-delivered { color: #48bb78; background: #f0fff4; }
.status-cancelled { color: #f56565; background: #fff5f5; }
.status-rto { color: #805ad5; background: #faf5ff; }
</style>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
