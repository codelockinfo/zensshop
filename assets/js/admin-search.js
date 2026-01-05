/**
 * Admin Header Search Functionality
 * Shows search results popup below the search input
 */

(function() {
    let searchTimeout;
    const searchToggle = document.getElementById('adminSearchToggle');
    const searchDropdown = document.getElementById('adminSearchDropdown');
    const searchInput = document.getElementById('adminSearchInput');
    const searchResults = document.getElementById('adminSearchResults');
    const searchResultsContent = document.getElementById('adminSearchResultsContent');
    const searchLoading = document.querySelector('.admin-search-loading');
    
    if (!searchToggle || !searchDropdown || !searchInput || !searchResults) return;
    
    // Toggle search dropdown
    searchToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        searchDropdown.classList.toggle('hidden');
        if (!searchDropdown.classList.contains('hidden')) {
            searchInput.focus();
        } else {
            searchResults.classList.add('hidden');
        }
    });
    
    // Debounce search function
    function performSearch(query) {
        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }
        
        // Show loading
        searchLoading.classList.remove('hidden');
        searchResults.classList.remove('hidden');
        searchResultsContent.innerHTML = '';
        
        // Fetch search results
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const searchUrl = baseUrl + '/admin/api/search?q=' + encodeURIComponent(query);
        fetch(searchUrl)
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data);
                searchLoading.classList.add('hidden');
            })
            .catch(error => {
                console.error('Search error:', error);
                searchLoading.classList.add('hidden');
                searchResultsContent.innerHTML = '<div class="p-4 text-center text-gray-500">Error loading search results</div>';
            });
    }
    
    // Display search results
    function displaySearchResults(data) {
        let html = '';
        let hasResults = false;
        
        // Products
        if (data.products && data.products.length > 0) {
            hasResults = true;
            html += `
                <div class="admin-search-section">
                    <div class="admin-search-section-header">
                        <i class="fas fa-box text-blue-500"></i>
                        <span>Products (${data.products.length})</span>
                    </div>
                    <div class="admin-search-section-content">
            `;
            
            data.products.forEach(product => {
                const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
                const defaultImage = baseUrl + '/assets/images/default-product.svg';
                const imageUrl = product.image || defaultImage;
                html += `
                    <a href="${product.url}" class="admin-search-item">
                        <img src="${imageUrl}" alt="${escapeHtml(product.name)}" class="admin-search-item-image" onerror="this.src='" + defaultImage + "'">
                        <div class="admin-search-item-content">
                            <div class="admin-search-item-title">${escapeHtml(product.name)}</div>
                            <div class="admin-search-item-meta">
                                ${product.sku ? `SKU: ${escapeHtml(product.sku)}` : ''}
                                ${product.sku && product.price ? ' • ' : ''}
                                ${product.price ? `$${parseFloat(product.price).toFixed(2)}` : ''}
                            </div>
                        </div>
                        <div class="admin-search-item-badge ${product.status === 'active' ? 'badge-success' : 'badge-draft'}">
                            ${product.status}
                        </div>
                    </a>
                `;
            });
            
            html += `</div></div>`;
        }
        
        // Categories
        if (data.categories && data.categories.length > 0) {
            hasResults = true;
            html += `
                <div class="admin-search-section">
                    <div class="admin-search-section-header">
                        <i class="fas fa-layer-group text-green-500"></i>
                        <span>Collections (${data.categories.length})</span>
                    </div>
                    <div class="admin-search-section-content">
            `;
            
            data.categories.forEach(category => {
                html += `
                    <a href="${category.url}" class="admin-search-item">
                        <div class="admin-search-item-icon bg-green-100 text-green-600">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="admin-search-item-content">
                            <div class="admin-search-item-title">${escapeHtml(category.name)}</div>
                            <div class="admin-search-item-meta">ID: ${category.id}</div>
                        </div>
                        <div class="admin-search-item-badge ${category.status === 'active' ? 'badge-success' : 'badge-draft'}">
                            ${category.status}
                        </div>
                    </a>
                `;
            });
            
            html += `</div></div>`;
        }
        
        // Orders
        if (data.orders && data.orders.length > 0) {
            hasResults = true;
            html += `
                <div class="admin-search-section">
                    <div class="admin-search-section-header">
                        <i class="fas fa-file-invoice text-purple-500"></i>
                        <span>Orders (${data.orders.length})</span>
                    </div>
                    <div class="admin-search-section-content">
            `;
            
            data.orders.forEach(order => {
                const orderDate = new Date(order.created_at).toLocaleDateString();
                html += `
                    <a href="${order.url}" class="admin-search-item">
                        <div class="admin-search-item-icon bg-purple-100 text-purple-600">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="admin-search-item-content">
                            <div class="admin-search-item-title">Order #${escapeHtml(order.order_number || order.id)}</div>
                            <div class="admin-search-item-meta">
                                ${escapeHtml(order.customer_name)} • $${parseFloat(order.total_amount).toFixed(2)} • ${orderDate}
                            </div>
                        </div>
                        <div class="admin-search-item-badge badge-${getOrderStatusClass(order.status)}">
                            ${order.status}
                        </div>
                    </a>
                `;
            });
            
            html += `</div></div>`;
        }
        
        // Customers
        if (data.customers && data.customers.length > 0) {
            hasResults = true;
            html += `
                <div class="admin-search-section">
                    <div class="admin-search-section-header">
                        <i class="fas fa-users text-orange-500"></i>
                        <span>Customers (${data.customers.length})</span>
                    </div>
                    <div class="admin-search-section-content">
            `;
            
            data.customers.forEach(customer => {
                html += `
                    <a href="${customer.url}" class="admin-search-item">
                        <div class="admin-search-item-icon bg-orange-100 text-orange-600">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="admin-search-item-content">
                            <div class="admin-search-item-title">${escapeHtml(customer.name)}</div>
                            <div class="admin-search-item-meta">
                                ${escapeHtml(customer.email)}
                                ${customer.phone ? ' • ' + escapeHtml(customer.phone) : ''}
                            </div>
                        </div>
                    </a>
                `;
            });
            
            html += `</div></div>`;
        }
        
        if (!hasResults) {
            html = '<div class="p-4 text-center text-gray-500">No results found</div>';
        }
        
        searchResultsContent.innerHTML = html;
    }
    
    // Get order status class
    function getOrderStatusClass(status) {
        const statusMap = {
            'pending': 'warning',
            'processing': 'info',
            'completed': 'success',
            'cancelled': 'danger',
            'refunded': 'secondary'
        };
        return statusMap[status] || 'secondary';
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Event listeners
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300); // 300ms debounce
    });
    
    // Close search dropdown and results when clicking outside
    document.addEventListener('click', function(e) {
        const searchContainer = document.querySelector('.admin-search-container');
        if (searchContainer && !searchContainer.contains(e.target)) {
            searchDropdown.classList.add('hidden');
            searchResults.classList.add('hidden');
        }
    });
    
    // Keep results open when clicking inside
    searchResults.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchDropdown.classList.add('hidden');
            searchResults.classList.add('hidden');
            searchInput.blur();
        }
    });
})();

