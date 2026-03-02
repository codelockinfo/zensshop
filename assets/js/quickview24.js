/* Quick View Modal - Fixed centering, mobile layout, consistent sizing */

function createQuickViewModal() {
    if (document.getElementById("quickViewModal")) return;
    const t = `
    <div id="quickViewModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true" id="quickViewBackdrop"></div>
        <!-- Modal Wrapper: perfectly centered, with gap from edges on mobile -->
        <div class="fixed inset-0 z-10 flex items-center justify-center p-4 sm:p-5" id="quickViewWrapper">
            <!-- Panel -->
            <div class="relative transform rounded-2xl text-left shadow-2xl transition-all w-full max-w-5xl bg-white flex flex-col opacity-0 scale-95 duration-300"
                 id="quickViewPanel"
                 style="height: min(88vh, 740px); max-height: min(88vh, 740px); overflow: hidden;">
                <!-- Close Button -->
                <button type="button"
                        class="absolute right-3 top-3 z-20 rounded-full bg-gray-100 p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-200 focus:outline-none transition shadow-sm"
                        onclick="closeQuickView()">
                    <span class="sr-only">Close</span>
                    <i class="fas fa-times text-sm w-5 h-5 flex items-center justify-center"></i>
                </button>
                <!-- Content -->
                <div class="flex-1 min-h-0 overflow-hidden rounded-2xl flex flex-col" id="quickViewContent">
                    <div class="flex flex-col items-center justify-center flex-1">
                        <i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-sm">Loading details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML("beforeend", t);
    document.getElementById("quickViewBackdrop").addEventListener("click", closeQuickView);
    document.getElementById("quickViewWrapper").addEventListener("click", function(e) {
        if (e.target === this) closeQuickView();
    });
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && !document.getElementById("quickViewModal").classList.contains("hidden")) closeQuickView();
    });
}

async function openQuickView(slug) {
    var modal = document.getElementById("quickViewModal");
    var panel = document.getElementById("quickViewPanel");
    var content = document.getElementById("quickViewContent");
    modal.classList.remove("hidden");
    setTimeout(function() {
        panel.classList.remove("opacity-0", "scale-95");
        panel.classList.add("opacity-100", "scale-100");
    }, 10);
    document.body.style.overflow = "hidden";
    // Loading state - same min-height as content state so modal doesn't jump
    content.innerHTML = `<div class="flex flex-col items-center justify-center flex-1 py-12"><i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i><p class="text-gray-500 text-sm">Loading...</p></div>`;
    try {
        var baseUrl = (typeof BASE_URL !== "undefined") ? BASE_URL : (window.location.pathname.split("/").slice(0, -1).join("/") || "");
        var resp = await fetch(baseUrl + "/api/quickview.php?slug=" + encodeURIComponent(slug));
        if (!resp.ok) throw new Error("Network response was not ok");
        var data = await resp.json();
        if (data.success) {
            renderQuickView(data.product);
        } else {
            content.innerHTML = `<div class="flex items-center justify-center text-red-500" style="min-height:480px;">${data.message || "Product not found"}</div>`;
        }
    } catch (err) {
        console.error("Error fetching quick view:", err);
        content.innerHTML = `<div class="flex items-center justify-center text-red-500" style="min-height:480px;">Failed to load product details.</div>`;
    }
}

function closeQuickView() {
    var modal = document.getElementById("quickViewModal");
    var panel = document.getElementById("quickViewPanel");
    panel.classList.remove("opacity-100", "scale-100");
    panel.classList.add("opacity-0", "scale-95");
    setTimeout(function() {
        modal.classList.add("hidden");
        document.body.style.overflow = "";
    }, 300);
}

// Event delegation for .quick-view-btn
!function() {
    if (window.quickViewInitialized) return;
    window.quickViewInitialized = true;
    document.body.addEventListener("click", function(e) {
        var btn = e.target.closest(".quick-view-btn");
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var slug = btn.getAttribute("data-product-slug");
            if (slug) {
                createQuickViewModal();
                openQuickView(slug);
            }
        }
    });
    if ('requestIdleCallback' in window) {
        requestIdleCallback(createQuickViewModal);
    } else {
        setTimeout(createQuickViewModal, 2000);
    }
}();

var qvSelectedOptions = {}, currentQVProduct = null;

function formatQVPrice(v) {
    var sym = (typeof CURRENCY_SYMBOL !== "undefined") ? CURRENCY_SYMBOL : "₹";
    if (!isNaN(parseInt(sym)) && String(sym).length > 2) sym = "₹";
    return sym + parseFloat(v).toFixed(2);
}

function getStockStatusText(status, qty, sales) {
    sales = sales || 0;
    if (status === "out_of_stock") return "Out of Stock";
    if (qty <= 0) return sales > 0 ? "Sold Out" : "Out of Stock";
    if (status === "on_backorder") return "On Backorder";
    return "In Stock";
}

function renderStockCountHTML(status, qty, sales) {
    sales = sales || 0;
    var label = getStockStatusText(status, qty, sales), html = "", cls = "text-red-600";
    if (label === "Out of Stock") html = '<i class="fas fa-times-circle mr-1"></i> Out of Stock';
    else if (label === "Sold Out") html = '<i class="fas fa-times-circle mr-1"></i> Sold Out';
    else if (qty > 0) { html = `<i class="fas fa-check-circle mr-1"></i> ${qty} items available`; cls = "text-primary"; }
    else if (qty < 0) { html = `<i class="fas fa-exclamation-circle mr-1"></i> Backorder (${Math.abs(qty)} pending)`; cls = "text-orange-600"; }
    else { html = `<i class="fas fa-check-circle mr-1"></i> ${label}`; cls = "text-primary"; }
    return `<span class="text-sm font-bold ${cls}">${html}</span>`;
}

function renderQuickView(t) {
    var content = document.getElementById("quickViewContent");
    currentQVProduct = t;
    var salePrice = parseFloat(t.sale_price || t.price);
    var origPrice = t.sale_price ? parseFloat(t.price) : null;
    var discountBadge = "";
    if (origPrice && origPrice > salePrice) {
        discountBadge = `<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${Math.round((origPrice - salePrice) / origPrice * 100)}%</span>`;
    }

    // Build image list (unique)
    var imgs = [], seen = new Set();
    if (t.images && t.images.length > 0) {
        t.images.forEach(function(img) { if (img && !seen.has(img)) { imgs.push(img); seen.add(img); }});
    } else if (t.image && !seen.has(t.image)) {
        imgs.push(t.image); seen.add(t.image);
    }
    if (t.variants && t.variants.length > 0) {
        t.variants.forEach(function(v) { if (v.image && !seen.has(v.image)) { imgs.push(v.image); seen.add(v.image); }});
    }

    var baseUrl = (typeof BASE_URL !== "undefined") ? BASE_URL : (window.location.pathname.split("/").slice(0, -1).join("/") || "");
    var productUrl = baseUrl + "/product.php?slug=" + t.slug;

    // Main image HTML
    var mainImgHtml = "";
    if (imgs.length > 0) {
        var firstImg = imgs[0];
        var firstExt = firstImg.split('.').pop().toLowerCase();
        var isFirstVideo = ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(firstExt);
        mainImgHtml = `
            <div class="relative block group overflow-hidden rounded-xl w-full bg-gray-50" style="aspect-ratio:1/1;">
                ${discountBadge ? `<div class="absolute top-2 left-2 z-10 shadow-sm" id="qvDiscountBadge">${discountBadge}</div>` : '<div id="qvDiscountBadge"></div>'}
                <a href="${productUrl}" class="absolute inset-0 w-full h-full flex items-center justify-center ${isFirstVideo ? 'hidden' : ''}" id="qvMainImageLink">
                    <img id="qvMainImage" src="${firstImg}" alt="${t.name}" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                </a>
                <video id="qvMainVideo" src="${isFirstVideo ? firstImg : ''}" controls class="absolute inset-0 w-full h-full object-contain bg-black ${isFirstVideo ? '' : 'hidden'}"></video>
            </div>`;
    }

    // Thumbnail slider HTML
    var thumbsHtml = "";
    if (imgs.length > 1) {
        var thumbItems = "";
        imgs.forEach(function(imgUrl, idx) {
            var ext = imgUrl.split('.').pop().toLowerCase();
            var isVid = ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(ext);
            var firstExt2 = imgs[0].split('.').pop().toLowerCase();
            var isFirstVid2 = ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(firstExt2);
            var isActive = idx === 0 && ((isFirstVid2 && isVid) || (!isFirstVid2 && !isVid));
            thumbItems += `<div class="swiper-slide h-auto">
                <button onclick="switchQVImage('${imgUrl}', this, ${isVid})"
                        data-img-url="${imgUrl}"
                        class="qv-thumb w-full h-14 border-2 rounded-lg overflow-hidden block transition focus:outline-none ${isActive ? 'border-primary' : 'border-transparent'} relative flex items-center justify-center bg-white">
                    ${isVid
                        ? `<video src="${imgUrl}" class="w-full h-full object-cover opacity-70 pointer-events-none"></video><i class="fas fa-play-circle text-white absolute inset-0 m-auto text-xl pointer-events-none flex items-center justify-center"></i>`
                        : `<img src="${imgUrl}" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/150x150?text=Img'">`
                    }
                </button>
            </div>`;
        });
        thumbsHtml = `<div class="relative w-full mt-3">
            <style>
                .qv-thumbnail-slider{padding:0 28px;position:relative;}
                .qv-thumbnail-slider .swiper-button-next,.qv-thumbnail-slider .swiper-button-prev{color:#000;width:24px;height:24px;background:#fff;border:1px solid #e5e7eb;border-radius:50%;top:50%;transform:translateY(-50%);margin-top:0;position:absolute;box-shadow:0 1px 3px rgba(0,0,0,.1);}
                .qv-thumbnail-slider .swiper-button-next{right:0;}
                .qv-thumbnail-slider .swiper-button-prev{left:0;}
                .qv-thumbnail-slider .swiper-button-next::after,.qv-thumbnail-slider .swiper-button-prev::after{font-size:10px;font-weight:bold;}
            </style>
            <div class="swiper qv-thumbnail-slider">
                <div class="swiper-wrapper">${thumbItems}</div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>`;
    }

    // Stars
    var starsHtml = "";
    var rating = Math.floor(t.rating || 5);
    for (var f = 0; f < 5; f++) starsHtml += `<i class="fas fa-star text-xs ${f < rating ? 'text-yellow-400' : 'text-gray-300'}"></i>`;

    // Variants
    var variantsHtml = "";
    qvSelectedOptions = {};
    if (t.options && t.options.length > 0) {
        t.options.forEach(function(opt) {
            var val = opt.values[0] || "";
            qvSelectedOptions[opt.name] = val;
            variantsHtml += `<div class="mb-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm font-bold text-gray-900">${opt.name}:</span>
                    <span class="text-sm font-medium text-teal-700 qv-option-val-display" data-option="${opt.name}">${val}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${opt.values.map(function(v, i) {
                        return `<button type="button" class="px-4 py-1.5 border rounded-md text-sm font-medium transition qv-variant-btn min-w-[3rem] ${i === 0 ? 'bg-[#154D35] text-white border-[#154D35]' : 'bg-transparent text-gray-700 border-gray-300 hover:border-gray-400'}"
                            onclick="selectQVVariant(this, '${opt.name.replace(/'/g,"\\'")}', '${v.replace(/'/g,"\\'")}' )"
                            data-option="${opt.name}" data-value="${v}">${v}</button>`;
                    }).join("")}
                </div>
            </div>`;
        });
    }

    var wishlistIcon = t.in_wishlist ? "fas" : "far";
    var wishlistLabel = t.in_wishlist ? "Remove from Wishlist" : "Add to Wishlist";
    var qtyVal = (t.stock_status === "out_of_stock" || (t.stock_quantity !== undefined && t.stock_quantity <= 0)) ? 0 : 1;

    // Build the two-column layout
    // On mobile: stacked (flex-col), scrollable as a unit
    // On desktop: side-by-side, right panel scrolls independently
    content.innerHTML = `
        <div class="qv-layout-inner flex flex-col md:flex-row" style="height:100%;min-height:0;overflow:hidden;">
            <!-- Left: Image — FIXED, never scrolls -->
            <div class="qv-img-col flex-shrink-0 md:w-[45%] p-3 md:p-6 border-b md:border-b-0 md:border-r border-gray-100 flex flex-col justify-center bg-white" style="overflow:hidden;">
                <div class="w-full">${mainImgHtml}</div>
                ${thumbsHtml}
            </div>
            <!-- Right: Details — SCROLLABLE -->
            <div class="flex-1 min-h-0 overflow-y-auto p-4 md:p-6 bg-white custom-scrollbar" style="-webkit-overflow-scrolling:touch;">

                <h2 id="qvTitle" class="text-xl md:text-2xl font-heading font-bold text-gray-900 mb-2 pr-8 leading-snug">${t.name}</h2>
                <div class="flex flex-wrap items-center gap-3 mb-3 text-sm">
                    <div class="flex items-center gap-1">
                        <div class="flex text-yellow-400">${starsHtml}</div>
                        <span class="text-gray-500 text-xs">(${t.review_count || Math.floor(Math.random()*50+5)} reviews)</span>
                    </div>
                    <span class="text-gray-400">|</span>
                    <span class="text-gray-500 text-xs">${Math.floor(Math.random()*20+5)} sold in last 18 hrs</span>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <span class="text-xl md:text-2xl font-bold text-[#1a3d32]" id="qvPrice">${formatQVPrice(salePrice)}</span>
                    <span id="qvOriginalPriceContainer" class="${origPrice ? '' : 'hidden'}">
                        <span class="text-gray-400 font-bold line-through text-base" id="qvOriginalPrice">${origPrice ? formatQVPrice(origPrice) : ''}</span>
                    </span>
                </div>

                <p id="qvDesc" class="text-gray-600 text-sm mb-4 leading-relaxed">
                    ${t.short_description || (t.description ? (t.description.length > 150 ? t.description.substring(0,150) + '...' : t.description) : 'No description available.')}
                </p>

                <div id="qvVariants" class="mb-4 ${variantsHtml ? 'border-t border-gray-100 pt-3' : ''}">${variantsHtml}</div>

                <div class="flex flex-col gap-3 mb-4">
                    <div class="flex gap-3 h-11">
                        <div id="qvQuantityContainer" class="flex items-center border border-black rounded-full w-28 h-full shrink-0 overflow-hidden">
                            <button onclick="updateQVQuantity(-1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">-</button>
                            <input type="text" id="qvQuantity" value="${qtyVal}" class="w-full flex-1 text-center border-none focus:ring-0 outline-none p-0 h-full text-black font-bold text-base bg-transparent shadow-none" readonly>
                            <button onclick="updateQVQuantity(1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">+</button>
                        </div>
                        <button id="qvAddToCartBtn" onclick="addToCartFromQV(${t.product_id||t.id})"
                                data-product-id="${t.product_id||t.id}"
                                class="flex-1 bg-black text-white h-full rounded-full hover:bg-gray-800 transition-all font-bold uppercase flex items-center justify-center gap-2 shadow-lg text-sm">
                            <i class="fas fa-shopping-cart"></i> <span>Add to Cart</span>
                        </button>
                    </div>
                    <button id="qvBuyNowBtn" onclick="buyNowFromQV(${t.product_id||t.id})"
                            data-product-id="${t.product_id||t.id}"
                            class="w-full bg-red-700 text-white h-11 rounded-full hover:bg-red-800 transition-all font-bold uppercase shadow-lg text-sm">
                        Buy It Now
                    </button>
                </div>

                <div class="mb-4 flex items-center space-x-2" id="qvStockCountContainer">
                    ${renderStockCountHTML(t.stock_status, t.stock_quantity, t.total_sales)}
                </div>

                <div id="qvActionsContainer" class="flex flex-wrap gap-3 text-gray-500 mb-4 font-medium text-xs">
                    <button class="hover:text-black flex items-center gap-1 transition wishlist-btn" data-product-id="${t.product_id||t.id}">
                        <i class="${wishlistIcon} fa-heart"></i> ${wishlistLabel}
                    </button>
                    <button class="hover:text-black flex items-center gap-1 transition" onclick="sharePage('${t.name.replace(/'/g,"\\'")}', 'Check out this product!', '${productUrl}')">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                    <button class="hover:text-black flex items-center gap-1 transition" onclick="toggleAskQuestionModal(true, '${t.name.replace(/'/g,"\\'")}')">
                        <i class="fas fa-question-circle"></i> Ask a question
                    </button>
                </div>

                <div id="qvPolicyBox" class="border-t border-gray-100 pt-3 space-y-2 text-xs text-gray-600 bg-gray-50 p-3 rounded-lg">
                    <div class="flex items-center gap-2 text-primary">
                        <i class="fas fa-box"></i>
                        <span class="font-medium">Pickup available at Shop location. Usually ready in 24 hours</span>
                    </div>
                    <div class="mt-2 pt-2 border-t border-gray-200 space-y-0.5">
                        <p><span class="font-bold text-gray-900">Sku:</span> <span id="qvSku">${t.sku || 'N/A'}</span></p>
                        <p><span class="font-bold text-gray-900">Available:</span>
                           <span id="qvAvailability" class="${(t.stock_status === 'in_stock' && (t.stock_quantity === undefined || t.stock_quantity > 0)) ? 'text-primary' : 'text-red-500'} font-bold">
                               ${getStockStatusText(t.stock_status, t.stock_quantity, t.total_sales)}
                           </span>
                        </p>
                    </div>
                    <div class="text-right">
                        <a href="${productUrl}" class="text-primary hover:text-black underline font-bold text-xs uppercase tracking-wide">
                            View full details <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>`;

    // Trigger variant auto-selection
    document.querySelectorAll("#qvVariants .qv-variant-btn").forEach(function(btn) {
        if (btn === btn.parentElement.children[0]) btn.click();
    });

    if (!t.variants || t.variants.length === 0) {
        var isOOS = (t.stock_status === "out_of_stock") || (t.stock_quantity !== undefined && t.stock_quantity <= 0);
        updateQVButtons(isOOS, getStockStatusText(t.stock_status, t.stock_quantity, t.total_sales));
    }

    if (imgs.length > 1) {
        loadSwiperIfNeeded(function() {
            new Swiper(".qv-thumbnail-slider", {
                slidesPerView: 4,
                spaceBetween: 8,
                navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" }
            });
        });
    }
}

function findMatchingQVVariant() {
    if (!currentQVProduct || !currentQVProduct.variants) return null;
    return currentQVProduct.variants.find(function(v) {
        var attrs = v.attributes;
        return Object.keys(qvSelectedOptions).every(function(k) { return attrs[k] === qvSelectedOptions[k]; });
    });
}

function loadSwiperIfNeeded(cb) {
    if (typeof Swiper !== "undefined") { cb(); return; }
    if (!document.querySelector('link[href*="swiper-bundle.min.css"]')) {
        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css";
        document.head.appendChild(link);
    }
    var script = document.createElement("script");
    script.src = "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js";
    script.onload = cb;
    document.body.appendChild(script);
}

window.switchQVImage = function(url, btn, isVideo) {
    var mainImg = document.getElementById("qvMainImage");
    var mainVid = document.getElementById("qvMainVideo");
    if (typeof isVideo !== "boolean") {
        var ext = url.split('.').pop().toLowerCase();
        isVideo = ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(ext);
    }
    if (isVideo) {
        if (mainImg) mainImg.classList.add('hidden');
        if (mainVid) { mainVid.src = url; mainVid.classList.remove('hidden'); mainVid.play().catch(function(){}); }
    } else {
        if (mainVid) { mainVid.pause(); mainVid.classList.add('hidden'); }
        if (mainImg) { mainImg.src = url; mainImg.classList.remove('hidden','object-contain'); mainImg.classList.add('object-cover'); }
    }
    document.querySelectorAll(".qv-thumb").forEach(function(t) { t.classList.remove("border-primary"); t.classList.add("border-transparent"); });
    if (btn) { btn.classList.remove("border-transparent"); btn.classList.add("border-primary"); }
};

window.selectQVVariant = function(btn, optionName, value) {
    qvSelectedOptions[optionName] = value;
    var display = btn.closest(".mb-4").querySelector(".qv-option-val-display");
    if (display) display.innerHTML = value;
    var siblings = btn.parentElement.querySelectorAll(".qv-variant-btn");
    siblings.forEach(function(s) {
        s.classList.remove("bg-[#154D35]","text-white","border-[#154D35]");
        s.classList.add("bg-white","text-gray-700","border-gray-300");
    });
    btn.classList.remove("bg-white","text-gray-700","border-gray-300");
    btn.classList.add("bg-[#154D35]","text-white","border-[#154D35]");

    if (!currentQVProduct || !currentQVProduct.variants) return;
    var matched = findMatchingQVVariant();
    if (!matched) return;
    var sp = parseFloat(matched.sale_price || matched.price);
    var op = matched.sale_price ? parseFloat(matched.price) : null;
    var priceEl = document.getElementById("qvPrice");
    var origEl = document.getElementById("qvOriginalPrice");
    var origContEl = document.getElementById("qvOriginalPriceContainer");
    var badgeEl = document.getElementById("qvDiscountBadge");
    if (priceEl) priceEl.innerHTML = formatQVPrice(sp);
    if (origEl && op) {
        origEl.innerHTML = formatQVPrice(op);
        origContEl.classList.remove("hidden");
        if (badgeEl) badgeEl.innerHTML = `<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${Math.round((op-sp)/op*100)}%</span>`;
    } else {
        if (origContEl) origContEl.classList.add("hidden");
        if (badgeEl) badgeEl.innerHTML = "";
    }
    var imgUrl = matched.image || currentQVProduct.image;
    if (imgUrl) {
        var thumbBtn = document.querySelector(`.qv-thumb[data-img-url="${imgUrl}"]`);
        var ext = imgUrl.split('.').pop().toLowerCase();
        window.switchQVImage(imgUrl, thumbBtn, ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(ext));
    }
    var skuEl = document.getElementById("qvSku");
    if (skuEl) skuEl.innerHTML = matched.sku || currentQVProduct.sku || "N/A";
    var availEl = document.getElementById("qvAvailability");
    var stockEl = document.getElementById("qvStockCountContainer");
    if (availEl) {
        var txt = getStockStatusText(matched.stock_status, matched.stock_quantity, currentQVProduct.total_sales);
        var oos = (matched.stock_status === "out_of_stock") || (matched.stock_quantity <= 0);
        availEl.innerHTML = txt;
        availEl.className = oos ? "text-red-500 font-bold" : "text-primary font-bold";
        updateQVButtons(oos, txt);
        if (stockEl) stockEl.innerHTML = renderStockCountHTML(matched.stock_status, matched.stock_quantity, currentQVProduct.total_sales);
        var qtyEl = document.getElementById("qvQuantity");
        if (qtyEl && matched.stock_quantity !== undefined && matched.stock_quantity !== null) {
            if (parseInt(qtyEl.value) > matched.stock_quantity) qtyEl.value = Math.max(0, matched.stock_quantity);
        }
    }
};

window.updateQVQuantity = function(delta) {
    var el = document.getElementById("qvQuantity");
    if (!el) return;
    var val = parseInt(el.value) + delta;
    var max = 9999;
    if (currentQVProduct) {
        if (currentQVProduct.variants && currentQVProduct.variants.length > 0) {
            var mv = findMatchingQVVariant();
            if (mv && mv.stock_quantity !== undefined && mv.stock_quantity !== null) max = parseInt(mv.stock_quantity);
        } else if (currentQVProduct.stock_quantity !== undefined && currentQVProduct.stock_quantity !== null) {
            max = parseInt(currentQVProduct.stock_quantity);
        }
    }
    if (max > 0) {
        if (val > max) { val = max; if (typeof showNotification === "function" && delta > 0) showNotification("Only " + max + " items available in stock."); }
        if (val < 1) val = 1;
    } else { val = 0; }
    el.value = val;
};

window.addToCartFromQV = function(productId) {
    var qty = parseInt(document.getElementById("qvQuantity").value);
    var btn = document.querySelector('#quickViewModal button[onclick*="addToCartFromQV"]');
    var origHtml = btn.innerHTML;
    if (typeof window.addToCart === "function") {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
        btn.classList.add("opacity-75","cursor-not-allowed");
        var p = window.addToCart(productId, qty, btn, qvSelectedOptions);
        if (p && typeof p.then === "function") {
            p.then(function(r) {
                if (r && r.success) { closeQuickView(); }
                else { btn.innerHTML = origHtml; btn.disabled = false; btn.classList.remove("opacity-75","cursor-not-allowed"); }
            }).catch(function() { btn.innerHTML = origHtml; btn.disabled = false; btn.classList.remove("opacity-75","cursor-not-allowed"); });
        } else {
            setTimeout(function() { btn.innerHTML = origHtml; btn.disabled = false; btn.classList.remove("opacity-75","cursor-not-allowed"); }, 1000);
        }
    }
};

window.buyNowFromQV = function(productId) {
    var qty = parseInt(document.getElementById("qvQuantity").value) || 1;
    var btn = document.querySelector('#quickViewModal button[onclick*="buyNowFromQV"]');
    if (btn && typeof setBtnLoading === "function") setBtnLoading(btn, true);
    var baseUrl = (typeof BASE_URL !== "undefined") ? BASE_URL : (window.location.pathname.split("/").slice(0,-1).join("/") || "");
    fetch(baseUrl + "/api/cart.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({product_id: productId, quantity: qty, variant_attributes: qvSelectedOptions})
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { window.location.href = "checkout"; }
        else { if (typeof showNotification === "function") showNotification(d.message || "Failed to add to cart"); if (btn && typeof setBtnLoading === "function") setBtnLoading(btn, false); }
    }).catch(function(e) { console.error("Error:", e); if (btn && typeof setBtnLoading === "function") setBtnLoading(btn, false); });
};

window.updateQVButtons = function(isOOS, label) {
    label = label || "Out of Stock";
    var atcBtn = document.querySelector('#quickViewModal button[onclick*="addToCartFromQV"]');
    var buyBtn = document.querySelector('#quickViewModal button[onclick*="buyNowFromQV"]');
    if (isOOS) {
        if (atcBtn) { atcBtn.disabled = true; atcBtn.classList.add("opacity-50","cursor-not-allowed"); atcBtn.innerHTML = `<span>${label}</span>`; }
        if (buyBtn) { buyBtn.disabled = true; buyBtn.classList.add("opacity-50","cursor-not-allowed"); }
    } else {
        if (atcBtn) { atcBtn.disabled = false; atcBtn.classList.remove("opacity-50","cursor-not-allowed"); atcBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> <span>Add to Cart</span>'; }
        if (buyBtn) { buyBtn.disabled = false; buyBtn.classList.remove("opacity-50","cursor-not-allowed"); }
    }
};
