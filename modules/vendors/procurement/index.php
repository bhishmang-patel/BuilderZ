<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Purchase Orders';
$current_page = 'procurement';

// Filters
$project_id = $_GET['project_id'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if ($project_id) {
    $where[] = "po.project_id = ?";
    $params[] = $project_id;
}
if ($status) {
    $where[] = "po.status = ?";
    $params[] = $status;
}
if ($search) {
    $where[] = "(po.po_number LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT po.*, pr.project_name, p.name as vendor_name 
        FROM purchase_orders po
        JOIN projects pr ON po.project_id = pr.id
        JOIN parties p ON po.vendor_id = p.id
        $whereClause
        ORDER BY po.id DESC";

$pos = $db->query($sql, $params)->fetchAll();

// Stats
$total_pos = count($pos);
$pending_pos = 0;
$approved_pos = 0;
$completed_pos = 0;

foreach ($pos as $po) {
    if ($po['status'] === 'pending') $pending_pos++;
    if ($po['status'] === 'approved') $approved_pos++;
    if ($po['status'] === 'completed') $completed_pos++;
}

$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    /* ── Reset & Base ─────────────────────────── */
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

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── Wrapper ─────────────────────────────── */
    .po-index-wrap {
        max-width: 1140px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .po-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        gap: 1rem;
        flex-wrap: wrap;
    }

    .po-page-header .eyebrow {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 0.3rem;
    }

    .po-page-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--ink);
        margin: 0;
    }

    .po-page-header h1 em {
        color: var(--accent);
        font-style: italic;
    }

    .btn-new-order {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.5rem;
        background: var(--ink);
        color: #fff;
        border-radius: 8px;
        text-decoration: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        transition: background 0.18s ease, transform 0.15s ease, box-shadow 0.18s ease;
        white-space: nowrap;
    }
    .btn-new-order:hover {
        background: var(--accent);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(181,98,42,0.28);
        color: #fff;
    }
    .btn-new-order:active { transform: translateY(0); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.75rem;
        animation: fadeUp 0.4s ease both;
    }

    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem 1.4rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        animation: fadeUp 0.4s ease both;
    }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.2s; }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26,23,20,0.07);
    }

    .stat-pip {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .pip-total    { background: #eef2ff; color: #4f63d2; }
    .pip-pending  { background: var(--accent-lt); color: var(--accent); }
    .pip-approved { background: #ecfdf5; color: #0f8f5a; }
    .pip-done     { background: #f0fdf4; color: #16a34a; }

    .stat-text {}
    .stat-label {
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--ink-soft);
        margin-bottom: 0.2rem;
    }
    .stat-value {
        font-family: 'Fraunces', serif;
        font-size: 1.65rem;
        font-weight: 700;
        color: var(--ink);
        line-height: 1;
        text-align: center;
    }

    /* ── Main Panel ──────────────────────────── */
    .po-panel {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    /* ── Panel Toolbar ───────────────────────── */
    .panel-toolbar {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
        flex-wrap: nowrap;
        overflow: hidden;
    }

    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-shrink: 0;
    }

    .toolbar-left .icon-box {
        width: 32px;
        height: 32px;
        background: var(--accent);
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.75rem;
        flex-shrink: 0;
    }

    .toolbar-left .panel-title {
        font-family: 'Fraunces', serif;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0;
        white-space: nowrap;
    }

    /* vertical divider */
    .toolbar-divider {
        width: 1.5px;
        height: 28px;
        background: var(--border);
        flex-shrink: 0;
    }

    .filter-form {
        display: flex;
        align-items: center;
        justify-content: right;
        gap: 0.5rem;
        flex: 1;
        min-width: 0;
    }

    .filter-select,
    .filter-input {
        height: 34px;
        padding: 0 0.75rem;
        border: 1.5px solid var(--border);
        border-radius: 7px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.8rem;
        color: var(--ink);
        background: var(--surface);
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        -webkit-appearance: none;
        appearance: none;
        min-width: 0;
    }

    .filter-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.6rem center;
        padding-right: 1.75rem;
        flex: 0 0 130px;
    }

    .filter-input {
        flex: 1 1 160px;
        max-width: 220px;
    }

    .filter-select:focus,
    .filter-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }

    .btn-filter-go,
    .btn-filter-clear {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.18s ease;
        flex-shrink: 0;
        text-decoration: none;
    }

    .btn-filter-go { background: var(--ink); color: white; }
    .btn-filter-go:hover { background: var(--accent); color: white; }

    .btn-filter-clear { background: #fee2e2; color: #b91c1c; }
    .btn-filter-clear:hover { background: #fca5a5; color: #7f1d1d; }

    @media (max-width: 820px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-divider { display: none; }
        .filter-form { width: 100%; flex-wrap: wrap; }
        .filter-select { flex: 1 1 120px; }
        .filter-input  { flex: 1 1 140px; max-width: 100%; }
    }

    /* ── Table ───────────────────────────────── */
    .po-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .po-table thead tr {
        background: #fdfcfa;
        border-bottom: 1.5px solid var(--border);
    }

    .po-table thead th {
        padding: 0.8rem 1.1rem;
        text-align: left;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-soft);
        white-space: nowrap;
    }
    .po-table thead th.th-r { text-align: right; }
    .po-table thead th.th-c { text-align: center; }

    .po-table tbody tr {
        border-bottom: 1px solid var(--border-lt);
        transition: background 0.14s ease;
    }
    .po-table tbody tr:last-child { border-bottom: none; }
    .po-table tbody tr:hover { background: #fdfcfa; }

    .po-table td {
        padding: 0.95rem 1.1rem;
        vertical-align: middle;
        color: var(--ink);
    }
    .po-table td.td-r { text-align: right; }
    .po-table td.td-c { text-align: center; }

    /* PO Number link */
    .po-num-link {
        font-weight: 700;
        color: var(--ink);
        text-decoration: none;
        font-size: 0.875rem;
        border-bottom: 1.5px solid transparent;
        transition: color 0.15s ease, border-color 0.15s ease;
    }
    .po-num-link:hover { color: var(--accent); border-bottom-color: var(--accent); }

    /* Date cell */
    .cell-date { color: var(--ink-soft); font-size: 0.82rem; }

    /* Project / vendor cell */
    .cell-primary { font-weight: 500; color: #2a2520; }
    .cell-sub { font-size: 0.75rem; color: var(--ink-mute); margin-top: 2px; }

    /* Ref */
    .cell-ref { color: var(--ink-mute); font-size: 0.82rem; font-variant-numeric: tabular-nums; }

    /* Amount */
    .cell-amount {
        font-weight: 700;
        color: var(--ink);
        font-variant-numeric: tabular-nums;
        font-family: 'Fraunces', serif;
        font-size: 0.95rem;
    }

    /* ── Status Badges ───────────────────────── */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.75rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .status-pill::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .pill-draft     { background: #f1f5f9; color: #64748b; }
    .pill-draft::before { background: #94a3b8; }

    .pill-pending   { background: var(--accent-lt); color: #a04d1e; }
    .pill-pending::before { background: var(--accent); }

    .pill-approved  { background: #ecfdf5; color: #065f46; }
    .pill-approved::before { background: #10b981; }

    .pill-rejected  { background: #fef2f2; color: #991b1b; }
    .pill-rejected::before { background: #ef4444; }

    .pill-completed { background: #eff6ff; color: #1e40af; }
    .pill-completed::before { background: #3b82f6; }

    /* ── Action Buttons ──────────────────────── */
    .action-group { display: flex; gap: 0.4rem; justify-content: flex-end; }

    .act-btn {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        text-decoration: none;
        border: 1.5px solid var(--border);
        background: var(--surface);
        color: var(--ink-soft);
        transition: all 0.16s ease;
        cursor: pointer;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .act-btn.print:hover { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }

    /* ── Empty State ─────────────────────────── */
    .empty-state {
        padding: 5rem 1rem;
        text-align: center;
    }
    .empty-state .empty-icon {
        font-size: 2.8rem;
        color: var(--border);
        margin-bottom: 1rem;
        display: block;
    }
    .empty-state p {
        font-size: 0.9rem;
        color: var(--ink-mute);
        margin: 0 0 1rem;
    }
    .empty-state .empty-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--accent);
        text-decoration: none;
        border-bottom: 1.5px solid var(--accent);
        padding-bottom: 1px;
        transition: opacity 0.15s ease;
    }
    .empty-state .empty-link:hover { opacity: 0.75; }

    /* ── Animations ──────────────────────────── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Responsive ──────────────────────────── */
    @media (max-width: 600px) {
        .po-page-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="po-index-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="po-page-header">
        <div>
            <div class="eyebrow">Procurement Module</div>
            <h1>Purchase <em>Orders</em></h1>
        </div>
        <a href="create.php" class="btn-new-order">
            <i class="fas fa-plus"></i> New Order
        </a>
    </div>

    <!-- ── Stats ────────────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-pip pip-total"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-text">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?= $total_pos ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-pending"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-text">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $pending_pos ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-approved"><i class="fas fa-check-circle"></i></div>
            <div class="stat-text">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= $approved_pos ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-done"><i class="fas fa-box-open"></i></div>
            <div class="stat-text">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $completed_pos ?></div>
            </div>
        </div>
    </div>

    <!-- ── Main Panel ────────────────────────── -->
    <div class="po-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="icon-box"><i class="fas fa-shopping-cart"></i></div>
                <div class="panel-title">All Purchase Orders</div>
            </div>

            <div class="toolbar-divider"></div>

            <form method="GET" class="filter-form">
                <select name="project_id" class="filter-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="approved"  <?= $status === 'approved'  ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected"  <?= $status === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>

                <input type="text" name="search" class="filter-input"
                       placeholder="Search PO # or vendor…"
                       value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="btn-filter-go" title="Search">
                    <i class="fas fa-search"></i>
                </button>

                <?php if ($project_id || $status || $search): ?>
                    <a href="index.php" class="btn-filter-clear" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x: auto;">
            <table class="po-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Date</th>
                        <th>Vendor/Project</th>
                        <th>Ref No</th>
                        <th class="th-c">Status</th>
                        <th class="th-c">Amount</th>
                        <th class="th-c">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pos)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <span class="empty-icon"><i class="fas fa-folder-open"></i></span>
                                    <p>No purchase orders found<?= ($project_id || $status || $search) ? ' matching your filters' : '' ?>.</p>
                                    <?php if ($project_id || $status || $search): ?>
                                        <a href="index.php" class="empty-link"><i class="fas fa-times"></i> Clear filters</a>
                                    <?php else: ?>
                                        <a href="create.php" class="empty-link"><i class="fas fa-plus"></i> Create your first order</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pos as $po): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $po['id'] ?>" class="po-num-link">
                                        <?= htmlspecialchars($po['po_number']) ?>
                                    </a>
                                </td>
                                <td class="cell-date">
                                    <?= date('d M Y', strtotime($po['order_date'])) ?>
                                </td>
                                <td>
                                    <div class="cell-primary"style="font-weight:700;color:var(--ink);font-size:0.875rem;"><?= htmlspecialchars($po['vendor_name']) ?></div>
                                    <?php if (!empty($po['vendor_mobile'])): ?>
                                        <div class="cell-sub"><?= htmlspecialchars($po['vendor_mobile']) ?></div>
                                    <?php endif; ?>
                                    <div class="cell-primary" style="margin-top:0.35rem; display:flex; align-items:center; gap:6px;"><?= renderProjectBadge($po['project_name'], $po['project_id']) ?></div>
                                </td>
                                <td class="cell-ref">
                                    <?= htmlspecialchars($po['reference_no'] ?? '—') ?>
                                </td>
                                <td class="td-c">
                                    <?php
                                        $s = $po['status'];
                                        $pillClass = match($s) {
                                            'draft'     => 'pill-draft',
                                            'pending'   => 'pill-pending',
                                            'approved'  => 'pill-approved',
                                            'rejected'  => 'pill-rejected',
                                            'completed' => 'pill-completed',
                                            default     => 'pill-draft'
                                        };
                                    ?>
                                    <span class="status-pill <?= $pillClass ?>">
                                        <?= ucfirst($s) ?>
                                    </span>
                                </td>
                                <td class="td-c">
                                    <span class="cell-amount"><?= formatCurrency($po['total_amount']) ?></span>
                                </td>
                                <td class="td-c">
                                    <div class="action-group">
                                        <a href="view.php?id=<?= $po['id'] ?>" class="act-btn" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($po['status'] === 'approved'): ?>
                                            <a href="print.php?id=<?= $po['id'] ?>" target="_blank" class="act-btn print" title="Print PDF">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /.po-panel -->

</div><!-- /.po-index-wrap -->

<?php include __DIR__ . '/../../../includes/footer.php'; ?>