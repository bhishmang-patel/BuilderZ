<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/InvestmentService.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

$investmentId = $_GET['id'] ?? null;
if (!$investmentId) {
    header("Location: index.php");
    exit;
}

$investmentService = new InvestmentService();
$investment = $investmentService->getInvestmentById($investmentId);

if (!$investment) die("Investment not found.");

$success = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount  = $_POST['amount'] ?? 0;
    $date    = $_POST['return_date'] ?? date('Y-m-d');
    $remarks = $_POST['remarks'] ?? '';

    if ($amount > 0 && !empty($date)) {
        if ($amount > $investment['balance']) {
            $error = "Return amount cannot exceed outstanding balance (" . formatCurrency($investment['balance']) . ")";
        } else {
            if ($investmentService->addReturn($investmentId, [
                'amount'      => $amount,
                'return_date' => $date,
                'remarks'     => $remarks,
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

$returns = $investmentService->getInvestmentReturns($investmentId);

$page_title   = 'Investment Returns';
$current_page = 'investments';

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }

:root {
    --ink:        #1a1714; --ink-soft:  #6b6560; --ink-mute:  #9e9690;
    --cream:      #f5f3ef; --surface:   #ffffff; --border:    #e8e3db; --border-lt: #f0ece5;
    --accent:     #2a58b5; --accent-lt: #eff4ff; --accent-md: #c7d9f9; --accent-bg: #f0f5ff;
    --green:      #059669; --green-lt:  #d1fae5;
    --orange:     #d97706; --orange-lt: #fef3c7;
    --red:        #dc2626; --red-lt:    #fee2e2;
    --purple:     #7c3aed; --purple-lt: #ede9fe;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1260px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }

/* ── Header ───────────────────────── */
.page-header {
    display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap;
    margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1.5px solid var(--border);
    opacity:0; animation:hdrIn .45s cubic-bezier(.22,1,.36,1) .05s forwards;
}
.eyebrow { font-size:.67rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); margin-bottom:.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }
.back-link { display:inline-flex; align-items:center; gap:.42rem; padding:.52rem 1rem; font-size:.82rem; font-weight:500; color:var(--ink-soft); border:1.5px solid var(--border); border-radius:7px; background:white; text-decoration:none; transition:all .18s; }
.back-link:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); text-decoration:none; }

/* ── Layout ───────────────────────── */
.ret-layout { display:grid; grid-template-columns:400px 1fr; gap:1.25rem; align-items:start; }
@media(max-width:980px) { .ret-layout { grid-template-columns:1fr; } }

/* ── Card base ────────────────────── */
.card {
    background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden;
    box-shadow:0 1px 4px rgba(26,23,20,.04); margin-bottom:1.1rem;
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both;
}
.card.c1{animation-delay:.08s} .card.c2{animation-delay:.14s} .card.c3{animation-delay:.10s}

.card-head { display:flex; align-items:center; gap:.7rem; padding:1.05rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff; }
.ch-ic { width:30px; height:30px; border-radius:7px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.75rem; }
.ch-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.ch-ic.green  { background:var(--green-lt);  color:var(--green); }
.ch-ic.purple { background:var(--purple-lt); color:var(--purple); }
.card-head h2 { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); margin:0; flex:1; }
.count-tag { font-size:.62rem; font-weight:800; padding:.15rem .55rem; border-radius:20px; background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); font-family:'DM Sans',sans-serif; }

.card-body { padding:1.4rem 1.5rem; }

/* ── Info rows ────────────────────── */
.ir { display:flex; justify-content:space-between; align-items:center; padding:.52rem 0; border-bottom:1px solid var(--border-lt); font-size:.82rem; }
.ir:last-child { border-bottom:none; }
.ir.highlight { border-top:1.5px solid var(--border); margin-top:.5rem; padding-top:.75rem; }
.ir-lbl { font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-mute); }
.ir-val { font-family:'Fraunces',serif; font-weight:700; color:var(--ink); text-align:right; }
.ir-val.green { color:var(--green); }
.ir-val.red   { color:var(--red); }
.ir.highlight .ir-lbl { font-weight:700; color:var(--ink); }
.ir.highlight .ir-val { font-size:1.05rem; }

