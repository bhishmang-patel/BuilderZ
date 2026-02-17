<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db          = Database::getInstance();
$material_id = intval($_GET['id'] ?? 0);

if (!$material_id) redirect('modules/inventory/index.php');

$stmt     = $db->select('materials', 'id = ?', [$material_id]);
$material = $stmt->fetch();
if (!$material) die('Material not found');

$page_title   = 'Ledger: ' . $material['material_name'];
$current_page = 'stock';

$sql_in = "SELECT c.challan_date as date, 
                  c.challan_no as reference, 
                  p.name as party_name, 
                  'purchase' as type,
                  ci.quantity,
                  ci.rate
           FROM challan_items ci
           JOIN challans c ON ci.challan_id = c.id
           JOIN parties p ON c.party_id = p.id
           WHERE ci.material_id = ? AND c.status = 'approved'";

$sql_out = "SELECT mu.usage_date as date, 
                   CONCAT('Usage - ', pr.project_name) as reference, 
                   u.full_name as party_name, 
                   'usage' as type,
                   mu.quantity,
                   0 as rate
            FROM material_usage mu
            JOIN projects pr ON mu.project_id = pr.id
            LEFT JOIN users u ON mu.created_by = u.id
            WHERE mu.material_id = ?";

$sql          = "($sql_in) UNION ALL ($sql_out) ORDER BY date DESC, type ASC";
$stmt         = $db->query($sql, [$material_id, $material_id]);
$transactions = $stmt->fetchAll();

$total_in  = 0;
$total_out = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'purchase') $total_in  += $t['quantity'];
    else                           $total_out += $t['quantity'];
}
$current_stock = $total_in - $total_out;
$is_low        = $current_stock < 10;

include __DIR__ . '/../../includes/header.php';
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
.pw  { max-width: 1100px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

/* ── Animations ───────────────────── */
@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }

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
.back-link {
    display: inline-flex; align-items: center; gap: 0.42rem;
    padding: 0.52rem 1rem; font-size: 0.82rem; font-weight: 500;
    color: var(--ink-soft); border: 1.5px solid var(--border);
    border-radius: 7px; background: white; text-decoration: none; transition: all 0.18s;
}
.back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Stat cards ───────────────────── */
.stat-row {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 860px) { .stat-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .stat-row { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.15rem 1.3rem;
    display: flex; align-items: center; gap: 0.9rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    transition: transform 0.2s, box-shadow 0.2s;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) both;
}
.stat-card.s1 { animation-delay: 0.08s; }
.stat-card.s2 { animation-delay: 0.13s; }
.stat-card.s3 { animation-delay: 0.18s; }
.stat-card.s4 { animation-delay: 0.23s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.08); }

.sc-ic {
    width: 40px; height: 40px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.9rem;
}
.sc-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.sc-ic.green  { background: var(--green-lt);  color: var(--green); }
.sc-ic.orange { background: var(--orange-lt); color: var(--orange); }
.sc-ic.red    { background: var(--red-lt);    color: var(--red); }
.sc-ic.purple { background: #ede9fe; color: #7c3aed; }

.sc-lbl { font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.26rem; }
.sc-val { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; color: var(--ink); line-height: 1; }
.sc-val.green  { color: var(--green); }
.sc-val.red    { color: var(--red); }
.sc-val.orange { color: var(--orange); }
.sc-unit { font-size: 0.72rem; font-weight: 500; color: var(--ink-mute); margin-left: 3px; }

/* ── Main panel ───────────────────── */
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
.pt-title {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); flex: 1;
    display: flex; align-items: center; gap: 0.5rem;
}
.count-tag {
    font-size: 0.62rem; font-weight: 800; padding: 0.15rem 0.55rem; border-radius: 20px;
    background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border);
    font-family: 'DM Sans', sans-serif;
}

