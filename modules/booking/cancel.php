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

// Check if already cancelled
if ($booking['status'] === 'cancelled') {
    setFlashMessage('warning', 'This booking is already cancelled');
    redirect('modules/booking/view.php?id=' . $booking_id);
}

// Fetch all payments for this booking
$sql = "SELECT * FROM payments 
        WHERE reference_type = 'booking' AND reference_id = ?
        ORDER BY payment_date ASC";
$stmt = $db->query($sql, [$booking_id]);
$payments = $stmt->fetchAll();

// Fetch existing refund if any
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

        $db->beginTransaction();
        try {
            $cancellation_date = $_POST['cancellation_date'];
            $cancellation_reason = sanitize($_POST['cancellation_reason']);
            $refund_amount = floatval($_POST['refund_amount']);
            $refund_mode = $_POST['refund_mode'];
            $refund_reference = sanitize($_POST['refund_reference']);
            $deduction_amount = floatval($_POST['deduction_amount']);
            $deduction_reason = sanitize($_POST['deduction_reason']);
            $remarks = sanitize($_POST['remarks']);
            
            // Insert cancellation record
            $cancellation_data = [
                'booking_id' => $booking_id,
                'cancellation_date' => $cancellation_date,
                'total_paid' => $booking['total_received'],
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
            
            // Update booking status
            $db->update('bookings', ['status' => 'cancelled'], 'id = ?', ['id' => $booking_id]);
            
            // Update flat status back to available
            $db->update('flats', ['status' => 'available'], 'id = ?', ['id' => $booking['flat_id']]);
            
            // Record refund as negative payment (expense) if refund amount > 0
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
            
            // Update financial records - add deduction as income
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

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<div class="cancellation-container">
    <form method="POST" id="cancellationForm" onsubmit="return validateCancellation()">
        <input type="hidden" name="action" value="cancel_booking">
        <?= csrf_field() ?>
        
        <!-- Warning Card -->
        <div class="cancel-card">
            <div class="cancel-header">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    Cancel Booking - <?= htmlspecialchars($booking['project_name']) ?> - Flat <?= htmlspecialchars($booking['flat_no']) ?>
                </h3>
            </div>
            <div class="cancel-body">
                <div class="warning-box">
                    <h4>
                        <i class="fas fa-exclamation-circle"></i>
                        Important: Booking Cancellation
                    </h4>
                    <p style="margin: 0.5rem 0; color: #856404; font-weight: 500;">
                        You are about to cancel this booking. Please review the following:
                    </p>
                    <ul>
                        <li>This action will mark the booking as cancelled</li>
                        <li>The flat will be made available for new bookings</li>
                        <li>You can specify refund amount and deduction charges</li>
                        <li>All financial records will be updated automatically</li>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>

                <!-- Booking Information -->
                <div class="info-section">
                    <h5>
                        <i class="fas fa-info-circle"></i>
                        Booking Information
                    </h5>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Customer:</span>
                            <span class="info-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mobile:</span>
                            <span class="info-value"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booking Date:</span>
                            <span class="info-value"><?= formatDate($booking['booking_date']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Agreement Value:</span>
                            <span class="info-value"><?= formatCurrency($booking['agreement_value']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="form-section">
                    <h5>
                        <i class="fas fa-history"></i>
                        Payment History (<?= count($payments) ?> Installments)
                    </h5>
                    <?php if (empty($payments)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 2rem;">No payments received for this booking</p>
                    <?php else: ?>
                        <table class="payment-history-table">
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
                                    <td><strong style="color: #38ef7d;"><?= formatCurrency($payment['amount']) ?></strong></td>
                                    <td><?= ucfirst($payment['payment_mode']) ?></td>
                                    <td><?= htmlspecialchars($payment['reference_no']) ?></td>
                                    <td><?= htmlspecialchars($payment['remarks']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2"><strong>TOTAL RECEIVED</strong></td>
                                    <td colspan="4"><strong><?= formatCurrency($booking['total_received']) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Refund Calculator -->
                <div class="refund-calculator">
                    <h5>
                        <i class="fas fa-calculator"></i>
                        Refund Calculator
                    </h5>
                    <div class="calculator-row">
                        <span class="calculator-label">Total Amount Received:</span>
                        <span class="calculator-value" style="color: #38ef7d;" id="display_total_received">
                            <?= formatCurrency($booking['total_received']) ?>
                        </span>
                    </div>
                    <div class="calculator-row">
                        <span class="calculator-label">Deduction/Charges (-):</span>
                        <span class="calculator-value" style="color: #f5576c;" id="display_deduction">₹ 0.00</span>
                    </div>
                    <div class="calculator-row">
                        <span class="calculator-label"><strong>Refund Amount:</strong></span>
                        <span class="calculator-value total" id="display_refund">₹ 0.00</span>
                    </div>
                </div>

                <!-- Cancellation Details Form -->
                <div class="form-section">
                    <h5>
                        <i class="fas fa-file-alt"></i>
                        Cancellation Details
                    </h5>
                    
                    <div class="form-row">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Cancellation Date *</label>
                            <input type="date" name="cancellation_date" class="form-control-custom" 
                                   required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Total Paid Amount (Read-only)</label>
                            <input type="text" class="form-control-custom readonly-field" 
                                   value="<?= formatCurrency($booking['total_received']) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Deduction/Cancellation Charges (₹) *</label>
                            <input type="number" name="deduction_amount" id="deduction_amount" 
                                   class="form-control-custom" step="0.01" min="0" 
                                   max="<?= $booking['total_received'] ?>" value="0" 
                                   required onchange="calculateRefund()">
                            <small style="color: #6c757d;">Amount to be retained as cancellation charges</small>
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Refund Amount (₹) *</label>
                            <input type="number" name="refund_amount" id="refund_amount" 
                                   class="form-control-custom" step="0.01" min="0" 
                                   max="<?= $booking['total_received'] ?>" 
                                   value="<?= $booking['total_received'] ?>" 
                                   required onchange="calculateDeduction()">
                            <small style="color: #6c757d;">Amount to be refunded to customer</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Deduction Reason *</label>
                            <input type="text" name="deduction_reason" class="form-control-custom" 
                                   placeholder="e.g., Administrative charges, Processing fee" required>
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Cancellation Reason *</label>
                            <select name="cancellation_reason" class="form-control-custom" required>
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

                    <div class="form-row">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Refund Mode *</label>
                            <select name="refund_mode" class="form-control-custom" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Refund Reference/Transaction No</label>
                            <input type="text" name="refund_reference" class="form-control-custom" 
                                   placeholder="UTR/Cheque No/Transaction ID">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label class="form-label-custom">Additional Remarks</label>
                        <textarea name="remarks" class="form-control-custom" 
                                  placeholder="Any additional notes about this cancellation..."></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="view.php?id=<?= $booking_id ?>" class="btn-secondary-custom">
                        <i class="fas fa-arrow-left"></i> Back to Booking
                    </a>
                    <button type="submit" class="btn-cancel-booking">
                        <i class="fas fa-ban"></i> Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Final Confirmation Modal -->
<div id="finalConfirmModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; border-radius: 16px; border:none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 30px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="modal-title" style="margin: 0; font-size:18px; font-weight:800; color:#ef4444; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i> Final Confirmation
            </h3>
            <button class="modal-close" onclick="document.getElementById('finalConfirmModal').style.display='none'" style="color:#94a3b8; font-size: 24px; background: transparent; border: none; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 30px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px; color: #1e293b; font-weight: 700;">Confirm Cancellation?</h4>
                <p style="color: #64748b; line-height: 1.5;">This action is <strong>irreversible</strong>. Please review the final amounts.</p>
            </div>
            
            <div style="background: #f8fafc; border-radius: 12px; padding: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: #64748b; font-weight: 500;">Total Received:</span>
                    <strong style="color: #334155;" id="modal_total"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: #64748b; font-weight: 500;">Deduction:</span>
                    <strong style="color: #ef4444;" id="modal_deduction"></strong>
                </div>
                <div style="border-top: 1px dashed #cbd5e1; margin: 5px 0 10px;"></div>
                <div style="display: flex; justify-content: space-between; font-size: 1.1em;">
                    <span style="color: #1e293b; font-weight: 700;">Refund Amount:</span>
                    <strong style="color: #10b981;" id="modal_refund"></strong>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 20px; background: #fff; border-radius: 0 0 16px 16px; display: flex; justify-content: center; gap: 10px;">
            <button type="button" class="modern-btn" style="background: #fff; color: #64748b; border: 1px solid #e2e8f0;" onclick="document.getElementById('finalConfirmModal').style.display='none'">Cancel</button>
            <button type="button" class="modern-btn" style="background: #ef4444; color: white; border: none;" onclick="submitCancellation()">Yes, Cancel Booking</button>
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
        alert('Error: Deduction + Refund must equal Total Received amount (' + totalReceived.toFixed(2) + ')');
        return false;
    }
    
    if (deduction < 0 || refund < 0) {
        alert('Error: Amounts cannot be negative');
        return false;
    }
    
    // Populate Modal
    document.getElementById('modal_total').textContent = '₹ ' + totalReceived.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('modal_deduction').textContent = '₹ ' + deduction.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('modal_refund').textContent = '₹ ' + refund.toLocaleString('en-IN', {minimumFractionDigits: 2});
    
    // Show Modal
    document.getElementById('finalConfirmModal').style.display = 'block';
    
    return false; // Stop default form submission
}

function submitCancellation() {
    document.getElementById('cancellationForm').submit();
}

// Initialize display
calculateRefund();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
