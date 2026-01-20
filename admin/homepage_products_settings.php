<?php
ob_start(); // Buffer output to prevent headers already sent errors and dirty JSON
// Initialize dependencies and logic BEFORE any output
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth(); // Ensure session
$db = Database::getInstance();
$productObj = new Product();
$baseUrl = getBaseUrl();
$success = '';

// Auto-migration: Ensure tables can store the external product_id (which might be BIGINT)
try {
    $db->execute("ALTER TABLE section_best_selling_products MODIFY product_id BIGINT");
    $db->execute("ALTER TABLE section_trending_products MODIFY product_id BIGINT");
} catch (Exception $e) { 
    // Ignore errors if column already modified or valid
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bestSellingIds = $_POST['best_selling_ids'] ?? '';
    $trendingIds = $_POST['trending_ids'] ?? '';
    
    // Process Best Selling
    $db->execute("DELETE FROM section_best_selling_products"); 
    if (!empty($bestSellingIds)) {
        // Allow potentially non-numeric IDs if product_id is string? The screenshot showed numbers. 
        // But to be safe, filter empty only.
        $ids = array_filter(explode(',', $bestSellingIds));
        $order = 1;
        foreach ($ids as $id) {
            $id = trim($id);
            if($id) $db->execute("INSERT INTO section_best_selling_products (product_id, sort_order) VALUES (?, ?)", [$id, $order++]);
        }
    }

    // Process Trending
    $db->execute("DELETE FROM section_trending_products"); 
    if (!empty($trendingIds)) {
        $ids = array_filter(explode(',', $trendingIds));
        $order = 1;
        foreach ($ids as $id) {
            $id = trim($id);
            if($id) $db->execute("INSERT INTO section_trending_products (product_id, sort_order) VALUES (?, ?)", [$id, $order++]);
        }
    }
    
    $successMsg = "Homepage product settings updated successfully!";
    
    // Handle AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        ob_end_clean(); // Clean all buffered output (includes includes/headers)
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $successMsg]);
        exit;
    }
    
    // Standard POST - Redirect with Session Flash
    $_SESSION['flash_success'] = $successMsg;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Check for success message from Session Flash
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch Selected Products JOINING on product_id
$queryBS = "SELECT p.*, h.sort_order FROM products p 
            JOIN section_best_selling_products h ON p.product_id = h.product_id 
            ORDER BY h.sort_order ASC";
$bestSellingProducts = $db->fetchAll($queryBS);

$queryTR = "SELECT p.*, h.sort_order FROM products p 
            JOIN section_trending_products h ON p.product_id = h.product_id 
            ORDER BY h.sort_order ASC";
$trendingProducts = $db->fetchAll($queryTR);

// Create ID strings for JS state - using product_id
$bestSellingIdsStr = implode(',', array_column($bestSellingProducts, 'product_id'));
$trendingIdsStr = implode(',', array_column($trendingProducts, 'product_id'));

// Fetch ALL active products including product_id column
$allProducts = $db->fetchAll("SELECT id, product_id, name, sku, featured_image FROM products WHERE status = 'active' ORDER BY name ASC");
$jsProducts = [];
foreach($allProducts as $p) {
    $img = getImageUrl($p['featured_image']);
    // Map 'id' in JS to the 'product_id' column value!
    $jsProducts[] = [
        'id' => $p['product_id'], 
        'system_id' => $p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'],
        'image' => $img,
        'search_text' => strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['product_id'])
    ];
}

