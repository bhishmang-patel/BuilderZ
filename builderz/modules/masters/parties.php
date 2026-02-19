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

$masterService = new MasterService();
$page_title = 'Parties';
$current_page = 'parties';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF Token verification failed');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $data = [
                'party_type' => $_POST['party_type'],
                'contractor_type' => $_POST['contractor_type'] ?? null,
                'name' => sanitize($_POST['name']),
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'gst_number' => sanitize($_POST['gst_number'])
            ];
            $masterService->createParty($data);
            setFlashMessage('success', 'Party created successfully');
            
            if (!empty($_POST['return_url'])) {
                header('Location: ' . $_POST['return_url']);
                exit;
            }
            
        } elseif ($action === 'update') {
            $data = [
                'party_type' => $_POST['party_type'],
                'contractor_type' => $_POST['contractor_type'] ?? null,
                'name' => sanitize($_POST['name']),
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'gst_number' => sanitize($_POST['gst_number'])
            ];
            $masterService->updateParty(intval($_POST['id']), $data);
            setFlashMessage('success', 'Party updated successfully');
            
        } elseif ($action === 'delete') {
            $masterService->deleteParty(intval($_POST['id']));
            setFlashMessage('success', 'Party deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/masters/parties.php');
}

// Fetch all parties
$filters = [
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];
$parties = $masterService->getAllParties($filters);

// Statistics
$stats = [
    'total' => count($parties),
    'customer' => count(array_filter($parties, fn($p) => $p['party_type'] === 'customer')),
    'vendor' => count(array_filter($parties, fn($p) => $p['party_type'] === 'vendor')),
    'labour' => count(array_filter($parties, fn($p) => $p['party_type'] === 'labour'))
];

