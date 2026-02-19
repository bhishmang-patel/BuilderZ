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
        
        $investmentId = $this->db->insert('investments', $insertData);

        // Link to Financial Transactions
        if ($investmentId) {
            $transactionData = [
                'transaction_type' => 'income',
                'category' => 'Investment',
                'reference_type' => 'investment',
                'reference_id' => $investmentId,
                'project_id' => $data['project_id'] ?? null,
                'transaction_date' => $data['investment_date'] ?? date('Y-m-d'),
                'amount' => $data['amount'] ?? 0,
                'description' => "Investment from " . ($data['investor_name'] ?? 'Investor') . " (" . ucfirst($data['investment_type'] ?? '') . ")",
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('financial_transactions', $transactionData);
        }

        return $investmentId;
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
        // Delete linked financial transaction
        $this->db->delete('financial_transactions', "reference_type = 'investment' AND reference_id = ?", [$id]);
        // Note: Linked returns and their transactions might need separate cleanup if not handled by FK cascades or distinct logic
        
        return $this->db->delete('investments', 'id = ?', [$id]);
    }

    public function getAllInvestments($filters = []) {
        // 1. Calculate Project Totals (Equity Base vs Debt)
        // We must do this for ALL investments to get accurate totals, regardless of filters
        $statsSql = "SELECT project_id,
                            SUM(CASE WHEN investment_type IN ('partner', 'personal') THEN amount ELSE 0 END) as total_equity_base,
                            SUM(CASE WHEN investment_type = 'loan' THEN amount ELSE 0 END) as total_debt,
                            SUM(amount) as total_capital
                     FROM investments
                     GROUP BY project_id";
        $projectStats = $this->db->query($statsSql)->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        // 2. Fetch Investments with their specific return data
        $sql = "SELECT i.*, p.project_name, 
                       COALESCE(SUM(ir.amount), 0) as total_returned,
                       (i.amount - COALESCE(SUM(ir.amount), 0)) as balance,
                       MAX(ir.return_date) as last_return_date
                FROM investments i 
                LEFT JOIN projects p ON i.project_id = p.id 
                LEFT JOIN investment_returns ir ON i.id = ir.investment_id
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

        $sql .= " GROUP BY i.id ORDER BY i.investment_date DESC";

        $stmt = $this->db->query($sql, $params);
        $investments = $stmt->fetchAll();

        // 3. Process Logic (The Equity Engine)
        foreach ($investments as &$inv) {
            $pid = $inv['project_id'];
            $stats = $projectStats[$pid] ?? ['total_equity_base' => 0, 'total_debt' => 0, 'total_capital' => 0];
            
            $inv['is_equity'] = in_array($inv['investment_type'], ['partner', 'personal']);
            
            // Share % (Ownership) - Only for Equity types, based on Equity Base
            if ($inv['is_equity'] && $stats['total_equity_base'] > 0) {
                $inv['share_percentage'] = ($inv['amount'] / $stats['total_equity_base']) * 100;
            } else {
                $inv['share_percentage'] = 0;
            }

            // Capital Mix % (Leverage context) - Based on Total Capital
            if ($stats['total_capital'] > 0) {
                $inv['capital_mix_percentage'] = ($inv['amount'] / $stats['total_capital']) * 100;
            } else {
                $inv['capital_mix_percentage'] = 0;
            }

            $inv['project_stats'] = $stats; // Attach stats for context if needed
        }

        return $investments;
    }

    public function getInvestmentById($id) {
        // Fetch specific investment
        $sql = "SELECT i.*, p.project_name,
                       COALESCE(SUM(ir.amount), 0) as total_returned,
                       (i.amount - COALESCE(SUM(ir.amount), 0)) as balance
                FROM investments i
                LEFT JOIN projects p ON i.project_id = p.id
                LEFT JOIN investment_returns ir ON i.id = ir.investment_id
                WHERE i.id = ?
                GROUP BY i.id";
        $investment = $this->db->query($sql, [$id])->fetch();

        if ($investment) {
            // Calculate Project Stats just for this project
            $pid = $investment['project_id'];
            $statsSql = "SELECT SUM(CASE WHEN investment_type IN ('partner', 'personal') THEN amount ELSE 0 END) as total_equity_base,
                                SUM(CASE WHEN investment_type = 'loan' THEN amount ELSE 0 END) as total_debt,
                                SUM(amount) as total_capital
                         FROM investments WHERE project_id = ?";
            $stats = $this->db->query($statsSql, [$pid])->fetch();

            $investment['is_equity'] = in_array($investment['investment_type'], ['partner', 'personal']);

            if ($investment['is_equity'] && $stats['total_equity_base'] > 0) {
                $investment['share_percentage'] = ($investment['amount'] / $stats['total_equity_base']) * 100;
            } else {
                $investment['share_percentage'] = 0;
            }

            if ($stats['total_capital'] > 0) {
                $investment['capital_mix_percentage'] = ($investment['amount'] / $stats['total_capital']) * 100;
            } else {
                $investment['capital_mix_percentage'] = 0;
            }
            
            $investment['project_stats'] = $stats;
        }

        return $investment;
    }
    
    public function getProjectInvestments($projectId) {
        return $this->getAllInvestments(['project_id' => $projectId]);
    }

    public function addReturn($investmentId, $data) {
        $data['investment_id'] = $investmentId;
        $data['created_at'] = date('Y-m-d H:i:s');
        
        // Allowed fields
        $fields = ['investment_id', 'amount', 'return_date', 'remarks', 'created_at'];
        $insertData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $insertData[$field] = $data[$field];
            }
        }
        
        $returnId = $this->db->insert('investment_returns', $insertData);

        // Link to Financial Transactions
        if ($returnId) {
            // Fetch investor name for description
            $inv = $this->getInvestmentById($investmentId);
            $investorName = $inv['investor_name'] ?? 'Investor';

            $transactionData = [
                'transaction_type' => 'expenditure',
                'category' => 'Investment Return',
                'reference_type' => 'investment_return',
                'reference_id' => $returnId,
                'project_id' => $inv['project_id'] ?? null,
                'transaction_date' => $data['return_date'] ?? date('Y-m-d'),
                'amount' => $data['amount'] ?? 0,
                'description' => "Return to " . $investorName . " - " . ($data['remarks'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('financial_transactions', $transactionData);
        }

        return $returnId;
    }

    public function getInvestmentReturns($investmentId) {
        return $this->db->select('investment_returns', 'investment_id = ? ORDER BY return_date DESC', [$investmentId])->fetchAll();
    }
}
