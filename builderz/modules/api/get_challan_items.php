<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

if (!isset($_GET['ids'])) {
    echo json_encode([]);
    exit;
}

$ids = explode(',', $_GET['ids']);
// Sanitize ids to integers
$ids = array_map('intval', $ids);
$ids = array_filter($ids);

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT 
            ci.id, 
            ci.challan_id, 
            c.challan_no, 
            c.challan_date, 
            m.material_name, 
            m.unit, 
            ci.quantity, 
            ci.rate, 
            ci.tax_rate,
            ci.tax_amount,
            ci.total_amount
        FROM challan_items ci 
        JOIN challans c ON ci.challan_id = c.id 
        JOIN materials m ON ci.material_id = m.id 
        WHERE ci.challan_id IN ($placeholders)
        ORDER BY c.challan_date ASC, c.challan_no ASC";

try {
    $stmt = $db->query($sql, $ids);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($items);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
