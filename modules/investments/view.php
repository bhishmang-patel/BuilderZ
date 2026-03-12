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
$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Investment ID not specified.');
    redirect('modules/investments/index.php');
}

$sql = "SELECT i.*, p.project_name 
        FROM investments i 
        JOIN projects p ON i.project_id = p.id 
        WHERE i.id = ?";
$investment = $db->query($sql, [$id])->fetch();

if (!$investment) {
    setFlashMessage('error', 'Investment not found.');
    redirect('modules/investments/index.php');
}

// Fetch returns
$returns = $db->query("SELECT * FROM investment_returns WHERE investment_id = ? ORDER BY return_date DESC", [$id])->fetchAll();

$total_returns = 0;
foreach ($returns as $r) {
    $total_returns += $r['amount'];
}
$balance = $investment['amount'] - $total_returns;
$recovery_pct = $investment['amount'] > 0 ? ($total_returns / $investment['amount']) * 100 : 0;

$page_title = 'Investment Details';
$current_page = 'investments';
include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #b5622a;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── Layout ──────────────────────────────── */
    .iv-wrap {
        max-width: 1060px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .iv-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        flex-wrap: wrap;
        animation: fadeUp 0.45s ease both;
    }

    .iv-header-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .iv-back {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--ink-mute);
        text-decoration: none;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: color 0.15s ease;
    }
    .iv-back:hover { color: var(--accent); }

    .iv-sep { color: var(--border); font-size: 0.75rem; }
    .iv-crumb { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }

    .iv-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 0.4rem;
        line-height: 1.1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .iv-meta {
        font-size: 0.8rem;
        color: var(--ink-mute);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .iv-meta .dot { color: var(--border); }

    /* ── Type Pills ──────────────────────────── */
    .type-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
    }
    .type-pill::before {
        content: '';
        width: 5px; height: 5px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .type-partner   { background: #ecfdf5; color: #065f46; }
    .type-partner::before { background: #10b981; }
    .type-personal  { background: #eef2ff; color: #3730a3; }
    .type-personal::before { background: #6366f1; }
    .type-loan      { background: #fff7ed; color: #c2410c; }
    .type-loan::before { background: #f97316; }
    .type-other     { background: #f3f4f6; color: #374151; }
    .type-other::before { background: #6b7280; }

    /* ── Action Buttons ──────────────────────── */
    .iv-actions {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .iv-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: 1.5px solid transparent;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .iv-btn:hover { transform: translateY(-1px); }
    .iv-btn:active { transform: translateY(0); }

    .iv-btn-back {
        background: var(--surface);
        border-color: var(--border);
        color: var(--ink-soft);
    }
    .iv-btn-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .iv-btn-returns {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    .iv-btn-returns:hover { background: #d1fae5; border-color: #6ee7b7; }

    /* ── Metric Strip ────────────────────────── */
    .iv-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: var(--border);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        animation: fadeUp 0.45s ease both;
    }
    @media (max-width: 700px) { .iv-strip { grid-template-columns: repeat(2, 1fr); } }

    .iv-cell {
        background: var(--surface);
        padding: 1.1rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .iv-cell-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-mute);
    }

    .iv-cell-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ink);
    }
    .iv-cell-value.accent {
        font-family: 'Fraunces', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--accent);
    }
    .iv-cell-value.green {
        font-family: 'Fraunces', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: #059669;
    }
    .iv-cell-value.red {
        font-family: 'Fraunces', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: #dc2626;
    }

    /* ── Two-col info grid ───────────────────── */
    .iv-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
        animation: fadeUp 0.45s ease both;
    }
    @media (max-width: 680px) { .iv-grid { grid-template-columns: 1fr; } }

    /* ── Cards ────────────────────────────────── */
    .iv-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.25rem;
        animation: fadeUp 0.45s ease both;
    }

    .iv-card-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 1rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .iv-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; flex-shrink: 0;
    }
    .icon-invest   { background: var(--accent-lt); color: var(--accent); }
    .icon-finance  { background: #ecfdf5; color: #059669; }
    .icon-returns  { background: #eef2ff; color: #4f63d2; }
    .icon-notes    { background: #fefce8; color: #a16207; }

    .iv-card-head h2 {
        font-family: 'Fraunces', serif;
        font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
    }
    .iv-card-head .iv-card-badge {
        margin-left: auto;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 6px;
        background: var(--cream);
        color: var(--ink-mute);
    }

    .iv-card-body { padding: 1.4rem 1.5rem; }

    /* ── Detail rows ─────────────────────────── */
    .iv-rows { display: flex; flex-direction: column; gap: 0; }

    .iv-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px solid var(--border-lt);
    }
    .iv-row:last-child { border-bottom: none; padding-bottom: 0; }
    .iv-row:first-child { padding-top: 0; }

    .iv-label { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); letter-spacing: 0.02em; flex-shrink: 0; }
    .iv-val { font-size: 0.875rem; font-weight: 600; color: var(--ink); text-align: right; word-break: break-word; }
    .iv-val.muted { color: var(--ink-mute); font-weight: 400; }

    /* ── Recovery Bar ────────────────────────── */
    .iv-recovery {
        margin-top: 0.6rem;
    }
    .iv-recovery-track {
        width: 100%;
        height: 8px;
        background: var(--border-lt);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }
    .iv-recovery-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .iv-recovery-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--ink-mute);
    }
    .iv-recovery-pct {
        font-family: 'Fraunces', serif;
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
    }

    /* ── Returns Table ────────────────────────── */
    .iv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .iv-table thead th {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ink-mute);
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 1.5px solid var(--border);
    }
    .iv-table thead th:last-child { text-align: right; }
    .iv-table tbody td {
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--border-lt);
        color: var(--ink);
        font-weight: 500;
    }
    .iv-table tbody tr:last-child td { border-bottom: none; }
    .iv-table tbody td:last-child { text-align: right; }
    .iv-table .td-amount {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #059669;
    }
    .iv-table tfoot td {
        padding: 0.85rem 1rem;
        border-top: 1.5px solid var(--border);
        font-weight: 700;
        font-family: 'Fraunces', serif;
        font-size: 0.95rem;
    }

    /* ── Empty State ──────────────────────────── */
    .iv-empty {
        text-align: center;
        padding: 2.5rem 1rem;
    }
    .iv-empty-icon {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: var(--cream);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.9rem;
        font-size: 1.2rem;
        color: var(--ink-mute);
    }
    .iv-empty h4 {
        font-family: 'Fraunces', serif;
        font-size: 1rem;
        font-weight: 600;
        color: var(--ink-soft);
        margin: 0 0 0.25rem;
    }
    .iv-empty p {
        font-size: 0.82rem;
        color: var(--ink-mute);
        margin: 0;
    }

    /* ── Remarks box ─────────────────────────── */
    .iv-remarks {
        background: var(--cream);
        border: 1px solid var(--border-lt);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        font-size: 0.85rem;
        line-height: 1.7;
        color: var(--ink-soft);
        white-space: pre-wrap;
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<?php
    // Type pill class
    $type_class = match($investment['investment_type']) {
        'partner'  => 'type-partner',
        'personal' => 'type-personal',
        'loan'     => 'type-loan',
        default    => 'type-other'
    };
    $balance_color = $balance > 0 ? 'red' : 'green';
    $recovery_color = $recovery_pct >= 100 ? '#059669' : ($recovery_pct > 50 ? '#3b82f6' : '#f59e0b');
?>

<div class="iv-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="iv-header">
        <div>
            <div class="iv-header-meta">
                <a href="index.php" class="iv-back"><i class="fas fa-arrow-left"></i> Investments</a>
                <span class="iv-sep">/</span>
                <span class="iv-crumb">View Details</span>
            </div>
            <h1>
                <?= htmlspecialchars($investment['investor_name']) ?>
                <span class="type-pill <?= $type_class ?>"><?= ucfirst($investment['investment_type']) ?></span>
            </h1>
            <div class="iv-meta">
                <span><i class="fas fa-calendar-alt"></i> <?= date('d M, Y', strtotime($investment['investment_date'])) ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($investment['project_name']) ?></span>
                <?php if ($investment['source']): ?>
                <span class="dot">•</span>
                <span><i class="fas fa-university"></i> <?= htmlspecialchars($investment['source']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="iv-actions">
            <a href="returns.php?id=<?= $id ?>" class="iv-btn iv-btn-returns">
                <i class="fas fa-hand-holding-usd"></i> Manage Returns
            </a>
            <a href="index.php" class="iv-btn iv-btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ── Metrics Strip ────────────────────── -->
    <div class="iv-strip">
        <div class="iv-cell">
            <div class="iv-cell-label">Invested Amount</div>
            <div class="iv-cell-value accent"><?= formatCurrency($investment['amount']) ?></div>
        </div>
        <div class="iv-cell">
            <div class="iv-cell-label">Total Returned</div>
            <div class="iv-cell-value green"><?= formatCurrency($total_returns) ?></div>
        </div>
        <div class="iv-cell">
            <div class="iv-cell-label">Outstanding Balance</div>
            <div class="iv-cell-value <?= $balance_color ?>"><?= formatCurrency(abs($balance)) ?></div>
        </div>
        <div class="iv-cell">
            <div class="iv-cell-label">Recovery</div>
            <div class="iv-cell-value" style="font-family:'Fraunces',serif; font-weight:700; font-size:1.3rem; color:<?= $recovery_color ?>">
                <?= number_format($recovery_pct, 1) ?>%
            </div>
        </div>
    </div>

    <!-- ── Info Grid ─────────────────────────── -->
    <div class="iv-grid">

        <!-- Investment Details Card -->
        <div class="iv-card">
            <div class="iv-card-head">
                <div class="iv-icon icon-invest"><i class="fas fa-file-invoice-dollar"></i></div>
                <h2>Investment Details</h2>
            </div>
            <div class="iv-card-body">
                <div class="iv-rows">
                    <div class="iv-row">
                        <span class="iv-label">Project</span>
                        <span class="iv-val"><?= renderProjectBadge($investment['project_name'], $investment['project_id']) ?></span>
                    </div>
                    <div class="iv-row">
                        <span class="iv-label">Investor Name</span>
                        <span class="iv-val"><?= htmlspecialchars($investment['investor_name']) ?></span>
                    </div>
                    <div class="iv-row">
                        <span class="iv-label">Type</span>
                        <span class="iv-val"><?= ucfirst($investment['investment_type']) ?></span>
                    </div>
                    <div class="iv-row">
                        <span class="iv-label">Date</span>
                        <span class="iv-val"><?= date('d M, Y', strtotime($investment['investment_date'])) ?></span>
                    </div>
                    <?php if ($investment['source']): ?>
                    <div class="iv-row">
                        <span class="iv-label">Source of Fund</span>
                        <span class="iv-val"><?= htmlspecialchars($investment['source']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($investment['manual_equity_percentage'] > 0): ?>
                    <div class="iv-row">
                        <span class="iv-label">Equity/Capital %</span>
                        <span class="iv-val" style="color:var(--accent); font-weight:700"><?= number_format($investment['manual_equity_percentage'], 2) ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Financial Summary Card -->
        <div class="iv-card">
            <div class="iv-card-head">
                <div class="iv-icon icon-finance"><i class="fas fa-chart-pie"></i></div>
                <h2>Financial Summary</h2>
            </div>
            <div class="iv-card-body">
                <div class="iv-rows">
                    <div class="iv-row">
                        <span class="iv-label">Invested</span>
                        <span class="iv-val" style="color:var(--accent)"><?= formatCurrency($investment['amount']) ?></span>
                    </div>
                    <div class="iv-row">
                        <span class="iv-label">Total Returned</span>
                        <span class="iv-val" style="color:#059669">+ <?= formatCurrency($total_returns) ?></span>
                    </div>
                    <div class="iv-row" style="border-bottom:none; padding-bottom:0.3rem">
                        <span class="iv-label">Balance Due</span>
                        <span class="iv-val" style="color:<?= $balance > 0 ? '#dc2626' : '#059669' ?>; font-weight:700"><?= formatCurrency(abs($balance)) ?></span>
                    </div>
                </div>

                <!-- Recovery Progress -->
                <div class="iv-recovery">
                    <div class="iv-recovery-pct" style="color:<?= $recovery_color ?>"><?= number_format($recovery_pct, 1) ?>% Recovered</div>
                    <div class="iv-recovery-track">
                        <div class="iv-recovery-fill" style="width:<?= min(100, $recovery_pct) ?>%; background:<?= $recovery_color ?>"></div>
                    </div>
                    <div class="iv-recovery-meta">
                        <span><?= count($returns) ?> payment<?= count($returns) !== 1 ? 's' : '' ?> received</span>
                        <span><?= formatCurrency($total_returns) ?> / <?= formatCurrency($investment['amount']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Returns History ───────────────────── -->
    <div class="iv-card">
        <div class="iv-card-head">
            <div class="iv-icon icon-returns"><i class="fas fa-history"></i></div>
            <h2>Returns History</h2>
            <span class="iv-card-badge"><?= count($returns) ?> Record<?= count($returns) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="iv-card-body" style="padding:0">
            <?php if (empty($returns)): ?>
                <div class="iv-empty">
                    <div class="iv-empty-icon"><i class="fas fa-inbox"></i></div>
                    <h4>No returns recorded</h4>
                    <p>Record payments back to the investor via the Manage Returns page.</p>
                </div>
            <?php else: ?>
                <table class="iv-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Remarks</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $i => $ret): ?>
                        <tr>
                            <td style="color:var(--ink-mute); font-weight:600"><?= $i + 1 ?></td>
                            <td>
                                <span style="font-weight:600; color:var(--ink-soft); font-size:0.82rem">
                                    <?= date('d M, Y', strtotime($ret['return_date'])) ?>
                                </span>
                            </td>
                            <td style="color:var(--ink-soft)"><?= htmlspecialchars($ret['remarks'] ?: '—') ?></td>
                            <td class="td-amount"><?= formatCurrency($ret['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="color:var(--ink-soft)">Total Returns</td>
                            <td style="text-align:right; color:#059669"><?= formatCurrency($total_returns) ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Remarks Card ──────────────────────── -->
    <?php if ($investment['remarks']): ?>
    <div class="iv-card">
        <div class="iv-card-head">
            <div class="iv-icon icon-notes"><i class="fas fa-sticky-note"></i></div>
            <h2>Remarks &amp; Notes</h2>
        </div>
        <div class="iv-card-body">
            <div class="iv-remarks"><?= nl2br(htmlspecialchars($investment['remarks'])) ?></div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
