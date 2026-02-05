    <?php if ($currentUser): ?>
        </main>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="confirm-modal-overlay hidden">
        <div class="confirm-modal">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="confirm-modal-title">Confirm Action</h3>
            </div>
            <div class="confirm-modal-body">
                <p class="confirm-modal-message" id="confirmModalMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="confirm-modal-footer">
                <button type="button" class="confirm-modal-btn confirm-modal-btn-cancel" id="confirmModalCancel">
                    Cancel
                </button>
                <button type="button" class="confirm-modal-btn confirm-modal-btn-confirm" id="confirmModalConfirm">
                    Confirm
                </button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin4.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-confirm.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-search1.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-notifications4.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-support3.js"></script>
    <?php endif; ?>
    <script>
    // Global function to show loading state on buttons
    function setBtnLoading(btn, isLoading) {
        if (!btn) return;
        
        if (isLoading) {
            // Lock the button dimensions to prevent layout shift
            const rect = btn.getBoundingClientRect();
            btn.style.width = rect.width + 'px';
            btn.style.height = rect.height + 'px';
            btn.style.minWidth = rect.width + 'px';
            btn.style.minHeight = rect.height + 'px';
            
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.style.display = 'inline-flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
            btn.style.opacity = '1';
            
            // Restore original dimensions and display
            btn.style.width = '';
            btn.style.height = '';
            btn.style.minWidth = '';
            btn.style.minHeight = '';
            btn.style.display = '';
            btn.style.alignItems = '';
            btn.style.justifyContent = '';
            
            delete btn.dataset.originalHtml;
        }
    }

    // Global listener for buttons with 'btn-loading' class
document.addEventListener('submit', function(e) {
    const form = e.target;
    // 1. Check for buttons inside the form
    let submitBtn = form.querySelector('.btn-loading[type="submit"]');
    
    // 2. If not found inside, check for buttons outside using the 'form' attribute
    if (!submitBtn && form.id) {
        submitBtn = document.querySelector(`.btn-loading[type="submit"][form="${form.id}"]`);
    }
    
    if (submitBtn) {
        setBtnLoading(submitBtn, true);
    }
});
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-loading');
        // Only trigger on click if it's NOT a submit button (to avoid double trigger with submit event)
        if (btn && btn.type !== 'submit') {
            setBtnLoading(btn, true);
        }
    });

    // Auto-dismiss alerts
    document.addEventListener('DOMContentLoaded', function() {
        // Target .admin-alert class OR any div with role="alert" OR common alert patterns
        const alertSelector = '.admin-alert, [role="alert"], .bg-red-100, .bg-green-100, .bg-blue-100';
        const alerts = document.querySelectorAll(alertSelector);
        
        alerts.forEach(function(alert) {
            // Only process if it looks like a message box (has text and padding)
            if (alert.innerText.trim().length > 0) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000); // 5 seconds
            }
        });
    });
    </script>
</body>
</html>

