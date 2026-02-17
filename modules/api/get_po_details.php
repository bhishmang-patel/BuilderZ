<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['po_id'])) {
    echo json_encode([]);
    exit;
}

$po_id = intval($_GET['po_id']);
$db = Database::getInstance();

$po = $db->query("
    SELECT po.id, po.po_number, po.vendor_id, 
           v.name as vendor_name, v.mobile as vendor_mobile, 
           v.email as vendor_email, v.address as vendor_address, 
           v.gst_number as vendor_gst
    FROM purchase_orders po
    LEFT JOIN parties v ON po.vendor_id = v.id
    WHERE po.id = ?
", [$po_id])->fetch(PDO::FETCH_ASSOC);

$items = $db->query("
    SELECT poi.*, m.material_name, m.unit, m.default_rate as current_market_rate
    FROM purchase_order_items poi
    JOIN materials m ON poi.material_id = m.id
    WHERE poi.po_id = ?
", [$po_id])->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'po' => $po,
    'items' => $items
]);
?>
