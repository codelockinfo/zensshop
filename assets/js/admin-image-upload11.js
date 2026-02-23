/**
 * Admin Image Upload with Drag and Drop & Reordering - Robust Version
 */

function setupImageUploadListeners() {
    try {
        initializeImageUpload();
    } catch (e) {
        console.error("Error initializing image upload:", e);
    }
    
    // Inject Toast CSS if not already there
    if (!document.getElementById('admin-toast-styles')) {
        const style = document.createElement('style');
        style.id = 'admin-toast-styles';
        style.innerHTML = `
            .admin-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                color: white;
                font-weight: 500;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                z-index: 9999;
                transform: translateX(100%);
                opacity: 0;
                transition: all 0.3s ease-in-out;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .admin-toast.show {
                transform: translateX(0);
                opacity: 1;
            }
            .admin-toast.error { background-color: #ef4444; }
            .admin-toast.success { background-color: #10b981; }
            .admin-toast.info { background-color: #3b82f6; }
        `;
        document.head.appendChild(style);
    }

    // Global prevention of default file opening
    if (!window.imageDragHandlersBound) {
        window.addEventListener('dragover', function(e) {
            e.preventDefault();
        }, false);
        window.addEventListener('drop', function(e) {
            e.preventDefault();
        }, false);
        window.imageDragHandlersBound = true;
    }
}

// Initialize immediately
setupImageUploadListeners();

