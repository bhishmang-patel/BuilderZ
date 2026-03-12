<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/InvestmentService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

$db = Database::getInstance();

$investmentId = $_GET['id'] ?? null;
if (!$investmentId) {
    header("Location: index.php");
    exit;
}

$investmentService = new InvestmentService();
$investment = $investmentService->getInvestmentById($investmentId);

if (!$investment) die("Investment not found.");

// Fetch bank accounts for the dropdown
$bank_accounts = $db->query("SELECT id, bank_name, account_number, account_type FROM company_accounts WHERE status = 'active' ORDER BY bank_name")->fetchAll();

$success = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_return') {
        $returnId = $_POST['return_id'] ?? null;
        if ($returnId && $investmentService->deleteReturn($returnId, $investmentId)) {
            $success = "Return deleted successfully.";
            $investment = $investmentService->getInvestmentById($investmentId);
        } else {
            $error = "Failed to delete the return.";
        }
    } else {
        $amount       = $_POST['amount'] ?? 0;
        $date         = $_POST['return_date'] ?? date('Y-m-d');
        $remarks      = $_POST['remarks'] ?? '';
        $payment_mode = $_POST['payment_mode'] ?? 'bank_transfer';
        $account_id   = !empty($_POST['company_account_id']) ? intval($_POST['company_account_id']) : null;

        // Require bank account for non-cash payment modes
        if (in_array($payment_mode, ['bank_transfer', 'upi', 'cheque']) && empty($account_id)) {
            $error = 'A bank account must be selected for ' . ucfirst(str_replace('_', ' ', $payment_mode)) . ' payments.';
        } elseif ($amount > 0 && !empty($date)) {
            if ($amount > $investment['balance']) {
                $error = "Return amount cannot exceed outstanding balance (" . formatCurrency($investment['balance']) . ")";
            } else {
                if ($investmentService->addReturn($investmentId, [
                    'amount'             => $amount,
                    'return_date'        => $date,
                    'remarks'            => $remarks,
                    'payment_mode'       => $payment_mode,
                    'company_account_id' => $account_id,
                ])) {
                    $success = "Return recorded successfully!";
                    $investment = $investmentService->getInvestmentById($investmentId);
                } else {
                    $error = "Failed to record return.";
                }
            }
        } else {
            $error = "Please enter a valid amount and date.";
        }
    }
}

$returns = $investmentService->getInvestmentReturns($investmentId);

$total_returns = 0;
foreach ($returns as $r) { $total_returns += $r['amount']; }
$recovery_pct = $investment['amount'] > 0 ? ($total_returns / $investment['amount']) * 100 : 0;
$recovery_color = $recovery_pct >= 100 ? '#059669' : ($recovery_pct > 50 ? '#3b82f6' : '#f59e0b');

$page_title   = 'Investment Returns';
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

@keyframes slideUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn   { from { opacity:0; transform:translateX(-8px); } to { opacity:1; transform:translateX(0); } }

/* ── Layout ──────────────────────────── */
.rt-wrap {
    max-width: 1060px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ── Header ──────────────────────────── */
.rt-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    flex-wrap: wrap;
    opacity: 0;
    animation: slideUp .45s cubic-bezier(.22,1,.36,1) .05s forwards;
}

.rt-header-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.rt-back {
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
.rt-back:hover { color: var(--accent); text-decoration: none; }

.rt-sep { color: var(--border); font-size: 0.75rem; }
.rt-crumb { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }

.rt-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 1.85rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 0.35rem;
    line-height: 1.1;
}
.rt-header h1 em { font-style: italic; color: var(--accent); }

