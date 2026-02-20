<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/ReportService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$project_id = $_GET['id'] ?? 0;
if (!$project_id) {
    setFlashMessage('error', 'Invalid Project ID');
    redirect('modules/reports/project_pl.php');
}

$reportService = new ReportService();
$data = $reportService->getProjectPLDetails($project_id);

if (!$data || !$data['summary']) {
    setFlashMessage('error', 'Project not found or no data available');
    redirect('modules/reports/project_pl.php');
}

$project = $data['summary'];
$expenses = $data['expense_breakdown'];

$page_title = 'Project P&L: ' . $project['project_name'];
$current_page = 'project_pl';

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<style>
/* Reusing styles from project_pl.php where appropriate, plus new ones */
:root {
    --ink:        #1a1714; --ink-soft: #6b6560; --ink-mute: #9e9690;
    --cream:      #f5f3ef; --surface:  #ffffff; --border:   #e8e3db; --border-lt: #f0ece5;
    --accent:     #2a58b5; --accent-bg:#f0f5ff; --green:    #059669; --green-lt: #d1fae5;
    --red:        #dc2626; --red-lt:   #fee2e2; --orange:   #d97706; --orange-lt:#fef3c7;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem 5rem; }

/* Header */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1.5px solid var(--border); padding-bottom: 1.5rem; opacity: 0; animation: fadeUp 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;}
.ph-title h1 { font-family: 'Fraunces', serif; font-size: 2rem; margin: 0; color: var(--ink); }
.ph-meta { font-size: 0.9rem; color: var(--ink-soft); margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }

.btn-back { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: 1.5px solid var(--border); background: white; color: var(--ink-soft); border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s; }
.btn-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* Stats Grid */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: white; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; text-align: center; 
    opacity: 0;
    animation: fadeUp 0.45s cubic-bezier(0.22,1,0.36,1) forwards;
}

/* Stagger effect */
.stat-card:nth-child(1) { animation-delay: 0.12s; }
.stat-card:nth-child(2) { animation-delay: 0.18s; }
.stat-card:nth-child(3) { animation-delay: 0.24s; }
.stat-card:nth-child(4) { animation-delay: 0.30s; }}
.sc-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--ink-mute); font-weight: 700; margin-bottom: 0.5rem; }
.sc-value { font-family: 'Fraunces', serif; font-size: 1.75rem; font-weight: 700; color: var(--ink); }
.sc-value.green { color: var(--green); }
.sc-value.red { color: var(--red); }
.sc-sub { font-size: 0.85rem; color: var(--ink-soft); margin-top: 5px; }

/* Details Panels */
.panels-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media(max-width: 768px) { .panels-grid { grid-template-columns: 1fr; } .stat-grid { grid-template-columns: 1fr 1fr; } }

.panel { background: white; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; height: 100%; 
    opacity: 0;
    animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) forwards;}
.panel-head { padding: 1.25rem; border-bottom: 1px solid var(--border-lt); background: #fafbff; display: flex; align-items: center; justify-content: space-between; }
.panel-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink); }
.panel-icon { color: var(--accent); font-size: 1.1rem; }
/* Stagger panels */
.panels-grid .panel:nth-child(1) { animation-delay: 0.38s; }
.panels-grid .panel:nth-child(2) { animation-delay: 0.45s; }

/* General expense panel */
.pw > .panel { animation-delay: 0.55s; }

.dt-list { padding: 0; margin: 0; list-style: none; }
.dt-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-lt); }
.dt-item:last-child { border-bottom: none; }
.dt-label { font-size: 0.9rem; color: var(--ink-soft); font-weight: 500; }
.dt-val { font-family: 'DM Sans', sans-serif; font-weight: 700; color: var(--ink); font-size: 0.95rem; }
.dt-val.green { color: var(--green); }
.dt-val.red { color: var(--red); }
.dt-val.orange { color: var(--orange); }

.alloc-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.alloc-table th { text-align: left; padding: 0.75rem 1.25rem; background: #fafbff; border-bottom: 1px solid var(--border-lt); font-size: 0.75rem; text-transform: uppercase; color: var(--ink-mute); letter-spacing: 0.05em; }
.alloc-table td { padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border-lt); color: var(--ink); }
.alloc-table tr:last-child td { border-bottom: none; }
.alloc-table .amount { text-align: right; font-weight: 600; font-family: 'DM Sans', sans-serif; }

