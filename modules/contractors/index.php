<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Contractor Bills';
$current_page = 'contractor_pay';

// Handle challan operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/contractors/index.php');
    }
    // Any future bulk actions can go here
}

// Fetch challans with filters
$contractor_filter = $_GET['contractor'] ?? '';
$project_filter = $_GET['project'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "c.status != 'rejected'";
$params = [];

if ($contractor_filter) {
    $where .= ' AND c.contractor_id = ?';
    $params[] = $contractor_filter;
}

$type_filter = $_GET['type'] ?? '';
if ($type_filter) {
    $where .= ' AND p.contractor_type = ?';
    $params[] = $type_filter;
}

if ($project_filter) {
    $where .= ' AND c.project_id = ?';
    $params[] = $project_filter;
}

if ($status_filter) {
    $where .= ' AND c.status = ?';
    $params[] = $status_filter;
}

$sql = "SELECT p.id as contractor_id, p.name as contractor_name, p.contractor_type, 
               p.gst_status, p.gst_number,
               COUNT(c.id) as total_bills,
               SUM(c.total_payable) as total_amount,
               SUM(c.paid_amount) as total_paid,
               SUM(c.pending_amount) as total_pending,
               GROUP_CONCAT(DISTINCT CONCAT(pr.id, ':', pr.project_name) SEPARATOR '||') as projects_data
        FROM contractor_bills c
        JOIN parties p ON c.contractor_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        WHERE $where
        GROUP BY c.contractor_id
        ORDER BY p.name ASC";

$stmt = $db->query($sql, $params);
$contractors_list = $stmt->fetchAll();

// Get contractors for filter
$contractors = $db->query("SELECT id, name, contractor_type FROM parties WHERE party_type = 'contractor' ORDER BY name")->fetchAll();

// Get unique contractor types
$contractor_types = $db->query("SELECT DISTINCT contractor_type FROM parties WHERE party_type = 'contractor' AND contractor_type IS NOT NULL AND contractor_type != '' ORDER BY contractor_type")->fetchAll(PDO::FETCH_COLUMN);

// Get projects
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

// Calculate Stats
$masterService = new MasterService();
$statsFilters = [
    'contractor' => $contractor_filter,
    'project' => $project_filter,
    'status' => $status_filter,
    'type' => $type_filter
];
$stats = $masterService->calculateContractorBillStats($statsFilters);

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
    .cont-wrap { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .cont-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .cont-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .cont-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .cont-header h1 em { color: var(--accent); font-style: italic; }

    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink);
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.28); color: white; }
    .btn-filter {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: white; color: var(--ink-soft);
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--border);
        cursor: pointer;
    }
    .btn-filter:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Stats Strip ────────────────────────── */
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

    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }
    .stat-icon {
        width: 36px; height: 36px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        background: var(--accent-bg); color: var(--accent);
        font-size: 1rem; margin-bottom: 0.25rem;
    }

    /* ── Main Panel ──────────────────────────── */
    .cont-panel {
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
        width: 32px; height: 32px; background: #f59e0b; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.2rem; }
    .toolbar-div { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; flex: 1; justify-content: flex-end; flex-wrap: wrap; }

    @media (max-width: 1100px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div { display: none; }
        .toolbar-actions { width: 100%; justify-content: flex-start; }
    }

    /* ── Filter Section ──────────────────────── */
    .filter-section {
        display: none; padding: 1.25rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .filter-section.show { display: block; }

    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

    .f-select {
        flex: 1; min-width: 160px; height: 42px; padding: 0 2.2rem 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s, box-shadow 0.15s;
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
    }
    .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }

    .btn-go, .btn-reset {
        height: 42px; padding: 0 1.4rem; border: none; border-radius: 8px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; text-decoration: none;
    }
    .btn-go { background: var(--ink); color: white; }
    .btn-go:hover { background: var(--accent); }
    .btn-reset { background: #f0ece5; color: var(--ink-soft); }
    .btn-reset:hover { background: var(--border); color: var(--ink); }

    /* ── Table ───────────────────────────────── */
    .cont-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .cont-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .cont-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .cont-table thead th.th-c { text-align: center; }
    .cont-table thead th.th-r { text-align: right; }

    .cont-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .cont-table tbody tr:last-child { border-bottom: none; }
    .cont-table tbody tr:hover { background: #fdfcfa; }

    .cont-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .cont-table td.td-c { text-align: center; }
    .cont-table td.td-r { text-align: right; }

    /* Avatar */
    .av-circ {
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em;
    }
    .pill.blue   { background: var(--accent-bg); color: #1e40af; }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.orange { background: #fff7ed; color: #c2410c; }
    .pill.gray   { background: #f0ece5; color: var(--ink-soft); }
    .pill.red    { background: #fef2f2; color: #dc2626; }

    /* GST Cell */
    .gst-cell { font-size: 0.78rem; text-align: center; }
    .gst-status { font-weight: 700; color: var(--ink); text-transform: capitalize; }
    .gst-num { font-family: monospace; font-size: 0.7rem; color: var(--ink-mute); margin-top: 1px; }

    .rcm-tag {
        font-size: 0.62rem; background: #fef2f2; color: #b91c1c;
        padding: 0.15rem 0.4rem; border-radius: 3px; margin-left: 0.4rem;
        font-weight: 700; letter-spacing: 0.05em;
    }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; justify-content: center; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; border: 1.5px solid var(--border);
        background: white; color: var(--ink-soft); cursor: pointer;
        transition: all 0.15s ease; text-decoration: none;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.approve:hover { border-color: #059669; color: #059669; background: #ecfdf5; }
    .act-btn.reject:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
    .act-btn.approve:hover { border-color: #10b981; color: #10b981; background: #ecfdf5; }

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

    /* ── Modals ──────────────────────────────── */
    .cont-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .cont-modal-backdrop.open { display: flex; }

    .cont-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    .cont-modal.sm { max-width: 480px; }
    .cont-modal.md { max-width: 900px; }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: var(--ink); margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-head h3 i { color: var(--accent); }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem; }

    .modal-footer {
        display: flex; justify-content: center; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary {
        background: #10b981; color: white; border: 1.5px solid #10b981;
    }
    .btn-primary:hover { background: #059669; border-color: #059669; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }

    /* Approve modal special styling */
    .approve-header {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        padding: 2rem 1.6rem; text-align: center;
        border-bottom: 1.5px solid #d1fae5;
    }
    .approve-icon {
        width: 64px; height: 64px; background: white;
        border-radius: 50%; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 1rem;
        box-shadow: 0 4px 12px rgba(16,185,129,0.15);
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>
<div class="cont-wrap">

    <!-- Header -->
    <div class="cont-header">
        <div>
            <div class="eyebrow">Payment Management</div>
            <h1>Contractor <em>Bills</em></h1>
        </div>
        <div class="header-actions">

            <a href="create_bill.php" class="btn-new">
                <i class="fas fa-plus"></i> New Bill
            </a>
        </div>
    </div>

    <!-- ── Stats Cards ───────────────────────── -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-title">Total Bills</div>
            <div class="stat-value"><?= number_format($stats['total_bills']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-title">Total Billed</div>
            <div class="stat-value" style="color:var(--accent)">
                <span class="short-val"><?= formatCurrencyShort($stats['total_billed']) ?></span>
                <span class="full-val"><?= formatCurrency($stats['total_billed']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#ecfdf5;color:#059669"><i class="fas fa-check-circle"></i></div>
            <div class="stat-title">Total Paid</div>
            <div class="stat-value" style="color:#059669">
                <span class="short-val"><?= formatCurrencyShort($stats['total_paid']) ?></span>
                <span class="full-val"><?= formatCurrency($stats['total_paid']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff7ed;color:#ea580c"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-title">Outstanding</div>
            <div class="stat-value" style="color:#ea580c">
                <span class="short-val"><?= formatCurrencyShort($stats['total_pending']) ?></span>
                <span class="full-val"><?= formatCurrency($stats['total_pending']) ?></span>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="cont-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-hard-hat"></i></div>
                <div>
                    <div class="toolbar-title">All Bills</div>
                    <div class="toolbar-subtitle">Manage contractor payments & bills</div>
                </div>
            </div>
            <div class="toolbar-div"></div>
            <div class="toolbar-actions">
                <button class="btn-filter" onclick="toggleFilters()">
                    <i class="fas fa-filter"></i> Filters
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section <?= ($contractor_filter || $project_filter || $status_filter || $type_filter) ? 'show' : '' ?>" id="filterSection">
            <form method="GET" class="filter-form">

                
                <select name="type" class="f-select">
                    <option value="">All Types</option>
                    <?php foreach ($contractor_types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="project" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="f-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial Paid</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
                
                <button type="submit" class="btn-go">Apply</button>
                
                <a href="index.php" class="btn-reset">Reset</a>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="cont-table">
                <thead>
                    <tr>
                        <th>Contractor</th>
                        <th class="th-c">Type</th>
                        <th class="th-c">GST</th>
                        <th class="th-c">Total Bills</th>
                        <th class="th-r">Total Amount</th>
                        <th class="th-r">Outstanding</th>
                        <th class="th-c">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contractors_list)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No contractor records found</h4>
                                    <p>Start by creating new bills.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($contractors_list as $c): 
                            // Determine GST Display
                            $gstNum = $c['gst_number'] ?? '';
                            $gstStatus = $c['gst_status'] ?? '';
                            
                            // If we have a number and status is not composition, force registered
                            // This corrects existing records that might be marked 'unregistered' but have a number
                            if (!empty($gstNum) && $gstStatus !== 'composition') {
                                $gstStatus = 'registered';
                            }
                            
                            // Default to unregistered if still empty
                            if (empty($gstStatus)) {
                                $gstStatus = 'unregistered';
                            }
                            
                            $gstLabel = ucfirst($gstStatus);
                            $gstClass = ($gstStatus === 'registered') ? 'green' : (($gstStatus === 'composition') ? 'orange' : 'gray');
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <span style="font-weight:700;color:var(--ink);font-size:0.95rem;"><?= htmlspecialchars($c['contractor_name']) ?></span>
                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php 
                                    if (!empty($c['projects_data'])) {
                                        $projs = explode('||', $c['projects_data']);
                                        foreach ($projs as $pStr) {
                                            $parts = explode(':', $pStr);
                                            if (count($parts) >= 2) {
                                                echo renderProjectBadge($parts[1], $parts[0]);
                                            }
                                        }
                                    }
                                ?>
                                </div>
                            </div>
                        </td>
                        <td class="td-c">
                            <span class="pill gray" style="font-size:0.65rem"><?= htmlspecialchars(str_replace('_',' ',$c['contractor_type'] ?? '-')) ?></span>
                        </td>
                        <td class="td-c">
                            <div class="gst-cell">
                                <div class="gst-status"><?= $gstLabel ?></div>
                                <?php if($gstNum): ?>
                                    <div class="gst-num"><?= $gstNum ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="td-c">
                            <span class="pill blue"><?= $c['total_bills'] ?> Bills</span>
                        </td>
                        <td class="td-r"><strong style="font-weight:600;color:var(--ink)"><?= formatCurrency($c['total_amount']) ?></strong></td>
                        <td class="td-r">
                            <?php if ($c['total_pending'] > 0): ?>
                                <span style="color:#f59e0b;font-weight:600"><?= formatCurrency($c['total_pending']) ?></span>
                            <?php else: ?>
                                <span class="pill green">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-c">
                            <div class="act-group">
                                <button type="button" class="act-btn" onclick="openBillModal(<?= $c['contractor_id'] ?>, '<?= htmlspecialchars(addslashes($c['contractor_name'])) ?>')" title="View Bills">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <style>
        .stat-pill { background:white; border:1px solid var(--border); padding:0.5rem 1rem; border-radius:8px; display:flex; flex-direction:column; min-width:100px; }
        .stat-pill .lbl { font-size:0.7rem; text-transform:uppercase; font-weight:700; color:var(--ink-mute); margin-bottom:2px; }
        .stat-pill .val { font-family:'Fraunces',serif; font-weight:700; font-size:1.1rem; color:var(--ink); }
        .stat-pill .val.green { color:#059669; }
        .stat-pill .val.orange { color:#d97706; }
    </style>

</div>



<script>
function toggleFilters() {
    document.getElementById('filterSection').classList.toggle('show');
}

function openBillModal(contractorId, contractorName) {
    document.getElementById('modalContractorName').textContent = contractorName;
    document.getElementById('billModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Reset stats
    document.getElementById('cb_total').textContent = '—';
    document.getElementById('cb_paid').textContent = '—';
    document.getElementById('cb_pending').textContent = '—';

    const tbody = document.getElementById('modalBillList');
    tbody.innerHTML = '<tr><td colspan="8" class="td-c" style="padding:2rem;color:var(--ink-mute)">Loading bills...</td></tr>';

    fetch(`<?= BASE_URL ?>modules/api/get_contractor_bills.php?contractor_id=${contractorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stats
                if(data.stats) {
                    document.getElementById('cb_total').textContent   = data.stats.total;
                    document.getElementById('cb_paid').textContent    = data.stats.paid;
                    document.getElementById('cb_pending').textContent = data.stats.pending;
                }

                if (data.bills.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="td-c" style="padding:2rem;">No bills found.</td></tr>';
                    return;
                }
                
                let html = '';
                data.bills.forEach(bill => {
                    html += `
                        <tr>
                            <td><span style="font-size:0.8rem;color:var(--ink-soft)">${bill.date}</span></td>
                            <td><span style="font-weight:600;color:var(--ink)">${bill.challan_no}</span></td>
                            <td><span style="font-size:0.8rem">${bill.project_name || '-'}</span></td>
                            <td><span style="font-size:0.75rem;font-family:monospace">${bill.work_order_no}</span></td>
                            <td class="td-r"><strong style="color:#10b981">${bill.amount}</strong></td>
                            <td class="td-c"><span class="pill ${bill.status_class}">${bill.status}</span></td>
                            <td class="td-c"><span class="pill ${bill.payment_class}">${bill.payment_status}</span></td>
                            <td class="td-c">
                                <a href="view_bill.php?id=${bill.id}" class="act-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="td-c" style="color:red">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="td-c" style="color:red">Failed to load bills.</td></tr>';
        });
}

function closeBillModal() {
    document.getElementById('billModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Close on click outside
document.getElementById('billModal').addEventListener('click', function(e) {
    if (e.target === this) closeBillModal();
});
</script>

<!-- Bills Modal -->
<div class="cont-modal-backdrop" id="billModal">
    <div class="cont-modal md">
        <div class="modal-head">
            <h3><i class="fas fa-file-invoice"></i> Bills: <span id="modalContractorName"></span></h3>
            <button type="button" class="modal-close" onclick="closeBillModal()">×</button>
        </div>
        <div class="modal-body">
            <!-- Stats -->
            <div style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                <div class="stat-pill">
                    <span class="lbl">Total Billed</span>
                    <span class="val" id="cb_total">—</span>
                </div>
                <div class="stat-pill">
                    <span class="lbl">Total Paid</span>
                    <span class="val green" id="cb_paid">—</span>
                </div>
                <div class="stat-pill">
                    <span class="lbl">Pending</span>
                    <span class="val orange" id="cb_pending">—</span>
                </div>
            </div>

            <div style="overflow-x:auto">
                <table class="cont-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bill No</th>
                            <th>Project</th>
                            <th>WO #</th>
                            <th class="th-r">Amount</th>
                            <th class="th-c">Approval</th>
                            <th class="th-c">Payment</th>
                            <th class="th-c">Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalBillList">
                        <!-- Loaded via JS -->
                        <tr><td colspan="7" class="td-c">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>