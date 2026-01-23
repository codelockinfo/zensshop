/**
 * Product Variants Management
 * Shopify-like variant creation system
 */

let variantOptions = []; // Array of {name: 'Size', values: ['Small', 'Medium']}
let generatedVariants = []; // Array of variant objects

// Predefined option names for quick selection
const commonOptionNames = ['Size', 'Color', 'Material', 'Style', 'Pattern'];

/**
 * Add a new variant option (e.g., Size, Color)
 */
function addVariantOption() {
    if (variantOptions.length >= 2) {
        alert('Maximum 2 variant options allowed');
        return;
    }
    
    const optionIndex = variantOptions.length;
    const optionId = `option_${optionIndex}`;
    
    const optionHtml = `
        <div class="variant-option-card border border-gray-300 rounded-lg p-4" data-option-index="${optionIndex}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold">Option ${optionIndex + 1}</h4>
                <button type="button" 
                        class="text-red-500 hover:text-red-700"
                        onclick="removeVariantOption(${optionIndex})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="admin-form-group mb-3">
                <label class="admin-form-label">Option Name</label>
                <div class="flex gap-2">
                    <select class="admin-form-select flex-1" 
                            id="${optionId}_name"
                            onchange="updateVariantOptionName(${optionIndex})">
                        <option value="">Select or type...</option>
                        ${commonOptionNames.map(name => `<option value="${name}">${name}</option>`).join('')}
                    </select>
                    <input type="text" 
                           class="admin-form-input flex-1" 
                           id="${optionId}_name_custom"
                           placeholder="Or type custom name"
                           onchange="updateVariantOptionName(${optionIndex})">
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
                               onkeydown="handleTagInputKeydown(event, ${optionIndex})"
                               onblur="handleTagInputBlur(event, ${optionIndex})">
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-1">Type values and press comma to create tags</p>
            </div>
        </div>
    `;
    
    document.getElementById('variantOptionsContainer').insertAdjacentHTML('beforeend', optionHtml);
    
    // Initialize option object
    variantOptions.push({
        name: '',
        values: []
    });
    
    // Update add button visibility
    updateAddButtonVisibility();
    
    // Generate variants when option is added
    setTimeout(() => generateVariants(), 100);
}

/**
 * Remove a variant option
 */
function removeVariantOption(index) {
    const optionCard = document.querySelector(`[data-option-index="${index}"]`);
    if (optionCard) {
        optionCard.remove();
    }
    
    variantOptions.splice(index, 1);
    
    // Re-index remaining options
    reindexVariantOptions();
    
    // Regenerate variants
    generateVariants();
    updateAddButtonVisibility();
}

/**
 * Re-index variant options after removal
 */
function reindexVariantOptions() {
    const cards = document.querySelectorAll('.variant-option-card');
    const newVariantOptions = [];
    
    cards.forEach((card, newIndex) => {
        card.setAttribute('data-option-index', newIndex);
        const optionId = `option_${newIndex}`;
        
        // Find and update IDs
        const nameSelect = card.querySelector('select[id$="_name"]');
        const nameInput = card.querySelector('input[id$="_name_custom"]');
        const valuesInput = card.querySelector('input[id$="_values"]');
        
        if (nameSelect) nameSelect.id = `${optionId}_name`;
        if (nameInput) nameInput.id = `${optionId}_name_custom`;
        if (valuesInput) valuesInput.id = `${optionId}_values`;
        
        // Update event handlers
        if (nameSelect) {
            nameSelect.onchange = () => updateVariantOptionName(newIndex);
        }
        if (nameInput) {
            nameInput.onchange = () => updateVariantOptionName(newIndex);
        }
        if (valuesInput) {
            valuesInput.onkeydown = (e) => handleTagInputKeydown(e, newIndex);
            valuesInput.onblur = (e) => handleTagInputBlur(e, newIndex);
        }
        
        // Update tag remove buttons
        const tagRemoveButtons = card.querySelectorAll('.tag-remove');
        tagRemoveButtons.forEach(btn => {
            const tagItem = btn.closest('.tag-item');
            if (tagItem) {
                const value = tagItem.getAttribute('data-value');
                btn.setAttribute('onclick', `removeTag(${newIndex}, '${escapeHtml(value)}')`);
            }
        });
        
        // Update remove button
        const removeBtn = card.querySelector('button[onclick*="removeVariantOption"]');
        if (removeBtn) {
            removeBtn.setAttribute('onclick', `removeVariantOption(${newIndex})`);
        }
        
        // Collect current option data
        const name = (nameSelect?.value || nameInput?.value || '').trim();
        const tagContainer = card.querySelector(`[id$="_tags"]`);
        const tags = tagContainer?.querySelectorAll('.tag-item') || [];
        const values = Array.from(tags).map(tag => tag.getAttribute('data-value'));
        
        if (name || values.length > 0) {
            newVariantOptions.push({ name, values });
        }
    });
    
    variantOptions = newVariantOptions;
}

/**
 * Update variant option name
 */
