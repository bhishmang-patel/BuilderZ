<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db           = Database::getInstance();
$page_title   = 'Stock Status';
$current_page = 'stock';

$sql = "SELECT m.*, 
        (SELECT COALESCE(SUM(ci.quantity), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status = 'approved') as total_in,
        (SELECT COALESCE(SUM(ci.quantity * ci.rate), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status = 'approved') as total_spend,
        (SELECT COALESCE(SUM(mu.quantity), 0) FROM material_usage mu WHERE mu.material_id = m.id) as total_out
    FROM materials m
    GROUP BY m.id
    HAVING total_in > 0 OR total_out > 0
    ORDER BY m.material_name";

$stock_data = $db->query($sql)->fetchAll();

$total_items     = count($stock_data);
$total_value     = 0;
$total_usage     = 0;
$low_stock_count = 0;

foreach ($stock_data as $item) {
    $real_stock  = $item['total_in'] - $item['total_out'];
    $avg_rate    = ($item['total_in'] > 0 && $item['total_spend'] > 0)
                    ? ($item['total_spend'] / $item['total_in'])
                    : $item['default_rate'];
    $cur_value   = ($item['total_spend'] > 0)
                    ? $item['total_spend']
                    : ($item['total_in'] * $item['default_rate']);
    $total_value += $cur_value;
    $total_usage += $item['total_out'];
    if ($real_stock < 10) $low_stock_count++;
}

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

/* ── Wrapper ──────────────────────── */
.pw { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

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

.btn-record {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.6rem 1.4rem; background: var(--ink); color: white;
    border: 1.5px solid var(--ink); border-radius: 8px; cursor: pointer;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
    text-decoration: none; transition: all 0.18s;
}
.btn-record:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.3); color: white; text-decoration: none; }
.btn-record:active { transform: translateY(0); }

/* ── Stat cards ───────────────────── */
.stat-row {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 900px) { .stat-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .stat-row { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.25rem 1.35rem;
    display: flex; align-items: center; gap: 1rem;
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
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; flex-shrink: 0;
}
.sc-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.sc-ic.green  { background: var(--green-lt);  color: var(--green); }
.sc-ic.orange { background: var(--orange-lt); color: var(--orange); }
.sc-ic.purple { background: #ede9fe; color: #7c3aed; }
.sc-ic.red    { background: var(--red-lt);    color: var(--red); }

.sc-lbl { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.28rem; }
.sc-val { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1; text-align: center; }
.sc-val.green  { color: var(--green); }
.sc-val.orange { color: var(--orange); }
.sc-val.red    { color: var(--red); }

/* ── Main panel ───────────────────── */
.panel {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.28s both;
}

/* ── Toolbar ──────────────────────── */
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

/* ── Stock table ──────────────────── */
.stock-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.stock-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.stock-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
}
.stock-table thead th.al-l { text-align: left; }
.stock-table thead th.al-c { text-align: center; }
.stock-table thead th.al-r { text-align: right; }

.stock-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.stock-table tbody tr:last-child { border-bottom: none; }
.stock-table tbody tr:hover { background: #f4f7fd; }
.stock-table tbody tr.row-in { animation: rowIn 0.26s cubic-bezier(0.22,1,0.36,1) forwards; }
.stock-table td { padding: 0.85rem 1rem; vertical-align: middle; }
.stock-table td.al-c { text-align: center; }
.stock-table td.al-r { text-align: right; }

/* material name cell */
.mat-cell { display: flex; align-items: center; gap: 0.75rem; }
.mat-av {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800; color: white;
}
.mat-name { font-weight: 700; color: var(--ink); font-size: 0.875rem; }
.mat-unit { font-size: 0.7rem; color: var(--ink-mute); margin-top: 1px; }

/* quantity badges */
.qty-in {
    display: inline-flex; align-items: center; gap: 0.28rem;
    padding: 0.22rem 0.65rem; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
    background: var(--green-lt); color: var(--green); border: 1px solid #6ee7b7;
}
.qty-out {
    display: inline-flex; align-items: center; gap: 0.28rem;
    padding: 0.22rem 0.65rem; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
    background: var(--red-lt); color: var(--red); border: 1px solid #fca5a5;
}

/* current stock */
.stock-val {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700; color: var(--ink);
    display: flex; align-items: center; justify-content: center; gap: 0.4rem; 
}
.stock-val.low  { color: var(--red); }
.stock-val.ok   { color: var(--green); }
.low-tag {
    font-size: 0.58rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    background: var(--red-lt); color: var(--red); border: 1px solid #fca5a5;
    padding: 0.12rem 0.45rem; border-radius: 20px;
}

/* avg price */
.avg-price { font-size: 0.82rem; font-weight: 600; color: var(--ink-soft); }
.est-icon { color: var(--orange); font-size: 0.68rem; margin-left: 3px; cursor: help; }

/* amount */
.amt-cell { font-family: 'Fraunces', serif; font-weight: 700; font-size: 0.92rem; color: var(--ink); }

/* progress bar */
.stock-bar-wrap { height: 4px; background: var(--border); border-radius: 2px; margin-top: 4px; overflow: hidden; width: 80px; }
.stock-bar      { height: 100%; border-radius: 2px; transition: width 0.3s; }

/* ledger button */
.ledger-btn {
    width: 28px; height: 28px; border-radius: 6px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-mute);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.65rem; cursor: pointer; text-decoration: none; transition: all 0.15s;
}
.ledger-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

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
            <div class="eyebrow">Inventory &rsaquo; Overview</div>
            <h1>Stock <em>Status</em></h1>
        </div>
        <a href="<?= BASE_URL ?>modules/inventory/usage.php" class="btn-record">
            <i class="fas fa-minus-circle"></i> Record Usage
        </a>
    </div>

    <!-- ── Stat Cards ──────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic purple"><i class="fas fa-cubes"></i></div>
            <div>
                <div class="sc-lbl">Total Items</div>
                <div class="sc-val"><?= $total_items ?></div>
            </div>
        </div>
        <div class="stat-card s2">
            <div class="sc-ic green"><i class="fas fa-wallet"></i></div>
            <div>
                <div class="sc-lbl">Total Spend</div>
                <div class="sc-val green"><?= formatCurrencyShort($total_value) ?></div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic red"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="sc-lbl">Low Stock</div>
                <div class="sc-val red"><?= $low_stock_count ?></div>
            </div>
        </div>
        <div class="stat-card s4">
            <div class="sc-ic blue"><i class="fas fa-dolly"></i></div>
            <div>
                <div class="sc-lbl">Total Usage</div>
                <div class="sc-val"><?= number_format($total_usage) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Main Panel ──────────────── -->
    <div class="panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-boxes"></i></div>
            <div class="pt-title">
                Stock Report
                <span class="count-tag"><?= $total_items ?> items</span>
            </div>
            <span style="font-size:0.75rem;color:var(--ink-mute);margin-left:auto;">Real-time inventory levels &amp; valuation</span>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table class="stock-table">
                <thead>
                    <tr>
                        <th class="al-l" style="padding-left:1.25rem;">Material</th>
                        <th class="al-c">In (Purchased)</th>
                        <th class="al-c">Out (Used)</th>
                        <th class="al-c">Current Stock</th>
                        <th class="al-c">Avg Rate</th>
                        <th class="al-r">Total Amount</th>
                        <th class="al-c" style="width:56px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_data)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <span class="es-icon"><i class="fas fa-boxes"></i></span>
                                    <h4>No stock data found</h4>
                                    <p>Stock will appear here once challans are approved.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        // Color palette for avatars
                        $palette = ['#2a58b5','#059669','#d97706','#7c3aed','#dc2626','#0891b2','#be185d','#16a34a'];
                        $i = 0;
                        foreach ($stock_data as $item):
                            $color      = $palette[$i % count($palette)];
                            $initial    = strtoupper(substr($item['material_name'], 0, 1));
                            $real_stock = $item['total_in'] - $item['total_out'];
                            $is_low     = $real_stock < 10;
                            $is_est     = false;

                            if ($item['total_in'] > 0 && $item['total_spend'] > 0) {
                                $avg_rate       = $item['total_spend'] / $item['total_in'];
                                $display_amount = $item['total_spend'];
                            } else {
                                $avg_rate       = $item['default_rate'];
                                $display_amount = $item['total_in'] * $avg_rate;
                                $is_est         = true;
                            }

                            // Progress bar: pct of stock remaining vs total_in
                            $bar_pct = $item['total_in'] > 0 ? max(0, min(100, ($real_stock / $item['total_in']) * 100)) : 0;
                            $bar_color = $is_low ? 'var(--red)' : ($bar_pct > 50 ? 'var(--green)' : 'var(--orange)');
                            $i++;
                    ?>
                        <tr class="row-in" style="animation-delay:<?= ($i - 1) * 30 ?>ms;">

                            <!-- Material Name -->
                            <td style="padding-left:1.25rem;">
                                <div class="mat-cell">
                                    <div style="padding: 2px 0;">
                                        <div class="mat-name" style="font-size: 0.95rem;"><?= htmlspecialchars($item['material_name']) ?></div>
                                        <div class="mat-unit"><?= ucfirst($item['unit']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- In -->
                            <td class="al-c">
                                <span class="qty-in">
                                    <i class="fas fa-arrow-down" style="font-size:0.55rem;"></i>
                                    <?= number_format($item['total_in'], 2) ?> <?= ucfirst($item['unit']) ?>
                                </span>
                            </td>

                            <!-- Out -->
                            <td class="al-c">
                                <span class="qty-out">
                                    <i class="fas fa-arrow-up" style="font-size:0.55rem;"></i>
                                    <?= number_format($item['total_out'], 2) ?> <?= ucfirst($item['unit']) ?>
                                </span>
                            </td>

                            <!-- Current Stock -->
                            <td class="al-c">
                                <div class="stock-val <?= $is_low ? 'low' : 'ok' ?>">
                                    <?= number_format($real_stock, 2) ?>
                                    <?php if ($is_low): ?>
                                        <span class="low-tag">Low</span>
                                    <?php endif; ?>
                                </div>
                                <div class="stock-bar-wrap" style="margin:5px auto 0;">
                                    <div class="stock-bar" style="width:<?= $bar_pct ?>%;background:<?= $bar_color ?>;"></div>
                                </div>
                            </td>

                            <!-- Avg Rate -->
                            <td class="al-c">
                                <span class="avg-price">
                                    <?= formatCurrency($avg_rate) ?> / <?= $item['unit'] ?>
                                    <?php if ($is_est): ?>
                                        <i class="fas fa-info-circle est-icon" title="Estimated — based on master rate (no bill data yet)"></i>
                                    <?php endif; ?>
                                </span>
                            </td>

                            <!-- Total Amount -->
                            <td class="al-r">
                                <span class="amt-cell"><?= formatCurrency($display_amount) ?></span>
                            </td>

                            <!-- Ledger link -->
                            <td class="al-c">
                                <a href="<?= BASE_URL ?>modules/inventory/ledger.php?id=<?= $item['id'] ?>"
                                   class="ledger-btn" title="View Ledger">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>