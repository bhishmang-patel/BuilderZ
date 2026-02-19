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
$page_title = 'Cancellation Details';
$current_page = 'booking';

$cancellation_id = intval($_GET['id'] ?? 0);

$sql = "SELECT bc.*, 
               b.booking_date, b.agreement_value, b.flat_id,
               f.flat_no, f.area_sqft,
               p.name as customer_name, p.mobile as customer_mobile, p.email as customer_email,
               pr.project_name,
               u.full_name as processed_by_name
        FROM booking_cancellations bc
        JOIN bookings b ON bc.booking_id = b.id
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON bc.processed_by = u.id
        WHERE bc.id = ?";

$stmt = $db->query($sql, [$cancellation_id]);
$cancellation = $stmt->fetch();

if (!$cancellation) {
    setFlashMessage('error', 'Cancellation record not found');
    redirect('modules/booking/index.php');
}

$sql = "SELECT * FROM payments 
        WHERE reference_type = 'booking' AND reference_id = ?
        ORDER BY payment_date ASC";
$stmt = $db->query($sql, [$cancellation['booking_id']]);
$payments = $stmt->fetchAll();

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

/* ── Wrapper ─────────────────────────── */
.page-wrap {
    max-width: 1020px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ════════════════════════════════════════
   ENTRANCE ANIMATIONS
   ════════════════════════════════════════ */
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

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
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 860px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) forwards;
}
.stat-card:nth-child(1) { animation-delay: 0.12s; }
.stat-card:nth-child(2) { animation-delay: 0.20s; }
.stat-card:nth-child(3) { animation-delay: 0.28s; }
.stat-card:nth-child(4) { animation-delay: 0.36s; }

/* ch-card stagger */
.ch-card {
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) forwards;
}
.ch-card:nth-of-type(1) { animation-delay: 0.42s; }
.ch-card:nth-of-type(2) { animation-delay: 0.50s; }
.ch-card:nth-of-type(3) { animation-delay: 0.58s; }
.ch-card:nth-of-type(4) { animation-delay: 0.66s; }
.ch-card:nth-of-type(5) { animation-delay: 0.74s; }

/* ── Page Header ─────────────────────── */
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

.header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.cancelled-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

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

/* ── Stat Cards ──────────────────────── */
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
}
.stat-card:hover { box-shadow: 0 6px 20px rgba(26,23,20,0.09); transform: translateY(-2px); }

