/**
 * Admin Image Upload with Drag and Drop & Reordering - Robust Version
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeImageUpload();
    
    // Global prevention of default file opening
    window.addEventListener('dragover', function(e) {
        e.preventDefault();
    }, false);
    window.addEventListener('drop', function(e) {
        e.preventDefault();
    }, false);
});

let draggedElement = null;

function initializeImageUpload() {
    const imagesInput = document.getElementById('imagesInput');
    
    // Initial Sync
    updateImagesInput();
    
    // Helpers to access global next index if needed
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
    const handle = box.querySelector('.move-handle');

    box.setAttribute('draggable', 'true');

    // CLICK: Browse (Only if not clicking handle or delete)
    box.addEventListener('click', function(e) {
        // Don't open file dialog if clicking remove button or the move handle
        if (e.target !== removeBtn && !e.target.closest('.remove-image-btn') && !e.target.closest('.move-handle')) {
            fileInput.click();
        }
    });
    
    // CHANGE: File Selected via Dialog
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files, box, placeholder, preview, removeBtn);
    });
    
    // REMOVE: Click X
    removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        removeImage(box, placeholder, preview, removeBtn);
    });

    // --- DRAG & DROP EVENTS ---

    // 1. Drag Start (Only for internal reordering)
    box.addEventListener('dragstart', function(e) {
        // Prevent drag if clicking remove button
        if (e.target.closest('.remove-image-btn')) {
            e.preventDefault();
            return;
        }

        // If user wants ONLY handle to initiate drag, uncomment this:
        // if (!e.target.closest('.move-handle')) { 
        //    if(!e.dataTransfer.files.length) { // Allow file drag
        //       // prevent default internal drag if not handle? 
        //       // Actually better to allow whole box drag but handle is visual cue.
        //    }
        // }
        
        // Mark as internal drag
        draggedElement = box;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', box.getAttribute('data-index')); // Payload
        box.classList.add('opacity-50');
    });

    // 2. Drag End
    box.addEventListener('dragend', function(e) {
        box.classList.remove('opacity-50');
        draggedElement = null;
        document.querySelectorAll('.image-upload-box').forEach(b => b.classList.remove('border-blue-500', 'border-2', 'bg-blue-50'));
        updateImagesInput();
    });

    // 3. Drag Over (Allow Drop)
    box.addEventListener('dragover', function(e) {
        e.preventDefault(); // allow drop
        e.stopPropagation();
        
        // Visual Feedback
        box.classList.add('border-blue-500', 'border-2', 'bg-blue-50');
        
        if (draggedElement) {
            e.dataTransfer.dropEffect = 'move';
        } else {
            // Assume file
            e.dataTransfer.dropEffect = 'copy';
        }
    });

    // 4. Drag Enter
    box.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        box.classList.add('border-blue-500', 'border-2', 'bg-blue-50');
    });

    // 5. Drag Leave
    box.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Detect if leaving the box entirely (not just into a child)
        if (e.relatedTarget && !box.contains(e.relatedTarget) && e.relatedTarget !== box) {
            box.classList.remove('border-blue-500', 'border-2', 'bg-blue-50');
        }
    });

    // 6. DROP (The Core Logic)
    box.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        box.classList.remove('border-blue-500', 'border-2', 'bg-blue-50');

        const dt = e.dataTransfer;
        
        console.log('Drop detected.');

        // PRIORITY 1: EXTERNAL FILES (Desktop Drag)
        // Check files FIRST. If files exist, it's an upload.
        // NOTE: dt.files might be empty during 'dragover' but populated on 'drop'.
        if (dt && dt.files && dt.files.length > 0) {
            console.log('File dropped:', dt.files[0].name);
            handleFiles(dt.files, box, placeholder, preview, removeBtn);
            // reset drag state just in case
            if(draggedElement) draggedElement = null; 
            return; 
        }

        // PRIORITY 2: INTERNAL REORDER
        if (draggedElement) {
            if (draggedElement !== box) {
                const container = document.getElementById('imageUploadArea');
                const boxes = Array.from(container.querySelectorAll('.image-upload-box'));
                const fromIndex = boxes.indexOf(draggedElement);
                const toIndex = boxes.indexOf(box);

                if (fromIndex < toIndex) {
                    container.insertBefore(draggedElement, box.nextSibling);
                } else {
                    container.insertBefore(draggedElement, box);
                }
                updateImagesInput();
            }
            // Cleanup
            draggedElement.classList.remove('opacity-50');
            draggedElement = null;
        }
    });
}

function handleFiles(files, box, placeholder, preview, removeBtn) {
    if (files.length === 0) return;
    const file = files[0];
    
    // Validate Image Type
    if (!file.type.match('image.*')) {
        alert('Please select only image files (JPG, PNG, GIF, etc).');
        return;
    }
    
    // Show Loading Spinner
    preview.innerHTML = '<div class="flex items-center justify-center h-32"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i><span class="ml-2 text-gray-500">Uploading...</span></div>';
    preview.classList.remove('hidden');
    placeholder.classList.add('hidden');
    
    // Prepare Upload
    const formData = new FormData();
    formData.append('image', file);
    
    // Determine BASE_URL robustly
    let baseUrl = '';
    if (typeof BASE_URL !== 'undefined') {
        baseUrl = BASE_URL;
    } else {
        // Fallback: try to guess from current path
        // url: /zensshop/admin/products/add.php -> base: /zensshop
        const parts = window.location.pathname.split('/admin/');
        if (parts.length > 1) {
            baseUrl = parts[0];
        } else {
            baseUrl = ''; // possibly root
        }
    }
    
    // Trim trailing slash
    baseUrl = baseUrl.replace(/\/$/, '');
    
    const uploadUrl = baseUrl + '/admin/api/upload.php';
    
    console.log('Uploading to:', uploadUrl);

    fetch(uploadUrl, {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Upload success:', data.path);
            box.setAttribute('data-image-path', data.path);
            preview.innerHTML = `
                <img src="${data.path}" alt="Preview" class="w-full h-32 object-cover rounded">
                <p class="text-xs text-gray-600 mt-2 truncate">${file.name}</p>
            `;
            removeBtn.classList.remove('hidden');
            updateImagesInput();
        } else {
            console.error('Upload failed:', data.message);
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            alert(data.message || 'Failed to upload image. Server returned error.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        alert('An error occurred while uploading. Please check your network or server logs.');
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
        <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative group" data-index="${index}" draggable="true">
            <!-- Drag Handle -->
            <div class="absolute top-2 left-2 cursor-grab move-handle text-grey-800 w-8 h-8 flex items-center justify-center rounded shadow-md z-20 transition-colors" title="Drag to reorder">
                <i class="fas fa-grip-vertical"></i>
            </div>
            
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