function updateVariantOptionName(index) {
    const card = document.querySelector(`[data-option-index="${index}"]`);
    if (!card) return;
    
    const nameSelect = card.querySelector(`[id$="_name"]`);
    const nameInput = card.querySelector(`[id$="_name_custom"]`);
    
    const name = nameSelect?.value || nameInput?.value || '';
    
    if (variantOptions[index]) {
        variantOptions[index].name = name;
    }
    
    generateVariants();
}

/**
 * Handle tag input keydown events
 */
function handleTagInputKeydown(event, index) {
    if (event.key === ',' || event.key === 'Enter') {
        event.preventDefault();
        addTagFromInput(index);
    } else if (event.key === 'Backspace') {
        const input = event.target;
        if (input.value === '') {
            // Remove last tag if input is empty
            removeLastTag(index);
        }
    }
}

/**
 * Handle tag input blur - add tag if there's text
 */
function handleTagInputBlur(event, index) {
    const input = event.target;
    if (input.value.trim()) {
        addTagFromInput(index);
    }
}

/**
 * Add a tag from the input field
 */
function addTagFromInput(index) {
    const card = document.querySelector(`[data-option-index="${index}"]`);
    if (!card) return;
    
    const input = card.querySelector(`[id$="_values"]`);
    const tagContainer = card.querySelector(`[id$="_tags"]`);
    const value = input.value.trim();
    
    if (!value) return;
    
    // Check if tag already exists
    const existingTags = Array.from(tagContainer.querySelectorAll('.tag-item')).map(tag => tag.textContent.trim());
    if (existingTags.includes(value)) {
        input.value = '';
        return;
    }
    
    // Create tag element
    const tagHtml = `
        <span class="tag-item" data-value="${escapeHtml(value)}">
            <span class="tag-text">${escapeHtml(value)}</span>
            <button type="button" class="tag-remove" onclick="removeTag(${index}, '${escapeHtml(value)}')">
                <i class="fas fa-times"></i>
            </button>
        </span>
    `;
    
    tagContainer.insertAdjacentHTML('beforeend', tagHtml);
    input.value = '';
    
    // Update variant options
    updateVariantOptionValues(index);
}

/**
 * Remove a tag
 */
function removeTag(index, value) {
    const card = document.querySelector(`[data-option-index="${index}"]`);
    if (!card) return;
    
    const tagContainer = card.querySelector(`[id$="_tags"]`);
    const tag = tagContainer.querySelector(`[data-value="${escapeHtml(value)}"]`);
    
    if (tag) {
        tag.remove();
        updateVariantOptionValues(index);
    }
}

/**
 * Remove last tag
 */
