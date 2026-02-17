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
$page_title = 'Financial Overview';
$current_page = 'financial_overview';

// Get filter values
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$project_filter = $_GET['project'] ?? '';
$view_mode = $_GET['view'] ?? 'summary';

require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();
$financialData = $reportService->getFinancialOverview($date_from, $date_to, $project_filter);

extract($financialData);

// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

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
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
    }

    /* ── Page Wrapper ────────────────────────── */
    .fin-wrap { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .fin-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .fin-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .fin-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .fin-header h1 em { font-style: italic; color: var(--accent); }
    
    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-dl {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: white; color: var(--ink-soft);
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--border);
    }
    .btn-dl:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Filter Section ──────────────────────── */
    .filter-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; padding: 1.5rem; margin-bottom: 1.75rem;
        animation: fadeUp 0.4s ease both;
    }

    .filter-form { display: flex; align-items: flex-end; gap: 0.65rem; flex-wrap: wrap; }

    .f-group { flex: 1; min-width: 180px; }
    .f-label {
        display: block; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .f-input, .f-select {
        width: 100%; height: 42px; padding: 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.15s, box-shadow 0.15s;
    }
    .f-select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .f-input:focus, .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }

    .date-range { display: flex; gap: 0.5rem; align-items: center; }
    .date-range span { color: var(--ink-mute); font-size: 0.82rem; }

    .btn-filter {
        height: 42px; padding: 0 1.4rem; border: none; border-radius: 8px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; background: var(--ink); color: white;
    }
    .btn-filter:hover { background: var(--accent); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.1rem; margin-bottom: 1.75rem;
    }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
        position: relative; overflow: hidden;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:nth-child(4) { animation-delay: .2s; }
    .stat-card:nth-child(5) { animation-delay: .25s; }
    .stat-card:nth-child(6) { animation-delay: .3s; }
    .stat-card:nth-child(7) { animation-delay: .35s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-card::before {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0;
        height: 3px; opacity: 0.8;
    }
    .stat-card.income::before { background: #10b981; }
    .stat-card.expense::before { background: #ef4444; }
    .stat-card.profit::before { background: var(--accent); }
    .stat-card.invested::before { background: #f59e0b; }
    .stat-card.roi::before { background: #8b5cf6; }
    .stat-card.balance::before { background: #0ea5e9; }
    .stat-card.trans::before { background: #64748b; }

    .stat-top {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 0.6rem;
    }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft);
    }

    .stat-icon {
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem;
    }
    .ico-green { background: #ecfdf5; color: #10b981; }
    .ico-red { background: #fef2f2; color: #ef4444; }
    .ico-blue { background: var(--accent-bg); color: var(--accent); }
    .ico-orange { background: #fffbeb; color: #f59e0b; }
    .ico-purple { background: #f5f3ff; color: #8b5cf6; }
    .ico-cyan { background: #ecfeff; color: #0ea5e9; }
    .ico-gray { background: #f8fafc; color: #64748b; }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
        margin-bottom: 0.4rem;
    }
    .stat-value.green { color: #10b981; }
    .stat-value.red { color: #ef4444; }
    .stat-value.blue { color: var(--accent); }
    .stat-value.orange { color: #f59e0b; }
    .stat-value.purple { color: #8b5cf6; }
    .stat-value.cyan { color: #0ea5e9; }

    .stat-sub {
        font-size: 0.72rem; color: var(--ink-mute);
    }

    /* ── View Tabs ───────────────────────────── */
    .view-tabs {
        display: inline-flex; background: #fdfcfa; padding: 0.3rem;
        border-radius: 10px; border: 1.5px solid var(--border-lt);
        margin-bottom: 1.75rem; gap: 0.25rem;
    }

    .view-tab {
        padding: 0.65rem 1.2rem; border-radius: 8px;
        font-weight: 600; font-size: 0.82rem; color: var(--ink-soft);
        border: none; background: transparent; cursor: pointer;
        transition: all 0.18s; display: flex; align-items: center; gap: 0.5rem;
    }
    .view-tab:hover { color: var(--ink); background: white; }
    .view-tab.active {
        background: white; color: var(--accent);
        box-shadow: 0 1px 3px rgba(26,23,20,0.08);
    }

    /* ── Breakdown Grid ──────────────────────── */
    .breakdown-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .breakdown-grid { grid-template-columns: 1fr; } }

    .category-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.4s ease both;
    }

    .panel-head {
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa; display: flex; align-items: center; gap: 0.6rem;
    }
    .panel-head h3 {
        font-family: 'Fraunces', serif; font-size: 0.95rem;
        font-weight: 600; color: var(--ink); margin: 0;
    }
    .panel-bar {
        width: 3px; height: 18px; border-radius: 2px;
    }
    .bar-green { background: #10b981; }
    .bar-red { background: #ef4444; }

    .panel-body { padding: 0; }

    .cat-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 1.5rem; border-bottom: 1px solid var(--border-lt);
        transition: background 0.13s;
    }
    .cat-item:last-child { border-bottom: none; }
    .cat-item:hover { background: #fdfcfa; }

    .cat-left { display: flex; align-items: center; gap: 0.4rem; }
    .cat-name { font-weight: 600; color: var(--ink); font-size: 0.82rem; }
    .cat-count { font-size: 0.72rem; color: var(--ink-mute); }

    .cat-total {
        background: #fdfcfa; padding: 1rem 1.5rem;
        display: flex; justify-content: space-between; align-items: center;
        border-top: 1.5px solid var(--border);
    }
    .cat-total .cat-name { text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.05em; }

    /* Empty state */
    .empty-panel {
        padding: 3rem 1rem; text-align: center;
    }
    .empty-panel i {
        font-size: 2rem; color: var(--border);
        margin-bottom: 0.5rem; display: block; opacity: 0.5;
    }
    .empty-panel p {
        font-size: 0.82rem; color: var(--ink-mute); margin: 0;
    }

    /* ── Daily Table ─────────────────────────── */
    .daily-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .daily-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .daily-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .daily-table thead th.th-r { text-align: right; }

    .daily-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .daily-table tbody tr:last-child { border-bottom: none; }
    .daily-table tbody tr:hover { background: #fdfcfa; }

    .daily-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .daily-table td.td-r { text-align: right; }

    .daily-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.4s ease both;
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="fin-wrap">

    <!-- Header -->
    <div class="fin-header">
        <div>
            <div class="eyebrow">Financial Analysis</div>
            <h1>Financial <em>Overview</em></h1>
        </div>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=financial_overview&format=excel&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-dl">
                <i class="fas fa-file-excel" style="color:#10b981"></i> Excel
            </a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=financial_overview&format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-dl">
                <i class="fas fa-file-csv" style="color:#0ea5e9"></i> CSV
            </a>
            <button class="btn-dl" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="f-group">
                <label class="f-label">Date Range</label>
                <div class="date-range">
                    <input type="date" name="date_from" class="f-input" value="<?= htmlspecialchars($date_from) ?>">
                    <span>to</span>
                    <input type="date" name="date_to" class="f-input" value="<?= htmlspecialchars($date_to) ?>">
                </div>
            </div>
            
            <div class="f-group">
                <label class="f-label">Project</label>
                <select name="project" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply
            </button>
        </form>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card income">
            <div class="stat-top">
                <div class="stat-label">Total Income</div>
                <div class="stat-icon ico-green"><i class="fas fa-arrow-down"></i></div>
            </div>
            <div class="stat-value green"><?= formatCurrencyShort($total_income) ?></div>
            <div class="stat-sub"><?= count($income_data) ?> transactions</div>
        </div>

        <div class="stat-card expense">
            <div class="stat-top">
                <div class="stat-label">Total Expenditure</div>
                <div class="stat-icon ico-red"><i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-value red"><?= formatCurrencyShort($total_expenditure) ?></div>
            <div class="stat-sub"><?= count($expenditure_data) ?> transactions</div>
        </div>

        <div class="stat-card profit">
            <div class="stat-top">
                <div class="stat-label">Net Profit</div>
                <div class="stat-icon ico-blue"><i class="fas fa-wallet"></i></div>
            </div>
            <div class="stat-value <?= $net_profit >= 0 ? 'blue' : 'red' ?>"><?= formatCurrencyShort($net_profit) ?></div>
            <div class="stat-sub">
                <?= $total_income > 0 ? number_format(($net_profit / $total_income) * 100, 1) : 0 ?>% Net Margin
            </div>
        </div>

        <div class="stat-card invested">
            <div class="stat-top">
                <div class="stat-label">Total Invested</div>
                <div class="stat-icon ico-orange"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-value orange"><?= formatCurrencyShort($total_invested) ?></div>
            <div class="stat-sub">Capital employed</div>
        </div>

        <?php if ($total_invested > 0): ?>
        <div class="stat-card roi">
            <div class="stat-top">
                <div class="stat-label">ROI</div>
                <div class="stat-icon ico-purple" title="ROI = (Net Profit / Total Invested) * 100"><i class="fas fa-percentage"></i></div>
            </div>
            <div class="stat-value purple"><?= number_format($roi, 1) ?>%</div>
            <div class="stat-sub">Returns on capital</div>
        </div>
        <?php endif; ?>

        <div class="stat-card balance">
            <div class="stat-top">
                <div class="stat-label">Cash Balance</div>
                <div class="stat-icon ico-cyan"><i class="fas fa-coins"></i></div>
            </div>
            <div class="stat-value cyan"><?= formatCurrencyShort($cash_balance) ?></div>
            <div class="stat-sub">Current position</div>
        </div>

        <div class="stat-card trans">
            <div class="stat-top">
                <div class="stat-label">Total Volume</div>
                <div class="stat-icon ico-gray"><i class="fas fa-exchange-alt"></i></div>
            </div>
            <div class="stat-value"><?= count($income_data) + count($expenditure_data) ?></div>
            <div class="stat-sub">Transaction count</div>
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
    </div>

    <!-- Content Area -->
    <?php if ($view_mode === 'summary' || $view_mode === 'category'): ?>
        <div class="breakdown-grid">
            
            <!-- Income Breakdown -->
            <div class="category-panel">
                <div class="panel-head">
                    <div class="panel-bar bar-green"></div>
                    <h3>Income Breakdown</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($income_by_category)): ?>
                        <div class="empty-panel">
                            <i class="fas fa-inbox"></i>
                            <p>No income data</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($income_by_category as $category => $data): ?>
                            <div class="cat-item">
                                <div class="cat-left">
                                    <span class="cat-name"><?= htmlspecialchars($category) ?></span>
                                    <span class="cat-count">(<?= $data['count'] ?>)</span>
                                </div>
                                <span style="font-weight:700;color:#10b981" title="<?= formatCurrencyIndian($data['amount']) ?>"><?= formatCurrencyShort($data['amount']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="cat-total" style="background:#ecfdf5">
                            <span class="cat-name" style="color:#065f46">Total Income</span>
                            <span style="font-size:1.1rem;font-family:'Fraunces',serif;font-weight:800;color:#10b981" title="<?= formatCurrencyIndian($total_income) ?>"><?= formatCurrencyShort($total_income) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expenditure Breakdown -->
            <div class="category-panel">
                <div class="panel-head">
                    <div class="panel-bar bar-red"></div>
                    <h3>Expenditure Breakdown</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($expenditure_by_category)): ?>
                        <div class="empty-panel">
                            <i class="fas fa-inbox"></i>
                            <p>No expenditure data</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($expenditure_by_category as $category => $data): ?>
                            <div class="cat-item">
                                <div class="cat-left">
                                    <span class="cat-name"><?= htmlspecialchars($category) ?></span>
                                    <span class="cat-count">(<?= $data['count'] ?>)</span>
                                </div>
                                <span style="font-weight:700;color:#ef4444" title="<?= formatCurrencyIndian($data['amount']) ?>"><?= formatCurrencyShort($data['amount']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="cat-total" style="background:#fef2f2">
                            <span class="cat-name" style="color:#b91c1c">Total Expenditure</span>
                            <span style="font-size:1.1rem;font-family:'Fraunces',serif;font-weight:800;color:#ef4444" title="<?= formatCurrencyIndian($total_expenditure) ?>"><?= formatCurrencyShort($total_expenditure) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <?php if ($view_mode === 'daily'): ?>
        <div class="daily-panel">
            <div style="overflow-x:auto">
                <table class="daily-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th class="th-r">Inflow</th>
                            <th class="th-r">Outflow</th>
                            <th class="th-r">Net Flow</th>
                            <th class="th-r">Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily_cashflow)): ?>
                            <tr>
                                <td colspan="6" style="padding:3rem;text-align:center;color:var(--ink-mute)">
                                    No transactions found for this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daily_cashflow as $date => $flow): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--ink)"><?= formatDate($date) ?></td>
                                <td style="color:var(--ink-soft)"><?= date('l', strtotime($date)) ?></td>
                                <td class="td-r">
                                    <?php if ($flow['inflow'] > 0): ?>
                                        <span style="color:#10b981;font-weight:700">+ <?= formatCurrency($flow['inflow']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--border)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-r">
                                    <?php if ($flow['outflow'] > 0): ?>
                                        <span style="color:#ef4444;font-weight:700">- <?= formatCurrency($flow['outflow']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--border)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-r">
                                    <span style="font-weight:700;color:<?= $flow['net'] >= 0 ? '#10b981' : '#ef4444' ?>">
                                        <?= formatCurrency($flow['net']) ?>
                                    </span>
                                </td>
                                <td class="td-r">
                                    <span style="font-family:monospace;font-size:0.875rem;font-weight:700;color:var(--ink)">
                                        <?= formatCurrency($flow['balance']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>