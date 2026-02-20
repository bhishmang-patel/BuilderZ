<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$page_title   = 'Project-wise P&L';
$current_page = 'project_pl';

require_once __DIR__ . '/../../../includes/ReportService.php';

$reportService = new ReportService();
$projects      = $reportService->getProjectPL();

$totals = ['bookings' => 0, 'sales' => 0, 'turnover' => 0, 'expense' => 0, 'net' => 0];

if (!empty($projects)) {
    foreach ($projects as $key => $project) {
        $projects[$key]['calc_turnover'] = (float) $project['total_received'];
        $projects[$key]['calc_expense']  = (float) $project['vendor_payments']

            + (float) $project['contractor_payments']
            + (float) $project['other_expenses']
            + (float) $project['total_refunds'];
        $projects[$key]['calc_profit'] = $projects[$key]['calc_turnover'] - $projects[$key]['calc_expense'];
        $projects[$key]['calc_margin'] = $projects[$key]['calc_turnover'] > 0
            ? ($projects[$key]['calc_profit'] / $projects[$key]['calc_turnover']) * 100
            : 0;
        $totals['bookings'] += $project['total_bookings'];
        $totals['sales']    += $project['total_sales'];
        $totals['turnover'] += $projects[$key]['calc_turnover'];
        $totals['expense']  += $projects[$key]['calc_expense'];
        $totals['net']      += $projects[$key]['calc_profit'];
    }
}

$net_margin = $totals['turnover'] > 0 ? ($totals['net'] / $totals['turnover']) * 100 : 0;

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }

:root {
    --ink:        #1a1714;
    --ink-soft:   #6b6560;
    --ink-mute:   #9e9690;
    --cream:      #f5f3ef;
    --surface:    #ffffff;
    --border:     #e8e3db;
    --border-lt:  #f0ece5;
    --accent:     #2a58b5;
    --accent-lt:  #eff4ff;
    --accent-md:  #c7d9f9;
    --accent-bg:  #f0f5ff;
    --accent-dk:  #1e429f;
    --green:      #059669;
    --green-lt:   #d1fae5;
    --orange:     #d97706;
    --orange-lt:  #fef3c7;
    --red:        #dc2626;
    --red-lt:     #fee2e2;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

/* ── Animations ───────────────────── */
@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

/* ── Page header ──────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    margin-bottom: 2.25rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    opacity: 0;
    animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size: 0.67rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.28rem; }
.page-header h1 { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: var(--ink); margin: 0; line-height: 1.1; }
.page-header h1 em { font-style: italic; color: var(--accent); }

.hdr-actions { display: flex; gap: 0.55rem; align-items: center; flex-wrap: wrap; }
.btn-export {
    display: inline-flex; align-items: center; gap: 0.38rem;
    padding: 0.5rem 0.9rem; border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; text-decoration: none; border: 1.5px solid var(--border);
    background: white; color: var(--ink-soft); transition: all 0.18s;
}
.btn-export:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Stat cards ───────────────────── */
.stat-row {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 900px)  { .stat-row { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 500px)  { .stat-row { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.25rem 1.35rem;
    display: flex; align-items: center; gap: 1rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    transition: transform 0.2s, box-shadow 0.2s;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) both;
    position: relative; overflow: hidden;
}
.stat-card::after {
    content: ''; position: absolute; right: 0; top: 0; bottom: 0;
    width: 3px; border-radius: 0 12px 12px 0;
}
.stat-card.s1 { animation-delay: 0.07s; } .stat-card.s1::after { background: var(--accent); }
.stat-card.s2 { animation-delay: 0.12s; } .stat-card.s2::after { background: var(--red); }
.stat-card.s3 { animation-delay: 0.17s; }
.stat-card.s4 { animation-delay: 0.22s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.08); }

.sc-ic {
    width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
}
.sc-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.sc-ic.red    { background: var(--red-lt);    color: var(--red); }
.sc-ic.green  { background: var(--green-lt);  color: var(--green); }
.sc-ic.orange { background: var(--orange-lt); color: var(--orange); }

.sc-lbl { font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.28rem; }
.sc-val { font-family: 'Fraunces', serif; font-size: 1.45rem; font-weight: 700; color: var(--ink); line-height: 1; text-align: center; }
.sc-val.green  { color: var(--green); }
.sc-val.red    { color: var(--red); }
.sc-val.accent { color: var(--accent); }
.sc-sub { font-size: 0.68rem; color: var(--ink-mute); margin-top: 3px; text-align: center; }

/* ── Panel ────────────────────────── */
.panel {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.28s both;
}

.panel-toolbar {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.pt-icon {
    width: 30px; height: 30px; background: var(--accent-lt); color: var(--accent);
    border-radius: 7px; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; flex-shrink: 0;
}
.pt-title { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); flex: 1; }
.pt-sub   { font-size: 0.72rem; color: var(--ink-mute); }

/* ── P&L Table ────────────────────── */
.pl-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.pl-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.pl-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.pl-table thead th.al-c { text-align: center; }
.pl-table thead th.al-r { text-align: right; }

/* main data row */
.pl-row td { padding: 0.92rem 1rem; border-bottom: 1px solid var(--border-lt); vertical-align: middle; transition: background 0.12s; }
.pl-row:hover td { background: #f4f7fd; }
.pl-row.open td  { background: var(--accent-bg); border-bottom: none; }
.pl-row { cursor: pointer; }
.pl-row.row-in { animation: rowIn 0.26s cubic-bezier(0.22,1,0.36,1) forwards; }

/* toggle chevron */
.toggle-btn {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--cream); border: 1.5px solid var(--border); color: var(--ink-mute);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.6rem; transition: all 0.22s; flex-shrink: 0;
}
.pl-row.open .toggle-btn { background: var(--accent); border-color: var(--accent); color: white; transform: rotate(180deg); }

/* project name cell */
.proj-name { font-weight: 700; color: var(--ink); font-size: 0.9rem; }
.proj-loc  { font-size: 0.72rem; color: var(--ink-mute); margin-top: 2px; display: flex; align-items: center; gap: 0.28rem; }

/* bookings */
.bookings-val { font-family: 'Fraunces', serif; font-weight: 700; color: var(--ink); font-size: 1rem; text-align: center; }

/* financial cells */
.money { font-family: 'Fraunces', serif; font-weight: 700; color: var(--ink); font-size: 0.92rem; }
.money.income { color: var(--accent); }
.money.expense { color: var(--red); }
.money.profit-pos { color: var(--green); }
.money.profit-neg { color: var(--red); }

/* margin pill */
.margin-pill {
    display: inline-block; padding: 0.22rem 0.72rem; border-radius: 20px;
    font-size: 0.7rem; font-weight: 800; letter-spacing: 0.04em;
}
.margin-pill.hi   { background: var(--green-lt);  color: var(--green); border: 1px solid #6ee7b7; }
.margin-pill.mid  { background: var(--orange-lt); color: var(--orange); border: 1px solid #fcd34d; }
.margin-pill.lo   { background: var(--red-lt);    color: var(--red); border: 1px solid #fca5a5; }
.margin-pill.neg  { background: #fef2f2; color: var(--red); border: 1px solid #fca5a5; }

/* ── Expanded detail row ──────────── */
.detail-row { display: none; }
.detail-row.open { display: table-row; }

.detail-inner {
    padding: 0 1.5rem 1.5rem 3.5rem;
    background: var(--accent-bg);
    border-bottom: 1.5px solid var(--border);
    animation: slideDown 0.25s cubic-bezier(0.22,1,0.36,1);
}

.detail-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
    background: white; border: 1.5px solid var(--accent-md);
    border-radius: 10px; padding: 1.35rem;
}
@media (max-width: 680px) { .detail-grid { grid-template-columns: 1fr; } }

.detail-col {}
.detail-col-head {
    font-size: 0.62rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase;
    margin-bottom: 0.85rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.4rem;
}
.detail-col-head.income  { color: var(--green); }
.detail-col-head.expense { color: var(--red); }

.detail-line {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 0.42rem 0; border-bottom: 1px solid var(--border-lt); font-size: 0.82rem;
}
.detail-line:last-child { border-bottom: none; }
.detail-line .dl-key { color: var(--ink-soft); font-weight: 500; }
.detail-line .dl-val { font-family: 'Fraunces', serif; font-weight: 700; font-size: 0.88rem; color: var(--ink); }
.detail-line .dl-val.green { color: var(--green); }
.detail-line .dl-val.red   { color: var(--red); }
.detail-line .dl-val.muted { color: var(--ink-mute); font-weight: 600; font-size: 0.8rem; }
.detail-line.pending .dl-val { color: var(--orange); }
.detail-line.subtotal { border-top: 1px solid var(--border); margin-top: 0.35rem; padding-top: 0.55rem; }
.detail-line.subtotal .dl-key { font-weight: 700; color: var(--ink); }

/* ── Tfoot ────────────────────────── */
.pl-table tfoot tr td {
    padding: 0.85rem 1rem; background: #fafbff;
    border-top: 1.5px solid var(--border); font-size: 0.855rem;
}
.tfoot-label { font-size: 0.65rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-mute); text-align: right; }
.tfoot-total { font-family: 'Fraunces', serif; font-weight: 700; font-size: 0.92rem; }

/* empty state */
.empty-state { text-align: center; padding: 4rem 1.5rem; }
.empty-state .es-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: var(--accent); opacity: 0.18; }
.empty-state h4 { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink-soft); margin: 0 0 0.35rem; }
.empty-state p  { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Reports &rsaquo; Financial</div>
            <h1>Project <em>P&amp;L</em></h1>
        </div>
        <div class="hdr-actions">
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=project_pl&format=excel" class="btn-export">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="<?= BASE_URL ?>modules/reports/download.php?action=download_report&report=project_pl&format=csv" class="btn-export">
                <i class="fas fa-file-csv"></i> CSV
            </a>
            <button onclick="window.print()" class="btn-export">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ── Stat Cards ──────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic blue"><i class="fas fa-coins"></i></div>
            <div>
                <div class="sc-lbl">Total Collected</div>
                <div class="sc-val accent"><?= formatCurrencyShort($totals['turnover'], false) ?></div>
                <div class="sc-sub"><?= formatCurrency($totals['turnover']) ?></div>
            </div>
        </div>
        <div class="stat-card s2">
            <div class="sc-ic red"><i class="fas fa-wallet"></i></div>
            <div>
                <div class="sc-lbl">Total Payout</div>
                <div class="sc-val red"><?= formatCurrencyShort($totals['expense'], false) ?></div>
                <div class="sc-sub"><?= formatCurrency($totals['expense']) ?></div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic <?= $totals['net'] >= 0 ? 'green' : 'red' ?>">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <div class="sc-lbl">Net Cash Flow</div>
                <div class="sc-val <?= $totals['net'] >= 0 ? 'green' : 'red' ?>"><?= formatCurrencyShort($totals['net'], false) ?></div>
                <div class="sc-sub"><?= formatCurrency($totals['net']) ?></div>
            </div>
        </div>
        <div class="stat-card s4" style="animation-delay:0.22s;">
            <div class="sc-ic orange"><i class="fas fa-percentage"></i></div>
            <div>
                <div class="sc-lbl">Net Margin</div>
                <div class="sc-val <?= $net_margin >= 0 ? 'green' : 'red' ?>"><?= number_format($net_margin, 1) ?>%</div>
                <div class="sc-sub"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?></div>
            </div>
        </div>
    </div>

    <!-- ── P&L Panel ───────────────── -->
    <div class="panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="pt-title">Project Financials</div>
            <span class="pt-sub">Click any row to expand detailed breakdown</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th style="width:46px;">#</th>
                        <th>Project</th>
                        <th class="al-c">Bookings</th>
                        <th class="al-r">Total Received</th>
                        <th class="al-r">Total Payout</th>
                        <th class="al-r">Net Cash</th>
                        <th class="al-c">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <span class="es-icon"><i class="fas fa-chart-bar"></i></span>
                                <h4>No project data found</h4>
                                <p>Financial data will appear once projects have transactions.</p>
                            </div>
                        </td></tr>
                    <?php else:
                        foreach ($projects as $idx => $row):
                            $profit    = $row['calc_profit'];
                            $margin    = $row['calc_margin'];
                            $mclass    = $margin >= 20 ? 'hi' : ($margin >= 10 ? 'mid' : ($margin >= 0 ? 'lo' : 'neg'));
                            $uid       = 'proj_' . $row['id'];
                    ?>
                        <!-- Main row -->
                        <tr class="pl-row row-in" id="row_<?= $uid ?>" 
                            style="animation-delay:<?= $idx * 35 ?>ms;"
                            onclick="window.location.href='view.php?id=<?= $row['id'] ?>'">
                            <td style="text-align:center;">
                                <div style="color:var(--ink-mute); font-size:0.8rem;"><?= $idx + 1 ?></div>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $row['id'] ?>" style="text-decoration:none; color:inherit;">
                                    <div class="proj-name"><?= renderProjectBadge($row['project_name'], $row['id']) ?></div>
                                    <?php if (!empty($row['location'])): ?>
                                    <div class="proj-loc">
                                        <i class="fas fa-map-marker-alt" style="font-size:0.6rem;"></i>
                                        <?= htmlspecialchars($row['location']) ?>
                                    </div>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td><div class="bookings-val"><?= $row['total_bookings'] ?></div></td>
                            <td style="text-align:right;"><span class="money income"><?= formatCurrency($row['calc_turnover']) ?></span></td>
                            <td style="text-align:right;"><span class="money expense"><?= formatCurrency($row['calc_expense']) ?></span></td>
                            <td style="text-align:right;">
                                <span class="money <?= $profit >= 0 ? 'profit-pos' : 'profit-neg' ?>"><?= formatCurrency($profit) ?></span>
                            </td>
                            <td style="text-align:center;">
                                <span class="margin-pill <?= $mclass ?>"><?= number_format($margin, 1) ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

                <!-- Totals footer -->
                <?php if (!empty($projects)): ?>
                <tfoot>
                    <tr>
                        <td></td>
                        <td class="tfoot-label">All Projects</td>
                        <td style="text-align:center;">
                            <span style="font-family:'Fraunces',serif;font-weight:700;font-size:0.95rem;color:var(--ink);"><?= $totals['bookings'] ?></span>
                        </td>
                        <td style="text-align:right;">
                            <span class="tfoot-total" style="color:var(--accent);"><?= formatCurrency($totals['turnover']) ?></span>
                        </td>
                        <td style="text-align:right;">
                            <span class="tfoot-total" style="color:var(--red);"><?= formatCurrency($totals['expense']) ?></span>
                        </td>
                        <td style="text-align:right;">
                            <span class="tfoot-total" style="color:<?= $totals['net'] >= 0 ? 'var(--green)' : 'var(--red)' ?>; font-size:1rem;">
                                <?= formatCurrency($totals['net']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php $mc = $net_margin >= 20 ? 'hi' : ($net_margin >= 10 ? 'mid' : ($net_margin >= 0 ? 'lo' : 'neg')); ?>
                            <span class="margin-pill <?= $mc ?>"><?= number_format($net_margin, 1) ?>%</span>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
// Toggle logic removed
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>