.rt-header-sub {
    font-size: 0.82rem;
    color: var(--ink-mute);
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.rt-header-sub .dot { color: var(--border); }

.rt-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.rt-btn {
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
.rt-btn:hover { transform: translateY(-1px); text-decoration: none; }

.rt-btn-view {
    background: var(--accent-lt);
    border-color: #f0c9a8;
    color: var(--accent);
}
.rt-btn-view:hover { background: #fde8d0; border-color: var(--accent); color: var(--accent); }

.rt-btn-back {
    background: var(--surface);
    border-color: var(--border);
    color: var(--ink-soft);
}
.rt-btn-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

/* ── Metric Strip ────────────────────── */
.rt-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    opacity: 0;
    animation: slideUp .42s cubic-bezier(.22,1,.36,1) .1s forwards;
}
@media (max-width: 700px) { .rt-strip { grid-template-columns: repeat(2, 1fr); } }

.rt-cell {
    background: var(--surface);
    padding: 1.1rem 1.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.rt-cell-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink-mute);
}

.rt-cell-value {
    font-family: 'Fraunces', serif;
    font-size: 1.25rem;
    font-weight: 700;
}
.rt-cell-value.amber { color: var(--accent); }
.rt-cell-value.green { color: #059669; }
.rt-cell-value.red   { color: #dc2626; }

/* ── Two-col Grid ────────────────────── */
.rt-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) { .rt-grid { grid-template-columns: 1fr; } }

/* ── Cards ────────────────────────────── */
.rt-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    opacity: 0;
    animation: slideUp .42s cubic-bezier(.22,1,.36,1) both;
}
.rt-card.d1 { animation-delay: .12s; }
.rt-card.d2 { animation-delay: .18s; }

.rt-card-head {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}

.rt-ci {
    width: 28px; height: 28px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; flex-shrink: 0;
}
.rt-ci.form    { background: #eef2ff; color: #4f63d2; }
.rt-ci.history { background: #ecfdf5; color: #059669; }

.rt-card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0; flex: 1;
}

.rt-badge {
    font-size: 0.62rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    background: var(--cream);
    color: var(--ink-mute);
    border: 1px solid var(--border);
}

.rt-card-body { padding: 1.4rem 1.5rem; }

/* ── Info Rows ───────────────────────── */
.rt-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0;
    border-bottom: 1px solid var(--border-lt);
    font-size: 0.82rem;
}
.rt-info-row:last-child { border-bottom: none; }
.rt-info-final {
    border-top: 1.5px solid var(--border);
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-bottom: none;
}

.rt-info-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--ink-mute);
}
.rt-info-final .rt-info-label { font-weight: 700; color: var(--ink); }

.rt-info-val {
    font-family: 'Fraunces', serif;
    font-weight: 700;
    color: var(--ink);
    text-align: right;
}
.rt-info-val.green { color: #059669; }
.rt-info-val.red   { color: #dc2626; }
.rt-info-final .rt-info-val { font-size: 1.05rem; }

/* ── Recovery Progress ───────────────── */
.rt-progress { margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid var(--border-lt); }
.rt-progress-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--ink-mute);
    margin-bottom: 0.4rem;
}
.rt-progress-track {
    width: 100%; height: 8px;
    background: var(--border-lt);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.35rem;
}
.rt-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.rt-progress-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ink-mute);
}

/* ── Alerts ──────────────────────────── */
.rt-alert {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.75rem 1rem;
    border-radius: 9px;
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 1rem;
}
.rt-alert.success { background: #d1fae5; color: #059669; border: 1.5px solid #6ee7b7; }
.rt-alert.error   { background: #fee2e2; color: #dc2626; border: 1.5px solid #fca5a5; }

/* ── Form Fields ─────────────────────── */
.rt-field { display: flex; flex-direction: column; gap: 0.28rem; margin-bottom: 0.9rem; }
.rt-field:last-child { margin-bottom: 0; }
.rt-field label {
    font-size: 0.63rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink-mute);
}
.rt-field label .req { color: #dc2626; margin-left: 2px; }
.rt-field input,
.rt-field textarea,
.rt-field select {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    color: var(--ink);
    background: #fdfcfa;
    outline: none;
    transition: border-color 0.18s, box-shadow 0.18s;
    -webkit-appearance: none;
    appearance: none;
}
.rt-field input { height: 40px; }
.rt-field textarea { min-height: 70px; resize: vertical; }
.rt-field input:focus,
.rt-field textarea:focus,
.rt-field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(181,98,42,0.11);
    background: white;
}

.rt-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.rt-field .rt-help {
    font-size: 0.7rem;
    color: var(--ink-mute);
    margin-top: 0.15rem;
}

/* ── Select wrapper ──────────────────── */
.rt-select-wrap { position: relative; }
.rt-select-wrap::after {
    content: '▾';
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--ink-mute);
    font-size: 11px;
    pointer-events: none;
}
.rt-select-wrap select { padding-right: 30px; }

