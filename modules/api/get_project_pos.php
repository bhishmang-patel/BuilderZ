<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['project_id'])) {
    echo json_encode([]);
    exit;
}

$project_id = intval($_GET['project_id']);
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : null;

// DEBUG LOG
file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " - Req: Prj=$project_id, Ven=$vendor_id\n", FILE_APPEND);

$db = Database::getInstance();
$sql = "SELECT id, po_number, total_amount, order_date 
        FROM purchase_orders 
        WHERE project_id = ? AND status = 'approved'";
$params = [$project_id];

if ($vendor_id) {
    $sql .= " AND vendor_id = ?";
    $params[] = $vendor_id;
}

$sql .= " ORDER BY id DESC";

$pos = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

// DEBUG LOG
file_put_contents(__DIR__ . '/debug_log.txt', date('Y-m-d H:i:s') . " - Found: " . count($pos) . " POs\n", FILE_APPEND);


echo json_encode($pos);
