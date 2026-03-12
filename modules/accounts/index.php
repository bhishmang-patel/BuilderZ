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
$stmt = $db->query("SELECT SUM(amount + COALESCE(gst_amount, 0)) as total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$monthly_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount + COALESCE(gst_amount, 0)) as total FROM expenses WHERE payment_method = 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$cash_expense = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(amount + COALESCE(gst_amount, 0)) as total FROM expenses WHERE payment_method != 'cash' AND DATE_FORMAT(date, '%Y-%m') = ?", [$current_month]);
$bank_expense = $stmt->fetch()['total'] ?? 0;

// Expense by Category (with Month Filter)
if (isset($_GET['cat_month'])) {
    $cat_month = $_GET['cat_month']; // Could be empty string for 'All Time'
} else {
    $cat_month = $current_month; // Default
}

if (empty($cat_month)) {
    $cat_stmt = $db->query("SELECT ec.name, SUM(e.amount + COALESCE(e.gst_amount, 0)) as total 
                            FROM expenses e 
                            JOIN expense_categories ec ON e.category_id = ec.id 
                            GROUP BY ec.name");
} else {
    $cat_stmt = $db->query("SELECT ec.name, SUM(e.amount + COALESCE(e.gst_amount, 0)) as total 
                            FROM expenses e 
                            JOIN expense_categories ec ON e.category_id = ec.id 
                            WHERE DATE_FORMAT(e.date, '%Y-%m') = ? 
                            GROUP BY ec.name", [$cat_month]);
}
$category_data = $cat_stmt->fetchAll();
$cat_labels = [];
$cat_values = [];
$cat_total_expense = 0;
foreach ($category_data as $row) {
    $cat_labels[] = $row['name'];
    $cat_values[] = $row['total'];
    $cat_total_expense += $row['total'];
}

// Generate last 12 months for dropdown
$month_options = [];
for ($i = 0; $i < 12; $i++) {
    $m_val = date('Y-m', strtotime("-$i months"));
    $m_label = date('F Y', strtotime("-$i months"));
    $month_options[$m_val] = $m_label;
}

// Last 6 Months Trend
$trend_stmt = $db->query("SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount + COALESCE(gst_amount, 0)) as total 
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
        gap: 1.25rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 16px;
        padding: 1.6rem 1.5rem 1.4rem;
        position: relative; overflow: hidden;
        animation: fadeUp 0.4s ease both;
        transition: transform 0.22s, box-shadow 0.22s;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s;  }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(26,23,20,0.09); }

    /* Coloured top-bar accent */
    .stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .stat-card.sc-purple::before { background: linear-gradient(90deg, #9333ea, #c084fc); }
    .stat-card.sc-green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
    .stat-card.sc-teal::before   { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }

    /* Faint watermark icon */
    .stat-card .s-watermark {
        position: absolute; right: -0.6rem; bottom: -0.9rem;
        font-size: 5rem; opacity: 0.045; line-height: 1;
        pointer-events: none; transition: opacity 0.22s, transform 0.22s;
    }
    .stat-card:hover .s-watermark { opacity: 0.07; transform: scale(1.06) rotate(-4deg); }

    /* Row: icon pill + label */
    .stat-top { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 1.1rem; }

    .s-pill {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.28rem 0.75rem 0.28rem 0.55rem;
        border-radius: 999px; font-size: 0.68rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
    }
    .pill-purple { background: #f3e8ff; color: #9333ea; }
    .pill-green  { background: #ecfdf5; color: #10b981; }
    .pill-teal   { background: #f0fdfa; color: #14b8a6; }
    .s-pill i    { font-size: 0.65rem; }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-mute); flex: 1;
    }

    /* Big value */
    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 800;
        color: var(--ink); line-height: 1; letter-spacing: -0.03em;
        font-variant-numeric: tabular-nums;
    }

    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }

    /* Sub-line */
    .stat-sub {
        margin-top: 0.55rem; font-size: 0.72rem; color: var(--ink-mute);
        font-weight: 500; display: flex; align-items: center; gap: 0.3rem;
    }
    .stat-sub .badge {
        display: inline-flex; align-items: center; gap: 0.2rem;
        padding: 0.15rem 0.5rem; border-radius: 6px;
        font-size: 0.65rem; font-weight: 700;
    }
    .badge-up   { background: #ecfdf5; color: #059669; }
    .badge-down { background: #fef2f2; color: #dc2626; }

    /* ── Charts Grid ─────────────────────────── */
    .charts-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .charts-grid { grid-template-columns: 1fr; } }

    .chart-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 16px; overflow: hidden;
        opacity: 0; animation: fadeUp 0.5s ease both;
        box-shadow: 0 1px 3px rgba(26,23,20,0.04);
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .chart-card:nth-child(1) { animation-delay: 0.18s; }
    .chart-card:nth-child(2) { animation-delay: 0.24s; }
    .chart-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(26,23,20,0.08);
    }

    .chart-header {
        display: flex; align-items: center; gap: 0.85rem;
        padding: 1.2rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: linear-gradient(135deg, #fdfcfa 0%, #f8f6f1 100%);
    }

    .chart-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .ico-blue   { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
    .ico-violet { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #7c3aed; }

    .chart-title {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700;
        color: var(--ink); letter-spacing: -0.01em;
    }
    /* Chart Filter Select */
    .chart-filter-form { margin-left: auto; }
    .chart-month-select {
        font-family: 'DM Sans', sans-serif; font-size: 0.75rem; font-weight: 600;
        color: var(--ink-soft); background: var(--cream);
        border: 1.5px solid var(--border); border-radius: 6px;
        padding: 0.25rem 1.6rem 0.25rem 0.6rem;
        appearance: none; -webkit-appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='3'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.5rem center;
        transition: all 0.15s ease;
    }
    .chart-month-select:hover, .chart-month-select:focus {
        border-color: var(--accent); color: var(--accent); outline: none;
    }

    .chart-body { padding: 1.5rem 1.5rem 1.25rem; }
    .chart-area {
        min-height: 280px; position: relative;
        display: flex; align-items: center; justify-content: center;
    }

    /* Doughnut center label */
    .chart-center-label {
        position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%);
        text-align: center; pointer-events: none; z-index: 2;
    }
    .chart-center-label .center-amount {
        font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 800;
        color: var(--ink); line-height: 1;
    }
    .chart-center-label .center-sub {
        font-size: 0.6rem; color: var(--ink-mute); text-transform: uppercase;
        letter-spacing: 0.12em; font-weight: 600; margin-top: 0.3rem;
    }

    .chart-summary {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem; margin-top: 1.25rem; padding-top: 1.15rem;
        border-top: 1.5px solid var(--border-lt);
    }
    .sum-item {
        text-align: center; padding: 0.5rem 0.25rem;
        border-radius: 8px; transition: background 0.15s;
    }
    .sum-item:hover { background: #faf8f5; }
    .sum-label {
        font-size: 0.65rem; font-weight: 700; color: var(--ink-mute);
        text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.4rem;
        display: flex; align-items: center; justify-content: center; gap: 0.35rem;
    }
    .sum-label .dot {
        width: 6px; height: 6px; border-radius: 50%; display: inline-block;
    }
    .dot-red   { background: #ef4444; }
    .dot-blue  { background: #3b82f6; }
    .dot-green { background: #10b981; }
    .sum-value { font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 700; }
    .sum-value.red    { color: #ef4444; }
    .sum-value.blue   { color: #3b82f6; }
    .sum-value.green  { color: #10b981; }

    /* Empty state */
    .empty-chart { padding: 3rem 1rem; text-align: center; }
    .empty-chart i { font-size: 2.5rem; color: var(--border); margin-bottom: 0.75rem; display: block; }
    .empty-chart p { font-size: 0.85rem; color: var(--ink-mute); margin: 0; }

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

    <div class="stat-card sc-purple">
        <i class="fas fa-wallet s-watermark"></i>
        <div class="stat-top">
            <span class="s-pill pill-purple"><i class="fas fa-wallet"></i> Monthly</span>
            <span class="stat-label">Total Expenses (<?= date('M Y') ?>)</span>
        </div>
        <div class="stat-value">
            <span class="short-val"><?= formatCurrencyShort($monthly_expense) ?></span>
            <span class="full-val"><?= formatCurrency($monthly_expense) ?></span>
        </div>
        <div class="stat-sub">All payment modes combined</div>
    </div>

    <div class="stat-card sc-green">
        <i class="fas fa-money-bill-wave s-watermark"></i>
        <div class="stat-top">
            <span class="s-pill pill-green"><i class="fas fa-money-bill-wave"></i> Cash</span>
            <span class="stat-label">Cash / Petty Cash</span>
        </div>
        <div class="stat-value">
            <span class="short-val"><?= formatCurrencyShort($cash_expense) ?></span>
            <span class="full-val"><?= formatCurrency($cash_expense) ?></span>
        </div>
        <div class="stat-sub">
            <?php 
                $cash_pct = $monthly_expense > 0 ? round(($cash_expense / $monthly_expense) * 100) : 0;
            ?>
            <span class="badge badge-up"><?= $cash_pct ?>%</span> of total this month
        </div>
    </div>

    <div class="stat-card sc-teal">
        <i class="fas fa-university s-watermark"></i>
        <div class="stat-top">
            <span class="s-pill pill-teal"><i class="fas fa-university"></i> Bank</span>
            <span class="stat-label">Bank / Online</span>
        </div>
        <div class="stat-value">
            <span class="short-val"><?= formatCurrencyShort($bank_expense) ?></span>
            <span class="full-val"><?= formatCurrency($bank_expense) ?></span>
        </div>
        <div class="stat-sub">
            <?php 
                $bank_pct = $monthly_expense > 0 ? round(($bank_expense / $monthly_expense) * 100) : 0;
            ?>
            <span class="badge badge-up"><?= $bank_pct ?>%</span> of total this month
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
                    <div class="chart-subtitle">Distribution Analysis</div>
                </div>
                <form method="GET" class="chart-filter-form" id="catFilterForm">
                    <!-- Preserve existing GET params except cat_month -->
                    <?php 
                    foreach ($_GET as $k => $v) {
                        if ($k !== 'cat_month') {
                            echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
                        }
                    }
                    ?>
                    <select name="cat_month" class="chart-month-select" onchange="document.getElementById('catFilterForm').submit();">
                        <option value="">All Time</option>
                        <?php foreach($month_options as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $cat_month === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="chart-body">
                <div class="chart-area">
                    <?php if (empty($category_data)): ?>
                        <div class="empty-chart">
                            <i class="fas fa-chart-pie"></i>
                            <p>No expense categories found.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-center-label">
                            <div class="center-amount"><?= formatCurrencyShort($cat_total_expense) ?></div>
                            <div class="center-sub"><?= $cat_month === '' ? 'All Time' : date('M Y', strtotime($cat_month . '-01')) ?></div>
                        </div>
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
                        <div class="sum-label"><span class="dot dot-red"></span> Highest</div>
                        <div class="sum-value red">
                            <?php 
                                $max_expense = !empty($trend_values) ? max($trend_values) : 0;
                                echo formatCurrencyShort($max_expense);
                            ?>
                        </div>
                    </div>
                    <div class="sum-item">
                        <div class="sum-label"><span class="dot dot-blue"></span> Average</div>
                        <div class="sum-value blue">
                            <?php 
                                $avg_expense = !empty($trend_values) ? array_sum($trend_values) / count($trend_values) : 0;
                                echo formatCurrencyShort($avg_expense);
                            ?>
                        </div>
                    </div>
                    <div class="sum-item">
                        <div class="sum-label"><span class="dot dot-green"></span> Current</div>
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
                            </td>
                            <td>
                                <span class="pill gray">
                                    <?= htmlspecialchars($expense['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-weight:700;color:var(--ink)">
                                    <?= formatCurrency($expense['amount'] + ($expense['gst_amount'] ?? 0)) ?>
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
                                    <form method="POST" action="list.php" id="del-expense-<?= $expense['id'] ?>" style="margin:0">
                                        <input type="hidden" name="delete_id" value="<?= $expense['id'] ?>">
                                        <button type="button" onclick="confirmExpenseDelete(<?= $expense['id'] ?>)" class="act-btn del" title="Delete">
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
    function confirmExpenseDelete(id) {
        window.customConfirm({
            title: 'Delete Expense?',
            text: 'Are you sure you want to delete this expense?',
            icon: '<i class="fas fa-trash-alt"></i>',
            confirmText: 'Yes, Delete'
        }, function() {
            document.getElementById('del-expense-' + id).submit();
        });
    }

    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.color = '#9e9690';

    const formatIndianShort = (num) => {
        if(num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
        if(num >= 100000) return (num / 100000).toFixed(2) + ' L';
        if(num >= 1000) return (num / 1000).toFixed(2) + ' K';
        return num.toFixed(0);
    };

    const premiumTooltip = {
        backgroundColor: 'rgba(26, 23, 20, 0.92)',
        titleColor: '#fff',
        bodyColor: 'rgba(255,255,255,0.85)',
        titleFont: { size: 13, weight: '700', family: "'DM Sans', sans-serif" },
        bodyFont: { size: 12, family: "'DM Sans', sans-serif" },
        borderColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        padding: { top: 10, bottom: 10, left: 14, right: 14 },
        cornerRadius: 10,
        displayColors: false,
        boxPadding: 4,
    };

    // ── Trend Line Chart ──────────────────────────
    <?php if (!empty($trend_data)): ?>
    (function() {
        var ctx = document.getElementById("trendChart");
        var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(124, 58, 237, 0.18)');
        gradient.addColorStop(0.6, 'rgba(124, 58, 237, 0.04)');
        gradient.addColorStop(1, 'rgba(124, 58, 237, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: "Expenses",
                    backgroundColor: gradient,
                    borderColor: '#7c3aed',
                    pointBackgroundColor: '#7c3aed',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2.5,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#7c3aed',
                    pointHoverBorderWidth: 3,
                    data: <?= json_encode($trend_values) ?>,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 7
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { left: 5, right: 10, top: 20, bottom: 0 } },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            maxTicksLimit: 6, color: '#9e9690',
                            font: { size: 11, weight: '500' }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 5, padding: 12,
                            color: '#9e9690',
                            font: { size: 11 },
                            callback: function(v) { return '₹' + formatIndianShort(v); }
                        },
                        grid: {
                            color: '#f0ece5',
                            drawBorder: false,
                            borderDash: [4, 4]
                        }
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...premiumTooltip,
                        callbacks: {
                            title: function(items) { return items[0].label; },
                            label: function(ctx) {
                                return '  ₹ ' + ctx.raw.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                        }
                    }
                },
            }
        });
    })();
    <?php endif; ?>

    // ── Category Doughnut Chart ───────────────────
    <?php if (!empty($category_data)): ?>
    (function() {
        var ctxPie = document.getElementById("categoryChart");
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($cat_labels) ?>,
                datasets: [{
                    data: <?= json_encode($cat_values) ?>,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6',
                        '#ef4444', '#14b8a6', '#ec4899', '#6366f1',
                        '#f97316', '#06b6d4'
                    ],
                    hoverBackgroundColor: [
                        '#2563eb', '#059669', '#d97706', '#7c3aed',
                        '#dc2626', '#0d9488', '#db2777', '#4f46e5',
                        '#ea580c', '#0891b2'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 8,
                    spacing: 2,
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10, boxHeight: 10,
                            padding: 14, usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '500' },
                            color: '#6b6560'
                        }
                    },
                    tooltip: {
                        ...premiumTooltip,
                        displayColors: true,
                        boxWidth: 10, boxHeight: 10,
                        usePointStyle: true,
                        callbacks: {
                            label: function(ctx) {
                                var val = ctx.raw || 0;
                                var total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                var pct = ((val / total) * 100).toFixed(1);
                                return '  ₹' + formatIndianShort(val) + '  (' + pct + '%)';
                            }
                        }
                    }
                },
            },
        });
    })();
    <?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>