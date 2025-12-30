/**
 * Notification Modal
 * Custom notification popup for frontend
 */

function showNotificationModal(message, type = 'success', title = null) {
    const modal = document.getElementById('notificationModal');
    const icon = document.getElementById('notificationIcon');
    const titleEl = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');
    
    if (!modal || !icon || !messageEl) return;
    
    // Set message
    messageEl.textContent = message;
    
    // Set title
    if (title) {
        titleEl.textContent = title;
    } else {
        switch (type) {
            case 'success':
                titleEl.textContent = 'Success';
                break;
            case 'error':
                titleEl.textContent = 'Error';
                break;
            case 'info':
                titleEl.textContent = 'Information';
                break;
            default:
                titleEl.textContent = 'Notification';
        }
    }
    
    // Update icon and styling based on type
    icon.className = 'notification-modal-icon';
    const iconElement = icon.querySelector('i');
    
    switch (type) {
        case 'success':
            icon.classList.add('success');
            iconElement.className = 'fas fa-check-circle';
            break;
        case 'error':
            icon.classList.add('error');
            iconElement.className = 'fas fa-times-circle';
            break;
        case 'info':
            icon.classList.add('info');
            iconElement.className = 'fas fa-info-circle';
            break;
        default:
            icon.classList.add('success');
            iconElement.className = 'fas fa-check-circle';
    }
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Auto close after 3 seconds for success messages
    if (type === 'success') {
        setTimeout(() => {
            closeNotificationModal();
        }, 3000);
    }
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Close on overlay click
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('notificationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeNotificationModal();
        }
    });
});

// Make function globally available
window.showNotificationModal = showNotificationModal;
window.closeNotificationModal = closeNotificationModal;