function removeLastTag(index) {
    const card = document.querySelector(`[data-option-index="${index}"]`);
    if (!card) return;
    
    const tagContainer = card.querySelector(`[id$="_tags"]`);
    const tags = tagContainer.querySelectorAll('.tag-item');
    
    if (tags.length > 0) {
        const lastTag = tags[tags.length - 1];
        const value = lastTag.getAttribute('data-value');
        removeTag(index, value);
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Update variant option values from tags
 */
function updateVariantOptionValues(index) {
    const card = document.querySelector(`[data-option-index="${index}"]`);
    if (!card) return;
    
    const tagContainer = card.querySelector(`[id$="_tags"]`);
    const tags = tagContainer.querySelectorAll('.tag-item');
    const values = Array.from(tags).map(tag => tag.getAttribute('data-value'));
    
    if (variantOptions[index]) {
        variantOptions[index].values = values;
    }
    
    generateVariants();
}

/**
 * Update add button visibility
 */
function updateAddButtonVisibility() {
    const addBtn = document.getElementById('addVariantOptionBtn');
    if (addBtn) {
        addBtn.style.display = variantOptions.length >= 2 ? 'none' : 'block';
    }
}

/**
 * Generate all variant combinations
 */
function generateVariants() {
    // Collect current option data
    variantOptions = [];
    const cards = document.querySelectorAll('.variant-option-card');
    
    cards.forEach((card, index) => {
        const nameSelect = card.querySelector(`[id$="_name"]`);
        const nameInput = card.querySelector(`[id$="_name_custom"]`);
        const tagContainer = card.querySelector(`[id$="_tags"]`);
        
        const name = (nameSelect?.value || nameInput?.value || '').trim();
        const tags = tagContainer?.querySelectorAll('.tag-item') || [];
        const values = Array.from(tags).map(tag => tag.getAttribute('data-value'));
        
        if (name && values.length > 0) {
            variantOptions.push({ name, values });
        }
    });
    
    // Generate combinations
    if (variantOptions.length === 0) {
        generatedVariants = [];
        renderVariantsTable();
        return;
    }
    
    // Store old variants to preserve their data (SKU, Price, etc.)
    const oldVariants = [...generatedVariants];
    
    // Calculate all combinations
    const combinations = generateCombinations(variantOptions);
    
    // Initialize variant data while preserving existing entries
    generatedVariants = combinations.map((variant, idx) => {
        // Try to find a match from old data
        // 1. Exact match
        let existing = oldVariants.find(v => 
            v.attributes && isEquivalent(v.attributes, variant.attributes)
        );
        
        // 2. Sub-match / Super-match (Structural changes)
        // If we added or removed an option box, try to find a variant that shares the same common attributes
        if (!existing) {
            existing = oldVariants.find(v => {
                const attrsA = v.attributes;
                const attrsB = variant.attributes;
                if (!attrsA || !attrsB) return false;
                
                const keysA = Object.keys(attrsA);
                const keysB = Object.keys(attrsB);
                if (keysA.length === 0 || keysB.length === 0) return false;

                // Determine which set of attributes is smaller
                const smaller = keysA.length <= keysB.length ? attrsA : attrsB;
                const larger = keysA.length <= keysB.length ? attrsB : attrsA;
                const smallerKeys = Object.keys(smaller);

                // Check if all attributes in the smaller set match the values in the larger set
                return smallerKeys.every(k => smaller[k] === larger[k]);
            });
        }
        
        return existing ? {
            ...existing,
            id: (existing.id && existing.id.toString().startsWith('variant_')) ? `variant_${idx}_${Date.now()}` : existing.id,
            attributes: variant.attributes // Use the NEW attributes structure
        } : {
            id: `variant_${idx}_${Date.now()}`,
            attributes: variant.attributes,
            sku: '',
            price: '',
            sale_price: '',
            stock_quantity: 0,
            stock_status: 'in_stock',
            image: '',
            barcode: '',
            is_default: idx === 0 ? 1 : 0
        };
    });
    
    renderVariantsTable();
    updateVariantsDataInput();
}

/**
 * Deep comparison of two objects (for variant attributes)
 */
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

/**
 * Generate all combinations of variant options
 */
function generateCombinations(options) {
    if (options.length === 0) return [];
    if (options.length === 1) {
        return options[0].values.map(value => ({
            attributes: {
                [options[0].name]: value
            }
        }));
    }
    
    // For 2 options, generate all combinations
    const combinations = [];
    const option1 = options[0];
    const option2 = options[1];
    
    option1.values.forEach(val1 => {
        option2.values.forEach(val2 => {
            combinations.push({
                attributes: {
                    [option1.name]: val1,
                    [option2.name]: val2
                }
            });
        });
    });
    
    return combinations;
}

/**
 * Render variants table
 */
function renderVariantsTable() {
    const container = document.getElementById('variantsTableContainer');
    const tbody = document.getElementById('variantsTableBody');
    
    if (!container || !tbody) return;
    
    if (generatedVariants.length === 0) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    
    tbody.innerHTML = generatedVariants.map((variant, index) => {
        const variantLabel = Object.entries(variant.attributes)
            .map(([key, val]) => `${key}: ${val}`)
            .join(' / ');
        
        return `
            <tr class="variant-row" data-variant-index="${index}">
                <td class="border border-gray-300 px-3 py-2">
                    <strong>${variantLabel}</strong>
                    <input type="hidden" 
                           class="variant-attributes" 
                           value='${JSON.stringify(variant.attributes)}'>
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="text" 
                           class="admin-form-input variant-sku w-full" 
                           value="${variant.sku || ''}"
                           placeholder="SKU"
                           onchange="updateVariantField(${index}, 'sku', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           step="0.01"
                           class="admin-form-input variant-price w-full" 
                           value="${variant.price || ''}"
                           placeholder="Price"
                           onchange="updateVariantField(${index}, 'price', this.value)">
                </td>
                <td class="border border-gray-300 px-3 py-2">
                    <input type="number" 
                           step="0.01"
                           class="admin-form-input variant-sale-price w-full" 
                           value="${variant.sale_price || ''}"
                           placeholder="Sale Price"
                           onchange="updateVariantField(${index}, 'sale_price', this.value)">
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
    }).join('');
}

/**
 * Upload variant image
 */
function uploadVariantImage(index, files) {
    if (files.length === 0) return;
    const file = files[0];
    
    if (!file.type.startsWith('image/')) {
        alert('Please select only image files.');
        return;
    }
    
    // Show loading state
    const container = document.querySelector(`[data-variant-index="${index}"] .variant-image-preview-container`);
    if (container) {
        container.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-500"></i>';
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
    const uploadUrl = baseUrl + '/admin/api/upload.php';
    
    fetch(uploadUrl, {
        method: 'POST', 
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateVariantField(index, 'image', data.path);
            renderVariantsTable(); // Re-render to show new image
        } else {
            alert(data.message || 'Failed to upload image');
            renderVariantsTable(); // Re-render to restore state
        }
    })
    .catch(error => {
        console.error(error);
        alert('An error occurred while uploading.');
        renderVariantsTable();
    });
}

/**
 * Update variant field
 */
function updateVariantField(index, field, value) {
    if (generatedVariants[index]) {
        generatedVariants[index][field] = value;
        updateVariantsDataInput();
    }
}

/**
 * Update hidden input with variants data
 */
function updateVariantsDataInput() {
    const input = document.getElementById('variantsDataInput');
    if (input) {
        input.value = JSON.stringify({
            options: variantOptions,
            variants: generatedVariants
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateAddButtonVisibility();
});

