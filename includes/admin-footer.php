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
    
    <script src="/oecom/assets/js/admin.js"></script>
    <script src="/oecom/assets/js/admin-confirm.js"></script>
    <script src="/oecom/assets/js/admin-search.js"></script>
    <?php endif; ?>
</body>
</html>