/* ── Section Tag ─────────────────────── */
.rt-section-tag {
    font-size: 0.6rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--ink-mute);
    padding: 0.8rem 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.4rem;
}
.rt-section-tag i { font-size: 0.7rem; opacity: 0.6; }
.rt-section-tag::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-lt);
}

/* ── Payment Mode Cards ──────────────── */
.pm-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.9rem;
}

.pm-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 0.75rem 0.5rem;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
}
.pm-card:hover {
    border-color: var(--accent);
    background: var(--accent-bg);
}
.pm-card.active {
    border-color: var(--accent);
    background: var(--accent-lt);
    box-shadow: 0 0 0 3px rgba(181,98,42,0.11);
}

.pm-card input { display: none; }

.pm-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    background: var(--cream);
    color: var(--ink-mute);
    transition: all 0.18s;
}
.pm-card.active .pm-icon {
    background: var(--accent);
    color: white;
}

.pm-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--ink-mute);
    transition: color 0.18s;
}
.pm-card.active .pm-label { color: var(--accent); }

/* ── Submit ──────────────────────────── */
.rt-submit {
    width: 100%;
    height: 42px;
    background: var(--ink);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}
.rt-submit:hover {
    background: var(--accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(181,98,42,0.3);
}

/* ── Table ────────────────────────────── */
.rt-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.rt-table thead th {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink-mute);
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1.5px solid var(--border);
}
.rt-table thead th.ar { text-align: right; }
.rt-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s;
}
.rt-table tbody tr:last-child { border-bottom: none; }
.rt-table tbody tr:hover { background: var(--accent-bg); }
.rt-table tbody tr.row-anim {
    opacity: 0;
    animation: rowIn 0.24s cubic-bezier(.22,1,.36,1) forwards;
}
.rt-table td { padding: 0.8rem 1rem; vertical-align: middle; }
.rt-table td.ar { text-align: right; }
.rt-table .td-idx {
    font-weight: 600;
    color: var(--ink-mute);
    font-size: 0.78rem;
    width: 36px;
}
.rt-table .td-date {
    font-weight: 600;
    color: var(--ink-soft);
    font-size: 0.82rem;
}
.rt-table .td-remarks {
    color: var(--ink-soft);
    font-size: 0.82rem;
}
.rt-table .td-amount {
    font-family: 'Fraunces', serif;
    font-weight: 700;
    color: #059669;
    font-variant-numeric: tabular-nums;
}
.rt-table .td-act {
    text-align: center;
    width: 42px;
}
.rt-table .td-act button {
    background: none;
    border: none;
    color: var(--ink-mute);
    cursor: pointer;
    font-size: 0.78rem;
    padding: 0.3rem;
    border-radius: 5px;
    transition: all 0.15s;
}
.rt-table .td-act button:hover { color: #dc2626; background: #fee2e2; }

.rt-table tfoot td {
    padding: 0.85rem 1rem;
    border-top: 1.5px solid var(--border);
    font-weight: 700;
    font-family: 'Fraunces', serif;
    font-size: 0.95rem;
}

/* ── Empty State ─────────────────────── */
.rt-empty {
    text-align: center;
    padding: 3.5rem 1.5rem;
}
.rt-empty-icon {
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
.rt-empty h4 {
    font-family: 'Fraunces', serif;
    font-size: 1rem;
    font-weight: 600;
    color: var(--ink-soft);
    margin: 0 0 0.25rem;
}
.rt-empty p {
    font-size: 0.82rem;
    color: var(--ink-mute);
    margin: 0;
}

/* ── Delete Modal ────────────────────── */
.rt-modal-backdrop {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); backdrop-filter: blur(6px);
    z-index: 9999; display: flex; align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; transition: all 0.25s ease;
}
.rt-modal-backdrop.open { opacity: 1; visibility: visible; }

.rt-modal {
    background: white; width: 90%; max-width: 380px;
    border-radius: 16px; padding: 2rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    transform: scale(0.9) translateY(20px);
    transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
    text-align: center;
}
.rt-modal-backdrop.open .rt-modal { transform: scale(1) translateY(0); }

.rt-modal-icon {
    width: 56px; height: 56px;
    background: #fee2e2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    font-size: 1.3rem;
    color: #dc2626;
}
.rt-modal h4 { margin: 0 0 0.5rem; font-size: 1.1rem; font-weight: 700; color: var(--ink); }
.rt-modal p { margin: 0 0 1.5rem; color: var(--ink-soft); font-size: 0.88rem; line-height: 1.5; }
.rt-modal-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.rt-modal-btn {
    padding: 0.65rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    transition: all 0.15s;
}
.rt-modal-btn.cancel { background: white; color: var(--ink); border: 1.5px solid var(--border); }
.rt-modal-btn.cancel:hover { background: var(--cream); }
.rt-modal-btn.danger { background: #dc2626; color: white; box-shadow: 0 4px 8px rgba(220,38,38,0.25); }
.rt-modal-btn.danger:hover { background: #b91c1c; }
</style>

<div class="rt-wrap">

    <!-- ── Header ──────────────────────── -->
    <div class="rt-header">
        <div>
            <div class="rt-header-meta">
                <a href="index.php" class="rt-back"><i class="fas fa-arrow-left"></i> Investments</a>
                <span class="rt-sep">/</span>
                <a href="view.php?id=<?= $investmentId ?>" class="rt-back"><?= htmlspecialchars($investment['investor_name']) ?></a>
                <span class="rt-sep">/</span>
                <span class="rt-crumb">Returns</span>
            </div>
            <h1>Manage <em>Returns</em></h1>
            <div class="rt-header-sub">
                <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($investment['investor_name']) ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($investment['project_name'] ?? 'General') ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-tag"></i> <?= ucfirst($investment['investment_type']) ?></span>
            </div>
        </div>
        <div class="rt-header-actions">
            <a href="view.php?id=<?= $investmentId ?>" class="rt-btn rt-btn-view">
                <i class="fas fa-eye"></i> View Investment
            </a>
            <a href="index.php" class="rt-btn rt-btn-back">
                <i class="fas fa-arrow-left"></i> All Investments
            </a>
        </div>
    </div>

    <!-- ── Metric Strip ────────────────── -->
    <div class="rt-strip">
        <div class="rt-cell">
            <div class="rt-cell-label">Invested Amount</div>
            <div class="rt-cell-value amber"><?= formatCurrency($investment['amount']) ?></div>
        </div>
        <div class="rt-cell">
            <div class="rt-cell-label">Total Returned</div>
            <div class="rt-cell-value green"><?= formatCurrency($total_returns) ?></div>
        </div>
        <div class="rt-cell">
            <div class="rt-cell-label">Outstanding</div>
            <div class="rt-cell-value <?= $investment['balance'] > 0 ? 'red' : 'green' ?>"><?= formatCurrency($investment['balance']) ?></div>
        </div>
        <div class="rt-cell">
            <div class="rt-cell-label">Recovery</div>
            <div class="rt-cell-value" style="color:<?= $recovery_color ?>"><?= number_format($recovery_pct, 1) ?>%</div>
        </div>
    </div>

    <!-- ── Main Grid ───────────────────── -->
    <div class="rt-grid">

        <!-- ── Left: Record Form ──── -->
        <div>
            <div class="rt-card d1">
                <div class="rt-card-head">
                    <div class="rt-ci form"><i class="fas fa-plus-circle"></i></div>
                    <h2>Record New Return</h2>
                </div>
                <div class="rt-card-body">

                    <?php if ($success): ?>
                        <div class="rt-alert success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="rt-alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($investment['balance'] <= 0): ?>
                        <div class="rt-alert success" style="margin-bottom:0">
                            <i class="fas fa-check-circle"></i>
                            Fully Settled! No outstanding balance remaining.
                        </div>
                    <?php else: ?>
                        <form method="POST">

                            <!-- Amount & Date -->
                            <div class="rt-section-tag"><i class="fas fa-rupee-sign"></i> Amount & Date</div>
                            <div class="rt-field-row">
                                <div class="rt-field">
                                    <label>Amount <span class="req">*</span></label>
                                    <input type="number" name="amount" step="0.01"
                                           max="<?= $investment['balance'] ?>" required
                                           placeholder="₹ 0.00">
                                </div>
                                <div class="rt-field">
                                    <label>Date <span class="req">*</span></label>
                                    <input type="date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <!-- Payment Mode -->
                            <div class="rt-section-tag"><i class="fas fa-wallet"></i> Payment Mode</div>
                            <div class="pm-grid">
                                <label class="pm-card active" data-mode="bank_transfer">
                                    <input type="radio" name="payment_mode" value="bank_transfer" checked>
                                    <div class="pm-icon"><i class="fas fa-university"></i></div>
                                    <span class="pm-label">Bank</span>
                                </label>
                                <label class="pm-card" data-mode="upi">
                                    <input type="radio" name="payment_mode" value="upi">
                                    <div class="pm-icon"><i class="fas fa-qrcode"></i></div>
                                    <span class="pm-label">UPI</span>
                                </label>
                                <label class="pm-card" data-mode="cash">
                                    <input type="radio" name="payment_mode" value="cash">
                                    <div class="pm-icon"><i class="fas fa-money-bill-wave"></i></div>
                                    <span class="pm-label">Cash</span>
                                </label>
                                <label class="pm-card" data-mode="cheque">
                                    <input type="radio" name="payment_mode" value="cheque">
                                    <div class="pm-icon"><i class="fas fa-money-check"></i></div>
                                    <span class="pm-label">Cheque</span>
                                </label>
                            </div>

                            <!-- Source / Destination Account -->
                            <div class="rt-section-tag"><i class="fas fa-building"></i> Company Account</div>
                            <div class="rt-field" id="accountField">
                                <label>Source / Destination Account <span style="color:#dc2626">*</span></label>
                                <div class="rt-select-wrap">
                                    <select name="company_account_id" id="accountSelect">
                                        <option value="">— Select bank account —</option>
                                        <?php foreach ($bank_accounts as $bank): ?>
                                            <option value="<?= $bank['id'] ?>">
                                                <?= htmlspecialchars($bank['bank_name']) ?> - ***<?= substr($bank['account_number'], -4) ?> (<?= ucfirst($bank['account_type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="rt-help">Which company account sends or receives this amount?</span>
                            </div>

                            <!-- Remarks -->
                            <div class="rt-section-tag"><i class="fas fa-sticky-note"></i> Notes</div>
                            <div class="rt-field">
                                <label>Remarks</label>
                                <textarea name="remarks" placeholder="e.g. Cheque #1234, NEFT ref, UPI txn ID..."></textarea>
                            </div>

                            <button type="submit" class="rt-submit">
                                <i class="fas fa-save"></i> Record Return
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Right: Investment Details ──────── -->
        <div>
            <div class="rt-card d2">
                <div class="rt-card-head">
                    <div class="rt-ci" style="background:var(--accent-lt); color:var(--accent)"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h2>Investment Details</h2>
                </div>
                <div class="rt-card-body">
                    <div class="rt-info-row">
                        <span class="rt-info-label">Investor</span>
                        <span class="rt-info-val"><?= htmlspecialchars($investment['investor_name']) ?></span>
                    </div>
                    <div class="rt-info-row">
                        <span class="rt-info-label">Project</span>
                        <span class="rt-info-val"><?= renderProjectBadge($investment['project_name'] ?? 'General', $investment['project_id']) ?></span>
                    </div>
                    <div class="rt-info-row">
                        <span class="rt-info-label">Date</span>
                        <span class="rt-info-val"><?= date('d M, Y', strtotime($investment['investment_date'])) ?></span>
                    </div>
                    <div class="rt-info-row">
                        <span class="rt-info-label">Type</span>
                        <span class="rt-info-val"><?= ucfirst($investment['investment_type']) ?></span>
                    </div>
                    <?php if (($investment['manual_equity_percentage'] ?? 0) > 0): ?>
                    <div class="rt-info-row">
                        <span class="rt-info-label">Equity/Capital %</span>
                        <span class="rt-info-val" style="color:var(--accent)"><?= number_format($investment['manual_equity_percentage'], 2) ?>%</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($investment['source']): ?>
                    <div class="rt-info-row">
                        <span class="rt-info-label">Source</span>
                        <span class="rt-info-val"><?= htmlspecialchars($investment['source']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="rt-info-row rt-info-final">
                        <span class="rt-info-label">Balance Due</span>
                        <span class="rt-info-val <?= $investment['balance'] > 0 ? 'red' : 'green' ?>">
                            <?= formatCurrency($investment['balance']) ?>
                        </span>
                    </div>

                    <!-- Recovery Bar -->
                    <div class="rt-progress">
                        <div class="rt-progress-label"><?= number_format($recovery_pct, 1) ?>% Recovered</div>
                        <div class="rt-progress-track">
                            <div class="rt-progress-fill" style="width:<?= min(100, $recovery_pct) ?>%; background:<?= $recovery_color ?>"></div>
                        </div>
                        <div class="rt-progress-meta">
                            <span><?= count($returns) ?> payment<?= count($returns) !== 1 ? 's' : '' ?></span>
                            <span><?= formatCurrency($total_returns) ?> / <?= formatCurrency($investment['amount']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="rt-modal-backdrop" id="deleteReturnModal">
    <div class="rt-modal">
        <div class="rt-modal-icon"><i class="fas fa-trash-alt"></i></div>
        <h4>Delete Return?</h4>
        <p>Are you sure you want to remove this <strong id="delReturnAmount"></strong> return? The balance will be recalculated.</p>
        <form method="POST" id="deleteReturnForm">
            <input type="hidden" name="action" value="delete_return">
            <input type="hidden" name="return_id" id="delReturnId">
            <div class="rt-modal-btns">
                <button type="button" class="rt-modal-btn cancel" onclick="closeDeleteReturn()">Cancel</button>
                <button type="submit" class="rt-modal-btn danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// Payment Mode Toggle
document.querySelectorAll('.pm-card').forEach(function(card) {
    card.addEventListener('click', function() {
        document.querySelectorAll('.pm-card').forEach(function(c) { c.classList.remove('active'); });
        card.classList.add('active');

        // Show/hide account dropdown based on mode
        var mode = card.dataset.mode;
        var accountField = document.getElementById('accountField');
        if (mode === 'cash') {
            accountField.style.display = 'none';
            document.getElementById('accountSelect').value = '';
        } else {
            accountField.style.display = 'flex';
        }
    });
});

// Delete Modal
function openDeleteReturn(id, amount) {
    document.getElementById('delReturnId').value = id;
    document.getElementById('delReturnAmount').textContent = amount;
    document.getElementById('deleteReturnModal').classList.add('open');
}
function closeDeleteReturn() {
    document.getElementById('deleteReturnModal').classList.remove('open');
}
document.getElementById('deleteReturnModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteReturn();
});
</script>

<script>
// Form submit validation — require bank account for non-cash modes
(function() {
    var form = document.querySelector('.rt-card.d1 form');
    if (!form) return;

    var accountSelect = document.getElementById('accountSelect');
    var cardBody = form.closest('.rt-card-body');

    function getSelectedMode() {
        var checked = form.querySelector('input[name="payment_mode"]:checked');
        return checked ? checked.value : 'bank_transfer';
    }

    function showToast(msg) {
        var old = cardBody.querySelector('.rt-toast');
        if (old) old.remove();

        var toast = document.createElement('div');
        toast.className = 'rt-toast';
        toast.innerHTML = '<i class="fas fa-circle-exclamation"></i><span>' + msg + '</span><button type="button" onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:#991b1b;opacity:0.5;cursor:pointer;font-size:0.85rem;padding:0.2rem;line-height:1">×</button>';
        toast.style.cssText = 'display:flex;align-items:center;gap:0.6rem;padding:0.75rem 1rem;border-radius:10px;font-size:0.8rem;font-weight:600;line-height:1.35;background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b;margin-bottom:1rem;animation:slideUp 0.35s ease';

        // Insert before the form's first section tag
        var firstSection = form.querySelector('.rt-section-tag');
        if (firstSection) form.insertBefore(toast, firstSection);
        else form.prepend(toast);

        setTimeout(function() { if (toast.parentElement) toast.remove(); }, 4000);
    }

    form.addEventListener('submit', function(e) {
        var mode = getSelectedMode();
        if (mode !== 'cash' && !accountSelect.value) {
            e.preventDefault();
            accountSelect.style.borderColor = '#ef4444';
            accountSelect.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
            accountSelect.focus();
            var label = mode.replace('_', ' ');
            label = label.charAt(0).toUpperCase() + label.slice(1);
            showToast('Please select a bank account for <strong>' + label.toUpperCase() + '</strong> payments.');
            return false;
        }
    });

    accountSelect.addEventListener('change', function() {
        this.style.borderColor = '';
        this.style.boxShadow = '';
        var toast = cardBody.querySelector('.rt-toast');
        if (toast) toast.remove();
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>