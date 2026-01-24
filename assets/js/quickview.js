(function() {
    // Prevent duplicate initialization
    if (window.quickViewInitialized) return;
    window.quickViewInitialized = true;

    function initQuickView() {
        // Add click event listeners via delegation
        document.body.addEventListener('click', function(e) {
            // Find closest .quick-view-btn if clicked inside
            const btn = e.target.closest('.quick-view-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation(); // Stop bubbling just in case
                const productSlug = btn.getAttribute('data-product-slug');
                if (productSlug) {
                    openQuickView(productSlug);
                }
            }
        });

        // Create modal DOM if not exists
        createQuickViewModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuickView);
    } else {
        initQuickView();
    }
})();

function createQuickViewModal() {
    if (document.getElementById('quickViewModal')) return;

    const modalHTML = `
    <div id="quickViewModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true" id="quickViewBackdrop"></div>

        <!-- Modal Panel Container -->
        <div class="fixed inset-0 z-10 overflow-hidden flex items-center justify-center p-4 sm:p-6">
            <!-- Panel -->
            <div class="relative transform rounded-lg bg-white text-left shadow-xl transition-all w-full max-w-5xl h-[85vh] flex flex-col opacity-0 scale-95 duration-300" id="quickViewPanel">
                
                <!-- Close Button -->
                <div class="absolute right-4 top-4 z-20">
                    <button type="button" class="rounded-full bg-gray-100 p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-200 focus:outline-none transition shadow-sm" onclick="closeQuickView()">
                        <span class="sr-only">Close</span>
                        <i class="fas fa-times text-xl w-6 h-6 flex items-center justify-center"></i>
                    </button>
                </div>

                <!-- Content (Flex/Grid Wrapper) -->
                <div class="flex-1 overflow-hidden rounded-lg" id="quickViewContent">
                    <!-- Loading State -->
                    <div class="flex flex-col items-center justify-center h-full">
                        <i class="fas fa-spinner fa-spin text-4xl text-black mb-4"></i>
                        <p class="text-gray-500">Loading details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Close on backdrop click
    document.getElementById('quickViewBackdrop').addEventListener('click', closeQuickView);
    
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('quickViewModal').classList.contains('hidden')) {
            closeQuickView();
        }
    });
}

async function openQuickView(slug) {
    const modal = document.getElementById('quickViewModal');
    const panel = document.getElementById('quickViewPanel');
    const content = document.getElementById('quickViewContent');

    // Show modal
    modal.classList.remove('hidden');
    // Animate in
    setTimeout(() => {
        panel.classList.remove('opacity-0', 'scale-95');
        panel.classList.add('opacity-100', 'scale-100');
    }, 10);
    document.body.style.overflow = 'hidden';

    // Show Loader
    content.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full">
            <i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Loading...</p>
        </div>
    `;

    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const response = await fetch(`${baseUrl}/api/quickview.php?slug=${encodeURIComponent(slug)}`);
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            renderQuickView(data.product);
        } else {
            content.innerHTML = `<div class="flex items-center justify-center h-full text-red-500">${data.message || 'Product not found'}</div>`;
        }
    } catch (error) {
        console.error('Error fetching quick view:', error);
        content.innerHTML = `<div class="flex items-center justify-center h-full text-red-500">Failed to load product details.</div>`;
    }
}

function closeQuickView() {
    const modal = document.getElementById('quickViewModal');
    const panel = document.getElementById('quickViewPanel');
    
    panel.classList.remove('opacity-100', 'scale-100');
    panel.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }, 300);
}

// Quick View Global State
let qvSelectedOptions = {};
let currentQVProduct = null;

