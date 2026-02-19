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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    setFlashMessage('error', 'Invalid Work Order ID.');
    redirect('modules/contractors/work_orders.php');
}

$wo = $masterService->getWorkOrder($id);
if (!$wo) {
    setFlashMessage('error', 'Work Order not found.');
    redirect('modules/contractors/work_orders.php');
}

$page_title = 'WO Details: ' . $wo['work_order_no'];
$current_page = 'work_orders';

// Fetch associated challans/bills if needed using a direct query for now
$challans = $db->query("SELECT * FROM challans WHERE work_order_id = ? ORDER BY created_at DESC", [$id])->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

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
        --accent:    #2a58b5;
        --accent-bg: #f0f5ff;
        --accent-lt: #eff4ff;
    }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    .wo-view-wrap {
        max-width: 1060px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .wo-view-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        flex-wrap: wrap;
        opacity: 0;
        animation: fadeUp 0.3s ease both;
    }

    .header-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .back-crumb {
        font-size: 0.75rem; font-weight: 600;
        color: var(--ink-mute); text-decoration: none;
        letter-spacing: 0.04em;
        display: inline-flex; align-items: center; gap: 0.3rem;
        transition: color 0.15s ease;
    }
    .back-crumb:hover { color: var(--accent); }
    .crumb-sep { color: var(--border); font-size: 0.75rem; }
    .crumb-current { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }

    .wo-view-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 1.85rem; font-weight: 700;
        color: var(--ink); margin: 0 0 0.4rem;
        line-height: 1.1;
        display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    }

    .wo-meta-line {
        font-size: 0.8rem; color: var(--ink-mute);
        display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
    }
    .wo-meta-line .dot { color: var(--border); }

    /* ── Status Pills ────────────────────────── */
    .status-pill {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.3rem 0.8rem; border-radius: 20px;
        font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
        white-space: nowrap; vertical-align: middle;
    }
    .status-pill .dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; background: currentColor; }
    .st-active { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .st-completed { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
    .st-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    /* ── Action Buttons ──────────────────────── */
    .header-actions {
        display: flex; align-items: center; gap: 0.6rem;
        flex-wrap: wrap; flex-shrink: 0;
    }
    .act {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.6rem 1.2rem; border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; font-weight: 600;
        cursor: pointer; text-decoration: none;
        border: 1.5px solid transparent;
        transition: all 0.18s ease; white-space: nowrap;
    }
    .act:hover { transform: translateY(-1px); }
    .act-back { background: var(--surface); border-color: var(--border); color: var(--ink-soft); }
    .act-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-edit { background: var(--ink); color: white; border-color: var(--ink); }
    .act-edit:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 12px rgba(42,88,181,0.25); }

    /* ── Summary Strip ───────────────────────── */
    .summary-strip {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px;
        background: var(--border); border: 1.5px solid var(--border);
        border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem;
        opacity: 0; animation: fadeUp 0.35s 0.05s ease both;
    }
    @media (max-width: 700px) { .summary-strip { grid-template-columns: repeat(2, 1fr); } }
    .strip-cell {
        background: var(--surface); padding: 1.1rem 1.4rem;
        display: flex; flex-direction: column; gap: 0.25rem;
    }
    .strip-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
    }
    .strip-value { font-size: 0.95rem; font-weight: 600; color: var(--ink); }
    .strip-value.accent {
        font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 700; color: var(--accent);
    }

    /* ── Info Grid ───────────────────────────── */
    .info-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;
        opacity: 0; animation: fadeUp 0.35s 0.1s ease both;
    }
    @media (max-width: 680px) { .info-grid { grid-template-columns: 1fr; } }
    
    .wo-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden; margin-bottom: 1.25rem;
        opacity: 0; animation: fadeUp 0.35s 0.12s ease both;
    }
    .wo-card-head {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .card-head-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; flex-shrink: 0;
    }
    .icon-contractor { background: #eef2ff; color: #4f63d2; }
    .icon-project { background: #ecfdf5; color: #059669; }
    .icon-financial { background: var(--accent-lt); color: var(--accent); }
    .wo-card-head h2 {
        font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
    }
    .wo-card-body { padding: 1.4rem 1.5rem; }

    .detail-row {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 1rem; padding: 0.7rem 0; border-bottom: 1px solid var(--border-lt);
    }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-row:first-child { padding-top: 0; }
    .dl { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); letter-spacing: 0.02em; flex-shrink: 0; }
    .dv { font-size: 0.875rem; font-weight: 600; color: var(--ink); text-align: right; word-break: break-word; }
    .dv.muted { color: var(--ink-mute); font-weight: 400; }

    /* ── Challans Table ──────────────────────── */
    .items-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .items-table th {
        padding: 0.75rem 1.1rem; text-align: left;
        font-size: 0.67rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft);
        background: #fdfcfa; border-bottom: 1.5px solid var(--border);
    }
    .items-table td { padding: 0.9rem 1.1rem; border-bottom: 1px solid var(--border-lt); }
    .items-table tr:hover { background: #fdfcfa; }
    
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="wo-view-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="wo-view-header">
        <div>
            <div class="header-meta">
                <a href="work_orders.php" class="back-crumb"><i class="fas fa-arrow-left"></i> Work Orders</a>
                <span class="crumb-sep">/</span>
                <span class="crumb-current">View Details</span>
            </div>
            <h1>
                <?= htmlspecialchars($wo['work_order_no']) ?>
                <span class="status-pill st-<?= $wo['status'] ?>">
                    <span class="dot"></span><?= ucfirst($wo['status']) ?>
                </span>
            </h1>
            <div class="wo-meta-line">
                <span><i class="fas fa-calendar-alt"></i> Created: <?= formatDate($wo['created_at']) ?></span>
                <span class="dot">•</span>
                <span>Type: <?= htmlspecialchars($wo['contractor_type'] ?? 'General') ?></span>
            </div>
        </div>

        <div class="header-actions">
            <!-- <a href="print_work_order.php?id=<?= $wo['id'] ?>" target="_blank" class="act act-back">
                <i class="fas fa-print"></i> Print
            </a> -->
            <a href="edit_work_order.php?id=<?= $wo['id'] ?>" class="act act-edit">
                <i class="fas fa-pencil-alt"></i> Edit Order
            </a>
            <a href="work_orders.php" class="act act-back">
                Back to List
            </a>
        </div>
    </div>

    <!-- ── Summary Strip ────────────────────── -->
    <div class="summary-strip">
        <div class="strip-cell">
            <div class="strip-label">Project</div>
            <div class="strip-value"><?= renderProjectBadge($wo['project_name'], $wo['project_id']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Contractor</div>
            <div class="strip-value"><?= htmlspecialchars($wo['contractor_name']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Status</div>
            <div class="strip-value"><?= ucfirst($wo['status']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Contract Value</div>
            <div class="strip-value accent"><?= formatCurrency($wo['contract_amount']) ?></div>
        </div>
    </div>

    <!-- ── Info Grid ────────────────────────── -->
    <div class="info-grid">
        <!-- Contractor Details -->
        <div class="wo-card">
            <div class="wo-card-head">
                <div class="card-head-icon icon-contractor"><i class="fas fa-hard-hat"></i></div>
                <h2>Contractor Details</h2>
            </div>
            <div class="wo-card-body">
                <div class="detail-row">
                    <span class="dl">Name</span>
                    <span class="dv"><?= htmlspecialchars($wo['contractor_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dl">Type</span>
                    <span class="dv"><?= htmlspecialchars($wo['contractor_type'] ?? 'General') ?></span>
                </div>
                <div class="detail-row">
                    <span class="dl">Mobile</span>
                    <span class="dv <?= empty($wo['contractor_mobile']) ? 'muted' : '' ?>">
                        <?= htmlspecialchars($wo['contractor_mobile'] ?? '—') ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="dl">GST Number</span>
                    <span class="dv <?= empty($wo['contractor_gst']) ? 'muted' : '' ?>">
                        <?= htmlspecialchars($wo['contractor_gst'] ?? '—') ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="dl">PAN Number</span>
                    <span class="dv <?= empty($wo['contractor_pan']) ? 'muted' : '' ?>">
                        <?= htmlspecialchars($wo['contractor_pan'] ?? '—') ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="dl">Address</span>
                    <span class="dv <?= empty($wo['contractor_address']) ? 'muted' : '' ?>">
                        <?= htmlspecialchars($wo['contractor_address'] ?? '—') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Order Details & Financials -->
        <div class="wo-card">
            <div class="wo-card-head">
                <div class="card-head-icon icon-project"><i class="fas fa-file-contract"></i></div>
                <h2>Order Details</h2>
            </div>
            <div class="wo-card-body">
                <div class="detail-row">
                    <span class="dl">Work Title</span>
                    <span class="dv"><?= htmlspecialchars($wo['title']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dl">Project</span>
                    <span class="dv"><?= htmlspecialchars($wo['project_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dl">Created Date</span>
                    <span class="dv"><?= formatDate($wo['created_at']) ?></span>
                </div>
                <div class="detail-row" style="margin-top:1rem; border-top:1px solid var(--border-lt); padding-top:0.7rem;">
                    <span class="dl">Contract Value</span>
                    <span class="dv" style="font-size:1rem;color:var(--accent);"><?= formatCurrency($wo['contract_amount']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="dl">GST Rate</span>
                    <span class="dv"><?= $wo['gst_rate'] ?>%</span>
                </div>
                <div class="detail-row">
                    <span class="dl">TDS Rate</span>
                    <span class="dv"><?= $wo['tds_percentage'] ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Linked Challans ──────────────────── -->
    <?php if (!empty($challans)): ?>
    <div class="wo-card">
        <div class="wo-card-head">
            <div class="card-head-icon icon-financial"><i class="fas fa-list-ul"></i></div>
            <h2>Linked Challans / Measurements</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Challan No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th style="text-align:right">Amount</th>
                        <th style="text-align:center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challans as $ch): ?>
                    <tr onclick="window.location.href='../vendors/challans/view.php?id=<?= $ch['id'] ?>'" style="cursor:pointer;">
                        <td style="font-family:monospace; color:var(--accent); font-weight:600;">
                            <?= htmlspecialchars($ch['challan_no']) ?>
                        </td>
                        <td><?= formatDate($ch['created_at']) ?></td>
                        <td><?= ucfirst($ch['challan_type']) ?></td>
                        <td style="text-align:right; font-weight:600;"><?= formatCurrency($ch['final_payable_amount']) ?></td>
                        <td style="text-align:center;">
                            <span class="status-pill st-<?= $ch['status'] ?? 'pending' ?>">
                                <span class="dot"></span><?= ucfirst($ch['status'] ?? 'Pending') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
