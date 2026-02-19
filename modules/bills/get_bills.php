<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$vendor_id = intval($_GET['vendor_id'] ?? 0);
$db = Database::getInstance();

try {
    if (!$vendor_id) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT b.*, c.challan_no 
            FROM bills b
            LEFT JOIN challans c ON b.challan_id = c.id
            WHERE b.party_id = ?
            ORDER BY b.bill_date DESC, b.created_at DESC";

    $stmt = $db->query($sql, [$vendor_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($bills);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
