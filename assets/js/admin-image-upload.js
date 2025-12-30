/**
 * Admin Image Upload with Drag and Drop
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeImageUpload();
    
    // Initialize existing images if present
    const imagesInput = document.getElementById('imagesInput');
    if (imagesInput && imagesInput.value) {
        try {
            const existingImages = JSON.parse(imagesInput.value);
            if (Array.isArray(existingImages)) {
                existingImages.forEach((imagePath, index) => {
                    if (imagePath) {
                        const box = document.querySelector(`.image-upload-box[data-index="${index}"]`);
                        if (box) {
                            const preview = box.querySelector('.image-preview');
                            const placeholder = box.querySelector('.upload-placeholder');
                            const removeBtn = box.querySelector('.remove-image-btn');
                            
                            if (preview && placeholder && removeBtn) {
                                uploadedImages[index] = { path: imagePath };
                                preview.innerHTML = `
                                    <img src="${imagePath}" alt="Preview" class="w-full h-32 object-cover rounded">
                                    <p class="text-xs text-gray-600 mt-2 truncate">Existing Image</p>
                                `;
                                preview.classList.remove('hidden');
                                placeholder.classList.add('hidden');
                                removeBtn.classList.remove('hidden');
                            }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error parsing existing images:', e);
        }
    }
});

function initializeImageUpload() {
    const uploadBoxes = document.querySelectorAll('.image-upload-box');
    const imagesInput = document.getElementById('imagesInput');
    let uploadedImages = [];
    
    uploadBoxes.forEach(box => {
        const fileInput = box.querySelector('.image-file-input');
        const placeholder = box.querySelector('.upload-placeholder');
        const preview = box.querySelector('.image-preview');
        const removeBtn = box.querySelector('.remove-image-btn');
        const index = parseInt(box.getAttribute('data-index'));
        
        // Click to browse
        box.addEventListener('click', function(e) {
            if (e.target !== removeBtn && !e.target.closest('.remove-image-btn')) {
                fileInput.click();
            }
        });
        
        // File input change
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files, index, box, placeholder, preview, removeBtn);
        });
        
        // Drag and drop
        box.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            box.classList.add('border-blue-500', 'bg-blue-50');
        });
        
        box.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            box.classList.remove('border-blue-500', 'bg-blue-50');
        });
        
        box.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            box.classList.remove('border-blue-500', 'bg-blue-50');
            
            const files = e.dataTransfer.files;
            handleFiles(files, index, box, placeholder, preview, removeBtn);
        });
        
        // Remove image
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            removeImage(index, box, placeholder, preview, removeBtn);
        });
    });
    
    function handleFiles(files, index, box, placeholder, preview, removeBtn) {
        if (files.length === 0) return;
        
        const file = files[0]; // Take first file for each box
        
        if (!file.type.startsWith('image/')) {
            if (typeof showConfirmModal !== 'undefined') {
                showConfirmModal('Please select only image files.', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Invalid File Type' });
            } else {
                alert('Please select only image files.');
            }
            return;
        }
        
        // Show loading state
        preview.innerHTML = '<div class="flex items-center justify-center h-32"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></div>';
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        
        // Upload file to server
        const formData = new FormData();
        formData.append('image', file);
        
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : window.location.pathname.split('/').slice(0, -1).join('/') || '';
        const uploadUrl = baseUrl + '/admin/api/upload';
        fetch(uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store image path
                uploadedImages[index] = {
                    path: data.path,
                    filename: data.filename
                };
                
                // Show preview
                preview.innerHTML = `
                    <img src="${data.path}" alt="Preview" class="w-full h-32 object-cover rounded">
                    <p class="text-xs text-gray-600 mt-2 truncate">${file.name}</p>
                `;
                removeBtn.classList.remove('hidden');
                
                // Update hidden input
                updateImagesInput();
            } else {
                // Show error
                preview.classList.add('hidden');
                placeholder.classList.remove('hidden');
                if (typeof showConfirmModal !== 'undefined') {
                    showConfirmModal(data.message || 'Failed to upload image', function() {
                        closeConfirmModal();
                    }, { isError: true, title: 'Upload Error' });
                } else {
                    alert(data.message || 'Failed to upload image');
                }
            }
        })
        .catch(error => {
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            if (typeof showConfirmModal !== 'undefined') {
                showConfirmModal('An error occurred while uploading the image.', function() {
                    closeConfirmModal();
                }, { isError: true, title: 'Upload Error' });
            } else {
                alert('An error occurred while uploading the image.');
            }
        });
    }
    
    function removeImage(index, box, placeholder, preview, removeBtn) {
        delete uploadedImages[index];
        preview.classList.add('hidden');
        preview.innerHTML = '';
        placeholder.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        updateImagesInput();
    }
    
    function updateImagesInput() {
        // Get all uploaded image paths
        const imagePaths = Object.values(uploadedImages)
            .filter(img => img && img.path)
            .map(img => img.path);
        
        if (imagesInput) {
            imagesInput.value = JSON.stringify(imagePaths);
        }
    }
}

