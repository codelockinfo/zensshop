var variantOptions = window.variantOptions || [];
var generatedVariants = window.generatedVariants || [];
var commonOptionNames = window.commonOptionNames || ["Size", "Color", "Material", "Style", "Pattern"];

// Assign back to window to ensure global availability
window.variantOptions = variantOptions;
window.generatedVariants = generatedVariants;
window.commonOptionNames = commonOptionNames;

function addVariantOption() {
    if (variantOptions.length >= 2) {
        console.log("Maximum 2 variant options allowed");
        return;
    }

    let index = document.querySelectorAll('.variant-option-card').length; // Use DOM count for new index
    // Note: reindexVariantOptions will fix indices if they are gapped, but for appending, length is fine initial guess.
    // However, safest is to append then reindex.
    
    // We push a placeholder to array, but reindex will overwrite it.
    // variantOptions.push({ name: "", values: [] }); 

    let optionId = `option_${index}`;
    let html = `
        <div class="variant-option-card border border-gray-300 rounded-lg p-4 mb-4" data-option-index="${index}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold option-title">Option ${index + 1}</h4>
                <button type="button" 
                        class="text-red-500 hover:text-red-700 remove-option-btn"
                        onclick="removeVariantOption(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="admin-form-group mb-3">
                <label class="admin-form-label">Option Name</label>
                <div class="flex gap-2">
                    <select class="admin-form-select flex-1 option-name-select" 
                            id="${optionId}_name"
                            onchange="updateVariantOptionName(${index})">
                        <option value="">Select or type...</option>
                        ${commonOptionNames.map(name => `<option value="${name}">${name}</option>`).join("")}
                    </select>
                    <input type="text" 
                           class="admin-form-input flex-1 option-name-custom" 
                           id="${optionId}_name_custom"
                           placeholder="Or type custom name"
                           onchange="updateVariantOptionName(${index})">
                </div>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Option Values</label>
                <div class="tag-input-container" id="${optionId}_tag_container">
                    <div class="tag-input-wrapper">
                        <div class="tag-list" id="${optionId}_tags"></div>
                        <input type="text" 
                               class="tag-input" 
                               id="${optionId}_values"
                               placeholder="Type and press comma to add tag"
                               onkeydown="handleTagInputKeydown(event, ${index})"
                               onblur="handleTagInputBlur(event, ${index})">
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-1">Type values and press comma to create tags</p>
            </div>
        </div>
    `;

    document.getElementById("variantOptionsContainer").insertAdjacentHTML("beforeend", html);
    
    // Check if we need to initialize variantOptions here or just rely on reindex
    // Better to rely on reindex to keep state consistent with DOM
    reindexVariantOptions();
    
    updateAddButtonVisibility();
    setTimeout(() => generateVariants(), 100);
}

function removeVariantOption(index) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (card) {
        card.remove();
        // We don't splice variantOptions here manually because reindexVariantOptions will rebuild it from DOM
        reindexVariantOptions();
        generateVariants();
        updateAddButtonVisibility();
    } else {
        console.error("Variant card not found for index:", index);
        // Fallback: try to reindex anyway in case of mismatch
        reindexVariantOptions();
    }
}

