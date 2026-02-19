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
$page_title = 'Cancelled Bookings';
$current_page = 'booking';

$sql = "SELECT bc.*, 
               b.booking_date, b.agreement_value, b.project_id, b.customer_id,
               f.flat_no,
               p.name as customer_name, p.mobile as customer_mobile,
               pr.project_name,
               u.full_name as processed_by_name
        FROM booking_cancellations bc
        JOIN bookings b ON bc.booking_id = b.id
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON bc.processed_by = u.id
        ORDER BY bc.cancellation_date DESC, bc.created_at DESC";

$stmt = $db->query($sql);
$cancellations = $stmt->fetchAll();

$total_refunded        = 0;
$total_deducted        = 0;
$total_cancelled_value = 0;

foreach ($cancellations as $c) {
    $total_refunded        += $c['refund_amount'];
    $total_deducted        += $c['deduction_amount'];
    $total_cancelled_value += $c['agreement_value'];
}

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
    --accent:    #2a58b5ff;
    --accent-bg: #fdf8f3;
}

body {
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
}

.page-wrap {
    max-width: 1260px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ════════════════════════════════════════
   1. PAGE ENTRANCE ANIMATIONS
   ════════════════════════════════════════ */

/* Header slides down */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
    opacity: 0;
    transform: translateY(-14px);
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

@keyframes fadeDown {
    to { opacity: 1; transform: translateY(0); }
}

/* Stats stagger up */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    transition: box-shadow 0.18s ease, transform 0.18s ease;
    opacity: 0;
    transform: translateY(16px);
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) forwards;
}
.stat-card:nth-child(1) { animation-delay: 0.12s; }
.stat-card:nth-child(2) { animation-delay: 0.20s; }
.stat-card:nth-child(3) { animation-delay: 0.28s; }
.stat-card:nth-child(4) { animation-delay: 0.36s; }
.stat-card:hover { box-shadow: 0 6px 20px rgba(26,23,20,0.1); transform: translateY(-2px); }

/* Main card fades up after stats */
.main-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    transform: translateY(20px);
    animation: fadeUp 0.45s cubic-bezier(0.22,1,0.36,1) 0.44s forwards;
}

@keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════
   2. TABLE ROW STAGGER (slide in from left)
   ════════════════════════════════════════ */
.data-table tbody tr.row-anim {
    opacity: 0;
    transform: translateX(-12px);
    animation: rowSlide 0.36s cubic-bezier(0.22,1,0.36,1) forwards;
}

@keyframes rowSlide {
    to { opacity: 1; transform: translateX(0); }
}

/* ── Page Header internals ───────────── */
.page-header .eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--ink);
    margin: 0;
}
.page-header h1 em { color: var(--accent); font-style: italic; }

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--ink-soft);
    text-decoration: none;
    padding: 0.45rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    background: white;
    transition: all 0.18s ease;
    white-space: nowrap;
}
.back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

