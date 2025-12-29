/**
 * Admin Confirmation Modal
 * Styled confirmation popup matching Remos theme
 */

let confirmCallback = null;

// Show confirmation modal
function showConfirmModal(message, onConfirm, options = {}) {
    const modal = document.getElementById('confirmModal');
    const messageEl = document.getElementById('confirmModalMessage');
    const confirmBtn = document.getElementById('confirmModalConfirm');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const titleEl = document.querySelector('.confirm-modal-title');
    const iconEl = document.querySelector('.confirm-modal-icon');
    
    if (!modal || !messageEl) return;
    
    // Set message
    messageEl.textContent = message || 'Are you sure you want to perform this action?';
    
    // Set title if provided
    if (options.title) {
        titleEl.textContent = options.title;
    } else {
        titleEl.textContent = options.isError ? 'Error' : 'Confirm Action';
    }
    
    // Update icon for error messages
    if (options.isError) {
        iconEl.innerHTML = '<i class="fas fa-times-circle"></i>';
        iconEl.style.background = 'linear-gradient(135deg, #f5365c 0%, #f56036 100%)';
        confirmBtn.textContent = 'OK';
        cancelBtn.style.display = 'none';
    } else {
        iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        iconEl.style.background = 'linear-gradient(135deg, #f5365c 0%, #f56036 100%)';
        confirmBtn.textContent = 'Confirm';
        cancelBtn.style.display = 'flex';
    }
    
    // Store callback
    confirmCallback = onConfirm;
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Handle confirm
    confirmBtn.onclick = function() {
        if (confirmCallback) {
            confirmCallback();
        }
        closeConfirmModal();
    };
    
    // Handle cancel
    cancelBtn.onclick = function() {
        closeConfirmModal();
    };
    
    // Close on overlay click (only if not error)
    if (!options.isError) {
        modal.onclick = function(e) {
            if (e.target === modal) {
                closeConfirmModal();
            }
        };
    } else {
        modal.onclick = null;
    }
    
    // Close on Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            closeConfirmModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

// Close confirmation modal
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const confirmBtn = document.getElementById('confirmModalConfirm');
    const titleEl = document.querySelector('.confirm-modal-title');
    const iconEl = document.querySelector('.confirm-modal-icon');
    
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        confirmCallback = null;
        
        // Reset modal state
        if (cancelBtn) {
            cancelBtn.style.display = 'flex';
        }
        if (confirmBtn) {
            confirmBtn.textContent = 'Confirm';
        }
        if (titleEl) {
            titleEl.textContent = 'Confirm Action';
        }
        if (iconEl) {
            iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            iconEl.style.background = 'linear-gradient(135deg, #f5365c 0%, #f56036 100%)';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Modal is ready
});

