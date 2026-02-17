<?php
require_once __DIR__ . '/../config/database.php';

class NotificationService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new notification
     * 
     * @param int|null $userId Target user ID (null for system-wide/broadcast if needed later)
     * @param string $title Notification title
     * @param string $message Notification message body
     * @param string $type Notification type (info, success, warning, error)
     * @param string|null $link Optional link to redirect on click
     * @return int|false Last insert ID or false on failure
     */
    public function create($userId, $title, $message, $type = 'info', $link = null) {
        $data = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link
        ];
        return $this->db->insert('notifications', $data);
    }

    /**
     * Get unread notification count for a user
     * 
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $this->db->query($sql, [$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get recent notifications for a user
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecent($userId, $limit = 5) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        // PDO limit parameter needs to be integer, but sometimes bound as string. 
        // Safer to interpolate or bind explicitly if driver supports. 
        // Our DB wrapper might just pass it. Let's try standard binding.
        // Actually, limit in MySQL via PDO often requires bindValue with INT type.
        // For simplicity with our wrapper:
        $sql = "SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql, ['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Mark a notification as read
     * 
     * @param int $notificationId
     * @param int $userId Security check to ensure user owns notification
     * @return bool
     */
    public function markAsRead($notificationId, $userId) {
        return $this->db->update('notifications', ['is_read' => 1], "id = :id AND user_id = :uid", ['id' => $notificationId, 'uid' => $userId]);
    }

    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead($userId) {
        return $this->db->update('notifications', ['is_read' => 1], "user_id = :uid AND is_read = 0", ['uid' => $userId]);
    }

    /**
     * Get all notifications for a user with pagination
     * 
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $this->db->query($sql, ['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Delete all notifications for a user
     * 
     * @param int $userId
     * @return bool
     */
    public function clearAll($userId) {
        return $this->db->delete('notifications', "user_id = :uid", ['uid' => $userId]);
    }
}