.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.stat-icon.amber  { background: #fef3c7; color: #d97706; }
.stat-icon.blue   { background: #dbeafe; color: #2563eb; }
.stat-icon.red    { background: #fee2e2; color: #dc2626; }
.stat-icon.green  { background: #d1fae5; color: #059669; }

.stat-label {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.25rem;
}
.stat-value {
    font-family: 'Fraunces', serif;
    font-size: 1.4rem; font-weight: 700; line-height: 1; color: var(--ink);
}

/* ── Section Cards ───────────────────── */
.ch-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
}

.ch-card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}

.ch-card-icon {
    width: 28px; height: 28px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; color: white;
    flex-shrink: 0;
}
.ch-card-icon.blue  { background: #4f63d2; }
.ch-card-icon.teal  { background: #0d9488; }
.ch-card-icon.red   { background: #dc2626; }
.ch-card-icon.amber { background: #d97706; }
.ch-card-icon.green { background: #059669; }

.ch-card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
}
.ch-card-head .count-tag {
    margin-left: auto;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.6rem; border-radius: 20px;
}

.ch-card-body { padding: 1.5rem; }

/* ── Info Grid ───────────────────────── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
@media (max-width: 580px) { .info-grid { grid-template-columns: 1fr; } }

.info-row {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.85rem 1rem;
    background: #fdfcfa;
    border: 1.5px solid var(--border-lt);
    border-radius: 8px;
}

.info-label {
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink-mute);
}

.info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ink);
    line-height: 1.4;
}

/* ── Payment Table ───────────────────── */
.pay-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.pay-table thead tr {
    background: #f5f1eb;
    border-bottom: 1.5px solid var(--border);
}
.pay-table thead th {
    padding: 0.7rem 1rem;
    text-align: left;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.pay-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s ease;
}
.pay-table tbody tr:last-child { border-bottom: none; }
.pay-table tbody tr:hover { background: #fdfcfa; }
.pay-table td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    color: var(--ink-soft);
}
.pay-table tfoot tr {
    background: #f5f1eb;
    border-top: 1.5px solid var(--border);
}
.pay-table tfoot td {
    padding: 0.85rem 1rem;
    font-size: 0.82rem; font-weight: 700;
    letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--ink-soft);
}
.pay-table tfoot .total-val {
    font-family: 'Fraunces', serif;
    font-size: 1.05rem; color: var(--ink);
}

.pay-num { font-weight: 700; color: var(--ink); }
.pay-amount {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 700; color: #059669;
}

.mode-badge {
    display: inline-block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.05em; text-transform: uppercase;
    color: var(--ink-soft); background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.5rem; border-radius: 4px;
}

.ref-text {
    font-size: 0.8rem;
    color: var(--ink-soft);
    font-family: monospace;
}

/* ── Empty payments ──────────────────── */
.empty-payments {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--ink-mute);
}
.empty-payments .ei { font-size: 2rem; opacity: 0.3; margin-bottom: 0.75rem; display: block; }
.empty-payments .et { font-size: 0.875rem; }

/* ── Remarks box ─────────────────────── */
.remarks-box {
    background: #fdf8f3;
    border: 1.5px solid #e0c9b5;
    border-left: 4px solid var(--accent);
    border-radius: 8px;
    padding: 1rem 1.25rem;
}
.remarks-label {
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.4rem;
}
.remarks-text {
    font-size: 0.875rem;
    color: var(--ink-soft);
    line-height: 1.65;
}

/* ── Action Bar ──────────────────────── */
.action-bar {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-top: 1.5rem;
    margin-top: 0.25rem;
    border-top: 1.5px solid var(--border-lt);

    opacity: 0;
    animation: fadeUp 0.38s cubic-bezier(0.22,1,0.36,1) 0.82s forwards;
}

.btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.65rem 1.4rem;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.18s ease;
    border: none; letter-spacing: 0.01em;
}
.btn-ghost {
    background: white;
    color: var(--ink-soft);
    border: 1.5px solid var(--border);
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

.btn-primary {
    background: var(--ink);
    color: white;
    border: 1.5px solid var(--ink);
}
.btn-primary:hover {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(181,98,42,0.28);
    text-decoration: none;
}
.btn-primary:active { transform: translateY(0); }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Bookings &rsaquo; Cancellations</div>
            <h1>Cancellation <em>Details</em></h1>
        </div>
        <div class="header-right">
            <span class="cancelled-tag">
                <i class="fas fa-ban" style="font-size:0.6rem;"></i>
                Cancelled
            </span>
            <a href="cancelled.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- ── Financial Summary Stats ──── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <div class="stat-label">Agreement Value</div>
                <div class="stat-value"><?= formatCurrency($cancellation['agreement_value']) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="stat-label">Total Received</div>
                <div class="stat-value"><?= formatCurrency($cancellation['total_paid']) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-scissors"></i></div>
            <div>
                <div class="stat-label">Deduction</div>
                <div class="stat-value"><?= formatCurrency($cancellation['deduction_amount']) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-hand-holding-usd"></i></div>
            <div>
                <div class="stat-label">Refund Amount</div>
                <div class="stat-value"><?= formatCurrency($cancellation['refund_amount']) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Booking Information ──────── -->
    <div class="ch-card">
        <div class="ch-card-head">
            <div class="ch-card-icon blue"><i class="fas fa-building"></i></div>
            <h2>Booking Information</h2>
        </div>
        <div class="ch-card-body">
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Project</span>
                    <span class="info-value"><?= renderProjectBadge($cancellation['project_name'], $cancellation['project_id']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Flat No.</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['flat_no']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Area</span>
                    <span class="info-value"><?= number_format($cancellation['area_sqft'], 2) ?> Sqft</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Booking Date</span>
                    <span class="info-value"><?= formatDate($cancellation['booking_date']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Customer Information ─────── -->
    <div class="ch-card">
        <div class="ch-card-head">
            <div class="ch-card-icon teal"><i class="fas fa-user-tie"></i></div>
            <h2>Customer Information</h2>
        </div>
        <div class="ch-card-body">
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['customer_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mobile</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['customer_mobile']) ?></span>
                </div>
                <div class="info-row" style="grid-column: span 2">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['customer_email']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Cancellation Information ─── -->
    <div class="ch-card">
        <div class="ch-card-head">
            <div class="ch-card-icon red"><i class="fas fa-ban"></i></div>
            <h2>Cancellation Information</h2>
        </div>
        <div class="ch-card-body">
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Cancellation Date</span>
                    <span class="info-value"><?= formatDate($cancellation['cancellation_date']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Processed By</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['processed_by_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Refund Mode</span>
                    <span class="info-value"><?= ucfirst($cancellation['refund_mode']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Refund Reference</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['refund_reference']) ?: '—' ?></span>
                </div>
                <div class="info-row" style="grid-column: span 2">
                    <span class="info-label">Cancellation Reason</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['cancellation_reason']) ?></span>
                </div>
                <div class="info-row" style="grid-column: span 2">
                    <span class="info-label">Deduction Reason</span>
                    <span class="info-value"><?= htmlspecialchars($cancellation['deduction_reason']) ?: '—' ?></span>
                </div>
            </div>

            <?php if (!empty($cancellation['remarks'])): ?>
            <div style="margin-top: 1rem;">
                <div class="remarks-box">
                    <div class="remarks-label"><i class="fas fa-comment-alt"></i> Additional Remarks</div>
                    <div class="remarks-text"><?= nl2br(htmlspecialchars($cancellation['remarks'])) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Payment History ───────────── -->
    <div class="ch-card">
        <div class="ch-card-head">
            <div class="ch-card-icon green"><i class="fas fa-history"></i></div>
            <h2>Payment History</h2>
            <span class="count-tag"><?= count($payments) ?> installment<?= count($payments) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($payments)): ?>
            <div class="ch-card-body">
                <div class="empty-payments">
                    <span class="ei"><i class="fas fa-inbox"></i></span>
                    <div class="et">No payments were received for this booking.</div>
                </div>
            </div>
        <?php else: ?>
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Reference No.</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $idx => $pay): ?>
                    <tr>
                        <td style="color:var(--ink-mute);font-size:.75rem;width:36px;"><?= $idx + 1 ?></td>
                        <td class="pay-num"><?= formatDate($pay['payment_date']) ?></td>
                        <td><span class="pay-amount"><?= formatCurrency($pay['amount']) ?></span></td>
                        <td><span class="mode-badge"><?= ucfirst($pay['payment_mode']) ?></span></td>
                        <td><span class="ref-text"><?= htmlspecialchars($pay['reference_no']) ?: '—' ?></span></td>
                        <td style="font-size:0.82rem; color:var(--ink-soft);"><?= htmlspecialchars($pay['remarks']) ?: '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total Received</td>
                        <td class="total-val"><?= formatCurrency($cancellation['total_paid']) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <!-- ── Action Bar ───────────────── -->
    <div class="action-bar">
        <a href="index.php" class="btn btn-ghost">
            <i class="fas fa-list"></i> All Bookings
        </a>
        <a href="view.php?id=<?= $cancellation['booking_id'] ?>" class="btn btn-ghost">
            <i class="fas fa-eye"></i> View Booking
        </a>
        <a href="<?= BASE_URL ?>modules/reports/download.php?action=cancellation_receipt&id=<?= $cancellation_id ?>"
           class="btn btn-primary" target="_blank">
            <i class="fas fa-file-pdf"></i> Download Receipt
        </a>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>