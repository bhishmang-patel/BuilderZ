<?php
require_once __DIR__ . '/../config/database.php';

class NotificationService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch pending CRM follow-ups dynamically as notifications
     */
    private function getDynamicCrmNotifications($userId) {
        // Ensure the dismissed table exists
        $this->db->query("CREATE TABLE IF NOT EXISTS user_dismissed_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_id VARCHAR(50) NOT NULL,
            dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_notif (user_id, notification_id)
        )");

        $sql = "SELECT f.*, l.full_name 
                FROM lead_followups f 
                JOIN leads l ON f.lead_id = l.id 
                LEFT JOIN user_dismissed_notifications udn 
                    ON udn.user_id = :uid AND udn.notification_id = CONCAT('crm_', f.id)
                WHERE f.is_completed = 0 
                AND DATE(f.followup_date) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                AND udn.id IS NULL
                ORDER BY f.followup_date DESC";
        $followups = $this->db->query($sql, ['uid' => $userId])->fetchAll();
        
        $dynamic = [];
        $currentTime = time();
        foreach ($followups as $pf) {
            $fDate = substr($pf['followup_date'], 0, 10);
            $timeStr = date('h:i A', strtotime($pf['followup_date']));
            
            // Check if the current timestamp has passed the scheduled exact timestamp
            $isOverdue = strtotime($pf['followup_date']) < $currentTime;
            
            $title = $isOverdue ? "Overdue: {$pf['full_name']}" : "Follow-up: {$pf['full_name']}";
            $type  = $isOverdue ? "error" : "warning";
            $msg   = $isOverdue ? "You missed a scheduled {$pf['interaction_type']} with {$pf['full_name']}."
                                : "You have a scheduled {$pf['interaction_type']} with {$pf['full_name']} at {$timeStr}.";
                                
            $dynamic[] = [
                'id' => 'crm_' . $pf['id'], // String ID so it cannot be dismissed via standard markAsRead
                'user_id' => $userId,
                'title' => $title,
                'message' => $msg,
                'type' => $type,
                'link' => BASE_URL . "modules/crm/view.php?id=" . $pf['lead_id'],
                'is_read' => 0, 
                'created_at' => $pf['followup_date'],
                'is_dynamic' => true,
                'due_date' => $pf['followup_date']
            ];
        }
        return $dynamic;
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
        $count = (int)$stmt->fetchColumn();
        
        $dynamic = $this->getDynamicCrmNotifications($userId);
        return $count + count($dynamic);
    }

    /**
     * Get recent notifications for a user
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecent($userId, $limit = 5) {
        $sql = "SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql, ['uid' => $userId]);
        $standard = $stmt->fetchAll();
        
        // Inject dynamic CRM notifications and sort
        $dynamic = $this->getDynamicCrmNotifications($userId);
        $merged = array_merge($dynamic, $standard);
        
        usort($merged, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($merged, 0, $limit);
    }

    /**
     * Mark a notification as read
     * 
     * @param int|string $notificationId
     * @param int $userId Security check to ensure user owns notification
     * @return bool
     */
    public function markAsRead($notificationId, $userId) {
        if (is_numeric($notificationId)) {
            return $this->db->update('notifications', ['is_read' => 1], "id = :id AND user_id = :uid", ['id' => $notificationId, 'uid' => $userId]);
        }
        
        // Handle dynamic CRM notification dismissal
        if (strpos($notificationId, 'crm_') === 0) {
            $followupId = str_replace('crm_', '', $notificationId);
            // Only allow dismissal if it's currently due (NOW() >= followup_date)
            $sql = "SELECT followup_date FROM lead_followups WHERE id = :id";
            $stmt = $this->db->query($sql, ['id' => $followupId]);
            $followup = $stmt->fetch();
            
            if ($followup && strtotime($followup['followup_date']) <= time()) {
                try {
                    $this->db->query("INSERT IGNORE INTO user_dismissed_notifications (user_id, notification_id) VALUES (:uid, :nid)", 
                        ['uid' => $userId, 'nid' => $notificationId]);
                    return true;
                } catch (Exception $e) {
                    // Table might not exist yet if getDynamic was never called, though unlikely
                }
            }
        }
        return false;
    }

    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead($userId) {
        $this->db->update('notifications', ['is_read' => 1], "user_id = :uid AND is_read = 0", ['uid' => $userId]);
        
        // Also wipe all currently due dynamic notifications
        $dynamic = $this->getDynamicCrmNotifications($userId);
        $currentTime = time();
        foreach ($dynamic as $notif) {
            if (isset($notif['due_date']) && strtotime($notif['due_date']) <= $currentTime) {
                try {
                    $this->db->query("INSERT IGNORE INTO user_dismissed_notifications (user_id, notification_id) VALUES (:uid, :nid)", 
                        ['uid' => $userId, 'nid' => $notif['id']]);
                } catch (Exception $e) {}
            }
        }
        return true;
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
        $standard = $stmt->fetchAll();
        
        // Inject dynamic notifications at the top of the first page
        if ($offset == 0) {
            $dynamic = $this->getDynamicCrmNotifications($userId);
            $standard = array_merge($dynamic, $standard);
        }
        
        return $standard;
    }

    /**
     * Delete all notifications for a user
     * 
     * @param int $userId
     * @return bool
     */
    public function clearAll($userId) {
        $this->db->delete('notifications', "user_id = :uid", ['uid' => $userId]);
        
        // Also wipe all currently due dynamic notifications
        $dynamic = $this->getDynamicCrmNotifications($userId);
        $currentTime = time();
        foreach ($dynamic as $notif) {
            if (isset($notif['due_date']) && strtotime($notif['due_date']) <= $currentTime) {
                try {
                    $this->db->query("INSERT IGNORE INTO user_dismissed_notifications (user_id, notification_id) VALUES (:uid, :nid)", 
                        ['uid' => $userId, 'nid' => $notif['id']]);
                } catch (Exception $e) {}
            }
        }
        return true;
    }
    /**
     * Notify all users who have a specific permission (or are admin)
     * 
     * @param string $permission The permission key required (e.g., 'sales', 'projects')
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $link
     * @return int Number of notifications sent
     */
    public function notifyUsersWithPermission($permission, $title, $message, $type = 'info', $link = null) {
        // Fetch all active users
        $users = $this->db->query("SELECT id, role, permissions FROM users WHERE status = 'active'")->fetchAll();
        
        $count = 0;
        foreach ($users as $user) {
            $shouldNotify = false;

            // 1. Admins always get notified
            if (($user['role'] ?? '') === 'admin') {
                $shouldNotify = true;
            } else {
                // 2. Check specific permission
                $perms = json_decode($user['permissions'] ?? '[]', true);
                if (is_array($perms) && in_array($permission, $perms)) {
                    $shouldNotify = true;
                }
            }

            if ($shouldNotify) {
                $this->create($user['id'], $title, $message, $type, $link);
                $count++;
            }
        }
        return $count;
    }
}
