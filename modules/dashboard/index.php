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

$project_filter = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$data = $reportService->getDashboardMetrics($project_filter);

// ── NEW ERP DATA MAPPING ──────────────────────────────
$total_sales        = $data['total_sales'];
$total_received     = $data['total_received'];
$total_receivables  = $data['total_receivables'];
$total_payables     = $data['total_payables'];

$total_units        = $data['total_units'];
$sold_units         = $data['sold_units'];
$available_units    = $data['available_units'];

$total_cash         = $data['total_cash'];
$total_expenses     = $data['total_expenses'];
$net_profit         = $data['net_profit'];

$monthly_stats      = $data['monthly_stats'];
$project_stats      = $data['project_stats'];
$recent_bookings    = $data['recent_bookings'];
$pending_approvals  = $data['pending_approvals'];
$project_cash_flow  = $data['project_cash_flow'] ?? [];

$sales_growth       = $data['sales_growth'] ?? 0;
$received_growth    = $data['received_growth'] ?? 0;
$expense_growth     = $data['expense_growth'] ?? 0;
$profit_growth      = $data['profit_growth'] ?? 0;
$pending_growth     = $data['pending_growth'] ?? 0;

$approvals_today    = $data['approvals_today'] ?? 0;

// Fetch all projects for the global dropdown
$all_projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

function getTrendClass($val) { return $val >= 0 ? 'positive' : 'negative'; }
function formatTrend($val) { return ($val > 0 ? '+' : '') . number_format($val, 1) . '%'; }

