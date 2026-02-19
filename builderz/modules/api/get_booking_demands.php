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
$demands = $db->query("SELECT bd.id, bd.stage_name, bd.demand_amount, bd.paid_amount, bd.due_date 
                       FROM booking_demands bd
                       JOIN bookings b ON bd.booking_id = b.id
                       WHERE bd.booking_id = ? AND bd.status != 'paid' AND b.status != 'cancelled'
                       ORDER BY bd.generated_date ASC", [$booking_id])->fetchAll();

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
