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
    
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin2.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-confirm.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-search1.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-notifications3.js"></script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : getBaseUrl(); ?>/assets/js/admin-support2.js"></script>
    <?php endif; ?>
    <script>
    // Auto-dismiss alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.admin-alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000); // 5 seconds
        });
    });
    </script>
</body>
</html>

