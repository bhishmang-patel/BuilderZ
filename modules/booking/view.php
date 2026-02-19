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
$page_title = 'Booking Details';
$current_page = 'booking';

$booking_id = intval($_GET['id'] ?? 0);

// Handle payment addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_payment') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
             setFlashMessage('error', 'Security token expired. Please try again.');
             redirect('modules/booking/view.php?id=' . $booking_id);
        }
        $payment_data = [
            'payment_type' => 'customer_receipt',
            'reference_type' => 'booking',
            'reference_id' => $booking_id,
            'party_id' => intval($_POST['party_id']),
            'demand_id' => !empty($_POST['demand_id']) ? intval($_POST['demand_id']) : null,
            'payment_date' => $_POST['payment_date'],
            'amount' => round(floatval($_POST['amount']), 2),
            'payment_mode' => $_POST['payment_mode'],
            'reference_no' => sanitize($_POST['reference_no']),
            'remarks' => sanitize($_POST['remarks']),
            'created_by' => $_SESSION['user_id']
        ];
        
        $db->beginTransaction();
        try {
            $payment_id = $db->insert('payments', $payment_data);
            updateBookingTotals($booking_id);
            logAudit('create', 'payments', $payment_id, null, $payment_data);
            $db->commit();
            
            setFlashMessage('success', 'Payment added successfully');
            redirect('modules/booking/view.php?id=' . $booking_id);
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to add payment: ' . $e->getMessage());
            redirect('modules/booking/view.php?id=' . $booking_id);
        }
    }
}

// Fetch booking details
$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft, f.floor,
               p.name as customer_name,
               p.mobile as customer_mobile,
               p.email as customer_email,
               p.address as customer_address,
               pr.project_name,
               sw.name as stage_of_work_name,
               u.full_name as created_by_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN stage_of_work sw ON b.stage_of_work_id = sw.id
        WHERE b.id = ?";

