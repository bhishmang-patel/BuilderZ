<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$page_title = 'All Expenses';

// --- Deletion ---
if (isset($_POST['delete_id'])) {
    $del_id = $_POST['delete_id'];
    try {
        $db->query("DELETE FROM expenses WHERE id = ?", [$del_id]);
        $_SESSION['success'] = "Expense record deleted successfully.";
        header("Location: list.php");
        exit;
    } catch (Exception $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// --- Filters ---
$date_from      = $_GET['date_from']      ?? date('Y-m-01');
$date_to        = $_GET['date_to']        ?? date('Y-m-d');
$project_id     = $_GET['project_id']     ?? '';
$category_id    = $_GET['category_id']    ?? '';
$payment_method = $_GET['payment_method'] ?? '';

$projects   = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$categories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();

$params = [$date_from, $date_to];
$where  = "WHERE e.date BETWEEN ? AND ?";
if (!empty($project_id))     { $where .= " AND e.project_id = ?";      $params[] = $project_id; }
if (!empty($category_id))    { $where .= " AND e.category_id = ?";     $params[] = $category_id; }
if (!empty($payment_method)) { $where .= " AND e.payment_method = ?";  $params[] = $payment_method; }

$sql = "SELECT e.*, ec.name as category_name, p.project_name 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id 
        LEFT JOIN projects p ON e.project_id = p.id 
        $where 
        ORDER BY e.date DESC, e.id DESC";

$expenses = $db->query($sql, $params)->fetchAll();

$total_amount = 0;
foreach ($expenses as $exp) {
    $total_amount += $exp['amount'];
}

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
    --accent:    #2a58b5ff;
    --accent-bg: #fdf8f3;
}

body {
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
}

.page-wrap {
    max-width: 1260px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ════════════════════════════════════════
   ENTRANCE ANIMATIONS
   ════════════════════════════════════════ */
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes rowSlide {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
    opacity: 0;
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

.filter-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.12s forwards;
}

.main-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.22s forwards;
}

.data-table tbody tr.row-anim {
    opacity: 0;
    transform: translateX(-10px);
    animation: rowSlide 0.34s cubic-bezier(0.22,1,0.36,1) forwards;
}

/* ── Page Header ─────────────────────── */
.page-header .eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--ink);
    margin: 0;
}
.page-header h1 em { color: var(--accent); font-style: italic; }

.header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.total-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    background: var(--cream);
    border: 1.5px solid var(--border);
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ink-soft);
    white-space: nowrap;
}
.total-pill .total-val {
    font-family: 'Fraunces', serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--accent);
}

.btn-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--ink-soft);
    text-decoration: none;
    padding: 0.45rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    background: white;
    transition: all 0.18s ease;
    white-space: nowrap;
}
.btn-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

.btn-link.primary {
    background: var(--ink);
    color: white;
    border-color: var(--ink);
}
.btn-link.primary:hover {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}

/* ── Filter Card ─────────────────────── */
.filter-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.filter-icon {
    width: 28px; height: 28px;
    border-radius: 6px;
    background: #ede9fe;
    color: #7c3aed;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; flex-shrink: 0;
}
.filter-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
}

.filter-body { padding: 1.25rem 1.5rem; }

.filter-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 1fr auto;
    gap: 1rem;
    align-items: flex-end;
}
@media (max-width: 900px) {
    .filter-grid { grid-template-columns: 1fr 1fr; }
    .filter-grid .filter-submit { grid-column: span 2; }
}
@media (max-width: 520px) {
    .filter-grid { grid-template-columns: 1fr; }
    .filter-grid .filter-submit { grid-column: 1; }
}

.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field label {
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute);
}
.field input,
.field select {
    width: 100%;
    height: 40px;
    padding: 0 0.85rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    color: var(--ink);
    background: #fdfcfa;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    -webkit-appearance: none;
    appearance: none;
}
.field select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.8rem center;
    padding-right: 2.2rem;
}
.field input:focus,
.field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    background: white;
}

