<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Monthly Cash Flow Report';
$current_page = 'reports';

// Get date range (default to current financial year)
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

// Generate month list for dropdown
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Calculate start and end dates
if ($month > 0) {
    // Specific month
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    $period_name = $months[$month] . ' ' . $year;
} else {
    // Entire year
    $start_date = $year . '-01-01';
    $end_date = $year . '-12-31';
    $period_name = 'Year ' . $year;
}

// Use ReportService
require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();

// Fetch data
$data = $reportService->getFinancialOverview($start_date, $end_date);
$cashflow_data = $data['daily_cashflow'];
$total_inflow = $data['total_income'];
$total_outflow = $data['total_expenditure'];
$net_cashflow = $data['net_profit'];

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/reports.css">

<div class="compact-report">
    <div class="card" style="border: none; margin-bottom: 1rem;">
        <div class="compact-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white;">
            <h3 class="compact-title">
                <i class="fas fa-chart-line"></i> Monthly Cash Flow Report
            </h3>
            <div class="compact-actions">
                <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=cash_flow&format=excel&year=<?= $year ?>&month=<?= $month ?>" class="btn-compact excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=cash_flow&format=csv&year=<?= $year ?>&month=<?= $month ?>" class="btn-compact csv">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <button class="btn-compact print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <div class="card-body" style="padding: 1rem;">
            <!-- Filters -->
            <div class="filter-compact">
                <form method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Year</label>
                            <select name="year" class="form-control">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Month</label>
                            <select name="month" class="form-control">
                                <option value="0">Entire Year</option>
                                <?php foreach ($months as $m => $name): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" style="padding-top: 22px;">
                            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                <i class="fas fa-filter"></i> View
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="stats-compact" style="grid-template-columns: repeat(4, 1fr);">
                <div class="stat-compact period">
                    <div class="stat-label-compact">Period</div>
                    <div class="stat-value-compact" style="font-size: 1rem;"><?= $period_name ?></div>
                </div>
                
                <div class="stat-compact receipts">
                    <div class="stat-label-compact">Total Inflow</div>
                    <div class="stat-value-compact"><?= formatCurrency($total_inflow) ?></div>
                </div>
                
                <div class="stat-compact payments">
                    <div class="stat-label-compact">Total Outflow</div>
                    <div class="stat-value-compact"><?= formatCurrency($total_outflow) ?></div>
                </div>
                
                <div class="stat-compact net">
                    <div class="stat-label-compact">Net Cash Flow</div>
                    <div class="stat-value-compact" style="color: <?= $net_cashflow >= 0 ? '#28a745' : '#dc3545' ?>;">
                        <?= formatCurrency($net_cashflow) ?>
                    </div>
                </div>
            </div>
            
            <!-- Cash Flow Table -->
            <div class="table-responsive">
                <table class="daily-flow-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Cash Inflow</th>
                            <th>Cash Outflow</th>
                            <th>Net Flow</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cashflow_data)): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 2rem; color: #6c757d;">No transactions found for this period</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cashflow_data as $row): ?>
                            <tr>
                                <td><?= formatDate($row['date']) ?></td>
                                <td><?= date('l', strtotime($row['date'])) ?></td>
                                <td>
                                    <?php if ($row['income'] > 0): ?>
                                        <span class="badge-compact receipt"><?= formatCurrency($row['income']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['expense'] > 0): ?>
                                        <span class="badge-compact payment"><?= formatCurrency($row['expense']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: <?= $row['net_flow'] >= 0 ? '#28a745' : '#dc3545' ?>;">
                                        <?= formatCurrency($row['net_flow']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: <?= $row['balance'] >= 0 ? '#0d6efd' : '#dc3545' ?>;">
                                        <?= formatCurrency($row['balance']) ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($cashflow_data)): ?>
                    <tfoot style="background: #e9ecef; font-weight: 700;">
                        <tr>
                            <td colspan="2" align="right" style="padding: 10px;">TOTAL:</td>
                            <td style="color: #28a745; padding: 10px;"><?= formatCurrency($total_inflow) ?></td>
                            <td style="color: #dc3545; padding: 10px;"><?= formatCurrency($total_outflow) ?></td>
                            <td style="color: <?= $net_cashflow >= 0 ? '#28a745' : '#dc3545' ?>; padding: 10px;">
                                <?= formatCurrency($net_cashflow) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            
            <?php if (!empty($cashflow_data)): ?>
            <div class="summary-compact" style="border-left-color: #0d6efd; background: #e7f3ff;">
                <h5 style="color: #0d6efd;"><i class="fas fa-info-circle"></i> Cash Flow Analysis</h5>
                <div class="summary-grid-compact">
                    <div class="summary-item-compact">
                        <span>Transaction Days:</span> <strong><?= count($cashflow_data) ?></strong>
                    </div>
                    <div class="summary-item-compact">
                        <span>Avg Daily Inflow:</span> <strong><?= formatCurrency($total_inflow / max(count($cashflow_data), 1)) ?></strong>
                    </div>
                    <div class="summary-item-compact">
                        <span>Cash Flow Trend:</span> 
                        <strong style="color: <?= $net_cashflow >= 0 ? '#28a745' : '#dc3545' ?>;">
                            <?= $net_cashflow >= 0 ? 'Positive' : 'Negative' ?>
                        </strong>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
