<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // 1. Fetch existing contractor challans
    $stmt = $db->query("SELECT * FROM challans WHERE challan_type = 'contractor'");
    $challans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($challans as $c) {
        // Map fields
        $data = [
            'project_id'       => $c['project_id'],
            'contractor_id'    => $c['party_id'],
            'work_order_id'    => $c['work_order_id'],
            'bill_no'          => $c['challan_no'],
            'bill_date'        => $c['challan_date'],
            'work_description' => $c['work_description'] ?? '', 
            'work_from_date'   => $c['work_from_date'],
            'work_to_date'     => $c['work_to_date'],
            'basic_amount'     => $c['bill_amount'] ?? 0, // Assuming bill_amount in challans was basic
            'gst_percentage'   => 0, // Not explicitly stored in challans usually, calculated or 0
            'gst_amount'       => $c['gst_amount'] ?? 0,
            'tds_percentage'   => 0,
            'tds_amount'       => $c['tds_amount'] ?? 0,
            'is_rcm'           => $c['is_rcm'] ?? 0,
            'total_payable'    => $c['final_payable_amount'] > 0 ? $c['final_payable_amount'] : $c['total_amount'],
            'paid_amount'      => $c['paid_amount'],
            'pending_amount'   => $c['pending_amount'],
            'status'           => $c['status'] ?? 'pending',
            'payment_status'   => $c['payment_status'] ?? 'pending',
            'created_by'       => $c['created_by'],
            'created_at'       => $c['created_at']
        ];

        // Check availability of columns in source to avoid undefined index if schema varies
        // But for likely columns:
        if (isset($c['gst_rate'])) $data['gst_percentage'] = $c['gst_rate'];

        $db->insert('contractor_bills', $data);
        $count++;
    }

    $db->commit();
    echo "Migration completed. moved $count records.";

} catch (Exception $e) {
    $db->rollback();
    echo "Migration failed: " . $e->getMessage();
}