.date-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }

.btn-filter {
    height: 40px;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0 1.25rem;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s ease, transform 0.1s ease;
    white-space: nowrap;
}
.btn-filter:hover { background: #9e521f; transform: translateY(-1px); }
.btn-filter:active { transform: translateY(0); }

/* ── Main Card ───────────────────────── */
.card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.card-icon {
    width: 28px; height: 28px;
    border-radius: 6px;
    background: #fee2e2; color: #dc2626;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; flex-shrink: 0;
}
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
}
.card-head .count-tag {
    margin-left: auto;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.6rem; border-radius: 20px;
}

/* ── Table ───────────────────────────── */
.table-wrap { overflow-x: auto; }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table thead tr {
    background: #f5f1eb;
    border-bottom: 1.5px solid var(--border);
}
.data-table thead th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.data-table thead th.right { text-align: right; }
.data-table thead th.center { text-align: center; }

.data-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s ease;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #fdfcfa; }

.data-table td {
    padding: 0.875rem 1rem;
    vertical-align: middle;
    color: var(--ink-soft);
}
.data-table td.right { text-align: right; }
.data-table td.center { text-align: center; }

/* ── Date cell ───────────────────────── */
.date-cell {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--accent);
    white-space: nowrap;
}

/* ── Project badge ───────────────────── */
.proj-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem; font-weight: 600;
    color: #2563eb;
    background: #dbeafe;
    border: 1px solid #bfdbfe;
    padding: 0.22rem 0.6rem;
    border-radius: 4px;
    white-space: nowrap;
}
.proj-badge.gray {
    color: var(--ink-soft);
    background: var(--cream);
    border-color: var(--border);
}

/* ── Category badge ──────────────────── */
.cat-badge {
    display: inline-block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.05em; text-transform: uppercase;
    color: var(--ink-soft);
    background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    white-space: nowrap;
}

