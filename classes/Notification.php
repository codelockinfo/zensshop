<?php
require_once __DIR__ . '/Database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new notification
     */
    public function create($type, $title, $message, $link = null) {
        try {
            $this->db->insert(
                "INSERT INTO admin_notifications (type, title, message, link) VALUES (?, ?, ?, ?)",
                [$type, $title, $message, $link]
            );
            return true;
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount() {
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0");
        return $result['count'] ?? 0;
    }
    
    /**
     * Get recent notifications
     */
    public function getRecent($limit = 10, $unreadOnly = false) {
        $where = $unreadOnly ? "WHERE is_read = 0" : "";
        return $this->db->fetchAll(
            "SELECT * FROM admin_notifications $where ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($id) {
        $this->db->execute("UPDATE admin_notifications SET is_read = 1 WHERE id = ?", [$id]);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead() {
        $this->db->execute("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
    }
    
    /**
     * Delete old notifications (older than 30 days)
     */
    public function deleteOld() {
        $this->db->execute("DELETE FROM admin_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    
    /**
     * Helper methods for specific notification types
     */
    public function notifyNewOrder($orderNumber, $customerName, $amount) {
        return $this->create(
            'order',
            'New Order Received',
            "Order #$orderNumber from $customerName for $amount",
            "/admin/orders/detail.php?order_number=" . urlencode($orderNumber)
        );
    }
    
    public function notifyNewCustomer($customerName, $customerId) {
        return $this->create(
            'customer',
            'New Customer Registered',
            "$customerName has created an account",
            "/admin/customers/view.php?id=$customerId"
        );
    }
    
    public function notifyNewSubscriber($email) {
        return $this->create(
            'subscriber',
            'New Newsletter Subscriber',
            "$email subscribed to the newsletter",
            "/admin/customers/subscribers.php"
        );
    }

    public function notifyPasswordChange($customerName, $customerId, $email) {
        return $this->create(
            'security',
            'Password Changed',
            "Password reset for customer $customerName ($email)",
            "/admin/customers/view.php?id=$customerId"
        );
    }
}
