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

require_once __DIR__ . '/../../includes/ReportService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';
$reportService = new ReportService();

$data = $reportService->getDashboardMetrics();

$total_sales     = $data['total_sales'];
$total_received  = $data['total_received'];
$total_pending   = $data['total_pending'];
$total_cancelled = $data['total_cancelled'];
$total_expenses  = $data['total_expenses'];
$total_invested  = $data['total_invested'] ?? 0;
$net_profit      = $data['net_profit'];
$monthly_stats   = $data['monthly_stats'];
$project_stats   = $data['project_stats'];
$recent_bookings = $data['recent_bookings'];
$pending_approvals = $data['pending_approvals'];

$sales_growth    = $data['sales_growth'] ?? 0;
$received_growth = $data['received_growth'] ?? 0;
$expense_growth  = $data['expense_growth'] ?? 0;
$profit_growth   = $data['profit_growth'] ?? 0;
$pending_growth  = $data['pending_growth'] ?? 0;

$approvals_today = $data['approvals_today'] ?? 0;

function getTrendClass($val) { return $val >= 0 ? 'positive' : 'negative'; }
function formatTrend($val) { return ($val > 0 ? '+' : '') . number_format($val, 1) . '%'; }

$total_income_yr  = array_sum(array_column($monthly_stats, 'income'));
$total_expense_yr = array_sum(array_column($monthly_stats, 'expense'));
$net_income_yr    = $total_income_yr - $total_expense_yr;
$profit_margin    = $total_income_yr > 0 ? ($net_income_yr / $total_income_yr) * 100 : 0;
$months           = count($monthly_stats);
$avg_monthly      = $months > 0 ? $total_income_yr / $months : 0;

$total_project_sales_calc = array_sum(array_column($project_stats, 'total_sales'));

