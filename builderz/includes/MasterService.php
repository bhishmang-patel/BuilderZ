<?php
require_once __DIR__ . '/../config/database.php';

class MasterService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ==========================================
    // WORK ORDERS
    // ==========================================

    public function createWorkOrder($data) {
        // Validation
        if (empty($data['project_id']) || empty($data['contractor_id']) || empty($data['contract_amount'])) {
            throw new Exception("Project, Contractor and Amount are required.");
        }
        
        // Generate Auto WO Number if not provided
        if (empty($data['work_order_no'])) {
            $data['work_order_no'] = 'WO-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        try {
            $id = $this->db->insert('work_orders', $data);
            logAudit('create', 'work_orders', $id, null, $data);
            return $id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                // Assuming unique constraint on work_order_no
                throw new Exception("A Work Order with this number already exists.");
            }
            throw $e;
        }
    }

    public function updateWorkOrder($id, $data) {
        try {
            $this->db->update('work_orders', $data, 'id = ?', ['id' => $id]);
            logAudit('update', 'work_orders', $id, null, $data);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("A Work Order with this number already exists.");
            }
            throw $e;
        }
    }

    public function getAllWorkOrders($filters = []) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['project_id'])) {
            $where .= ' AND w.project_id = ?';
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['contractor_id'])) {
            $where .= ' AND w.contractor_id = ?';
            $params[] = $filters['contractor_id'];
        }

        $sql = "SELECT w.*, p.project_name, c.name as contractor_name, c.contractor_type,
                (SELECT COALESCE(SUM(ch.final_payable_amount), 0) FROM challans ch WHERE ch.work_order_id = w.id) as billed_amount
                FROM work_orders w
                JOIN projects p ON w.project_id = p.id
                JOIN parties c ON w.contractor_id = c.id
                WHERE $where
                ORDER BY w.created_at DESC";
        return $this->db->query($sql, $params)->fetchAll();
    }

    public function calculateContractorBillStats($filters = []) {
        $where = "c.status != 'rejected'";
        $params = [];

        if (!empty($filters['contractor'])) {
            $where .= ' AND c.contractor_id = ?';
            $params[] = $filters['contractor'];
        }
        if (!empty($filters['project'])) {
            $where .= ' AND c.project_id = ?';
            $params[] = $filters['project'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND c.status = ?';
            $params[] = $filters['status'];
        }

        // Join parties if we need to filter by 'type' (contractor_type) or just generally for cleaner query structure
        $sql = "SELECT COUNT(c.id) as total_bills, 
                       COALESCE(SUM(c.total_payable), 0) as total_billed, 
                       COALESCE(SUM(c.paid_amount), 0) as total_paid,
                       COALESCE(SUM(c.pending_amount), 0) as total_pending
                FROM contractor_bills c
                LEFT JOIN parties p ON c.contractor_id = p.id
                WHERE $where";

        if (!empty($filters['type'])) {
            $sql .= " AND p.contractor_type = ?";
            $params[] = $filters['type'];
        }

        $result = $this->db->query($sql, $params)->fetch();
        
        return [
            'total_bills'   => $result['total_bills'],
            'total_billed'  => $result['total_billed'],
            'total_paid'    => $result['total_paid'],
            'total_pending' => $result['total_pending']
        ];
    }
    
    public function calculateWorkOrderStats($filters = []) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['project_id'])) {
            $where .= ' AND w.project_id = ?';
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['contractor_id'])) {
            $where .= ' AND w.contractor_id = ?';
            $params[] = $filters['contractor_id'];
        }

        // 1. Total Active Orders & Contract Value
        $sqlOrders = "SELECT COUNT(*) as active_orders, COALESCE(SUM(contract_amount), 0) as total_value 
                      FROM work_orders w WHERE $where AND w.status = 'active'";
        $orders = $this->db->query($sqlOrders, $params)->fetch();

        // 2. Total Paid Amount (Sum of payments against challans of these work orders)
        // Join payments -> challans -> work_orders
        $sqlPaid = "SELECT COALESCE(SUM(pay.amount), 0) as total_paid
                    FROM payments pay
                    JOIN challans ch ON pay.reference_id = ch.id AND pay.reference_type = 'challan'
                    JOIN work_orders w ON ch.work_order_id = w.id
                    WHERE $where AND w.status = 'active' AND ch.status != 'rejected'";
        $paid = $this->db->query($sqlPaid, $params)->fetch();

        return [
            'active_orders' => $orders['active_orders'],
            'total_value'   => $orders['total_value'],
            'total_paid'    => $paid['total_paid'],
            'pending_value' => max(0, $orders['total_value'] - $paid['total_paid'])
        ];
    }

    public function getWorkOrder($id) {
        $sql = "SELECT w.*, p.project_name, 
                       c.name as contractor_name, c.mobile as contractor_mobile, 
                       c.address as contractor_address, c.gst_number as contractor_gst, 
                       c.pan_number as contractor_pan, c.contractor_type
                FROM work_orders w
                JOIN projects p ON w.project_id = p.id
                JOIN parties c ON w.contractor_id = c.id
                WHERE w.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    // ==========================================
    // PROJECTS
    // ==========================================

    public function getAllProjects($filters = []) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $where .= ' AND (project_name LIKE ? OR location LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($filters['location'])) {
            $where .= ' AND location = ?';
            $params[] = $filters['location'];
        }

        if (!empty($filters['status'])) {
            $where .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM bookings b WHERE b.project_id = p.id AND b.status = 'active') as booked_count,
                 CASE 
                    WHEN p.has_multiple_towers = 1 THEN (
                        SELECT COUNT(DISTINCT SUBSTRING_INDEX(flat_no, '-', 1)) 
                        FROM flats 
                        WHERE project_id = p.id AND flat_no LIKE '%-%'
                    )
                    ELSE 1 
                END as tower_count
                FROM projects p WHERE $where ORDER BY created_at DESC";
        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getDistinctLocations() {
        return $this->db->query("SELECT DISTINCT location FROM projects WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getProject($id) {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM bookings b WHERE b.project_id = p.id AND b.status = 'active') as booked_count
                FROM projects p WHERE id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    public function createProject($data, $userId) {
        $allowedInfos = ['project_name', 'location', 'start_date', 'expected_completion', 'total_floors', 'total_flats', 'total_shops', 'total_offices', 'status', 'has_multiple_towers', 'default_stage_of_work_id', 'land_cost'];
        $insertData = array_intersect_key($data, array_flip($allowedInfos));
        $insertData['created_by'] = $userId;
        
        // Ensure defaults
        $insertData['status'] = $insertData['status'] ?? 'active';
        $insertData['total_floors'] = intval($insertData['total_floors'] ?? 0);
        $insertData['total_flats'] = intval($insertData['total_flats'] ?? 0);
        $insertData['total_shops'] = intval($insertData['total_shops'] ?? 0);
        $insertData['total_offices'] = intval($insertData['total_offices'] ?? 0);
        $insertData['default_stage_of_work_id'] = !empty($data['default_stage_of_work_id']) ? intval($data['default_stage_of_work_id']) : null;
        $insertData['has_multiple_towers'] = isset($data['has_multiple_towers']) ? 1 : 0;
        $insertData['land_cost'] = !empty($data['land_cost']) ? floatval($data['land_cost']) : 0.00;

        $id = $this->db->insert('projects', $insertData);
        logAudit('create', 'projects', $id, null, $insertData);
        return $id;
    }

    public function updateProject($id, $data) {
        $allowedInfos = ['project_name', 'location', 'start_date', 'expected_completion', 'total_floors', 'total_flats', 'total_shops', 'total_offices', 'status', 'has_multiple_towers', 'default_stage_of_work_id', 'land_cost'];
        $updateData = array_intersect_key($data, array_flip($allowedInfos));
        
        $updateData['status'] = $updateData['status'] ?? 'active';
        $updateData['total_floors'] = intval($updateData['total_floors'] ?? 0);
        $updateData['total_flats'] = intval($updateData['total_flats'] ?? 0);
        $updateData['total_shops'] = intval($updateData['total_shops'] ?? 0);
        $updateData['total_offices'] = intval($updateData['total_offices'] ?? 0);
        $updateData['default_stage_of_work_id'] = !empty($data['default_stage_of_work_id']) ? intval($data['default_stage_of_work_id']) : null;
        $updateData['has_multiple_towers'] = isset($data['has_multiple_towers']) ? 1 : 0;
        $updateData['land_cost'] = !empty($data['land_cost']) ? floatval($data['land_cost']) : 0.00;

        $this->db->update('projects', $updateData, 'id = ?', ['id' => $id]);
        logAudit('update', 'projects', $id, null, $updateData);
        return true;
    }

    public function deleteProject($id) {
        $stmt = $this->db->select('bookings',('project_id = ?'), [$id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Cannot delete project with active bookings.");
        }
        $stmt = $this->db->select('flats', ('project_id = ?'), [$id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Cannot delete project that has flats.");
        }
        logAudit('delete', 'projects', $id);
        return $this->db->delete('projects', 'id = ?', [$id]);
    }

    // --- Flats Management ---

    public function getAllFlats($filters = []) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['project_id'])) {
            $where .= ' AND f.project_id = ?';
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND f.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND f.flat_no LIKE ?';
            $params[] = "%{$filters['search']}%";
        }

        $sql = "SELECT f.*, p.project_name, 
                       b.agreement_value as booked_amount, 
                       b.rate as booked_rate
                FROM flats f
                JOIN projects p ON f.project_id = p.id
                LEFT JOIN bookings b ON f.id = b.flat_id AND b.status != 'cancelled'
                WHERE $where
                ORDER BY p.project_name, f.floor, f.flat_no";
        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getFlat($id) {
        return $this->db->select('flats', 'id = ?', [$id])->fetch();
    }

    public function createFlat($data) {
        // Basic validation
        if (empty($data['project_id']) || empty($data['flat_no'])) {
            throw new Exception("Project and Flat Number are required.");
        }
        $id = $this->db->insert('flats', $data);
        logAudit('create', 'flats', $id, null, $data);
        return $id;
    }

    public function updateFlat($id, $data) {
        $this->db->update('flats', $data, 'id = ?', ['id' => $id]);
        logAudit('update', 'flats', $id, null, $data);
        return true;
    }

    public function deleteFlat($id) {
        $stmt = $this->db->select('bookings', 'flat_id = ?', [$id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Cannot delete booked flat.");
        }
        logAudit('delete', 'flats', $id);
        return $this->db->delete('flats', 'id = ?', [$id]);
    }

    public function bulkDeleteFlats($ids) {
        if (empty($ids)) {
            throw new Exception("No flats selected for deletion.");
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // 1. Check if any selected flat is booked/sold
        $checkSql = "SELECT flat_no FROM flats WHERE id IN ($placeholders) AND status != 'available'";
        $stmt = $this->db->query($checkSql, $ids);
        if ($stmt->rowCount() > 0) {
            $invalid = $stmt->fetchAll(PDO::FETCH_COLUMN);
            throw new Exception("Cannot delete the following flats as they are not available: " . implode(', ', $invalid));
        }

        // 2. Check for dependency (bookings) - Redundant if status check works perfectly but good for safety
        $checkBookings = "SELECT f.flat_no FROM bookings b JOIN flats f ON b.flat_id = f.id WHERE b.flat_id IN ($placeholders)";
        $stmt = $this->db->query($checkBookings, $ids);
        if ($stmt->rowCount() > 0) {
            $bookedFlats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            throw new Exception("Cannot delete the following flats as they have booking records: " . implode(', ', $bookedFlats));
        }

        // 3. Delete
        $this->db->beginTransaction();
        try {
            $deleteSql = "DELETE FROM flats WHERE id IN ($placeholders)";
            $this->db->query($deleteSql, $ids);
            
            // Audit Log (Bulk)
            logAudit('bulk_delete', 'flats', 0, null, ['deleted_ids' => $ids]);
            
            $this->db->commit();
            return count($ids);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function bulkCreateFlats($projectId, $floorCount, $flatsPerFloor, $prefix, $areaSqft, $ratePerSqft) {
        $this->db->beginTransaction();
        try {
            $count = 0;
            for ($floor = 1; $floor <= $floorCount; $floor++) {
                for ($flat = 1; $flat <= $flatsPerFloor; $flat++) {
                    $flatNo = $prefix . $floor . str_pad($flat, 2, '0', STR_PAD_LEFT);
                    
                    // Check if flat already exists (optional but good practice)
                    // For now, we assume user knows what they are doing or DB unique constraint handles it
                    
                    $data = [
                        'project_id' => $projectId,
                        'flat_no' => $flatNo,
                        'floor' => $floor,
                        'area_sqft' => $areaSqft,
                        'rate_per_sqft' => $ratePerSqft,
                        'status' => 'available'
                    ];
                    $id = $this->db->insert('flats', $data);
                    logAudit('create', 'flats', $id, null, $data);
                    $count++;
                }
            }
            $this->db->commit();
            return $count;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getFlatStats() {
        return $this->db->query("SELECT status, COUNT(*) as count FROM flats GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getDetailedStats() {
        return $this->db->query("SELECT unit_type, status, COUNT(*) as count FROM flats GROUP BY unit_type, status")->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Parties Management ---

    public function getAllParties($filters = []) {
        $where = '1=1';
        $params = [];
        $joinChallans = false;

        if (!empty($filters['type'])) {
            $where .= ' AND p.party_type = ?';
            $params[] = $filters['type'];
            
            // If fetching vendors, we might want material info
            if ($filters['type'] === 'vendor') {
                $joinChallans = true;
            }
        }
        
        if (!empty($filters['search'])) {
            $where .= ' AND (p.name LIKE ? OR p.mobile LIKE ? OR p.email LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        // New Vendor Filters
        if (!empty($filters['vendor_type'])) {
            $where .= ' AND p.vendor_type = ?';
            $params[] = $filters['vendor_type'];
        }
        if (!empty($filters['contractor_type'])) {
            $where .= ' AND p.contractor_type = ?';
            $params[] = $filters['contractor_type'];
        }
        if (!empty($filters['city'])) {
             $where .= ' AND p.city = ?';
             $params[] = $filters['city'];
        }
        if (!empty($filters['gst_status'])) {
            $where .= ' AND p.gst_status = ?';
            $params[] = $filters['gst_status'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = $filters['status'];
        } else {
            // Default to NOT showing pending vendors
            $where .= " AND p.status != 'pending'";
        }

        if (!empty($filters['material'])) {
            $where .= " AND m.material_name LIKE ?";
            $params[] = "%{$filters['material']}%";
            $joinChallans = true;
        }

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM bills b WHERE b.party_id = p.id) as bill_count,
                (SELECT GROUP_CONCAT(DISTINCT CONCAT(pr.id, ':', pr.project_name) SEPARATOR '||') 
                 FROM challans ch 
                 JOIN projects pr ON ch.project_id = pr.id 
                 WHERE ch.party_id = p.id) as vendor_projects,
                (SELECT COALESCE(SUM(amount), 0) FROM bills b WHERE b.party_id = p.id) as total_billed_amount,
                (p.opening_balance + (SELECT COALESCE(SUM(b.amount - b.paid_amount), 0) FROM bills b WHERE b.party_id = p.id AND b.status != 'rejected')) as outstanding_balance";

        if ($joinChallans) {
             $sql .= ", GROUP_CONCAT(DISTINCT m.material_name SEPARATOR ', ') as supplied_materials,
                       SUM(ci.quantity) as total_quantity";
        }

        $sql .= " FROM parties p ";

        if ($joinChallans) {
            $sql .= " LEFT JOIN challans c ON p.id = c.party_id
                      LEFT JOIN challan_items ci ON c.id = ci.challan_id
                      LEFT JOIN materials m ON ci.material_id = m.id";
        }

        $sql .= " WHERE $where";
        
        if ($joinChallans) {
            $sql .= " GROUP BY p.id";
        }
        
        $sql .= " ORDER BY p.name ASC";
        
        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getParty($id) {
        return $this->db->select('parties', 'id = ?', [$id])->fetch();
    }

    public function createParty($data) {
        if (!empty($data['gst_number'])) {
            $data['gst_number'] = strtoupper(trim($data['gst_number']));
            if (!preg_match("/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/", $data['gst_number'])) {
                throw new Exception("Invalid GST Number format. It must be exactly 15 characters (e.g. 22AAAAA0000A1Z5).");
            }
        }
        try {
            $id = $this->db->insert('parties', $data);
            logAudit('create', 'parties', $id, null, $data);
            return $id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'idx_unique_gst') !== false) {
                    throw new Exception("A vendor with this GST Number already exists.");
                }
            }
            throw $e;
        }
    }

    public function updateParty($id, $data) {
        if (!empty($data['gst_number'])) {
            $data['gst_number'] = strtoupper(trim($data['gst_number']));
            if (!preg_match("/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/", $data['gst_number'])) {
                throw new Exception("Invalid GST Number format. It must be exactly 15 characters (e.g. 22AAAAA0000A1Z5).");
            }
        }
        try {
            $this->db->update('parties', $data, 'id = ?', ['id' => $id]);
            logAudit('update', 'parties', $id, null, $data);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'idx_unique_gst') !== false) {
                    throw new Exception("A vendor with this GST Number already exists.");
                }
            }
            throw $e;
        }
    }

    public function deleteParty($id) {
        // Check for dependencies in Challans
        $count = $this->db->query("SELECT COUNT(*) FROM challans WHERE party_id = ?", [$id])->fetchColumn();
        if ($count > 0) {
            throw new Exception("Cannot delete vendor: They have existing challans linked.");
        }
        
        // Check for dependencies in Purchase Orders
        $count = $this->db->query("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ?", [$id])->fetchColumn();
        if ($count > 0) {
            throw new Exception("Cannot delete vendor: They have existing Purchase Orders linked.");
        }

        // Check for dependencies in Payments
        $count = $this->db->query("SELECT COUNT(*) FROM payments WHERE party_id = ?", [$id])->fetchColumn();
        if ($count > 0) {
            throw new Exception("Cannot delete vendor: They have existing Payments linked.");
        }
        
        // Check for dependencies in Bills
        $count = $this->db->query("SELECT COUNT(*) FROM bills WHERE party_id = ?", [$id])->fetchColumn();
        if ($count > 0) {
            throw new Exception("Cannot delete vendor: They have existing bills linked.");
        }

        logAudit('delete', 'parties', $id);
        return $this->db->delete('parties', 'id = ?', [$id]);
    }

    // ==========================================
    // COMPANY BANK ACCOUNTS
    // ==========================================

    public function getAllBankAccounts($filters = []) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $sql = "SELECT * FROM company_accounts WHERE $where ORDER BY bank_name, account_name";
        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getActiveBankAccounts() {
        return $this->db->query("SELECT * FROM company_accounts WHERE status = 'active' ORDER BY account_name")->fetchAll();
    }

    public function getBankAccount($id) {
        return $this->db->select('company_accounts', 'id = ?', [$id])->fetch();
    }

    public function createBankAccount($data) {
        // Validation
        if (empty($data['bank_name']) || empty($data['account_number']) || empty($data['account_name'])) {
            throw new Exception("Bank Name, Account Name and Account Number are required.");
        }

        // Ensure defaults
        $data['account_type'] = $data['account_type'] ?? 'current';

        try {
            $id = $this->db->insert('company_accounts', $data);
            logAudit('create', 'company_accounts', $id, null, $data);
            return $id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 throw new Exception("An account with this number already exists.");
            }
            throw $e;
        }
    }

    public function updateBankAccount($id, $data) {
        $this->db->update('company_accounts', $data, 'id = ?', ['id' => $id]);
        logAudit('update', 'company_accounts', $id, null, $data);
        return true;
    }

    public function deleteBankAccount($id) {
        // Check for dependencies (Future: Payments)
        // For now, just soft delete or check if payments exist
        /*
        $count = $this->db->query("SELECT COUNT(*) FROM payments WHERE bank_account_id = ?", [$id])->fetchColumn();
        if ($count > 0) {
            throw new Exception("Cannot delete account: Transactions exist.");
        }
        */
        
        logAudit('delete', 'company_accounts', $id);
        return $this->db->delete('company_accounts', 'id = ?', [$id]);
    }
}
