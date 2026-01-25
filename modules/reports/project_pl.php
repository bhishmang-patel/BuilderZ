<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();
$page_title = 'Project-wise P&L';
$current_page = 'project_pl';

require_once __DIR__ . '/../../includes/ReportService.php';

// Fetch project-wise profit & loss
$reportService = new ReportService();
$projects = $reportService->getProjectPL();

// Calculate Totals for Stats Cards
$totals = [
    'bookings' => 0,
    'sales' => 0,       // Keep for informational purposes
    'turnover' => 0,    // Cash Inflow
    'expense' => 0,     // Cash Outflow
    'net' => 0
];

if (!empty($projects)) {
    foreach ($projects as $key => $project) {
        // 1. TURNOVER (CASH INFLOW) = Total Received (Gross)
        $projects[$key]['calc_turnover'] =
            (float) $project['total_received'];

        // 2. EXPENSES (CASH OUTFLOW) = Vendor + Labour + Other + Refunds
        $projects[$key]['calc_expense'] =
            (float) $project['vendor_payments']
            + (float) $project['labour_payments']
            + (float) $project['other_expenses']
            + (float) $project['total_refunds'];

        // 3. PROFIT = SALES − EXPENSES
        $projects[$key]['calc_profit'] =
            $projects[$key]['calc_turnover'] - $projects[$key]['calc_expense'];

        // 4. MARGIN = PROFIT ÷ SALES
        $projects[$key]['calc_margin'] =
            $projects[$key]['calc_turnover'] > 0
                ? ($projects[$key]['calc_profit'] / $projects[$key]['calc_turnover']) * 100
                : 0;

        $totals['bookings'] += $project['total_bookings'];
        $totals['sales'] += $project['total_sales'];
        $totals['turnover'] += $projects[$key]['calc_turnover'];
        $totals['expense'] += $projects[$key]['calc_expense'];
        $totals['net'] += $projects[$key]['calc_profit'];
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card-modern {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}
.stat-card-modern::after {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    width: 6px;
    height: 100%;
    /* Accent color bar on right */
}
.stat-card-modern.blue::after { background: #3b82f6; }
.stat-card-modern.red::after { background: #ef4444; }
.stat-card-modern.green::after { background: #10b981; }

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}
.stat-icon.blue { background: #eff6ff; color: #3b82f6; }
.stat-icon.red { background: #fef2f2; color: #ef4444; }
.stat-icon.green { background: #ecfdf5; color: #10b981; }

.stat-info h4 { margin: 0; font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-info .value { font-size: 28px; font-weight: 800; color: #1e293b; margin-top: 4px; letter-spacing: -0.5px; }

/* Main Table Container */
.pl-container {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 30px;
}
.pl-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}
.pl-title h3 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 10px; }
.pl-subtitle { color: #64748b; font-size: 13px; margin-top: 4px; }

/* Custom Action Buttons */
.action-btn {
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}
.action-btn.excel { background: #fdf4ff; color: #9333ea; border: 1px solid #f0abfc; }
.action-btn.excel:hover { background: #fae8ff; color: #7e22ce; }
.action-btn.csv { background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8; }
.action-btn.csv:hover { background: #fce7f3; color: #be185d; }
.action-btn.print { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.action-btn.print:hover { background: #e2e8f0; color: #1e293b; }

/* Table Styling */
.pl-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.pl-table th {
    text-align: left;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
}
.pl-table td {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    transition: background 0.2s;
}
.pl-row:hover td { background: #f8fafc; }
.pl-row.expanded td { background: #eff6ff; border-bottom: none; }

/* Toggle Icon */
.toggle-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.2s;
    cursor: pointer;
}
.pl-row.expanded .toggle-icon { transform: rotate(180deg); background: #3b82f6; color: #fff; }

/* Detail Expansion */
.details-row { display: none; }
.details-row.active { display: table-row; animation: slideDown 0.3s ease-out; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.details-wrapper {
    padding: 0 20px 24px 70px; /* Indent to align with project name */
    background: #eff6ff; /* Match active row bg */
    border-bottom: 1px solid #e2e8f0;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    border: 1px solid #dbeafe;
}

.detail-section h5 {
    margin: 0 0 16px 0;
    font-size: 13px;
    font-weight: 700;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 10px;
}
.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 13px;
}
.detail-item span.label { color: #64748b; }
.detail-item span.amount { font-family: monospace; font-weight: 600; color: #333; font-size: 14px; }

/* Typography Helpers */
.font-mono { font-family: 'Consolas', monospace; letter-spacing: -0.3px; }
.text-success { color: #10b981; }
.text-danger { color: #ef4444; }
.text-primary { color: #3b82f6; }
.fw-bold { font-weight: 700; }
.fw-heavy { font-weight: 800; }

/* Stats Card Hover Effect */
.stat-info .full-value { display: none; font-size: 20px; } /* Slightly smaller for full value */
.stat-card-modern:hover .stat-info .short-value { display: none; }
.stat-card-modern:hover .stat-info .full-value { display: block; animation: fadeIn 0.2s ease-in; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(2px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Top Stats -->
<div class="stats-container">
    <div class="stat-card-modern blue">
        <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
        <div class="stat-info">
            <h4>Total Collected</h4>
            <div class="value">
                <span class="short-value"><?= formatCurrencyShort($totals['turnover']) ?></span>
                <span class="full-value"><?= formatCurrency($totals['turnover']) ?></span>
            </div>
        </div>
    </div>
    <div class="stat-card-modern red">
        <div class="stat-icon red"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h4>Total Payout</h4>
            <div class="value">
                <span class="short-value"><?= formatCurrencyShort($totals['expense']) ?></span>
                <span class="full-value"><?= formatCurrency($totals['expense']) ?></span>
            </div>
        </div>
    </div>
    <div class="stat-card-modern green">
        <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h4>Net Cash Flow</h4>
            <div class="value" style="color: <?= $totals['net'] >= 0 ? '#10b981' : '#ef4444' ?>;">
                <span class="short-value"><?= formatCurrencyShort($totals['net']) ?></span>
                <span class="full-value"><?= formatCurrency($totals['net']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Main Accordion Table -->
<div class="pl-container">
    <div class="pl-header">
        <div class="pl-title">
            <h3><div class="chart-icon-box blue" style="width:32px; height:32px; font-size:14px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:#eff6ff; color:#3b82f6;"><i class="fas fa-balance-scale"></i></div> Project Financials</h3>
            <div class="pl-subtitle">Click row to view detailed breakdown</div>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=project_pl&format=excel" class="action-btn excel"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=project_pl&format=csv" class="action-btn csv"><i class="fas fa-file-code"></i> CSV</a>
            <button class="action-btn print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <table class="pl-table">
        <thead>
            <tr>
                <th style="width: 50px;"></th> <!-- Toggle -->
                <th>Project Details</th>
                <th>Bookings</th>
                <th>Total Received</th>
                <th>Total Payout</th>
                <th>Net Cash</th>
                <th>Margin</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
                <tr><td colspan="7" style="text-align:center; padding:50px; color:#64748b;">No data found</td></tr>
            <?php else: ?>
                <?php foreach ($projects as $idx => $row): ?>
                    <!-- Main Row -->
                    <tr class="pl-row" onclick="toggleDetails(this)">
                        <td style="text-align: center;">
                            <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#0f172a; font-size:15px;"><?= htmlspecialchars($row['project_name']) ?></div>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;"><i class="fas fa-map-marker-alt" style="font-size:11px; margin-right:4px;"></i> <?= htmlspecialchars($row['location']) ?></div>
                        </td>
                        <td style="text-align:center; font-weight:600;"><?= $row['total_bookings'] ?></td>
                        <td class="font-mono fw-bold"><?= formatCurrency($row['calc_turnover']) ?></td>
                        <td class="font-mono fw-bold text-danger"><?= formatCurrency($row['calc_expense']) ?></td>
                        <td class="font-mono fw-heavy" style="font-size:16px; color:<?= $row['calc_profit'] >= 0 ? '#10b981' : '#ef4444' ?>;">
                            <?= formatCurrency($row['calc_profit']) ?>
                        </td>
                        <td>
                            <?php $m = $row['calc_margin']; $c = $m>=20?'green':($m>=10?'orange':'red'); ?>
                            <span class="badge-soft <?= $c ?>"><?= number_format($m, 1) ?>%</span>
                        </td>
                    </tr>
                    
                    <!-- Details Row -->
                    <tr class="details-row">
                        <td colspan="7" style="padding:0;">
                            <div class="details-wrapper">
                                <div class="details-grid">
                                    <!-- Income Section -->
                                    <div class="detail-section">
                                        <h5 style="color:#059669;"><i class="fas fa-arrow-down"></i> Income Breakdown</h5>
                                        <div class="detail-item">
                                            <span class="label">Total Sales Booked</span>
                                            <span class="amount" style="color: #64748b;"><?= formatCurrency($row['total_sales']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="label">Customer Receipts</span>
                                            <span class="amount text-success"><?= formatCurrency($row['total_received']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="label">Cancellation Income</span>
                                            <span class="amount text-success"><?= formatCurrency($row['cancellation_income']) ?></span>
                                        </div>
                                        <div class="detail-item" style="margin-top:15px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                                            <span class="label" style="font-weight:600;">Pending Collection</span>
                                            <span class="amount" style="color:#f59e0b;"><?= formatCurrency($row['customer_pending']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Expense Section -->
                                    <div class="detail-section">
                                        <h5 style="color:#dc2626;"><i class="fas fa-arrow-up"></i> Expense Breakdown (Cash)</h5>
                                        <div class="detail-item">
                                            <span class="label">Vendor Payments</span>
                                            <span class="amount"><?= formatCurrency($row['vendor_payments']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="label">Labour Payments</span>
                                            <span class="amount"><?= formatCurrency($row['labour_payments']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="label">Other Expenses</span>
                                            <span class="amount"><?= formatCurrency($row['other_expenses']) ?></span>
                                        </div>
                                         <div class="detail-item">
                                            <span class="label">Refunds Issued</span>
                                            <span class="amount text-danger"><?= formatCurrency($row['total_refunds']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <!-- Total Footer -->
        <tfoot style="background:#f8fafc;">
            <tr>
                <td></td>
                <td style="text-align:right; font-weight:700; padding:20px;">TOTALS:</td>
                <td style="text-align:center; font-weight:700;"><?= $totals['bookings'] ?></td>
                <td class="font-mono fw-bold"><?= formatCurrency($totals['turnover']) ?></td>
                <td class="font-mono fw-bold text-danger"><?= formatCurrency($totals['expense']) ?></td>
                <td class="font-mono fw-heavy" style="font-size:16px; color:<?= $totals['net']>=0 ? '#10b981':'#ef4444' ?>;">
                    <?= formatCurrency($totals['net']) ?>
                </td>
                <td>
                    <span class="badge-soft blue"><?= $totals['turnover']>0 ? number_format(($totals['net']/$totals['turnover'])*100, 1) : 0 ?>%</span>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
function toggleDetails(row) {
    row.classList.toggle('expanded');
    const nextRow = row.nextElementSibling;
    if (nextRow && nextRow.classList.contains('details-row')) {
        nextRow.classList.toggle('active');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
