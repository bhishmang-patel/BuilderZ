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

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if ($vendor_id <= 0) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();

try {
    // Fetch unbilled items for this vendor
    // We join challan_items -> challans -> materials
    // Filter by: Vendor, Approved Challan, Not Billed (bill_id IS NULL or 0)
    $sql = "SELECT 
                ci.id as item_id,
                ci.challan_id,
                c.challan_no,
                c.challan_date,
                ci.material_id, 
                m.material_name, 
                m.unit, 
                ci.quantity,
                ci.rate
            FROM challan_items ci
            JOIN challans c ON ci.challan_id = c.id
            JOIN materials m ON ci.material_id = m.id
            WHERE c.party_id = ?
              AND c.status = 'approved'
              AND (c.bill_id IS NULL OR c.bill_id = 0)
            ORDER BY c.created_at DESC, m.material_name ASC";

    $items = $db->query($sql, [$vendor_id])->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($items);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
