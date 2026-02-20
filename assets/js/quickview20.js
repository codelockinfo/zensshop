function createQuickViewModal(){if(document.getElementById("quickViewModal"))return;let t=`
    <div id="quickViewModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true" id="quickViewBackdrop"></div>

        <!-- Modal Panel Container -->
        <div class="fixed inset-0 z-10 overflow-hidden flex items-center justify-center p-4 sm:p-6" id="quickViewWrapper">
            <!-- Panel -->
            <div class="relative transform rounded-lg text-left shadow-xl transition-all w-full max-w-5xl h-[85vh] flex flex-col opacity-0 scale-95 duration-300" id="quickViewPanel">
                
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
    `;document.body.insertAdjacentHTML("beforeend",t),document.getElementById("quickViewBackdrop").addEventListener("click",closeQuickView),document.getElementById("quickViewWrapper").addEventListener("click",function(t){t.target===this&&closeQuickView()}),document.addEventListener("keydown",function(t){"Escape"!==t.key||document.getElementById("quickViewModal").classList.contains("hidden")||closeQuickView()})}async function openQuickView(t){let e=document.getElementById("quickViewModal"),i=document.getElementById("quickViewPanel"),a=document.getElementById("quickViewContent");e.classList.remove("hidden"),setTimeout(()=>{i.classList.remove("opacity-0","scale-95"),i.classList.add("opacity-100","scale-100")},10),document.body.style.overflow="hidden",a.innerHTML=`
        <div class="flex flex-col items-center justify-center h-full">
            <i class="fas fa-spinner fa-spin text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Loading...</p>
        </div>
    `;try{let s="undefined"!=typeof BASE_URL?BASE_URL:window.location.pathname.split("/").slice(0,-1).join("/")||"",n=await fetch(`${s}/api/quickview.php?slug=${encodeURIComponent(t)}`);if(!n.ok)throw Error("Network response was not ok");let l=await n.json();l.success?renderQuickView(l.product):a.innerHTML=`<div class="flex items-center justify-center h-full text-red-500">${l.message||"Product not found"}</div>`}catch(r){console.error("Error fetching quick view:",r),a.innerHTML='<div class="flex items-center justify-center h-full text-red-500">Failed to load product details.</div>'}}function closeQuickView(){let t=document.getElementById("quickViewModal"),e=document.getElementById("quickViewPanel");e.classList.remove("opacity-100","scale-100"),e.classList.add("opacity-0","scale-95"),setTimeout(()=>{t.classList.add("hidden"),document.body.style.overflow=""},300)}!function(){
    window.quickViewInitialized||(
        window.quickViewInitialized=!0,
        document.body.addEventListener("click",function(t){
            let e=t.target.closest(".quick-view-btn");
            if(e){
                t.preventDefault();
                t.stopPropagation();
                let i=e.getAttribute("data-product-slug");
                if(i) {
                    createQuickViewModal(); // Create on demand
                    openQuickView(i);
                }
            }
        }),
        // Also create on idle
        'requestIdleCallback' in window ? requestIdleCallback(createQuickViewModal) : setTimeout(createQuickViewModal, 2000)
    )
}();let qvSelectedOptions={},currentQVProduct=null;function formatQVPrice(t){let e="undefined"!=typeof CURRENCY_SYMBOL?CURRENCY_SYMBOL:"₹";return!isNaN(parseInt(e))&&String(e).length>2&&(e="₹"),e+parseFloat(t).toFixed(2)}function getStockStatusText(t,e,i=0){return"out_of_stock"===t?"Out of Stock":e<=0?i>0?"Sold Out":"Out of Stock":"on_backorder"===t?"On Backorder":"In Stock"}function renderStockCountHTML(t,e,i=0){let a=getStockStatusText(t,e,i),s="",n="text-red-600";return"Out of Stock"===a?s='<i class="fas fa-times-circle mr-1"></i> Out of Stock':"Sold Out"===a?s='<i class="fas fa-times-circle mr-1"></i> Sold Out':e>0?(s=`<i class="fas fa-check-circle mr-1"></i> ${e} items available`,n="text-primary"):e<0?(s=`<i class="fas fa-exclamation-circle mr-1"></i> Backorder (${Math.abs(e)} pending)`,n="text-orange-600"):(s=`<i class="fas fa-check-circle mr-1"></i> ${a}`,n="text-primary"),`<span class="text-sm font-bold ${n}">${s}</span>`}function renderQuickView(t){let e=document.getElementById("quickViewContent");currentQVProduct=t;let i=parseFloat(t.sale_price||t.price),a=t.sale_price?parseFloat(t.price):null,s="";a&&a>i&&(s=`<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${Math.round((a-i)/a*100)}%</span>`);let n=[],l=new Set;t.images&&t.images.length>0?t.images.forEach(t=>{t&&!l.has(t)&&(n.push(t),l.add(t))}):t.image&&!l.has(t.image)&&(n.push(t.image),l.add(t.image)),t.variants&&t.variants.length>0&&t.variants.forEach(t=>{t.image&&!l.has(t.image)&&(n.push(t.image),l.add(t.image))});let r="",o="",c="undefined"!=typeof BASE_URL?BASE_URL:window.location.pathname.split("/").slice(0,-1).join("/")||"",d=`${c}/product.php?slug=${t.slug}`;    const firstImg = n[0];
    const firstExt = firstImg.split('.').pop().toLowerCase();
    const isFirstVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(firstExt);

    n.length>0&&(r=`
        <div class="relative block group overflow-hidden rounded-lg w-full bg-gray-50 mx-auto" style="aspect-ratio: 1 / 1;">
            ${s?`<div class="absolute top-2 left-2 z-10 shadow-sm" id="qvDiscountBadge">${s}</div>`:'<div id="qvDiscountBadge"></div>'}
            <div class="w-full h-full relative">
                <a href="${d}" class="absolute inset-0 w-full h-full flex items-center justify-center ${isFirstVideo ? 'hidden' : ''}" id="qvMainImageLink">
                    <img id="qvMainImage" src="${firstImg}" alt="${t.name}" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" onerror="this.src='https://placehold.co/600x600?text=Product+Image'">
                </a>
                <video id="qvMainVideo" src="${isFirstVideo ? firstImg : ''}" controls class="absolute inset-0 w-full h-full object-contain bg-black ${isFirstVideo ? '' : 'hidden'}"></video>
            </div>
        </div>`,n.length>1&&(o=`
            <div class="relative w-full">
                <style>
                    .qv-thumbnail-slider {
                        padding: 0 30px; 
                        position: relative;
                    }
                    .qv-thumbnail-slider .swiper-button-next,
                    .qv-thumbnail-slider .swiper-button-prev {
                        color: #000;
                        width: 28px;
                        height: 28px;
                        background: #fff;
                        border: 1px solid #e5e7eb;
                        border-radius: 50%;
                        top: 50%;
                        transform: translateY(-50%);
                        margin-top: 0;
                        position: absolute;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    }
                    .qv-thumbnail-slider .swiper-button-next:hover,
                    .qv-thumbnail-slider .swiper-button-prev:hover {
                        background: #f9fafb;
                    }
                    .qv-thumbnail-slider .swiper-button-next { right: 0; }
                    .qv-thumbnail-slider .swiper-button-prev { left: 0; }
                    .qv-thumbnail-slider .swiper-button-next::after,
                    .qv-thumbnail-slider .swiper-button-prev::after { font-size: 12px; font-weight: bold; }
                </style>
                <div class="swiper qv-thumbnail-slider mt-4">
                    <div class="swiper-wrapper">`,n.forEach((imgUrl,idx)=>{
                    const ext = imgUrl.split('.').pop().toLowerCase();
                    const isVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(ext);
                    o+=`
                 <div class="swiper-slide h-auto">
                     <button onclick="switchQVImage('${imgUrl}', this, ${isVideo})" 
                             data-img-url="${imgUrl}"
                             class="qv-thumb w-full h-16 border-2 rounded-md overflow-hidden block transition focus:outline-none bg-white ${0===idx&&((isFirstVideo&&isVideo)||(!isFirstVideo&&!isVideo))?"border-primary":"border-transparent"} relative flex items-center justify-center bg-black">
                        ${isVideo?`<video src="${imgUrl}" class="w-full h-full object-cover opacity-70 pointer-events-none"></video><i class="fas fa-play-circle text-white absolute inset-0 m-auto text-xl pointer-events-none flex items-center justify-center"></i>`:`<img src="${imgUrl}" class="w-full h-full object-cover bg-white" onerror="this.src='https://placehold.co/150x150?text=Product+Image'">`}
                     </button>
                 </div>`}),o+=`
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>`));let u="",p=Math.floor(t.rating||5);for(let f=0;f<5;f++)u+=`<i class="fas fa-star text-xs ${f<p?"text-yellow-400":"text-gray-300"}"></i>`;let v="";qvSelectedOptions={},t.options&&t.options.length>0&&t.options.forEach(opt=>{let val=opt.values[0]||"";qvSelectedOptions[opt.name]=val,v+=`
             <div class="mb-5">
                 <div class="flex items-center gap-2 mb-2">
                     <span class="text-sm font-bold text-gray-900">${opt.name}:</span>
                     <span class="text-sm font-medium text-teal-700 qv-option-val-display" data-option="${opt.name}">${val}</span>
                 </div>
                 <div class="flex flex-wrap gap-2">
                     ${opt.values.map((e,i)=>`
                         <button type="button" class="px-5 py-2 border rounded-md text-sm font-medium transition qv-variant-btn min-w-[3rem] ${0===i?"bg-[#154D35] text-white border-[#154D35]":"bg-transparent text-gray-700 border-gray-300 hover:border-gray-400"}" 
                                 onclick="selectQVVariant(this, '${opt.name.replace(/'/g,"\\'")}', '${e.replace(/'/g,"\\'")}')"
                                 data-option="${opt.name}" data-value="${e}">${e}</button>
                     `).join("")}
                 </div>
             </div>`});let m=t.in_wishlist?"fas":"far",g=t.in_wishlist?"Remove from Wishlist":"Add to Wishlist";if(e.innerHTML=`
        <div class="h-full grid grid-cols-1 md:grid-cols-2 bg-transparent">
            <div class="p-6 md:p-8 bg-transparent md:border-r border-gray-100 flex flex-col justify-between overflow-hidden relative max-h-[500px]">
                <div class="relative flex-1 flex flex-col items-center justify-center w-full min-h-0">
                    ${r}
                </div>
                ${o}
            </div>

            <div class="p-6 md:p-8 h-full overflow-y-auto custom-scrollbar bg-transparent relative">
                <h2 id="qvTitle" class="text-2xl md:text-3xl font-heading font-bold text-gray-900 mb-2 pr-8 truncate-3-lines">${t.name}</h2>
                <div class="flex flex-wrap items-center gap-4 mb-4 text-sm">
                    <div class="flex items-center gap-1">
                        <div class="flex text-yellow-400">${u}</div>
                        <span class="text-gray-500">(${t.review_count||Math.floor(50*Math.random())+5} reviews)</span>
                    </div>
                    <span class="text-gray-400">|</span>
                    <span class="text-gray-600 font-medium">${Math.floor(20*Math.random())+5} sold in last 18 hours</span>
                </div>
                
                <div class="mb-4 flex items-center gap-3">
                    <span class="text-2xl font-bold text-[#1a3d32]" id="qvPrice">${formatQVPrice(i)}</span>
                    <span id="qvOriginalPriceContainer" class="${a?"":"hidden"}">
                        <span class="text-gray-400 font-bold line-through text-lg" id="qvOriginalPrice">${a?formatQVPrice(a):""}</span>
                    </span>
                </div>

                <p id="qvDesc" class="text-gray-600 text-sm mb-6 leading-relaxed">
                    ${t.short_description || (t.description ? (t.description.length > 150 ? t.description.substring(0,150) + "..." : t.description) : "No description available.")}
                </p>

                <div class="space-y-2 mb-6 text-sm text-gray-700">
                    ${t.highlights&&t.highlights.length>0?t.highlights.map(t=>`
                            <div class="flex items-start gap-2">
                                <i class="${t.icon||"fas fa-check"} text-primary mt-0.5"></i>
                                <span>${t.text}</span>
                            </div>
                        `).join(""):""}
                </div>

                <div id="qvVariants" class="mb-6 border-t border-gray-100 pt-4">
                    ${v}
                </div>

                <div class="flex flex-col gap-3 mb-6">
                    <div class="flex gap-3 h-12">
                        <div id="qvQuantityContainer" class="flex items-center border border-black rounded-full w-28 h-full shrink-0 overflow-hidden">
                            <button onclick="updateQVQuantity(-1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">-</button>
                            <input type="text" id="qvQuantity" value="${"out_of_stock"===t.stock_status||void 0!==t.stock_quantity&&t.stock_quantity<=0?0:1}" class="w-full flex-1 text-center border-none focus:ring-0 outline-none focus:outline-none p-0 h-full text-black font-bold text-lg bg-transparent shadow-none" readonly>
                            <button onclick="updateQVQuantity(1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-100 text-black transition text-lg font-medium focus:outline-none">+</button>
                        </div>
                        <button id="qvAddToCartBtn" onclick="addToCartFromQV(${t.product_id||t.id})" 
                                data-product-id="${t.product_id||t.id}"
                                data-product-name="${t.name.replace(/"/g, '&quot;')}"
                                data-product-price="${t.sale_price||t.price}"
                                data-product-slug="${t.slug}"
                                class="flex-1 bg-black text-white h-full rounded-full hover:bg-gray-800 transition-all font-bold uppercase flex items-center justify-center gap-2 shadow-lg text-sm">
                            <i class="fas fa-shopping-cart"></i> <span>Add to Cart</span>
                        </button>
                    </div>
                    <button id="qvBuyNowBtn" onclick="buyNowFromQV(${t.product_id||t.id})" 
                            data-product-id="${t.product_id||t.id}"
                            data-product-name="${t.name.replace(/"/g, '&quot;')}"
                            data-product-price="${t.sale_price||t.price}"
                            data-product-slug="${t.slug}"
                            class="w-full bg-red-700 text-white h-12 rounded-full hover:bg-red-800 transition-all font-bold uppercase shadow-lg text-sm">
                        Buy It Now
                    </button>
                </div>

                <!-- Availability Status (Dynamic Quantity Display) -->
                <div class="mb-6 flex items-center space-x-2" id="qvStockCountContainer">
                    ${renderStockCountHTML(t.stock_status,t.stock_quantity,t.total_sales)}
                </div>
                
                <div id="qvActionsContainer" class="flex gap-4 text-gray-500 mb-6 font-medium">
                    <button class="hover:text-black flex items-center gap-1 transition wishlist-btn quick-view text-xs md:text-sm" 
                            data-product-id="${t.product_id||t.id}">
                        <i class="${m} fa-heart"></i> ${g}
                    </button>
                    <button class="hover:text-black flex items-center gap-1 transition text-xs md:text-sm" onclick="sharePage('${t.name.replace(/'/g,"\\'")}', 'Check out this product!', '${d}')">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                    <button class="hover:text-black flex items-center gap-1 transition text-xs md:text-sm" onclick="toggleAskQuestionModal(true, '${t.name.replace(/'/g,"\\'")}')">
                        <i class="fas fa-question-circle"></i> Ask a question
                    </button>
                </div>

                <div id="qvPolicyBox" class="border-t border-gray-100 pt-4 space-y-2 text-sm text-gray-600 bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center gap-2 text-primary">
                         <i class="fas fa-box"></i>
                         <span class="font-medium">Pickup available at Shop location. Usually ready in 24 hours</span>
                    </div>
                    <div class="mt-2 pt-2 border-t border-gray-200">
                        <p><span class="font-bold text-gray-900">Sku:</span> <span id="qvSku">${t.sku||"N/A"}</span></p>
                        <p><span class="font-bold text-gray-900">Available:</span> <span id="qvAvailability" class="${"in_stock"===t.stock_status&&(void 0===t.stock_quantity||t.stock_quantity>0)?"text-primary":"text-red-500"} font-bold">${getStockStatusText(t.stock_status,t.stock_quantity,t.total_sales)}</span></p>
                    </div>
                    <div class="mt-2 text-right">
                        <a href="${d}" class="text-primary hover:text-black underline font-bold text-xs uppercase tracking-wide">View full details <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    `,document.querySelectorAll("#qvVariants .qv-variant-btn").forEach(t=>{let e=t.parentElement.children;t===e[0]&&t.click()}),!t.variants||0===t.variants.length){    let b="out_of_stock"===t.stock_status||void 0!==t.stock_quantity&&t.stock_quantity<=0;updateQVButtons(b,getStockStatusText(t.stock_status,t.stock_quantity,t.total_sales))}n.length>1&&loadSwiperIfNeeded(()=>{new Swiper(".qv-thumbnail-slider",{slidesPerView:4,spaceBetween:10,navigation:{nextEl:".swiper-button-next",prevEl:".swiper-button-prev"},breakpoints:{640:{slidesPerView:4}}})})}function findMatchingQVVariant(){return currentQVProduct&&currentQVProduct.variants?currentQVProduct.variants.find(t=>{let e=t.attributes;return Object.keys(qvSelectedOptions).every(t=>e[t]===qvSelectedOptions[t])}):null}function loadSwiperIfNeeded(t){if("undefined"!=typeof Swiper){t();return}if(!document.querySelector('link[href*="swiper-bundle.min.css"]')){let e=document.createElement("link");e.rel="stylesheet",e.href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css",document.head.appendChild(e)}let i=document.createElement("script");i.src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js",i.onload=t,document.body.appendChild(i)}window.switchQVImage=function(url, btn, isVideo = false) {
    const mainImg = document.getElementById("qvMainImage");
    const mainVideo = document.getElementById("qvMainVideo");
    
    // Auto-detect if needed
    if (typeof isVideo !== 'boolean') {
         const ext = url.split('.').pop().toLowerCase();
         isVideo = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'].includes(ext);
    }
    
    if (isVideo) {
        if (mainImg) mainImg.classList.add('hidden');
        if (mainVideo) {
            mainVideo.src = url;
            mainVideo.classList.remove('hidden');
            mainVideo.play().catch(e => { /* Autoplay prevented */ });
        }
    } else {
        if (mainVideo) {
            mainVideo.pause();
            mainVideo.classList.add('hidden');
        }
        if (mainImg) {
            mainImg.src = url;
            mainImg.classList.remove('hidden');
            mainImg.classList.remove('object-contain');
            mainImg.classList.add('object-cover');
        }
    }
    
    document.querySelectorAll(".qv-thumb").forEach(t=>{
        t.classList.remove("border-primary");
        t.classList.add("border-transparent");
    });
    
    if (btn) {
        btn.classList.remove("border-transparent");
        btn.classList.add("border-primary");
    }
},window.selectQVVariant=function(t,e,i){qvSelectedOptions[e]=i;let a=t.closest(".mb-5").querySelector(".qv-option-val-display");a&&(a.innerHTML=i);let s=t.parentElement.querySelectorAll(".qv-variant-btn");if(s.forEach(t=>{t.classList.remove("bg-[#154D35]","text-white","border-[#154D35]"),t.classList.add("bg-white","text-gray-700","border-gray-300")}),t.classList.remove("bg-white","text-gray-700","border-gray-300"),t.classList.add("bg-[#154D35]","text-white","border-[#154D35]"),currentQVProduct&&currentQVProduct.variants){let n=findMatchingQVVariant();if(n){let l=parseFloat(n.sale_price||n.price),r=n.sale_price?parseFloat(n.price):null,o=document.getElementById("qvPrice"),c=document.getElementById("qvOriginalPrice"),d=document.getElementById("qvOriginalPriceContainer"),u=document.getElementById("qvDiscountBadge");o&&(o.innerHTML=formatQVPrice(l)),c&&r?(c.innerHTML=formatQVPrice(r),d.classList.remove("hidden"),u&&(u.innerHTML=`<span class="bg-red-500 text-white px-2 py-1 text-xs font-bold rounded">-${Math.round((r-l)/r*100)}%</span>`)):(d&&d.classList.add("hidden"),u&&(u.innerHTML=""));let p=n.image||currentQVProduct.image;if(p){let f=document.querySelector(`.qv-thumb[data-img-url="${p}"]`);const ext = p.split('.').pop().toLowerCase();const isVideo = ['mp4','webm','ogg','mov','avi','mkv','m4v'].includes(ext);switchQVImage(p,f,isVideo)}let v=document.getElementById("qvSku");v&&(v.innerHTML=n.sku||currentQVProduct.sku||"N/A");let m=document.getElementById("qvAvailability"),g=document.getElementById("qvStockCountContainer");if(m){let b=getStockStatusText(n.stock_status,n.stock_quantity,currentQVProduct.total_sales),$="out_of_stock"===n.stock_status||n.stock_quantity<=0;m.innerHTML=b,m.className=$?"text-red-500 font-bold":"text-primary font-bold",updateQVButtons($,b),g&&(g.innerHTML=renderStockCountHTML(n.stock_status,n.stock_quantity,currentQVProduct.total_sales));let y=document.getElementById("qvQuantity");if(y&&void 0!==n.stock_quantity&&null!==n.stock_quantity){let h=parseInt(y.value);h>n.stock_quantity&&(y.value=Math.max(0,n.stock_quantity))}}}}},window.updateQVQuantity=function(t){let e=document.getElementById("qvQuantity");if(!e)return;let i=parseInt(e.value)+t;let a=9999;if(currentQVProduct){if(currentQVProduct.variants&&currentQVProduct.variants.length>0){let s=findMatchingQVVariant();s&&void 0!==s.stock_quantity&&null!==s.stock_quantity&&(a=parseInt(s.stock_quantity))}else void 0!==currentQVProduct.stock_quantity&&null!==currentQVProduct.stock_quantity&&(a=parseInt(currentQVProduct.stock_quantity))}if(a>0){i>a&&(i=a,"function"==typeof showNotification&&t>0&&showNotification(`Only ${a} items available in stock.`));i<1&&(i=1)}else{i=0}e.value=i},window.addToCartFromQV=function(t){let e=parseInt(document.getElementById("qvQuantity").value),i=document.querySelector('#quickViewModal button[onclick*="addToCartFromQV"]'),a=i.innerHTML;if("function"==typeof window.addToCart){i.innerHTML='<i class="fas fa-spinner fa-spin"></i> Adding...',i.disabled=!0,i.classList.add("opacity-75","cursor-not-allowed");let s=window.addToCart(t,e,i,qvSelectedOptions);s&&"function"==typeof s.then?s.then(t=>{t&&t.success?closeQuickView():(i.innerHTML=a,i.disabled=!1,i.classList.remove("opacity-75","cursor-not-allowed"))}).catch(()=>{i.innerHTML=a,i.disabled=!1,i.classList.remove("opacity-75","cursor-not-allowed")}):setTimeout(()=>{i.innerHTML=a,i.disabled=!1,i.classList.remove("opacity-75","cursor-not-allowed")},1e3)}},window.buyNowFromQV=function(t){let e=parseInt(document.getElementById("qvQuantity").value)||1,i=document.querySelector('#quickViewModal button[onclick*="buyNowFromQV"]');i&&setBtnLoading(i,!0);let a="undefined"!=typeof BASE_URL?BASE_URL:window.location.pathname.split("/").slice(0,-1).join("/")||"";fetch(a+"/api/cart.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({product_id:t,quantity:e,variant_attributes:qvSelectedOptions})}).then(t=>t.json()).then(t=>{t.success?window.location.href="checkout":("function"==typeof showNotification&&showNotification(t.message||"Failed to add product to cart"),i&&setBtnLoading(i,!1))}).catch(t=>{console.error("Error:",t),i&&setBtnLoading(i,!1)})},window.updateQVButtons=function(t,e="Out of Stock"){let i=document.querySelector('#quickViewModal button[onclick*="addToCartFromQV"]'),a=document.querySelector('#quickViewModal button[onclick*="buyNowFromQV"]');t?(i&&(i.disabled=!0,i.classList.add("opacity-50","cursor-not-allowed"),i.innerHTML=`<span>${e}</span>`),a&&(a.disabled=!0,a.classList.add("opacity-50","cursor-not-allowed"))):(i&&(i.disabled=!1,i.classList.remove("opacity-50","cursor-not-allowed"),i.innerHTML='<i class="fas fa-shopping-cart"></i> <span>Add to Cart</span>'),a&&(a.disabled=!1,a.classList.remove("opacity-50","cursor-not-allowed")))};
