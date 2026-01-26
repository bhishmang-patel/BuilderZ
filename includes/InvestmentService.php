<?php

class InvestmentService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createInvestment($data, $userId) {
        $data['created_at'] = date('Y-m-d H:i:s');
        // Removing action field if present
        if (isset($data['action'])) unset($data['action']);
        
        $fields = ['project_id', 'investor_name', 'investment_type', 'amount', 'investment_date', 'remarks'];
        $insertData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $insertData[$field] = $data[$field];
            }
        }
        
        return $this->db->insert('investments', $insertData);
    }

    public function updateInvestment($id, $data) {
        $fields = ['project_id', 'investor_name', 'investment_type', 'amount', 'investment_date', 'remarks'];
        $updateData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        return $this->db->update('investments', $updateData, 'id = ?', [$id]);
    }

    public function deleteInvestment($id) {
        return $this->db->delete('investments', 'id = ?', [$id]);
    }

    public function getAllInvestments($filters = []) {
        $sql = "SELECT i.*, p.project_name 
                FROM investments i 
                LEFT JOIN projects p ON i.project_id = p.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND i.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (i.investor_name LIKE ? OR p.project_name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($filters['investment_type'])) {
            $sql .= " AND i.investment_type = ?";
            $params[] = $filters['investment_type'];
        }

        $sql .= " ORDER BY i.investment_date DESC";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function getInvestmentById($id) {
        return $this->db->select('investments', 'id = ?', [$id])->fetch();
    }
    
    public function getProjectInvestments($projectId) {
        return $this->getAllInvestments(['project_id' => $projectId]);
    }
}
