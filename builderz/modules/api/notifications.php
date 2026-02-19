<?php
require_once __DIR__ . '/../../config/database.php';
session_start();
require_once __DIR__ . '/../../includes/NotificationService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notificationService = new NotificationService();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_unread_count':
            $count = $notificationService->getUnreadCount($userId);
            echo json_encode(['count' => $count]);
            break;

        case 'get_recent':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $notifications = $notificationService->getRecent($userId, $limit);
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'mark_read':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['id'])) {
                    $notificationService->markAsRead($input['id'], $userId);
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Missing notification ID');
                }
            }
            break;

        case 'mark_all_read':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $notificationService->markAllAsRead($userId);
                echo json_encode(['success' => true]);
            }
            break;

        case 'clear_all':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $notificationService->clearAll($userId);
                echo json_encode(['success' => true]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
