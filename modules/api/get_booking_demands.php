<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

header('Content-Type: application/json');

$booking_id = intval($_GET['booking_id'] ?? 0);

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Booking ID']);
    exit;
}

$db = Database::getInstance();
$demands = $db->query("SELECT id, stage_name, demand_amount, paid_amount, due_date 
                       FROM booking_demands 
                       WHERE booking_id = ? AND status != 'paid' 
                       ORDER BY generated_date ASC", [$booking_id])->fetchAll();

$data = [];
foreach ($demands as $d) {
    $data[] = [
        'id' => $d['id'],
        'label' => $d['stage_name'] . ' - ₹' . ($d['demand_amount'] - $d['paid_amount']) . ' (Due: ' . formatDate($d['due_date']) . ')'
    ];
}

echo json_encode([
    'success' => true,
    'demands' => $data
]);
