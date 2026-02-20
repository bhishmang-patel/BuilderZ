<?php
require_once __DIR__ . '/../config/database.php';

class ReportService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch financial overview data including income and expenditure.
     * 
     * @param string $date_from Start date
     * @param string $date_to End date
     * @param int|string $project_filter Optional project ID to filter by
     * @return array Contains income, expenditure, key totals, and daily cash flow
     */
    public function getFinancialOverview($date_from, $date_to, $project_filter = '') {

        /* =========================
        FETCH INCOME
        ========================== */
        $income_sql = "SELECT 
            'Customer Receipt' as category,
            p.payment_date as transaction_date,
            p.amount,
            pt.name as party_name,
            pr.project_name,
            pr.id as project_id
        FROM payments p
        JOIN parties pt ON p.party_id = pt.id
        LEFT JOIN bookings b ON p.reference_type = 'booking' AND p.reference_id = b.id
        LEFT JOIN flats f ON b.flat_id = f.id
        LEFT JOIN projects pr ON f.project_id = pr.id
        WHERE p.payment_type = 'customer_receipt'
        AND p.payment_date BETWEEN ? AND ?
        " . ($project_filter ? " AND pr.id = ?" : "") . "

        UNION ALL

        SELECT 
            'Cancellation Charges' as category,
            ft.transaction_date,
            ft.amount,
            '-' as party_name,
            pr.project_name,
            pr.id as project_id
        FROM financial_transactions ft
        LEFT JOIN projects pr ON ft.project_id = pr.id
        WHERE ft.transaction_type = 'income'
        AND ft.transaction_date BETWEEN ? AND ?
        " . ($project_filter ? " AND ft.project_id = ?" : "") . "

        ORDER BY transaction_date ASC";

        $params = [$date_from, $date_to];
        if ($project_filter) $params[] = $project_filter;
        $params = array_merge($params, [$date_from, $date_to]);
        if ($project_filter) $params[] = $project_filter;

        $income_data = $this->db->query($income_sql, $params)->fetchAll();


        /* =========================
        FETCH EXPENDITURE
        ========================== */
        $expenditure_sql = "SELECT 
            CASE 
                WHEN p.payment_type = 'vendor_payment' THEN 'Vendor Payment'
                WHEN p.payment_type = 'vendor_bill_payment' THEN 'Vendor Bill Payment'
                WHEN p.payment_type = 'contractor_payment' THEN 'Contractor Bill Payment'
                WHEN p.payment_type = 'customer_refund' THEN 'Customer Refund'
            END as category,
            p.payment_date as transaction_date,
            p.amount,
            CONVERT(pt.name USING utf8mb4) as party_name,
            CONVERT(COALESCE(pr.project_name, ch_proj.project_name, cb_proj.project_name, '-') USING utf8mb4) as project_name,
            COALESCE(pr.id, ch_proj.id, cb_proj.id) as project_id
        FROM payments p
        JOIN parties pt ON p.party_id = pt.id
        LEFT JOIN bills vb ON p.payment_type = 'vendor_bill_payment' AND p.reference_id = vb.id
        LEFT JOIN (SELECT bill_id, MAX(project_id) as project_id FROM challans GROUP BY bill_id) ch ON vb.id = ch.bill_id
        LEFT JOIN projects ch_proj ON ch.project_id = ch_proj.id
        LEFT JOIN contractor_bills cb ON p.payment_type = 'contractor_payment' AND p.reference_id = cb.id
        LEFT JOIN projects cb_proj ON cb.project_id = cb_proj.id
        LEFT JOIN bookings b ON p.payment_type = 'customer_refund' AND p.reference_type = 'booking_cancellation' AND p.reference_id = b.id
        LEFT JOIN projects pr ON b.project_id = pr.id
        
        WHERE p.payment_type IN ('vendor_payment','vendor_bill_payment','contractor_payment','customer_refund')
        AND p.payment_date BETWEEN ? AND ?

        UNION ALL

        SELECT 
            ft.category,
            ft.transaction_date,
            ft.amount,
            CONVERT(COALESCE(inv.investor_name, ft.description, '-') USING utf8mb4) as party_name,
            CONVERT(pr.project_name USING utf8mb4) as project_name,
            pr.id as project_id
        FROM financial_transactions ft
        LEFT JOIN projects pr ON ft.project_id = pr.id
        LEFT JOIN investment_returns ir ON ft.reference_type = 'investment_return' AND ft.reference_id = ir.id
        LEFT JOIN investments inv ON ir.investment_id = inv.id
        
        WHERE ft.transaction_type = 'expenditure'
        AND ft.transaction_date BETWEEN ? AND ?
        " . ($project_filter ? " AND ft.project_id = ?" : "") . "

        ORDER BY transaction_date ASC";

        $exp_params = [$date_from, $date_to, $date_from, $date_to];
        if ($project_filter) $exp_params[] = $project_filter;

        $expenditure_data = $this->db->query($expenditure_sql, $exp_params)->fetchAll();

        // MERGE EXPENSES INTO EXPENDITURE DATA
        $expense_sql = "SELECT 
            ec.name as category,
            e.date as transaction_date,
            e.amount,
            ec.name as party_name,
            p.project_name,
            p.id as project_id
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN projects p ON e.project_id = p.id
        WHERE e.date BETWEEN ? AND ?
        " . ($project_filter ? " AND e.project_id = ?" : "") . "
        ORDER BY transaction_date ASC";

        $exp_params2 = [$date_from, $date_to];
        if ($project_filter) $exp_params2[] = $project_filter;

        $expense_data = $this->db->query($expense_sql, $exp_params2)->fetchAll();
        
        $expenditure_data = array_merge($expenditure_data, $expense_data);
        
        // Re-sort matched array by date
        usort($expenditure_data, function($a, $b) {
            return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
        });


        /* =========================
              FETCH INVESTMENTS
        ========================== */
        $invest_sql = "SELECT 
            'Investment' as category,
            investment_date as transaction_date,
            amount,
            investor_name as party_name,
            p.project_name,
            p.id as project_id
        FROM investments i
        LEFT JOIN projects p ON i.project_id = p.id
        WHERE investment_date BETWEEN ? AND ?
        " . ($project_filter ? " AND i.project_id = ?" : "") . "
        ORDER BY investment_date ASC";

        $invest_params = [$date_from, $date_to];
        if ($project_filter) $invest_params[] = $project_filter;

        $investment_data = $this->db->query($invest_sql, $invest_params)->fetchAll();


        /* =========================
                CALCULATIONS 
        ========================== */

        // FIX 1: Total income must include ALL income categories
        $total_income = array_sum(array_column($income_data, 'amount'));

        $total_expenditure = array_sum(array_column($expenditure_data, 'amount'));

        // Profit = Performance (Investment NEVER included)
        $net_profit = $total_income - $total_expenditure;

        $total_invested = array_sum(array_column($investment_data, 'amount'));

        $roi = $total_invested > 0 ? ($net_profit / $total_invested) * 100 : 0;

        // Period cash position (Opening balance assumed 0)
        $cash_balance = $total_invested + $total_income - $total_expenditure;


        /* =========================
            GROUPING & CASHFLOW
        ========================== */

        $income_by_category = $this->groupByCategory($income_data);
        $expenditure_by_category = $this->groupByCategory($expenditure_data);

        $daily_cashflow = $this->calculateDailyCashflow(
            $income_data,
            $expenditure_data,
            $investment_data
        );

        return [
            'income_data' => $income_data,
            'expenditure_data' => $expenditure_data,
            'investment_data' => $investment_data,

            'total_income' => $total_income,
            'total_expenditure' => $total_expenditure,
            'net_profit' => $net_profit,

            'total_invested' => $total_invested,
            'total_returned' => 0, // Not calculated here
            'net_invested'   => 0, // Not calculated here
            'roi' => $roi,
            'cash_balance' => $cash_balance,

            'income_by_category' => $income_by_category,
            'expenditure_by_category' => $expenditure_by_category,
            'daily_cashflow' => $daily_cashflow
        ];
    }


    private function groupByCategory($data) {
        $grouped = [];
        foreach ($data as $item) {
            $cat = $item['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = ['amount' => 0, 'count' => 0];
            }
            $grouped[$cat]['amount'] += $item['amount'];
            $grouped[$cat]['count']++;
        }
        return $grouped;
    }

    private function calculateDailyCashflow($income_data, $expenditure_data, $investment_data = []) {

        $transactions = [];

        foreach ($income_data as $row) {
            $transactions[] = [
                'date' => $row['transaction_date'],
                'amount' => $row['amount'],
                'type' => 'inflow',
                'category' => $row['category'],
                'party_name' => $row['party_name'] ?? '-',
                'project_name' => $row['project_name'] ?? '-',
                'project_id' => $row['project_id'] ?? null
            ];
        }

        foreach ($investment_data as $row) {
            $transactions[] = [
                'date' => $row['transaction_date'],
                'amount' => $row['amount'],
                'type' => 'inflow',
                'category' => 'Investment',
                'party_name' => $row['party_name'] ?? '-',
                'project_name' => $row['project_name'] ?? '-',
                'project_id' => $row['project_id'] ?? null
            ];
        }

        foreach ($expenditure_data as $row) {
            $transactions[] = [
                'date' => $row['transaction_date'],
                'amount' => $row['amount'],
                'type' => 'outflow',
                'category' => $row['category'],
                'party_name' => $row['party_name'] ?? '-',
                'project_name' => $row['project_name'] ?? '-',
                'project_id' => $row['project_id'] ?? null
            ];
        }

        // Sort by Date ASC
        usort($transactions, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

        // Calculate Running Balance
        $running_balance = 0;
        foreach ($transactions as &$txn) {
            if ($txn['type'] === 'inflow') {
                $running_balance += $txn['amount'];
            } else {
                $running_balance -= $txn['amount'];
            }
            $txn['balance'] = $running_balance;
        }

        return $transactions; // Return detailed list instead of grouped daily
    }


    public function getProjectPL() {
        $sql = "SELECT p.id, p.project_name, p.location, p.status,
               -- Active bookings count
               (SELECT COUNT(DISTINCT b.id) 
                FROM bookings b 
                JOIN flats f ON b.flat_id = f.id 
                WHERE f.project_id = p.id AND b.status = 'active') as total_bookings,
                
               -- Total sales (only active bookings)
               (SELECT COALESCE(SUM(b.agreement_value), 0) 
                FROM bookings b 
                JOIN flats f ON b.flat_id = f.id 
                WHERE f.project_id = p.id AND b.status = 'active') as total_sales,
                
               -- Total received from customer receipts
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                JOIN bookings b ON pay.reference_type = 'booking' AND pay.reference_id = b.id
                JOIN flats f ON b.flat_id = f.id
                WHERE f.project_id = p.id 
                AND pay.payment_type = 'customer_receipt') as total_received,
                
               -- Customer pending (only active bookings)
               (SELECT COALESCE(SUM(b.total_pending), 0) 
                FROM bookings b 
                JOIN flats f ON b.flat_id = f.id 
                WHERE f.project_id = p.id AND b.status = 'active') as customer_pending,
                
               -- Cancellation income (deduction charges kept)
               (SELECT COALESCE(SUM(ft.amount), 0)
                FROM financial_transactions ft
                WHERE ft.project_id = p.id
                AND ft.transaction_type = 'income'
                AND ft.category = 'cancellation_charges') as cancellation_income,
                
               -- Customer refunds (money returned)
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                JOIN booking_cancellations bc ON pay.reference_type = 'booking_cancellation' AND pay.reference_id = bc.id
                JOIN bookings b ON bc.booking_id = b.id
                JOIN flats f ON b.flat_id = f.id
                WHERE f.project_id = p.id
                AND pay.payment_type = 'customer_refund') as total_refunds,
                
               -- Material expenses
               (SELECT COALESCE(SUM(mu.quantity * m.default_rate), 0) 
                FROM material_usage mu 
                JOIN materials m ON mu.material_id = m.id
                WHERE mu.project_id = p.id) as material_cost,
                

                
                -- Vendor payments (Both direct old payments and new bill payments)
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                LEFT JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id
                LEFT JOIN bills b ON pay.reference_type = 'bill' AND pay.reference_id = b.id
                WHERE (c.project_id = p.id OR b.id IN (SELECT DISTINCT bill_id FROM challans WHERE project_id = p.id AND bill_id IS NOT NULL))
                AND pay.payment_type IN ('vendor_payment', 'vendor_bill_payment')) as vendor_payments,




               -- Contractor payments
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                LEFT JOIN contractor_bills cb ON pay.reference_type = 'contractor_bill' AND pay.reference_id = cb.id
                LEFT JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id
                WHERE (cb.project_id = p.id OR (c.project_id = p.id AND pay.payment_type = 'contractor_payment'))
                AND pay.payment_type = 'contractor_payment') as contractor_payments,

               -- Other Expenses (Direct Financial Transactions)
               (SELECT COALESCE(SUM(ft.amount), 0)
                FROM financial_transactions ft
                WHERE ft.project_id = p.id
                AND ft.transaction_type = 'expenditure') as other_expenses,

               -- General Expenses (from expenses table)
               (SELECT COALESCE(SUM(e.amount), 0)
                FROM expenses e
                WHERE e.project_id = p.id) as general_expenses

        FROM projects p
        ORDER BY p.project_name";

        $projects = $this->db->query($sql)->fetchAll();

        foreach ($projects as &$project) {
            $project['material_cost'] = floatval($project['material_cost'] ?? 0);
            $project['labour_cost'] = floatval($project['labour_cost'] ?? 0);
            $project['vendor_payments'] = floatval($project['vendor_payments'] ?? 0);
            $project['contractor_payments'] = floatval($project['contractor_payments'] ?? 0);
            $project['contractor_payments'] = floatval($project['contractor_payments'] ?? 0);
            $project['other_expenses'] = floatval($project['other_expenses'] ?? 0);
            $project['general_expenses'] = floatval($project['general_expenses'] ?? 0);
            $project['total_refunds'] = floatval($project['total_refunds'] ?? 0);
            $project['cancellation_income'] = floatval($project['cancellation_income'] ?? 0);
            $project['total_received'] = floatval($project['total_received'] ?? 0);
            $project['total_sales'] = floatval($project['total_sales'] ?? 0);
            
            // STRICT DASHBOARD MATCHING LOGIC
            // Dashboard Net Profit = Total Received - Total Payments (Vendor + Labour + Contractor + Refunds)
            // It EXCLUDES Cancellation Income and Other Expenses (Financial Transactions)
            
            $project['total_income'] = $project['total_received']; // Exclude cancellation_income from "Operating Income"
            
            $project['total_expense'] = $project['vendor_payments'] + $project['contractor_payments'] + $project['total_refunds'] + $project['general_expenses'];
            
            // Gross Profit (Cash Basis)
            $project['gross_profit'] = $project['total_income'] - ($project['vendor_payments'] + $project['contractor_payments']);
            
            // Net Profit (Cash Basis)
            $project['net_profit'] = $project['total_income'] - $project['total_expense'];
            
            // Margin based on Total Sales (Project Value) to match the visible table columns
            $project['profit_margin'] = $project['total_sales'] > 0 ? ($project['net_profit'] / $project['total_sales']) * 100 : 0;
        }

        return $projects;
    }

    public function getProjectPLDetails($project_id) {
        // reuse getProjectPL logic but filter by ID
        // optimized: fetch just one
        $projects = $this->getProjectPL(); // For now, reuse and filter in PHP (or simple query) - easier to maintain consistent logic
        // But for efficiency let's copy the logic or filter array
        $project = null;
        foreach ($projects as $p) {
            if ($p['id'] == $project_id) {
                $project = $p;
                break;
            }
        }
        
        if (!$project) return null;

        // Fetch detailed expense breakdown
        $expense_breakdown = $this->db->query("SELECT ec.name as category_name, SUM(e.amount) as total_amount 
            FROM expenses e 
            JOIN expense_categories ec ON e.category_id = ec.id 
            WHERE e.project_id = ? 
            GROUP BY ec.id 
            ORDER BY total_amount DESC", [$project_id])->fetchAll();

        return [
            'summary' => $project,
            'expense_breakdown' => $expense_breakdown
        ];
    }

    public function getPaymentRegister($date_from, $date_to, $payment_type_filter = '') {
        $where = 'p.payment_date BETWEEN ? AND ?';
        $params = [$date_from, $date_to];

        if ($payment_type_filter) {
            $where .= ' AND p.payment_type = ?';
            $params[] = $payment_type_filter;
        }

        $sql = "SELECT p.*, 
                   pt.name as party_name,
                   u.full_name as created_by_name,
                   COALESCE(pr_booking.project_name, pr_challan.project_name, pr_bill.project_name, pr_cbill.project_name) as project_name,
                   COALESCE(pr_booking.id, pr_challan.id, pr_bill.id, pr_cbill.id) as project_id
            FROM payments p
            JOIN parties pt ON p.party_id = pt.id
            LEFT JOIN users u ON p.created_by = u.id
            
            -- Join for Bookings (Customer Receipts)
            LEFT JOIN bookings b ON p.payment_type = 'customer_receipt' AND p.reference_id = b.id
            LEFT JOIN projects pr_booking ON b.project_id = pr_booking.id
            
            -- Join for Vendor Payments (Direct Challan)
            LEFT JOIN challans c ON p.payment_type = 'vendor_payment' AND p.reference_type = 'challan' AND p.reference_id = c.id
            LEFT JOIN projects pr_challan ON c.project_id = pr_challan.id
            
            -- Join for Vendor Bill Payments
            LEFT JOIN bills vb ON p.payment_type = 'vendor_bill_payment' AND p.reference_id = vb.id
            LEFT JOIN (SELECT bill_id, MAX(project_id) as project_id FROM challans GROUP BY bill_id) ch_link ON vb.id = ch_link.bill_id
            LEFT JOIN projects pr_bill ON ch_link.project_id = pr_bill.id
            
            -- Join for Contractor Payments
            LEFT JOIN contractor_bills cb ON p.payment_type = 'contractor_payment' AND p.reference_id = cb.id
            LEFT JOIN projects pr_cbill ON cb.project_id = pr_cbill.id
            
            WHERE $where
            
            UNION ALL
            
            SELECT 
                e.id,
                'expense' COLLATE utf8mb4_unicode_ci as payment_type,
                NULL as reference_type,
                NULL as reference_id,
                NULL as party_id,
                e.bank_account_id as company_account_id,
                e.date as payment_date,
                e.amount,
                e.payment_method COLLATE utf8mb4_unicode_ci as payment_mode,
                e.reference_no COLLATE utf8mb4_unicode_ci,
                e.description COLLATE utf8mb4_unicode_ci as remarks,
                e.created_by,
                NULL as created_at,
                e.updated_at,
                NULL as demand_id,
                
                ec.name COLLATE utf8mb4_unicode_ci as party_name,
                u.full_name COLLATE utf8mb4_unicode_ci as created_by_name,
                p.project_name COLLATE utf8mb4_unicode_ci,
                e.project_id
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN projects p ON e.project_id = p.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.date BETWEEN ? AND ?
            
            ORDER BY payment_date DESC";

        // Duplicate params for the UNION part
        $params = array_merge($params, [$date_from, $date_to]);

        $payments = $this->db->query($sql, $params)->fetchAll();

        // Calculate totals
        $totals = [
            'receipts' => 0,
            'payments' => 0,
            'refunds' => 0,
            'counts' => [
                'receipts' => 0,
                'vendor' => 0,
                'labour' => 0,
                'contractor' => 0,
                'refunds' => 0,
                'total' => count($payments)
            ],
            'canc_income' => 0,
            'net_cashflow' => 0,
            'net_income' => 0
        ];

        foreach ($payments as $payment) {
            if ($payment['payment_type'] === 'customer_receipt') {
                $totals['receipts'] += $payment['amount'];
                $totals['counts']['receipts']++;
            } elseif ($payment['payment_type'] === 'customer_refund') {
                $totals['refunds'] += $payment['amount'];
                $totals['counts']['refunds']++;
            } elseif ($payment['payment_type'] === 'vendor_payment' || $payment['payment_type'] === 'vendor_bill_payment') {
                $totals['payments'] += $payment['amount'];
                $totals['counts']['vendor']++;
            } elseif ($payment['payment_type'] === 'contractor_payment') {
                $totals['payments'] += $payment['amount'];
                $totals['counts']['contractor']++;
            } elseif ($payment['payment_type'] === 'expense') {
                $totals['payments'] += $payment['amount'];
                // We'll count expenses in 'vendor' or strict 'other' for now, or just add to total count
            }
        }

        // Fetch cancellation income
        $sql = "SELECT COALESCE(SUM(amount), 0) as total_cancellation_income
                FROM financial_transactions
                WHERE transaction_type = 'income'
                AND category = 'cancellation_charges'
                AND transaction_date BETWEEN ? AND ?";
        $canc_income = $this->db->query($sql, [$date_from, $date_to])->fetch()['total_cancellation_income'] ?? 0;
        
        $totals['canc_income'] = $canc_income;
        $totals['net_cashflow'] = $totals['receipts'] - ($totals['payments'] + $totals['refunds']);
        $totals['net_income'] = $totals['receipts'] + $totals['canc_income'] - ($totals['payments'] + $totals['refunds']);

        return [
            'payments' => $payments,
            'totals' => $totals
        ];
    }


    public function getVendorOutstanding() {
        // Updated to use the 'bills' table instead of 'challans'
        $sql = "SELECT p.id as vendor_id, p.name as vendor_name, p.mobile, p.gst_number,
                   COUNT(b.id) as total_challans,
                   COALESCE(SUM(b.amount), 0) as total_amount,
                   COALESCE(SUM(b.paid_amount), 0) as paid_amount,
                   COALESCE(SUM(b.amount - b.paid_amount), 0) as pending_amount
            FROM parties p

            LEFT JOIN bills b ON p.id = b.party_id
            WHERE p.party_type = 'vendor'
            GROUP BY p.id
            ORDER BY p.name ASC";

        $vendors = $this->db->query($sql)->fetchAll();

        // Calculate grand totals here to keep view fetching simple
        $totals = [
            'total_amount' => 0,
            'paid_amount' => 0,
            'pending_amount' => 0,
            'count' => count($vendors),
            'outstanding_count' => 0
        ];

        foreach ($vendors as $vendor) {
            $totals['total_amount'] += $vendor['total_amount'];
            $totals['paid_amount'] += $vendor['paid_amount'];
            $totals['pending_amount'] += $vendor['pending_amount'];
            if ($vendor['pending_amount'] > 0) {
                $totals['outstanding_count']++;
            }
        }

        return [
            'vendors' => $vendors,
            'totals' => $totals
        ];
    }


    public function getLabourOutstanding() {
        $sql = "SELECT p.id as labour_id, p.name as labour_name, p.mobile,
                   COUNT(c.id) as total_challans,
                   SUM(c.total_amount) as total_amount,
                   SUM(c.paid_amount) as paid_amount,
                   SUM(c.total_amount - c.paid_amount) as pending_amount
            FROM parties p
            LEFT JOIN challans c ON p.id = c.party_id AND c.challan_type = 'labour'
            WHERE p.party_type = 'labour'
            GROUP BY p.id
            HAVING pending_amount > 0 OR total_challans > 0
            ORDER BY pending_amount DESC, p.name";

        $labours = $this->db->query($sql)->fetchAll();

        // Calculate grand totals to simplify the view
        $totals = [
            'total_amount' => 0,
            'paid_amount' => 0,
            'pending_amount' => 0,
            'count' => count($labours),
            'outstanding_count' => 0
        ];

        foreach ($labours as $labour) {
            $totals['total_amount'] += $labour['total_amount'];
            $totals['paid_amount'] += $labour['paid_amount'];
            $totals['pending_amount'] += $labour['pending_amount'];
            if ($labour['pending_amount'] > 0) {
                $totals['outstanding_count']++;
            }
        }

        return [
            'labours' => $labours,
            'totals' => $totals
        ];
    }


    public function getCustomerPending() {
        $sql = "SELECT b.id, b.booking_date, b.agreement_value, b.total_received, b.total_pending,
                   f.flat_no, f.floor,
                   p.id as customer_id, p.name as customer_name, p.mobile, p.email,
                   pr.project_name,
                   DATEDIFF(CURDATE(), b.booking_date) as days_since_booking
            FROM bookings b
            JOIN flats f ON b.flat_id = f.id
            JOIN parties p ON b.customer_id = p.id
            JOIN projects pr ON b.project_id = pr.id
            WHERE b.total_pending > 0 AND b.status = 'active'
            ORDER BY b.total_pending DESC, b.booking_date";

        $payments = $this->db->query($sql)->fetchAll();

        // Calculate totals
        $totals = [
            'agreement_value' => 0,
            'received' => 0,
            'pending' => 0,
            'count' => count($payments)
        ];

        foreach ($payments as $payment) {
            $totals['agreement_value'] += $payment['agreement_value'];
            $totals['received'] += $payment['total_received'];
            $totals['pending'] += $payment['total_pending'];
        }

        return [
            'payments' => $payments,
            'totals' => $totals
        ];
    }

    private function calculateTrend($current, $previous) {
        if ($previous == 0) {
            return 0; // Avoid fake 100%
        }
        return (($current - $previous) / $previous) * 100;
    }

    public function getDashboardMetrics() {
        // 1. Total Sales (Agreement Value of Active Bookings)
        $stmt = $this->db->query("SELECT COALESCE(SUM(agreement_value), 0) as total_sales FROM bookings WHERE status = 'active'");
        $total_sales = $stmt->fetch()['total_sales'];

        // 2. Total Received (Customer Receipts)
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_received FROM payments WHERE payment_type = 'customer_receipt'");
        $total_received = $stmt->fetch()['total_received'];

        // 3. Cancellation Income
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as canc_income FROM financial_transactions WHERE transaction_type = 'income' AND category = 'cancellation_charges'");
        $cancellation_income = $stmt->fetch()['canc_income'];

        // 4. Total Pending (Sum of pending from active bookings only)
        $stmt = $this->db->query("SELECT COALESCE(SUM(total_pending), 0) as total_pending FROM bookings WHERE status = 'active'");
        $total_pending = $stmt->fetch()['total_pending'];

        // 5. Total Expenses (Cash Basis: Vendor + Labour + Contractor + Refunds + General Expenses)
        // Matches Payment Register logic
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM payments WHERE payment_type IN ('vendor_payment', 'customer_refund', 'vendor_bill_payment', 'contractor_payment')");
        $payment_expenses = $stmt->fetch()['total_expenses'];
        
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_gen_expenses FROM expenses");
        $gen_expenses = $stmt->fetch()['total_gen_expenses'];
        
        $total_expenses = $payment_expenses + $gen_expenses;

        // 6. Net Profit
        $net_profit = $total_received - $total_expenses;

        // 7. Total Invested & Net Invested
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_invested FROM investments");
        $total_invested = $stmt->fetch()['total_invested'];

        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_returned FROM investment_returns");
        $total_returned = $stmt->fetch()['total_returned'];

        $net_invested = $total_invested - $total_returned;

        // 7. Monthly Stats for Chart (Current Year)
        $monthly_stats = $this->db->query("SELECT 
            m.month,
            COALESCE(i.inflow, 0) as income,
            COALESCE(e.expense, 0) as expense
        FROM (
            SELECT 1 as month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 
            UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
        ) m
        LEFT JOIN (
            SELECT MONTH(payment_date) as month, SUM(amount) as inflow 
            FROM payments WHERE payment_type = 'customer_receipt' AND YEAR(payment_date) = YEAR(CURDATE())
            GROUP BY MONTH(payment_date)
        ) i ON m.month = i.month
        LEFT JOIN (
            SELECT month, SUM(amount) as expense FROM (
                SELECT MONTH(payment_date) as month, amount 
                FROM payments 
                WHERE payment_type IN ('vendor_payment', 'customer_refund', 'vendor_bill_payment', 'contractor_payment') 
                AND YEAR(payment_date) = YEAR(CURDATE())
                
                UNION ALL
                
                SELECT MONTH(date) as month, amount 
                FROM expenses 
                WHERE YEAR(date) = YEAR(CURDATE())
            ) as combined_exp
            GROUP BY month
        ) e ON m.month = e.month
        ORDER BY m.month")->fetchAll();

        // 8. Project Stats
        // 8. Project Stats (Active bookings only)
        $project_stats = $this->db->query("SELECT p.id as project_id, p.project_name, COALESCE(SUM(b.agreement_value), 0) as total_sales
            FROM projects p
            LEFT JOIN bookings b ON p.id = b.project_id AND b.status = 'active'
            GROUP BY p.id")->fetchAll();

        // 9. Recent Bookings
        $recent_bookings = $this->db->query("SELECT 
            b.id, b.booking_date, b.agreement_value, pa.name as customer_name, b.customer_id, b.status, p.project_name, p.id as project_id, f.flat_no
            FROM bookings b
            LEFT JOIN projects p ON b.project_id = p.id
            LEFT JOIN flats f ON b.flat_id = f.id
            LEFT JOIN parties pa ON b.customer_id = pa.id
            ORDER BY b.booking_date DESC
            LIMIT 5")->fetchAll(); 

        // 10. Pending Approvals (Challans + Bills)
        $pending_approvals = $this->db->query("SELECT id, challan_no, total_amount, party_name, type FROM (
            SELECT c.id, CONVERT(c.challan_no USING utf8) as challan_no, c.total_amount, p.name as party_name, 'challan' as type
            FROM challans c
            LEFT JOIN parties p ON c.party_id = p.id
            WHERE c.status = 'pending'
            UNION ALL
            SELECT b.id, CONVERT(b.bill_no USING utf8) as challan_no, b.total_payable as total_amount, p.name as party_name, 'contractor_bill' as type
            FROM contractor_bills b
            LEFT JOIN parties p ON b.contractor_id = p.id
            WHERE b.status = 'pending'
        ) as combined_pending
        ORDER BY id DESC
        LIMIT 5")->fetchAll();
            
        // 11. Trends Calculations (MoM)
        $current_month = date('m');
        $current_year = date('Y');
        $last_month_time = strtotime('-1 month');
        $last_month = date('m', $last_month_time);
        $last_month_year = date('Y', $last_month_time);

        // Sales Trend (New Sales Booked)
        $stmt = $this->db->query("SELECT COALESCE(SUM(agreement_value), 0) as val FROM bookings WHERE status = 'active' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?", [$current_month, $current_year]);
        $sales_this_month = $stmt->fetch()['val'];
        $stmt = $this->db->query("SELECT COALESCE(SUM(agreement_value), 0) as val FROM bookings WHERE status = 'active' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?", [$last_month, $last_month_year]);
        $sales_last_month = $stmt->fetch()['val'];
        $sales_growth = $this->calculateTrend($sales_this_month, $sales_last_month);

        // Received Trend
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM payments WHERE payment_type = 'customer_receipt' AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?", [$current_month, $current_year]);
        $received_this_month = $stmt->fetch()['val'];
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM payments WHERE payment_type = 'customer_receipt' AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?", [$last_month, $last_month_year]);
        $received_last_month = $stmt->fetch()['val'];
        $received_growth = $this->calculateTrend($received_this_month, $received_last_month);

        // Expenses Trend (Using Payments table + Expenses table)
        $stmt = $this->db->query("
            SELECT (
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_type IN ('vendor_payment', 'customer_refund', 'vendor_bill_payment', 'contractor_payment') AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?) +
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(date) = ? AND YEAR(date) = ?)
            ) as val", [$current_month, $current_year, $current_month, $current_year]);
        $expense_this_month = $stmt->fetch()['val'];
        $stmt = $this->db->query("
            SELECT (
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_type IN ('vendor_payment', 'customer_refund', 'vendor_bill_payment', 'contractor_payment') AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?) +
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(date) = ? AND YEAR(date) = ?)
            ) as val", [$last_month, $last_month_year, $last_month, $last_month_year]);
        $expense_last_month = $stmt->fetch()['val'];
        $expense_growth = $this->calculateTrend($expense_this_month, $expense_last_month);

        // Net Profit Trend (MoM)
        // Profit This Month = (Received + Canc Income) - Expenses
        // Canc Income Trend
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM financial_transactions WHERE transaction_type = 'income' AND category = 'cancellation_charges' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?", [$current_month, $current_year]);
        $canc_this_month = $stmt->fetch()['val'];
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM financial_transactions WHERE transaction_type = 'income' AND category = 'cancellation_charges' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?", [$last_month, $last_month_year]);
        $canc_last_month = $stmt->fetch()['val'];       
        $profit_this_month = ($received_this_month + $canc_this_month) - $expense_this_month;
        $profit_last_month = ($received_last_month + $canc_last_month) - $expense_last_month;        
        $profit_growth = $this->calculateTrend($profit_this_month, $profit_last_month);

        // Pending Change (Net Additions)
        // New Pending = Sales This Month - Received This Month
        $pending_net_this = $sales_this_month - $received_this_month;
        $pending_net_last = $sales_last_month - $received_last_month;
        $pending_growth = $this->calculateTrend($pending_net_this, $pending_net_last);

        // 12. Approved Today Count (Challans + Bills)
        $stmt = $this->db->query("SELECT 
            (SELECT COUNT(*) FROM challans WHERE status = 'approved' AND DATE(updated_at) = CURDATE()) + 
            (SELECT COUNT(*) FROM contractor_bills WHERE status = 'approved' AND DATE(updated_at) = CURDATE()) as count");
        $approvals_today = $stmt->fetch()['count'] ?? 0;

        // 13. Total Cancelled Count
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled'");
        $total_cancelled = $stmt->fetch()['count'] ?? 0;

        return [
            'total_sales' => $total_sales,
            'total_received' => $total_received,
            'total_pending' => $total_pending,
            'total_expenses' => $total_expenses,
            'net_profit' => $net_profit,
            'total_invested' => $total_invested,
            'total_returned' => $total_returned,
            'net_invested'   => $net_invested,
            'monthly_stats' => $monthly_stats,
            'project_stats' => $project_stats,
            'recent_bookings' => $recent_bookings,
            'pending_approvals' => $pending_approvals,
            'sales_growth' => $sales_growth,
            'received_growth' => $received_growth,
            'expense_growth' => $expense_growth,
            'profit_growth' => $profit_growth,
            'pending_growth' => $pending_growth,
            'approvals_today' => $approvals_today,
            'total_cancelled' => $total_cancelled
        ];
    }

    public function getInvestmentROI($date_from = null, $date_to = null) {
        $date_from = $date_from ?? date('Y-01-01');
        $date_to = $date_to ?? date('Y-12-31');

        // 1. Project-wise Performance
        // Get total invested and returned per project
        $sql = "SELECT p.id as project_id, p.project_name,
                       SUM(CASE WHEN i.id IS NOT NULL THEN i.amount ELSE 0 END) as total_invested,
                       COALESCE(SUM(ir.amount), 0) as total_returned,
                       MAX(i.investment_date) as last_investment_date,
                       MAX(ir.return_date) as last_return_date
                FROM projects p
                LEFT JOIN investments i ON p.id = i.project_id
                LEFT JOIN investment_returns ir ON i.id = ir.investment_id
                GROUP BY p.id
                HAVING total_invested > 0
                ORDER BY total_invested DESC";
        $projects = $this->db->query($sql)->fetchAll();

        foreach ($projects as &$proj) {
            $proj['net_profit'] = $proj['total_returned'] - $proj['total_invested'];
            $proj['roi_percentage'] = $proj['total_invested'] > 0 ? ($proj['net_profit'] / $proj['total_invested']) * 100 : 0;
            
            // Annualized Return (Simple approximation)
            // If investment span > 1 year, divide ROI by years
            if ($proj['last_investment_date']) {
                $start = new DateTime($proj['last_investment_date']); // Using last investment as proxy for now, ideally strictly first
                $end = new DateTime($proj['last_return_date'] ?? 'now');
                $interval = $start->diff($end);
                $years = $interval->y + ($interval->m / 12) + ($interval->d / 365);
                
                if ($years > 1) {
                    $proj['annualized_roi'] = $proj['roi_percentage'] / $years;
                } else {
                    $proj['annualized_roi'] = $proj['roi_percentage'];
                }
            } else {
                $proj['annualized_roi'] = 0;
            }
        }

        // 2. Monthly Returns Trend (Cash Flow In)
        $monthly_returns = $this->db->query("SELECT 
            DATE_FORMAT(return_date, '%Y-%m') as month_key,
            SUM(amount) as total_amount
        FROM investment_returns
        WHERE return_date BETWEEN ? AND ?
        GROUP BY month_key
        ORDER BY month_key ASC", [$date_from, $date_to])->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fill gaps for chart
        $chart_labels = [];
        $chart_data = [];
        $start = new DateTime($date_from);
        $end = new DateTime($date_to);
        $end->modify('last day of this month');
        
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start, $interval, $end);

        foreach ($period as $dt) {
            $key = $dt->format("Y-m");
            $chart_labels[] = $dt->format("M Y");
            $chart_data[] = $monthly_returns[$key] ?? 0;
        }

        return [
            'projects' => $projects,
            'chart_labels' => $chart_labels,
            'chart_data' => $chart_data,
            'total_invested' => array_sum(array_column($projects, 'total_invested')),
            'total_returned' => array_sum(array_column($projects, 'total_returned'))
        ];
    }
}