/* ── Alerts ───────────────────────── */
.alert { display:flex; align-items:center; gap:.65rem; padding:.75rem 1rem; border-radius:9px; font-size:.82rem; font-weight:600; margin-bottom:1rem; }
.alert.success { background:var(--green-lt); color:var(--green); border:1.5px solid #6ee7b7; }
.alert.error   { background:var(--red-lt);   color:var(--red);   border:1.5px solid #fca5a5; }

/* ── Fields ───────────────────────── */
.mf { display:flex; flex-direction:column; gap:.28rem; margin-bottom:.9rem; }
.mf:last-child { margin-bottom:0; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf label .req { color:var(--red); margin-left:2px; }
.mf input,.mf textarea { width:100%; padding:.65rem .85rem; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; color:var(--ink); background:#fdfcfa; outline:none; transition:border-color .18s,box-shadow .18s; }
.mf input { height:40px; }
.mf textarea { min-height:70px; resize:vertical; }
.mf input:focus,.mf textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white; }

.btn-submit {
    width:100%; height:42px; background:var(--ink); color:white; border:none;
    border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:700;
    cursor:pointer; transition:all .18s; margin-top:.5rem;
    display:flex; align-items:center; justify-content:center; gap:.45rem;
}
.btn-submit:hover { background:var(--accent); transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); }

/* ── Table ────────────────────────── */
.rt-table { width:100%; border-collapse:collapse; font-size:.855rem; }
.rt-table thead tr { background:#eef2fb; border-bottom:1.5px solid var(--border); }
.rt-table thead th { padding:.65rem 1rem; font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft); text-align:left; }
.rt-table thead th.al-r { text-align:right; }
.rt-table tbody tr { border-bottom:1px solid var(--border-lt); transition:background .12s; }
.rt-table tbody tr:last-child { border-bottom:none; }
.rt-table tbody tr:hover { background:#f4f7fd; }
.rt-table tbody tr.row-in { animation:rowIn .24s cubic-bezier(.22,1,.36,1) forwards; }
.rt-table td { padding:.75rem 1rem; vertical-align:middle; }
.rt-table td.al-r { text-align:right; }
.rt-table td.amount { font-family:'Fraunces',serif; font-weight:700; color:var(--green); }

/* ── Empty state ──────────────────── */
.empty-state { text-align:center; padding:4rem 1.5rem; }
.empty-state .es-icon { font-size:2.5rem; display:block; margin-bottom:.75rem; color:var(--accent); opacity:.18; }
.empty-state h4 { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink-soft); margin:0 0 .35rem; }
.empty-state p  { font-size:.82rem; color:var(--ink-mute); margin:0; }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Investments &rsaquo; Returns</div>
            <h1>Manage <em>Returns</em></h1>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> All Investments
        </a>
    </div>

    <div class="ret-layout">

        <!-- ── Left column ─────────── -->
        <div>

            <!-- Details card -->
            <div class="card c1">
                <div class="card-head">
                    <div class="ch-ic purple"><i class="fas fa-info-circle"></i></div>
                    <h2>Investment Details</h2>
                </div>
                <div class="card-body">
                    <div class="ir">
                        <span class="ir-lbl">Project</span>
                        <span class="ir-val"><?= htmlspecialchars($investment['project_name'] ?? 'General') ?></span>
                    </div>
                    <div class="ir">
                        <span class="ir-lbl">Investor</span>
                        <span class="ir-val"><?= htmlspecialchars($investment['investor_name']) ?></span>
                    </div>
                    <div class="ir">
                        <span class="ir-lbl">Date</span>
                        <span class="ir-val"><?= date('d M, Y', strtotime($investment['investment_date'])) ?></span>
                    </div>
                    <div class="ir">
                        <span class="ir-lbl">Total Invested</span>
                        <span class="ir-val"><?= formatCurrency($investment['amount']) ?></span>
                    </div>
                    <?php if ($investment['is_equity']): ?>
                    <div class="ir">
                        <span class="ir-lbl">Equity Share</span>
                        <span class="ir-val" style="color:var(--purple);"><?= number_format($investment['share_percentage'], 1) ?>%</span>
                    </div>
                    <?php else: ?>
                    <div class="ir">
                        <span class="ir-lbl">Leverage Ratio</span>
                        <span class="ir-val" style="color:var(--ink-mute);"><?= number_format($investment['capital_mix_percentage'], 1) ?>% of Cap</span>
                    </div>
                    <?php endif; ?>
                    <div class="ir">
                        <span class="ir-lbl">Total Returned</span>
                        <span class="ir-val green"><?= formatCurrency($investment['total_returned']) ?></span>
                    </div>
                    <div class="ir highlight">
                        <span class="ir-lbl">Outstanding Balance</span>
                        <span class="ir-val <?= $investment['balance'] > 0 ? 'red' : 'green' ?>">
                            <?= formatCurrency($investment['balance']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Record return form -->
            <div class="card c2">
                <div class="card-head">
                    <div class="ch-ic blue"><i class="fas fa-hand-holding-usd"></i></div>
                    <h2>Record New Return</h2>
                </div>
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($investment['balance'] <= 0): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i>
                            Fully Repaid! No outstanding balance remaining.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mf">
                                <label>Amount <span class="req">*</span></label>
                                <input type="number" name="amount" step="0.01"
                                       max="<?= $investment['balance'] ?>" required
                                       placeholder="Enter amount">
                            </div>
                            <div class="mf">
                                <label>Date <span class="req">*</span></label>
                                <input type="date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mf">
                                <label>Remarks</label>
                                <textarea name="remarks" placeholder="Optional remarks (e.g. Transaction ID)"></textarea>
                            </div>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Save Return
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- ── Right column: History ─── -->
        <div>
            <div class="card c3">
                <div class="card-head">
                    <div class="ch-ic green"><i class="fas fa-history"></i></div>
                    <h2>Return History</h2>
                    <span class="count-tag"><?= count($returns) ?></span>
                </div>

                <?php if (empty($returns)): ?>
                    <div class="empty-state">
                        <span class="es-icon"><i class="fas fa-undo"></i></span>
                        <h4>No returns recorded yet</h4>
                        <p>Returns will appear here once they're logged.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="rt-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                    <th class="al-r">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returns as $i => $ret): ?>
                                    <tr class="row-in" style="animation-delay:<?= $i*22 ?>ms;">
                                        <td><?= date('d M, Y', strtotime($ret['return_date'])) ?></td>
                                        <td><?= htmlspecialchars($ret['remarks'] ?: '—') ?></td>
                                        <td class="al-r amount">
                                            <?= formatCurrency($ret['amount']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>