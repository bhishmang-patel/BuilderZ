<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Basic auth check - allow any logged in user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$search = $_GET['search'] ?? '';

try {
    if (strlen($search) < 1) {
        echo json_encode([]);
        exit;
    }

    // Search challans by number and return associated vendor details
    // We want challans where we can import vendor data
    $sql = "SELECT c.id, c.challan_no, c.challan_date, 
                   p.name as vendor_name, 
                   p.mobile, 
                   p.email, 
                   p.address, 
                   p.gst_number
            FROM challans c
            LEFT JOIN parties p ON c.party_id = p.id
            WHERE c.challan_no LIKE ?
            ORDER BY c.created_at DESC
            LIMIT 10";

    $stmt = $db->query($sql, ["%$search%"]);
    $challans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($challans);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