// Quick View Render Logic
function renderQuickView(product) {
    const content = document.getElementById('quickViewContent');
    currentQVProduct = product;
    
    // Calculate Default Discount
    const priceValue = parseFloat(product.sale_price || product.price);
    const originalPriceValue = product.sale_price ? parseFloat(product.price) : null;
    let discountHTML = '';
    if (originalPriceValue && originalPriceValue > priceValue) {
        const discount = Math.round(((originalPriceValue - priceValue) / originalPriceValue) * 100);
        discountHTML = `<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${discount}%</span>`;
    }

    // Collect all unique images (regular + variant images)
    const galleryItems = [];
    const seenUrls = new Set();
    
    // Add product images
    if (product.images && product.images.length > 0) {
        product.images.forEach(img => {
            if (img && !seenUrls.has(img)) {
                galleryItems.push(img);
                seenUrls.add(img);
            }
        });
    } else if (product.image && !seenUrls.has(product.image)) {
        galleryItems.push(product.image);
        seenUrls.add(product.image);
    }
    
    // Add variant images
    if (product.variants && product.variants.length > 0) {
        product.variants.forEach(v => {
            if (v.image && !seenUrls.has(v.image)) {
                galleryItems.push(v.image);
                seenUrls.add(v.image);
            }
        });
    }

    let imagesHTML = '';
    let thumbnailsHTML = '';
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
    const productUrl = `${baseUrl}/product.php?slug=${product.slug}`;
    
    if (galleryItems.length > 0) {
        imagesHTML = `
        <div class="relative block group overflow-hidden rounded-lg h-full flex items-center justify-center w-full min-h-0 bg-gray-50">
            ${discountHTML ? `<div class="absolute top-2 left-2 z-10 shadow-sm" id="qvDiscountBadge">${discountHTML}</div>` : '<div id="qvDiscountBadge"></div>'}
            <img id="qvMainImage" src="${galleryItems[0]}" alt="${product.name}" class="max-h-full max-w-full object-contain transition duration-500 group-hover:scale-105">
        </div>`;
        
        if (galleryItems.length > 1) {
            thumbnailsHTML = `<div class="flex gap-2 mt-4 overflow-x-auto pb-2 custom-scrollbar w-full" id="qvThumbContainer">`;
            galleryItems.forEach((img, idx) => {
                 thumbnailsHTML += `
                 <button onclick="switchQVImage('${img}', this)" 
                         data-img-url="${img}"
                         class="qv-thumb w-16 h-16 border-2 rounded-md overflow-hidden flex-shrink-0 transition focus:outline-none bg-white ${idx === 0 ? 'border-primary' : 'border-transparent'}">
                    <img src="${img}" class="w-full h-full object-cover">
                 </button>`;
            });
            thumbnailsHTML += `</div>`;
        }
    }

    // Rating
    let ratingStars = '';
    const ratingValue = Math.floor(product.rating || 5);
    for(let i=0; i<5; i++) {
        ratingStars += `<i class="fas fa-star text-xs ${i < ratingValue ? 'text-yellow-400' : 'text-gray-300'}"></i>`;
    }

    // Variants HTML Construction
    let variantsHTML = '';
    qvSelectedOptions = {};
    
    if (product.options && product.options.length > 0) {
        product.options.forEach(opt => {
             const firstVal = opt.values[0] || '';
             qvSelectedOptions[opt.name] = firstVal;
             
             variantsHTML += `
             <div class="mb-5">
                 <div class="flex items-center gap-2 mb-2">
                     <span class="text-sm font-bold text-gray-900">${opt.name}:</span>
                     <span class="text-sm font-medium text-teal-700 qv-option-val-display" data-option="${opt.name}">${firstVal}</span>
                 </div>
                 <div class="flex flex-wrap gap-2">
                     ${opt.values.map((val, idx) => `
                         <button type="button" class="px-5 py-2 border rounded-md text-sm font-medium transition qv-variant-btn min-w-[3rem] ${idx === 0 ? 'bg-[#154D35] text-white border-[#154D35]' : 'bg-white text-gray-700 border-gray-300 hover:border-gray-400'}" 
                                 onclick="selectQVVariant(this, '${opt.name.replace(/'/g, "\\'")}', '${val.replace(/'/g, "\\'")}')"
                                 data-option="${opt.name}" data-value="${val}">${val}</button>
                     `).join('')}
                 </div>
             </div>`;
        });
    }

    // Wishlist Button State
    const wishlistIconClass = product.in_wishlist ? "fas" : "far";
    const wishlistText = product.in_wishlist ? "Remove from Wishlist" : "Add to Wishlist";

    // View Counts
    const soldCount = Math.floor(Math.random() * 20) + 5;
    const viewingCount = Math.floor(Math.random() * 30) + 10;

    content.innerHTML = `
        <div class="h-full grid grid-cols-1 md:grid-cols-2 bg-white">
            <div class="p-6 md:p-8 bg-white md:border-r border-gray-100 flex flex-col justify-between overflow-hidden relative max-h-[600px]">
                <div class="relative flex-1 flex flex-col items-center justify-center w-full min-h-0">
                    ${imagesHTML}
                </div>
                ${thumbnailsHTML}
            </div>

            <div class="p-6 md:p-8 h-full overflow-y-auto custom-scrollbar bg-white relative">
                <h2 class="text-2xl md:text-3xl font-heading font-bold text-gray-900 mb-2 pr-8">${product.name}</h2>
                <div class="flex flex-wrap items-center gap-4 mb-4 text-sm">
                    <div class="flex items-center gap-1">
                        <div class="flex text-yellow-400">${ratingStars}</div>
                        <span class="text-gray-500">(${product.review_count || Math.floor(Math.random() * 50) + 5} reviews)</span>
                    </div>
                    <span class="text-gray-400">|</span>
                    <span class="text-gray-600 font-medium">${soldCount} sold in last 18 hours</span>
                </div>
                
                <div class="mb-4 flex items-center gap-3">
                    <span class="text-2xl font-bold text-black" id="qvPrice">${formatCurrency(priceValue)}</span>
                    <span id="qvOriginalPriceContainer" class="${originalPriceValue ? '' : 'hidden'}">
                        <span class="text-gray-400 line-through text-lg" id="qvOriginalPrice">${originalPriceValue ? formatCurrency(originalPriceValue) : ''}</span>
                    </span>
                </div>

                <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                    ${product.short_description || product.description?.substring(0, 150) + '...' || 'Elegant and timeless design.'}
                </p>

                <div class="space-y-2 mb-6 text-sm text-gray-700">
                    ${product.highlights && product.highlights.length > 0 ? 
                        product.highlights.map(h => `
                            <div class="flex items-start gap-2">
                                <i class="${h.icon || 'fas fa-check'} text-green-600 mt-0.5"></i>
                                <span>${h.text}</span>
                            </div>
                        `).join('') : `
                        <div class="flex items-start gap-2">
                            <i class="fas fa-truck text-green-600 mt-0.5"></i>
                            <span>Estimate delivery times: <strong>3-5 days International</strong></span>
                        </div>
                        <div class="flex items-start gap-2 animate-pulse">
                            <i class="fas fa-eye text-green-600 mt-0.5"></i>
                            <span><strong>${viewingCount} people</strong> are viewing this right now</span>
                        </div>
                    `}
                </div>

                <div id="qvVariants" class="mb-6 border-t border-gray-100 pt-4">
                    ${variantsHTML}
                </div>

                <div class="flex flex-col gap-3 mb-6">
                    <div class="flex gap-3 h-12">
                        <div class="flex items-center border border-black rounded-full w-28 h-full shrink-0 overflow-hidden">
                            <button onclick="updateQVQuantity(-1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">-</button>
                            <input type="number" id="qvQuantity" value="1" min="1" class="w-full flex-1 text-center border-none focus:ring-0 p-0 h-full text-black font-bold text-lg bg-transparent" readonly>
                            <button onclick="updateQVQuantity(1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">+</button>
                        </div>
                        <button onclick="addToCartFromQV(${product.product_id || product.id})" class="flex-1 bg-black text-white h-full rounded-full hover:bg-gray-800 transition-all font-bold uppercase flex items-center justify-center gap-2 shadow-lg text-sm">
                            <i class="fas fa-shopping-cart"></i> <span>Add to Cart</span>
                        </button>
                    </div>
                    <button onclick="buyNowFromQV(${product.product_id || product.id})" class="w-full bg-red-700 text-white h-12 rounded-full hover:bg-red-800 transition-all font-bold uppercase shadow-lg text-sm">
                        Buy It Now
                    </button>
                </div>
                
                <div class="flex gap-4 text-xs text-gray-500 mb-6 font-medium">
                    <button class="hover:text-black flex items-center gap-1 transition wishlist-btn" 
                            data-product-id="${product.product_id || product.id}" 
                            onclick="if(typeof toggleWishlist === 'function') toggleWishlist(this);">
                        <i class="${wishlistIconClass} fa-heart"></i> ${wishlistText}
                    </button>
                    <button class="hover:text-black flex items-center gap-1 transition" onclick="sharePage('${product.name.replace(/'/g, "\\'")}', 'Check out this product!', '${productUrl}')">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                </div>

                <div class="border-t border-gray-100 pt-4 space-y-2 text-sm text-gray-600 bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center gap-2 text-green-700">
                         <i class="fas fa-box"></i>
                         <span class="font-medium">Pickup available at Shop location. Usually ready in 24 hours</span>
                    </div>
                    <div class="mt-2 pt-2 border-t border-gray-200">
                        <p><span class="font-bold text-gray-900">Sku:</span> <span id="qvSku">${product.sku || 'N/A'}</span></p>
                        <p><span class="font-bold text-gray-900">Available:</span> <span id="qvAvailability" class="${product.stock_status === 'in_stock' ? 'text-green-600' : 'text-red-500'} font-bold">${product.stock_status === 'in_stock' ? 'In Stock' : 'Out of Stock'}</span></p>
                    </div>
                    <div class="mt-2 text-right">
                        <a href="${productUrl}" class="text-primary hover:text-black underline font-bold text-xs uppercase tracking-wide">View full details <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Auto-select first variant options to trigger UI updates
    document.querySelectorAll('#qvVariants .qv-variant-btn').forEach(btn => {
         const siblings = btn.parentElement.children;
         if (btn === siblings[0]) btn.click();
    });
}

window.switchQVImage = function(url, btn) {
    const mainImg = document.getElementById('qvMainImage');
    if (mainImg) mainImg.src = url;

    // Update thumbnail borders
    document.querySelectorAll('.qv-thumb').forEach(thumb => {
        thumb.classList.remove('border-primary');
        thumb.classList.add('border-transparent');
    });
    if (btn) {
        btn.classList.remove('border-transparent');
        btn.classList.add('border-primary');
    }
};

window.selectQVVariant = function(btn, option, value) {
    qvSelectedOptions[option] = value;
    
    const labelDisplay = btn.closest('.mb-5').querySelector('.qv-option-val-display');
    if(labelDisplay) labelDisplay.innerHTML = value;

    const siblings = btn.parentElement.querySelectorAll('.qv-variant-btn');
    siblings.forEach(b => {
        b.classList.remove('bg-[#154D35]', 'text-white', 'border-[#154D35]');
        b.classList.add('bg-white', 'text-gray-700', 'border-gray-300');
    });
    btn.classList.remove('bg-white', 'text-gray-700', 'border-gray-300');
    btn.classList.add('bg-[#154D35]', 'text-white', 'border-[#154D35]');

    // Find and update matching variant details
    if (currentQVProduct && currentQVProduct.variants) {
        const variant = findMatchingQVVariant();
        if (variant) {
            // Update Price
            const price = parseFloat(variant.sale_price || variant.price);
            const originalPrice = variant.sale_price ? parseFloat(variant.price) : null;
            
            const priceEl = document.getElementById('qvPrice');
            const originalPriceEl = document.getElementById('qvOriginalPrice');
            const originalPriceContainer = document.getElementById('qvOriginalPriceContainer');
            const badgeContainer = document.getElementById('qvDiscountBadge');

            if (priceEl) priceEl.innerHTML = formatCurrency(price);
            if (originalPriceEl && originalPrice) {
                originalPriceEl.innerHTML = formatCurrency(originalPrice);
                originalPriceContainer.classList.remove('hidden');
                
                if (badgeContainer) {
                    const discount = Math.round(((originalPrice - price) / originalPrice) * 100);
                    badgeContainer.innerHTML = `<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${discount}%</span>`;
                }
            } else {
                if (originalPriceContainer) originalPriceContainer.classList.add('hidden');
                if (badgeContainer) badgeContainer.innerHTML = '';
            }

            // Update Image and matching thumbnail border
            if (variant.image) {
                const thumb = document.querySelector(`.qv-thumb[data-img-url="${variant.image}"]`);
                switchQVImage(variant.image, thumb);
            }

            // Update SKU
            const skuEl = document.getElementById('qvSku');
            if (skuEl) skuEl.innerHTML = variant.sku || currentQVProduct.sku || 'N/A';

            // Update Availability
            const availEl = document.getElementById('qvAvailability');
            if (availEl) {
                const isInStock = variant.stock_status === 'in_stock' && variant.stock_quantity > 0;
                availEl.innerHTML = isInStock ? 'In Stock' : 'Out of Stock';
                availEl.className = isInStock ? 'text-green-600 font-bold' : 'text-red-500 font-bold';
            }
        }
    }
};

function findMatchingQVVariant() {
    if (!currentQVProduct || !currentQVProduct.variants) return null;
    return currentQVProduct.variants.find(variant => {
        const attrs = variant.attributes;
        return Object.keys(qvSelectedOptions).every(key => attrs[key] === qvSelectedOptions[key]);
    });
}

window.updateQVQuantity = function(change) {
    const input = document.getElementById('qvQuantity');
    let val = parseInt(input.value) + change;
    if (val < 1) val = 1;
    input.value = val;
};

window.addToCartFromQV = function(productId) {
    const qty = parseInt(document.getElementById('qvQuantity').value);
    const btn = document.querySelector('#quickViewModal button[onclick*="addToCartFromQV"]');
    const originalContent = btn.innerHTML; // Cache content to restore

    if (typeof window.addToCart === 'function') {
        // Loading State
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');

        // Call addToCart which returns a Promise (in cart6.js)
        const promise = window.addToCart(productId, qty, btn, qvSelectedOptions);
        
        if (promise && typeof promise.then === 'function') {
            promise.then((result) => {
                if (result && result.success) {
                    closeQuickView();
                } else {
                     // Reset if failed
                     btn.innerHTML = originalContent;
                     btn.disabled = false;
                     btn.classList.remove('opacity-75', 'cursor-not-allowed');
                }
            }).catch(() => {
                 btn.innerHTML = originalContent;
                 btn.disabled = false;
                 btn.classList.remove('opacity-75', 'cursor-not-allowed');
            });
        } else {
             // Fallback if not promise
             setTimeout(() => {
                 btn.innerHTML = originalContent;
                 btn.disabled = false;
                 btn.classList.remove('opacity-75', 'cursor-not-allowed');
             }, 1000);
        }
    } else {
    }
};

window.buyNowFromQV = function(productId) {
    const qty = parseInt(document.getElementById('qvQuantity').value) || 1;
    const btn = document.querySelector('#quickViewModal button[onclick*="buyNowFromQV"]');
    
    if (btn) setBtnLoading(btn, true);

    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
    
    fetch(baseUrl + '/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            product_id: productId, 
            quantity: qty,
            variant_attributes: qvSelectedOptions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect directly to checkout
            window.location.href = 'checkout.php';
        } else {
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to add product to cart');
            } 
            if (btn) setBtnLoading(btn, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (btn) setBtnLoading(btn, false);
    });
};

