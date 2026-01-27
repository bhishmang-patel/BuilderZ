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

$db = Database::getInstance();
$page_title = 'Payment Register';
$current_page = 'payment_register';

// Get filter values
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_type_filter = $_GET['type'] ?? '';

// Fetch payments
require_once __DIR__ . '/../../includes/ReportService.php';
$reportService = new ReportService();
$data = $reportService->getPaymentRegister($date_from, $date_to, $payment_type_filter);

$payments = $data['payments'];
$totals = $data['totals'];

// Legacy variable names for view compatibility (can be refactored further later)
$total_receipts = $totals['receipts'];
$total_payments = $totals['payments'];
$total_refunds = $totals['refunds'];
$customer_receipts_count = $totals['counts']['receipts'];
$vendor_payments_count = $totals['counts']['vendor'];
$labour_payments_count = $totals['counts']['labour'];
$refunds_count = $totals['counts']['refunds'];
$cancellation_income = $totals['canc_income'];
$net_cashflow = $totals['net_cashflow'];
$net_income = $totals['net_income'];

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Stats Card Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-modern {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    border-color: #cbd5e1;
}

.stat-card-modern.receipts { border-bottom: 4px solid #10b981; }
.stat-card-modern.payments { border-bottom: 4px solid #f59e0b; }
.stat-card-modern.refunds { border-bottom: 4px solid #ef4444; }
.stat-card-modern.net { border-bottom: 4px solid #3b82f6; }

.stat-label-modern {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value-modern {
    font-size: 24px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-subtext {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 500;
}

.filter-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.badge-pill {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.badge-pill.green { background: #dcfce7; color: #166534; }
.badge-pill.blue { background: #dbeafe; color: #1e40af; }
.badge-pill.orange { background: #ffedd5; color: #9a3412; }
.badge-pill.red { background: #fee2e2; color: #991b1b; }
.badge-pill.gray { background: #f1f5f9; color: #475569; }

/* Filter Controls */
.filter-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.modern-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.btn-download {
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
}
.btn-download:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }

</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box emerald"><i class="fas fa-file-invoice-dollar"></i></div>
                        Payment Register
                    </h3>
                    <div class="chart-subtitle">Comprehensive record of all financial transactions</div>
                </div>
                <div class="chart-actions-group">
                    <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=payment_register&format=excel&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="modern-btn btn-download">
                        <i class="fas fa-file-excel" style="color: #10b981;"></i> Excel
                    </a>
                    <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=payment_register&format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="modern-btn btn-download">
                        <i class="fas fa-file-csv" style="color: #0ea5e9;"></i> CSV
                    </a>
                    <button class="modern-btn btn-download" onclick="window.print()">
                        <i class="fas fa-print" style="color: #64748b;"></i> Print
                    </button>
                </div>
            </div>

            <div style="padding: 25px;">

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card-modern receipts">
                        <div class="stat-label-modern">Total Receipts</div>
                        <div class="stat-icon-circle" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-arrow-down"></i></div>
                        <div class="stat-value-modern" style="color: #10b981;">
                            <span class="short-value"><?= formatCurrencyShort($total_receipts) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_receipts) ?></span>
                        </div>
                        <div class="stat-subtext">From <?= $customer_receipts_count ?> transactions</div>
                    </div>
                    <div class="stat-card-modern payments">
                        <div class="stat-label-modern">Total Payments</div>
                        <div class="stat-icon-circle" style="background: #fffbeb; color: #f59e0b;"><i class="fas fa-arrow-up"></i></div>
                        <div class="stat-value-modern" style="color: #f59e0b;">
                            <span class="short-value"><?= formatCurrencyShort($total_payments) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_payments) ?></span>
                        </div>
                        <div class="stat-subtext">Vendors: <?= $vendor_payments_count ?> | Labour: <?= $labour_payments_count ?></div>
                    </div>
                    <div class="stat-card-modern refunds">
                        <div class="stat-label-modern">Refunds</div>
                        <div class="stat-icon-circle" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-undo"></i></div>
                        <div class="stat-value-modern" style="color: #ef4444;">
                            <span class="short-value"><?= formatCurrencyShort($total_refunds) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($total_refunds) ?></span>
                        </div>
                        <div class="stat-subtext"><?= $refunds_count ?> processed</div>
                    </div>
                    <div class="stat-card-modern net">
                        <div class="stat-label-modern">Net Cash Flow</div>
                        <div class="stat-icon-circle" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-wallet"></i></div>
                        <div class="stat-value-modern" style="color: <?= $net_cashflow >= 0 ? '#3b82f6' : '#ef4444' ?>;">
                            <span class="short-value"><?= formatCurrencyShort($net_cashflow) ?></span>
                            <span class="full-value"><?= formatCurrencyIndian($net_cashflow) ?></span>
                        </div>
                        <div class="stat-subtext">Receipts - (Payments + Refunds)</div>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="input-label">Date Range</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="date" name="date_from" class="form-control-custom" value="<?= htmlspecialchars($date_from) ?>">
                                <span style="color: #94a3b8;">to</span>
                                <input type="date" name="date_to" class="form-control-custom" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                        </div>
                        
                        <div style="flex: 1; min-width: 200px;">
                            <label class="input-label">Payment Type</label>
                            <select name="type" class="form-control-custom">
                                <option value="">All Transactions</option>
                                <option value="customer_receipt" <?= $payment_type_filter === 'customer_receipt' ? 'selected' : '' ?>>Customer Receipt</option>
                                <option value="customer_refund" <?= $payment_type_filter === 'customer_refund' ? 'selected' : '' ?>>Customer Refund</option>
                                <option value="vendor_payment" <?= $payment_type_filter === 'vendor_payment' ? 'selected' : '' ?>>Vendor Payment</option>
                                <option value="labour_payment" <?= $payment_type_filter === 'labour_payment' ? 'selected' : '' ?>>Labour Payment</option>
                            </select>
                        </div>

                        <div style="display: flex; align-items: flex-end;">
                            <button type="submit" class="modern-btn" style="background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); color: white; margin-top: 25px;">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Table -->
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>TYPE</th>
                                <th>PARTY NAME</th>
                                <th>MODE</th>
                                <th>REF NO</th>
                                <th>INFLOW</th>
                                <th>OUTFLOW</th>
                                <th>REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-search" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                        No payment records found for the selected period.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): 
                                    $inflow = ($payment['payment_type'] === 'customer_receipt') ? $payment['amount'] : 0;
                                    $outflow = (in_array($payment['payment_type'], ['vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment'])) ? $payment['amount'] : 0;
                                    
                                    // Match color logic with source modules (Vendors uses Name, others use ID)
                                    $isVendor = in_array($payment['payment_type'], ['vendor_payment', 'vendor_bill_payment']);
                                    $colorKey = $isVendor ? $payment['party_name'] : $payment['party_id'];
                                    
                                    $partyColor = ColorHelper::getCustomerColor($colorKey);
                                    $partyInitial = strtoupper(substr($payment['party_name'], 0, 1));
                                    
                                    $badgeClass = 'gray';
                                    $badgeLabel = 'Other';
                                    if($payment['payment_type'] === 'customer_receipt') { $badgeClass = 'green'; $badgeLabel = 'Receipt'; }
                                    if($payment['payment_type'] === 'customer_refund') { $badgeClass = 'red'; $badgeLabel = 'Refund'; }
                                    if($payment['payment_type'] === 'vendor_payment') { $badgeClass = 'blue'; $badgeLabel = 'Vendor Pay'; }
                                    if($payment['payment_type'] === 'vendor_bill_payment') { $badgeClass = 'blue'; $badgeLabel = 'Bill Pay'; }
                                    if($payment['payment_type'] === 'labour_payment') { $badgeClass = 'orange'; $badgeLabel = 'Labour Pay'; }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;"><?= date('d M Y', strtotime($payment['payment_date'])) ?></div>
                                    </td>
                                    <td><span class="badge-pill <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                                    <td>
                                        <div style="display:flex; align-items:center;">
                                            <div class="avatar-circle" style="background: <?= $partyColor ?>; color: #fff; width:32px; height:32px; font-size:12px; margin-right:10px;"><?= $partyInitial ?></div>
                                            <span style="font-weight:600; color:#334155;"><?= htmlspecialchars($payment['party_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><span style="font-size:13px; text-transform:capitalize; color: #64748b;"><?= htmlspecialchars($payment['payment_mode']) ?></span></td>
                                    <td><span style="font-family: monospace; color: #64748b; font-size: 13px;"><?= htmlspecialchars($payment['reference_no'] ?: '-') ?></span></td>
                                    <td>
                                        <?php if($inflow > 0): ?>
                                            <span style="color: #10b981; font-weight: 700;">+ <?= formatCurrency($inflow) ?></span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($outflow > 0): ?>
                                            <span style="color: #ef4444; font-weight: 600;">- <?= formatCurrency($outflow) ?></span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size: 13px; color: #94a3b8;"><?= htmlspecialchars(substr($payment['remarks'], 0, 40)) ?><?= strlen($payment['remarks']) > 40 ? '...' : '' ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($payments)): ?>
                        <tfoot style="background: #f8fafc; font-weight: 700;">
                            <tr>
                                <td colspan="5" style="text-align: right; color: #64748b;">TOTALS</td>
                                <td style="color: #10b981;"><?= formatCurrency($total_receipts) ?></td>
                                <td style="color: #ef4444;"><?= formatCurrency($total_payments + $total_refunds) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
