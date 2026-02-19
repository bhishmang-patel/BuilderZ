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
$current_page = 'accounts';

// Current Month Stats
$current_month = date('Y-m');
$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$monthly_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE payment_method = 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$cash_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount) as total FROM expenses WHERE payment_method != 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$bank_expense = $stmt->fetch()['total'] ?? 0;

// Expense by Category
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

// Last 6 Months Trend
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

// Recent Transactions
$recent_expenses = $db->query(
    "SELECT e.*, ec.name as category_name, p.project_name 
     FROM expenses e 
     LEFT JOIN expense_categories ec ON e.category_id = ec.id 
     LEFT JOIN projects p ON e.project_id = p.id
     ORDER BY e.date DESC, e.id DESC LIMIT 5"
)->fetchAll();

// Fetch Active Project for fallback display
$active_project_stmt = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY created_at DESC LIMIT 1");
$active_project = $active_project_stmt->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    /* ── Page Wrapper ────────────────────────── */
    .acc-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .acc-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .acc-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .acc-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .acc-header h1 em { color: var(--accent); font-style: italic; }

    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink);
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.28); color: white; }
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: white; color: var(--ink-soft);
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--border);
    }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
        display: flex; align-items: center; gap: 1.25rem;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .s-icon {
        width: 48px; height: 48px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .ico-purple { background: #f3e8ff; color: #9333ea; }
    .ico-green  { background: #ecfdf5; color: #10b981; }
    .ico-teal   { background: #f0fdfa; color: #14b8a6; }

    .stat-content { flex: 1; }
    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
    }

    /* ── Charts Grid ─────────────────────────── */
    .charts-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .charts-grid { grid-template-columns: 1fr; } }

    .chart-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    .chart-header {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 1.3rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .chart-icon {
        width: 36px; height: 36px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; flex-shrink: 0;
    }
    .ico-blue   { background: #eff6ff; color: #3b82f6; }
    .ico-violet { background: #f5f3ff; color: #8b5cf6; }

    .chart-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); }
    .chart-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.2rem; }

    .chart-body { padding: 1.5rem; }
    .chart-area { min-height: 280px; display: flex; align-items: center; justify-content: center; }

    .chart-summary {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1rem; margin-top: 1.5rem; padding-top: 1.25rem;
        border-top: 1.5px solid var(--border-lt);
    }
    .sum-item { text-align: center; }
    .sum-label { font-size: 0.7rem; font-weight: 600; color: var(--ink-mute); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
    .sum-value { font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 700; }
    .sum-value.red    { color: #ef4444; }
    .sum-value.blue   { color: #3b82f6; }
    .sum-value.green  { color: #10b981; }

    /* Empty state */
    .empty-chart {
        padding: 3rem 1rem; text-align: center;
    }
    .empty-chart i {
        font-size: 2.5rem; color: var(--border);
        margin-bottom: 0.75rem; display: block;
    }
    .empty-chart p {
        font-size: 0.85rem; color: var(--ink-mute); margin: 0;
    }

    /* ── Table Panel ─────────────────────────── */
    .table-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.5s 0.25s ease both;
    }

    .table-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .table-header-left { display: flex; align-items: center; gap: 0.75rem; }
    .tbl-icon { width: 36px; height: 36px; background: #ecfdf5; color: #059669; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }

    .acc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .acc-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .acc-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .acc-table thead th.th-c { text-align: center; }

    .acc-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .acc-table tbody tr:last-child { border-bottom: none; }
    .acc-table tbody tr:hover { background: #fdfcfa; }

    .acc-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .acc-table td.td-c { text-align: center; }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em;
    }
    .pill.blue  { background: #eff6ff; color: #1e40af; }
    .pill.gray  { background: #f0ece5; color: var(--ink-soft); }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; justify-content: center; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.del:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
    .act-btn.view:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }

    /* FAB */
    .fab {
        position: fixed; bottom: 2rem; right: 2rem;
        width: 56px; height: 56px; background: var(--ink);
        border-radius: 50%; display: flex; align-items: center;
        justify-content: center; color: white; font-size: 1.2rem;
        text-decoration: none; box-shadow: 0 8px 24px rgba(26,23,20,0.25);
        transition: all 0.2s; z-index: 100;
    }
    .fab:hover { background: var(--accent); transform: scale(1.08); box-shadow: 0 12px 32px rgba(181,98,42,0.35); color: white; }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="acc-wrap">

    <!-- Header -->
    <div class="acc-header">
        <div>
            <div class="eyebrow">Financial Overview</div>
            <h1>Accounts & <em>Expenses</em></h1>
        </div>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>modules/accounts/categories.php" class="btn-secondary">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
            <a href="<?= BASE_URL ?>modules/accounts/add.php" class="btn-new">
                <i class="fas fa-plus"></i> Record Expense
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="s-icon ico-purple"><i class="fas fa-wallet"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Expenses (<?= date('M') ?>)</div>
                <div class="stat-value"><?= formatCurrencyShort($monthly_expense) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-green"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <div class="stat-label">Cash / Petty Cash</div>
                <div class="stat-value"><?= formatCurrencyShort($cash_expense) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-teal"><i class="fas fa-university"></i></div>
            <div class="stat-content">
                <div class="stat-label">Bank / Online</div>
                <div class="stat-value"><?= formatCurrencyShort($bank_expense) ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-grid">
        
        <!-- Category Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon ico-blue"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <div class="chart-title">Expenses by Category</div>
                    <div class="chart-subtitle"><?= date('M Y') ?> Distribution</div>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-area">
                    <?php if (empty($category_data)): ?>
                        <div class="empty-chart">
                            <i class="fas fa-chart-pie"></i>
                            <p>No expense categories found.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="categoryChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon ico-violet"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="chart-title">Expense Trend</div>
                    <div class="chart-subtitle">Last 6 Months Analysis</div>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-area">
                    <?php if (empty($trend_data)): ?>
                        <div class="empty-chart">
                            <i class="fas fa-chart-line"></i>
                            <p>No expense history available.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="trendChart"></canvas>
                    <?php endif; ?>
                </div>

                <div class="chart-summary">
                    <div class="sum-item">
                        <div class="sum-label">Highest Month</div>
                        <div class="sum-value red">
                            <?php 
                                $max_expense = !empty($trend_values) ? max($trend_values) : 0;
                                echo formatCurrencyShort($max_expense);
                            ?>
                        </div>
                    </div>
                    <div class="sum-item">
                        <div class="sum-label">Average</div>
                        <div class="sum-value blue">
                            <?php 
                                $avg_expense = !empty($trend_values) ? array_sum($trend_values) / count($trend_values) : 0;
                                echo formatCurrencyShort($avg_expense);
                            ?>
                        </div>
                    </div>
                    <div class="sum-item">
                        <div class="sum-label">This Month</div>
                        <div class="sum-value green">
                            <?= formatCurrencyShort($monthly_expense) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Transactions Table -->
    <div class="table-panel">
        <div class="table-header">
            <div class="table-header-left">
                <div class="tbl-icon"><i class="fas fa-history"></i></div>
                <div>
                    <div class="chart-title">Recent Transactions</div>
                    <div class="chart-subtitle">Latest 5 expense records</div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>modules/accounts/list.php" class="act-btn view" title="View All">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div style="overflow-x:auto">
            <table class="acc-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th class="th-c">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:3rem;color:var(--ink-mute)">
                                No transactions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_expenses as $expense): ?>
                        <tr>
                            <td>
                                <span style="font-weight:600;color:#3b82f6;font-size:0.82rem">
                                    <?= date('d M Y', strtotime($expense['date'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($expense['project_name'])): ?>
                                  <?= renderProjectBadge($expense['project_name'], $expense['project_id']) ?>
                                <?php elseif ($active_project): ?>
                                    <?= renderProjectBadge($active_project['project_name'], $active_project['id']) ?>
                                <?php else: ?>
                                    <span class="pill gray">Head Office</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:0.875rem;color:var(--ink);font-weight:500">
                                    <?= htmlspecialchars($expense['description'] ?: '—') ?>
                                </div>
                                <?php if($expense['gst_included']): ?>
                                    <div style="font-size:0.7rem;color:#10b981;margin-top:0.2rem">
                                        <i class="fas fa-check-circle"></i> GST Paid: <?= formatCurrencyShort($expense['gst_amount']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="pill gray">
                                    <?= htmlspecialchars($expense['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-weight:700;color:var(--ink)">
                                    <?= formatCurrency($expense['amount']) ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size:0.75rem;text-transform:uppercase;color:var(--ink-mute);font-weight:600">
                                    <?= $expense['payment_method'] ?>
                                </span>
                            </td>
                            <td class="td-c">
                                <div class="act-group">
                                    <a href="add.php?id=<?= $expense['id'] ?>" class="act-btn" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <form method="POST" action="list.php" onsubmit="return confirm('Delete this expense?');" style="margin:0">
                                        <input type="hidden" name="delete_id" value="<?= $expense['id'] ?>">
                                        <button type="submit" class="act-btn del" title="Delete">
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

<!-- Floating Action Button -->
<a href="<?= BASE_URL ?>modules/accounts/add.php" class="fab" title="Record Expense">
    <i class="fas fa-plus"></i>
</a>

<!-- Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    Chart.defaults.color = 'var(--ink-mute)';
    Chart.defaults.font.family = "'DM Sans', sans-serif";

    const formatIndianShort = (num) => {
        if(num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
        if(num >= 100000) return (num / 100000).toFixed(2) + ' L';
        if(num >= 1000) return (num / 1000).toFixed(2) + ' K';
        return num.toFixed(0);
    };

    // Trend Line Chart
    <?php if (!empty($trend_data)): ?>
    var ctx = document.getElementById("trendChart");
    var trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [{
                label: "Expenses",
                backgroundColor: "rgba(139, 92, 246, 0.05)",
                borderColor: "#8b5cf6",
                pointBackgroundColor: "#8b5cf6",
                pointBorderColor: "#fff",
                pointHoverBackgroundColor: "#fff",
                pointHoverBorderColor: "#8b5cf6",
                data: <?= json_encode($trend_values) ?>,
                fill: true,
                tension: 0.3,
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 5
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 10, top: 20, bottom: 0 } },
            scales: {
                x: { 
                    grid: { display: false, drawBorder: false }, 
                    ticks: { maxTicksLimit: 6, color: 'var(--ink-mute)', font: {size: 11} } 
                },
                y: { 
                    ticks: { 
                        maxTicksLimit: 5, 
                        padding: 10, 
                        color: 'var(--ink-mute)',
                        font: {size: 11},
                        callback: function(value) { return '₹' + formatIndianShort(value); } 
                    }, 
                    grid: { 
                        color: "var(--border-lt)", 
                        drawBorder: false, 
                        borderDash: [3]
                    } 
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: "#fff",
                    bodyColor: "var(--ink-soft)",
                    titleColor: 'var(--ink)',
                    titleFont: { size: 13, weight: 600 },
                    borderColor: 'var(--border)',
                    borderWidth: 1.5,
                    padding: 12,
                    displayColors: false,
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

    // Category Doughnut Chart
    <?php if (!empty($category_data)): ?>
    var ctxPie = document.getElementById("categoryChart");
    var categoryChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($cat_labels) ?>,
            datasets: [{
                data: <?= json_encode($cat_values) ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#14b8a6', '#f59e0b', '#ef4444', '#6b6560', '#9333ea'],
                hoverBackgroundColor: ['#2563eb', '#059669', '#0d9488', '#d97706', '#dc2626', '#57534e', '#7c3aed'],
                hoverBorderColor: "var(--border)",
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
                        font: { size: 11 },
                        color: 'var(--ink-soft)'
                    } 
                },
                tooltip: {
                    backgroundColor: "#fff",
                    bodyColor: "var(--ink-soft)",
                    titleColor: 'var(--ink)',
                    borderColor: 'var(--border)',
                    borderWidth: 1.5,
                    padding: 12,
                    displayColors: true, 
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