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
$page_title = 'Accounts & Expenses';

// --- DATA FETCHING ---

// 1. Current Month Stats
$current_month = date('Y-m');
$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$monthly_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE payment_method = 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$cash_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE payment_method != 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$bank_expense = $stmt->fetch()['total'] ?? 0;

// 2. Expense by Category (Pie Chart)
$cat_stmt = $db->query("SELECT ec.name, SUM(e.amount) as total 
                        FROM expenses e 
                        JOIN expense_categories ec ON e.category_id = ec.id 
                        WHERE DATE_FORMAT(e.date, '%Y-%m') = ? 
                        GROUP BY ec.name", [$current_month]);
$category_data = $cat_stmt->fetchAll();
$cat_labels = [];
$cat_values = [];
foreach ($category_data as $row) {
    $cat_labels[] = $row['name'];
    $cat_values[] = $row['total'];
}

// 3. Last 6 Months Trend (Bar Chart)
$trend_stmt = $db->query("SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total 
                          FROM expenses 
                          WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                          GROUP BY month 
                          ORDER BY month ASC");
$trend_data = $trend_stmt->fetchAll();
$trend_labels = [];
$trend_values = [];
foreach ($trend_data as $row) {
    $trend_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $trend_values[] = $row['total'];
}

// 4. Recent Transactions
$recent_expenses = $db->query(
    "SELECT e.*, ec.name as category_name, p.project_name 
     FROM expenses e 
     LEFT JOIN expense_categories ec ON e.category_id = ec.id 
     LEFT JOIN projects p ON e.project_id = p.id
     ORDER BY e.date DESC, e.id DESC LIMIT 5"
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounts.css">

<div class="container-fluid">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800" style="font-weight: 900;">Accounts Overview</h1>
            <p class="mb-0 text-muted" style="font-weight: 700; font-size: 12px; margin-top: 25px">Financial snapshot for <?= date('F Y') ?></p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/accounts/categories.php" class="modern-btn" style="background: #fff; color: #64748b; border: 1px solid #e2e8f0; margin-right: 10px;">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
            <a href="<?= BASE_URL ?>modules/accounts/add.php" class="modern-btn">
                <i class="fas fa-plus"></i> Record Expense
            </a>
        </div>
    </div>

    <!-- Modern Stats Grid (Matches Inventory) -->
    <div class="stats-container">
        <!-- Total Expense Card -->
        <div class="stat-card-modern">
            <div class="stat-icon bg-violet-light">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-info">
                <h4>Total Expenses (<?= date('M') ?>)</h4>
                <div class="value"><?= formatCurrency($monthly_expense) ?></div>
            </div>
        </div>

        <!-- Cash Expense Card -->
        <div class="stat-card-modern">
            <div class="stat-icon bg-emerald-light">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h4>Cash / Petty Cash</h4>
                <div class="value"><?= formatCurrency($cash_expense) ?></div>
            </div>
        </div>

        <!-- Bank Expense Card -->
        <div class="stat-card-modern">
            <div class="stat-icon bg-teal-light">
                <i class="fas fa-university"></i>
            </div>
            <div class="stat-info">
                <h4>Bank / Online</h4>
                <div class="value"><?= formatCurrency($bank_expense) ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Layout (CSS Grid) -->
    <div class="charts-grid">
        <!-- LEFT COLUMN: Category Chart -->
        <div>
            <div class="chart-card-custom h-100">
                <div class="chart-header-custom">
                    <div class="chart-title-group">
                        <h3>
                            <div class="chart-icon-box" style="background: #eff6ff; color: #3b82f6;">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            Expenses by Category
                        </h3>
                        <div class="chart-subtitle" style="margin-left: 52px;"><?= date('M Y') ?> Distribution</div>
                    </div>
                </div>
                <!-- Removed p-0 to allow padding for chart -->
                <div class="card-body"> 
                    <div class="chart-area" style="height: 300px; display: flex; align-items: center; justify-content: center;">
                        <?php if (empty($category_data)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-chart-pie fa-3x mb-3" style="opacity: 0.3;"></i>
                                <p class="mb-0">No expense categories found.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="categoryChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Trend Chart -->
        <div>
            <div class="chart-card-custom h-100">
                <div class="chart-header-custom">
                    <div class="chart-title-group">
                        <h3>
                            <div class="chart-icon-box" style="background: #f5f3ff; color: #8b5cf6;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            Expense Trend
                        </h3>
                        <div class="chart-subtitle" style="margin-left: 52px;">Last 6 Months Analysis</div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column" style="flex-direction: column !important;">
                    <div class="chart-area flex-grow-1" style="min-height: 250px; display: flex; align-items: center; justify-content: center;">
                        <?php if (empty($trend_data)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-chart-line fa-3x mb-3" style="opacity: 0.3;"></i>
                                <p class="mb-0">No expense history available.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="trendChart"></canvas>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Summary Stats Below Chart -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-4 text-center">
                            <div class="small text-muted mb-1">Highest Month</div>
                            <div class="h5 mb-0 font-weight-bold text-danger">
                                <?php 
                                    $max_expense = !empty($trend_values) ? max($trend_values) : 0;
                                    echo formatCurrencyShort($max_expense);
                                ?>
                            </div>
                        </div>
                        <div class="col-4 text-center border-left border-right">
                            <div class="small text-muted mb-1">Average</div>
                            <div class="h5 mb-0 font-weight-bold text-primary">
                                <?php 
                                    $avg_expense = !empty($trend_values) ? array_sum($trend_values) / count($trend_values) : 0;
                                    echo formatCurrencyShort($avg_expense);
                                ?>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="small text-muted mb-1">This Month</div>
                            <div class="h5 mb-0 font-weight-bold text-success">
                                <?= formatCurrencyShort($monthly_expense) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="row">
        <div class="col-12">
            <div class="chart-card-custom">
                 <div class="chart-header-custom">
                    <div class="chart-title-group">
                        <h3>
                            <div class="chart-icon-box" style="background: #ecfdf5; color: #059669;">
                                <i class="fas fa-history"></i>
                            </div>
                            Recent Transactions
                        </h3>
                        <div class="chart-subtitle" style="margin-left: 52px;">Latest 5 expense records</div>
                    </div>
                    <div>
                         <a href="<?= BASE_URL ?>modules/accounts/list.php" class="action-btn" title="View All">
                            <i class="fas fa-arrow-right"></i>
                         </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th width="12%"><div class="th-inner left">Date</div></th>
                                <th width="15%"><div class="th-inner left">Project</div></th>
                                <th width="25%"><div class="th-inner left">Description</div></th>
                                <th width="15%"><div class="th-inner left">Category</div></th>
                                <th width="15%"><div class="th-inner left">Amount</div></th>
                                <th width="10%"><div class="th-inner left">Mode</div></th>
                                <th width="50"><div class="th-inner left">Action</div></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_expenses)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_expenses as $expense): 
                                    $cat_initial = strtoupper(substr($expense['category_name'] ?: '?', 0, 1));
                                    // Stable random color based on category ID
                                    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6610f2', '#fd7e14'];
                                    $bg_color = $colors[($expense['category_id'] ?? 0) % count($colors)];
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:#4e73df; font-size:13px;">
                                            <?= date('d M Y', strtotime($expense['date'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($expense['project_name'])): ?>
                                            <span class="badge-soft blue" style="font-size:11px;">
                                                <i class="fas fa-building" style="margin-right:4px;"></i> <?= htmlspecialchars($expense['project_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-soft gray" style="font-size:11px;">Head Office</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:13.5px; color:#1e293b; font-weight:500;">
                                            <?= htmlspecialchars($expense['description'] ?: '—') ?>
                                        </div>
                                        <?php if($expense['gst_included']): ?>
                                            <div style="font-size:11px; color:#1cc88a;">
                                                <i class="fas fa-check-circle"></i> GST Paid: <?= formatCurrencyShort($expense['gst_amount']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-left">
                                        <div class="badge-soft gray">
                                            <?= htmlspecialchars($expense['category_name']) ?>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <span style="font-weight:700; color:#1e293b;">
                                            <?= formatCurrency($expense['amount']) ?>
                                        </span>
                                    </td>
                                    <td class="text-left">
                                        <span style="font-size:12px; text-transform:uppercase; color:#858796; font-weight:600;">
                                            <?= $expense['payment_method'] ?>
                                        </span>
                                    </td>
                                    <td class="text-left">
                                        <div class="d-flex justify-content-center" style="gap: 5px;">
                                            <a href="add.php?id=<?= $expense['id'] ?>" class="action-btn" title="Edit Expense">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="list.php" onsubmit="return confirmAction(event, 'Are you sure you want to delete this expense?', 'Yes, Delete It');" style="margin:0;">
                                                <input type="hidden" name="delete_id" value="<?= $expense['id'] ?>">
                                                <button type="submit" class="action-btn delete-btn" title="Delete Expense">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="<?= BASE_URL ?>modules/accounts/add.php" class="action-fab">
        <i class="fas fa-plus"></i>
    </a>

</div>

<!-- Chart Scripts -->
<script>
    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.color = '#858796';
    Chart.defaults.font.family = 'Nunito', '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';

    // Helper for formatting
    const formatIndianShort = (num) => {
        if(num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
        if(num >= 100000) return (num / 100000).toFixed(2) + ' L';
        if(num >= 1000) return (num / 1000).toFixed(2) + ' K';
        return num.toFixed(0);
    };

    // 1. Trend Line Chart
    <?php if (!empty($trend_data)): ?>
    var ctx = document.getElementById("trendChart");
    var trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [{
                label: "Expenses",
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "#4e73df",
                pointBackgroundColor: "#4e73df",
                pointBorderColor: "#fff",
                pointHoverBackgroundColor: "#fff",
                pointHoverBorderColor: "#4e73df",
                data: <?= json_encode($trend_values) ?>,
                fill: true,
                tension: 0.3,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
            scales: {
                x: { 
                    grid: { display: false, drawBorder: false }, 
                    ticks: { maxTicksLimit: 6, color: '#858796', font: {size: 11} } 
                },
                y: { 
                    ticks: { 
                        maxTicksLimit: 5, 
                        padding: 10, 
                        color: '#858796',
                        font: {size: 11},
                        callback: function(value) { return '₹' + formatIndianShort(value); } 
                    }, 
                    grid: { 
                        color: "rgb(234, 236, 244)", 
                        drawBorder: false, 
                        borderDash: [2]
                    } 
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleMarginBottom: 10,
                    titleColor: '#6e707e',
                    titleFont: { size: 14 },
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10,
                    callbacks: {
                        label: function(context) {
                            return 'Expenses: ₹' + formatIndianShort(context.raw);
                        }
                    }
                }
            },
        }
    });
    <?php endif; ?>

    // 2. Category Doughnut Chart
    <?php if (!empty($category_data)): ?>
    var ctxPie = document.getElementById("categoryChart");
    var categoryChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($cat_labels) ?>,
            datasets: [{
                data: <?= json_encode($cat_values) ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#60616f', '#373840'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
                borderWidth: 0
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom', 
                    labels: { 
                        boxWidth: 12, 
                        padding: 12,
                        font: { size: 11 }
                    } 
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: true, 
                    caretPadding: 10,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ₹' + formatIndianShort(value) + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '65%',
        },
    });
    <?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
