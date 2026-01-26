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
        throw new Exception("Vendor ID is required");
    }

    // Get Challans for this vendor that are NOT linked to any bill yet
    $sql = "SELECT c.id, c.challan_no, c.challan_date, c.total_amount, 
                   (SELECT GROUP_CONCAT(m.material_name SEPARATOR ', ') 
                    FROM challan_items ci 
                    JOIN materials m ON ci.material_id = m.id 
                    WHERE ci.challan_id = c.id) as materials,
                   (SELECT COALESCE(SUM(quantity), 0) FROM challan_items ci WHERE ci.challan_id = c.id) as total_quantity
            FROM challans c
            WHERE c.party_id = ? 
              AND c.challan_type = 'material'
              AND c.id NOT IN (SELECT challan_id FROM bills WHERE challan_id IS NOT NULL)
            ORDER BY c.created_at DESC";

    $stmt = $db->query($sql, [$vendor_id]);
    $challans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($challans);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