/* ── Stat internals ──────────────────── */
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.stat-icon.purple { background: #ede9fe; color: #7c3aed; }
.stat-icon.amber  { background: #fef3c7; color: #d97706; }
.stat-icon.red    { background: #fee2e2; color: #dc2626; }
.stat-icon.green  { background: #d1fae5; color: #059669; }

.stat-label {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.25rem;
}
.stat-value {
    font-family: 'Fraunces', serif;
    font-size: 1.5rem; font-weight: 700; line-height: 1; color: var(--ink);
}

/* ── Card head ───────────────────────── */
.card-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.1rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.card-icon {
    width: 30px; height: 30px; border-radius: 7px;
    background: #fee2e2; color: #dc2626;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; flex-shrink: 0;
}
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;
}
.card-head .sub { margin-left: auto; font-size: 0.72rem; color: var(--ink-mute); }

/* ── Table ───────────────────────────── */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table thead tr { background: #f5f1eb; border-bottom: 1.5px solid var(--border); }
.data-table thead th {
    padding: 0.75rem 1rem;
    text-align: left; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.data-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s ease;
    cursor: pointer;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #fdf8f3; }
.data-table td { padding: 0.9rem 1rem; vertical-align: middle; color: var(--ink-soft); }

/* ── Table Cells ─────────────────────── */
.date-cell { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); white-space: nowrap; }

.entity-cell { display: flex; align-items: center; gap: 0.6rem; white-space: nowrap; }


.entity-name { font-weight: 600; color: var(--ink); line-height: 1.2; }
.entity-sub  { font-size: 0.7rem; color: var(--ink-mute); margin-top: 1px; }

.flat-badge {
    display: inline-block; font-size: 0.7rem; font-weight: 700;
    letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--ink-soft); background: var(--cream);
    border: 1px solid var(--border); padding: 0.2rem 0.55rem;
    border-radius: 4px; white-space: nowrap;
}

.amount       { font-weight: 600; color: var(--ink); white-space: nowrap; }
.amount.blue  { color: #2563eb; }
.amount.green { color: #059669; }
.amount.red   { color: #dc2626; }

.proc-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.7rem; font-weight: 600; color: var(--ink-soft);
    background: var(--cream); border: 1px solid var(--border);
    padding: 0.2rem 0.6rem; border-radius: 20px; white-space: nowrap;
}

.view-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 7px;
    border: 1.5px solid var(--border); background: white;
    color: var(--ink-soft); text-decoration: none;
    font-size: 0.75rem; transition: all 0.16s ease;
}
.view-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); transform: translateX(2px); }

/* New Cell Styles */
.cust-flat-cell { display: flex; flex-direction: column; line-height: 1.3; }
.cust-name { font-weight: 700; color: var(--ink); font-size: 0.9rem; }
.cust-sub  { font-size: 0.75rem; color: var(--ink-mute); margin-top: 2px; font-weight: 500; }
.mobile-cell { font-family: 'DM Sans', sans-serif; font-weight: 500; color: var(--ink-soft); font-size: 0.85rem; letter-spacing: 0.02em; }

/* ════════════════════════════════════════
   3. TOOLTIP — reason popover on hover
   ════════════════════════════════════════ */
.reason-wrap {
    position: relative;
    display: inline-block;
    max-width: 160px;
}
.reason-text {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8rem;
    color: var(--ink-soft);
    cursor: default;
    border-bottom: 1px dashed var(--border);
    padding-bottom: 1px;
}
.reason-tooltip {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(6px);
    background: var(--ink);
    color: white;
    font-size: 0.75rem;
    line-height: 1.55;
    padding: 0.6rem 0.9rem;
    border-radius: 8px;
    white-space: normal;
    width: 230px;
    z-index: 200;
    pointer-events: none;
    box-shadow: 0 8px 24px rgba(26,23,20,0.22);
    opacity: 0;
    transform: translateX(-50%) translateY(6px);
    transition: opacity 0.22s ease, transform 0.22s ease;
}
.reason-tooltip::after {
    content: '';
    position: absolute;
    top: 100%; left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: var(--ink);
}
.reason-wrap:hover .reason-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* ════════════════════════════════════════
   4. SLIDE-IN DETAIL MODAL
   ════════════════════════════════════════ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(26,23,20,0.45);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.28s ease;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}
.modal-overlay.open {
    opacity: 1;
    pointer-events: all;
}

.modal-panel {
    width: 420px;
    max-width: 95vw;
    height: 100vh;
    background: var(--surface);
    border-left: 1.5px solid var(--border);
    box-shadow: -8px 0 40px rgba(26,23,20,0.14);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateX(100%);
    transition: transform 0.38s cubic-bezier(0.22,1,0.36,1);
}
.modal-overlay.open .modal-panel {
    transform: translateX(0);
}

.modal-header {
    padding: 1.4rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
    display: flex; align-items: center; gap: 0.75rem;
    flex-shrink: 0;
}
.modal-header-icon {
    width: 34px; height: 34px; border-radius: 8px;
    background: #fee2e2; color: #dc2626;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}
.modal-title {
    font-family: 'Fraunces', serif;
    font-size: 1.05rem; font-weight: 700; color: var(--ink);
    margin: 0; flex: 1;
}
.modal-close {
    width: 28px; height: 28px; border-radius: 6px;
    border: 1.5px solid var(--border); background: white;
    color: var(--ink-soft);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    transition: all 0.16s ease; flex-shrink: 0;
}
.modal-close:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.modal-body { flex: 1; overflow-y: auto; padding: 1.6rem; }

/* Sections stagger in after panel slides */
.modal-section {
    margin-bottom: 1.6rem;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.modal-overlay.open .modal-section:nth-child(1) { opacity:1; transform:none; transition-delay:0.22s; }
.modal-overlay.open .modal-section:nth-child(2) { opacity:1; transform:none; transition-delay:0.28s; }
.modal-overlay.open .modal-section:nth-child(3) { opacity:1; transform:none; transition-delay:0.34s; }
.modal-overlay.open .modal-section:nth-child(4) { opacity:1; transform:none; transition-delay:0.40s; }
.modal-overlay.open .modal-section:nth-child(5) { opacity:1; transform:none; transition-delay:0.46s; }

.modal-sec-label {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.75rem;
    padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-lt);
}
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }

.modal-field-label {
    font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--ink-mute); margin-bottom: 0.2rem;
}
.modal-field-value { font-size: 0.9rem; font-weight: 600; color: var(--ink); }
.modal-field-value.blue  { color: #2563eb; }
.modal-field-value.green { color: #059669; }
.modal-field-value.red   { color: #dc2626; }
.modal-field-value.serif {
    font-family: 'Fraunces', serif;
    font-size: 1.1rem;
}

.modal-entity {
    display: flex; align-items: center; gap: 0.75rem;
    background: var(--cream); border: 1.5px solid var(--border);
    border-radius: 10px; padding: 0.85rem 1rem;
}
.modal-entity .name { font-weight: 700; font-size: 0.9rem; color: var(--ink); }
.modal-entity .sub  { font-size: 0.75rem; color: var(--ink-mute); margin-top: 2px; }

.modal-reason-box {
    background: #fdf8f3; border: 1.5px solid #e0c9b5;
    border-radius: 8px; padding: 0.85rem 1rem;
    font-size: 0.875rem; color: var(--ink-soft); line-height: 1.6;
}

.modal-footer {
    padding: 1.1rem 1.6rem;
    border-top: 1.5px solid var(--border-lt);
    background: #fdfcfa; flex-shrink: 0;
    opacity: 0;
    transition: opacity 0.3s ease 0.5s;
}
.modal-overlay.open .modal-footer { opacity: 1; }

.modal-footer-link {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%; padding: 0.7rem 1rem;
    background: var(--ink); color: white;
    border-radius: 8px; font-size: 0.875rem; font-weight: 600;
    text-decoration: none;
    transition: all 0.18s ease; letter-spacing: 0.02em;
}
.modal-footer-link:hover {
    background: var(--accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(181,98,42,0.28);
}

/* ── Empty state ─────────────────────── */
.empty-state { text-align: center; padding: 5rem 2rem; color: var(--ink-mute); }
.empty-state .ei { font-size: 3rem; opacity: 0.25; margin-bottom: 1rem; display: block; }
.empty-state .et { font-family: 'Fraunces', serif; font-size: 1.1rem; color: var(--ink-soft); margin-bottom: 0.4rem; }
.empty-state .es { font-size: 0.875rem; }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Bookings</div>
            <h1>Cancelled <em>Bookings</em></h1>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Bookings
        </a>
    </div>

    <!-- ── Stats ────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-ban"></i></div>
            <div class="stat-body">
                <div class="stat-label">Total Cancellations</div>
                <div class="stat-value"><?= count($cancellations) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-body">
                <div class="stat-label">Cancelled Value</div>
                <div class="stat-value"><?= formatCurrencyShort($total_cancelled_value) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="stat-body">
                <div class="stat-label">Total Refunded</div>
                <div class="stat-value"><?= formatCurrencyShort($total_refunded) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-scissors"></i></div>
            <div class="stat-body">
                <div class="stat-label">Total Deducted</div>
                <div class="stat-value"><?= formatCurrencyShort($total_deducted) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Main Table Card ───────────── -->
    <div class="main-card">
        <div class="card-head">
            <div class="card-icon"><i class="fas fa-ban"></i></div>
            <h2>Cancelled Bookings</h2>
            <span class="sub">Click any row to preview details</span>
        </div>

        <div class="table-wrap">
            <?php if (empty($cancellations)): ?>
                <div class="empty-state">
                    <span class="ei"><i class="fas fa-inbox"></i></span>
                    <div class="et">No Cancelled Bookings</div>
                    <div class="es">There are no cancelled bookings in the system yet.</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer / Flat</th>
                            <th>Mobile</th>
                            <th>Agr. Value</th>
                            <th>Total Paid</th>
                            <th>Deduction</th>
                            <th>Refund</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cancellations as $i => $c):

                            $delay_ms  = 50 + $i * 45;

                            $modal_data = json_encode([
                                'id'           => $c['id'],
                                'cancel_date'  => date('d M Y', strtotime($c['cancellation_date'])),
                                'booking_date' => date('d M Y', strtotime($c['booking_date'])),
                                'project'      => $c['project_name'],
                                'flat'         => $c['flat_no'],
                                'customer'     => $c['customer_name'],
                                'mobile'       => $c['customer_mobile'],
                                'agr_value'    => formatCurrencyShort($c['agreement_value']),
                                'total_paid'   => formatCurrencyShort($c['total_paid']),
                                'deduction'    => formatCurrencyShort($c['deduction_amount']),
                                'refund'       => formatCurrencyShort($c['refund_amount']),
                                'reason'       => $c['cancellation_reason'],

                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <tr class="row-anim"
                            style="animation-delay:<?= $delay_ms ?>ms"
                            onclick="openModal(<?= htmlspecialchars($modal_data, ENT_QUOTES) ?>)">

                            <td><span class="date-cell"><?= date('d M Y', strtotime($c['cancellation_date'])) ?></span></td>

                            <td>
                                <div class="cust-flat-cell">
                                    <span class="cust-name"><?= htmlspecialchars($c['customer_name']) ?></span>
                                    <div style="margin-top:2px; display:flex; align-items:center; gap:5px;">
                                        <?= renderProjectBadge($c['project_name'], $c['project_id']) ?>
                                        <span class="cust-sub" style="margin:0;">&ndash; <?= htmlspecialchars($c['flat_no']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="mobile-cell"><?= htmlspecialchars($c['customer_mobile']) ?></span>
                            </td>

                            <td><span class="amount"><?= formatCurrencyShort($c['agreement_value']) ?></span></td>
                            <td><span class="amount blue"><?= formatCurrencyShort($c['total_paid']) ?></span></td>
                            <td><span class="amount green"><?= formatCurrencyShort($c['deduction_amount']) ?></span></td>
                            <td><span class="amount red"><?= formatCurrencyShort($c['refund_amount']) ?></span></td>

                            <td>
                                <div class="reason-wrap">
                                    <span class="reason-text"><?= htmlspecialchars($c['cancellation_reason']) ?></span>
                                    <div class="reason-tooltip"><?= htmlspecialchars($c['cancellation_reason']) ?></div>
                                </div>
                            </td>

                            <td onclick="event.stopPropagation()">
                                <a href="cancellation_details.php?id=<?= $c['id'] ?>" class="view-btn" title="Open full page">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ════════════════════════════════════
     DETAIL MODAL
     ════════════════════════════════════ -->
<div class="modal-overlay" id="detailModal" onclick="closeOnOverlay(event)">
    <div class="modal-panel">

        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-ban"></i></div>
            <h2 class="modal-title">Cancellation Details</h2>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>

        <div class="modal-body" id="modalBody"></div>

        <div class="modal-footer">
            <a href="#" id="modalFullLink" class="modal-footer-link">
                <i class="fas fa-external-link-alt"></i> View Full Details Page
            </a>
        </div>

    </div>
</div>

<script>
const overlay  = document.getElementById('detailModal');
const modalBody = document.getElementById('modalBody');
const fullLink  = document.getElementById('modalFullLink');

function openModal(d) {
    fullLink.href = `cancellation_details.php?id=${d.id}`;

    modalBody.innerHTML = `
        <div class="modal-section">
            <div class="modal-sec-label">Customer</div>
            <div class="modal-entity">
                <div>
                    <div class="name">${escHtml(d.customer)}</div>
                    <div class="sub">${escHtml(d.mobile)}</div>
                </div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-sec-label">Booking Info</div>
            <div class="modal-grid">
                <div>
                    <div class="modal-field-label">Project</div>
                    <div class="modal-field-value">${escHtml(d.project)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Flat No.</div>
                    <div class="modal-field-value">${escHtml(d.flat)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Booking Date</div>
                    <div class="modal-field-value">${escHtml(d.booking_date)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Cancelled On</div>
                    <div class="modal-field-value">${escHtml(d.cancel_date)}</div>
                </div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-sec-label">Financial Summary</div>
            <div class="modal-grid">
                <div>
                    <div class="modal-field-label">Agreement Value</div>
                    <div class="modal-field-value serif">${escHtml(d.agr_value)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Total Paid</div>
                    <div class="modal-field-value serif blue">${escHtml(d.total_paid)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Deduction</div>
                    <div class="modal-field-value serif green">${escHtml(d.deduction)}</div>
                </div>
                <div>
                    <div class="modal-field-label">Refund</div>
                    <div class="modal-field-value serif red">${escHtml(d.refund)}</div>
                </div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-sec-label">Cancellation Reason</div>
            <div class="modal-reason-box">${escHtml(d.reason) || '—'}</div>
        </div>


    `;

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

function closeOnOverlay(e) {
    if (e.target === overlay) closeModal();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>