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
            // Determine Store ID (Omni-store logic)
            if (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }

            if (!$storeId && isset($_SESSION['user_email'])) {
                $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                $storeId = $storeUser['store_id'] ?? null;
            }

            $this->db->insert(
                "INSERT INTO admin_notifications (type, title, message, link, store_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())",
                [$type, $title, $message, $link, $storeId]
            );

            // Send Slack Notification
            if (defined('SLACK_API_KEY') && defined('CHANNEL_ID')) {
                $slackUrl = 'https://slack.com/api/chat.postMessage';
                
                // Format the Slack message to include the link if it exists
                $slackMessage = "*$title*\n$message";
                if ($link) {
                    $fullLink = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' . ltrim($link, '/') : $link;
                    $slackMessage .= "\n<$fullLink|View Details>";
                }
                
                $slackData = [
                    'channel' => CHANNEL_ID,
                    'text' => $slackMessage
                ];
                
                $ch = curl_init($slackUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . SLACK_API_KEY,
                    'Content-Type: application/json; charset=utf-8'
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackData));
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout so it doesn't block
                
                // Fix for local WAMP SSL Certificate issues
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    error_log("Slack Curl Error: " . curl_error($ch));
                } else {
                    error_log("Slack Response: " . $result);
                }
                
                curl_close($ch);
            } else {
                error_log("Slack API Key or Channel ID is NOT defined when trying to send notification.");
            }

            return true;
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0 AND store_id = ?", [$storeId]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Get recent notifications
     */
    public function getRecent($limit = 10, $unreadOnly = false, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $where = $unreadOnly ? "WHERE is_read = 0 AND store_id = ?" : "WHERE store_id = ?";
        return $this->db->fetchAll(
            "SELECT * FROM admin_notifications $where ORDER BY created_at DESC LIMIT ?",
            [$storeId, $limit]
        );
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($id, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $this->db->execute("UPDATE admin_notifications SET is_read = 1 WHERE id = ? AND store_id = ?", [$id, $storeId]);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $this->db->execute("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0 AND store_id = ?", [$storeId]);
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

    public function notifyCancellationRequest($orderNumber, $customerName) {
        return $this->create(
            'order',
            'Cancellation Request',
            "Cancellation request for order #$orderNumber from $customerName",
            "/admin/orders/detail.php?order_number=" . urlencode($orderNumber)
        );
    }

    public function notifyRefundRequest($orderNumber, $customerName) {
        return $this->create(
            'order',
            'Refund Request',
            "Refund request for order #$orderNumber from $customerName",
            "/admin/orders/detail.php?order_number=" . urlencode($orderNumber)
        );
    }
}
