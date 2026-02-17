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
$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Bill ID missing');
    redirect('modules/vendors/index.php');
}

$bill = $db->query("SELECT b.*, p.name as vendor_name, p.vendor_type, p.gst_number, p.address 
                    FROM bills b 
                    JOIN parties p ON b.party_id = p.id 
                    WHERE b.id = ?", [$id])->fetch();

if (!$bill) {
    setFlashMessage('error', 'Bill not found');
    redirect('modules/vendors/index.php');
}

$items = $db->query("SELECT 
                        ci.quantity, ci.rate, 
                        m.material_name, m.unit, 
                        c.challan_no, c.challan_date, 
                        pr.project_name
                     FROM challan_items ci
                     JOIN materials m ON ci.material_id = m.id
                     JOIN challans c ON ci.challan_id = c.id
                     LEFT JOIN projects pr ON c.project_id = pr.id
                     WHERE c.bill_id = ?
                     ORDER BY c.challan_date DESC, c.challan_no DESC", [$id])->fetchAll();

// Calculate GST % based on totals
$gst_pct = ($bill['taxable_amount'] > 0) ? round(($bill['tax_amount'] / $bill['taxable_amount']) * 100) : 0;

$page_title = 'View Vendor Bill #' . $bill['bill_no'];
include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    --accent-lt: #eff4ff;
    --accent-md: #c7d9f9;
    --accent-bg: #f0f5ff;
    --accent-dk: #1e429f;
    --green:     #059669;
    --green-lt:  #d1fae5;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

/* ── Wrapper ──────────────────────── */
.pw { max-width: 900px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

/* ── Animations ───────────────────── */
@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes cardIn { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-6px);  } to { opacity:1; transform:translateX(0); } }

/* ── Page header ──────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    margin-bottom: 2.25rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    opacity: 0;
    animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size: 0.67rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.28rem; }
.page-header h1 { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: var(--ink); margin: 0; line-height: 1.1; }
.page-header h1 em { font-style: italic; color: var(--accent); }

.hdr-right { display: flex; align-items: center; gap: 0.6rem; }
.back-link {
    display: inline-flex; align-items: center; gap: 0.42rem;
    padding: 0.52rem 1rem; font-size: 0.82rem; font-weight: 500;
    color: var(--ink-soft); border: 1.5px solid var(--border);
    border-radius: 7px; background: white; text-decoration: none; transition: all 0.18s;
}
.back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Status badge ─────────────────── */
.status-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.68rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;
    padding: 0.28rem 0.75rem; border-radius: 20px;
}
.status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.status-badge.pending  { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
.status-badge.approved { background: var(--accent-lt); color: var(--accent-dk); border: 1px solid var(--accent-md); }
.status-badge.paid     { background: var(--green-lt);  color: var(--green); border: 1px solid #6ee7b7; }
.status-badge.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.status-badge.partial  { background: #ede9fe; color: #7c3aed; border: 1px solid #ddd6fe; }

/* ── Cards ────────────────────────── */
.card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden; margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0; animation: cardIn 0.42s cubic-bezier(0.22,1,0.36,1) both;
}
.card.c1 { animation-delay: 0.10s; }
.card.c2 { animation-delay: 0.18s; }
.card.c3 { animation-delay: 0.26s; }
.card.c4 { animation-delay: 0.34s; }

.card-head {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.ch-ic {
    width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.78rem;
}
.ch-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.ch-ic.green  { background: var(--green-lt);  color: var(--green); }
.ch-ic.orange { background: #fdf8f3; color: #b5622a; border: 1px solid #e0c9b5; }
.ch-ic.purple { background: #ede9fe; color: #7c3aed; }
.ch-ic.gray   { background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border); }

.card-head h2 {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
    color: var(--ink); margin: 0; flex: 1;
}
.card-body { padding: 1.5rem; }

/* ── Hero card (bill summary) ─────── */
.hero-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden; margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0; animation: cardIn 0.42s cubic-bezier(0.22,1,0.36,1) 0.08s both;
}
.hero-inner {
    display: flex; align-items: stretch;
    gap: 0; flex-wrap: wrap;
}
.hero-main {
    flex: 1; min-width: 260px;
    padding: 2rem 2rem 2rem;
    border-right: 1.5px solid var(--border-lt);
}
.hero-bill-no {
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
    text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.4rem;
}
.hero-title {
    font-family: 'Fraunces', serif; font-size: 1.65rem; font-weight: 700;
    color: var(--ink); margin: 0 0 0.6rem; line-height: 1.15;
}
.hero-title em { font-style: italic; color: var(--accent); }
.hero-meta { font-size: 0.82rem; color: var(--ink-mute); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.hero-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: var(--border); }
.hero-meta strong { color: var(--ink-soft); }

.hero-amount {
    min-width: 220px;
    padding: 2rem;
    background: var(--cream);
    display: flex; flex-direction: column; justify-content: center; align-items: flex-end;
    text-align: right;
}
.ha-lbl { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.13em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.4rem; }
.ha-val { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 700; color: var(--ink); line-height: 1; }
.ha-sub { font-size: 0.72rem; color: var(--ink-mute); margin-top: 0.4rem; }

/* ── Info grid ────────────────────── */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media (max-width: 580px) { .info-grid { grid-template-columns: 1fr; } }

.info-block {}
.ib-label {
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: var(--ink-mute);
    margin-bottom: 0.65rem; padding-bottom: 0.4rem;
    border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.38rem;
}
.ib-name {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
    color: var(--ink); margin-bottom: 0.4rem;
}
.ib-line {
    font-size: 0.82rem; color: var(--ink-soft); line-height: 1.7;
    display: flex; align-items: baseline; gap: 0.4rem;
}
.ib-line .il-key { font-weight: 600; color: var(--ink-mute); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; min-width: 52px; flex-shrink: 0; }
.ib-line .il-val { color: var(--ink-soft); }
.ib-line .il-val.mono { font-family: 'Courier New', monospace; font-size: 0.8rem; color: var(--ink); }

/* attachment link */
.attach-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    margin-top: 0.5rem; padding: 0.38rem 0.75rem;
    background: var(--accent-lt); color: var(--accent); border: 1px solid var(--accent-md);
    border-radius: 6px; font-size: 0.75rem; font-weight: 700;
    text-decoration: none; transition: all 0.18s;
}
.attach-link:hover { background: var(--accent); color: white; text-decoration: none; }

/* ── Challans table ───────────────── */
.tbl-wrap { border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; }
.ch-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.ch-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.ch-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.ch-table thead th.al-r { text-align: right; }
.ch-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.ch-table tbody tr:last-child { border-bottom: none; }
.ch-table tbody tr:hover { background: #f4f7fd; }
.ch-table tbody tr.row-in { animation: rowIn 0.26s cubic-bezier(0.22,1,0.36,1) forwards; }
.ch-table td { padding: 0.78rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.ch-table td.al-r { text-align: right; }

.challan-no { font-weight: 700; color: var(--ink); font-size: 0.875rem; }
.challan-pill {
    display: inline-block; padding: 0.18rem 0.6rem; border-radius: 20px;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.04em;
    background: var(--accent-lt); color: var(--accent-dk); border: 1px solid var(--accent-md);
}
.proj-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.68rem; font-weight: 600;
    background: var(--cream); color: var(--ink-soft); border: 1px solid var(--border);
}
.num-cell { font-family: 'Fraunces', serif; font-weight: 700; color: var(--ink); }

/* empty state */
.empty-state {
    text-align: center; padding: 2.5rem 1.5rem;
    border: 2px dashed var(--border); border-radius: 10px;
    color: var(--ink-mute); font-size: 0.875rem;
}
.empty-state .es-icon { font-size: 1.75rem; display: block; margin-bottom: 0.6rem; opacity: 0.25; }

/* ── Payment summary ──────────────── */
.pay-table { max-width: 360px; margin-left: auto; }
.pay-row {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 0.65rem 0; border-bottom: 1px solid var(--border-lt);
}
.pay-row:last-child { border-bottom: none; }
.pay-row .pr-lbl { font-size: 0.82rem; color: var(--ink-soft); font-weight: 500; }
.pay-row .pr-val { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); }
.pay-row.grand {
    margin-top: 0.25rem; padding: 0.85rem 1rem;
    background: var(--cream); border: 1.5px solid var(--border); border-radius: 9px;
    border-bottom: none;
}
.pay-row.grand .pr-lbl { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-mute); }
.pay-row.grand .pr-val { font-size: 1.5rem; color: var(--accent); }

/* ── Action bar ───────────────────── */
.action-bar {
    display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;
    padding: 1rem 1.5rem; background: #fafbff; border-top: 1.5px solid var(--border-lt);
}
.btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.6rem 1.2rem; border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; text-decoration: none; border: 1.5px solid transparent; transition: all 0.18s;
}
.btn-ghost { background: white; border-color: var(--border); color: var(--ink-soft); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }
.btn-primary { background: var(--ink); color: white; border-color: var(--ink); }
.btn-primary:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.3); text-decoration: none; }
.btn-primary:active { transform: translateY(0); }
.btn-danger { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
.btn-danger:hover { background: #b91c1c; color: white; border-color: #b91c1c; }

/* ── Confirmation Modal ───────────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(26,23,20,0.4); backdrop-filter: blur(4px); z-index: 999;
    display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.25s ease;
}

.modal-overlay.active { opacity: 1; visibility: visible; }

.conf-modal { background: white; border-radius: 18px; padding: 2rem 1.75rem; max-width: 600px; width: 90%;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12); opacity: 0; transform: translateY(18px); transition: 
        transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.25s ease;
}

.modal-overlay.active .conf-modal {
    opacity: 1;
    transform: translateY(0);
}


.cm-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 1.25rem;
    display: flex; align-items: center; justify-content: center; font-size: 1.75rem;
}
.cm-title { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); margin: 0 0 0.5rem; text-align: center; }
.cm-text { font-size: 0.95rem; color: var(--ink-soft); margin: 0 0 2rem; text-align: center; line-height: 1.5; }

.cm-actions { display: flex; gap: 1rem; }
.cm-btn { flex: 1; padding: 1rem; border-radius: 12px; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; transition: transform 0.1s; }
.cm-btn:active { transform: scale(0.98); }
.cm-cancel { background: var(--border-lt); color: var(--ink); }
.cm-confirm { color: white; }

@media (min-width: 640px) {
    .modal-overlay { align-items: center; justify-content: center; padding: 1rem; }
    .conf-modal { position: relative; border-radius: 20px; transform: scale(0.95); opacity: 0; bottom: auto; }
    .modal-overlay.active .conf-modal { transform: scale(1); opacity: 1; }
}
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Vendors &rsaquo; Bills</div>
            <h1>Bill <em>#<?= htmlspecialchars($bill['bill_no']) ?></em></h1>
        </div>
        <div class="hdr-right">
            <span class="status-badge <?= htmlspecialchars($bill['status']) ?>">
                APPROVAL: <?= strtoupper(htmlspecialchars($bill['status'])) ?>
            </span>
            <span class="status-badge <?= htmlspecialchars($bill['payment_status']) ?>">
                PAYMENT: <?= strtoupper(htmlspecialchars($bill['payment_status'])) ?>
            </span>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- ── Hero: Bill Summary ──────── -->
    <div class="hero-card">
        <div class="hero-inner">
            <div class="hero-main">
                <div class="hero-bill-no">Invoice &rsaquo; <?= htmlspecialchars($bill['bill_no']) ?></div>
                <div class="hero-title">
                    <?= htmlspecialchars($bill['vendor_name']) ?>
                </div>
                <div class="hero-meta">
                    <span><?= formatDate($bill['bill_date']) ?></span>
                    <span class="dot"></span>
                    <span><strong>Ref</strong> #<?= $bill['id'] ?></span>
                    <span class="dot"></span>
                    <span><?= ucfirst(htmlspecialchars($bill['vendor_type'])) ?></span>
                </div>
            </div>
            <div class="hero-amount">
                <div class="ha-lbl">Total Amount</div>
                <div class="ha-val"><?= formatCurrency($bill['amount']) ?></div>
                <div class="ha-sub">incl. taxes &amp; levies</div>
            </div>
        </div>
    </div>

    <!-- ── Card 1: Vendor & Meta ───── -->
    <div class="card c1">
        <div class="card-head">
            <div class="ch-ic blue"><i class="fas fa-truck"></i></div>
            <h2>Vendor &amp; Reference</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">

                <!-- Vendor details -->
                <div class="info-block">
                    <div class="ib-label"><i class="fas fa-user-tag"></i> Vendor Details</div>
                    <div class="ib-name"><?= htmlspecialchars($bill['vendor_name']) ?></div>
                    <?php if (!empty($bill['address'])): ?>
                        <div class="ib-line">
                            <span class="il-key">Address</span>
                            <span class="il-val"><?= nl2br(htmlspecialchars($bill['address'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="ib-line">
                        <span class="il-key">GST</span>
                        <span class="il-val mono"><?= htmlspecialchars($bill['gst_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="ib-line">
                        <span class="il-key">Type</span>
                        <span class="il-val"><?= ucfirst(htmlspecialchars($bill['vendor_type'])) ?></span>
                    </div>
                </div>

                <!-- Meta / attachment -->
                <div class="info-block">
                    <div class="ib-label"><i class="fas fa-file-contract"></i> Bill Reference</div>
                    <div class="ib-line">
                        <span class="il-key">Bill No</span>
                        <span class="il-val mono"><?= htmlspecialchars($bill['bill_no']) ?></span>
                    </div>
                    <div class="ib-line">
                        <span class="il-key">Date</span>
                        <span class="il-val"><?= formatDate($bill['bill_date']) ?></span>
                    </div>
                    <div class="ib-line">
                        <span class="il-key">Ref ID</span>
                        <span class="il-val mono">#<?= $bill['id'] ?></span>
                    </div>
                    <div class="ib-line">
                        <span class="il-key">Status</span>
                        <span class="il-val">
                            <span class="status-badge <?= htmlspecialchars($bill['status']) ?>">
                                APPROVAL: <?= strtoupper(htmlspecialchars($bill['status'])) ?>
                            </span>
                            <span class="status-badge <?= htmlspecialchars($bill['payment_status']) ?>">
                                PAYMENT: <?= strtoupper(htmlspecialchars($bill['payment_status'])) ?>
                            </span>
                        </span>
                    </div>
                    <?php if (!empty($bill['file_path'])): ?>
                        <a href="<?= BASE_URL . htmlspecialchars($bill['file_path']) ?>" target="_blank" class="attach-link">
                            <i class="fas fa-paperclip"></i> View Attachment
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Card 2: Linked Challans ─── -->
    <div class="card c2">
        <div class="card-head">
            <div class="ch-ic orange"><i class="fas fa-link"></i></div>
            <h2>Challan Details
                <?php if (!empty($items)): ?>
                    <span style="font-size:0.62rem;font-weight:800;padding:0.15rem 0.55rem;border-radius:20px;background:var(--accent);color:white;font-family:'DM Sans',sans-serif;margin-left:0.25rem;">
                        <?= count($items) ?>
                    </span>
                <?php endif; ?>
            </h2>
        </div>
        <div class="card-body" style="padding: <?= empty($items) ? '1.5rem' : '0'; ?>">

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <span class="es-icon"><i class="fas fa-unlink"></i></span>
                    No linked items found for this bill.
                </div>
            <?php else: ?>
                <div class="tbl-wrap">
                    <table class="ch-table">
                        <thead>
                            <tr>
                                <th>Challan No</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th class="al-r">Qty</th>
                                <th class="al-r">Rate</th>
                                <th class="al-r">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): 
                                $amount = $item['quantity'] * $item['rate'];
                            ?>
                            <tr class="row-in" style="animation-delay: <?= $i * 35 ?>ms;">
                                <td>
                                    <span class="challan-pill"><?= htmlspecialchars($item['challan_no']) ?></span>
                                </td>
                                <td style="color:var(--ink-soft);"><?= formatDate($item['challan_date']) ?></td>
                                <td>
                                    <?php if (!empty($item['project_name'])): ?>
                                        <span class="proj-pill">
                                            <i class="fas fa-building" style="font-size:0.58rem;"></i>
                                            <?= htmlspecialchars($item['project_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--ink-mute);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600; color:var(--ink);">
                                    <?= htmlspecialchars($item['material_name']) ?>
                                </td>
                                <td style="font-size:0.8rem; text-transform:uppercase;"><?= htmlspecialchars($item['unit']) ?></td>
                                <td class="al-r num-cell"><?= floatval($item['quantity']) ?></td>
                                <td class="al-r num-cell"><?= floatval($item['rate']) ?></td>
                                <td class="al-r num-cell"><?= formatCurrency($amount) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ── Card 3: Payment Summary ─── -->
    <div class="card c3">
        <div class="card-head">
            <div class="ch-ic green"><i class="fas fa-rupee-sign"></i></div>
            <h2>Payment Summary</h2>
        </div>
        <div class="card-body">
            <div class="pay-table">
                <div class="pay-row">
                    <span class="pr-lbl">Total Amount</span>
                    <span class="pr-val"><?= formatCurrency($bill['taxable_amount']) ?></span>
                </div>
                <div class="pay-row">
                    <span class="pr-lbl">GST (<?= $gst_pct ?>%)</span>
                    <span class="pr-val"><?= formatCurrency($bill['tax_amount']) ?></span>
                </div>
                <div class="pay-row grand">
                    <span class="pr-lbl">Grand Total</span>
                    <span class="pr-val"><?= formatCurrency($bill['amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- Action bar -->
        <div class="action-bar no-print">
            <button onclick="window.print()" class="btn btn-ghost">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if ($bill['status'] === 'pending'): ?>
                <button onclick="updateStatus('rejected')" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button onclick="updateStatus('approved')" class="btn btn-primary">
                    <i class="fas fa-check"></i> Approve Bill
                </button>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Hidden status form -->
<form id="statusForm" method="POST" action="../bills/update_status.php" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
    <input type="hidden" name="status" id="statusInput">
</form>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confModal">
    <div class="conf-modal">
        <div class="cm-icon" id="cmIcon"></div>
        <h3 class="cm-title" id="cmTitle"></h3>
        <p class="cm-text" id="cmText"></p>
        <div class="cm-actions">
            <button class="cm-btn cm-cancel" onclick="closeModal()">Back</button>
            <button class="cm-btn cm-confirm" id="cmConfirmBtn">Confirm</button>
        </div>
    </div>
</div>

<script>
let currentStatus = '';

function updateStatus(status) {
    currentStatus = status;
    const isApprove = status === 'approved';
    const modal = document.getElementById('confModal');
    const icon = document.getElementById('cmIcon');
    const btn = document.getElementById('cmConfirmBtn');
    
    // Setup Content
    if (isApprove) {
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        icon.style.background = 'var(--green-lt)';
        icon.style.color = 'var(--green)';
        document.getElementById('cmTitle').innerText = 'Approve Bill?';
        document.getElementById('cmText').innerText = 'This action will finalize the bill and update inventory/ledgers. This cannot be undone.';
        btn.style.background = 'var(--green)';
        btn.innerText = 'Yes, Approve It';
    } else {
        icon.innerHTML = '<i class="fas fa-times-circle"></i>';
        icon.style.background = '#fee2e2';
        icon.style.color = '#dc2626';
        document.getElementById('cmTitle').innerText = 'Reject Bill?';
        document.getElementById('cmText').innerText = 'Are you sure you want to reject this bill? The vendor will be notified.';
        btn.style.background = '#dc2626';
        btn.innerText = 'Reject Bill';
    }
    
    // Open Modal
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('confModal').classList.remove('active');
}

document.getElementById('cmConfirmBtn').onclick = function() {
    if (!currentStatus) return;
    document.getElementById('statusInput').value = currentStatus;
    document.getElementById('statusForm').submit();
};

// Close on backdrop click
document.getElementById('confModal').onclick = function(e) {
    if (e.target === this) closeModal();
};
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?> 