// START HTML OUTPUT
$pageTitle = 'Homepage Products';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Homepage Product Sections</h1>
        <div class="flex gap-3">
            <button type="button" onclick="window.location.href='index.php'" class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 bg-white text-gray-700 font-medium transition-colors">
                Cancel
            </button>
            <button type="submit" form="settingsForm" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>

    <!-- Global Message Container -->
    <div id="global_message_container">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" id="settingsForm">
        
        <!-- Best Selling Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Best Selling Section</h2>
                <p class="text-sm text-gray-500">Select specific products to display in the Best Selling slider.</p>
            </div>
            <div class="p-6">
                <input type="hidden" name="best_selling_ids" id="best_selling_ids" value="<?php echo htmlspecialchars($bestSellingIdsStr); ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Search or Select Product</label>
                    
                    <!-- Unified Combo Box -->
                    <div class="relative product-combobox" id="combo_best_selling">
                        <input type="text" 
                               class="w-full border rounded-lg p-2 pl-9 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm cursor-text" 
                               placeholder="Start typing to search or click to view list..."
                               autocomplete="off">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 text-xs cursor-pointer toggle-icon"></i>
                        
                        <div class="combobox-options absolute z-10 w-full bg-white shadow-xl rounded-b-lg border border-gray-200 mt-1 max-h-60 overflow-y-auto hidden">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <div id="msg_best_selling" class="hidden"></div>
                
                <div class="bg-gray-50 rounded-lg p-4 min-h-[100px] max-h-96 overflow-y-auto">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Selected Products</h3>
                    <ul id="list_best_selling" class="space-y-2">
                        <?php foreach ($bestSellingProducts as $p): ?>
                        <li class="bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm" data-id="<?php echo $p['product_id']; ?>">
                            <div class="flex items-center gap-3">
                                <?php $imgUrl = getImageUrl($p['featured_image']); ?>
                                <?php if($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-10 h-10 object-cover rounded">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>
                                <?php endif; ?>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($p['name'] ?? ''); ?></span>
                                <span class="text-xs text-gray-500">(Product ID: <?php echo $p['product_id']; ?>)</span>
                            </div>
                            <button type="button" onclick="removeItem('best_selling', <?php echo $p['product_id']; ?>)" class="text-red-500 hover:text-red-700 p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($bestSellingProducts)): ?>
                        <li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default best sellers will be shown.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Trending Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Trending Section</h2>
                <p class="text-sm text-gray-500">Select specific products to display in the Trending slider.</p>
            </div>
            <div class="p-6">
                <input type="hidden" name="trending_ids" id="trending_ids" value="<?php echo htmlspecialchars($trendingIdsStr); ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Search or Select Product</label>
                    
                    <!-- Unified Combo Box -->
                    <div class="relative product-combobox" id="combo_trending">
                        <input type="text" 
                               class="w-full border rounded-lg p-2 pl-9 focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm cursor-text" 
                               placeholder="Start typing to search or click to view list..."
                               autocomplete="off">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 text-xs cursor-pointer toggle-icon"></i>
                        
                        <div class="combobox-options absolute z-10 w-full bg-white shadow-xl rounded-b-lg border border-gray-200 mt-1 max-h-60 overflow-y-auto hidden">
                            <!-- Items populated by JS -->
                        </div>
                    </div>
                </div>

                <div id="msg_trending" class="hidden"></div>
                
                <div class="bg-gray-50 rounded-lg p-4 min-h-[100px] max-h-96 overflow-y-auto">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Selected Products</h3>
                    <ul id="list_trending" class="space-y-2">
                        <?php foreach ($trendingProducts as $p): ?>
                        <li class="bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm" data-id="<?php echo $p['product_id']; ?>">
                            <div class="flex items-center gap-3">
                                <?php $imgUrl = getImageUrl($p['featured_image']); ?>
                                <?php if($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="w-10 h-10 object-cover rounded">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>
                                <?php endif; ?>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($p['name'] ?? ''); ?></span>
                                <span class="text-xs text-gray-500">(Product ID: <?php echo $p['product_id']; ?>)</span>
                            </div>
                            <button type="button" onclick="removeItem('trending', <?php echo $p['product_id']; ?>)" class="text-red-500 hover:text-red-700 p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($trendingProducts)): ?>
                        <li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default trending items will be shown.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>


    </form>
</div>

<script>
// Available Products from PHP
const availableProducts = <?php echo json_encode($jsProducts); ?>;

const collections = {
    best_selling: new Set(<?php echo $bestSellingIdsStr ? '[' . $bestSellingIdsStr . ']' : '[]'; ?>),
    trending: new Set(<?php echo $trendingIdsStr ? '[' . $trendingIdsStr . ']' : '[]'; ?>)
};

// Initialize Combo Box for a section
function initComboBox(key) {
    const container = document.getElementById('combo_' + key);
    const input = container.querySelector('input');
    const dropdown = container.querySelector('.combobox-options');
    const toggleIcon = container.querySelector('.toggle-icon');
    
    // Render list function
    function renderList(filterText = '') {
        const term = filterText.toLowerCase();
        // Filter products
        const filtered = availableProducts.filter(p => 
            term === '' || p.search_text.includes(term)
        );
        
        if (filtered.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">No products found</div>';
        } else {
            dropdown.innerHTML = filtered.map(p => `
                <div class="p-2 hover:bg-blue-50 cursor-pointer border-b flex items-center gap-3 transition" 
                     onclick="selectItem('${key}', ${p.id})">
                    <div class="w-8 h-8 flex-shrink-0 bg-gray-200 rounded overflow-hidden">
                         ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover">` : '<i class="fas fa-image text-gray-400 flex items-center justify-center h-full w-full"></i>'}
                    </div>
                    <div>
                        <div class="font-medium text-sm text-gray-800">${p.name}</div>
                        <div class="text-xs text-gray-500">SKU: ${p.sku ? p.sku : 'N/A'} (Product ID: ${p.id})</div>
                    </div>
                </div>
            `).join('');
        }
    }
    
    // Toggle Dropdown
    function showDropdown() {
        renderList(input.value);
        dropdown.classList.remove('hidden');
    }
    
    function hideDropdown() {
        // Small delay to allow click event to register
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    }
    
    // Events
    input.addEventListener('focus', showDropdown);
    input.addEventListener('input', (e) => renderList(e.target.value));
    input.addEventListener('blur', hideDropdown);
    toggleIcon.addEventListener('click', () => {
        if (dropdown.classList.contains('hidden')) {
            input.focus();
        } else {
            hideDropdown(); // Wait, this needs immediate hide if clicked icon? 
            // Better to let focus handle it, but for icon click:
            dropdown.classList.contains('hidden') ? input.focus() : dropdown.classList.add('hidden');
        }
    });
}

// Global selection handler
function selectItem(key, id) {
    const p = availableProducts.find(prod => prod.id === id);
    if (p) {
        addItem(key, p.id, p.name, p.image);
        // Clear input
        document.querySelector(`#combo_${key} input`).value = '';
    }
}

function addItem(key, id, name, image) {
    id = parseInt(id);
    
    if (collections[key].has(id)) {
        showMessage(key, 'Product already added.', 'error');
        return;
    }
    
    collections[key].add(id);
    
    const list = document.getElementById('list_' + key);
    const emptyMsg = list.querySelector('.empty-msg');
    if (emptyMsg) emptyMsg.remove();
    
    const li = document.createElement('li');
    li.className = 'bg-white border border-gray-200 rounded p-2 flex items-center justify-between shadow-sm animate-fade-in';
    li.dataset.id = id;
    
    const imgHtml = image 
        ? `<img src="${image}" class="w-10 h-10 object-cover rounded">`
        : `<div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>`;
        
    li.innerHTML = `
        <div class="flex items-center gap-3">
            ${imgHtml}
            <span class="font-medium text-gray-800">${name}</span>
            <span class="text-xs text-gray-500">(Product ID: ${id})</span>
        </div>
        <button type="button" onclick="removeItem('${key}', ${id})" class="text-red-500 hover:text-red-700 p-1">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    list.appendChild(li);
    updateHiddenInput(key);
    
    // showMessage(key, 'Product added successfully', 'success'); // Disabled per request
}

function removeItem(key, id) {
    collections[key].delete(id);
    
    const list = document.getElementById('list_' + key);
    const item = list.querySelector(`li[data-id="${id}"]`);
    if (item) item.remove();
    
    if (collections[key].size === 0) {
        list.innerHTML = '<li class="text-center text-gray-400 py-4 italic empty-msg">No products selected. Default products will be shown.</li>';
    }
    updateHiddenInput(key);
}

function updateHiddenInput(key) {
    const list = document.getElementById('list_' + key);
    const items = list.querySelectorAll('li[data-id]');
    const ids = Array.from(items).map(li => li.dataset.id);
    
    document.getElementById(key + '_ids').value = ids.join(',');
}

function showMessage(key, msg, type='error') {
    const el = document.getElementById('msg_' + key);
    if(!el) return;
    
    const colorClass = type === 'error' ? 'text-red-500 bg-red-50 border-red-200' : 'text-green-500 bg-green-50 border-green-200';
    
    el.className = `mb-3 px-3 py-2 rounded border text-sm ${colorClass}`;
    el.innerText = msg;
    el.classList.remove('hidden');
    
    setTimeout(() => {
        el.classList.add('hidden');
    }, 3000);
}

// Global Message Handler
function showGlobalMessage(msg) {
    const el = document.getElementById('global_message_container');
    el.innerHTML = `<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-fade-in">${msg}</div>`;
    
    setTimeout(() => {
        el.innerHTML = '';
    }, 4000); 
}

// AJAX Form Submit
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = document.querySelector('button[form="settingsForm"]');
    let originalHtml = '';
    
    if (submitBtn) {
        originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
    }
    
    fetch('<?php echo basename($_SERVER['PHP_SELF']); ?>', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text().then(text => {
            try { return JSON.parse(text); }
            catch (e) { throw new Error('Server Error'); }
        });
    })
    .then(data => {
        if (data.success) { showGlobalMessage(data.message); }
    })
    .catch(err => {
        console.error(err);
        const el = document.getElementById('global_message_container');
        el.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 animate-fade-in">Error saving: ${err.message}</div>`;
        setTimeout(() => { el.innerHTML = ''; }, 5000);
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }
    });
});

// Init
initComboBox('best_selling');
initComboBox('trending');

// Hide PHP message if present
// Hide PHP message if present and clean URL
document.addEventListener('DOMContentLoaded', function() {
    const phpMsg = document.querySelector('#global_message_container .bg-green-100');
    if (phpMsg) { 
        setTimeout(() => { 
            phpMsg.style.transition = "opacity 0.5s ease";
            phpMsg.style.opacity = "0";
            setTimeout(() => phpMsg.remove(), 500);
        }, 3000); 
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