$hasFilters = !empty($filters['search']) || !empty($filters['type']);

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
    .par-wrap { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .par-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }
    .par-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .par-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--ink); cursor: pointer;
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.28); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 640px)  { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem;
        display: flex; align-items: center; gap: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both; position: relative; overflow: hidden;
    }
    .stat-card:nth-child(1){animation-delay:.05s}
    .stat-card:nth-child(2){animation-delay:.1s}
    .stat-card:nth-child(3){animation-delay:.15s}
    .stat-card:nth-child(4){animation-delay:.2s}
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }
    .stat-card::before {
        content:''; position:absolute; right:0; top:0; bottom:0;
        width:3px;
    }
    .stat-card.c-all::before  { background: var(--accent); }
    .stat-card.c-cust::before { background: #10b981; }
    .stat-card.c-vend::before { background: var(--accent); }
    .stat-card.c-lab::before  { background: #f59e0b; }

    .s-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; flex-shrink: 0;
    }
    .si-blue   { background: var(--accent-bg); color: var(--accent); }
    .si-green  { background: #ecfdf5; color: #10b981; }
    .si-orange { background: #fff7ed; color: #f59e0b; }

    .stat-info { flex: 1; }
    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.3rem;
    }
    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700;
        color: var(--ink); line-height: 1;
    }

    /* ── Main Panel ──────────────────────────── */
    .par-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: visible;
        animation: fadeUp 0.45s 0.25s ease both;
    }

    /* Toolbar */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa; flex-wrap: wrap;
    }
    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: var(--accent);
        border-radius: 7px; display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.15rem; }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; margin-left: auto; flex-wrap: wrap; }

    .btn-filter {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1.1rem; border: 1.5px solid var(--border);
        background: white; color: var(--ink-soft); border-radius: 8px;
        font-size: 0.82rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s;
    }
    .btn-filter:hover, .btn-filter.active { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* Filter Section */
    .filter-section {
        padding: 1.1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .filter-form { display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap; }
    .f-search {
        flex: 2; min-width: 200px; position: relative;
    }
    .f-search i {
        position: absolute; left: 0.85rem; top: 50%;
        transform: translateY(-50%); color: var(--ink-mute); font-size: 0.78rem;
        pointer-events: none;
    }
    .f-search input {
        width: 100%; height: 40px; padding: 0 0.85rem 0 2.3rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .f-search input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,.1); }
    .f-select {
        height: 40px; padding: 0 2rem 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white; outline: none;
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.7rem center;
        transition: border-color .15s; cursor: pointer;
    }
    .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,.1); }
    .btn-apply {
        height: 40px; padding: 0 1.2rem; border: none; border-radius: 8px;
        background: var(--ink); color: white; font-size: 0.875rem;
        font-weight: 600; cursor: pointer; transition: background .18s;
        display: flex; align-items: center; gap: 0.4rem;
    }
    .btn-apply:hover { background: var(--accent); }
    .btn-reset {
        height: 40px; padding: 0 1rem; border: 1.5px solid var(--border);
        background: white; color: var(--ink-soft); border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        text-decoration: none; display: flex; align-items: center; gap: 0.4rem;
        transition: all .18s;
    }
    .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

    /* Table */
    .par-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .par-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .par-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .par-table thead th.th-c { text-align: center; }
    .par-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .par-table tbody tr:last-child { border-bottom: none; }
    .par-table tbody tr:hover { background: #fdfcfa; }
    .par-table td { padding: 0.85rem 1rem; vertical-align: middle; }
    .par-table td.td-c { text-align: center; }

    /* Avatar */
    .av-sq {
        width: 32px; height: 32px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }

    /* Pills */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    }
    .pill.customer    { background: #ecfdf5; color: #065f46; }
    .pill.vendor      { background: var(--accent-bg); color: #1e40af; }
    .pill.contractor  { background: #f5f3ff; color: #6d28d9; }
    .pill.labour      { background: #fff7ed; color: #c2410c; }

    /* Actions */
    .act-group { display: flex; gap: 0.3rem; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; cursor: pointer;
        border: 1.5px solid var(--border); background: white;
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.del:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

    /* Empty state */
    .empty-state { padding: 4rem 1rem; text-align: center; }
    .empty-state i { font-size: 2.5rem; color: var(--border); margin-bottom: 0.75rem; display: block; }
    .empty-state h4 { font-size: 1rem; font-weight: 700; color: var(--ink-soft); margin: 0 0 0.3rem; }
    .empty-state p { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

    /* ── Modals ──────────────────────────────── */
    .m-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.55); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1.5rem;
    }
    .m-backdrop.open { display: flex; }

    .m-box {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    .m-box.sm { max-width: 420px; }
    .m-box.md { max-width: 600px; }
    @keyframes modalIn { from{opacity:0;transform:translateY(-14px)} to{opacity:1;transform:translateY(0)} }

    /* Modal head variants */
    .m-head {
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        display: flex; align-items: flex-start; justify-content: space-between;
    }
    .m-head.blue-head { background: var(--accent); }
    .m-head.plain-head { background: #fdfcfa; }

    .m-head-icon {
        width: 36px; height: 36px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; margin-right: 0.75rem; flex-shrink: 0;
    }
    .ic-white { background: rgba(255,255,255,0.15); color: white; }

    .m-head-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; margin: 0; }
    .m-head-title.white { color: white; }
    .m-head-title.dark { color: var(--ink); }
    .m-head-sub { font-size: 0.75rem; margin: 0.2rem 0 0; }
    .m-head-sub.white { color: rgba(255,255,255,0.7); }

    .m-close {
        width: 30px; height: 30px; display: flex; align-items: center;
        justify-content: center; border: none; border-radius: 7px;
        font-size: 1.1rem; cursor: pointer; transition: all 0.15s;
        flex-shrink: 0;
    }
    .m-close.white { background: rgba(255,255,255,0.15); color: white; }
    .m-close.white:hover { background: rgba(255,255,255,0.28); }
    .m-close.dark { background: var(--cream); color: var(--ink-mute); }
    .m-close.dark:hover { background: var(--border); color: var(--ink); }

    .m-body { padding: 1.75rem 1.6rem; overflow-y: auto; max-height: 70vh; }
    .m-foot {
        padding: 1.1rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa; display: flex; justify-content: flex-end; gap: 0.65rem;
    }

    /* Section label */
    .sec-label {
        font-size: 0.65rem; font-weight: 800; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        display: flex; align-items: center; gap: 0.5rem;
        margin-bottom: 1rem; margin-top: 1.5rem;
    }
    .sec-label::after { content:''; flex:1; height:1px; background:var(--border-lt); }
    .sec-label:first-child { margin-top: 0; }

    /* Form fields */
    .field { margin-bottom: 1rem; }
    .field label {
        display: block; font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select, .field textarea {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color .18s, box-shadow .18s;
        font-family: 'DM Sans', sans-serif;
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,.1);
    }
    .field textarea { resize: vertical; min-height: 80px; }

    .field-row { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; }

    /* Buttons */
    .btn {
        padding: 0.65rem 1.3rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all .18s; display: inline-flex;
        align-items: center; gap: 0.4rem;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary { background: var(--ink); color: white; border: 1.5px solid var(--ink); }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,.3); }
    .btn-danger { background: #ef4444; color: white; border: 1.5px solid #ef4444; }
    .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 14px rgba(239,68,68,.3); }

    /* View info row */
    .vrow {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 0.75rem 0; border-bottom: 1px solid var(--border-lt);
        font-size: 0.82rem;
    }
    .vrow:last-child { border-bottom: none; }
    .vrow-label { color: var(--ink-soft); font-weight: 500; }
    .vrow-val { color: var(--ink); font-weight: 600; text-align: right; max-width: 60%; }

    /* Delete modal center */
    .del-center { padding: 2.5rem 1.6rem; text-align: center; }
    .del-icon {
        width: 64px; height: 64px; background: #fef2f2;
        border-radius: 50%; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 1.25rem;
    }

    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="par-wrap">

    <!-- Header -->
    <div class="par-header">
        <div>
            <div class="eyebrow">Master Data</div>
            <h1>Parties</h1>
        </div>
        <button class="btn-new" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> New Party
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card c-all">
            <div class="s-icon si-blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Parties</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
            </div>
        </div>
        <div class="stat-card c-cust">
            <div class="s-icon si-green"><i class="fas fa-user-tie"></i></div>
            <div class="stat-info">
                <div class="stat-label">Customers</div>
                <div class="stat-value"><?= $stats['customer'] ?></div>
            </div>
        </div>
        <div class="stat-card c-vend">
            <div class="s-icon si-blue"><i class="fas fa-truck"></i></div>
            <div class="stat-info">
                <div class="stat-label">Vendors</div>
                <div class="stat-value"><?= $stats['vendor'] ?></div>
            </div>
        </div>
        <div class="stat-card c-lab">
            <div class="s-icon si-orange"><i class="fas fa-hard-hat"></i></div>
            <div class="stat-info">
                <div class="stat-label">Labour</div>
                <div class="stat-value"><?= $stats['labour'] ?></div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="par-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="toolbar-title">All Parties</div>
                    <div class="toolbar-subtitle">Customers, vendors &amp; contractors</div>
                </div>
            </div>
            <div class="toolbar-actions">
                <button class="btn-filter <?= $hasFilters ? 'active' : '' ?>" onclick="toggleFilter()" id="filterToggle">
                    <i class="fas fa-filter"></i> Filters<?= $hasFilters ? ' <span style="background:var(--accent);color:white;border-radius:10px;padding:0 5px;font-size:0.65rem;">on</span>' : '' ?>
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section" id="filterSection" style="display:<?= $hasFilters ? 'block' : 'none' ?>">
            <form method="GET" class="filter-form">
                <div class="f-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search name, mobile, email…"
                           value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <select name="type" class="f-select">
                    <option value="">All Types</option>
                    <option value="customer"   <?= $filters['type']==='customer'   ? 'selected' : '' ?>>Customer</option>
                    <option value="vendor"     <?= $filters['type']==='vendor'     ? 'selected' : '' ?>>Vendor</option>
                    <option value="contractor" <?= $filters['type']==='contractor' ? 'selected' : '' ?>>Contractor</option>
                </select>
                <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
                <a href="parties.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="par-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="th-c">Type</th>
                        <th>Contact Info</th>
                        <th>GST Number</th>
                        <th class="th-c">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parties)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h4>No parties found</h4>
                                    <p>Try adjusting your filters or add a new party.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($parties as $party):
                            $color   = ColorHelper::getCustomerColor($party['id']);
                            $initial = ColorHelper::getInitial($party['name']);
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center">
                                <div class="av-sq" style="background:<?= $color ?>"><?= $initial ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($party['name'] ?? '') ?></div>
                                    <?php if (!empty($party['contractor_type'])): ?>
                                        <div style="font-size:0.72rem;color:var(--ink-mute);margin-top:0.1rem"><?= htmlspecialchars($party['contractor_type']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="td-c">
                            <span class="pill <?= $party['party_type'] ?>"><?= ucfirst($party['party_type']) ?></span>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:0.2rem">
                                <?php if (!empty($party['mobile'])): ?>
                                    <span style="font-size:0.8rem;color:var(--ink-soft)">
                                        <i class="fas fa-phone-alt" style="font-size:0.7rem;margin-right:4px;color:var(--ink-mute)"></i>
                                        <?= htmlspecialchars($party['mobile']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($party['email'])): ?>
                                    <span style="font-size:0.8rem;color:var(--ink-soft)">
                                        <i class="fas fa-envelope" style="font-size:0.7rem;margin-right:4px;color:var(--ink-mute)"></i>
                                        <?= htmlspecialchars($party['email']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-size:0.82rem;font-weight:600;color:var(--ink-soft);font-family:monospace">
                                <?= htmlspecialchars($party['gst_number'] ?: '—') ?>
                            </span>
                        </td>
                        <td class="td-c">
                            <div class="act-group" style="justify-content:center">
                                <button class="act-btn" onclick='viewParty(<?= htmlspecialchars(json_encode($party)) ?>)' title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="act-btn" onclick='editParty(<?= htmlspecialchars(json_encode($party)) ?>)' title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="act-btn del" onclick="openDel(<?= $party['id'] ?>)" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /par-panel -->

</div><!-- /par-wrap -->


<!-- ═══════ VIEW MODAL ═══════ -->
<div class="m-backdrop" id="viewModal">
    <div class="m-box sm">
        <div class="m-head plain-head">
            <div style="display:flex;align-items:center;flex:1">
                <div class="m-head-icon" style="background:var(--accent-bg);color:var(--accent);margin-right:.75rem">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="m-head-title dark" id="viewName">—</div>
                    <div style="margin-top:.15rem" id="viewTypePill"></div>
                </div>
            </div>
            <button class="m-close dark" onclick="closeModal('viewModal')">×</button>
        </div>
        <div class="m-body">
            <div class="vrow"><span class="vrow-label">Mobile</span><span class="vrow-val" id="viewMobile">—</span></div>
            <div class="vrow"><span class="vrow-label">Email</span><span class="vrow-val" id="viewEmail">—</span></div>
            <div class="vrow"><span class="vrow-label">GST Number</span><span class="vrow-val" id="viewGst">—</span></div>
            <div class="vrow"><span class="vrow-label">Address</span><span class="vrow-val" id="viewAddress">—</span></div>
        </div>
        <div class="m-foot">
            <button class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>


<!-- ═══════ ADD MODAL ═══════ -->
<div class="m-backdrop" id="addModal">
    <div class="m-box md">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="return_url" id="add_return_url">

            <div class="m-head blue-head">
                <div style="display:flex;align-items:center">
                    <div class="m-head-icon ic-white"><i class="fas fa-user-plus"></i></div>
                    <div>
                        <div class="m-head-title white">Add New Party</div>
                        <div class="m-head-sub white">Enter party details</div>
                    </div>
                </div>
                <button type="button" class="m-close white" onclick="closeModal('addModal')">×</button>
            </div>

            <div class="m-body">
                <div class="sec-label">Basic Info</div>

                <div class="field">
                    <label>Party Type *</label>
                    <select name="party_type" required onchange="toggleContractorType('add',this.value)">
                        <option value="">Select Type</option>
                        <option value="customer">Customer</option>
                        <option value="vendor">Vendor</option>
                        <option value="contractor">Contractor</option>
                    </select>
                </div>

                <div class="field" id="addContractorDiv" style="display:none">
                    <label>Contractor Trade</label>
                    <select name="contractor_type">
                        <option value="">Select Trade</option>
                        <option value="Civil">Civil</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Carpentry">Carpentry</option>
                        <option value="Painting">Painting</option>
                        <option value="Fabrication">Fabrication</option>
                        <option value="Labour Supply">Labour Supply</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field" style="grid-column:1/-1">
                        <label>Party Name *</label>
                        <input type="text" name="name" required placeholder="Business or person name">
                    </div>
                    <div class="field">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" placeholder="22AAAAA0000A1Z5"
                               maxlength="15" oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>

                <div class="sec-label">Contact Details</div>

                <div class="field-row">
                    <div class="field">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" placeholder="10-digit mobile"
                               pattern="\d{10}" maxlength="10" minlength="10"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="field">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Email address">
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label>Address</label>
                        <textarea name="address" rows="3" placeholder="Full billing address"></textarea>
                    </div>
                </div>
            </div>

            <div class="m-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Party</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════ EDIT MODAL ═══════ -->
<div class="m-backdrop" id="editModal">
    <div class="m-box md">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">

            <div class="m-head blue-head">
                <div style="display:flex;align-items:center">
                    <div class="m-head-icon ic-white"><i class="fas fa-user-edit"></i></div>
                    <div>
                        <div class="m-head-title white">Edit Party</div>
                        <div class="m-head-sub white">Update party information</div>
                    </div>
                </div>
                <button type="button" class="m-close white" onclick="closeModal('editModal')">×</button>
            </div>

            <div class="m-body">
                <div class="sec-label">Basic Info</div>

                <div class="field">
                    <label>Party Type *</label>
                    <select name="party_type" id="editPartyType" required onchange="toggleContractorType('edit',this.value)">
                        <option value="">Select Type</option>
                        <option value="customer">Customer</option>
                        <option value="vendor">Vendor</option>
                        <option value="contractor">Contractor</option>
                    </select>
                </div>

                <div class="field" id="editContractorDiv" style="display:none">
                    <label>Contractor Trade</label>
                    <select name="contractor_type" id="editContractorType">
                        <option value="">Select Trade</option>
                        <option value="Civil">Civil</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Carpentry">Carpentry</option>
                        <option value="Painting">Painting</option>
                        <option value="Fabrication">Fabrication</option>
                        <option value="Labour Supply">Labour Supply</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field" style="grid-column:1/-1">
                        <label>Party Name *</label>
                        <input type="text" name="name" id="editName" required placeholder="Business or person name">
                    </div>
                    <div class="field">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" id="editGst" placeholder="22AAAAA0000A1Z5"
                               maxlength="15" oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>

                <div class="sec-label">Contact Details</div>

                <div class="field-row">
                    <div class="field">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" id="editMobile" placeholder="10-digit mobile"
                               pattern="\d{10}" maxlength="10" minlength="10"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="field">
                        <label>Email Address</label>
                        <input type="email" name="email" id="editEmail" placeholder="Email address">
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label>Address</label>
                        <textarea name="address" id="editAddress" rows="3" placeholder="Full billing address"></textarea>
                    </div>
                </div>
            </div>

            <div class="m-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Party</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════ DELETE MODAL ═══════ -->
<div class="m-backdrop" id="delModal">
    <div class="m-box sm">
        <div class="del-center">
            <div class="del-icon">
                <i class="fas fa-trash-alt" style="font-size:1.75rem;color:#ef4444"></i>
            </div>
            <h3 style="margin:0 0 .65rem;color:var(--ink);font-weight:700">Delete Party?</h3>
            <p style="color:var(--ink-soft);margin:0 0 1.5rem;line-height:1.6;font-size:0.875rem">
                Are you sure you want to delete this party?<br>
                <strong style="color:#ef4444">This action cannot be undone.</strong>
            </p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delId">
                <div style="display:flex;gap:.75rem;justify-content:center">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Party</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

function toggleFilter() {
    const s = document.getElementById('filterSection');
    const t = document.getElementById('filterToggle');
    const visible = s.style.display !== 'none';
    s.style.display = visible ? 'none' : 'block';
    t.classList.toggle('active', !visible);
}

function openDel(id) {
    document.getElementById('delId').value = id;
    openModal('delModal');
}

function toggleContractorType(prefix, val) {
    const div = document.getElementById(prefix + 'ContractorDiv');
    div.style.display = val === 'contractor' ? 'block' : 'none';
    if (val !== 'contractor') {
        div.querySelector('select').value = '';
    }
}

function viewParty(p) {
    document.getElementById('viewName').textContent = p.name || '—';
    document.getElementById('viewMobile').textContent  = p.mobile  || '—';
    document.getElementById('viewEmail').textContent   = p.email   || '—';
    document.getElementById('viewGst').textContent     = p.gst_number || '—';
    document.getElementById('viewAddress').textContent = p.address  || '—';

    const cls = { customer:'#ecfdf5', vendor:'#eff6ff', contractor:'#f5f3ff', labour:'#fff7ed' };
    const col = { customer:'#065f46', vendor:'#1e40af', contractor:'#6d28d9',  labour:'#c2410c' };
    const t = p.party_type || '';
    document.getElementById('viewTypePill').innerHTML =
        `<span style="display:inline-block;padding:.2rem .6rem;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;background:${cls[t]||'#f0ece5'};color:${col[t]||'#6b6560'}">${t.charAt(0).toUpperCase()+t.slice(1)}</span>`;

    openModal('viewModal');
}

function editParty(p) {
    document.getElementById('editId').value    = p.id;
    document.getElementById('editPartyType').value = p.party_type;
    document.getElementById('editName').value  = p.name;
    document.getElementById('editMobile').value = p.mobile || '';
    document.getElementById('editEmail').value = p.email  || '';
    document.getElementById('editGst').value   = p.gst_number || '';
    document.getElementById('editAddress').value = p.address || '';

    toggleContractorType('edit', p.party_type);
    if (p.party_type === 'contractor' && p.contractor_type) {
        document.getElementById('editContractorType').value = p.contractor_type;
    }
    openModal('editModal');
}

// Click outside to close
document.querySelectorAll('.m-backdrop').forEach(bd => {
    bd.addEventListener('click', e => {
        if (e.target === bd) { bd.classList.remove('open'); document.body.style.overflow = ''; }
    });
});

// Auto-open modal from URL params
document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.has('open_add_modal')) {
        openModal('addModal');
        if (p.has('pre_party_type')) {
            const sel = document.querySelector('#addModal select[name="party_type"]');
            if (sel) { sel.value = p.get('pre_party_type'); toggleContractorType('add', sel.value); }
        }
        if (p.has('return_url')) {
            document.getElementById('add_return_url').value = p.get('return_url');
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>