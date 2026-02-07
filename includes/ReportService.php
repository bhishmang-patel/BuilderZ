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
            pr.project_name
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
            pr.project_name
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
                WHEN p.payment_type = 'labour_payment' THEN 'Labour Payment'
                WHEN p.payment_type = 'customer_refund' THEN 'Customer Refund'
            END as category,
            p.payment_date as transaction_date,
            p.amount,
            pt.name as party_name,
            '-' as project_name
        FROM payments p
        JOIN parties pt ON p.party_id = pt.id
        WHERE p.payment_type IN ('vendor_payment','vendor_bill_payment','labour_payment','customer_refund')
        AND p.payment_date BETWEEN ? AND ?

        UNION ALL

        SELECT 
            ft.category,
            ft.transaction_date,
            ft.amount,
            '-' as party_name,
            pr.project_name
        FROM financial_transactions ft
        LEFT JOIN projects pr ON ft.project_id = pr.id
        WHERE ft.transaction_type = 'expenditure'
        AND ft.transaction_date BETWEEN ? AND ?
        " . ($project_filter ? " AND ft.project_id = ?" : "") . "

        ORDER BY transaction_date ASC";

        $exp_params = [$date_from, $date_to, $date_from, $date_to];
        if ($project_filter) $exp_params[] = $project_filter;

        $expenditure_data = $this->db->query($expenditure_sql, $exp_params)->fetchAll();


        /* =========================
              FETCH INVESTMENTS
        ========================== */
        $invest_sql = "SELECT 
            'Investment' as category,
            investment_date as transaction_date,
            amount,
            investor_name as party_name,
            p.project_name
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
                'flow' => 'inflow'
            ];
        }

        foreach ($investment_data as $row) {
            $transactions[] = [
                'date' => $row['transaction_date'],
                'amount' => $row['amount'],
                'flow' => 'inflow'
            ];
        }

        foreach ($expenditure_data as $row) {
            $transactions[] = [
                'date' => $row['transaction_date'],
                'amount' => $row['amount'],
                'flow' => 'outflow'
            ];
        }

        usort($transactions, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

        $daily = [];
        $running_balance = 0;

        foreach ($transactions as $txn) {
            $date = $txn['date'];

            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'inflow' => 0,
                    'outflow' => 0,
                    'net' => 0,
                    'balance' => 0
                ];
            }

            if ($txn['flow'] === 'inflow') {
                $daily[$date]['inflow'] += $txn['amount'];
                $running_balance += $txn['amount'];
            } else {
                $daily[$date]['outflow'] += $txn['amount'];
                $running_balance -= $txn['amount'];
            }

            $daily[$date]['net'] = $daily[$date]['inflow'] - $daily[$date]['outflow'];
            $daily[$date]['balance'] = $running_balance;
        }

        return $daily;
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
                
               -- Labour expenses
               (SELECT COALESCE(SUM(c3.total_amount), 0) 
                FROM challans c3 
                WHERE c3.project_id = p.id AND c3.challan_type = 'labour') as labour_cost,
                
                -- Vendor payments (Both direct old payments and new bill payments)
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                LEFT JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id
                LEFT JOIN bills b ON pay.reference_type = 'bill' AND pay.reference_id = b.id
                LEFT JOIN challans cb ON b.challan_id = cb.id
                WHERE (c.project_id = p.id OR cb.project_id = p.id)
                AND pay.payment_type IN ('vendor_payment', 'vendor_bill_payment')) as vendor_payments,


               -- Labour payments (Added for Cash Flow calculation)
               (SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id
                WHERE c.project_id = p.id
                AND pay.payment_type = 'labour_payment') as labour_payments,

               -- Other Expenses (Direct Financial Transactions)
               (SELECT COALESCE(SUM(ft.amount), 0)
                FROM financial_transactions ft
                WHERE ft.project_id = p.id
                AND ft.transaction_type = 'expenditure') as other_expenses

        FROM projects p
        ORDER BY p.project_name";

        $projects = $this->db->query($sql)->fetchAll();

        foreach ($projects as &$project) {
            $project['material_cost'] = floatval($project['material_cost'] ?? 0);
            $project['labour_cost'] = floatval($project['labour_cost'] ?? 0);
            $project['vendor_payments'] = floatval($project['vendor_payments'] ?? 0);
            $project['labour_payments'] = floatval($project['labour_payments'] ?? 0);
            $project['other_expenses'] = floatval($project['other_expenses'] ?? 0);
            $project['total_refunds'] = floatval($project['total_refunds'] ?? 0);
            $project['cancellation_income'] = floatval($project['cancellation_income'] ?? 0);
            $project['total_received'] = floatval($project['total_received'] ?? 0);
            $project['total_sales'] = floatval($project['total_sales'] ?? 0);
            
            // STRICT DASHBOARD MATCHING LOGIC
            // Dashboard Net Profit = Total Received - Total Payments (Vendor + Labour + Refunds)
            // It EXCLUDES Cancellation Income and Other Expenses (Financial Transactions)
            
            $project['total_income'] = $project['total_received']; // Exclude cancellation_income from "Operating Income"
            
            $project['total_expense'] = $project['vendor_payments'] + $project['labour_payments'] + $project['total_refunds'];
            
            // Gross Profit (Cash Basis)
            $project['gross_profit'] = $project['total_income'] - ($project['vendor_payments'] + $project['labour_payments']);
            
            // Net Profit (Cash Basis)
            $project['net_profit'] = $project['total_income'] - $project['total_expense'];
            
            // Margin based on Total Sales (Project Value) to match the visible table columns
            $project['profit_margin'] = $project['total_sales'] > 0 ? ($project['net_profit'] / $project['total_sales']) * 100 : 0;
        }

        return $projects;
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
                   u.full_name as created_by_name
            FROM payments p
            JOIN parties pt ON p.party_id = pt.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE $where
            ORDER BY p.payment_date DESC, p.created_at DESC";

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
            } elseif ($payment['payment_type'] === 'labour_payment') {
                $totals['payments'] += $payment['amount'];
                $totals['counts']['labour']++;
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

        // 5. Total Expenses (Cash Basis: Vendor + Labour + Refunds)
        // Matches Payment Register logic
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment')");
        $total_expenses = $stmt->fetch()['total_expenses'];

        // 6. Net Profit
        $net_profit = $total_received - $total_expenses;

        // 7. Total Invested
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as total_invested FROM investments");
        $total_invested = $stmt->fetch()['total_invested'];

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
            SELECT MONTH(payment_date) as month, SUM(amount) as expense 
            FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment') AND YEAR(payment_date) = YEAR(CURDATE())
            GROUP BY MONTH(payment_date)
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

        // 10. Pending Approvals (Challans)
        $pending_approvals = $this->db->query("SELECT 
            c.id, c.challan_no, c.total_amount, p.name as party_name
            FROM challans c
            LEFT JOIN parties p ON c.party_id = p.id
            WHERE c.status = 'pending'
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

        // Expenses Trend (Using Payments table for accurate cashflow timing)
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment') AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?", [$current_month, $current_year]);
        $expense_this_month = $stmt->fetch()['val'];
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) as val FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment') AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?", [$last_month, $last_month_year]);
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

        // 12. Approved Today Count
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM challans WHERE status = 'approved' AND DATE(updated_at) = CURDATE()");
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
}
