<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Cancel Booking';
$current_page = 'booking';

$booking_id = intval($_GET['id'] ?? 0);

// Fetch booking details with all payments
$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft,
               p.name as customer_name,
               p.mobile as customer_mobile,
               pr.project_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        WHERE b.id = ?";

$stmt = $db->query($sql, [$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlashMessage('error', 'Booking not found');
    redirect('modules/booking/index.php');
}

if ($booking['status'] === 'cancelled') {
    setFlashMessage('warning', 'This booking is already cancelled');
    redirect('modules/booking/view.php?id=' . $booking_id);
}

// Fetch all payments
$sql = "SELECT * FROM payments 
        WHERE reference_type = 'booking' AND reference_id = ?
        ORDER BY payment_date ASC";
$stmt = $db->query($sql, [$booking_id]);
$payments = $stmt->fetchAll();

$sql = "SELECT * FROM booking_cancellations WHERE booking_id = ?";
$stmt = $db->query($sql, [$booking_id]);
$existing_cancellation = $stmt->fetch();

// Handle cancellation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_booking') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            setFlashMessage('error', 'Security token expired. Please try again.');
            redirect('modules/booking/cancel.php?id=' . $booking_id);
        }

        try {
            $db->beginTransaction();

            $locked_booking = $db->query("SELECT total_received, status, flat_id FROM bookings WHERE id = ? FOR UPDATE", [$booking_id])->fetch();

            if (!$locked_booking) {
                throw new Exception("Booking not found during processing.");
            }
            if ($locked_booking['status'] === 'cancelled') {
                throw new Exception("Booking is already cancelled.");
            }

            $cancellation_date = $_POST['cancellation_date'];
            $cancellation_reason = sanitize($_POST['cancellation_reason']);
            $refund_amount = floatval($_POST['refund_amount']);
            $refund_mode = $_POST['refund_mode'];
            $refund_reference = sanitize($_POST['refund_reference']);
            $deduction_amount = floatval($_POST['deduction_amount']);
            $deduction_reason = sanitize($_POST['deduction_reason']);
            $remarks = sanitize($_POST['remarks']);
            
            $total_input = $refund_amount + $deduction_amount;
            $total_actual = floatval($locked_booking['total_received']);

            if (abs($total_input - $total_actual) > 1.00) {
                throw new Exception("Mismatch in amounts. Refund ($refund_amount) + Deduction ($deduction_amount) must equal Total Received ($total_actual).");
            }

            if ($refund_amount < 0 || $deduction_amount < 0) {
                 throw new Exception("Amounts cannot be negative.");
            }
            
            $cancellation_data = [
                'booking_id' => $booking_id,
                'cancellation_date' => $cancellation_date,
                'total_paid' => $total_actual,
                'refund_amount' => $refund_amount,
                'deduction_amount' => $deduction_amount,
                'deduction_reason' => $deduction_reason,
                'refund_mode' => $refund_mode,
                'refund_reference' => $refund_reference,
                'cancellation_reason' => $cancellation_reason,
                'remarks' => $remarks,
                'processed_by' => $_SESSION['user_id']
            ];
            
            $cancellation_id = $db->insert('booking_cancellations', $cancellation_data);
            
            $db->update('bookings', ['status' => 'cancelled'], 'id = ?', ['id' => $booking_id]);
            $db->update('flats', ['status' => 'available'], 'id = ?', ['id' => $booking['flat_id']]);
            
            if ($refund_amount > 0) {
                $refund_payment_data = [
                    'payment_type' => 'customer_refund',
                    'reference_type' => 'booking_cancellation',
                    'reference_id' => $cancellation_id,
                    'party_id' => $booking['customer_id'],
                    'payment_date' => $cancellation_date,
                    'amount' => $refund_amount,
                    'payment_mode' => $refund_mode,
                    'reference_no' => $refund_reference,
                    'remarks' => 'Refund for cancelled booking #' . $booking_id,
                    'created_by' => $_SESSION['user_id']
                ];
                $db->insert('payments', $refund_payment_data);
            }
            
            if ($deduction_amount > 0) {
                $income_data = [
                    'transaction_type' => 'income',
                    'category' => 'cancellation_charges',
                    'reference_type' => 'booking_cancellation',
                    'reference_id' => $cancellation_id,
                    'project_id' => $booking['project_id'],
                    'transaction_date' => $cancellation_date,
                    'amount' => $deduction_amount,
                    'description' => 'Cancellation charges - Booking #' . $booking_id . ' - ' . $deduction_reason,
                    'created_by' => $_SESSION['user_id']
                ];
                $db->insert('financial_transactions', $income_data);
            }
            
            logAudit('update', 'bookings', $booking_id, ['status' => $booking['status']], ['status' => 'cancelled']);
            logAudit('create', 'booking_cancellations', $cancellation_id, null, $cancellation_data);
            
            $db->commit();
            
            setFlashMessage('success', 'Booking cancelled successfully. Refund amount: ' . formatCurrency($refund_amount));
            redirect('modules/booking/cancellation_details.php?id=' . $cancellation_id);
            
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to cancel booking: ' . $e->getMessage());
        }
    }
}

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
    .bc-wrap { max-width: 920px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .bc-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: center; gap: 0.75rem;
    }

    .back-btn {
        width: 38px; height: 38px; border-radius: 9px;
        background: var(--surface); border: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        color: var(--ink-soft); text-decoration: none;
        transition: all 0.18s; flex-shrink: 0;
    }
    .back-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .bc-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: #ef4444; margin-bottom: 0.3rem;
    }
    .bc-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    /* ── Cards ───────────────────────────────── */
    .bc-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden; margin-bottom: 1.5rem;
        animation: fadeUp 0.4s ease both;
    }

    .card-header {
        padding: 1.15rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .card-header h3 {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
        color: var(--ink); margin: 0; display: flex; align-items: center; gap: 0.6rem;
    }
    .card-header h3 i { font-size: 0.85rem; color: var(--accent); }

    .card-body { padding: 1.5rem; }

    /* ── Warning Box ─────────────────────────── */
    .warn-box {
        background: #fef2f2; border: 1.5px solid #fca5a5;
        border-radius: 11px; padding: 1.25rem; margin-bottom: 1.5rem;
    }
    .warn-box h4 {
        font-size: 0.95rem; font-weight: 700; color: #991b1b;
        margin: 0 0 0.6rem; display: flex; align-items: center; gap: 0.5rem;
    }
    .warn-box h4 i { font-size: 0.9rem; }
    .warn-box p { font-size: 0.85rem; color: #7f1d1d; margin: 0 0 0.75rem; }
    .warn-box ul { margin: 0; padding-left: 1.5rem; }
    .warn-box li { font-size: 0.82rem; color: #991b1b; margin-bottom: 0.35rem; }

    /* ── Info Section ────────────────────────── */
    .sec-title {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        margin-bottom: 1rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .sec-title i { font-size: 0.75rem; color: var(--accent); }

    .info-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem 1.5rem;
    }
    @media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }

    .info-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.4rem 0;
    }
    .info-label { font-size: 0.8rem; font-weight: 600; color: var(--ink-soft); }
    .info-value { font-size: 0.875rem; font-weight: 500; color: var(--ink); }

    /* ── Payment Table ───────────────────────── */
    .pay-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .pay-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .pay-table thead th {
        padding: 0.65rem 0.85rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft);
    }
    .pay-table tbody tr { border-bottom: 1px solid var(--border-lt); }
    .pay-table tbody tr:last-child { border-bottom: none; }
    .pay-table td { padding: 0.7rem 0.85rem; vertical-align: middle; }
    .pay-table tfoot { background: #fdfcfa; border-top: 1.5px solid var(--border); }
    .pay-table tfoot td { padding: 0.85rem; font-weight: 700; }

    /* ── Calculator Box ──────────────────────── */
    .calc-box {
        background: linear-gradient(135deg, var(--ink) 0%, #3e3936 100%);
        border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;
    }
    .calc-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.6rem 0;
    }
    .calc-label { font-size: 0.85rem; color: rgba(255,255,255,0.8); }
    .calc-value { font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 700; }
    .calc-value.green { color: #10b981; }
    .calc-value.red { color: #ef4444; }
    .calc-value.white { color: white; }

    .calc-row.total {
        border-top: 1px solid rgba(255,255,255,0.2);
        margin-top: 0.5rem; padding-top: 1rem;
    }
    .calc-row.total .calc-label { font-weight: 700; color: white; font-size: 0.95rem; }
    .calc-row.total .calc-value { font-size: 1.6rem; }

    /* ── Form Fields ─────────────────────────── */
    .field { margin-bottom: 1.1rem; }
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
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }
    .field input[readonly] { background: #f0ece5; color: var(--ink-mute); cursor: not-allowed; }
    .field small { font-size: 0.72rem; color: var(--ink-mute); margin-top: 0.3rem; display: block; }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    @media (max-width: 640px) { .field-row { grid-template-columns: 1fr; } }

    /* ── Buttons ─────────────────────────────── */
    .btn-row {
        display: flex; justify-content: flex-end; gap: 0.75rem;
        margin-top: 2rem; padding-top: 1.5rem;
        border-top: 1.5px solid var(--border-lt);
    }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-danger {
        background: #ef4444; color: white; border: 1.5px solid #ef4444;
    }
    .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 14px rgba(239,68,68,0.3); }

    /* ── Modal ───────────────────────────────── */
    .bc-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .bc-modal-backdrop.open { display: flex; }

    .bc-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 480px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fef2f2;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: #b91c1c; margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: white; font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: #fee2e2; color: #991b1b; }

    .modal-body { padding: 1.75rem 1.6rem; }

    .modal-summary {
        background: #fdfcfa; border: 1px solid var(--border);
        border-radius: 10px; padding: 1.25rem; margin-bottom: 1.25rem;
    }
    .summary-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.5rem 0;
    }
    .summary-row.total {
        border-top: 1px dashed var(--border);
        margin-top: 0.5rem; padding-top: 0.75rem;
    }
    .summary-label { font-size: 0.85rem; color: var(--ink-soft); }
    .summary-value { font-weight: 700; }
    .summary-row.total .summary-label { font-size: 0.95rem; color: var(--ink); font-weight: 700; }
    .summary-row.total .summary-value { font-family: 'Fraunces', serif; font-size: 1.2rem; color: #10b981; }

    .modal-footer {
        display: flex; justify-content: center; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="bc-wrap">

    <!-- Header -->
    <div class="bc-header">
        <a href="view.php?id=<?= $booking_id ?>" class="back-btn" title="Back to Booking">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="eyebrow">Cancellation Process</div>
            <h1><?= htmlspecialchars($booking['project_name']) ?> - <?= htmlspecialchars($booking['flat_no']) ?></h1>
        </div>
    </div>

    <form method="POST" id="cancellationForm" onsubmit="return validateCancellation()">
        <input type="hidden" name="action" value="cancel_booking">
        <?= csrf_field() ?>

        <!-- Warning Card -->
        <div class="bc-card">
            <div class="card-body">
                <div class="warn-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important: Booking Cancellation</h4>
                    <p>You are about to cancel this booking. Please review the following:</p>
                    <ul>
                        <li>This action will mark the booking as cancelled</li>
                        <li>The flat will be made available for new bookings</li>
                        <li>You can specify refund amount and deduction charges</li>
                        <li>All financial records will be updated automatically</li>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>

                <!-- Booking Info -->
                <div class="sec-title"><i class="fas fa-info-circle"></i> Booking Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Customer</span>
                        <span class="info-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Mobile</span>
                        <span class="info-value"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Booking Date</span>
                        <span class="info-value"><?= formatDate($booking['booking_date']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Agreement Value</span>
                        <span class="info-value"><?= formatCurrency($booking['agreement_value']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History Card -->
        <div class="bc-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Payment History (<?= count($payments) ?> Installments)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <p style="text-align:center;color:var(--ink-mute);padding:2rem">No payments received for this booking</p>
                <?php else: ?>
                    <div style="overflow-x:auto">
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Mode</th>
                                    <th>Reference No</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $index => $payment): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= formatDate($payment['payment_date']) ?></td>
                                    <td><strong style="color:#10b981"><?= formatCurrency($payment['amount']) ?></strong></td>
                                    <td><?= ucfirst($payment['payment_mode']) ?></td>
                                    <td><?= htmlspecialchars($payment['reference_no'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($payment['remarks'] ?: '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">TOTAL RECEIVED</td>
                                    <td colspan="4"><strong><?= formatCurrency($booking['total_received']) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Refund Calculator Card -->
        <div class="bc-card">
            <div class="card-header">
                <h3><i class="fas fa-calculator"></i> Refund Calculator</h3>
            </div>
            <div class="card-body">
                <div class="calc-box">
                    <div class="calc-row">
                        <span class="calc-label">Total Amount Received</span>
                        <span class="calc-value green"><?= formatCurrency($booking['total_received']) ?></span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Deduction/Charges (−)</span>
                        <span class="calc-value red" id="display_deduction">₹ 0.00</span>
                    </div>
                    <div class="calc-row total">
                        <span class="calc-label">Refund Amount</span>
                        <span class="calc-value white" id="display_refund">₹ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancellation Details Card -->
        <div class="bc-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Cancellation Details</h3>
            </div>
            <div class="card-body">

                <div class="field-row">
                    <div class="field">
                        <label>Cancellation Date *</label>
                        <input type="date" name="cancellation_date" required 
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="field">
                        <label>Total Paid Amount (Read-only)</label>
                        <input type="text" value="<?= formatCurrency($booking['total_received']) ?>" readonly>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Deduction/Cancellation Charges (₹) *</label>
                        <input type="number" name="deduction_amount" id="deduction_amount" 
                               step="0.01" min="0" max="<?= $booking['total_received'] ?>" 
                               value="0" required onchange="calculateRefund()">
                        <small>Amount to be retained as cancellation charges</small>
                    </div>
                    <div class="field">
                        <label>Refund Amount (₹) *</label>
                        <input type="number" name="refund_amount" id="refund_amount" 
                               step="0.01" min="0" max="<?= $booking['total_received'] ?>" 
                               value="<?= $booking['total_received'] ?>" 
                               required onchange="calculateDeduction()">
                        <small>Amount to be refunded to customer</small>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Deduction Reason *</label>
                        <input type="text" name="deduction_reason" required
                               placeholder="e.g., Administrative charges, Processing fee">
                    </div>
                    <div class="field">
                        <label>Cancellation Reason *</label>
                        <select name="cancellation_reason" required>
                            <option value="">Select Reason</option>
                            <option value="Customer Request">Customer Request</option>
                            <option value="Financial Issues">Financial Issues</option>
                            <option value="Property Issues">Property Issues</option>
                            <option value="Better Opportunity">Better Opportunity</option>
                            <option value="Family Reasons">Family Reasons</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Refund Mode *</label>
                        <select name="refund_mode" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Refund Reference/Transaction No</label>
                        <input type="text" name="refund_reference" 
                               placeholder="UTR/Cheque No/Transaction ID">
                    </div>
                </div>

                <div class="field">
                    <label>Additional Remarks</label>
                    <textarea name="remarks" rows="3" 
                              placeholder="Any additional notes about this cancellation..."></textarea>
                </div>

                <div class="btn-row">
                    <a href="view.php?id=<?= $booking_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Booking
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Confirm Cancellation
                    </button>
                </div>

            </div>
        </div>

    </form>

</div>

<!-- Confirmation Modal -->
<div class="bc-modal-backdrop" id="confirmModal">
    <div class="bc-modal">
        <div class="modal-head">
            <h3><i class="fas fa-exclamation-triangle"></i> Final Confirmation</h3>
            <button type="button" class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:1.25rem">
                <h4 style="margin:0 0 0.5rem;color:var(--ink);font-weight:700">Confirm Cancellation?</h4>
                <p style="color:var(--ink-soft);line-height:1.6;font-size:0.875rem">
                    This action is <strong>irreversible</strong>. Please review the final amounts.
                </p>
            </div>

            <div class="modal-summary">
                <div class="summary-row">
                    <span class="summary-label">Total Received</span>
                    <strong class="summary-value" style="color:var(--ink)" id="modal_total"></strong>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Deduction</span>
                    <strong class="summary-value" style="color:#ef4444" id="modal_deduction"></strong>
                </div>
                <div class="summary-row total">
                    <span class="summary-label">Refund Amount</span>
                    <span class="summary-value" id="modal_refund"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitCancellation()">
                Yes, Cancel Booking
            </button>
        </div>
    </div>
</div>

<script>
const totalReceived = <?= $booking['total_received'] ?>;

function calculateRefund() {
    const deduction = parseFloat(document.getElementById('deduction_amount').value) || 0;
    const refund = totalReceived - deduction;
    
    document.getElementById('refund_amount').value = refund.toFixed(2);
    document.getElementById('display_deduction').textContent = '₹ ' + deduction.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('display_refund').textContent = '₹ ' + refund.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function calculateDeduction() {
    const refund = parseFloat(document.getElementById('refund_amount').value) || 0;
    const deduction = totalReceived - refund;
    
    document.getElementById('deduction_amount').value = deduction.toFixed(2);
    document.getElementById('display_deduction').textContent = '₹ ' + deduction.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('display_refund').textContent = '₹ ' + refund.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function validateCancellation() {
    const deduction = parseFloat(document.getElementById('deduction_amount').value) || 0;
    const refund = parseFloat(document.getElementById('refund_amount').value) || 0;
    
    if (Math.abs((deduction + refund) - totalReceived) > 0.01) {
        Swal.fire({
            icon: 'error',
            title: 'Calculation Error',
            text: 'Deduction + Refund must equal Total Received amount (' + totalReceived.toFixed(2) + ')',
            confirmButtonColor: '#ef4444',
            customClass: {
                popup: 'premium-swal-popup',
                title: 'premium-swal-title',
                content: 'premium-swal-content',
                confirmButton: 'premium-swal-confirm'
            }
        });
        return false;
    }
    
    if (deduction < 0 || refund < 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Amounts cannot be negative',
            confirmButtonColor: '#ef4444',
            customClass: {
                popup: 'premium-swal-popup',
                title: 'premium-swal-title',
                content: 'premium-swal-content',
                confirmButton: 'premium-swal-confirm'
            }
        });
        return false;
    }
    
    document.getElementById('modal_total').textContent = '₹ ' + totalReceived.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('modal_deduction').textContent = '₹ ' + deduction.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('modal_refund').textContent = '₹ ' + refund.toLocaleString('en-IN', {minimumFractionDigits: 2});
    
    openModal();
    return false;
}

function openModal() { document.getElementById('confirmModal').classList.add('open'); }
function closeModal() { document.getElementById('confirmModal').classList.remove('open'); }

function submitCancellation() {
    document.getElementById('cancellationForm').submit();
}

document.getElementById('confirmModal').addEventListener('click', e => {
    if (e.target.id === 'confirmModal') closeModal();
});

calculateRefund();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>