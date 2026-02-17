<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$masterService = new MasterService();
$db = Database::getInstance();
$page_title = 'Work Orders';
$current_page = 'work_orders';

// Handle only updates/deletes if necessary (creation moved to create_work_order.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/contractors/work_orders.php');
    }
    
    // Future validation for edits/deletes can go here if we keep edit as modal or move it too
}

// Fetch Work Orders
$filters = [
    'project_id' => $_GET['project'] ?? '',
    'contractor_id' => $_GET['contractor'] ?? ''
];
$workOrders = $masterService->getAllWorkOrders($filters);

// Calculate Stats
$stats = $masterService->calculateWorkOrderStats($filters);

// Data for dropdowns
$projects = $masterService->getAllProjects();
$contractors = $masterService->getAllParties(['type' => 'contractor']);

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
    .wo-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .wo-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .wo-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .wo-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .wo-header h1 em { color: var(--accent); font-style: italic; }

    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink); cursor: pointer;
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.28); color: white; }

    /* ── Main Panel ──────────────────────────── */
    .wo-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.4s ease both;
    }

    /* ── Toolbar ─────────────────────────────── */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: #6366f1; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.2rem; }
    .toolbar-div { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    /* ── Stats Strip ─────────────────────────── */
    .stats-strip {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;
        margin-bottom: 2rem; animation: fadeUp 0.3s ease both;
    }
    @media (max-width: 900px) { .stats-strip { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 500px) { .stats-strip { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); padding: 1.25rem 1.5rem;
        border: 1px solid var(--border); border-radius: 12px;
        display: flex; flex-direction: column; gap: 0.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); border-color: var(--accent-lt); }

    .stat-title {
        font-size: 0.75rem; font-weight: 700; color: var(--ink-soft);
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .stat-value {
        font-size: 1.6rem; font-weight: 700; color: var(--ink);
        font-family: 'Fraunces', serif; line-height: 1.1;
    }
    .stat-icon {
        width: 36px; height: 36px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        background: var(--accent-bg); color: var(--accent);
        font-size: 1rem; margin-bottom: 0.25rem;
    }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; flex: 1; justify-content: flex-end; flex-wrap: wrap; }


    .filter-section {
        padding: 1.25rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

    .f-select {
        flex: 1; min-width: 180px; height: 42px; padding: 0 2.2rem 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s, box-shadow 0.15s;
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
    }
    .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }

    .btn-filter {
        height: 42px; padding: 0 1.4rem; border: none; border-radius: 8px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; background: var(--ink); color: white;
    }
    .btn-filter:hover { background: var(--accent); }

    /* ── Table ───────────────────────────────── */
    .wo-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .wo-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .wo-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .wo-table thead th.th-c { text-align: center; }
    .wo-table thead th.th-r { text-align: right; }

    .wo-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .wo-table tbody tr:last-child { border-bottom: none; }
    .wo-table tbody tr:hover { background: #fdfcfa; }

    .wo-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .wo-table td.td-c { text-align: center; }
    .wo-table td.td-r { text-align: right; }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em;
    }
    .pill.blue  { background: var(--accent-bg); color: #1e40af; }
    .pill.green { background: #ecfdf5; color: #065f46; }
    .pill.gray  { background: #f0ece5; color: var(--ink-soft); }

    /* Actions */
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

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

<div class="wo-wrap">

    <!-- Header -->
    <div class="wo-header">
        <div>
            <div class="eyebrow">Contract Management</div>
            <h1>All Work <em>Orders</em></h1>
        </div>
        <div>
            <a href="create_work_order.php" class="btn-new">
                <i class="fas fa-plus"></i> Add Work Order
            </a>
        </div>
    </div>

    <!-- ── Stats Cards ───────────────────────── -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hammer"></i></div>
            <div class="stat-title">Active Orders</div>
            <div class="stat-value"><?= number_format($stats['active_orders']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-contract"></i></div>
            <div class="stat-title">Commitment Value</div>
            <div class="stat-value" style="color:var(--accent)"><?= formatCurrencyShort($stats['total_value']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#ecfdf5;color:#059669"><i class="fas fa-check-circle"></i></div>
            <div class="stat-title">Paid Amount</div>
            <div class="stat-value" style="color:#059669"><?= formatCurrencyShort($stats['total_paid']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff7ed;color:#ea580c"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-title">Pending</div>
            <div class="stat-value" style="color:#ea580c"><?= formatCurrencyShort($stats['pending_value']) ?></div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="wo-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-file-contract"></i></div>
                <div>
                    <div class="toolbar-title">Contracts List</div>
                    <div class="toolbar-subtitle">Manage contractor agreements and values</div>
                </div>
            </div>
            <div class="toolbar-div"></div>
            <!-- Actions like export could go here -->
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <select name="project" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" <?= $filters['project_id'] == $project['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="contractor" class="f-select">
                    <option value="">All Contractors</option>
                    <?php foreach ($contractors as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filters['contractor_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-filter">Filter</button>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="wo-table">
                <thead>
                    <tr>
                        <th style="padding-left:1.5rem">WO #</th>
                        <th>Work Title</th>
                        <th>Contractor/Project</th>
                        <th>Type</th>
                        <th class="th-c">Contract Value</th>
                        <th class="th-c">Paid</th>
                        <th class="th-c">TDS</th>
                        <th class="th-c">Status</th>
                        <th style="padding-right:1.5rem">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($workOrders)): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h4>No work orders found</h4>
                                    <p>Create a new work order to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($workOrders as $wo): 
                        // Mock data for paid amount until payments are linked
                        $paidAmount = 0; 
                    ?>
                    <tr>
                        <td style="padding-left:1.5rem">
                            <a href="view_work_order.php?id=<?= $wo['id'] ?>" style="font-family:monospace;color:var(--accent);font-weight:600;text-decoration:none;">
                                <?= $wo['work_order_no'] ?>
                            </a>
                        </td>
                        <td><span style="font-weight:600;color:var(--ink)"><?= htmlspecialchars($wo['title']) ?></span></td>
                        <td>
                            <div style="font-weight:700;color:var(--ink);font-size:0.875rem;"><?= htmlspecialchars($wo['contractor_name']) ?></div>
                            <div class="cell-primary" style="margin-top:0.35rem; display:flex; align-items:center; gap:6px;"><?= renderProjectBadge($wo['project_name'], $wo['project_id']) ?></div>
                        </td>
                        <td><span class="pill gray" style="font-size:0.65rem"><?= htmlspecialchars($wo['contractor_type'] ?? '-') ?></span></td>
                        <td class="td-c" style="font-family:monospace;font-weight:600"><?= formatCurrency($wo['contract_amount']) ?></td>
                        <td class="td-c" style="color:var(--ink-mute)"><?= formatCurrency($paidAmount) ?></td>
                        <td class="td-c"><span class="pill gray"><?= $wo['tds_percentage'] ?>%</span></td>
                        <td class="td-c">
                            <?php if($wo['status'] === 'active'): ?>
                                <span class="pill green">Active</span>
                            <?php else: ?>
                                <span class="pill gray">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right:1.5rem">
                            <div class="act-group" style="display:flex;gap:0.5rem">
                                <a href="view_work_order.php?id=<?= $wo['id'] ?>" class="act-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_work_order.php?id=<?= $wo['id'] ?>" class="act-btn" title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