// Calculate Net Cash Position dynamically: Current Bank Balances + Expected Receivables - Pending Payables
$net_cash_position = $total_cash + $total_receivables - $total_payables;

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
        font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
    }
    
    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }

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
    <div class="dash-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div class="eyebrow">Overview</div>
            <h1>Welcome back, <em><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></em></h1>
            <div class="dash-subtitle">Here's what's happening with your projects today</div>
        </div>
        
        <!-- Global Project Filter -->
        <form method="GET" class="filter-form" style="display:flex; align-items:center; gap:0.5rem;">
            <label for="project_id" style="font-size:0.75rem; font-weight:700; color:var(--ink-soft); text-transform:uppercase; letter-spacing:0.05em;">Project Filter:</label>
            <select name="project_id" id="project_id" onchange="this.form.submit()" style="padding:0.5rem 2rem 0.5rem 1rem; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans'; font-size:0.85rem; font-weight:600; outline:none; cursor:pointer;">
                <option value="">All Projects (Company View)</option>
                <?php foreach ($all_projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $project_filter == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- ── Top Stats Grid (ERP View) ────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-blue" style="background:#eff6ff; color:#3b82f6;"><i class="fas fa-wallet"></i></div>
            </div>
            <div class="stat-label">Net Cash Position</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($net_cash_position) ?></span>
                <span class="full-val"><?= formatCurrency($net_cash_position) ?></span>
            </div>
            <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.3rem;">Cash + Receivables - Payables</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-teal" style="background:#ecfdf5; color:#10b981;"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-label">Outstanding Receivables</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($total_receivables) ?></span>
                <span class="full-val"><?= formatCurrency($total_receivables) ?></span>
            </div>
            <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.3rem;">Pending from Customers</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-orange" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-file-invoice-dollar"></i></div>
            </div>
            <div class="stat-label">Total Payables</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($total_payables) ?></span>
                <span class="full-val"><?= formatCurrency($total_payables) ?></span>
            </div>
            <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.3rem;">Pending Vendor/Contractor Bills</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="s-icon ico-purple" style="background:#f3e8ff; color:#a855f7;"><i class="fas fa-building"></i></div>
            </div>
            <div class="stat-label">Available Inventory</div>
            <div class="stat-value">
                <span class="short-val"><?= $available_units ?> Unsold</span>
                <span class="full-val"><?= $available_units ?> Unsold</span>
            </div>
            <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.3rem;">Out of <?= $total_units ?> total units</div>
        </div>
    </div>
    <!-- ── Chart Row ────────────────────────── -->
    <div class="chart-row">

        <!-- Project Cash Flow Chart -->
        <div class="chart-card">
            <div class="chart-head">
                <div class="ch-title-block">
                    <div class="ch-icon chi-blue"><i class="fas fa-chart-simple"></i></div>
                    <h2 class="ch-title">Project Cash Flow</h2>
                    <div class="ch-sub">Collections vs Expenditures</div>
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="leg-dot" style="background:#10b981"></span>
                        Collected
                    </div>
                    <div class="legend-item">
                        <span class="leg-dot" style="background:#ef4444"></span>
                        Spent
                    </div>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-canvas-wrap">
                    <canvas id="projectCashFlowChart"></canvas>
                </div>
                
                <?php
                $tot_coll = array_sum(array_column($project_cash_flow, 'total_collected'));
                $tot_spent = array_sum(array_column($project_cash_flow, 'total_expenses'));
                $net_flow = $tot_coll - $tot_spent;
                $p_count = count($project_cash_flow);
                $p_margin = $tot_coll > 0 ? ($net_flow / $tot_coll) * 100 : 0;
                $avg_flow = $p_count > 0 ? $tot_coll / $p_count : 0;
                ?>
                <div class="chart-footer-stats">
                    <div class="cfs-cell green">
                        <span class="cfs-label">Net Balance</span>
                        <span class="cfs-value"><?= formatCurrencyShort($net_flow) ?></span>
                        <span class="cfs-hint">
                            <?php if ($net_flow >= 0): ?>
                                <i class="fas fa-arrow-up"></i> Positive Flow
                            <?php else: ?>
                                <i class="fas fa-arrow-down"></i> Deficit
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="cfs-cell blue">
                        <span class="cfs-label">Avg Collected</span>
                        <span class="cfs-value"><?= formatCurrencyShort($avg_flow) ?></span>
                        <span class="cfs-hint">Per project average</span>
                    </div>
                    <div class="cfs-cell purple">
                        <span class="cfs-label">Flow Margin</span>
                        <span class="cfs-value"><?= number_format($p_margin, 1) ?>%</span>
                        <span class="cfs-hint">
                            <?php
                                if ($p_margin >= 20) echo 'Excellent margin';
                                elseif ($p_margin >= 10) echo 'Good margin';
                                else echo 'Average margin';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory & Alerts -->
        <div class="chart-card">
            <div class="chart-head">
                <div class="ch-title-block">
                    <div class="ch-icon chi-purple"><i class="fas fa-building"></i></div>
                    <h2 class="ch-title">Inventory Status</h2>
                    <div class="ch-sub">Sold vs Available Units</div>
                </div>
            </div>
            <div class="chart-body">
                <div class="doughnut-wrap">
                    <canvas id="inventoryChart"></canvas>
                    <div class="doughnut-center">
                        <span class="dc-val"><?= $total_units ?></span>
                        <span class="dc-label">Total Units</span>
                    </div>
                </div>

                <div class="project-list" style="margin-top: 1.5rem;">
                    <h3 style="font-size:0.85rem; font-weight:700; margin-bottom:0.8rem; color:var(--ink);">Actionable Alerts</h3>
                    <?php if (empty($pending_approvals)): ?>
                        <div style="font-size:0.8rem; color:var(--ink-mute); text-align:center; padding:1rem 0;">No pending approvals! You are all caught up.</div>
                    <?php else: ?>
                        <?php foreach ($pending_approvals as $pa): 
                            $icon = $pa['type'] === 'challan' ? 'file-invoice' : 'hard-hat';
                            $color = $pa['type'] === 'challan' ? '#f59e0b' : '#3b82f6';
                            $link = $pa['type'] === 'challan' ? "/builderz/modules/inventory/challans/edit.php?id={$pa['id']}" : "#";
                        ?>
                        <div class="proj-item">
                            <div class="proj-dot" style="background:<?= $color ?>; display:flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:6px; color:white; font-size:0.6rem;">
                                <i class="fas fa-<?= $icon ?>"></i>
                            </div>
                            <div class="proj-info">
                                <div class="proj-name">
                                    <a href="<?= $link ?>" style="color:var(--ink); text-decoration:none;"><?= htmlspecialchars($pa['challan_no']) ?></a>
                                </div>
                                <div class="proj-cat"><?= htmlspecialchars($pa['party_name']) ?></div>
                            </div>
                            <div class="proj-stats">
                                <span class="pst-amount"><?= formatCurrencyShort($pa['total_amount']) ?></span>
                                <span class="pst-perc" style="color:#ef4444; font-weight:600;">Pending</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mini-stats">
                    <div class="mini-stat">
                        <span class="ms-val" style="color:#10b981;"><?= $sold_units ?></span>
                        <span class="ms-lbl">Units Sold</span>
                    </div>
                    <div class="mini-stat">
                        <span class="ms-val" style="color:#ef4444;"><?= $available_units ?></span>
                        <span class="ms-lbl">Units Available</span>
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

    // ── Project Cash Flow Chart ──────────────────────────────
    <?php
    $pcf_labels = [];
    $pcf_collected = [];
    $pcf_spent = [];
    foreach ($project_cash_flow as $pcf) {
        $pcf_labels[] = addslashes($pcf['project_name']);
        $pcf_collected[] = $pcf['total_collected'];
        $pcf_spent[] = $pcf['total_expenses'];
    }
    ?>
    const ctxPCF = document.getElementById('projectCashFlowChart')?.getContext('2d');
    if (ctxPCF) {
        new Chart(ctxPCF, {
            type: 'bar',
            data: {
                labels: [<?= "'" . implode("','", $pcf_labels) . "'" ?>],
                datasets: [
                    {
                        label: 'Collected',
                        data: [<?= implode(',', $pcf_collected) ?>],
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.8,
                        hoverBackgroundColor: '#059669',
                        maxBarThickness: 10
                    },
                    {
                        label: 'Spent',
                        data: [<?= implode(',', $pcf_spent) ?>],
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.8,
                        hoverBackgroundColor: '#dc2626',
                        maxBarThickness: 10
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
    }

    // ── Inventory Doughnut ─────────────────────────────
    const ctxInv = document.getElementById('inventoryChart').getContext('2d');
    new Chart(ctxInv, {
        type: 'doughnut',
        data: {
            labels: ['Sold Units', 'Available Units'],
            datasets: [{
                data: [<?= $sold_units ?>, <?= $available_units ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 6,
                cutout: '72%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: true } },
            layout: { padding: 10 }
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>