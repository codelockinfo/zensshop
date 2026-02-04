let variantOptions=[],generatedVariants=[];const commonOptionNames=["Size","Color","Material","Style","Pattern"];function addVariantOption(){if(variantOptions.length>=2){console.log("Maximum 2 variant options allowed");return}let e=variantOptions.length,t=`option_${e}`,a=`
        <div class="variant-option-card border border-gray-300 rounded-lg p-4" data-option-index="${e}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold">Option ${e+1}</h4>
                <button type="button" 
                        class="text-red-500 hover:text-red-700"
                        onclick="removeVariantOption(${e})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="admin-form-group mb-3">
                <label class="admin-form-label">Option Name</label>
                <div class="flex gap-2">
                    <select class="admin-form-select flex-1" 
                            id="${t}_name"
                            onchange="updateVariantOptionName(${e})">
                        <option value="">Select or type...</option>
                        ${commonOptionNames.map(e=>`<option value="${e}">${e}</option>`).join("")}
                    </select>
                    <input type="text" 
                           class="admin-form-input flex-1" 
                           id="${t}_name_custom"
                           placeholder="Or type custom name"
                           onchange="updateVariantOptionName(${e})">
                </div>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Option Values</label>
                <div class="tag-input-container" id="${t}_tag_container">
                    <div class="tag-input-wrapper">
                        <div class="tag-list" id="${t}_tags"></div>
                        <input type="text" 
                               class="tag-input" 
                               id="${t}_values"
                               placeholder="Type and press comma to add tag"
                               onkeydown="handleTagInputKeydown(event, ${e})"
                               onblur="handleTagInputBlur(event, ${e})">
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-1">Type values and press comma to create tags</p>
            </div>
        </div>
    `;document.getElementById("variantOptionsContainer").insertAdjacentHTML("beforeend",a),variantOptions.push({name:"",values:[]}),updateAddButtonVisibility(),setTimeout(()=>generateVariants(),100)}function removeVariantOption(e){let t=document.querySelector(`[data-option-index="${e}"]`);t&&t.remove(),variantOptions.splice(e,1),reindexVariantOptions(),generateVariants(),updateAddButtonVisibility()}function reindexVariantOptions(){let e=document.querySelectorAll(".variant-option-card"),t=[];e.forEach((e,a)=>{e.setAttribute("data-option-index",a);let n=`option_${a}`,i=e.querySelector('select[id$="_name"]'),r=e.querySelector('input[id$="_name_custom"]'),l=e.querySelector('input[id$="_values"]');i&&(i.id=`${n}_name`),r&&(r.id=`${n}_name_custom`),l&&(l.id=`${n}_values`),i&&(i.onchange=()=>updateVariantOptionName(a)),r&&(r.onchange=()=>updateVariantOptionName(a)),l&&(l.onkeydown=e=>handleTagInputKeydown(e,a),l.onblur=e=>handleTagInputBlur(e,a));let o=e.querySelectorAll(".tag-remove");o.forEach(e=>{let t=e.closest(".tag-item");if(t){let n=t.getAttribute("data-value");e.setAttribute("onclick",`removeTag(${a}, '${escapeHtml(n)}')`)}});let s=e.querySelector('button[onclick*="removeVariantOption"]');s&&s.setAttribute("onclick",`removeVariantOption(${a})`);let u=(i?.value||r?.value||"").trim(),d=e.querySelector('[id$="_tags"]'),p=d?.querySelectorAll(".tag-item")||[],c=Array.from(p).map(e=>e.getAttribute("data-value"));(u||c.length>0)&&t.push({name:u,values:c})}),variantOptions=t}function updateVariantOptionName(e){let t=document.querySelector(`[data-option-index="${e}"]`);if(!t)return;let a=t.querySelector('[id$="_name"]'),n=t.querySelector('[id$="_name_custom"]'),i=a?.value||n?.value||"";variantOptions[e]&&(variantOptions[e].name=i),generateVariants()}function handleTagInputKeydown(e,t){if(","===e.key||"Enter"===e.key)e.preventDefault(),addTagFromInput(t);else if("Backspace"===e.key){let a=e.target;""===a.value&&removeLastTag(t)}}function handleTagInputBlur(e,t){let a=e.target;a.value.trim()&&addTagFromInput(t)}function addTagFromInput(e){let t=document.querySelector(`[data-option-index="${e}"]`);if(!t)return;let a=t.querySelector('[id$="_values"]'),n=t.querySelector('[id$="_tags"]'),i=a.value.trim();if(!i)return;let r=Array.from(n.querySelectorAll(".tag-item")).map(e=>e.textContent.trim());if(r.includes(i)){a.value="";return}let l=`
        <span class="tag-item" data-value="${escapeHtml(i)}">
            <span class="tag-text">${escapeHtml(i)}</span>
            <button type="button" class="tag-remove" onclick="removeTag(${e}, '${escapeHtml(i)}')">
                <i class="fas fa-times"></i>
            </button>
        </span>
    `;n.insertAdjacentHTML("beforeend",l),a.value="",updateVariantOptionValues(e)}function removeTag(e,t){let a=document.querySelector(`[data-option-index="${e}"]`);if(!a)return;let n=a.querySelector('[id$="_tags"]'),i=n.querySelector(`[data-value="${escapeHtml(t)}"]`);i&&(i.remove(),updateVariantOptionValues(e))}function removeLastTag(e){let t=document.querySelector(`[data-option-index="${e}"]`);if(!t)return;let a=t.querySelector('[id$="_tags"]'),n=a.querySelectorAll(".tag-item");if(n.length>0){let i=n[n.length-1],r=i.getAttribute("data-value");removeTag(e,r)}}function escapeHtml(e){let t=document.createElement("div");return t.textContent=e,t.innerHTML}function updateVariantOptionValues(e){let t=document.querySelector(`[data-option-index="${e}"]`);if(!t)return;let a=t.querySelector('[id$="_tags"]'),n=a.querySelectorAll(".tag-item"),i=Array.from(n).map(e=>e.getAttribute("data-value"));variantOptions[e]&&(variantOptions[e].values=i),generateVariants()}function updateAddButtonVisibility(){let e=document.getElementById("addVariantOptionBtn");e&&(e.style.display=variantOptions.length>=2?"none":"block")}function generateVariants(){variantOptions=[];let e=document.querySelectorAll(".variant-option-card");if(e.forEach((e,t)=>{let a=e.querySelector('[id$="_name"]'),n=e.querySelector('[id$="_name_custom"]'),i=e.querySelector('[id$="_tags"]'),r=(a?.value||n?.value||"Option "+(t+1)).trim(),l=i?.querySelectorAll(".tag-item")||[],o=Array.from(l).map(e=>e.getAttribute("data-value"));r&&o.length>0&&variantOptions.push({name:r,values:o})}),0===variantOptions.length){generatedVariants=[],renderVariantsTable();return}let t=[...generatedVariants],a=generateCombinations(variantOptions);generatedVariants=a.map((e,a)=>{let n=t.find(t=>t.attributes&&isEquivalent(t.attributes,e.attributes));return n||(n=t.find(t=>{let a=t.attributes,n=e.attributes;if(!a||!n)return!1;let i=Object.keys(a),r=Object.keys(n);if(0===i.length||0===r.length)return!1;let l=i.length<=r.length?a:n,o=i.length<=r.length?n:a,s=Object.keys(l);return s.every(e=>l[e]===o[e])})),n?{...n,id:n.id&&n.id.toString().startsWith("variant_")?`variant_${a}_${Date.now()}`:n.id,attributes:e.attributes}:{id:`variant_${a}_${Date.now()}`,attributes:e.attributes,sku:"",price:"",sale_price:"",stock_quantity:0,stock_status:"in_stock",image:"",barcode:"",is_default:0===a?1:0}}),renderVariantsTable(),updateVariantsDataInput()}function isEquivalent(e,t){if(!e||!t)return!1;var a=Object.getOwnPropertyNames(e),n=Object.getOwnPropertyNames(t);if(a.length!=n.length)return!1;for(var i=0;i<a.length;i++){var r=a[i];if(e[r]!==t[r])return!1}return!0}function generateCombinations(e){if(0===e.length)return[];if(1===e.length)return e[0].values.map(t=>({attributes:{[e[0].name]:t}}));let t=[],a=e[0],n=e[1];return a.values.forEach(e=>{n.values.forEach(i=>{t.push({attributes:{[a.name]:e,[n.name]:i}})})}),t}function renderVariantsTable(){let e=document.getElementById("variantsTableContainer"),t=document.getElementById("variantsTableBody");if(e&&t){if(0===generatedVariants.length){e.classList.add("hidden");return}e.classList.remove("hidden"),t.innerHTML=generatedVariants.map((e,t)=>{let a=Object.entries(e.attributes).map(([e,t])=>`${e}: ${t}`).join(" / ");return`
            <tr class="variant-row" data-variant-index="${t}">
                <td class="border border-gray-300 px-3 py-2">
                    <strong>${a}</strong>
                    <input type="hidden" 
                           class="variant-attributes" 
                           value='${JSON.stringify(e.attributes)}'>
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="text" 
                           class="admin-form-input variant-sku w-full" 
                           value="${e.sku||""}"
                           placeholder="SKU"
                           onchange="updateVariantField(${t}, 'sku', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           step="0.01"
                           class="admin-form-input variant-price w-full" 
                           value="${e.price||""}"
                           placeholder="Price"
                           onchange="updateVariantField(${t}, 'price', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           step="0.01"
                           class="admin-form-input variant-sale-price w-full" 
                           value="${e.sale_price||""}"
                           placeholder="Sale Price"
                           onchange="updateVariantField(${t}, 'sale_price', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           class="admin-form-input variant-stock w-full" 
                           value="${e.stock_quantity||0}"
                           placeholder="Stock"
                           onchange="updateVariantField(${t}, 'stock_quantity', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <div class="flex items-center justify-center">
                        <div class="variant-image-preview-container bg-gray-100 rounded border border-gray-200 w-12 h-12 flex-shrink-0 flex items-center justify-center overflow-hidden cursor-pointer hover:bg-gray-200 transition-colors" 
                             onclick="document.getElementById('variant_file_input_${t}').click()" 
                             title="Click to upload image">
                            ${e.image?`<img src="${e.image}" class="w-full h-full object-cover">`:'<i class="fas fa-image text-gray-400"></i>'}
                        </div>
                        <input type="file" 
                               id="variant_file_input_${t}" 
                               class="hidden" 
                               accept="image/*"
                               onchange="uploadVariantImage(${t}, this.files)">
                    </div>
                </td>
            </tr>
        `}).join("")}}function uploadVariantImage(e,t){if(0===t.length)return;let a=t[0];if(!a.type.startsWith("image/")){console.log("Please select only image files.");return}let n=document.querySelector(`[data-variant-index="${e}"] .variant-image-preview-container`);n&&(n.innerHTML='<i class="fas fa-spinner fa-spin text-blue-500"></i>');let i=new FormData;i.append("image",a);let r="undefined"!=typeof BASE_URL?BASE_URL:"";fetch(r+"/admin/api/upload.php",{method:"POST",body:i}).then(e=>e.json()).then(t=>{t.success?(updateVariantField(e,"image",t.path),renderVariantsTable()):(console.log(t.message||"Failed to upload image"),renderVariantsTable())}).catch(e=>{console.error(e),console.log("An error occurred while uploading."),renderVariantsTable()})}function updateVariantField(e,t,a){generatedVariants[e]&&(generatedVariants[e][t]=a,updateVariantsDataInput())}function updateVariantsDataInput(){let e=document.getElementById("variantsDataInput");e&&(e.value=JSON.stringify({options:variantOptions,variants:generatedVariants}))}document.addEventListener("DOMContentLoaded",function(){updateAddButtonVisibility()});