/* ── Page Animations ───────────────────────── */

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

</style>

<div class="pw">
    <!-- Header -->
    <div class="page-header">
        <div class="ph-title">
            <div style="font-size:0.8rem; text-transform:uppercase; color:var(--accent); letter-spacing:0.1em; font-weight:700; margin-bottom:0.5rem;">Project Financial Report</div>
            <h1><?= htmlspecialchars($project['project_name']) ?></h1>
            <div class="ph-meta">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($project['location']) ?>
                &nbsp;&bull;&nbsp;
                <i class="fas fa-home"></i> <?= $project['total_bookings'] ?> Active Bookings
            </div>
        </div>
        <a href="project_pl.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="sc-label">Total Revenue</div>
            <div class="sc-value"><?= formatCurrencyShort($project['total_income']) ?></div>
            <div class="sc-sub">Collected</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Total Expense</div>
            <div class="sc-value red"><?= formatCurrencyShort($project['total_expense']) ?></div>
            <div class="sc-sub">Outflow</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Net Profit</div>
            <div class="sc-value <?= $project['net_profit'] >= 0 ? 'green' : 'red' ?>"><?= formatCurrencyShort($project['net_profit']) ?></div>
            <div class="sc-sub"><?= formatCurrency($project['net_profit']) ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Profit Margin</div>
            <div class="sc-value <?= $project['profit_margin'] >= 0 ? 'green' : 'red' ?>"><?= number_format($project['profit_margin'], 1) ?>%</div>
            <div class="sc-sub">of Total Sales</div>
        </div>
    </div>

    <div class="panels-grid">
        <!-- INCOME SECTION -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">Income Breakdown</div>
                <div class="panel-icon"><i class="fas fa-arrow-circle-down"></i></div>
            </div>
            <div class="dt-list">
                <div class="dt-item">
                    <span class="dt-label">Total Sales Value (Booked)</span>
                    <span class="dt-val"><?= formatCurrency($project['total_sales']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">Customer Receipts</span>
                    <span class="dt-val green">+ <?= formatCurrency($project['total_received']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">Cancellation Income</span>
                    <span class="dt-val green">+ <?= formatCurrency($project['cancellation_income']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">Pending Collection</span>
                    <span class="dt-val orange"><?= formatCurrency($project['customer_pending']) ?></span>
                </div>
                <div class="dt-item" style="background:#fcfcfc;">
                    <span class="dt-label" style="font-weight:700; color:var(--ink);">Total Operating Income</span>
                    <span class="dt-val" style="font-weight:700;"><?= formatCurrency($project['total_income']) ?></span>
                </div>
            </div>
        </div>

        <!-- EXPENSE SECTION -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">Expense Breakdown</div>
                <div class="panel-icon"><i class="fas fa-arrow-circle-up"></i></div>
            </div>
            <div class="dt-list">
                 <div class="dt-item">
                    <span class="dt-label">Vendor Payments</span>
                    <span class="dt-val"><?= formatCurrency($project['vendor_payments']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">Contractor Payments</span>
                    <span class="dt-val"><?= formatCurrency($project['contractor_payments']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">Customer Refunds</span>
                    <span class="dt-val"><?= formatCurrency($project['total_refunds']) ?></span>
                </div>
                <div class="dt-item">
                    <span class="dt-label">General Expenses</span>
                    <span class="dt-val"><?= formatCurrency($project['general_expenses']) ?></span>
                </div>
                <div class="dt-item" style="background:#fcfcfc;">
                    <span class="dt-label" style="font-weight:700; color:var(--ink);">Total Outflow</span>
                    <span class="dt-val red" style="font-weight:700;"><?= formatCurrency($project['total_expense']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- GENERAL EXPENSES BREAKDOWN -->
    <?php if (!empty($expenses)): ?>
    <div class="panel" style="margin-top:1.5rem;">
        <div class="panel-head">
            <div class="panel-title">General Expenses (Category-wise)</div>
            <div class="panel-icon"><i class="fas fa-tags"></i></div>
        </div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th>Expense Category</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right;">% of General Exp.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $exp): 
                    $pct = ($project['general_expenses'] > 0) ? ($exp['total_amount'] / $project['general_expenses']) * 100 : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($exp['category_name']) ?></td>
                    <td class="amount"><?= formatCurrency($exp['total_amount']) ?></td>
                    <td class="amount" style="color:var(--ink-mute); font-size:0.85rem;"><?= number_format($pct, 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
