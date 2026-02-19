<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Payment Demands';
$current_page = 'demands';

// Fetch all projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

// Fetch all unique stages for filter
$stages = $db->query("SELECT DISTINCT stage_name FROM booking_demands ORDER BY stage_name")->fetchAll(PDO::FETCH_COLUMN);

$project_id = $_GET['project_id'] ?? '';
$stage_filter = $_GET['stage'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($project_id)) {
    $where_conditions[] = "b.project_id = ?";
    $params[] = $project_id;
}

if (!empty($stage_filter)) {
    $where_conditions[] = "bd.stage_name = ?";
    $params[] = $stage_filter;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Fetch Demands
$sql = "SELECT bd.*, b.customer_id, b.project_id, p.name as customer_name, f.flat_no, pr.project_name
        FROM booking_demands bd
        JOIN bookings b ON bd.booking_id = b.id
        JOIN parties p ON b.customer_id = p.id
        JOIN flats f ON b.flat_id = f.id
        JOIN projects pr ON b.project_id = pr.id
        $where_clause
        AND b.status != 'cancelled'
        AND bd.status != 'paid'
        ORDER BY bd.generated_date DESC";

$demands = $db->query($sql, $params)->fetchAll();

// Calculate totals
$total_demanded = 0;
$total_paid = 0;
$total_balance = 0;

foreach ($demands as $d) {
    $total_demanded += $d['demand_amount'];
    $total_paid += $d['paid_amount'];
    $total_balance += ($d['demand_amount'] - $d['paid_amount']);
}

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
    .pd-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .pd-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
    }

    .pd-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .pd-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .pd-header h1 em { color: var(--accent); font-style: italic; }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 720px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.1rem 1.3rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .s-icon {
        width: 36px; height: 36px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; margin-bottom: 0.75rem;
    }
    .ico-orange { background: var(--accent-lt); color: var(--accent); }
    .ico-green  { background: #ecfdf5; color: #10b981; }
    .ico-red    { background: #fef2f2; color: #ef4444; }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
    }

    /* ── Main Panel ──────────────────────────── */
    .pd-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    /* ── Toolbar ─────────────────────────────── */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: var(--accent); border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-left: 0.4rem; }
    .toolbar-div { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; flex: 1; justify-content: flex-end; flex-wrap: nowrap; }

    /* Filter */
    .filter-wrap {
        display: inline-flex;
        align-items: center;
        gap: 18px;
        padding: 0 20px;
        height: 44px;
        background: var(--accent-bg);
        border: 1.5px solid #e0c9b5;
        border-radius: 12px;
    }

    /* Left side (icon + FILTER text) */
    .filter-left {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
    }

    /* Select */
    .filter-select {
        border: none;
        background: transparent;
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        cursor: pointer;
        outline: none;
        appearance: none;
        padding-right: 18px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23b5622a' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right center;
    }

    @media (max-width: 920px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div { display: none; }
        .toolbar-actions { width: 100%; justify-content: flex-start; }
    }

    /* ── Table ───────────────────────────────── */
    .pd-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .pd-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .pd-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .pd-table thead th.th-r { text-align: right; }

    .pd-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .pd-table tbody tr:last-child { border-bottom: none; }
    .pd-table tbody tr:hover { background: #fdfcfa; }

    .pd-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .pd-table td.td-r { text-align: right; }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
    }
    .pill.blue   { background: #eff6ff; color: #1e40af; }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.orange { background: var(--accent-lt); color: var(--accent); }
    .pill.red    { background: #fef2f2; color: #b91c1c; }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; justify-content: flex-end; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.print:hover { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }

    /* Empty state */
    .empty-state {
        padding: 4rem 1rem; text-align: center;
    }
    .empty-state i {
        font-size: 2.5rem; color: var(--border);
        margin-bottom: 0.75rem; display: block;
    }
    .empty-state h4 {
        font-size: 1rem; font-weight: 700; color: var(--ink-soft);
        margin: 0 0 0.35rem;
    }
    .empty-state p {
        font-size: 0.82rem; color: var(--ink-mute); margin: 0;
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="pd-wrap">

    <!-- Header -->
    <div class="pd-header">
        <div class="eyebrow">Outstanding Requests</div>
        <h1>Payment <em>Demands</em></h1>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="s-icon ico-orange"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-label">Total Demanded</div>
            <div class="stat-value"><?= formatCurrencyShort($total_demanded) ?></div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Total Paid</div>
            <div class="stat-value"><?= formatCurrencyShort($total_paid) ?></div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-red"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Total Balance</div>
            <div class="stat-value"><?= formatCurrencyShort($total_balance) ?></div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="pd-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-list-alt"></i></div>
                <div>
                    <div class="toolbar-title">All Demands</div>
                    <span class="toolbar-subtitle">Direct payment requests to customers</span>
                </div>
            </div>
            <div class="toolbar-div"></div>

            <div class="toolbar-actions">
                    <form method="GET" class="filter-wrap">
                        <div class="filter-left">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </div>

                        <select name="project_id" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Projects</option>
                            <?php foreach($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Stage Filter -->
                        <div style="width:1px; height:24px; background:#e0c9b5; margin:0 5px;"></div>
                        
                        <select name="stage" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Stages</option>
                            <?php foreach($stages as $stg): ?>
                                <option value="<?= htmlspecialchars($stg) ?>" <?= $stage_filter === $stg ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($stg) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer / Flat</th>
                        <th>Stage Name</th>
                        <th class="th-r">Amt Demanded</th>
                        <th class="th-r">Paid</th>
                        <th class="th-r">Balance</th>
                        <th>Status</th>
                        <th class="th-r">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($demands)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No demands generated yet</h4>
                                    <p>Payment demands will appear here once construction milestones are marked complete.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($demands as $d): 
                            $balance = $d['demand_amount'] - $d['paid_amount'];
                        ?>
                        <tr>
                            <td><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= date('d M Y', strtotime($d['generated_date'])) ?></span></td>
                            <td>
                                <div style="font-weight:700;color:var(--ink);font-size:0.875rem"><?= htmlspecialchars($d['customer_name']) ?></div>
                                <div style="margin-top:0.35rem; display:flex; align-items:center; gap:6px;">
                                    <?= renderProjectBadge($d['project_name'], $d['project_id']) ?>
                                    <span style="font-size:0.75rem;color:var(--ink-mute);"><?= htmlspecialchars($d['flat_no']) ?></span>
                                </div>
                            </td>
                            <td><span class="pill blue"><?= htmlspecialchars($d['stage_name']) ?></span></td>
                            <td class="td-r"><strong style="font-weight:700;color:var(--ink)"><?= formatCurrencyShort($d['demand_amount']) ?></strong></td>
                            <td class="td-r"><span style="font-weight:600;color:#10b981"><?= formatCurrencyShort($d['paid_amount']) ?></span></td>
                            <td class="td-r">
                                <?php if ($balance > 0): ?>
                                    <span style="font-weight:600;color:#ef4444"><?= formatCurrencyShort($balance) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--border)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['status'] == 'pending'): ?>
                                    <span class="pill red">Pending</span>
                                <?php elseif ($d['status'] == 'partial'): ?>
                                    <span class="pill orange">Partial</span>
                                <?php else: ?>
                                    <span class="pill green">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-r">
                                <div class="act-group">
                                    <a href="<?= BASE_URL ?>modules/booking/print_demand.php?id=<?= $d['id'] ?>" 
                                       target="_blank" class="act-btn print" title="Print Demand Letter">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>modules/booking/view.php?id=<?= $d['booking_id'] ?>" 
                                       class="act-btn" title="View Booking">
                                        <i class="fas fa-eye"></i>
                                    </a>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>