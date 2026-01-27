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
$page_title = 'Financial Overview';
$current_page = 'financial_overview';

// Get filter values
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$project_filter = $_GET['project'] ?? '';
$view_mode = $_GET['view'] ?? 'summary'; // summary, daily, category

require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();
$financialData = $reportService->getFinancialOverview($date_from, $date_to, $project_filter);

extract($financialData);

// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-modern {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    border-color: #cbd5e1;
}

.stat-card-modern.income { border-bottom: 4px solid #10b981; }
.stat-card-modern.expense { border-bottom: 4px solid #f5576c; }
.stat-card-modern.profit { border-bottom: 4px solid #3b82f6; }
.stat-card-modern.invested { border-bottom: 4px solid #f59e0b; }
.stat-card-modern.roi { border-bottom: 4px solid #8b5cf6; }
.stat-card-modern.balance { border-bottom: 4px solid #06b6d4; }
.stat-card-modern.transactions { border-bottom: 4px solid #64748b; }

.stat-label-modern {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-value-modern {
    font-size: 28px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
}

.stat-icon-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

/* Category Panels */
.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.category-panel {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 20px;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}
.category-item:last-child { border-bottom: none; }

.cat-name { font-weight: 600; color: #334155; font-size: 14px; }
.cat-count { font-size: 12px; color: #94a3b8; margin-left: 5px; }

/* View Tabs */
.view-tabs {
    display: flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: inline-flex;
}

.view-tab {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    color: #64748b;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-tab:hover { color: #1e293b; }

.view-tab.active {
    background: white;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-chart-pie"></i></div>
                        Financial Overview
                    </h3>
                    <div class="chart-subtitle">Analyze income, expenses, and cash flow performance</div>
                </div>
                <div class="chart-actions-group">
                    <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=financial_overview&format=excel&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569;">
                        <i class="fas fa-file-excel" style="color: #10b981;"></i> Excel
                    </a>
                    <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=financial_overview&format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569;">
                        <i class="fas fa-file-csv" style="color: #0ea5e9;"></i> CSV
                    </a>
                    <button class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569;" onclick="window.print()">
                        <i class="fas fa-print" style="color: #64748b;"></i> Print
                    </button>
                </div>
            </div>

            <div style="padding: 25px;">

                <!-- Filters -->
                <form method="GET" class="filter-card">
                    <div class="filter-row" style="display: flex; gap: 15px; align-items: flex-end;">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="input-label" style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; display: block;">Date Range</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="date" name="date_from" class="form-control-custom" value="<?= htmlspecialchars($date_from) ?>" style="height: 42px;">
                                <span style="color: #94a3b8;">to</span>
                                <input type="date" name="date_to" class="form-control-custom" value="<?= htmlspecialchars($date_to) ?>" style="height: 42px;">
                            </div>
                        </div>
                        
                        <div style="flex: 1; min-width: 200px;">
                            <label class="input-label" style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; display: block;">Project</label>
                            <select name="project" class="form-control-custom" style="height: 42px;">
                                <option value="">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="width: 140px;">
                            <button type="submit" class="modern-btn" style="width: 100%; justify-content: center; background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); color: white; margin-bottom: 5px;">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card-modern income">
                        <div class="stat-label-modern">
                            Total Income
                            <div class="stat-icon-circle" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-arrow-down"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #10b981;">
                            <span class="short-value"><?= formatCurrencyShort($total_income) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_income) ?></span>
                        </div>
                        <div class="stat-subtext"><?= count($income_data) ?> transactions recorded</div>
                    </div>
                    <div class="stat-card-modern expense">
                        <div class="stat-label-modern">
                            Total Expenditure
                            <div class="stat-icon-circle" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-arrow-up"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #ef4444;">
                            <span class="short-value"><?= formatCurrencyShort($total_expenditure) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_expenditure) ?></span>
                        </div>
                        <div class="stat-subtext"><?= count($expenditure_data) ?> transactions recorded</div>
                    </div>
                    <div class="stat-card-modern profit">
                        <div class="stat-label-modern">
                            Net Profit
                            <div class="stat-icon-circle" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-wallet"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: <?= $net_profit >= 0 ? '#3b82f6' : '#ef4444' ?>;">
                            <span class="short-value"><?= formatCurrencyShort($net_profit) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($net_profit) ?></span>
                        </div>
                        <div class="stat-subtext">
                            <?= $total_income > 0 ? number_format(($net_profit / $total_income) * 100, 1) : 0 ?>% Net Margin
                        </div>
                    </div>

                    <div class="stat-card-modern invested">
                        <div class="stat-label-modern">
                            Total Invested
                            <div class="stat-icon-circle" style="background: #fffbeb; color: #f59e0b;"><i class="fas fa-hand-holding-usd"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #f59e0b;">
                            <span class="short-value"><?= formatCurrencyShort($total_invested) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_invested) ?></span>
                        </div>
                        <div class="stat-subtext">Capital employed</div>
                    </div>

                    <?php if ($total_invested > 0): ?>
                    <div class="stat-card-modern roi">
                        <div class="stat-label-modern">
                            Return on Investment
                            <div class="stat-icon-circle" style="background: #f5f3ff; color: #8b5cf6;" title="ROI = (Net Profit / Total Invested) * 100"><i class="fas fa-percentage"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #8b5cf6;">
                            <?= number_format($roi, 1) ?>%
                        </div>
                        <div class="stat-subtext">Returns on capital</div>
                    </div>
                    <?php endif; ?>

                    <div class="stat-card-modern balance">
                        <div class="stat-label-modern">
                            Cash Balance
                            <div class="stat-icon-circle" style="background: #e0f2fe; color: #0ea5e9;"><i class="fas fa-coins"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #0ea5e9;">
                            <span class="short-value"><?= formatCurrencyShort($cash_balance) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($cash_balance) ?></span>
                        </div>
                        <div class="stat-subtext">Invested + Income - Expenses</div>
                    </div>

                    <div class="stat-card-modern transactions">
                        <div class="stat-label-modern">
                            Total Volume
                            <div class="stat-icon-circle" style="background: #f1f5f9; color: #64748b;"><i class="fas fa-exchange-alt"></i></div>
                        </div>
                        <div class="stat-value-modern" style="color: #475569;"><?= count($income_data) + count($expenditure_data) ?></div>
                        <div class="stat-subtext">Combined transaction count</div>
                    </div>
                </div>
                
                <!-- View Tabs -->
                <div class="view-tabs">
                    <button class="view-tab <?= $view_mode === 'summary' ? 'active' : '' ?>" onclick="location.href='?view=summary&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&project=<?= $project_filter ?>'">
                        <i class="fas fa-chart-pie"></i> Category Summary
                    </button>
                    <button class="view-tab <?= $view_mode === 'daily' ? 'active' : '' ?>" onclick="location.href='?view=daily&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&project=<?= $project_filter ?>'">
                        <i class="fas fa-calendar-alt"></i> Daily Cash Flow
                    </button>
                    <!-- <button class="view-tab <?= $view_mode === 'category' ? 'active' : '' ?>" onclick="location.href='?view=category&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&project=<?= $project_filter ?>'">
                        <i class="fas fa-list"></i> Detailed List
                    </button> -->
                </div>

                <!-- Content Area -->
                <?php if ($view_mode === 'summary' || $view_mode === 'category'): ?>
                    <div class="breakdown-grid">
                        <!-- Income Breakdown -->
                        <div class="category-panel">
                            <h5 style="margin-bottom: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 4px; height: 20px; background: #10b981; border-radius: 2px;"></span>
                                Income Breakdown
                            </h5>
                            <?php if (empty($income_by_category)): ?>
                                <div style="text-align: center; color: #94a3b8; padding: 40px 0;">
                                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No income data
                                </div>
                            <?php else: ?>
                                <?php foreach ($income_by_category as $category => $data): ?>
                                    <div class="category-item">
                                        <div style="display: flex; align-items: center;">
                                            <span class="cat-name"><?= htmlspecialchars($category) ?></span>
                                            <span class="cat-count">(<?= $data['count'] ?>)</span>
                                        </div>
                                        <span style="font-weight: 700; color: #10b981;" title="<?= formatCurrencyIndian($data['amount']) ?>"><?= formatCurrencyShort($data['amount']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="category-item" style="background: #ecfdf5; padding: 15px 10px; margin: 10px -10px -10px; border-radius: 8px; border: none;">
                                    <span class="cat-name">TOTAL INCOME</span>
                                    <span style="font-size: 16px; font-weight: 800; color: #10b981;" title="<?= formatCurrencyIndian($total_income) ?>"><?= formatCurrencyShort($total_income) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Expenditure Breakdown -->
                        <div class="category-panel">
                            <h5 style="margin-bottom: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 4px; height: 20px; background: #ef4444; border-radius: 2px;"></span>
                                Expenditure Breakdown
                            </h5>
                            <?php if (empty($expenditure_by_category)): ?>
                                <div style="text-align: center; color: #94a3b8; padding: 40px 0;">
                                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No expenditure data
                                </div>
                            <?php else: ?>
                                <?php foreach ($expenditure_by_category as $category => $data): ?>
                                    <div class="category-item">
                                        <div style="display: flex; align-items: center;">
                                            <span class="cat-name"><?= htmlspecialchars($category) ?></span>
                                            <span class="cat-count">(<?= $data['count'] ?>)</span>
                                        </div>
                                        <span style="font-weight: 700; color: #ef4444;" title="<?= formatCurrencyIndian($data['amount']) ?>"><?= formatCurrencyShort($data['amount']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="category-item" style="background: #fef2f2; padding: 15px 10px; margin: 10px -10px -10px; border-radius: 8px; border: none;">
                                    <span class="cat-name">TOTAL EXPENDITURE</span>
                                    <span style="font-size: 16px; font-weight: 800; color: #ef4444;" title="<?= formatCurrencyIndian($total_expenditure) ?>"><?= formatCurrencyShort($total_expenditure) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($view_mode === 'daily'): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>DAY</th>
                                    <th>INFLOW</th>
                                    <th>OUTFLOW</th>
                                    <th>NET FLOW</th>
                                    <th>CLOSING BALANCE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daily_cashflow)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center" style="padding: 40px; color: #94a3b8;">
                                            No transactions found for this period.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daily_cashflow as $date => $flow): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #1e293b;"><?= formatDate($date) ?></td>
                                        <td style="color: #64748b;"><?= date('l', strtotime($date)) ?></td>
                                        <td>
                                            <?php if ($flow['inflow'] > 0): ?>
                                                <span style="color: #10b981; font-weight: 600;">+ <?= formatCurrency($flow['inflow']) ?></span>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($flow['outflow'] > 0): ?>
                                                <span style="color: #ef4444; font-weight: 600;">- <?= formatCurrency($flow['outflow']) ?></span>
                                            <?php else: ?>
                                                <span style="color: #cbd5e1;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: <?= $flow['net'] >= 0 ? '#10b981' : '#ef4444' ?>;">
                                                <?= formatCurrency($flow['net']) ?>
                                            </span>
                                        </td>
                                        <td style="font-family: monospace; font-size: 14px; font-weight: 700; color: #1e293b;">
                                            <?= formatCurrency($flow['balance']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
