<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/ProcurementService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$service = new ProcurementService();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('modules/vendors/procurement/index.php');
}

$po = $service->getPOById($id);
if (!$po) {
    die("Purchase Order not found");
}

// Fetch Settings for Terms
$settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'po_terms'")->fetchAll(PDO::FETCH_KEY_PAIR);
$po_terms = $settings['po_terms'] ?? '';

$page_title = 'PO Details: ' . $po['po_number'];
$current_page = 'procurement';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired.');
         redirect("modules/vendors/procurement/view.php?id=$id");
    }

    $action = $_POST['action'];
    if ($action === 'approve') {
        $service->updateStatus($id, 'approved');
        setFlashMessage('success', 'Purchase Order approved successfully');
    } elseif ($action === 'reject') {
        $service->updateStatus($id, 'rejected');
        setFlashMessage('warning', 'Purchase Order rejected');
    }
    redirect("modules/vendors/procurement/view.php?id=$id");
}

include __DIR__ . '/../../../includes/header.php';
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
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── Layout ──────────────────────────────── */
    .po-view-wrap {
        max-width: 1060px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .po-view-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        flex-wrap: wrap;
    }

    .header-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .back-crumb {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--ink-mute);
        text-decoration: none;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: color 0.15s ease;
    }
    .back-crumb:hover { color: var(--accent); }

    .crumb-sep { color: var(--border); font-size: 0.75rem; }
    .crumb-current { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }

    .po-view-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 0.4rem;
        line-height: 1.1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .po-meta-line {
        font-size: 0.8rem;
        color: var(--ink-mute);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .po-meta-line .dot { color: var(--border); }

    /* ── Status Pills ────────────────────────── */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
    }
    .status-pill::before {
        content: '';
        width: 5px; height: 5px;
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
    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .act {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: 1.5px solid transparent;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .act:hover { transform: translateY(-1px); }
    .act:active { transform: translateY(0); }

    .act-back {
        background: var(--surface);
        border-color: var(--border);
        color: var(--ink-soft);
    }
    .act-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .act-print {
        background: var(--surface);
        border-color: var(--border);
        color: var(--ink-soft);
    }
    .act-print:hover { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }

    .act-approve {
        background: #ecfdf5;
        border-color: #6ee7b7;
        color: #065f46;
    }
    .act-approve:hover {
        background: #10b981;
        border-color: #10b981;
        color: white;
        box-shadow: 0 4px 12px rgba(16,185,129,0.25);
    }

    .act-reject {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
    }
    .act-reject:hover {
        background: #ef4444;
        border-color: #ef4444;
        color: white;
        box-shadow: 0 4px 12px rgba(239,68,68,0.25);
    }

    /* ── Summary Strip ───────────────────────── */
    .summary-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: var(--border);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 700px) { .summary-strip { grid-template-columns: repeat(2, 1fr); } }

    .strip-cell {
        background: var(--surface);
        padding: 1.1rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .strip-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-mute);
    }

    .strip-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ink);
    }

    .strip-value.accent {
        font-family: 'Fraunces', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--accent);
    }

    /* ── Two-col info grid ───────────────────── */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 680px) { .info-grid { grid-template-columns: 1fr; } }

    /* ── Cards ───────────────────────────────── */
    .po-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }

    .po-card-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 1rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .card-head-icon {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        flex-shrink: 0;
    }

    .icon-vendor   { background: #eef2ff; color: #4f63d2; }
    .icon-project  { background: #ecfdf5; color: #059669; }
    .icon-items    { background: var(--accent-lt); color: var(--accent); }
    .icon-notes    { background: #f8f5f0; color: var(--ink-soft); }

    .po-card-head h2 {
        font-family: 'Fraunces', serif;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0;
    }

    .po-card-body { padding: 1.4rem 1.5rem; }

    /* ── Detail rows ─────────────────────────── */
    .detail-list { display: flex; flex-direction: column; gap: 0; }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px solid var(--border-lt);
    }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-row:first-child { padding-top: 0; }

    .dl { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); letter-spacing: 0.02em; flex-shrink: 0; }
    .dv { font-size: 0.875rem; font-weight: 600; color: var(--ink); text-align: right; word-break: break-word; }
    .dv.muted { color: var(--ink-mute); font-weight: 400; }
    .dv.warn  { color: #d97706; }

    /* ── Items Table ─────────────────────────── */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .items-table thead tr {
        background: #fdfcfa;
        border-bottom: 1.5px solid var(--border);
    }

    .items-table thead th {
        padding: 0.75rem 1.1rem;
        text-align: left;
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-soft);
        white-space: nowrap;
    }
    .items-table thead th.th-r { text-align: right; }
    .items-table thead th.th-c { text-align: center; }

    .items-table tbody tr {
        border-bottom: 1px solid var(--border-lt);
        transition: background 0.14s ease;
    }
    .items-table tbody tr:last-child { border-bottom: none; }
    .items-table tbody tr:hover { background: #fdfcfa; }

    .items-table td {
        padding: 0.9rem 1.1rem;
        vertical-align: middle;
    }
    .items-table td.td-r { text-align: right; }
    .items-table td.td-c { text-align: center; }

    .mat-name { font-weight: 600; color: var(--ink); }
    .mat-unit {
        display: inline-block;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--ink-mute);
        background: var(--cream);
        border: 1px solid var(--border);
        padding: 0.15rem 0.45rem;
        border-radius: 4px;
    }
    .td-qty  { font-variant-numeric: tabular-nums; color: var(--ink-soft); font-weight: 500; }
    .td-rate { font-variant-numeric: tabular-nums; color: var(--ink-soft); }
    .td-total { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

    /* received badges */
    .recv-full    { background: #ecfdf5; color: #065f46; }
    .recv-partial { background: var(--accent-lt); color: #a04d1e; }
    .recv-none    { background: #f1f5f9; color: #64748b; }

    .recv-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.22rem 0.6rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    /* ── Grand Total Row ─────────────────────── */
    .grand-total-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-top: 1.5px solid var(--border);
        background: #fdfcfa;
    }
    .gt-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-soft);
    }
    .gt-amount {
        font-family: 'Fraunces', serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--accent);
        font-variant-numeric: tabular-nums;
    }

    /* ── Notes ───────────────────────────────── */
    .notes-text {
        font-size: 0.875rem;
        color: var(--ink-soft);
        line-height: 1.7;
        margin: 0;
        white-space: pre-line;
    }

    /* ── Animations ──────────────────────────── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .po-view-header { animation: fadeUp 0.3s ease both; }
    .summary-strip  { animation: fadeUp 0.35s 0.05s ease both; }
    .info-grid      { animation: fadeUp 0.35s 0.1s ease both; }
    .po-card        { animation: fadeUp 0.35s 0.12s ease both; }
</style>

<div class="po-view-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="po-view-header">
        <div>
            <div class="header-meta">
                <a href="index.php" class="back-crumb"><i class="fas fa-arrow-left"></i> Procurement</a>
                <span class="crumb-sep">/</span>
                <span class="crumb-current">View Order</span>
            </div>
            <h1>
                <?= htmlspecialchars($po['po_number']) ?>
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
                <span class="status-pill <?= $pillClass ?>"><?= ucfirst($s) ?></span>
            </h1>
            <div class="po-meta-line">
                <span><i class="fas fa-calendar-alt"></i> <?= formatDate($po['order_date']) ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($po['created_by_name']) ?></span>
            </div>
        </div>

        <div class="header-actions">
            <?php if ($po['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="act act-approve">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </form>
                <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="act act-reject">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($po['status'] === 'approved' || $po['status'] === 'completed'): ?>
                <a href="print.php?id=<?= $id ?>" target="_blank" class="act act-print">
                    <i class="fas fa-print"></i> Print
                </a>
            <?php endif; ?>

            <a href="index.php" class="act act-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ── Summary Strip ────────────────────── -->
    <div class="summary-strip">
        <div class="strip-cell">
            <div class="strip-label">Project</div>
            <div class="strip-value"><?= renderProjectBadge($po['project_name'], $po['project_id']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Vendor</div>
            <div class="strip-value"><?= htmlspecialchars($po['vendor_name']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Expected Delivery</div>
            <div class="strip-value"><?= $po['expected_date'] ? formatDate($po['expected_date']) : 'Immediate' ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Order Total</div>
            <div class="strip-value accent"><?= formatCurrency($po['total_amount']) ?></div>
        </div>
    </div>

    <!-- ── Info Cards (2-col) ────────────────── -->
    <div class="info-grid">

        <!-- Vendor Details -->
        <div class="po-card">
            <div class="po-card-head">
                <div class="card-head-icon icon-vendor"><i class="fas fa-store"></i></div>
                <h2>Vendor Details</h2>
            </div>
            <div class="po-card-body">
                <div class="detail-list">
                    <div class="detail-row">
                        <span class="dl">Vendor Name</span>
                        <span class="dv"><?= htmlspecialchars($po['vendor_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="dl">Mobile</span>
                        <span class="dv <?= empty($po['vendor_mobile']) ? 'muted' : '' ?>">
                            <?= htmlspecialchars($po['vendor_mobile'] ?? '—') ?>
                        </span>
                    </div>
                    <?php if (!empty($po['vendor_gst'])): ?>
                    <div class="detail-row">
                        <span class="dl">GST Number</span>
                        <span class="dv"><?= htmlspecialchars($po['vendor_gst']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="dl">Reference No</span>
                        <span class="dv <?= empty($po['reference_no']) ? 'muted' : '' ?>">
                            <?= htmlspecialchars($po['reference_no'] ?? '—') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Details -->
        <div class="po-card">
            <div class="po-card-head">
                <div class="card-head-icon icon-project"><i class="fas fa-building"></i></div>
                <h2>Project Details</h2>
            </div>
            <div class="po-card-body">
                <div class="detail-list">
                    <div class="detail-row">
                        <span class="dl">Project</span>
                        <span class="dv"><?= renderProjectBadge($po['project_name'], $po['project_id']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="dl">Order Date</span>
                        <span class="dv"><?= formatDate($po['order_date']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="dl">Expected Delivery</span>
                        <span class="dv <?= $po['expected_date'] ? 'warn' : 'muted' ?>">
                            <?= $po['expected_date'] ? formatDate($po['expected_date']) : 'Immediate' ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="dl">Payment Terms</span>
                        <span class="dv"><?= htmlspecialchars($po['payment_terms'] ?? 'Standard') ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Order Items ───────────────────────── -->
    <div class="po-card">
        <div class="po-card-head">
            <div class="card-head-icon icon-items"><i class="fas fa-boxes"></i></div>
            <h2>Order Items</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Material</th>
                        <th class="th-c">Unit</th>
                        <th class="th-c">Quantity</th>
                        <th class="th-r">Rate</th>
                        <th class="th-r">GST</th>
                        <th class="th-r">Total</th>
                        <th class="th-c">Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($po['items'] as $i => $item): ?>
                        <tr>
                            <td style="color:var(--ink-mute);font-size:0.75rem;width:36px"><?= $i + 1 ?></td>
                            <td><span class="mat-name"><?= htmlspecialchars($item['material_name']) ?></span></td>
                            <td class="td-r"><span class="mat-unit"><?= strtoupper($item['unit']) ?></span></td>
                            <td class="td-r td-qty"><?= number_format($item['quantity'], 2) ?></td>
                            <td class="td-r td-rate"><?= formatCurrency($item['rate']) ?></td>
                            <td class="td-r td-rate">
                                <span style="display:block;font-size:0.75rem;color:var(--ink-mute)"><?= number_format($item['tax_rate'], 0) ?>%</span>
                                <span style="font-weight:600;color:var(--ink-soft)"><?= formatCurrency($item['tax_amount']) ?></span>
                            </td>
                            <td class="td-r td-total"><?= formatCurrency($item['total_amount']) ?></td>
                            <td class="td-c">
                                <?php if ($item['received_qty'] == 0): ?>
                                    <span class="recv-badge recv-none">Pending</span>
                                <?php elseif ($item['received_qty'] < $item['quantity']): ?>
                                    <span class="recv-badge recv-partial"><?= $item['received_qty'] ?> / <?= $item['quantity'] ?></span>
                                <?php else: ?>
                                    <span class="recv-badge recv-full"><i class="fas fa-check"></i> Full</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="grand-total-row">
            <span class="gt-label">Grand Total</span>
            <span class="gt-amount"><?= formatCurrency($po['total_amount']) ?></span>
        </div>
    </div>

    <!-- ── Notes ─────────────────────────────── -->
    <?php if ($po['notes']): ?>
    <div class="po-card">
        <div class="po-card-head">
            <div class="card-head-icon icon-notes"><i class="fas fa-file-alt"></i></div>
            <h2>Notes &amp; Instructions</h2>
        </div>
        <div class="po-card-body">
            <p class="notes-text"><?= htmlspecialchars($po['notes']) ?></p>
        </div>
    </div>

    <?php if ($po_terms): ?>
    <div class="po-card">
        <div class="po-card-head">
            <div class="card-head-icon icon-notes" style="background:#fefce8;color:#a16207"><i class="fas fa-gavel"></i></div>
            <h2>Terms & Conditions</h2>
        </div>
        <div class="po-card-body">
            <p class="notes-text" style="font-size:0.82rem"><?= nl2br(htmlspecialchars($po_terms)) ?></p>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>