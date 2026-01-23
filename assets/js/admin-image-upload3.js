/**
 * Admin Image Upload with Drag and Drop & Reordering - Robust Version
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeImageUpload();
});

let draggedElement = null;

function initializeImageUpload() {
    const imagesInput = document.getElementById('imagesInput');
    
    // Initial Sync
    updateImagesInput();
    
    // Helpers to access global next index if needed, though we rely on DOM count mostly now
    if (typeof window.nextImageIndex === 'undefined') window.nextImageIndex = 0;

    // Rescan the DOM to bind events
    const existingBoxes = document.querySelectorAll('.image-upload-box');
    existingBoxes.forEach(box => {
        const index = parseInt(box.getAttribute('data-index'));
        if (!isNaN(index)) {
             bindBoxEvents(box);
             if (index >= window.nextImageIndex) {
                 window.nextImageIndex = index + 1;
             }
        }
    });
}

function bindBoxEvents(box) {
    const fileInput = box.querySelector('.image-file-input');
    const placeholder = box.querySelector('.upload-placeholder');
    const preview = box.querySelector('.image-preview');
    const removeBtn = box.querySelector('.remove-image-btn');
    
    // Enable Draggable
    box.setAttribute('draggable', 'true');

    // CLICK: Browse
    box.addEventListener('click', function(e) {
        if (e.target !== removeBtn && !e.target.closest('.remove-image-btn')) {
            fileInput.click();
        }
    });
    
    // CHANGE: File Selected
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files, box, placeholder, preview, removeBtn);
    });
    
    // REMOVE: Click X
    removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        removeImage(box, placeholder, preview, removeBtn);
    });

    // --- DRAG & DROP EVENTS ---

    // 1. Drag Start
    box.addEventListener('dragstart', function(e) {
        if (e.target.closest('.remove-image-btn')) {
            e.preventDefault();
            return;
        }
        draggedElement = box;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', box.innerHTML); // fallback
        box.classList.add('opacity-50');
    });

    // 2. Drag End
    box.addEventListener('dragend', function(e) {
        box.classList.remove('opacity-50');
        draggedElement = null;
        document.querySelectorAll('.image-upload-box').forEach(b => b.classList.remove('border-blue-500', 'border-2'));
        updateImagesInput();
    });

    // 3. Drag Over (Allow Drop)
    box.addEventListener('dragover', function(e) {
        e.preventDefault(); // Necessary to allow dropping
        e.dataTransfer.dropEffect = 'move';
        
        // Highlight target if it's not the one we are dragging
        if (draggedElement && draggedElement !== box) {
            box.classList.add('border-blue-500', 'border-2');
        }
    });

    // 4. Drag Leave
    box.addEventListener('dragleave', function(e) {
        box.classList.remove('border-blue-500', 'border-2');
    });

    // 5. DROP (The Core Logic)
    box.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        box.classList.remove('border-blue-500', 'border-2');

        // CASE 1: Reorder (Internal Drag) - HIGHER PRIORITY
        // If we are dragging an element from within our system, we prioritize moving it
        // over checking for files (because browsers sometimes duplicate dragging images as 'files')
        if (draggedElement) {
            if (draggedElement !== box) {
                const container = document.getElementById('imageUploadArea');
                // Get all boxes as array
                const boxes = Array.from(container.querySelectorAll('.image-upload-box'));
                const fromIndex = boxes.indexOf(draggedElement);
                const toIndex = boxes.indexOf(box);

                if (fromIndex < toIndex) {
                    // Dragging from left to right (or top to bottom)
                    // Insert AFTER the target
                    container.insertBefore(draggedElement, box.nextSibling);
                } else {
                    // Dragging from right to left
                    // Insert BEFORE the target
                    container.insertBefore(draggedElement, box);
                }
                
                updateImagesInput();
            }
            return; // Stop here! Do not fall through to file check.
        }

        // CASE 2: File Upload (from Desktop or External)
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files, box, placeholder, preview, removeBtn);
            return;
        }
    });
}

function handleFiles(files, box, placeholder, preview, removeBtn) {
    if (files.length === 0) return;
    const file = files[0];
    
    if (!file.type.startsWith('image/')) {
        console.log('Please select only image files.');
        return;
    }
    
    preview.innerHTML = '<div class="flex items-center justify-center h-32"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></div>';
    preview.classList.remove('hidden');
    placeholder.classList.add('hidden');
    
    const formData = new FormData();
    formData.append('image', file);
    
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
    const uploadUrl = baseUrl + '/admin/api/upload.php';
    
    fetch(uploadUrl, {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            box.setAttribute('data-image-path', data.path);
            preview.innerHTML = `
                <img src="${data.path}" alt="Preview" class="w-full h-32 object-cover rounded">
                <p class="text-xs text-gray-600 mt-2 truncate">${file.name}</p>
            `;
            removeBtn.classList.remove('hidden');
            updateImagesInput();
        } else {
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            console.log(data.message || 'Failed to upload image');
        }
    })
    .catch(error => {
        console.error(error);
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        console.log('An error occurred while uploading.');
    });
}

function removeImage(box, placeholder, preview, removeBtn) {
    box.removeAttribute('data-image-path');
    preview.classList.add('hidden');
    preview.innerHTML = '';
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
    updateImagesInput();
}

function updateImagesInput() {
    const imagesInput = document.getElementById('imagesInput');
    const container = document.getElementById('imageUploadArea');
    if (imagesInput && container) {
        const boxes = container.querySelectorAll('.image-upload-box');
        const paths = [];
        
        boxes.forEach(box => {
            let path = box.getAttribute('data-image-path');
            if (!path) {
                // Check for existing image (PHP rendered)
                const img = box.querySelector('.image-preview img');
                if (img && !box.querySelector('.image-preview').classList.contains('hidden')) {
                     path = img.getAttribute('src');
                }
            }
            if (path) {
                paths.push(path);
            }
        });
        
        imagesInput.value = JSON.stringify(paths);
        console.log('Updated Images Order:', paths);
    }
}

function addMoreImage() {
    const container = document.getElementById('imageUploadArea');
    // Ensure index is unique
    const index = window.nextImageIndex++; 
    
    const boxHtml = `
        <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative" data-index="${index}" draggable="true">
            <input type="file" accept="image/*" class="hidden image-file-input">
            <div class="upload-placeholder">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600">Drop your images here or <span class="text-blue-500">click to browse</span>.</p>
            </div>
            <div class="image-preview hidden"></div>
            <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', boxHtml);
    const box = container.lastElementChild;
    if (box) {
        bindBoxEvents(box);
    }
}
