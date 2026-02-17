<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Missing ID');
    }

    $db = Database::getInstance();
    $id = intval($_GET['id']);

    // Fetch Plan
    $plan = $db->query("SELECT * FROM stage_of_work WHERE id = ?", [$id])->fetch();
    if (!$plan) {
        throw new Exception('Plan not found');
    }

    // Fetch Items
    $items = $db->query("SELECT * FROM stage_of_work_items WHERE stage_of_work_id = ? ORDER BY stage_order ASC", [$id])->fetchAll();

    echo json_encode(['success' => true, 'plan' => $plan, 'items' => $items]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