include __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    *, *::before, *::after { box-sizing: border-box; }

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

    body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

    /* ── Wrapper ─────────────────────────────── */
    .dash-wrap { max-width: 1320px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Page Header ─────────────────────────── */
    .dash-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
    }
    .dash-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .dash-header h1 {
        font-family: 'Fraunces', serif; font-size: 2.2rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0 0 0.5rem;
    }
    .dash-header h1 em { color: var(--accent); font-style: italic; }
    .dash-subtitle { font-size: 0.875rem; color: var(--ink-soft); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 1000px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px)  { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.4rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:nth-child(4) { animation-delay: .2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.8rem; }

    .s-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; flex-shrink: 0;
    }
    .ico-orange { background: var(--accent-lt); color: var(--accent); }
    .ico-purple { background: #f3e8ff; color: #9333ea; }
    .ico-blue   { background: #eff6ff; color: #3b82f6; }
    .ico-teal   { background: #ccfbf1; color: #14b8a6; }

    .trend-pill {
        display: inline-flex; align-items: center; gap: 0.25rem;
        padding: 0.22rem 0.55rem; border-radius: 20px;
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em;
    }
    .trend-pill.positive { background: #ecfdf5; color: #065f46; }
    .trend-pill.negative { background: #fef2f2; color: #991b1b; }

    .stat-label {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.75rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
    }

    /* ── Chart Cards ─────────────────────────── */
    .chart-row {
        display: grid; grid-template-columns: 2fr 1fr;
        gap: 1.25rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .chart-row { grid-template-columns: 1fr; } }

    .chart-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        display: flex; flex-direction: column;
        animation: fadeUp 0.45s 0.25s ease both;
    }

    .chart-head {
        display: flex; align-items: flex-start; justify-content: space-between;
        padding: 1.2rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa; gap: 1rem; flex-wrap: wrap;
    }

    .ch-title-block {}
    .ch-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; margin-bottom: 0.5rem;
    }
    .chi-blue   { background: #3b82f6; color: white; }
    .chi-purple { background: #a855f7; color: white; }

    .ch-title {
        font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 600;
        color: var(--ink); margin: 0 0 0.2rem;
    }
    .ch-sub { font-size: 0.75rem; color: var(--ink-mute); }

    .chart-legend {
        display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;
    }
    .legend-item {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.75rem; font-weight: 600; color: var(--ink-soft);
    }
    .leg-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .leg-val { font-weight: 800; color: var(--ink); margin-left: 0.2rem; }

    .chart-body { padding: 1.5rem 1.6rem; flex: 1; }

    .chart-canvas-wrap { position: relative; min-height: 240px; }

    /* ── Footer Stats (below chart) ──────────── */
    .chart-footer-stats {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1rem; margin-top: 1.5rem;
    }
    .cfs-cell {
        padding: 1rem 1.2rem; border-radius: 10px; border: 1px solid var(--border);
    }
    .cfs-cell.green  { background: #ecfdf5; border-color: #a7f3d0; }
    .cfs-cell.blue   { background: #eff6ff; border-color: #bfdbfe; }
    .cfs-cell.purple { background: #f3e8ff; border-color: #ddd6fe; }

    .cfs-label {
        display: block; font-size: 0.68rem; font-weight: 700;
        letter-spacing: 0.08em; text-transform: uppercase;
        margin-bottom: 0.4rem;
    }
    .cfs-cell.green  .cfs-label { color: #059669; }
    .cfs-cell.blue   .cfs-label { color: #2563eb; }
    .cfs-cell.purple .cfs-label { color: #9333ea; }

    .cfs-value {
        display: block; font-family: 'Fraunces', serif;
        font-size: 1.3rem; font-weight: 700; color: var(--ink);
        margin-bottom: 0.2rem;
    }
    .cfs-hint {
        font-size: 0.7rem; font-weight: 500;
    }
    .cfs-cell.green  .cfs-hint { color: #059669; }
    .cfs-cell.blue   .cfs-hint { color: #3b82f6; }
    .cfs-cell.purple .cfs-hint { color: #9333ea; }

    /* ── Doughnut Chart Card ─────────────────── */
    .doughnut-wrap { position: relative; height: 200px; margin: 1.25rem 0; }
    .doughnut-center {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        text-align: center; pointer-events: none;
    }
    .doughnut-center .dc-val {
        display: block; font-family: 'Fraunces', serif;
        font-size: 1.5rem; font-weight: 700; color: var(--ink);
    }
    .doughnut-center .dc-label {
        font-size: 0.7rem; color: var(--ink-mute); font-weight: 500;
    }

    /* ── Project List ────────────────────────── */
    .project-list { margin-top: auto; }
    .proj-item {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.9rem; border-radius: 10px; margin-bottom: 0.7rem;
        border: 1px solid var(--border); background: #fdfcfa;
    }
    .proj-item:last-child { margin-bottom: 0; }

    .proj-dot {
        width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
    }
    .proj-info { flex: 1; min-width: 0; }
    .proj-name {
        font-size: 0.82rem; font-weight: 700; color: var(--ink);
        margin: 0 0 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .proj-cat { font-size: 0.68rem; color: var(--ink-mute); }

    .proj-stats { text-align: right; flex-shrink: 0; }
    .pst-amount { display: block; font-weight: 700; font-size: 0.85rem; color: var(--ink); }
    .pst-perc   { font-size: 0.7rem; color: var(--ink-mute); }

    /* ── Mini stats row (below projects) ────── */
    .mini-stats {
        display: flex; justify-content: space-around; gap: 1rem;
        margin-top: 1.25rem; padding-top: 1.25rem;
        border-top: 1px solid var(--border-lt);
    }
    .mini-stat { text-align: center; }
    .mini-stat .ms-val {
        display: block; font-family: 'Fraunces', serif;
        font-size: 1.4rem; font-weight: 700; color: var(--ink);
    }
    .mini-stat .ms-lbl { font-size: 0.7rem; color: var(--ink-mute); }

    /* ── Animations ──────────────────────────── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="dash-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="dash-header">
        <div class="eyebrow">Overview</div>
        <h1>Welcome back, <em><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></em></h1>
        <div class="dash-subtitle">Here's what's happening with your projects today</div>
    </div>

    <!-- ── Top Stats Grid ───────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-orange"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-label">Total Invested</div>
            <div class="stat-value"><?= formatCurrencyShort($total_invested) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-purple"><i class="fas fa-file-invoice"></i></div>
                <span class="trend-pill <?= getTrendClass($expense_growth) ?>">
                    <?= $expense_growth >= 0 ? '↑' : '↓' ?> <?= formatTrend($expense_growth) ?>
                </span>
            </div>
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value"><?= formatCurrencyShort($total_expenses) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-blue"><i class="fas fa-indian-rupee-sign"></i></div>
                <span class="trend-pill <?= getTrendClass($sales_growth) ?>">
                    <?= $sales_growth >= 0 ? '↑' : '↓' ?> <?= formatTrend($sales_growth) ?>
                </span>
            </div>
            <div class="stat-label">Total Sales</div>
            <div class="stat-value"><?= formatCurrencyShort($total_sales) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-teal"><i class="fas fa-chart-bar"></i></div>
                <span class="trend-pill <?= getTrendClass($profit_growth) ?>">
                    <?= $profit_growth >= 0 ? '↑' : '↓' ?> <?= formatTrend($profit_growth) ?>
                </span>
            </div>
            <div class="stat-label">Net Profit</div>
            <div class="stat-value"><?= formatCurrencyShort($net_profit) ?></div>
        </div>
    </div>

    <!-- ── Chart Row ────────────────────────── -->
    <div class="chart-row">

        <!-- Cash Flow Chart -->
        <div class="chart-card">
            <div class="chart-head">
                <div class="ch-title-block">
                    <div class="ch-icon chi-blue"><i class="fas fa-chart-simple"></i></div>
                    <h2 class="ch-title">Monthly Cash Flow</h2>
                    <div class="ch-sub">Financial year <?= date('Y') ?></div>
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="leg-dot" style="background:#10b981"></span>
                        Income <span class="leg-val"><?= formatCurrencyShort($total_income_yr) ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="leg-dot" style="background:#ef4444"></span>
                        Expense <span class="leg-val"><?= formatCurrencyShort($total_expense_yr) ?></span>
                    </div>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-canvas-wrap">
                    <canvas id="cashFlowChart"></canvas>
                </div>

                <div class="chart-footer-stats">
                    <div class="cfs-cell green">
                        <span class="cfs-label">Net Income</span>
                        <span class="cfs-value"><?= formatCurrencyShort($net_income_yr) ?></span>
                        <span class="cfs-hint">
                            <?php if ($profit_growth >= 0): ?>
                                <i class="fas fa-arrow-up"></i> +<?= number_format($profit_growth, 1) ?>% growth
                            <?php else: ?>
                                <i class="fas fa-arrow-down"></i> <?= number_format($profit_growth, 1) ?>% decline
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="cfs-cell blue">
                        <span class="cfs-label">Avg Monthly</span>
                        <span class="cfs-value"><?= formatCurrencyShort($avg_monthly) ?></span>
                        <span class="cfs-hint">Per month average</span>
                    </div>
                    <div class="cfs-cell purple">
                        <span class="cfs-label">Profit Margin</span>
                        <span class="cfs-value"><?= number_format($profit_margin, 1) ?>%</span>
                        <span class="cfs-hint">
                            <?php
                                if ($profit_margin >= 20) echo 'Excellent margin';
                                elseif ($profit_margin >= 10) echo 'Good margin';
                                else echo 'Average margin';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales by Project -->
        <div class="chart-card">
            <div class="chart-head">
                <div class="ch-title-block">
                    <div class="ch-icon chi-purple"><i class="fas fa-chart-pie"></i></div>
                    <h2 class="ch-title">Sales by Project</h2>
                    <div class="ch-sub">Distribution overview</div>
                </div>
            </div>
            <div class="chart-body">
                <div class="doughnut-wrap">
                    <canvas id="projectChart"></canvas>
                    <div class="doughnut-center">
                        <span class="dc-val"><?= formatCurrencyShort($total_project_sales_calc) ?></span>
                        <span class="dc-label">Total Sales</span>
                    </div>
                </div>

                <div class="project-list">
                    <?php
                    $projLabels = []; $projValues = []; $projColors = [];
                    $idx = 0;
                    foreach ($project_stats as $proj):
                        $perc  = $total_project_sales_calc > 0 ? ($proj['total_sales'] / $total_project_sales_calc) * 100 : 0;
                        $color = ColorHelper::getProjectColor($proj['project_id']);
                        $projLabels[] = addslashes($proj['project_name']);
                        $projValues[] = $proj['total_sales'];
                        $projColors[] = $color;
                        $cat = ($idx == 0) ? 'Primary project' : 'Secondary project';
                        $idx++;
                    ?>
                    <div class="proj-item">
                        <div class="proj-dot" style="background:<?= $color ?>"></div>
                        <div class="proj-info">
                            <div class="proj-name"><?= htmlspecialchars($proj['project_name']) ?></div>
                            <div class="proj-cat"><?= $cat ?></div>
                        </div>
                        <div class="proj-stats">
                            <span class="pst-amount"><?= formatCurrencyShort($proj['total_sales']) ?></span>
                            <span class="pst-perc"><?= number_format($perc, 0) ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mini-stats">
                    <div class="mini-stat">
                        <span class="ms-val"><?= count($project_stats) ?></span>
                        <span class="ms-lbl">Active Projects</span>
                    </div>
                    <div class="mini-stat">
                        <span class="ms-val"><?= count($recent_bookings) ?></span>
                        <span class="ms-lbl">Total Bookings</span>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.chart-row -->

</div><!-- /.dash-wrap -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'DM Sans', 'Segoe UI', sans-serif";
    Chart.defaults.color = '#9e9690';

    const formatIndianShort = (num) => {
        if (num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
        if (num >= 100000)   return (num / 100000).toFixed(2) + ' L';
        if (num >= 1000)     return (num / 1000).toFixed(2) + ' K';
        return num.toFixed(0);
    };

    // ── Cash Flow Chart ──────────────────────────────
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
                    barPercentage: 0.55,
                    categoryPercentage: 0.8,
                    hoverBackgroundColor: '#059669'
                },
                {
                    label: 'Expense',
                    data: [<?= implode(',', array_column($monthly_stats, 'expense')) ?>],
                    backgroundColor: '#ef4444',
                    borderRadius: 6,
                    barPercentage: 0.55,
                    categoryPercentage: 0.8,
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
                    backgroundColor: '#1a1714',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 13, weight: 600 },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: ctx => {
                            let l = ctx.dataset.label || '';
                            if (l) l += ': ';
                            return l + '₹ ' + formatIndianShort(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0ece5', drawBorder: false },
                    ticks: {
                        color: '#9e9690',
                        font: { size: 11, weight: 500 },
                        callback: v => v === 0 ? '0' : formatIndianShort(v)
                    },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#9e9690', font: { size: 11, weight: 500 } },
                    border: { display: false }
                }
            }
        }
    });

    // ── Project Doughnut ─────────────────────────────
    const ctxProj = document.getElementById('projectChart').getContext('2d');
    new Chart(ctxProj, {
        type: 'doughnut',
        data: {
            labels: [<?= "'" . implode("','", $projLabels) . "'" ?>],
            datasets: [{
                data: [<?= implode(',', $projValues) ?>],
                backgroundColor: [<?= "'" . implode("','", $projColors) . "'" ?>],
                borderWidth: 0,
                hoverOffset: 6,
                cutout: '72%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            layout: { padding: 10 }
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>