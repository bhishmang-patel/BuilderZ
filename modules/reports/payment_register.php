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
checkPermission(['admin', 'accountant', 'project_manager']);

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

// Legacy variable names for view compatibility
$total_receipts = $totals['receipts'];
$total_payments = $totals['payments'];
$total_refunds = $totals['refunds'];
$customer_receipts_count = $totals['counts']['receipts'];
$vendor_payments_count = $totals['counts']['vendor'];
$vendor_payments_count = $totals['counts']['vendor'];
$contractor_payments_count = $totals['counts']['contractor'];
$refunds_count = $totals['counts']['refunds'];
$cancellation_income = $totals['canc_income'];
$net_cashflow = $totals['net_cashflow'];
$net_income = $totals['net_income'];

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
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
    }

    /* ── Page Wrapper ────────────────────────── */
    .reg-wrap { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .reg-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .reg-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .reg-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .reg-header h1 em { font-style: italic; color: var(--accent); }

    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-dl {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: white; color: var(--ink-soft);
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--border);
    }
    .btn-dl:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
        position: relative; overflow: hidden;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:nth-child(4) { animation-delay: .2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-card::before {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0;
        height: 3px; opacity: 0.8;
    }
    .stat-card.receipts::before { background: #10b981; }
    .stat-card.payments::before { background: #f59e0b; }
    .stat-card.refunds::before { background: #ef4444; }
    .stat-card.net::before { background: var(--accent); }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.6rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
        margin-bottom: 0.4rem;
    }
    
    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }
    .stat-value.green { color: #10b981; }
    .stat-value.orange { color: #f59e0b; }
    .stat-value.red { color: #ef4444; }
    .stat-value.blue { color: var(--accent); }

    .stat-sub {
        font-size: 0.72rem; color: var(--ink-mute);
    }

    /* ── Main Panel ──────────────────────────── */
    .reg-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.25s ease both;
    }

    /* ── Toolbar ─────────────────────────────── */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: #10b981; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.2rem; }
    .toolbar-div { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; flex: 1; justify-content: flex-end; flex-wrap: wrap; }

    @media (max-width: 1100px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div { display: none; }
        .toolbar-actions { width: 100%; justify-content: flex-start; }
    }

    /* ── Filter Section ──────────────────────── */
    .filter-section {
        padding: 1.25rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .filter-form { display: flex; align-items: flex-end; gap: 0.65rem; flex-wrap: wrap; }

    .f-group { flex: 1; min-width: 180px; }
    .f-label {
        display: block; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .f-input, .f-select {
        width: 100%; height: 42px; padding: 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s, box-shadow 0.15s;
    }
    .f-select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .f-input:focus, .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }

    .date-range { display: flex; gap: 0.5rem; align-items: center; }
    .date-range span { color: var(--ink-mute); font-size: 0.82rem; }

    .btn-filter {
        height: 42px; padding: 0 1.4rem; border: none; border-radius: 8px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; background: var(--ink); color: white;
    }
    .btn-filter:hover { background: var(--accent); }

    /* ── Table ───────────────────────────────── */
    .reg-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .reg-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .reg-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .reg-table thead th.th-c { text-align: center; }
    .reg-table thead th.th-r { text-align: right; }

    .reg-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .reg-table tbody tr:last-child { border-bottom: none; }
    .reg-table tbody tr:hover { background: #fdfcfa; }

    .reg-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .reg-table td.td-c { text-align: center; }
    .reg-table td.td-r { text-align: right; }

    .reg-table tfoot {
        background: #fdfcfa; border-top: 1.5px solid var(--border);
        font-weight: 700;
    }
    .reg-table tfoot td { padding: 1rem; }

    /* Avatar */
    .av-circ {
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em;
    }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.blue   { background: var(--accent-bg); color: #1e40af; }
    .pill.orange { background: #fff7ed; color: #c2410c; }
    .pill.red    { background: #fef2f2; color: #b91c1c; }
    .pill.gray   { background: #f0ece5; color: var(--ink-soft); }

    /* Empty state */
    .empty-state {
        padding: 4rem 1rem; text-align: center;
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

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="reg-wrap">

    <!-- Header -->
    <div class="reg-header">
        <div>
            <div class="eyebrow">Financial Reports</div>
            <h1>Payment <em>Register</em></h1>
        </div>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=payment_register&format=excel&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-dl">
                <i class="fas fa-file-excel" style="color:#10b981"></i> Excel
            </a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=payment_register&format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="btn-dl">
                <i class="fas fa-file-csv" style="color:#0ea5e9"></i> CSV
            </a>
            <button class="btn-dl" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card receipts">
            <div class="stat-label">Total Receipts</div>
            <div class="stat-value green">
                <span class="short-val"><?= formatCurrencyShort($total_receipts) ?></span>
                <span class="full-val"><?= formatCurrency($total_receipts) ?></span>
            </div>
            <div class="stat-sub">From <?= $customer_receipts_count ?> transactions</div>
        </div>

        <div class="stat-card payments">
            <div class="stat-label">Total Payments</div>
            <div class="stat-value orange">
                <span class="short-val"><?= formatCurrencyShort($total_payments) ?></span>
                <span class="full-val"><?= formatCurrency($total_payments) ?></span>
            </div>
            <div class="stat-sub">Vendors: <?= $vendor_payments_count ?> | Contractors: <?= $contractor_payments_count ?></div>
        </div>

        <div class="stat-card refunds">
            <div class="stat-label">Refunds</div>
            <div class="stat-value red">
                <span class="short-val"><?= formatCurrencyShort($total_refunds) ?></span>
                <span class="full-val"><?= formatCurrency($total_refunds) ?></span>
            </div>
            <div class="stat-sub"><?= $refunds_count ?> processed</div>
        </div>

        <div class="stat-card net">
            <div class="stat-label">Net Cash Flow</div>
            <div class="stat-value <?= $net_cashflow >= 0 ? 'blue' : 'red' ?>">
                <span class="short-val"><?= formatCurrencyShort($net_cashflow) ?></span>
                <span class="full-val"><?= formatCurrency($net_cashflow) ?></span>
            </div>
            <div class="stat-sub">Receipts - (Payments + Refunds)</div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="reg-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <div class="toolbar-title">Transaction Log</div>
                    <div class="toolbar-subtitle">Comprehensive record of all financial transactions</div>
                </div>
            </div>
            <div class="toolbar-div"></div>
            <div class="toolbar-actions"></div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="f-group">
                    <label class="f-label">Date Range</label>
                    <div class="date-range">
                        <input type="date" name="date_from" class="f-input" value="<?= htmlspecialchars($date_from) ?>">
                        <span>to</span>
                        <input type="date" name="date_to" class="f-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="f-group">
                    <label class="f-label">Payment Type</label>
                    <select name="type" class="f-select">
                        <option value="">All Transactions</option>
                        <option value="customer_receipt" <?= $payment_type_filter === 'customer_receipt' ? 'selected' : '' ?>>Customer Receipt</option>
                        <option value="customer_refund" <?= $payment_type_filter === 'customer_refund' ? 'selected' : '' ?>>Customer Refund</option>
                        <option value="vendor_payment" <?= $payment_type_filter === 'vendor_payment' ? 'selected' : '' ?>>Vendor Payment</option>
                        <option value="vendor_payment" <?= $payment_type_filter === 'vendor_payment' ? 'selected' : '' ?>>Vendor Payment</option>
                        <option value="contractor_payment" <?= $payment_type_filter === 'contractor_payment' ? 'selected' : '' ?>>Contractor Payment</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="reg-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Party Name</th>
                        <th>Project</th>
                        <th>Mode</th>
                        <th>Ref No</th>
                        <th class="th-r">Inflow</th>
                        <th class="th-r">Outflow</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h4>No payment records found</h4>
                                    <p>No transactions found for the selected period.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($payments as $payment): 
                            $inflow = ($payment['payment_type'] === 'customer_receipt') ? $payment['amount'] : 0;
                            $outflow = (in_array($payment['payment_type'], ['vendor_payment', 'customer_refund', 'vendor_bill_payment', 'contractor_payment'])) ? $payment['amount'] : 0;
                            
                            $isVendor = in_array($payment['payment_type'], ['vendor_payment', 'vendor_bill_payment']);
                            $colorKey = $isVendor ? $payment['party_name'] : $payment['party_id'];
                            
                            $partyColor = ColorHelper::getCustomerColor($colorKey);
                            $partyInitial = strtoupper(substr($payment['party_name'], 0, 1));
                            
                            $badgeClass = 'gray';
                            $badgeLabel = 'Other';
                            if($payment['payment_type'] === 'customer_receipt') { $badgeClass = 'green'; $badgeLabel = 'Receipt'; }
                            if($payment['payment_type'] === 'customer_refund') { $badgeClass = 'red'; $badgeLabel = 'Refund'; }
                            if($payment['payment_type'] === 'vendor_payment') { $badgeClass = 'blue'; $badgeLabel = 'Vendor Pay'; }
                            if($payment['payment_type'] === 'vendor_bill_payment') { $badgeClass = 'blue'; $badgeLabel = 'Vendor Bill'; }
                            if($payment['payment_type'] === 'contractor_payment') { $badgeClass = 'orange'; $badgeLabel = 'Contractor Bill'; }
                    ?>
                    <tr>
                        <td>
                            <span style="font-weight:600;color:var(--ink)"><?= date('d M Y', strtotime($payment['payment_date'])) ?></span>
                        </td>
                        <td><span class="pill <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center">
                                <span style="font-weight:600"><?= htmlspecialchars($payment['party_name']) ?></span>
                            </div>
                        </td>
                        <td>
                            <?= renderProjectBadge($payment['project_name'], $payment['project_id']) ?>
                        </td>
                        <td><span style="font-size:0.82rem;text-transform:capitalize;color:var(--ink-soft)"><?= htmlspecialchars($payment['payment_mode']) ?></span></td>
                        <td><span style="font-family:monospace;color:var(--ink-mute);font-size:0.82rem"><?= htmlspecialchars($payment['reference_no'] ?: '—') ?></span></td>
                        <td class="td-r">
                            <?php if($inflow > 0): ?>
                                <span style="color:#10b981;font-weight:700">+ <?= formatCurrency($inflow) ?></span>
                            <?php else: ?>
                                <span style="color:var(--border)">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-r">
                            <?php if($outflow > 0): ?>
                                <span style="color:#ef4444;font-weight:700">- <?= formatCurrency($outflow) ?></span>
                            <?php else: ?>
                                <span style="color:var(--border)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span style="font-size:0.82rem;color:var(--ink-mute)"><?= htmlspecialchars(substr($payment['remarks'], 0, 40)) ?><?= strlen($payment['remarks']) > 40 ? '...' : '' ?></span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($payments)): ?>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right;color:var(--ink-soft)">TOTALS</td>
                        <td style="color:#10b981;text-align:right"><?= formatCurrency($total_receipts) ?></td>
                        <td style="color:#ef4444;text-align:right"><?= formatCurrency($total_payments + $total_refunds) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>