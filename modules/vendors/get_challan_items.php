<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Ensure user is logged in
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$challan_id = isset($_GET['challan_id']) ? intval($_GET['challan_id']) : 0;

if ($challan_id <= 0) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();

try {
    // Fetch items for this challan
    $items = $db->query(
        "SELECT 
            ci.id as item_id,
            ci.material_id, 
            c.id as challan_id,
            c.challan_no, 
            m.material_name, 
            m.unit, 
            ci.quantity, 
            ci.rate 
         FROM challan_items ci
         JOIN materials m ON ci.material_id = m.id
         JOIN challans c ON ci.challan_id = c.id
         WHERE ci.challan_id = ?",
        [$challan_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($items);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
