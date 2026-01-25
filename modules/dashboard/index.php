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
$page_title = 'Dashboard';
$current_page = 'dashboard';

// Use ReportService
require_once __DIR__ . '/../../includes/ReportService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';
$reportService = new ReportService();

// Fetch Dashboard Metrics
$data = $reportService->getDashboardMetrics();

$total_sales = $data['total_sales'];
$total_received = $data['total_received'];
$total_pending = $data['total_pending'];
$total_cancelled = $data['total_cancelled'];
$total_expenses = $data['total_expenses'];
$net_profit = $data['net_profit'];
$monthly_stats = $data['monthly_stats'];
$project_stats = $data['project_stats'];
$recent_bookings = $data['recent_bookings'];
$pending_approvals = $data['pending_approvals'];

// Trends
$sales_growth = $data['sales_growth'] ?? 0;
$received_growth = $data['received_growth'] ?? 0;
$expense_growth = $data['expense_growth'] ?? 0;
$profit_growth = $data['profit_growth'] ?? 0;
$pending_growth = $data['pending_growth'] ?? 0;

// New Metrics
$approvals_today = $data['approvals_today'] ?? 0;

// Helper to format trend
function getTrendClass($val) {
    return $val >= 0 ? 'positive' : 'negative';
}
function formatTrend($val) {
    return ($val > 0 ? '+' : '') . number_format($val, 1) . '%';
}

// --- New Calculations for UI ---
$total_income_yr = array_sum(array_column($monthly_stats, 'income'));
$total_expense_yr = array_sum(array_column($monthly_stats, 'expense'));
$net_income_yr = $total_income_yr - $total_expense_yr;
$profit_margin = $total_income_yr > 0 ? ($net_income_yr / $total_income_yr) * 100 : 0;
// Avoid division by zero if count is 0
$months = count($monthly_stats);
$avg_monthly = $months > 0 ? $total_income_yr / $months : 0;

$total_project_sales_calc = array_sum(array_column($project_stats, 'total_sales')); 