function reindexVariantOptions() {
    let cards = document.querySelectorAll(".variant-option-card");
    let newOptions = [];

    cards.forEach((card, index) => {
        // Update data-option-index
        card.setAttribute("data-option-index", index);
        
        // Update title
        let title = card.querySelector('.option-title');
        if (title) title.textContent = `Option ${index + 1}`;

        let optionId = `option_${index}`;

        // Update Inputs IDs and Events
        let nameSelect = card.querySelector('select.option-name-select');
        let nameCustom = card.querySelector('input.option-name-custom');
        let valuesInput = card.querySelector('input.tag-input');
        let tagsContainer = card.querySelector('.tag-list');

        if (nameSelect) {
            nameSelect.id = `${optionId}_name`;
            nameSelect.setAttribute('onchange', `updateVariantOptionName(${index})`);
        }
        if (nameCustom) {
            nameCustom.id = `${optionId}_name_custom`;
            nameCustom.setAttribute('onchange', `updateVariantOptionName(${index})`);
        }
        if (valuesInput) {
            valuesInput.id = `${optionId}_values`;
            valuesInput.setAttribute('onkeydown', `handleTagInputKeydown(event, ${index})`);
            valuesInput.setAttribute('onblur', `handleTagInputBlur(event, ${index})`);
        }
        if (tagsContainer) {
            tagsContainer.id = `${optionId}_tags`;
            // Update remove tag buttons
            let removeBtns = tagsContainer.querySelectorAll('.tag-remove');
            removeBtns.forEach(btn => {
                // We need the value
                let tagItem = btn.closest('.tag-item');
                if (tagItem) {
                    let val = tagItem.getAttribute('data-value');
                    btn.setAttribute('onclick', `removeTag(${index}, '${escapeHtml(val)}')`);
                }
            });
        }

        // Update Remove Option Button
        let removeBtn = card.querySelector('.remove-option-btn');
        if (removeBtn) {
            removeBtn.setAttribute('onclick', `removeVariantOption(${index})`);
        }

        // Rebuild Options Data
        let nameVal = (nameSelect && nameSelect.value) || (nameCustom && nameCustom.value) || "";
        let tagItems = tagsContainer ? tagsContainer.querySelectorAll(".tag-item") : [];
        let values = Array.from(tagItems).map(item => item.getAttribute("data-value"));
        
        if (nameVal || values.length > 0) {
            newOptions.push({ name: nameVal, values: values });
        }
        
        // Push empty placeholder if it's a fresh card to keep index alignment? 
        // Actually valid options should correspond to cards.
        // If the card is empty, we still want it to exist in variantOptions usually, 
        // to track that there is a card there.
        if (!nameVal && values.length === 0) {
             newOptions.push({ name: "", values: [] });
        }
    });

    variantOptions = newOptions;
}

function updateVariantOptionName(index) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (!card) return;

    let nameSelect = card.querySelector('select.option-name-select');
    let nameCustom = card.querySelector('input.option-name-custom');
    let name = (nameSelect && nameSelect.value) || (nameCustom && nameCustom.value) || "";

    if (variantOptions[index]) {
        variantOptions[index].name = name;
    }
    generateVariants();
}

function handleTagInputKeydown(e, index) {
    if (e.key === "," || e.key === "Enter") {
        e.preventDefault();
        addTagFromInput(index);
    } else if (e.key === "Backspace") {
        let input = e.target;
        if (input.value === "") {
            removeLastTag(index);
        }
    }
}

function handleTagInputBlur(e, index) {
    let input = e.target;
    if (input.value.trim()) {
        addTagFromInput(index);
    }
}

function addTagFromInput(index) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (!card) return;

    let input = card.querySelector('input.tag-input');
    let tagsList = card.querySelector('.tag-list');
    let value = input.value.trim();

    if (!value) return;

    let currentTags = Array.from(tagsList.querySelectorAll(".tag-item")).map(item => item.textContent.trim());
    if (currentTags.includes(value)) {
        input.value = "";
        return;
    }

    let html = `
        <span class="tag-item" data-value="${escapeHtml(value)}">
            <span class="tag-text">${escapeHtml(value)}</span>
            <button type="button" class="tag-remove" onclick="removeTag(${index}, '${escapeHtml(value)}')">
                <i class="fas fa-times"></i>
            </button>
        </span>
    `;
    tagsList.insertAdjacentHTML("beforeend", html);
    input.value = "";
    updateVariantOptionValues(index);
}

function removeTag(index, value) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (!card) return;
    
    let tagsList = card.querySelector('.tag-list');
    // Using attribute selector for reliability with spaces/special chars
    let tag = tagsList.querySelector(`.tag-item[data-value="${escapeHtml(value)}"]`);
    if (tag) {
        tag.remove();
        updateVariantOptionValues(index);
    }
}

function removeLastTag(index) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (!card) return;

    let tagsList = card.querySelector('.tag-list');
    let tags = tagsList.querySelectorAll(".tag-item");
    if (tags.length > 0) {
        let lastTag = tags[tags.length - 1];
        let val = lastTag.getAttribute("data-value");
        removeTag(index, val);
    }
}

