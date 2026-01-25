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
$page_title = 'Income & Expenditure Report';
$current_page = 'reports';

// Get filter values
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$project_filter = $_GET['project'] ?? '';

// Use ReportService
require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();
$data = $reportService->getFinancialOverview($date_from, $date_to, $project_filter);

$income_data = $data['income_data'];
$expenditure_data = $data['expenditure_data'];
$total_income = $data['total_income'];
$total_expenditure = $data['total_expenditure'];
$net_profit = $data['net_profit'];
$income_by_category = $data['income_by_category'];
$expenditure_by_category = $data['expenditure_by_category'];

// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/reports.css">

<div class="compact-report">
    <div class="card" style="border: none; margin-bottom: 1rem;">
        <div class="compact-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
            <h3 class="compact-title">
                <i class="fas fa-chart-pie"></i>
                Income & Expenditure Report
            </h3>
            <div class="compact-actions">
                <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=income_expenditure&format=excel&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-compact excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=income_expenditure&format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-compact csv">
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
                            <label>From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="col-md-3">
                            <label>To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Project</label>
                            <select name="project" class="form-control">
                                <option value="">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" style="padding-top: 22px;">
                            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards-fo" style="grid-template-columns: repeat(3, 1fr);">
                <div class="summary-card-fo income">
                    <div class="summary-card-fo-header">
                        <div class="summary-icon-fo">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div>
                            <div class="summary-card-title-fo">Total Income</div>
                            <div class="summary-card-details-fo"><?= count($income_data) ?> transactions</div>
                        </div>
                    </div>
                    <div class="summary-card-amount-fo"><?= formatCurrency($total_income) ?></div>
                </div>
                
                <div class="summary-card-fo expenditure">
                    <div class="summary-card-fo-header">
                        <div class="summary-icon-fo">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div>
                            <div class="summary-card-title-fo">Total Expenditure</div>
                            <div class="summary-card-details-fo"><?= count($expenditure_data) ?> transactions</div>
                        </div>
                    </div>
                    <div class="summary-card-amount-fo"><?= formatCurrency($total_expenditure) ?></div>
                </div>
                
                <div class="summary-card-fo profit">
                    <div class="summary-card-fo-header">
                        <div class="summary-icon-fo">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="summary-card-title-fo">Net Profit/Loss</div>
                            <div class="summary-card-details-fo">
                                <?= $total_income > 0 ? number_format(($net_profit / $total_income) * 100, 1) : 0 ?>% margin
                            </div>
                        </div>
                    </div>
                    <div class="summary-card-amount-fo" style="color: <?= $net_profit >= 0 ? '#38ef7d' : '#f5576c' ?>;">
                        <?= formatCurrency($net_profit) ?>
                    </div>
                </div>
            </div>
            
            <!-- Category Breakdown -->
            <div class="content-grid">
                <!-- Income Breakdown -->
                <div class="category-panel-fo">
                    <h5>
                        <i class="fas fa-coins" style="color: #38ef7d;"></i>
                        Income Breakdown
                    </h5>
                    <?php if (empty($income_by_category)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 1rem; font-size: 0.85rem;">No income recorded</p>
                    <?php else: ?>
                        <?php foreach ($income_by_category as $category => $data): ?>
                            <div class="category-item-fo">
                                <div>
                                    <span class="category-name-fo"><?= htmlspecialchars($category) ?></span>
                                    <span class="category-count-fo">(<?= $data['count'] ?>)</span>
                                </div>
                                <span class="category-amount-fo" style="color: #38ef7d;">
                                    <?= formatCurrency($data['amount']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="category-item-fo" style="background: #e8f5e9; font-weight: 700; margin-top: 0.35rem;">
                            <span class="category-name-fo">TOTAL</span>
                            <span class="category-amount-fo" style="color: #38ef7d;">
                                <?= formatCurrency($total_income) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Expenditure Breakdown -->
                <div class="category-panel-fo">
                    <h5>
                        <i class="fas fa-money-bill-wave" style="color: #f5576c;"></i>
                        Expenditure Breakdown
                    </h5>
                    <?php if (empty($expenditure_by_category)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 1rem; font-size: 0.85rem;">No expenditure recorded</p>
                    <?php else: ?>
                        <?php foreach ($expenditure_by_category as $category => $data): ?>
                            <div class="category-item-fo">
                                <div>
                                    <span class="category-name-fo"><?= htmlspecialchars($category) ?></span>
                                    <span class="category-count-fo">(<?= $data['count'] ?>)</span>
                                </div>
                                <span class="category-amount-fo" style="color: #f5576c;">
                                    <?= formatCurrency($data['amount']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="category-item-fo" style="background: #ffe7e7; font-weight: 700; margin-top: 0.35rem;">
                            <span class="category-name-fo">TOTAL</span>
                            <span class="category-amount-fo" style="color: #f5576c;">
                                <?= formatCurrency($total_expenditure) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Profit/Loss Summary -->
            <div class="profit-summary-fo">
                <h4>
                    <i class="fas fa-balance-scale"></i>
                    NET PROFIT/LOSS
                </h4>
                <div class="profit-amount-fo" style="color: <?= $net_profit >= 0 ? '#d4edda' : '#f8d7da' ?>;">
                    <?= $net_profit >= 0 ? '+' : '' ?><?= formatCurrency($net_profit) ?>
                </div>
                <div class="profit-percentage-fo">
                    <?= $net_profit >= 0 ? '✓' : '✗' ?> 
                    <?= $total_income > 0 ? number_format(($net_profit / $total_income) * 100, 2) : 0 ?>% 
                    <?= $net_profit >= 0 ? 'Profit Margin' : 'Loss Margin' ?>
                </div>
                <div class="profit-breakdown-fo">
                    <div>
                        <div style="opacity: 0.9;">Total Income</div>
                        <div style="font-weight: 700;"><?= formatCurrency($total_income) ?></div>
                    </div>
                    <div>
                        <div style="opacity: 0.9;">Total Expenditure</div>
                        <div style="font-weight: 700;"><?= formatCurrency($total_expenditure) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