// Toast Notification Utility
function showToast(message, type = 'info', duration = 3000) {
    // Remove existing toasts
    const existing = document.querySelectorAll('.admin-toast');
    existing.forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = `admin-toast ${type}`;
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';

    toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Auto hide
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

var draggedElement = window.draggedElement || null;
window.draggedElement = draggedElement;

function initializeImageUpload() {
    const imagesInput = document.getElementById('imagesInput');
    
    // Initial Sync
    updateImagesInput();
    
    // Helpers to access global next index if needed
    if (typeof window.nextImageIndex === 'undefined') window.nextImageIndex = 0;

    // Rescan the DOM to bind events
    const existingBoxes = document.querySelectorAll('.image-upload-box');
    existingBoxes.forEach(box => {
        if (!box) return;
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
    if (!box || box.dataset.uploadInitialized) return;
    box.dataset.uploadInitialized = 'true';

    try {
        const fileInput = box.querySelector('.image-file-input');
        const placeholder = box.querySelector('.upload-placeholder');
        const preview = box.querySelector('.image-preview');
        const removeBtn = box.querySelector('.remove-image-btn');
        const handle = box.querySelector('.move-handle');

        if (!fileInput || !placeholder || !preview || !removeBtn) {
            console.warn("Missing elements in image box", box);
            return;
        }

        box.setAttribute('draggable', 'true');

        // CLICK: Browse (Only if not clicking handle or delete)
        box.addEventListener('click', function(e) {
            // Don't open file dialog if clicking remove button, move handle, or the file input itself
            if (e.target !== fileInput && e.target !== removeBtn && !e.target.closest('.remove-image-btn') && !e.target.closest('.move-handle')) {
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

            if (dt && dt.files && dt.files.length > 0) {
                console.log('File dropped:', dt.files[0].name);
                handleFiles(dt.files, box, placeholder, preview, removeBtn);
                // reset drag state just in case
                if(draggedElement) draggedElement = null; 
                return; 
            }

            // PRIORITY 2: INTERNAL REORDER
            if (draggedElement) {
                if (draggedElement !== box && draggedElement.parentNode === box.parentNode) {
                    const container = document.getElementById('imageUploadArea');
                    const boxes = Array.from(container.querySelectorAll('.image-upload-box'));
                    const fromIndex = boxes.indexOf(draggedElement);
                    const toIndex = boxes.indexOf(box);

                    // Robust insertBefore with null check
                    if (fromIndex < toIndex) {
                        // Inserting after (nextSibling might be null, which allows append)
                        container.insertBefore(draggedElement, box.nextSibling);
                    } else {
                        // Inserting before (box is valid node)
                        container.insertBefore(draggedElement, box);
                    }
                    updateImagesInput();
                } else {
                    console.warn("Invalid drop target or dragged element lost");
                }
                
                // Cleanup
                draggedElement.classList.remove('opacity-50');
                draggedElement = null;
            }
        });
    } catch (err) {
        console.error("Error binding box events:", err);
    }
}

function handleFiles(files, box, placeholder, preview, removeBtn) {
    if (!files || files.length === 0) return;
    const file = files[0];
    
    // Validate Image/Video Type
    if (!file.type.match('image.*') && !file.type.match('video.*')) {
        showToast('Please select only image or video files.', 'error');
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
            console.log('Upload success:', data.db_path);
            box.setAttribute('data-image-path', data.db_path);
            
            // Preview uses the full URL
            const previewUrl = data.path;
            const isVideo = previewUrl.match(/\.(mp4|webm|ogg|mov)$/i);
            
            if (isVideo) {
                 preview.innerHTML = `
                    <video src="${previewUrl}" class="w-full h-32 object-cover rounded" controls></video>
                    <p class="text-xs text-gray-600 mt-2 truncate">${file.name}</p>
                `;
            } else {
                preview.innerHTML = `
                    <img src="${previewUrl}" alt="Preview" class="w-full h-32 object-cover rounded">
                    <p class="text-xs text-gray-600 mt-2 truncate">${file.name}</p>
                `;
            }
            
            removeBtn.classList.remove('hidden');
            const boxRemoveBtn = box.querySelector('.remove-box-btn');
            if (boxRemoveBtn) boxRemoveBtn.classList.add('hidden');
            updateImagesInput();
        } else {
            console.error('Upload failed:', data.message);
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            const boxRemoveBtn = box.querySelector('.remove-box-btn');
            if (boxRemoveBtn) boxRemoveBtn.classList.remove('hidden');
            showToast(data.message || 'Failed to upload file. Server returned error.', 'error', 5000);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        const boxRemoveBtn = box.querySelector('.remove-box-btn');
        if (boxRemoveBtn) boxRemoveBtn.classList.remove('hidden');
        showToast('An error occurred while uploading. Please check your network or server logs.', 'error', 5000);
    });
}

function removeImage(box, placeholder, preview, removeBtn) {
    box.removeAttribute('data-image-path');
    preview.classList.add('hidden');
    preview.innerHTML = '';
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
    const boxRemoveBtn = box.querySelector('.remove-box-btn');
    if (boxRemoveBtn) boxRemoveBtn.classList.remove('hidden');
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
                // Check for existing image OR video (PHP rendered)
                const img = box.querySelector('.image-preview img');
                const video = box.querySelector('.image-preview video');
                
                if (box.querySelector('.image-preview') && !box.querySelector('.image-preview').classList.contains('hidden')) {
                    if (img) {
                        path = img.getAttribute('src');
                    } else if (video) {
                        path = video.getAttribute('src');
                    }
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

function removeImageBox(btn) {
    const box = btn.closest('.image-upload-box');
    if (box) {
        box.remove();
        updateImagesInput();
    }
}

function addMoreImage() {
    const container = document.getElementById('imageUploadArea');
    if (!container) return;
    
    // Ensure index is unique
    if (typeof window.nextImageIndex === 'undefined') window.nextImageIndex = 0;
    const index = window.nextImageIndex++; 
    
    const boxHtml = `
        <div class="image-upload-box border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors relative group" data-index="${index}" draggable="true">
            <!-- Drag Handle -->
            <div class="absolute top-2 left-2 cursor-grab move-handle text-grey-800 w-8 h-8 flex items-center justify-center rounded shadow-md z-20 transition-colors" title="Drag to reorder">
                <i class="fas fa-grip-vertical"></i>
            </div>
            
            <input type="file" accept="image/*,video/*" class="hidden image-file-input">
            <div class="upload-placeholder">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600">Drop media (Images/Videos)<br><span class="text-xs text-gray-400">(Recommended 1:1)</span></p>
                <span class="text-blue-500 text-sm">click to browse</span>
            </div>
            <div class="image-preview hidden"></div>
            <button type="button" class="remove-image-btn absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 hidden">
                <i class="fas fa-times text-xs"></i>
            </button>
            <button type="button" onclick="event.stopPropagation(); removeImageBox(this);" class="remove-box-btn absolute top-2 right-2 bg-gray-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-gray-700 z-30" title="Remove this box">
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
