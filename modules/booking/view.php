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
        $payment_data = [
            'payment_type' => 'customer_receipt',
            'reference_type' => 'booking',
            'reference_id' => $booking_id,
            'party_id' => intval($_POST['party_id']),
            'payment_date' => $_POST['payment_date'],
            'amount' => floatval($_POST['amount']),
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
               u.full_name as created_by_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.id = ?";

$stmt = $db->query($sql, [$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlashMessage('error', 'Booking not found');
    redirect('modules/booking/index.php');
}

// Fetch payment history
$sql = "SELECT p.*, u.full_name as created_by_name
        FROM payments p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.reference_type = 'booking' AND p.reference_id = ?
        ORDER BY p.payment_date DESC, p.created_at DESC";
$stmt = $db->query($sql, [$booking_id]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Premium Modal Styles (Mini version for Cancel) */
.custom-modal {
    display: none; 
    position: fixed; 
    z-index: 10000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(15, 23, 42, 0.6); 
    backdrop-filter: blur(8px);
    transition: all 0.3s;
}

.custom-modal-content {
    background-color: #ffffff;
    margin: 15vh auto; /* Center vertically */
    border: none;
    width: 90%; 
    max-width: 450px;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

@keyframes modalSlideUp {
    from { transform: translateY(30px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-body-premium {
    padding: 32px;
    background: #ffffff;
    text-align: center;
}

.modern-btn {
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}
.modern-btn:hover { transform: translateY(-2px); }

.btn-cancel-ghost {
    background: #f1f5f9;
    color: #64748b;
}
.btn-cancel-ghost:hover { background: #e2e8f0; color: #475569; }

.btn-danger-modern {
    background: #ef4444;
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.btn-danger-modern:hover { background: #dc2626; box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4); }
</style>

<div class="booking-details-container">
    <div class="row" style="align-items: flex-start; column-gap: 1.5rem; margin: 0;">
        <!-- Main Content -->
        <div class="col-lg-9" style="flex: 0 0 calc(75% - 1.125rem); max-width: calc(75% - 1.125rem);">
            <!-- Booking Details Card -->
            <div class="info-card-modern">
                <div class="card-header-modern">
                    <h3>
                        <i class="fas fa-file-contract"></i>
                        Booking Details
                    </h3>
                    <div class="progress-badge">
                        <?php
                        $progress_percent = ($booking['agreement_value'] > 0) 
                            ? ($booking['total_received'] / $booking['agreement_value']) * 100 
                            : 0;
                        ?>
                        <?= number_format($progress_percent, 1) ?>% Paid
                    </div>
                </div>
                <div class="card-body-modern">
                    <!-- Financial Summary Cards -->
                    <div class="financial-cards">
                        <div class="financial-card agreement">
                            <div class="financial-label">Agreement Value</div>
                            <div class="financial-amount"><?= formatCurrency($booking['agreement_value']) ?></div>
                        </div>
                        <div class="financial-card received">
                            <div class="financial-label">Total Received</div>
                            <div class="financial-amount"><?= formatCurrency($booking['total_received']) ?></div>
                        </div>
                        <div class="financial-card pending">
                            <div class="financial-label">Pending Balance</div>
                            <div class="financial-amount"><?= formatCurrency($booking['total_pending']) ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress-header">
                            <span style="font-weight: 600; color: #2c3e50;">Payment Progress</span>
                            <span style="font-weight: 700; color: #11998e;"><?= number_format($progress_percent, 1) ?>%</span>
                        </div>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar-fill" style="width: <?= min($progress_percent, 100) ?>%;">
                                <?php if ($progress_percent > 15): ?>
                                    <?= formatCurrency($booking['total_received']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div>
                            <div class="section-header">
                                <i class="fas fa-building"></i>
                                Property Details
                            </div>
                            <div class="info-item">
                                <span class="info-label">Project:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['project_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Flat No:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['flat_no']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Floor:</span>
                                <span class="info-value"><?= $booking['floor'] ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Area:</span>
                                <span class="info-value"><?= number_format($booking['area_sqft'], 2) ?> Sqft</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Rate:</span>
                                <span class="info-value">₹ <?= $booking['rate'] ? number_format($booking['rate'], 2) : '0.00' ?> /sqft</span>
                            </div>
                        </div>

                        <div>
                            <div class="section-header">
                                <i class="fas fa-user-tie"></i>
                                Customer Details
                            </div>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mobile:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_email']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['customer_address']) ?></span>
                            </div>
                            <?php if (!empty($booking['referred_by'])): ?>
                            <div class="info-item">
                                <span class="info-label">Referred By:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['referred_by']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Booking Info -->
                    <div class="section-header" style="margin-top: 1rem;">
                        <i class="fas fa-calendar-check"></i>
                        Booking Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Booking Date:</span>
                            <span class="info-value"><?= formatDate($booking['booking_date']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="badge-modern <?= $booking['status'] === 'active' ? 'badge-info-modern' : ($booking['status'] === 'completed' ? 'badge-success-modern' : 'badge-warning-modern') ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created By:</span>
                            <span class="info-value"><?= htmlspecialchars($booking['created_by_name']) ?></span>
                        </div>
                        <?php if (!empty($booking['stamp_duty_registration']) && $booking['stamp_duty_registration'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Stamp Duty:</span>
                            <span class="info-value">₹ <?= number_format($booking['stamp_duty_registration'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['registration_amount']) && $booking['registration_amount'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Registration:</span>
                            <span class="info-value">₹ <?= number_format($booking['registration_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['gst_amount']) && $booking['gst_amount'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">GST:</span>
                            <span class="info-value">₹ <?= number_format($booking['gst_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['development_charge']) && $booking['development_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Development Charge:</span>
                            <span class="info-value">₹ <?= number_format($booking['development_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['parking_charge']) && $booking['parking_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Parking Charge:</span>
                            <span class="info-value">₹ <?= number_format($booking['parking_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['society_charge']) && $booking['society_charge'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Society Charge:</span>
                            <span class="info-value">₹ <?= number_format($booking['society_charge'], 2) ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Est. Total Cost Calculation & Display -->
                        <?php
                            $total_charges = ($booking['development_charge'] ?? 0) + 
                                           ($booking['parking_charge'] ?? 0) + 
                                           ($booking['society_charge'] ?? 0);
                            
                            $total_taxes = ($booking['stamp_duty_registration'] ?? 0) + 
                                         ($booking['registration_amount'] ?? 0) + 
                                         ($booking['gst_amount'] ?? 0);
                                         
                            $est_total_cost = $booking['agreement_value'] - $total_charges - $total_taxes;
                        ?>
                        <div class="info-item" style="background-color: #f8fafc; border-radius: 8px; padding: 8px;">
                            <span class="info-label" style="color: #0f172a; font-weight: 700;">Est. Total Cost:</span>
                            <span class="info-value" style="color: #11998e; font-weight: 700;">₹ <?= number_format($est_total_cost, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History Card -->
            <div class="info-card-modern">
                <div class="card-header-modern">
                    <h3>
                        <i class="fas fa-history"></i>
                        Payment History
                    </h3>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox empty-state-icon"></i>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #6c757d;">No payments received yet</div>
                            <p style="color: #999; margin-top: 0.5rem;">Click "Add Payment" to record the first payment</p>
                        </div>
                    <?php else: ?>
                        <div class="payment-table-wrapper">
                            <table class="payment-table">
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
                                        <td><?= formatDate($payment['payment_date']) ?></td>
                                        <td><strong style="color: #38ef7d;"><?= formatCurrency($payment['amount']) ?></strong></td>
                                        <td>
                                            <span class="badge-modern badge-info-modern">
                                                <?= ucfirst($payment['payment_mode']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($payment['reference_no']) ?></td>
                                        <td><?= htmlspecialchars($payment['remarks']) ?></td>
                                        <td><?= htmlspecialchars($payment['created_by_name']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>modules/reports/download.php?action=payment_receipt&id=<?= $payment['id'] ?>" 
                                               class="btn btn-sm btn-danger" target="_blank" title="Download Receipt">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span>TOTAL RECEIVED</span>
                                                <span style="font-size: 1.3rem;"><?= formatCurrency($booking['total_received']) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3" style="flex: 0 0 calc(25% - 0.375rem); max-width: calc(25% - 0.375rem);">
            <div class="info-card-modern sidebar-card">
                <div class="card-header-modern" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3>
                        <i class="fas fa-tasks"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body-modern">
                    <?php if ($booking['status'] === 'active'): ?>
                    <button class="action-btn action-btn-primary" onclick="showModal('addPaymentModal')">
                        <i class="fas fa-plus-circle"></i>
                        Add Payment
                    </button>

                    <a href="<?= BASE_URL ?>modules/booking/edit.php?id=<?= $booking_id ?>" class="action-btn action-btn-secondary">
                        <i class="fas fa-edit"></i>
                        Edit Booking
                    </a>
                    
                    <a href="<?= BASE_URL ?>modules/booking/cancel.php?id=<?= $booking_id ?>" 
                       class="action-btn action-btn-danger" 
                       onclick="event.preventDefault(); openCancelModal(this.href)">
                        <i class="fas fa-ban"></i>
                        Cancel Booking
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= BASE_URL ?>modules/booking/index.php" class="action-btn action-btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Bookings
                    </a>

                    <?php if ($booking['total_pending'] <= 0): ?>
                        <div class="status-alert success">
                            <i class="fas fa-check-circle status-alert-icon" style="color: #38ef7d;"></i>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #2c3e50;">Fully Paid!</div>
                            <div style="color: #6c757d; margin-top: 0.25rem;">All payments received</div>
                        </div>
                    <?php else: ?>
                        <div class="status-alert warning">
                            <i class="fas fa-exclamation-triangle status-alert-icon" style="color: #f5576c;"></i>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #2c3e50;">Pending Amount</div>
                            <div class="status-alert-amount" style="color: #f5576c;">
                                <?= formatCurrency($booking['total_pending']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="summary-box">
                        <h6>
                            <i class="fas fa-chart-line"></i>
                            Payment Summary
                        </h6>
                        <div class="summary-item">
                            <span>Total Installments:</span>
                            <strong><?= count($payments) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Average Payment:</span>
                            <strong><?= count($payments) > 0 ? formatCurrency($booking['total_received'] / count($payments)) : '₹ 0' ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Last Payment:</span>
                            <strong><?= !empty($payments) ? formatDate($payments[0]['payment_date']) : 'N/A' ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div id="addPaymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-money-bill-wave"></i> Add Payment</h3>
            <button class="modal-close" onclick="hideModal('addPaymentModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="party_id" value="<?= $booking['customer_id'] ?>">
                
                <div class="alert alert-info">
                    <strong>Pending Balance:</strong> <?= formatCurrency($booking['total_pending']) ?>
                </div>

                <div class="form-group">
                    <label>Remaining Payment (₹)</label>
                    <input type="text" readonly class="form-control" value="<?= $booking['total_pending'] ?>" style="background-color: #e9ecef;">
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Amount (₹) *</label>
                            <input type="number" name="amount" step="0.01" required 
                                   max="<?= $booking['total_pending'] ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Payment Mode *</label>
                            <select name="payment_mode" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Reference/Transaction No</label>
                            <input type="text" name="reference_no" placeholder="UTR/Cheque No">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideModal('addPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Premium Cancel Booking Modal -->
<div id="cancelBookingModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-body-premium">
            <div style="width: 72px; height: 72px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto;">
                <i class="fas fa-ban" style="font-size: 32px; color: #ef4444;"></i>
            </div>
            
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #1e293b;">Cancel Booking?</h3>
            <p style="margin: 0 0 32px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                Are you sure you want to proceed with cancellation?<br>
                <span style="color: #ef4444; font-weight: 600;">This will take you to the cancellation processing page.</span>
            </p>
            
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" class="modern-btn btn-cancel-ghost" onclick="closeCancelModal()">
                    Keep Booking
                </button>
                <a href="#" id="confirm_cancel_btn" class="modern-btn btn-danger-modern">
                    Yes, Proceed
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function openCancelModal(url) {
    document.getElementById('confirm_cancel_btn').href = url;
    document.getElementById('cancelBookingModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelBookingModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        closeCancelModal();
    }
    // Also handle addPaymentModal standard closing
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