/* ── Ledger table ─────────────────── */
.ledger-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.ledger-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.ledger-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.ledger-table thead th.al-c { text-align: center; }
.ledger-table thead th.al-r { text-align: right; }
.ledger-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.ledger-table tbody tr:last-child { border-bottom: none; }
.ledger-table tbody tr:hover { background: #f4f7fd; }
.ledger-table tbody tr.row-in { animation: rowIn 0.26s cubic-bezier(0.22,1,0.36,1) forwards; }
.ledger-table td { padding: 0.82rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.ledger-table td.al-c { text-align: center; }
.ledger-table td.al-r { text-align: right; }

/* date cell */
.date-main { font-size: 0.82rem; font-weight: 700; color: var(--ink); }
.date-sub  { font-size: 0.68rem; color: var(--ink-mute); margin-top: 1px; }

/* type badge */
.type-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.22rem 0.72rem; border-radius: 20px;
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
    white-space: nowrap;
}
.type-pill.purchase { background: var(--green-lt);  color: var(--green); border: 1px solid #6ee7b7; }
.type-pill.usage    { background: var(--orange-lt); color: var(--orange); border: 1px solid #fcd34d; }

/* party / reference */
.party-name { font-weight: 700; color: var(--ink); font-size: 0.875rem; }
.ref-text   { font-family: 'Courier New', monospace; font-size: 0.78rem; color: var(--ink-soft); }

/* in / out qty */
.qty-in  { font-family: 'Fraunces', serif; font-weight: 700; color: var(--green); font-size: 0.9rem; }
.qty-out { font-family: 'Fraunces', serif; font-weight: 700; color: var(--red);   font-size: 0.9rem; }
.qty-nil { color: var(--ink-mute); font-size: 0.85rem; }

/* rate */
.rate-val { font-size: 0.82rem; font-weight: 600; color: var(--ink-soft); }

/* empty state */
.empty-state { text-align: center; padding: 4rem 1.5rem; }
.empty-state .es-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: var(--accent); opacity: 0.18; }
.empty-state h4 { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink-soft); margin: 0 0 0.35rem; }
.empty-state p  { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

/* net stock footer inside panel */
.ledger-foot {
    display: flex; align-items: center; justify-content: flex-end; gap: 2rem;
    padding: 0.85rem 1.5rem; background: #fafbff; border-top: 1.5px solid var(--border-lt);
    flex-wrap: wrap;
}
.lf-item { text-align: right; }
.lf-lbl  { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.lf-val  { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 700; color: var(--ink); margin-top: 1px; }
.lf-val.green  { color: var(--green); }
.lf-val.red    { color: var(--red); }
.lf-div  { width: 1px; height: 32px; background: var(--border); }
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Inventory &rsaquo; Ledger</div>
            <h1><em><?= htmlspecialchars($material['material_name']) ?></em></h1>
        </div>
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock
        </a>
    </div>

    <!-- ── Stat Cards ──────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic blue"><i class="fas fa-box"></i></div>
            <div>
                <div class="sc-lbl">Current Stock</div>
                <div class="sc-val <?= $is_low ? 'red' : 'green' ?>">
                    <?= number_format($current_stock, 2) ?>
                    <span class="sc-unit"><?= strtolower(htmlspecialchars($material['unit'])) ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic green"><i class="fas fa-arrow-down"></i></div>
            <div>
                <div class="sc-lbl">Total Purchased</div>
                <div class="sc-val green">
                    <?= number_format($total_in, 2) ?>
                    <span class="sc-unit"><?= strtolower(htmlspecialchars($material['unit'])) ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card s4">
            <div class="sc-ic red"><i class="fas fa-arrow-up"></i></div>
            <div>
                <div class="sc-lbl">Total Used</div>
                <div class="sc-val red">
                    <?= number_format($total_out, 2) ?>
                    <span class="sc-unit"><?= strtolower(htmlspecialchars($material['unit'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Ledger Panel ────────────── -->
    <div class="panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-history"></i></div>
            <div class="pt-title">
                Transaction History
                <span class="count-tag"><?= count($transactions) ?> records</span>
            </div>
            <span style="font-size:0.75rem;color:var(--ink-mute);margin-left:auto;">
                All purchases &amp; usage entries · sorted by date
            </span>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="al-c">Type</th>
                        <th>Vendor</th>
                        <th>Reference</th>
                        <th class="al-r">In (+)</th>
                        <th class="al-r">Out (−)</th>
                        <th class="al-r">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <span class="es-icon"><i class="fas fa-folder-open"></i></span>
                                    <h4>No transactions yet</h4>
                                    <p>Purchases and usage entries will appear here once recorded.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        foreach ($transactions as $i => $txn):
                            $is_purchase = $txn['type'] === 'purchase';
                            $d = date_create($txn['date']);
                    ?>
                        <tr class="row-in" style="animation-delay:<?= $i * 28 ?>ms;">

                            <!-- Date -->
                            <td>
                                <div class="date-main"><?= date_format($d, 'd M Y') ?></div>
                                <div class="date-sub"><?= date_format($d, 'l') ?></div>
                            </td>

                            <!-- Type -->
                            <td class="al-c">
                                <?php if ($is_purchase): ?>
                                    <span class="type-pill purchase">
                                        <i class="fas fa-cart-plus" style="font-size:0.6rem;"></i> Purchase
                                    </span>
                                <?php else: ?>
                                    <span class="type-pill usage">
                                        <i class="fas fa-hammer" style="font-size:0.6rem;"></i> Usage
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Party -->
                            <td>
                                <span class="party-name"><?= htmlspecialchars($txn['party_name'] ?? 'N/A') ?></span>
                            </td>

                            <!-- Reference -->
                            <td>
                                <span class="ref-text"><?= htmlspecialchars($txn['reference']) ?></span>
                            </td>

                            <!-- In -->
                            <td class="al-r">
                                <?php if ($is_purchase): ?>
                                    <span class="qty-in">+ <?= number_format($txn['quantity'], 2) ?></span>
                                <?php else: ?>
                                    <span class="qty-nil">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Out -->
                            <td class="al-r">
                                <?php if (!$is_purchase): ?>
                                    <span class="qty-out">− <?= number_format($txn['quantity'], 2) ?></span>
                                <?php else: ?>
                                    <span class="qty-nil">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Rate -->
                            <td class="al-r">
                                <span class="rate-val">
                                    <?= $txn['rate'] > 0 ? formatCurrency($txn['rate']) : '<span class="qty-nil">—</span>' ?>
                                </span>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer totals -->
        <?php if (!empty($transactions)): ?>
        <div class="ledger-foot">
            <div class="lf-item">
                <div class="lf-lbl">Total In</div>
                <div class="lf-val green">+ <?= number_format($total_in, 2) ?> <span style="font-size:0.72rem;font-weight:500;color:var(--ink-mute)"><?= strtolower(htmlspecialchars($material['unit'])) ?></span></div>
            </div>
            <div class="lf-div"></div>
            <div class="lf-item">
                <div class="lf-lbl">Total Out</div>
                <div class="lf-val red">− <?= number_format($total_out, 2) ?> <span style="font-size:0.72rem;font-weight:500;color:var(--ink-mute)"><?= strtolower(htmlspecialchars($material['unit'])) ?></span></div>
            </div>
            <div class="lf-div"></div>
            <div class="lf-item">
                <div class="lf-lbl">Net Stock</div>
                <div class="lf-val <?= $is_low ? 'red' : 'green' ?>"><?= number_format($current_stock, 2) ?> <span style="font-size:0.72rem;font-weight:500;color:var(--ink-mute)"><?= strtolower(htmlspecialchars($material['unit'])) ?></span></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>