$stmt = $db->query($sql, [$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlashMessage('error', 'Booking not found');
    redirect('modules/booking/index.php');
}

// Fetch payment history
$sql = "SELECT p.*, u.full_name as created_by_name, bd.stage_name as demand_stage
        FROM payments p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN booking_demands bd ON p.demand_id = bd.id
        WHERE p.reference_type = 'booking' AND p.reference_id = ?
        ORDER BY p.payment_date DESC, p.created_at DESC";
$stmt = $db->query($sql, [$booking_id]);
$payments = $stmt->fetchAll();

$demands = $db->query("SELECT * FROM booking_demands WHERE booking_id = ? AND status != 'paid' ORDER BY generated_date ASC", [$booking_id])->fetchAll();

// Calculate percentages and totals
$progress_percent = ($booking['agreement_value'] > 0) 
    ? ($booking['total_received'] / $booking['agreement_value']) * 100 
    : 0;

$total_charges = ($booking['development_charge'] ?? 0) + 
                ($booking['parking_charge'] ?? 0) + 
                ($booking['society_charge'] ?? 0);

$total_taxes = ($booking['stamp_duty_registration'] ?? 0) + 
              ($booking['registration_amount'] ?? 0) + 
              ($booking['gst_amount'] ?? 0);
              
$est_total_cost = $booking['agreement_value'] - $total_charges - $total_taxes;

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
    .bv-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .bv-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .header-left { display: flex; align-items: center; gap: 0.75rem; }
    .back-btn {
        width: 38px; height: 38px; border-radius: 9px;
        background: var(--surface); border: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        color: var(--ink-soft); text-decoration: none;
        transition: all 0.18s; flex-shrink: 0;
    }
    .back-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .header-title-group {}
    .header-title-group .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .header-title-group h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    .status-badge {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.45rem 1rem; border-radius: 20px;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .status-badge.active  { background: #eff6ff; color: #1e40af; }
    .status-badge.completed { background: #ecfdf5; color: #065f46; }
    .status-badge.cancelled { background: #fef2f2; color: #b91c1c; }
    .status-badge::before { content: '●'; font-size: 0.5rem; }

    /* ── Layout ──────────────────────────────── */
    .bv-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
    @media (max-width: 1024px) { .bv-grid { grid-template-columns: 1fr; } }
    .bv-sidebar { position: sticky; top: 2rem; }

    /* ── Cards ───────────────────────────────── */
    .bv-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden; margin-bottom: 1.5rem;
        animation: fadeUp 0.4s ease both;
    }
    .bv-card.sidebar-card { margin-bottom: 0; }

    .card-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.15rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .card-header h3 {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
        color: var(--ink); margin: 0; display: flex; align-items: center; gap: 0.6rem;
    }
    .card-header h3 i { font-size: 0.85rem; color: var(--accent); }

    .card-body { padding: 1.5rem; }

    /* ── Financial Cards ─────────────────────── */
    .fin-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1rem; margin-bottom: 1.5rem;
    }
    @media (max-width: 720px) { .fin-grid { grid-template-columns: 1fr; } }

    .fin-card {
        padding: 1.1rem 1.3rem; border-radius: 11px;
        border: 1.5px solid var(--border);
    }
    .fin-card.agreement { background: #eff6ff; border-color: #bfdbfe; }
    .fin-card.received  { background: #ecfdf5; border-color: #a7f3d0; }
    .fin-card.pending   { background: var(--accent-lt); border-color: #e0c9b5; }

    .fin-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; margin-bottom: 0.4rem;
    }
    .fin-card.agreement .fin-label { color: #1e40af; }
    .fin-card.received  .fin-label { color: #065f46; }
    .fin-card.pending   .fin-label { color: var(--accent); }

    .fin-amount {
        font-family: 'Fraunces', serif; font-size: 1.5rem;
        font-weight: 700; color: var(--ink);
    }

    /* ── Progress Bar ────────────────────────── */
    .prog-wrap {
        padding: 1.25rem; background: #fdfcfa; border-radius: 10px;
        border: 1px solid var(--border-lt); margin-bottom: 1.5rem;
    }
    .prog-top {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 0.6rem;
    }
    .prog-label { font-size: 0.82rem; font-weight: 600; color: var(--ink-soft); }
    .prog-percent { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 700; color: #10b981; }

    .prog-bar-bg {
        height: 32px; background: #f0ece5; border-radius: 20px;
        overflow: hidden; position: relative;
    }
    .prog-bar-fill {
        height: 100%; background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        border-radius: 20px; display: flex; align-items: center; justify-content: flex-end;
        padding-right: 1rem; font-size: 0.75rem; font-weight: 700;
        color: white; transition: width 0.8s ease;
    }

    /* ── Info Grid ───────────────────────────── */
    .info-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem 2rem; margin-bottom: 1.5rem;
    }
    @media (max-width: 720px) { .info-grid { grid-template-columns: 1fr; } }

    .info-section {}
    .sec-title {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        margin-bottom: 1rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .sec-title i { font-size: 0.75rem; color: var(--accent); }

    .info-item {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 0.5rem 0; gap: 1rem;
    }
    .info-label {
        font-size: 0.8rem; font-weight: 600; color: var(--ink-soft);
        flex-shrink: 0;
    }
    .info-value {
        font-size: 0.875rem; font-weight: 500; color: var(--ink);
        text-align: right;
    }

    .info-item.highlight {
        background: #fdfcfa; border-radius: 8px;
        padding: 0.65rem 0.85rem; margin: 0.25rem 0;
    }
    .info-item.highlight .info-label { color: var(--ink); font-weight: 700; }
    .info-item.highlight .info-value { color: #10b981; font-weight: 700; }

    /* ── Payment Table ───────────────────────── */
    .pay-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .pay-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .pay-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft);
    }
    .pay-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .pay-table tbody tr:last-child { border-bottom: none; }
    .pay-table tbody tr:hover { background: #fdfcfa; }
    .pay-table td { padding: 0.8rem 1rem; vertical-align: middle; }

    .pay-table tfoot { background: #fdfcfa; border-top: 1.5px solid var(--border); }
    .pay-table tfoot td {
        padding: 1rem 1.5rem; font-weight: 700;
        font-size: 0.9rem; color: var(--ink);
    }
    .pay-table tfoot .total-amt {
        font-family: 'Fraunces', serif; font-size: 1.2rem;
        color: #10b981;
    }

    .demand-tag {
        display: inline-block; padding: 0.2rem 0.6rem;
        background: #eef2ff; color: #4f46e5; border-radius: 5px;
        font-size: 0.7rem; font-weight: 600; margin-top: 0.25rem;
    }

    .btn-pdf {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.4rem 0.8rem; background: #fef2f2; color: #b91c1c;
        border: 1px solid #fca5a5; border-radius: 6px;
        font-size: 0.75rem; font-weight: 600;
        text-decoration: none; transition: all 0.15s;
    }
    .btn-pdf:hover { background: #fee2e2; color: #991b1b; }

    /* Empty state */
    .empty-state {
        padding: 3rem 1rem; text-align: center;
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

    /* ── Sidebar ─────────────────────────────── */
    .bv-sidebar {}

    .action-btn {
        width: 100%; display: flex; align-items: center; gap: 0.6rem;
        padding: 0.7rem 1.1rem; margin-bottom: 0.6rem;
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 8px; font-size: 0.85rem; font-weight: 600;
        color: var(--ink); text-decoration: none; cursor: pointer;
        transition: all 0.18s; text-align: left;
    }
    .action-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); transform: translateY(-1px); }
    
    .action-btn.primary { background: var(--ink); color: white; border-color: var(--ink); }
    .action-btn.primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.28); }

    .action-btn.danger { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .action-btn.danger:hover { background: #fee2e2; border-color: #ef4444; color: #991b1b; }

    .action-btn i { width: 16px; font-size: 0.9rem; }

    .divider { height: 1px; background: var(--border-lt); margin: 1.25rem 0; }

    /* Alert box */
    .alert-box {
        padding: 1.25rem; border-radius: 11px;
        border: 1.5px solid; margin-bottom: 1rem;
    }
    .alert-box.success { background: #ecfdf5; border-color: #a7f3d0; }
    .alert-box.warning { background: var(--accent-lt); border-color: #e0c9b5; }

    .alert-box .alert-icon {
        font-size: 1.5rem; margin-bottom: 0.6rem;
        display: block;
    }
    .alert-box.success .alert-icon { color: #10b981; }
    .alert-box.warning .alert-icon { color: var(--accent); }

    .alert-box h5 {
        font-size: 0.95rem; font-weight: 700; color: var(--ink);
        margin: 0 0 0.3rem;
    }
    .alert-box p {
        font-size: 0.8rem; margin: 0;
    }
    .alert-box.success p { color: #065f46; }
    .alert-box.warning p { color: var(--accent); }

    .alert-box .alert-amt {
        font-family: 'Fraunces', serif; font-size: 1.3rem;
        font-weight: 700; margin-top: 0.4rem; display: block;
    }
    .alert-box.warning .alert-amt { color: var(--accent); }

    /* Summary box */
    .sum-box {
        padding: 1.25rem; background: #fdfcfa;
        border: 1px solid var(--border-lt); border-radius: 10px;
        margin-bottom: 1rem;
    }
    .sum-box h6 {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        margin: 0 0 1rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .sum-box h6 i { font-size: 0.75rem; color: var(--accent); }

    .sum-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.45rem 0; font-size: 0.8rem;
    }
    .sum-item span { color: var(--ink-soft); }
    .sum-item strong { color: var(--ink); font-weight: 600; }

    /* ── Modal ───────────────────────────────── */
    .bv-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .bv-modal-backdrop.open { display: flex; }

    .bv-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 580px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
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
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem; }

    .modal-footer {
        display: flex; justify-content: flex-end; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    /* Form fields */
    .field {
        margin-bottom: 1.1rem;
    }
    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select, .field textarea {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        -webkit-appearance: none; appearance: none;
    }
    .field select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }

    .alert-info {
        background: #eff6ff; border: 1.5px solid #bfdbfe;
        padding: 1rem; border-radius: 10px; margin-bottom: 1.25rem;
        font-size: 0.85rem; color: #1e40af;
    }
    .alert-info strong { font-weight: 700; }

    .btn {
        padding: 0.7rem 1.4rem; border: none; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="bv-wrap">

    <!-- Header -->
    <div class="bv-header">
        <div class="header-left">
            <a href="<?= BASE_URL ?>modules/booking/index.php" class="back-btn" title="Back to Bookings">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-title-group">
                <div class="eyebrow">Booking #<?= $booking_id ?></div>
                <h1><?= htmlspecialchars($booking['project_name']) ?> - <?= htmlspecialchars($booking['flat_no']) ?></h1>
            </div>
        </div>
        <span class="status-badge <?= strtolower($booking['status']) ?>">
            <?= ucfirst($booking['status']) ?>
        </span>
    </div>

    <!-- Layout Grid -->
    <div class="bv-grid">

        <!-- Main Content -->
        <div>

            <!-- Financial Overview Card -->
            <div class="bv-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Financial Overview</h3>
                </div>
                <div class="card-body">
                    
                    <!-- Financial Cards -->
                    <div class="fin-grid">
                        <div class="fin-card agreement">
                            <div class="fin-label">Agreement Value</div>
                            <div class="fin-amount"><?= formatCurrencyShort($booking['agreement_value']) ?></div>
                        </div>
                        <div class="fin-card received">
                            <div class="fin-label">Total Received</div>
                            <div class="fin-amount"><?= formatCurrencyShort($booking['total_received']) ?></div>
                        </div>
                        <div class="fin-card pending">
                            <div class="fin-label">Pending Balance</div>
                            <div class="fin-amount"><?= formatCurrencyShort($booking['total_pending']) ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="prog-wrap">
                        <div class="prog-top">
                            <span class="prog-label">Payment Progress</span>
                            <span class="prog-percent"><?= number_format($progress_percent, 1) ?>%</span>
                        </div>
                        <div class="prog-bar-bg">
                            <div class="prog-bar-fill" style="width: <?= min($progress_percent, 100) ?>%;">
                                <?php if ($progress_percent > 15): ?>
                                    <?= formatCurrencyShort($booking['total_received']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Details Card -->
            <div class="bv-card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Booking Details</h3>
                </div>
                <div class="card-body">

                    <div class="info-grid">
                        <!-- Property -->
                        <div class="info-section">
                            <div class="sec-title"><i class="fas fa-building"></i> Property Details</div>
                            <div class="info-item">
                                <span class="info-label">Project</span>
                                <span class="info-value"><?= renderProjectBadge($booking['project_name'], $booking['project_id']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Flat No</span>
                                <span class="info-value"><?= htmlspecialchars($booking['flat_no']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Floor</span>
                                <span class="info-value"><?= $booking['floor'] ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Area</span>
                                <span class="info-value"><?= number_format($booking['area_sqft'], 2) ?> sqft</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Rate</span>
                                <span class="info-value">₹<?= $booking['rate'] ? number_format($booking['rate'], 2) : '0.00' ?> /sqft</span>
                            </div>
                        </div>

                        <!-- Customer -->
                        <div class="info-section">
                            <div class="sec-title"><i class="fas fa-user-tie"></i> Customer Details</div>
                            <div class="info-item">
                                <span class="info-label">Name</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mobile</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_email'] ?: '—') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Address</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_address'] ?: '—') ?></span>
                            </div>
                            <?php if (!empty($booking['referred_by'])): ?>
                            <div class="info-item">
                                <span class="info-label">Referred By</span>
                                <span class="info-value"><?= htmlspecialchars($booking['referred_by']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sec-title" style="margin-top:1.5rem"><i class="fas fa-calendar-check"></i> Booking Information</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Booking Date</span>
                            <span class="info-value"><?= formatDate($booking['booking_date']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Plan</span>
                            <span class="info-value" style="color:#4f46e5;font-weight:600">
                                <?= !empty($booking['stage_of_work_name']) ? htmlspecialchars($booking['stage_of_work_name']) : 'Custom / Manual' ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created By</span>
                            <span class="info-value"><?= htmlspecialchars($booking['created_by_name']) ?></span>
                        </div>
                        <?php if (!empty($booking['stamp_duty_registration']) && $booking['stamp_duty_registration'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Stamp Duty</span>
                            <span class="info-value">₹<?= number_format($booking['stamp_duty_registration'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['registration_amount']) && $booking['registration_amount'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Registration</span>
                            <span class="info-value">₹<?= number_format($booking['registration_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['gst_amount']) && $booking['gst_amount'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">GST</span>
                            <span class="info-value">₹<?= number_format($booking['gst_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['development_charge']) && $booking['development_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Development Charge</span>
                            <span class="info-value">₹<?= number_format($booking['development_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['parking_charge']) && $booking['parking_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Parking Charge</span>
                            <span class="info-value">₹<?= number_format($booking['parking_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['society_charge']) && $booking['society_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Society Charge</span>
                            <span class="info-value">₹<?= number_format($booking['society_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-item highlight">
                        <span class="info-label">Est. Total Cost</span>
                        <span class="info-value">₹<?= number_format($est_total_cost, 2) ?></span>
                    </div>

                </div>
            </div>

            <!-- Payment History Card -->
            <div class="bv-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Payment History</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No payments recorded yet</h4>
                            <p>Click "Add Payment" to record the first payment</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto">
                            <table class="pay-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Reference No</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($payment['payment_date']) ?></span></td>
                                        <td><span style="font-weight:700;color:#10b981"><?= formatCurrencyShort($payment['amount']) ?></span></td>
                                        <td>
                                            <div style="font-weight:600"><?= ucfirst($payment['payment_mode']) ?></div>
                                            <?php if(!empty($payment['demand_stage'])): ?>
                                                <span class="demand-tag"><?= htmlspecialchars($payment['demand_stage']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($payment['reference_no'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($payment['remarks'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($payment['created_by_name']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>modules/reports/download.php?action=payment_receipt&id=<?= $payment['id'] ?>" 
                                               class="btn-pdf" target="_blank">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7">
                                            <div style="display:flex;justify-content:space-between;align-items:center">
                                                <span>TOTAL RECEIVED</span>
                                                <span class="total-amt"><?= formatCurrency($booking['total_received']) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="bv-sidebar">

            <!-- Actions Card -->
            <div class="bv-card sidebar-card">
                <div class="card-header" style="background:linear-gradient(135deg, var(--ink) 0%, #3e3936 100%);border:none">
                    <h3 style="color:white"><i class="fas fa-tasks"></i> Quick Actions</h3>
                </div>
                <div class="card-body">

                    <?php if ($booking['status'] === 'active'): ?>
                    <button class="action-btn primary" onclick="openModal('paymentModal')">
                        <i class="fas fa-plus-circle"></i> Add Payment
                    </button>

                    <a href="<?= BASE_URL ?>modules/booking/edit.php?id=<?= $booking_id ?>" class="action-btn">
                        <i class="fas fa-pencil-alt"></i> Edit Booking
                    </a>

                    <a href="<?= BASE_URL ?>modules/booking/cancel.php?id=<?= $booking_id ?>" 
                       class="action-btn danger"
                       onclick="event.preventDefault(); openCancelModal(this.href)">
                        <i class="fas fa-ban"></i> Cancel Booking
                    </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>modules/booking/index.php" class="action-btn">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>

                    <div class="divider"></div>

                    <a href="<?= BASE_URL ?>modules/booking/export_pdf.php?id=<?= $booking_id ?>" 
                       class="action-btn" style="background:var(--ink);color:white;border-color:var(--ink)" target="_blank">
                        <i class="fas fa-file-download"></i> Booking Confirmation
                    </a>

                    <?php if ($booking['total_pending'] <= 0): ?>
                        <div class="alert-box success">
                            <i class="fas fa-check-circle alert-icon"></i>
                            <h5>Fully Paid!</h5>
                            <p>All payments received</p>
                        </div>
                    <?php else: ?>
                        <div class="alert-box warning">
                            <i class="fas fa-exclamation-triangle alert-icon"></i>
                            <h5>Pending Amount</h5>
                            <p>Outstanding balance</p>
                            <span class="alert-amt"><?= formatCurrency($booking['total_pending']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="sum-box">
                        <h6><i class="fas fa-chart-line"></i> Payment Summary</h6>
                        <div class="sum-item">
                            <span>Total Installments</span>
                            <strong><?= count($payments) ?></strong>
                        </div>
                        <div class="sum-item">
                            <span>Average Payment</span>
                            <strong><?= count($payments) > 0 ? formatCurrencyShort($booking['total_received'] / count($payments)) : '₹ 0' ?></strong>
                        </div>
                        <div class="sum-item">
                            <span>Last Payment</span>
                            <strong><?= !empty($payments) ? formatDate($payments[0]['payment_date']) : 'N/A' ?></strong>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>

</div>


<!-- Payment Modal -->
<div class="bv-modal-backdrop" id="paymentModal">
    <div class="bv-modal">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" name="party_id" value="<?= $booking['customer_id'] ?>">

            <div class="modal-head">
                <h3><i class="fas fa-money-bill-wave"></i> Add Payment</h3>
                <button type="button" class="modal-close" onclick="closeModal('paymentModal')">×</button>
            </div>

            <div class="modal-body">
                <div class="alert-info">
                    <strong>Pending Balance:</strong> <?= formatCurrency($booking['total_pending']) ?>
                </div>

                <div class="field">
                    <label>Payment For (Optional)</label>
                    <select name="demand_id">
                        <option value="">General / Oldest Unpaid (Default)</option>
                        <?php foreach ($demands as $d): ?>
                            <option value="<?= $d['id'] ?>">
                                <?= htmlspecialchars($d['stage_name']) ?> - ₹<?= number_format($d['demand_amount'], 2) ?> (Due: <?= formatDate($d['due_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Payment Date *</label>
                        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="field">
                        <label>Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" required 
                               max="<?= $booking['total_pending'] ?>" placeholder="0.00">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Payment Mode *</label>
                        <select name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Reference/Transaction No</label>
                        <input type="text" name="reference_no" placeholder="UTR/Cheque No">
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3" placeholder="Any additional notes..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="bv-modal-backdrop" id="cancelModal">
    <div class="bv-modal" style="max-width:480px">
        <div class="modal-head" style="background:#fef2f2;border-color:#fca5a5">
            <h3 style="color:#b91c1c"><i class="fas fa-exclamation-triangle"></i> Cancel Booking?</h3>
            <button type="button" class="modal-close" onclick="closeModal('cancelModal')">×</button>
        </div>
        <div style="padding:2rem;text-align:center">
            <div style="width:56px;height:56px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem">
                <i class="fas fa-ban" style="font-size:1.5rem;color:#ef4444"></i>
            </div>
            <h4 style="margin:0 0 0.75rem;color:var(--ink);font-weight:700">Are you sure?</h4>
            <p style="color:var(--ink-soft);margin:0 0 1.25rem;line-height:1.6;font-size:0.875rem">
                This will take you to the cancellation processing page<br>
                where you can manage refunds and deductions.
            </p>
            <div style="display:flex;gap:0.75rem;justify-content:center">
                <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">
                    Keep Booking
                </button>
                <a href="#" id="confirm_cancel_btn" class="btn" style="background:#ef4444;color:white">
                    Yes, Proceed
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.bv-modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});

function openCancelModal(url) {
    document.getElementById('confirm_cancel_btn').href = url;
    openModal('cancelModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>