<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ReportService.php';

requireAuth();
checkPermission(['admin', 'finance_manager']);

$page_title = 'Investment ROI Report';
$current_page = 'roi_report';

$reportService = new ReportService();

// Date Filters
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to = $_GET['date_to'] ?? date('Y-12-31');

$data = $reportService->getInvestmentROI($date_from, $date_to);
$projects = $data['projects'];

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
    .roi-wrap { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .roi-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .roi-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .roi-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .roi-header h1 em { color: var(--accent); font-style: italic; }
    .roi-header p {
        font-size: 0.82rem; color: var(--ink-mute); margin: 0.3rem 0 0;
    }

    /* Date Filter Form */
    .date-filter {
        display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
    }
    .date-filter input {
        height: 40px; padding: 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white;
        outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .date-filter input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(42,88,181,.1);
    }
    .btn-apply {
        height: 40px; padding: 0 1.3rem; border: none; border-radius: 8px;
        background: var(--ink); color: white; font-size: 0.875rem;
        font-weight: 600; cursor: pointer; transition: background .18s;
        display: flex; align-items: center; gap: 0.4rem;
    }
    .btn-apply:hover { background: var(--accent); }

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
        position: relative; overflow: hidden;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-card::before {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0;
        height: 3px; opacity: 0.8;
    }
    .stat-card.invested::before { background: var(--accent); }
    .stat-card.returned::before { background: #10b981; }
    .stat-card.profit::before { background: #8b5cf6; }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.5rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
        margin-bottom: 0.3rem;
    }
    
    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }
    .stat-value.blue { color: var(--accent); }
    .stat-value.green { color: #10b981; }
    .stat-value.red { color: #ef4444; }
    .stat-value.purple { color: #8b5cf6; }

    .stat-sub {
        font-size: 0.78rem; color: var(--ink-mute); font-weight: 500;
    }

    /* ── Chart Card ──────────────────────────── */
    .chart-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden; margin-bottom: 1.75rem;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    .chart-head {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .chart-icon {
        width: 32px; height: 32px; background: #10b981; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .chart-title {
        font-family: 'Fraunces', serif; font-size: 0.95rem;
        font-weight: 600; color: var(--ink);
    }

    .chart-body { padding: 1.75rem; background: white; }

    /* ── Table Card ──────────────────────────── */
    .table-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.25s ease both;
    }

    .table-head {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .table-icon {
        width: 32px; height: 32px; background: #8b5cf6; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .table-title {
        font-family: 'Fraunces', serif; font-size: 0.95rem;
        font-weight: 600; color: var(--ink);
    }

    /* Table */
    .roi-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .roi-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .roi-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .roi-table thead th.th-r { text-align: right; }

    .roi-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .roi-table tbody tr:last-child { border-bottom: none; }
    .roi-table tbody tr:hover { background: #fdfcfa; }

    .roi-table td { padding: 0.85rem 1rem; vertical-align: middle; }
    .roi-table td.td-r { text-align: right; }

    /* Empty state */
    .empty-state { padding: 3rem 1rem; text-align: center; }
    .empty-state i { font-size: 2.5rem; color: var(--border); margin-bottom: 0.75rem; display: block; }
    .empty-state h4 { font-size: 1rem; font-weight: 700; color: var(--ink-soft); margin: 0 0 0.3rem; }
    .empty-state p { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="roi-wrap">

    <!-- Header -->
    <div class="roi-header">
        <div>
            <div class="eyebrow">Financial Analytics</div>
            <h1>Investment ROI <em>Report</em></h1>
            <p>Analyze returns and project profitability</p>
        </div>
        <form method="GET" class="date-filter">
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            <span style="color:var(--ink-mute);font-size:0.85rem">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            <button type="submit" class="btn-apply">
                <i class="fas fa-filter"></i> Apply
            </button>
        </form>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card invested">
            <div class="stat-label">Total Invested</div>
            <div class="stat-value blue">
                <span class="short-val"><?= formatCurrencyShort($data['total_invested']) ?></span>
                <span class="full-val"><?= formatCurrency($data['total_invested']) ?></span>
            </div>
            <div class="stat-sub">Capital deployed</div>
        </div>

        <div class="stat-card returned">
            <div class="stat-label">Total Returned</div>
            <div class="stat-value green">
                <span class="short-val"><?= formatCurrencyShort($data['total_returned']) ?></span>
                <span class="full-val"><?= formatCurrency($data['total_returned']) ?></span>
            </div>
            <div class="stat-sub">Revenue generated</div>
        </div>

        <div class="stat-card profit">
            <?php 
                $net_profit = $data['total_returned'] - $data['total_invested'];
                $total_roi = $data['total_invested'] > 0 ? ($net_profit / $data['total_invested']) * 100 : 0;
                $profit_class = $net_profit >= 0 ? 'green' : 'red';
            ?>
            <div class="stat-label">Net Profit / ROI</div>
            <div class="stat-value <?= $profit_class ?>">
                <span class="short-val"><?= formatCurrencyShort($net_profit) ?></span>
                <span class="full-val"><?= formatCurrency($net_profit) ?></span>
            </div>
            <div class="stat-sub">
                <span style="font-weight:700;color:<?= $net_profit >= 0 ? '#10b981' : '#ef4444' ?>">
                    <?= number_format($total_roi, 1) ?>% ROI
                </span>
            </div>
        </div>
    </div>

    <!-- Chart Card -->
    <div class="chart-card">
        <div class="chart-head">
            <div class="chart-icon"><i class="fas fa-chart-line"></i></div>
            <div class="chart-title">Monthly Returns Trend</div>
        </div>
        <div class="chart-body">
            <div style="height:350px;position:relative">
                <canvas id="roiChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-head">
            <div class="table-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="table-title">Project-wise Performance</div>
        </div>

        <div style="overflow-x:auto">
            <table class="roi-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th class="th-r">Invested</th>
                        <th class="th-r">Returned</th>
                        <th class="th-r">Net Profit</th>
                        <th class="th-r">ROI %</th>
                        <th class="th-r">Ann. ROI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-chart-bar"></i>
                                    <h4>No investment data</h4>
                                    <p>No investment records found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $proj): ?>
                        <tr>
                            <td>
                                    <?= renderProjectBadge($proj['project_name'], $proj['project_id']) ?>
                            </td>
                            <td class="td-r">
                                <span style="font-weight:600;color:var(--ink-soft)">
                                    <?= formatCurrencyShort($proj['total_invested']) ?>
                                </span>
                            </td>
                            <td class="td-r">
                                <span style="font-weight:600;color:#10b981">
                                    <?= formatCurrencyShort($proj['total_returned']) ?>
                                </span>
                            </td>
                            <td class="td-r">
                                <span style="font-weight:700;color:<?= $proj['net_profit'] >= 0 ? '#10b981' : '#ef4444' ?>">
                                    <?= formatCurrencyShort($proj['net_profit']) ?>
                                </span>
                            </td>
                            <td class="td-r">
                                <span style="font-family:'Fraunces',serif;font-weight:700;font-size:0.9rem;color:var(--ink)">
                                    <?= number_format($proj['roi_percentage'], 1) ?>%
                                </span>
                            </td>
                            <td class="td-r">
                                <span style="color:var(--ink-mute);font-weight:500">
                                    <?= number_format($proj['annualized_roi'], 1) ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('roiChart');
    if (!ctx) return;
    
    // Gradient
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($data['chart_labels']) ?>,
            datasets: [{
                label: 'Monthly Returns',
                data: <?= json_encode($data['chart_data']) ?>,
                borderColor: '#10b981',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26, 23, 20, 0.95)',
                    titleColor: '#f5f3ef',
                    bodyColor: '#f5f3ef',
                    borderColor: '#e8e3db',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            return 'Returns: ₹ ' + context.parsed.y.toLocaleString('en-IN');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { 
                        color: '#f0ece5',
                        lineWidth: 1
                    },
                    ticks: { 
                        color: '#9e9690',
                        font: { size: 11 },
                        callback: function(val) {
                            if (val >= 1000000) return '₹ ' + (val/1000000).toFixed(1) + 'M';
                            if (val >= 1000) return '₹ ' + (val/1000).toFixed(0) + 'K';
                            return '₹ ' + val;
                        }
                    }
                },
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        color: '#9e9690',
                        font: { size: 11 }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>