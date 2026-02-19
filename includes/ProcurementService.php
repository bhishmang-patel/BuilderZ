<?php
require_once __DIR__ . '/../config/database.php';

class ProcurementService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createPO($data, $items) {
        try {
            $this->db->beginTransaction();

            $po_number = $this->generatePONumber();
            
            $poData = [
                'po_number' => $po_number,
                'project_id' => $data['project_id'],
                'vendor_id' => $data['vendor_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending', // Default status
                'created_by' => $_SESSION['user_id']
            ];

            $po_id = $this->db->insert('purchase_orders', $poData);
            $total_amount = 0;

            foreach ($items as $item) {
                $base_amount = $item['quantity'] * $item['rate'];
                $tax_amount = $item['tax_amount'] ?? 0;
                $line_total = $base_amount + $tax_amount;
                
                $total_amount += $line_total;

                $this->db->insert('purchase_order_items', [
                    'po_id' => $po_id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $tax_amount,
                    'total_amount' => $line_total
                ]);
            }

            // Update total amount
            $this->db->update('purchase_orders', ['total_amount' => $total_amount], 'id = ?', ['id' => $po_id]);

            logAudit('create', 'purchase_orders', $po_id, null, ['po_number' => $po_number]);
            
            $this->db->commit();
            return $po_id;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function generatePONumber() {
        $year = date('Y');
        
        // Fetch prefix from settings
        $prefix = 'PO'; // Default
        $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'po_prefix'");
        $result = $stmt->fetch();
        if ($result && !empty($result['setting_value'])) {
            $prefix = $result['setting_value'];
        }

        $countSql = "SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = ?";
        $count = $this->db->query($countSql, [$year])->fetchColumn();
        $next = $count + 1;
        
        return $prefix . '/' . $year . '/' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    public function getPOById($id) {
        $po = $this->db->select('purchase_orders', 'id = ?', [$id])->fetch();
        if (!$po) return null;

        $po['items'] = $this->db->query("
            SELECT poi.*, m.material_name, m.unit 
            FROM purchase_order_items poi 
            JOIN materials m ON poi.material_id = m.id 
            WHERE poi.po_id = ?
        ", [$id])->fetchAll();
        
        // Fetch detailed vendor info
        $vendor = $this->db->select('parties', 'id = ?', [$po['vendor_id']])->fetch();
        if ($vendor) {
            $po['vendor_name']   = $vendor['name'];
            $po['vendor_mobile'] = $vendor['mobile'];
            $po['vendor_gst']    = $vendor['gst_number'];
            $po['vendor_email']  = $vendor['email'];
            $po['vendor_address']= $vendor['address'];
        } else {
            $po['vendor_name'] = 'Unknown Vendor';
        }

        $po['project_name'] = getProjectName($po['project_id']);
        $po['created_by_name'] = $this->getUserName($po['created_by']);

        return $po;
    }

    public function updateStatus($id, $status) {
        $this->db->update('purchase_orders', ['status' => $status], 'id = ?', ['id' => $id]);
        logAudit('update_status', 'purchase_orders', $id, null, ['status' => $status]);
    }
    
    private function getUserName($user_id) {
        $user = $this->db->select('users', 'id = ?', [$user_id], 'full_name')->fetch();
        return $user ? $user['full_name'] : 'Unknown';
    }
    public function updatePOStatus($po_id) {
        // Fetch all items
        $items = $this->db->select('purchase_order_items', 'po_id = ?', [$po_id])->fetchAll();
        
        if (empty($items)) return;

        $all_completed = true;
        $any_received = false;

        foreach ($items as $item) {
            if ($item['received_qty'] > 0) {
                $any_received = true;
            }
            if ($item['received_qty'] < $item['quantity']) {
                $all_completed = false;
            }
        }

        $new_status = 'approved'; // Default if nothing received yet (but it was already approved to be here)
        
        // Fetch current status to ensure we don't regress if manually set (optional, but safer to just calc)
        // Actually, if it's 'completed', and we reverse a challan (future feature), it should go back.
        
        if ($all_completed) {
            $new_status = 'completed';
        } elseif ($any_received) {
            //$new_status = 'partial'; // If we want a partial status. 
            // The current enum in view.php matches: draft, pending, approved, rejected, completed.
            // 'partial' is not explicitly handled in badging there yet, but 'completed' is.
            // Let's stick to 'approved' if partial, or maybe introduc 'partial' if the user wants.
            // For now, let's keep it 'approved' (meaning active) until FULLY completed, 
            // OR if the system supports 'partial', we can use it.
            // Checking view.php badges again... 
            // view.php has: draft, pending, approved, rejected, completed.
            // So 'partial' might break the badge style if not added. 
            // Let's just stick to 'approved' for partial reception, or 'completed' for full.
            // WAIT: The stats count 'Completed'. 
            // User asked: "when will the PO completed numbers will be changed".
            // So strictly only when ALL items are done.
            $new_status = 'approved'; 
        }

        // However, if we want to show progress, 'partial' is good. 
        // But let's strictly answer "Completed".
        
        if ($all_completed) {
            $this->db->update('purchase_orders', ['status' => 'completed'], 'id = ?', ['id' => $po_id]);
            logAudit('auto_update', 'purchase_orders', $po_id, null, ['status' => 'completed']);
        } else {
             // Revert to approved if it was completed but then something changed (unlikely with just add, but good for robustness)
             // Check if currently completed
             $curr = $this->db->select('purchase_orders', 'id=?', [$po_id], 'status')->fetch();
             if ($curr && $curr['status'] === 'completed') {
                 $this->db->update('purchase_orders', ['status' => 'approved'], 'id = ?', ['id' => $po_id]);
             }
        }
    }
}
?>
