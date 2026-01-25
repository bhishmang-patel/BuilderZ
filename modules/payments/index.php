<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$db = Database::getInstance();
$page_title = 'Payments';
$current_page = 'payments';

// Handle payment operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'make_payment') {
        $payment_type = $_POST['payment_type'];
        $reference_id = intval($_POST['reference_id']);
        $party_id = intval($_POST['party_id']);
        $payment_date = $_POST['payment_date'];
        $amount = floatval($_POST['amount']);
        $payment_mode = $_POST['payment_mode'];
        $reference_no = sanitize($_POST['reference_no']);
        $remarks = sanitize($_POST['remarks']);
        
        $db->beginTransaction();
        try {
            $payment_data = [
                'payment_type' => $payment_type,
                'reference_type' => $payment_type === 'customer_receipt' ? 'booking' : 'challan',
                'reference_id' => $reference_id,
                'party_id' => $party_id,
                'payment_date' => $payment_date,
                'amount' => $amount,
                'payment_mode' => $payment_mode,
                'reference_no' => $reference_no,
                'remarks' => $remarks,
                'created_by' => $_SESSION['user_id']
            ];
            
            $payment_id = $db->insert('payments', $payment_data);
            
            // Update totals based on payment type
            if ($payment_type === 'customer_receipt') {
                updateBookingTotals($reference_id);
            } else {
                updateChallanPaidAmount($reference_id);
            }
            
            logAudit('create', 'payments', $payment_id, null, $payment_data);
            $db->commit();
            
            setFlashMessage('success', 'Payment recorded successfully');
            $redirect_url = $_POST['redirect_url'] ?? 'modules/payments/index.php';
            redirect($redirect_url);
            
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to record payment: ' . $e->getMessage());
            $redirect_url = $_POST['redirect_url'] ?? 'modules/payments/index.php';
            redirect($redirect_url);
        }
    }
}

