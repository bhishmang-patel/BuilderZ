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
$page_title = 'Customer Pending Payments';
$current_page = 'customer_pending';

// Fetch customer pending payments
require_once __DIR__ . '/../../includes/ReportService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';
$reportService = new ReportService();
$data = $reportService->getCustomerPending();
$pending_payments = $data['payments'];
$totals = $data['totals'];
$total_agreement = $totals['agreement_value'];
$total_received = $totals['received'];
$total_pending = $totals['pending'];

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Page Specific Styles */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card-modern {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s;
}
.stat-card-modern:hover { transform: translateY(-3px); }
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.stat-info h4 {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}
.stat-info .value {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 5px;
    line-height: 1.2;
}
/* Icon colors */
.stat-icon.blue { background: #eff6ff; color: #3b82f6; }
.stat-icon.green { background: #ecfdf5; color: #10b981; }
.stat-icon.orange { background: #fff7ed; color: #f59e0b; }

/* Modal Custom Override */
.custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.custom-modal.show { display: flex; animation: fadeIn 0.2s ease-out; }
.modal-content {
    background: #fff;
    width: 90%;
    max-width: 600px;
    border-radius: 16px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}
.modal-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display:flex; align-items:center; gap:10px; }
.close-modal { background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; transition: color 0.2s; }
.close-modal:hover { color: #ef4444; }

.modal-body { padding: 25px; }

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8fafc;
}

/* Form Styles */
.modern-input, .modern-select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    color: #1e293b;
    background: #fff;
    transition: all 0.2s;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
.modern-input:focus, .modern-select:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Custom Button Colors */
.modern-btn.excel { background: #7c3aed; color: white; }
.modern-btn.excel:hover { background: #6d28d9; }
.modern-btn.csv { background: #db2777; color: white; }
.modern-btn.csv:hover { background: #be185d; }
</style>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon blue">
            <i class="fas fa-file-contract"></i>
        </div>
        <div class="stat-info">
            <h4>Total Agreement Value</h4>
            <div class="value"><?= formatCurrency($total_agreement) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Total Received</h4>
            <div class="value"><?= formatCurrency($total_received) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon orange">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Total Pending</h4>
            <div class="value" style="color: #f59e0b;"><?= formatCurrency($total_pending) ?></div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="chart-card-custom" style="padding: 0; overflow: hidden;">
    <div class="chart-header-custom" style="padding: 25px;">
        <div class="chart-title-group">
            <h3>
                <div class="chart-icon-box orange"><i class="fas fa-exclamation-triangle"></i></div>
                Pending Payments Report
            </h3>
            <div class="chart-subtitle">List of customers with outstanding dues</div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=customer_pending&format=excel" class="modern-btn excel" style="width: auto; padding: 10px 15px;">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=customer_pending&format=csv" class="modern-btn csv" style="width: auto; padding: 10px 15px;">
                <i class="fas fa-file-code"></i> CSV
            </a>
            <button class="modern-btn secondary" style="width: auto; padding: 10px 15px;" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th style="padding-left: 25px;">Customer</th>
                    <th>Project / Flat</th>
                    <th>Booking Date</th>
                    <th>Agreement</th>
                    <th>Received</th>
                    <th>Pending</th>
                    <th>Days</th>
                    <th style="text-align: right; padding-right: 25px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_payments)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px;">
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <div style="width: 60px; height: 60px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                                    <i class="fas fa-check" style="font-size: 24px; color: #10b981;"></i>
                                </div>
                                <h4 style="margin: 0; color: #1e293b;">All clear!</h4>
                                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">No pending payments found.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pending_payments as $payment): 
                        $color = ColorHelper::getCustomerColor($payment['customer_id']);
                        $initial = ColorHelper::getInitial($payment['customer_name']);
                    ?>
                    <tr>
                        <td style="padding-left: 25px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; background: <?= $color ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 12px;">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($payment['customer_name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($payment['mobile']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #334155; font-size: 13px;"><?= htmlspecialchars($payment['project_name']) ?></div>
                            <div style="font-size: 12px; color: #64748b;">Flat: <strong><?= htmlspecialchars($payment['flat_no']) ?></strong> (Flr <?= $payment['floor'] ?>)</div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #475569;"><?= formatDate($payment['booking_date']) ?></div>
                        </td>
                        <td style="font-family: monospace; font-weight: 600;"><?= formatCurrency($payment['agreement_value']) ?></td>
                        <td>
                            <span class="badge-soft green" style="font-family: monospace;">
                                <?= formatCurrency($payment['total_received']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span class="badge-soft red" style="font-family: monospace;">
                                    <?= formatCurrency($payment['total_pending']) ?>
                                </span>
                                <?php
                                $pending_percent = ($payment['agreement_value'] > 0) 
                                    ? ($payment['total_pending'] / $payment['agreement_value']) * 100 
                                    : 0;
                                ?>
                                <span style="font-size: 11px; font-weight: 700; color: #ef4444;"><?= round($pending_percent) ?>%</span>
                            </div>
                            <div style="width: 100%; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden;">
                                <div style="width: <?= min(100, $pending_percent) ?>%; height: 100%; background: #ef4444; border-radius: 3px;"></div>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 12px; font-weight: 600; color: #64748b;"><?= $payment['days_since_booking'] ?> Days</span>
                        </td>
                        <td style="text-align: right; padding-right: 25px;">
                            <button class="modern-btn" onclick="showPaymentModal('customer_receipt', <?= $payment['id'] ?>, <?= $payment['customer_id'] ?>, <?= $payment['total_pending'] ?>, '<?= htmlspecialchars($payment['customer_name']) ?>')" style="width: auto; padding: 8px 16px; min-width: auto;">
                                <i class="fas fa-rupee-sign"></i> Receive
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="custom-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <div class="chart-icon-box blue" style="width: 32px; height: 32px; font-size: 14px;"><i class="fas fa-money-bill-wave"></i></div>
                Record Payment
            </h3>
            <button class="close-modal" onclick="hideModal('paymentModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>modules/payments/index.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="make_payment">
                <input type="hidden" name="redirect_url" value="modules/reports/customer_pending.php">
                <input type="hidden" name="payment_type" id="payment_type">
                <input type="hidden" name="reference_id" id="reference_id">
                <input type="hidden" name="party_id" id="party_id">
                <input type="hidden" id="max_pending_amount" value="0">
                
                <div style="background: #eff6ff; border: 1px solid #dbeafe; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-size: 13px; color: #475569; font-weight: 600;">Customer</span>
                        <span id="party_name_display" style="font-size: 13px; color: #1e293b; font-weight: 700;">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; color: #475569; font-weight: 600;">Pending Amount</span>
                        <span id="pending_amount_display" style="font-size: 16px; color: #ef4444; font-weight: 800;">₹ 0.00</span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>" class="modern-input">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Amount (₹) *</label>
                            <input type="number" name="amount" id="payment_amount" step="0.01" required onchange="calculateBalance()" oninput="calculateBalance()" class="modern-input" placeholder="0.00">
                             <small id="amount_warning" style="color: #ef4444; display: none; margin-top: 5px; font-weight: 600; font-size: 12px;">
                                <i class="fas fa-exclamation-triangle"></i> Amount exceeds pending!
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Payment Mode *</label>
                            <div style="position: relative;">
                                <select name="payment_mode" required class="modern-select">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="upi">UPI</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                                <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 12px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" placeholder="UTR / Cheque No" class="modern-input">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="Any additional notes..." class="modern-input" style="min-height: 80px;"></textarea>
                </div>
                
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                         <div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Remaining Balance</div>
                            <div id="remaining_calc" style="font-size: 18px; font-weight: 800; color: #10b981;">₹ 0.00</div>
                        </div>
                        <div style="font-size: 11px; color: #94a3b8;">After this payment</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modern-btn secondary" onclick="hideModal('paymentModal')">Cancel</button>
                <button type="submit" class="modern-btn" id="payment_submit_btn">
                    <i class="fas fa-check-circle"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showPaymentModal(type, refId, partyId, pendingAmount, partyName) {
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = refId;
    document.getElementById('party_id').value = partyId;
    document.getElementById('payment_amount').value = '';
    document.getElementById('max_pending_amount').value = pendingAmount;
    document.getElementById('party_name_display').textContent = partyName;
    document.getElementById('pending_amount_display').textContent = '₹ ' + parseFloat(pendingAmount).toFixed(2);
    document.getElementById('remaining_calc').textContent = '₹ ' + parseFloat(pendingAmount).toFixed(2);
    document.getElementById('amount_warning').style.display = 'none';
    
    // Show modal
    const modal = document.getElementById('paymentModal');
    modal.classList.add('show');
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
}

function calculateBalance() {
    const pendingAmount = parseFloat(document.getElementById('max_pending_amount').value);
    const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
    const remaining = pendingAmount - paymentAmount;
    const warningEl = document.getElementById('amount_warning');
    const submitBtn = document.getElementById('payment_submit_btn');
    const remainingEl = document.getElementById('remaining_calc');
    
    if (remaining >= 0) {
        remainingEl.textContent = '₹ ' + remaining.toFixed(2);
        remainingEl.style.color = remaining === 0 ? '#10b981' : '#3b82f6';
        warningEl.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    } else {
        remainingEl.textContent = '₹ ' + Math.abs(remaining).toFixed(2) + ' (Excess)';
        remainingEl.style.color = '#ef4444';
        warningEl.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.classList.remove('show');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
