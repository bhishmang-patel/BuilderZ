<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Labour Outstanding';
$current_page = 'labour_outstanding';

// Fetch labour outstanding
require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();
$data = $reportService->getLabourOutstanding();
$labours = $data['labours'];
$totals = $data['totals'];
$grand_total = $totals['total_amount'];
$grand_paid = $totals['paid_amount'];
$grand_pending = $totals['pending_amount'];

require_once __DIR__ . '/../../includes/ColorHelper.php';


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
.stat-icon.red { background: #fef2f2; color: #ef4444; }

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
        <div class="stat-icon orange">
            <i class="fas fa-hard-hat"></i>
        </div>
        <div class="stat-info">
            <h4>Total Labour Cost</h4>
            <div class="value"><?= formatCurrency($grand_total) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Total Paid</h4>
            <div class="value"><?= formatCurrency($grand_paid) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Total Outstanding</h4>
            <div class="value" style="color: #ef4444;"><?= formatCurrency($grand_pending) ?></div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="chart-card-custom" style="padding: 0; overflow: hidden;">
    <div class="chart-header-custom" style="padding: 25px;">
        <div class="chart-title-group">
            <h3>
                <div class="chart-icon-box blue"><i class="fas fa-users-cog"></i></div>
                Labour Outstanding
            </h3>
            <div class="chart-subtitle">Payables to labour contractors</div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=labour_outstanding&format=excel" class="modern-btn excel" style="width: auto; padding: 10px 15px;">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=labour_outstanding&format=csv" class="modern-btn csv" style="width: auto; padding: 10px 15px;">
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
                    <th style="padding-left: 25px;">Labour/Contractor</th>

                    <th>Challans</th>
                    <th>Total Cost</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labours)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 50px;">
                             <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <div style="width: 60px; height: 60px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                                    <i class="fas fa-check" style="font-size: 24px; color: #10b981;"></i>
                                </div>
                                <h4 style="margin: 0; color: #1e293b;">All Settled!</h4>
                                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">No outstanding payments to labour/contractors.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($labours as $labour): ?>
                    <tr>
                        <td style="padding-left: 25px;">
                             <div style="display: flex; align-items: center; gap: 12px;">
                                <?php $labourColor = ColorHelper::getCustomerColor($labour['labour_id']); ?>
                                <div style="width: 36px; height: 36px; background: <?= $labourColor ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 12px;">
                                    <?= strtoupper(substr($labour['labour_name'] ?? 'L', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($labour['labour_name']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($labour['mobile']) ?></div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 12px;">
                                <?= $labour['total_challans'] ?>
                            </span>
                        </td>
                        <td style="font-family: monospace; font-weight: 600;"><?= formatCurrency($labour['total_amount']) ?></td>
                        <td><span class="badge-soft green" style="font-family: monospace;"><?= formatCurrency($labour['paid_amount']) ?></span></td>
                        <td style="width: 200px;">
                            <?php if ($labour['pending_amount'] > 0): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <span class="badge-soft red" style="font-family: monospace;">
                                        <?= formatCurrency($labour['pending_amount']) ?>
                                    </span>
                                    <?php
                                    $payment_percent = ($labour['total_amount'] > 0) 
                                        ? ($labour['paid_amount'] / $labour['total_amount']) * 100 
                                        : 0;
                                    ?>
                                    <span style="font-size: 11px; font-weight: 700; color: #10b981;"><?= round($payment_percent) ?>% Paid</span>
                                </div>
                                <div style="width: 100%; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?= min(100, $payment_percent) ?>%; height: 100%; background: #10b981; border-radius: 3px;"></div>
                                </div>
                            <?php else: ?>
                                <span class="badge-soft green">Settled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="modern-btn secondary" style="width: auto; padding: 8px 12px; min-width: auto;" onclick="viewLabourDetails(<?= $labour['labour_id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="modern-btn" style="width: auto; padding: 8px 16px; min-width: auto;" onclick="showPaymentModal('labour_account_payment', <?= $labour['labour_id'] ?>, <?= $labour['labour_id'] ?>, <?= $labour['pending_amount'] ?>, <?= htmlspecialchars(json_encode($labour['labour_name']), ENT_QUOTES) ?>)">
                                    <i class="fas fa-rupee-sign"></i> Pay
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/payment_modal_premium.css">

<!-- Labour Details Modal -->
<div id="labourDetailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
             <h3 class="modal-title">
                <div class="chart-icon-box blue" style="width: 32px; height: 32px; font-size: 14px;"><i class="fas fa-file-invoice"></i></div>
                Labour Work Details
            </h3>
            <button class="close-modal" onclick="hideModal('labourDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="labour_details_content" style="max-height: 70vh; overflow-y: auto;">
            <div style="display: flex; justify-content: center; padding: 40px;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; color: #3b82f6;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Premium Payment Modal -->
<div id="paymentModal" class="custom-modal payment-modal-overlay">
    <div class="payment-modal-content">
        <div class="payment-modal-header">
            <h3 class="payment-modal-title">
                <div class="payment-modal-icon"><i class="fas fa-wallet"></i></div>
                Record Payment
            </h3>
            <button class="payment-modal-close" onclick="hideModal('paymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>modules/payments/index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="make_payment">
            <input type="hidden" name="redirect_url" value="modules/reports/labour_outstanding.php">
            <input type="hidden" name="payment_type" id="payment_type">
            <input type="hidden" name="reference_id" id="reference_id">
            <input type="hidden" name="party_id" id="party_id">
            <input type="hidden" id="max_pending_amount" value="0">
            
            <div class="payment-info-card">
                <div>
                    <div class="payment-info-label">Pay To</div>
                    <div class="payment-info-value" id="party_name_display">-</div>
                </div>
                <div style="text-align: right;">
                    <div class="payment-info-label">Outstanding</div>
                    <div class="payment-amount-large" id="pending_amount_display">₹ 0.00</div>
                </div>
            </div>

            <div class="payment-form-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="payment-input-group">
                            <label class="payment-label">Payment Date</label>
                            <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>" class="payment-input">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-input-group">
                            <label class="payment-label">Amount</label>
                            <div class="payment-currency-wrapper">
                                <span class="payment-currency-symbol">₹</span>
                                <input type="number" name="payment_amount_final" id="payment_amount" step="0.01" required onchange="calculateBalance()" oninput="calculateBalance()" class="payment-input has-symbol" placeholder="0.00">
                            </div>
                            <small id="amount_warning" style="color: #ef4444; display: none; margin-top: 5px; font-weight: 600; font-size: 11px;">
                                <i class="fas fa-exclamation-triangle"></i> Exceeds outstanding!
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                         <div class="payment-input-group">
                            <label class="payment-label">Payment Mode</label>
                            <div class="payment-select-wrapper">
                                <select name="payment_mode" required class="payment-input">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="upi">UPI</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                                <i class="fas fa-chevron-down payment-select-arrow"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-input-group">
                            <label class="payment-label">Reference No</label>
                            <input type="text" name="reference_no" placeholder="UTR / Cheque No" class="payment-input">
                        </div>
                    </div>
                </div>
                
                <div class="payment-input-group">
                    <label class="payment-label">Remarks (Optional)</label>
                    <textarea name="remarks" rows="2" placeholder="Add any notes here..." class="payment-input" style="min-height: 80px; resize: none;"></textarea>
                </div>
            </div>

            <div class="payment-balance-row">
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">Remaining Balance</div>
                <div id="remaining_calc" style="font-size: 16px; font-weight: 700; color: #10b981;">₹ 0.00</div>
            </div>

            <div class="payment-modal-footer">
                <button type="button" class="btn-cancel" onclick="hideModal('paymentModal')">Cancel</button>
                <button type="submit" class="btn-confirm" id="payment_submit_btn">
                    <i class="fas fa-check"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewLabourDetails(labourId) {
    // Show modal with loading state first
    const modal = document.getElementById('labourDetailsModal');
    document.getElementById('labour_details_content').innerHTML = `
        <div style="display: flex; justify-content: center; padding: 40px;">
            <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; color: #3b82f6;"></i>
        </div>
    `;
    modal.classList.add('show');

    fetch('<?= BASE_URL ?>modules/reports/labour_details_ajax.php?labour_id=' + labourId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('labour_details_content').innerHTML = html;
        });
}

function showPaymentModal(type, refId, partyId, pendingAmount, partyName) {
    // Close details modal if open
    hideModal('labourDetailsModal');
    
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = refId;
    document.getElementById('party_id').value = partyId;
    document.getElementById('payment_amount').value = ''; // Reset amount
    document.getElementById('max_pending_amount').value = pendingAmount;
    document.getElementById('party_name_display').textContent = partyName;
    document.getElementById('pending_amount_display').textContent = '₹ ' + parseFloat(pendingAmount).toFixed(2);

    document.getElementById('remaining_calc').textContent = '₹ ' + parseFloat(pendingAmount).toFixed(2);
    document.getElementById('amount_warning').style.display = 'none';
    
    // Trigger balance calculation to update UI
    calculateBalance();
    
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