// Get filter values
$payment_type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Fetch pending items for payment
$pending_bookings = $db->query("SELECT b.id, b.booking_date, b.agreement_value, b.total_received, b.total_pending,
                                        f.flat_no, p.name as customer_name, p.id as party_id, pr.project_name, pr.id as project_id, p.id as customer_id
                                 FROM bookings b
                                 JOIN flats f ON b.flat_id = f.id
                                 JOIN parties p ON b.customer_id = p.id
                                 JOIN projects pr ON b.project_id = pr.id
                                 WHERE b.total_pending > 0 AND b.status = 'active'
                                 ORDER BY b.booking_date DESC")->fetchAll();

$pending_vendor_challans = $db->query("SELECT c.id, c.challan_no, c.challan_date, c.total_amount, c.paid_amount, c.pending_amount,
                                              p.name as vendor_name, p.id as party_id, pr.project_name, pr.id as project_id
                                       FROM challans c
                                       JOIN parties p ON c.party_id = p.id
                                       JOIN projects pr ON c.project_id = pr.id
                                       WHERE c.challan_type = 'material' AND c.pending_amount > 0 AND c.status IN ('approved', 'partial')
                                       ORDER BY c.challan_date")->fetchAll();

$pending_labour_challans = $db->query("SELECT c.id, c.challan_no, c.challan_date, c.total_amount, c.paid_amount, c.pending_amount,
                                              p.name as labour_name, p.id as party_id, pr.project_name, pr.id as project_id
                                       FROM challans c
                                       JOIN parties p ON c.party_id = p.id
                                       JOIN projects pr ON c.project_id = pr.id
                                       WHERE c.challan_type = 'labour' AND c.pending_amount > 0 AND c.status IN ('approved', 'partial')
                                       ORDER BY c.challan_date")->fetchAll();

// Fetch recent payments with filters
$where = '1=1';
$params = [];

if ($payment_type_filter) {
    $where .= ' AND p.payment_type = ?';
    $params[] = $payment_type_filter;
}

if ($date_from) {
    $where .= ' AND p.payment_date >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $where .= ' AND p.payment_date <= ?';
    $params[] = $date_to;
}

$sql = "SELECT p.*, 
               pt.name as party_name,
               u.full_name as created_by_name
        FROM payments p
        JOIN parties pt ON p.party_id = pt.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT 50";

$stmt = $db->query($sql, $params);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Payment Page Specific Styles */
.chart-icon-box.emerald {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
}

.modern-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 5px;
}

.modern-tab {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modern-tab:hover {
    color: #059669;
}

.modern-tab.active {
    color: #059669;
    border-bottom-color: #059669;
}

.tab-badge {
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
}

.modern-tab.active .tab-badge {
    background: #ecfdf5;
    color: #059669;
}

/* Premium Modal Styles */
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

.custom-modal.active {
    display: block;
    animation: fadeIn 0.3s ease-out;
}

.custom-modal-content {
    background-color: #ffffff;
    margin: 4% auto;
    border: none;
    width: 90%; 
    max-width: 600px;
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

.modal-header-premium {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-title-group h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-title-group p {
    margin: 6px 0 0 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

.modal-close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}
.modal-close-btn:hover { background: rgba(255, 255, 255, 0.3); transform: rotate(90deg); }

.modal-body-premium {
    padding: 32px;
    background: #ffffff;
}

.form-section-title {
    font-size: 11px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #f1f5f9;
}

/* Modern Inputs */
.modern-input, .modern-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    color: #1e293b;
    transition: all 0.2s;
    outline: none;
    background: #f8fafc;
}

.modern-input:focus, .modern-select:focus {
    border-color: #10b981;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
}

.input-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #334155;
    font-size: 13px;
}

.modal-footer-premium {
    padding: 24px 32px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); }

.btn-ghost {
    background: transparent;
    color: #64748b;
    border: 2px solid #e2e8f0;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-ghost:hover { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

.payment-summary-box {
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.summary-row:last-child { margin-bottom: 0; }

.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box emerald"><i class="fas fa-money-bill-wave"></i></div>
                        Payment Management
                    </h3>
                    <div class="chart-subtitle">Track and record payments for customers, vendors, and labour</div>
                </div>
            </div>

            <div style="padding: 20px;">
                <!-- Tabs -->
                <div class="modern-tabs">
                    <button class="modern-tab active" onclick="switchTab('customer')">
                        <i class="fas fa-user-circle"></i> Customer Receipts
                        <span class="tab-badge"><?= count($pending_bookings) ?></span>
                    </button>
                    <button class="modern-tab" onclick="switchTab('vendor')">
                        <i class="fas fa-truck"></i> Vendor Payments
                        <span class="tab-badge"><?= count($pending_vendor_challans) ?></span>
                    </button>
                    <button class="modern-tab" onclick="switchTab('labour')">
                        <i class="fas fa-hard-hat"></i> Labour Payments
                        <span class="tab-badge"><?= count($pending_labour_challans) ?></span>
                    </button>
                    <button class="modern-tab" onclick="switchTab('history')">
                        <i class="fas fa-history"></i> Payment History
                    </button>
                </div>

                <!-- Customer Receipts Tab -->
                <div id="customer-tab" class="tab-content active">
                    <?php if (empty($pending_bookings)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; font-weight: 500;">All customer payments are up to date! 🎉</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>PROJECT</th>
                                        <th>FLAT</th>
                                        <th>CUSTOMER</th>
                                        <th>BOOKING DATE</th>
                                        <th>AGREEMENT VALUE</th>
                                        <th>RECEIVED</th>
                                        <th>PENDING</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($pending_bookings as $booking): 
                                        $color = ColorHelper::getProjectColor($booking['project_id']);
                                        $initial = ColorHelper::getInitial($booking['project_name']);
                                        
                                        $custColor = ColorHelper::getCustomerColor($booking['customer_id'] ?? 0);
                                        $custInitial = ColorHelper::getInitial($booking['customer_name']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                                <span style="font-weight:700;"><?= htmlspecialchars($booking['project_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge-pill blue"><?= htmlspecialchars($booking['flat_no']) ?></span></td>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-circle" style="background: <?= $custColor ?>; color: #fff;"><?= $custInitial ?></div>
                                                <div style="display:flex; flex-direction:column;">
                                                    <span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($booking['customer_name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= formatDate($booking['booking_date']) ?></td>
                                        <td><span class="badge-pill green"><?= formatCurrency($booking['agreement_value']) ?></span></td>
                                        <td><span style="color: #10b981; font-weight: 600;"><?= formatCurrency($booking['total_received']) ?></span></td>
                                        <td><span style="color: #f59e0b; font-weight: 600;"><?= formatCurrency($booking['total_pending']) ?></span></td>
                                        <td>
                                            <button class="modern-btn" onclick="showPaymentModal('customer_receipt', <?= $booking['id'] ?>, <?= $booking['party_id'] ?>, <?= $booking['total_pending'] ?>, '<?= htmlspecialchars(addslashes($booking['customer_name'])) ?>')" style="padding: 6px 12px; font-size: 11px;">
                                                <i class="fas fa-plus"></i> Collect
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Vendor Payments Tab -->
                <div id="vendor-tab" class="tab-content" style="display: none;">
                    <?php if (empty($pending_vendor_challans)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; font-weight: 500;">No pending vendor payments.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>PROJECT</th>
                                        <th>VENDOR</th>
                                        <th>CHALLAN NO</th>
                                        <th>DATE</th>
                                        <th>TOTAL</th>
                                        <th>PAID</th>
                                        <th>PENDING</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($pending_vendor_challans as $challan): 
                                        $color = ColorHelper::getProjectColor($challan['project_id']);
                                        $initial = ColorHelper::getInitial($challan['project_name']);
                                        
                                        $vendorColor = ColorHelper::getCustomerColor($challan['party_id']);
                                        $vendorInitial = ColorHelper::getInitial($challan['vendor_name']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                                <span style="font-weight:700;"><?= htmlspecialchars($challan['project_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-circle" style="background: <?= $vendorColor ?>; color: #fff; width:28px; height:28px; font-size:11px; margin-right:8px;"><?= $vendorInitial ?></div>
                                                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($challan['vendor_name']) ?></div>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($challan['challan_no']) ?></strong></td>
                                        <td><?= formatDate($challan['challan_date']) ?></td>
                                        <td><?= formatCurrency($challan['total_amount']) ?></td>
                                        <td><span style="color: #10b981;"><?= formatCurrency($challan['paid_amount']) ?></span></td>
                                        <td><span style="color: #f59e0b; font-weight: 600;"><?= formatCurrency($challan['pending_amount']) ?></span></td>
                                        <td>
                                            <button class="modern-btn" onclick="showPaymentModal('vendor_payment', <?= $challan['id'] ?>, <?= $challan['party_id'] ?>, <?= $challan['pending_amount'] ?>, '<?= htmlspecialchars(addslashes($challan['vendor_name'])) ?>')" style="padding: 6px 12px; font-size: 11px;">
                                                <i class="fas fa-file-invoice-dollar"></i> Pay
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Labour Payments Tab -->
                <div id="labour-tab" class="tab-content" style="display: none;">
                    <?php if (empty($pending_labour_challans)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; font-weight: 500;">No pending labour payments.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>PROJECT</th>
                                        <th>LABOUR/CONTRACTOR</th>
                                        <th>PAY NO</th>
                                        <th>DATE</th>
                                        <th>TOTAL</th>
                                        <th>PAID</th>
                                        <th>PENDING</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($pending_labour_challans as $challan): 
                                        $color = ColorHelper::getProjectColor($challan['project_id']);
                                        $initial = ColorHelper::getInitial($challan['project_name']);
                                        
                                        $labourColor = ColorHelper::getCustomerColor($challan['party_id']);
                                        $labourInitial = ColorHelper::getInitial($challan['labour_name']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                                <span style="font-weight:700;"><?= htmlspecialchars($challan['project_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-circle" style="background: <?= $labourColor ?>; color: #fff; width:28px; height:28px; font-size:11px; margin-right:8px;"><?= $labourInitial ?></div>
                                                <span style="font-weight:600;"><?= htmlspecialchars($challan['labour_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($challan['challan_no']) ?></strong></td>
                                        <td><?= formatDate($challan['challan_date']) ?></td>
                                        <td><?= formatCurrency($challan['total_amount']) ?></td>
                                        <td><span style="color: #10b981;"><?= formatCurrency($challan['paid_amount']) ?></span></td>
                                        <td><span style="color: #f59e0b; font-weight: 600;"><?= formatCurrency($challan['pending_amount']) ?></span></td>
                                        <td>
                                            <button class="modern-btn" onclick="showPaymentModal('labour_payment', <?= $challan['id'] ?>, <?= $challan['party_id'] ?>, <?= $challan['pending_amount'] ?>, '<?= htmlspecialchars(addslashes($challan['labour_name'])) ?>')" style="padding: 6px 12px; font-size: 11px;">
                                                <i class="fas fa-hand-holding-usd"></i> Pay
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment History Tab -->
                <div id="history-tab" class="tab-content" style="display: none;">
                    
                    <!-- Filters -->
                    <form method="GET" class="filter-card mb-3">
                        <div class="filter-row">
                            <select name="type" class="modern-select" style="flex:1;">
                                <option value="">All Payment Types</option>
                                <option value="customer_receipt" <?= $payment_type_filter === 'customer_receipt' ? 'selected' : '' ?>>Customer Receipt</option>
                                <option value="customer_refund" <?= $payment_type_filter === 'customer_refund' ? 'selected' : '' ?>>Customer Refund</option>
                                <option value="vendor_payment" <?= $payment_type_filter === 'vendor_payment' ? 'selected' : '' ?>>Vendor Payment</option>
                                <option value="labour_payment" <?= $payment_type_filter === 'labour_payment' ? 'selected' : '' ?>>Labour Payment</option>
                            </select>
                            
                            <div style="flex:1; display:flex; align-items:center; gap:5px;">
                                <span style="font-size:12px; color:#64748b;">From:</span>
                                <input type="date" name="date_from" class="modern-select" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            
                            <div style="flex:1; display:flex; align-items:center; gap:5px;">
                                <span style="font-size:12px; color:#64748b;">To:</span>
                                <input type="date" name="date_to" class="modern-select" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            
                            <button type="submit" class="modern-btn">Apply</button>
                            <a href="modules/payments/index.php?tab=history" class="modern-btn" style="background:#94a3b8;">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>TYPE</th>
                                    <th>PARTY</th>
                                    <th>AMOUNT</th>
                                    <th>MODE</th>
                                    <th>REF NO</th>
                                    <th>REMARKS</th>
                                    <th>BY</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="9" class="text-center" style="padding:40px; color:#94a3b8;">No payment records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): 
                                        $typeClass = 'gray';
                                        $typeLabel = 'Other';
                                        if($payment['payment_type'] === 'customer_receipt') { $typeClass = 'green'; $typeLabel = 'Receipt'; }
                                        if($payment['payment_type'] === 'vendor_payment') { $typeClass = 'blue'; $typeLabel = 'Vendor Pay'; }
                                        if($payment['payment_type'] === 'labour_payment') { $typeClass = 'orange'; $typeLabel = 'Labour Pay'; }
                                    ?>
                                    <tr>
                                        <td><?= formatDate($payment['payment_date']) ?></td>
                                        <td><span class="badge-pill <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                                        <td><span style="font-weight:600; color:#334155;"><?= htmlspecialchars($payment['party_name']) ?></span></td>
                                        <td><span style="font-weight: 700; color: #10b981;"><?= formatCurrency($payment['amount']) ?></span></td>
                                        <td><span style="font-size:12px; text-transform:capitalize;"><?= htmlspecialchars($payment['payment_mode']) ?></span></td>
                                        <td><span style="font-size:12px; color:#64748b;"><?= htmlspecialchars($payment['reference_no'] ?: '-') ?></span></td>
                                        <td><span style="font-size:12px; color:#64748b;"><?= htmlspecialchars(substr($payment['remarks'], 0, 30)) ?></span></td>
                                        <td><div class="avatar-circle av-gray" title="<?= htmlspecialchars($payment['created_by_name']) ?>" style="width:24px; height:24px; font-size:10px;"><?= strtoupper(substr($payment['created_by_name'],0,1)) ?></div></td>
                                        <td>
                                            <?php if($payment['payment_type'] === 'customer_receipt'): ?>
                                                <a href="<?= BASE_URL ?>modules/reports/download.php?action=payment_receipt&id=<?= $payment['id'] ?>" class="action-btn" target="_blank" title="Download Receipt">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Premium Payment Modal -->
<div id="paymentModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-cash-register"></i> Record Payment</h3>
                <p>Process a new transaction</p>
            </div>
            <button class="modal-close-btn" onclick="closeLocalModal('paymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <div class="modal-body-premium">
                <input type="hidden" name="action" value="make_payment">
                <input type="hidden" name="payment_type" id="payment_type">
                <input type="hidden" name="reference_id" id="reference_id">
                <input type="hidden" name="party_id" id="party_id">
                <input type="hidden" id="max_pending_amount" value="0">
                
                <div class="payment-summary-box">
                    <div class="summary-row">
                        <span style="color: #64748b; font-weight: 600;">Party Name</span>
                        <strong id="party_name_display" style="color: #1e293b; font-size: 15px;">-</strong>
                    </div>
                    <div class="summary-row">
                        <span style="color: #64748b; font-weight: 600;">Total Pending</span>
                        <strong id="pending_amount_display" style="color: #f59e0b; font-size: 15px;">₹ 0.00</strong>
                    </div>
                </div>

                <div class="form-section-title"><i class="fas fa-file-invoice"></i> Transaction Details</div>

                <div class="form-grid-premium">
                    <div>
                        <label class="input-label">Date *</label>
                        <input type="date" name="payment_date" class="modern-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="input-label">Amount (₹) *</label>
                        <input type="number" name="amount" id="payment_amount" class="modern-input" step="0.01" required oninput="calculateBalance()" placeholder="0.00" style="font-weight: 700; color: #10b981;">
                    </div>
                </div>
                
                <div class="form-grid-premium" style="margin-top: 20px;">
                    <div>
                        <label class="input-label">Payment Mode *</label>
                        <select name="payment_mode" class="modern-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="input-label">Reference / UTR No</label>
                        <input type="text" name="reference_no" class="modern-input" placeholder="Transaction ID">
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <label class="input-label">Remarks</label>
                    <textarea name="remarks" class="modern-input" rows="2" placeholder="Add optional notes..."></textarea>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 12px; margin-top: 24px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 13px; color: #64748b; font-weight: 600;">Remaining Balance</span>
                    <strong id="remaining_calc" style="font-size: 16px; color: #1e293b;">₹ 0.00</strong>
                </div>
                <div id="amount_warning" style="color: #ef4444; font-size: 12px; margin-top: 8px; display: none; text-align: right; font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> Requires admin approval for overpayment
                </div>

            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeLocalModal('paymentModal')">Cancel</button>
                <button type="submit" id="payment_submit_btn" class="btn-save">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.modern-tab').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tab + '-tab').style.display = 'block';
    
    // Find the button that called this
    const tabs = document.querySelectorAll('.modern-tab');
    if(tab === 'customer') tabs[0].classList.add('active');
    if(tab === 'vendor') tabs[1].classList.add('active');
    if(tab === 'labour') tabs[2].classList.add('active');
    if(tab === 'history') tabs[3].classList.add('active');
}

// Modal helper using the new CSS system
function openLocalModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLocalModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function showPaymentModal(type, refId, partyId, pendingAmount, partyName) {
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = refId;
    document.getElementById('party_id').value = partyId;
    document.getElementById('payment_amount').value = '';
    document.getElementById('max_pending_amount').value = pendingAmount;
    
    document.getElementById('party_name_display').textContent = partyName;
    const formattedPending = parseFloat(pendingAmount).toFixed(2);
    document.getElementById('pending_amount_display').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').style.color = '#1e293b';
    document.getElementById('amount_warning').style.display = 'none';
    
    openLocalModal('paymentModal');
}

function calculateBalance() {
    const pendingAmount = parseFloat(document.getElementById('max_pending_amount').value);
    const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
    const remaining = pendingAmount - paymentAmount;
    const warningEl = document.getElementById('amount_warning');
    const submitBtn = document.getElementById('payment_submit_btn');
    const displayEl = document.getElementById('remaining_calc');
    
    if (remaining >= 0) {
        displayEl.textContent = '₹ ' + remaining.toFixed(2);
        displayEl.style.color = remaining === 0 ? '#10b981' : '#1e293b';
        warningEl.style.display = 'none';
        // submitBtn.disabled = false; // Allow submission basically always now?
    } else {
        displayEl.textContent = '₹ ' + Math.abs(remaining).toFixed(2) + ' (Excess)';
        displayEl.style.color = '#ef4444';
        warningEl.style.display = 'block';
        // submitBtn.disabled = true; // Maybe allow overpayment? Keeping logic strict for now
    }
}

// Auto-switch tab based on URL parameter
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        switchTab(tab);
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
