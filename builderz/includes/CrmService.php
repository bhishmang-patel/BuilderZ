<?php
require_once __DIR__ . '/../config/database.php';

class CrmService {
    private $db;
    private static $instance = null;

    private function __construct() {
        $this->db = Database::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // --- LEADS MANAGEMENT ---

    public function addLead($data) {
        $fields = [
            'full_name' => $data['full_name'],
            'mobile'    => $data['mobile'],
            'email'     => $data['email'] ?? null,
            'address'   => $data['address'] ?? null,
            'source'    => $data['source'] ?? 'Walk-in',
            'status'    => $data['status'] ?? 'New',
            'notes'     => $data['notes'] ?? null,
            'assigned_to' => $_SESSION['user_id'] ?? null
        ];
        return $this->db->insert('leads', $fields);
    }

    public function updateLead($id, $data) {
        return $this->db->update('leads', $data, "id = $id");
    }

    public function getLead($id) {
        return $this->db->query("SELECT * FROM leads WHERE id = ?", [$id])->fetch();
    }

    public function getLeads($filters = []) {
        $sql = "SELECT * FROM leads WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (full_name LIKE ? OR mobile LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY created_at DESC";
        return $this->db->query($sql, $params)->fetchAll();
    }
    
    public function getLeadStats() {
        return $this->db->query("SELECT status, COUNT(*) as count FROM leads GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // --- FOLLOW-UPS ---

    public function addFollowup($data) {
        $fields = [
            'lead_id' => $data['lead_id'],
            'interaction_type' => $data['interaction_type'],
            'notes' => $data['notes'],
            'followup_date' => !empty($data['followup_date']) ? $data['followup_date'] : null,
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        $id = $this->db->insert('lead_followups', $fields);
        
        // Auto-update lead status if needed
        if ($data['interaction_type'] === 'Site Visit') {
            $this->updateLead($data['lead_id'], ['status' => 'Site Visit']);
        } elseif ($data['interaction_type'] === 'Meeting') {
             $this->updateLead($data['lead_id'], ['status' => 'Interested']);
        }
        
        return $id;
    }

    public function getFollowups($leadId) {
        return $this->db->query("SELECT * FROM lead_followups WHERE lead_id = ? ORDER BY created_at DESC", [$leadId])->fetchAll();
    }
    
    public function getPendingFollowups() {
        $sql = "SELECT f.*, l.full_name, l.mobile 
                FROM lead_followups f 
                JOIN leads l ON f.lead_id = l.id 
                WHERE f.is_completed = 0 
                AND f.followup_date <= CURDATE() 
                ORDER BY f.followup_date ASC";
        return $this->db->query($sql)->fetchAll();
    }
}