/* ── Description ─────────────────────── */
.desc-main { font-size: 0.875rem; font-weight: 600; color: var(--ink); }
.desc-gst  { font-size: 0.72rem; color: #059669; margin-top: 2px; display: flex; align-items: center; gap: 0.25rem; }

/* ── Amount ──────────────────────────── */
.amount {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 700; color: var(--ink);
    white-space: nowrap;
}

/* ── Mode badge ──────────────────────── */
.mode-badge {
    display: inline-block;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase;
    color: var(--ink-mute);
    background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.5rem;
    border-radius: 4px;
}

/* ── Action buttons ──────────────────── */
.action-wrap { display: flex; align-items: center; justify-content: center; gap: 0.4rem; }

.act-btn {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 0.72rem;
    transition: all 0.16s ease; cursor: pointer; text-decoration: none;
}
.act-btn.edit {
    border: 1.5px solid var(--border);
    background: white; color: var(--ink-soft);
}
.act-btn.edit:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.act-btn.del {
    border: 1.5px solid #fecaca;
    background: #fff5f5; color: #dc2626;
}
.act-btn.del:hover { background: #dc2626; border-color: #dc2626; color: white; }

/* ── Table footer ────────────────────── */
.table-foot td {
    padding: 0.85rem 1rem;
    background: #f5f1eb;
    border-top: 1.5px solid var(--border);
    font-size: 0.75rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-soft);
}
.table-foot .foot-total {
    font-family: 'Fraunces', serif;
    font-size: 1.05rem; color: var(--ink);
    text-align: right; letter-spacing: 0;
}

/* ── Empty state ─────────────────────── */
.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    color: var(--ink-mute);
}
.empty-state .ei { font-size: 3rem; opacity: 0.25; margin-bottom: 1rem; display: block; }
.empty-state .et { font-family: 'Fraunces', serif; font-size: 1.1rem; color: var(--ink-soft); margin-bottom: 0.4rem; }
.empty-state .es { font-size: 0.875rem; }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Accounts</div>
            <h1>Expense <em>Register</em></h1>
        </div>
        <div class="header-right">
            <div class="total-pill">
                <span>Total</span>
                <span class="total-val"><?= formatCurrency($total_amount) ?></span>
            </div>
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="btn-link">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>modules/accounts/add.php" class="btn-link primary">
                <i class="fas fa-plus"></i> New Expense
            </a>
        </div>
    </div>

    <!-- ── Filters ──────────────────── -->
    <div class="filter-card">
        <div class="filter-head">
            <div class="filter-icon"><i class="fas fa-filter"></i></div>
            <h2>Filter Expenses</h2>
        </div>
        <div class="filter-body">
            <form method="GET">
                <div class="filter-grid">
                    <div class="field">
                        <label>Date Range</label>
                        <div class="date-pair">
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                            <input type="date" name="date_to"   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Project / Site</label>
                        <select name="project_id">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Payment Mode</label>
                        <select name="payment_method">
                            <option value="">All Modes</option>
                            <option value="cash"   <?= $payment_method === 'cash'   ? 'selected' : '' ?>>Cash</option>
                            <option value="bank"   <?= $payment_method === 'bank'   ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="upi"    <?= $payment_method === 'upi'    ? 'selected' : '' ?>>UPI</option>
                            <option value="cheque" <?= $payment_method === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                            <option value="card"   <?= $payment_method === 'card'   ? 'selected' : '' ?>>Card</option>
                        </select>
                    </div>
                    <div class="filter-submit">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Main Table ───────────────── -->
    <div class="main-card">
        <div class="card-head">
            <div class="card-icon"><i class="fas fa-receipt"></i></div>
            <h2>Expense Records</h2>
            <span class="count-tag"><?= count($expenses) ?> record<?= count($expenses) !== 1 ? 's' : '' ?></span>
        </div>

        <div class="table-wrap">
            <?php if (empty($expenses)): ?>
                <div class="empty-state">
                    <span class="ei"><i class="fas fa-search"></i></span>
                    <div class="et">No Expenses Found</div>
                    <div class="es">Try adjusting your filters to see results.</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Description</th>
                            <th class="center">Category</th>
                            <th class="right">Amount</th>
                            <th class="center">Mode</th>
                            <th class="center" style="width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $i => $row):
                            $delay_ms = 40 + $i * 38;
                        ?>
                        <tr class="row-anim" style="animation-delay:<?= $delay_ms ?>ms">

                            <td>
                                <span class="date-cell"><?= date('d M Y', strtotime($row['date'])) ?></span>
                            </td>

                            <td>
                                <?php if (!empty($row['project_name'])): ?>
                                   <?= renderProjectBadge($row['project_name'], $row['project_id']) ?>                                    </span>
                                <?php else: ?>
                                    <span class="proj-badge gray">Head Office</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="desc-main"><?= htmlspecialchars($row['description'] ?: '—') ?></div>
                                <?php if (!empty($row['gst_included'])): ?>
                                    <div class="desc-gst">
                                        <i class="fas fa-check-circle" style="font-size:0.65rem;"></i>
                                        GST Paid: <?= formatCurrencyShort($row['gst_amount']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="center">
                                <span class="cat-badge"><?= htmlspecialchars($row['category_name'] ?: '—') ?></span>
                            </td>

                            <td class="right">
                                <span class="amount"><?= formatCurrency($row['amount']) ?></span>
                            </td>

                            <td class="center">
                                <span class="mode-badge"><?= $row['payment_method'] ?></span>
                            </td>

                            <td class="center">
                                <div class="action-wrap">
                                    <a href="add.php?id=<?= $row['id'] ?>" class="act-btn edit" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirmAction(event, 'Delete this expense?', 'Yes, Delete');" style="margin:0; display:contents;">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="act-btn del" title="Delete">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-foot">
                        <tr>
                            <td colspan="4">Total (<?= count($expenses) ?> records)</td>
                            <td class="foot-total"><?= formatCurrency($total_amount) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>