function escapeHtml(text) {
    if (!text) return text;
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function updateVariantOptionValues(index) {
    let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
    if (!card) return;

    let tagsList = card.querySelector('.tag-list');
    let tags = tagsList.querySelectorAll(".tag-item");
    let values = Array.from(tags).map(item => item.getAttribute("data-value"));

    if (variantOptions[index]) {
        variantOptions[index].values = values;
    }
    generateVariants();
}

function updateAddButtonVisibility() {
    let btn = document.getElementById("addVariantOptionBtn");
    if (btn) {
        let count = document.querySelectorAll('.variant-option-card').length;
        btn.style.display = count >= 2 ? "none" : "block";
    }
}

function generateVariants() {
    // Re-sync variantOptions from DOM before generating to be safe
    // Actually reindexVariantOptions does this, but we can do a lightweight sync here if needed.
    // But rely on state being kept up to date by event handlers.
    
    // Safety check: if variantOptions is empty but DOM has cards?
    // reindexVariantOptions(); // Maybe too heavy to run on every generate?
    // Let's rely on event handlers calling updateVariantOptionValues/Name which update state.

    // Filter out options with no values
    let activeOptions = variantOptions.filter(opt => opt.name && opt.values.length > 0);
    
    if (activeOptions.length === 0) {
        generatedVariants = [];
        renderVariantsTable();
        updateVariantsDataInput(); // Clear data
        return;
    }

    let previousVariants = [...generatedVariants];
    let combinations = generateCombinations(activeOptions);

    generatedVariants = combinations.map((combo, index) => {
        // Try to find existing variant with same attributes to preserve data
        let existing = previousVariants.find(v => v.attributes && isEquivalent(v.attributes, combo.attributes));
        
        // Improved matching logic: if simple match fails, try fuzzy matching (e.g. key order diff)
        // isEquivalent handles key order.
        
        if (existing) {
             return {
                 ...existing,
                 id: (existing.id && existing.id.toString().startsWith("variant_")) ? `variant_${index}_${Date.now()}` : existing.id,
                 attributes: combo.attributes
             };
        }
        
        return {
            id: `variant_${index}_${Date.now()}`,
            attributes: combo.attributes,
            sku: "",
            price: "",
            sale_price: "",
            stock_quantity: 0,
            stock_status: "in_stock",
            image: "",
            barcode: "",
            is_default: (index === 0) ? 1 : 0
        };
    });

    renderVariantsTable();
    updateVariantsDataInput();
}

function isEquivalent(a, b) {
    if (!a || !b) return false;
    var aProps = Object.getOwnPropertyNames(a);
    var bProps = Object.getOwnPropertyNames(b);
    if (aProps.length != bProps.length) return false;
    for (var i = 0; i < aProps.length; i++) {
        var propName = aProps[i];
        if (a[propName] !== b[propName]) return false;
    }
    return true;
}

function generateCombinations(options) {
    if (options.length === 0) return [];
    if (options.length === 1) {
        return options[0].values.map(val => ({
            attributes: { [options[0].name]: val }
        }));
    }
    
    let result = [];
    let opt1 = options[0];
    let opt2 = options[1]; // Max 2 options supported logic

    opt1.values.forEach(val1 => {
        opt2.values.forEach(val2 => {
             result.push({
                 attributes: {
                     [opt1.name]: val1,
                     [opt2.name]: val2
                 }
             });
        });
    });

    return result;
}

function renderVariantsTable() {
    let container = document.getElementById("variantsTableContainer");
    let tbody = document.getElementById("variantsTableBody");

    if (!container || !tbody) return;

    if (generatedVariants.length === 0) {
        container.classList.add("hidden");
        return;
    }

    container.classList.remove("hidden");
    tbody.innerHTML = generatedVariants.map((variant, index) => {
        let attrString = Object.entries(variant.attributes)
            .map(([key, val]) => `${key}: ${val}`)
            .join(" / ");
            
        return `
            <tr class="variant-row" data-variant-index="${index}">
                <td class="border border-gray-300 px-3 py-2">
                    <strong>${attrString}</strong>
                    <input type="hidden" 
                           class="variant-attributes" 
                           value='${JSON.stringify(variant.attributes)}'>
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="text" 
                           class="admin-form-input variant-sku w-full" 
                           value="${variant.sku || ""}"
                           placeholder="SKU"
                           onchange="updateVariantField(${index}, 'sku', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                     <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                        <input type="number" 
                               step="0.01"
                               class="admin-form-input variant-price w-full pl-8" 
                               value="${variant.price || ""}"
                               placeholder="0.00"
                               onchange="updateVariantField(${index}, 'price', this.value)">
                    </div>
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                        <input type="number" 
                               step="0.01"
                               class="admin-form-input variant-sale-price w-full pl-8" 
                               value="${variant.sale_price || ""}"
                               placeholder="0.00"
                               onchange="updateVariantField(${index}, 'sale_price', this.value)">
                    </div>
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           class="admin-form-input variant-stock w-full" 
                           value="${variant.stock_quantity || 0}"
                           placeholder="Stock"
                           onchange="updateVariantField(${index}, 'stock_quantity', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <div class="flex items-center justify-center">
                        <div class="variant-image-preview-container bg-gray-100 rounded border border-gray-200 w-12 h-12 flex-shrink-0 flex items-center justify-center overflow-hidden cursor-pointer hover:bg-gray-200 transition-colors" 
                             onclick="document.getElementById('variant_file_input_${index}').click()" 
                             title="Click to upload image">
                            ${variant.image ? `<img src="${variant.image}" class="w-full h-full object-cover">` : '<i class="fas fa-image text-gray-400"></i>'}
                        </div>
                        <input type="file" 
                               id="variant_file_input_${index}" 
                               class="hidden" 
                               accept="image/*"
                               onchange="uploadVariantImage(${index}, this.files)">
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function uploadVariantImage(index, files) {
    if (files.length === 0) return;
    let file = files[0];
    
    if (!file.type.startsWith("image/")) {
        alert("Please select only image files.");
        return;
    }

    let previewContainer = document.querySelector(`[data-variant-index="${index}"] .variant-image-preview-container`);
    if (previewContainer) {
        previewContainer.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-500"></i>';
    }

    let formData = new FormData();
    formData.append("image", file);

    let baseUrl = (typeof BASE_URL !== "undefined") ? BASE_URL : "";

    fetch(baseUrl + "/admin/api/upload.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateVariantField(index, "image", data.path);
            renderVariantsTable();
        } else {
            alert(data.message || "Failed to upload image");
            renderVariantsTable(); // Restore icon
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred while uploading.");
        renderVariantsTable();
    });
}

function updateVariantField(index, field, value) {
    if (generatedVariants[index]) {
        generatedVariants[index][field] = value;
        updateVariantsDataInput();
    }
}

function updateVariantsDataInput() {
    let input = document.getElementById("variantsDataInput");
    if (input) {
        input.value = JSON.stringify({
            options: variantOptions,
            variants: generatedVariants
        });
    }
}

// Function that was missing and causing issues
function initializeVariantsFromData(data) {
    // console.log("Initializing variants from:", data);
    
    // Clear existing
    document.getElementById("variantOptionsContainer").innerHTML = "";
    variantOptions = [];
    generatedVariants = [];

    if (data.options && Array.isArray(data.options)) {
        data.options.forEach(opt => {
            // Add DOM element
            addVariantOption(); 
            // The addVariantOption adds a blank one at the end. We need to fill it.
            let index = variantOptions.length - 1;
            
            // Fill Name
            let card = document.querySelector(`.variant-option-card[data-option-index="${index}"]`);
            if (card) {
                let nameSelect = card.querySelector('select.option-name-select');
                let nameCustom = card.querySelector('input.option-name-custom');
                
                if (commonOptionNames.includes(opt.name)) {
                    if (nameSelect) nameSelect.value = opt.name;
                } else {
                     if (nameSelect) nameSelect.value = ""; // Custom
                     if (nameCustom) nameCustom.value = opt.name;
                }
                
                // Fill Values (Tags)
                if (opt.values && Array.isArray(opt.values)) {
                    opt.values.forEach(val => {
                         let tagsList = card.querySelector('.tag-list');
                         let html = `
                            <span class="tag-item" data-value="${escapeHtml(val)}">
                                <span class="tag-text">${escapeHtml(val)}</span>
                                <button type="button" class="tag-remove" onclick="removeTag(${index}, '${escapeHtml(val)}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </span>
                        `;
                        tagsList.insertAdjacentHTML("beforeend", html);
                    });
                }
            }
        });
        
        // Sync state
        reindexVariantOptions();
    }
    
    if (data.variants && Array.isArray(data.variants)) {
        generatedVariants = data.variants;
        renderVariantsTable();
        updateVariantsDataInput();
    } else {
        generateVariants();
    }
}

document.addEventListener("DOMContentLoaded", updateAddButtonVisibility);
document.addEventListener("adminPageLoaded", updateAddButtonVisibility);