include __DIR__ . '/../../includes/header.php';
?>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Custom Dashboard Styles for New Charts ONLY (reverted global stats styles) */
/* Shared Custom Card Style */
.chart-card-custom {
    background: #fff;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 100%;
    border: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
}
.chart-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
}
.chart-title-group h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.chart-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.chart-icon-box.blue { background: #3b82f6; color: white; }
.chart-icon-box.purple { background: #a855f7; color: white; }
.chart-icon-box.orange { background: #f59e0b; color: white; } /* Added for bottom section */

.chart-subtitle {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 4px;
    font-weight: 500;
    padding-left: 42px; 
}

/* Legend & Footer Styles from previous step */
.chart-legend-custom {
    display: flex;
    gap: 20px;
    align-items: center;
}
.legend-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.legend-val {
    font-weight: 800;
    margin-left: 4px;
}
.legend-val.inc { color: #10b981; }
.legend-val.exp { color: #ef4444; }

.chart-area-wrapper {
    flex: 1;
    position: relative;
    min-height: 250px;
}

.stats-footer-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 30px;
}
.stat-box-modern {
    padding: 20px;
    border-radius: 16px;
}
.stat-box-modern.green { background: #ecfdf5; }
.stat-box-modern.blue { background: #eff6ff; }
.stat-box-modern.purple { background: #f3e8ff; }

.stat-box-modern label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-box-modern.green label { color: #059669; }
.stat-box-modern.blue label { color: #2563eb; }
.stat-box-modern.purple label { color: #9333ea; }

.stat-box-modern .value {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
    display: block;
    margin-bottom: 4px;
}
.stat-box-modern .sub-text {
    font-size: 11px;
    display: block;
    font-weight: 500;
}
.stat-box-modern.green .sub-text { color: #059669; }
.stat-box-modern.blue .sub-text { color: #3b82f6; }
.stat-box-modern.purple .sub-text { color: #9333ea; }

/* Doughnut & Project List (retained) */
.doughnut-container {
    position: relative;
    height: 220px;
    margin: 20px 0;
}
.doughnut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.doughnut-center h4 { margin: 0; font-size: 24px; font-weight: 800; color: #1e293b; }
.doughnut-center span { font-size: 12px; color: #64748b; font-weight: 500; }

.project-list { margin-top: auto; }
.project-list-item {
    display: flex; align-items: center; padding: 15px; border-radius: 12px; margin-bottom: 12px;
}
.project-list-item.primary { background: #ecfdf5; }
.project-list-item.secondary { background: #eff6ff; }
.project-list-item:last-child { margin-bottom: 0; }

.p-dot { width: 12px; height: 12px; border-radius: 4px; margin-right: 12px; }
.p-dot.green { background: #10b981; }
.p-dot.blue { background: #6366f1; }
.p-info h5 { margin: 0; font-size: 14px; font-weight: 700; color: #1e293b; }
.p-info span { font-size: 11px; color: #64748b; }
.p-stats { margin-left: auto; text-align: right; }
.p-stats .amount { display: block; font-weight: 700; font-size: 14px; color: #1e293b; }
.p-stats .perc { font-size: 11px; color: #64748b; }

.mini-stats-row {
    display: flex; justify-content: space-around; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9;
}
.mini-stat { text-align: center; }
.mini-stat .val { display: block; font-size: 18px; font-weight: 700; color: #1e293b; }
.mini-stat .lbl { font-size: 11px; color: #64748b; }

/* --- NEW: Bottom Section Styles (Table & List) --- */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px; /* Row spacing */
    margin-top: -10px;
}
.modern-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    padding: 12px 15px;
    letter-spacing: 0.5px;
    border: none;
    text-align: center;
    vertical-align: middle !important;
}
.modern-table tbody tr {
    background: #fff; /* or transparent if card is white */
    /* If card has padding, maybe no background needed on tr unless hovered? 
       Actually user image shows clean rows. Let's keep simple. */
}
.modern-table td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    text-align: center;
    vertical-align: middle !important;
}
.modern-table tr:last-child td { border-bottom: none; }

/* Avatars */
.avatar-square {
    width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #fff; margin-right: 12px; flex-shrink: 0;
}
.avatar-circle {
    width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #fff; margin-right: 12px; flex-shrink: 0; font-size: 12px;
}
.av-green { background: #10b981; }
.av-blue { background: #3b82f6; }
.av-purple { background: #a855f7; }
.av-orange { background: #f59e0b; }

/* Badges */
.badge-pill {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-pill.blue { background: #eff6ff; color: #3b82f6; } /* Flat No */
.badge-pill.green { background: #ecfdf5; color: #10b981; } /* Amount */

/* Actions */
.action-btn { color: #94a3b8; cursor: pointer; transition: 0.2s; }
.action-btn:hover { color: #64748b; }

/* Empty State */
.empty-state-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
    height: 100%;
}
.check-circle-lg {
    width: 80px; height: 80px; border-radius: 50%; background: #f1f5f9; color: #94a3b8;
    display: flex; align-items: center; justify-content: center; font-size: 32px;
    margin-bottom: 20px;
}
.empty-title { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
.empty-desc { font-size: 13px; color: #64748b; max-width: 250px; line-height: 1.5; margin-bottom: 25px; }
.btn-amber {
    background: #d97706; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 14px;
}
.btn-amber:hover { background: #b45309; color: #fff; }

.bottom-stats-row {
     display: flex; gap: 15px; width: 100%; margin-top: auto;
}
.b-stat-box {
    flex: 1; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px;
}
.b-stat-label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; }
.b-stat-val { font-size: 20px; fontWeight: 800; color: #10b981; }
.b-stat-val.blue { color: #3b82f6; }

/* Footer summary on left card */
.table-footer {
    display: flex;
    align-items: center;
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}
.tf-item { margin-right: 40px; }
.tf-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 4px; }
.tf-val { font-size: 18px; font-weight: 800; color: #1e293b; }
.tf-val.green { color: #10b981; }
.export-link { margin-left: auto; color: #3b82f6; font-weight: 600; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 6px;}

</style>

<div class="stats-grid">
    <!-- Preserved Top Stats (Unchanged) -->
    <div class="stat-card">
        <div class="stat-header">
            <div class="icon-box blue"><i class="fas fa-indian-rupee-sign"></i></div>
            <div class="trend-pill <?= getTrendClass($sales_growth) ?>"><?= formatTrend($sales_growth) ?></div>
        </div>
        <div class="stat-label">Total Sales</div>
        <div class="stat-value">
            <span class="short-value"><?= formatCurrencyShort($total_sales) ?></span>
            <span class="full-value"><?= formatCurrency($total_sales) ?></span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="icon-box green"><i class="fas fa-chart-line"></i></div>
            <div class="trend-pill <?= getTrendClass($received_growth) ?>"><?= formatTrend($received_growth) ?></div>
        </div>
        <div class="stat-label">Total Received</div>
        <div class="stat-value">
            <span class="short-value"><?= formatCurrencyShort($total_received) ?></span>
            <span class="full-value"><?= formatCurrency($total_received) ?></span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="icon-box orange"><i class="fas fa-clock"></i></div>
            <div class="trend-pill <?= getTrendClass($pending_growth) ?>"><?= formatTrend($pending_growth) ?></div>
        </div>
        <div class="stat-label">Total Pending</div>
        <div class="stat-value">
            <span class="short-value"><?= formatCurrencyShort($total_pending) ?></span>
            <span class="full-value"><?= formatCurrency($total_pending) ?></span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="icon-box purple"><i class="fas fa-file-invoice"></i></div>
            <div class="trend-pill <?= getTrendClass($expense_growth) ?>"><?= formatTrend($expense_growth) ?></div>
        </div>
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value">
            <span class="short-value"><?= formatCurrencyShort($total_expenses) ?></span>
            <span class="full-value"><?= formatCurrency($total_expenses) ?></span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="icon-box teal"><i class="fas fa-chart-bar"></i></div>
            <div class="trend-pill <?= getTrendClass($profit_growth) ?>"><?= formatTrend($profit_growth) ?></div>
        </div>
        <div class="stat-label">Net Profit</div>
        <div class="stat-value">
            <span class="short-value"><?= formatCurrencyShort($net_profit) ?></span>
            <span class="full-value"><?= formatCurrency($net_profit) ?></span>
        </div>
    </div>
</div>

<div class="row" style="margin-bottom: 30px;">
    <!-- Monthly Cash Flow Chart (Unchanged) -->
    <div class="col-8">
        <div class="chart-card-custom">
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-chart-simple"></i></div>
                        Monthly Cash Flow
                    </h3>
                    <div class="chart-subtitle">Financial year <?= date('Y') ?></div>
                </div>
                <div class="chart-legend-custom">
                    <div class="legend-pill">
                        <span class="legend-dot" style="background: #10b981;"></span>
                        Income <span class="legend-val inc"><?= formatCurrencyShort($total_income_yr) ?></span>
                    </div>
                    <div class="legend-pill">
                        <span class="legend-dot" style="background: #ef4444;"></span>
                        Expense <span class="legend-val exp"><?= formatCurrencyShort($total_expense_yr) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="chart-area-wrapper">
                <canvas id="cashFlowChart"></canvas>
            </div>

            <div class="stats-footer-grid">
                <div class="stat-box-modern green">
                    <label>Net Income</label>
                    <span class="value"><?= formatCurrencyShort($net_income_yr) ?></span>
                    <span class="sub-text">
                        <?php if ($profit_growth >= 0): ?>
                            <i class="fas fa-arrow-up"></i> +<?= number_format($profit_growth, 1) ?>% growth
                        <?php else: ?>
                            <i class="fas fa-arrow-down" style="color: #ef4444;"></i> <?= number_format($profit_growth, 1) ?>% decline
                        <?php endif; ?>
                    </span>
                </div>
                <div class="stat-box-modern blue">
                    <label>Avg Monthly</label>
                    <span class="value"><?= formatCurrencyShort($avg_monthly) ?></span>
                    <span class="sub-text">Per month average</span>
                </div>
                <div class="stat-box-modern purple">
                    <?php 
                    $marginText = 'Average margin';
                    if($profit_margin >= 20) $marginText = 'Excellent margin';
                    elseif($profit_margin >= 10) $marginText = 'Good margin';
                    ?>
                    <label>Profit Margin</label>
                    <span class="value"><?= number_format($profit_margin, 1) ?>%</span>
                    <span class="sub-text"><?= $marginText ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales by Project Chart (Unchanged) -->
    <div class="col-4">
        <div class="chart-card-custom">
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box purple"><i class="fas fa-chart-pie"></i></div>
                        Sales by Project
                    </h3>
                    <div class="chart-subtitle">Distribution overview</div>
                </div>
            </div>

            <div class="doughnut-container">
                <canvas id="projectChart"></canvas>
                <div class="doughnut-center">
                    <h4><?= formatCurrencyShort($total_project_sales_calc) ?></h4>
                    <span>Total Sales</span>
                </div>
            </div>

            <div class="project-list">
                <?php 
                // Prepare data for chart and list
                $projLabels = [];
                $projValues = [];
                $projColors = [];
                
                $i = 0;
                foreach($project_stats as $proj): 
                    $perc = $total_project_sales_calc > 0 ? ($proj['total_sales'] / $total_project_sales_calc) * 100 : 0;
                    $color = ColorHelper::getProjectColor($proj['project_id']);
                    
                    // Arrays for JS Chart
                    $projLabels[] = addslashes($proj['project_name']);
                    $projValues[] = $proj['total_sales'];
                    $projColors[] = $color;

                    $bgClass = ($i == 0) ? 'primary' : 'secondary';
                    $lbl = ($i == 0) ? 'Primary project' : 'Secondary project';
                    $i++;
                ?>
                <div class="project-list-item <?= $bgClass ?>">
                    <div class="p-dot" style="background-color: <?= $color ?>"></div>
                    <div class="p-info">
                        <h5><?= htmlspecialchars($proj['project_name']) ?></h5>
                        <span><?= $lbl ?></span>
                    </div>
                    <div class="p-stats">
                        <span class="amount"><?= formatCurrencyShort($proj['total_sales']) ?></span>
                        <span class="perc"><?= number_format($perc, 0) ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mini-stats-row">
                <div class="mini-stat">
                    <span class="val"><?= count($project_stats) ?></span>
                    <span class="lbl">Active Projects</span>
                </div>
                <div class="mini-stat">
                    <span class="val"><?= count($recent_bookings) // Placeholder ?></span>
                    <span class="lbl">Total Bookings</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     NEW BOTTOM SECTION 
========================== -->



<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.color = '#94a3b8';

    // Helper for formatting large numbers in Indian system (approx)
    const formatIndianShort = (num) => {
        if(num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
        if(num >= 100000) return (num / 100000).toFixed(2) + ' L';
        if(num >= 1000) return (num / 1000).toFixed(2) + ' K';
        return num;
    };

    // Cash Flow Chart
    const ctxFlow = document.getElementById('cashFlowChart').getContext('2d');
    new Chart(ctxFlow, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Income',
                    data: [<?= implode(',', array_column($monthly_stats, 'income')) ?>],
                    backgroundColor: '#10b981', 
                    borderRadius: 6,
                    barPercentage: 0.5,
                    categoryPercentage: 0.8,
                    borderWidth: 0,
                    hoverBackgroundColor: '#059669'
                },
                {
                    label: 'Expense',
                    data: [<?= implode(',', array_column($monthly_stats, 'expense')) ?>],
                    backgroundColor: '#ef4444', 
                    borderRadius: 6,
                    barPercentage: 0.5,
                    categoryPercentage: 0.8,
                    borderWidth: 0,
                    hoverBackgroundColor: '#dc2626'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                    titleFont: { size: 13 },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            return label + '₹ ' + formatIndianShort(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: '#94a3b8',
                        font: {size: 11, weight: 500},
                        callback: function(value) {
                             if(value === 0) return '0';
                             return formatIndianShort(value);
                        }
                    },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: {size: 11, weight: 500} },
                    border: { display: false }
                }
            }
        }
    });

    // Project Sales - Doughnut
    const ctxProj = document.getElementById('projectChart').getContext('2d');
    new Chart(ctxProj, {
        type: 'doughnut',
        data: {
            labels: [<?= "'" . implode("','", $projLabels) . "'" ?>],
            datasets: [{
                data: [<?= implode(',', $projValues) ?>],
                backgroundColor: [<?= "'" . implode("','", $projColors) . "'" ?>],
                borderWidth: 0,
                hoverOffset: 4,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            },
            layout: { padding: 10 }
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
