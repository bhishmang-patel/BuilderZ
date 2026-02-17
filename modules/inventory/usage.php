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
$page_title   = 'Material Usage';
$current_page = 'usage';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/inventory/usage.php');
    }
    if (($_POST['action'] ?? '') === 'record_usage') {
        $project_id  = intval($_POST['project_id']);
        $material_id = intval($_POST['material_id']);
        $quantity    = floatval($_POST['quantity']);
        $usage_date  = $_POST['usage_date'];
        $remarks     = sanitize($_POST['remarks'] ?? '');

        $stock_sql = "SELECT m.material_name, m.unit,
                        (
                            (SELECT COALESCE(SUM(ci.quantity), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status = 'approved')
                            -
                            (SELECT COALESCE(SUM(mu.quantity), 0) FROM material_usage mu WHERE mu.material_id = m.id)
                        ) as current_stock
                      FROM materials m WHERE m.id = ?";
        $mat = $db->query($stock_sql, [$material_id])->fetch();

        if ($quantity <= 0) {
            setFlashMessage('error', 'Usage quantity must be greater than zero.');
        } elseif ($mat['current_stock'] < $quantity) {
            setFlashMessage('error', "Insufficient stock for {$mat['material_name']}. Available: {$mat['current_stock']} {$mat['unit']}");
        } else {
            $db->beginTransaction();
            try {
                $data = ['project_id' => $project_id, 'material_id' => $material_id, 'quantity' => $quantity, 'usage_date' => $usage_date, 'remarks' => $remarks, 'created_by' => $_SESSION['user_id']];
                $id   = $db->insert('material_usage', $data);
                logAudit('create', 'material_usage', $id, null, $data);

                // ── Low Stock Notification ──
                // Calculate new stock
                $new_stock = $mat['current_stock'] - $quantity;
                $min_limit = $mat['min_limit'] ?? 10.0; // Default to 10 if not set

                if ($new_stock < $min_limit) {
                    require_once __DIR__ . '/../../includes/NotificationService.php';
                    $ns = new NotificationService();
                    
                    $notifTitle = "Low Stock Alert: " . $mat['material_name'];
                    $notifMsg   = "Stock for {$mat['material_name']} has fallen to {$new_stock} {$mat['unit']} (Below limit: $min_limit)";
                    $notifLink  = BASE_URL . "modules/inventory/index.php";

                    // Notify Admin and potentially Store Manager
                    $ns->create(1, $notifTitle, $notifMsg, 'warning', $notifLink);
                }

                $db->commit();
                setFlashMessage('success', 'Material usage recorded successfully');
                redirect('modules/inventory/usage.php');
            } catch (Exception $e) {
                $db->rollback();
                setFlashMessage('error', 'Error recording usage: ' . $e->getMessage());
            }
        }
    }
}

$usage_history = $db->query(
    "SELECT mu.*, p.project_name, m.material_name, m.unit,
            COALESCE(NULLIF(u.full_name, ''), u.username, 'Unknown') AS used_by_name
     FROM material_usage mu
     JOIN projects p ON mu.project_id = p.id
     JOIN materials m ON mu.material_id = m.id
     LEFT JOIN users u ON mu.created_by = u.id
     ORDER BY mu.usage_date DESC, mu.created_at DESC"
)->fetchAll();

$projects  = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$materials = $db->query(
    "SELECT m.id, m.material_name, m.unit,
        (
            (SELECT COALESCE(SUM(ci.quantity), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status = 'approved')
            -
            (SELECT COALESCE(SUM(mu.quantity), 0) FROM material_usage mu WHERE mu.material_id = m.id)
        ) AS current_stock
     FROM materials m HAVING current_stock > 0 ORDER BY m.material_name"
)->fetchAll();

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
.pw  { max-width: 1240px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

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

/* ── Two-col layout ───────────────── */
.layout { display: grid; grid-template-columns: 360px 1fr; gap: 1.25rem; align-items: start; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }

/* ── Card base ────────────────────── */
.card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) both;
}
.card.c1 { animation-delay: 0.08s; }
.card.c2 { animation-delay: 0.16s; }

.card-head {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.ch-ic {
    width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.78rem;
}
.ch-ic.orange { background: var(--orange-lt); color: var(--orange); border: 1px solid #fcd34d; }
.ch-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.card-head h2 {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
    color: var(--ink); margin: 0; flex: 1;
}
.card-head p { font-size: 0.72rem; color: var(--ink-mute); margin: 2px 0 0; }
.step-tag {
    font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream); border: 1px solid var(--border);
    padding: 0.18rem 0.6rem; border-radius: 20px;
}
.count-tag {
    font-size: 0.62rem; font-weight: 800; padding: 0.15rem 0.55rem; border-radius: 20px;
    background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border);
    font-family: 'DM Sans', sans-serif;
}

.card-body { padding: 1.5rem; }

/* ── Section labels ──────────────── */
.sec {
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.13em; text-transform: uppercase;
    color: var(--ink-mute); margin: 1.2rem 0 0.75rem;
    padding-bottom: 0.38rem; border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.38rem;
}
.sec:first-child { margin-top: 0; }

/* ── Form fields ──────────────────── */
.mf { display: flex; flex-direction: column; gap: 0.32rem; margin-bottom: 1rem; }
.mf:last-of-type { margin-bottom: 0; }
.mf label { font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.mf label .req { color: #dc2626; margin-left: 2px; }
.mf input, .mf select, .mf textarea {
    width: 100%; height: 40px; padding: 0 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    -webkit-appearance: none; appearance: none;
}
.mf textarea { height: auto; padding: 0.65rem 0.85rem; resize: vertical; min-height: 72px; }
.mf select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.85rem center; padding-right: 2.2rem;
}
.mf input:focus, .mf select:focus, .mf textarea:focus {
    border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.11); background: white;
}
.mf-row { display: grid; grid-template-columns: 1fr auto; gap: 0.65rem; align-items: end; }
.unit-badge {
    height: 40px; padding: 0 0.85rem; display: flex; align-items: center;
    background: var(--cream); border: 1.5px solid var(--border); border-radius: 8px;
    font-size: 0.82rem; font-weight: 700; color: var(--ink-mute); white-space: nowrap;
    min-width: 56px; justify-content: center;
}

/* stock hint */
.stock-hint {
    margin-top: 0.4rem; padding: 0.45rem 0.75rem;
    background: var(--green-lt); border: 1px solid #6ee7b7; border-radius: 6px;
    font-size: 0.72rem; font-weight: 600; color: var(--green);
    display: none; align-items: center; gap: 0.38rem;
}
.stock-hint.show { display: flex; }
.stock-hint.low  { background: var(--red-lt); border-color: #fca5a5; color: var(--red); }

/* submit button */
.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 0.45rem;
    width: 100%; height: 42px;
    background: var(--orange); color: white; border: none; border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.18s; margin-top: 1.25rem;
}
.btn-submit:hover { background: #b45309; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(217,119,6,0.35); }
.btn-submit:active { transform: translateY(0); }

/* ── History table ────────────────── */
.hist-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.hist-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.hist-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.hist-table thead th.al-c { text-align: center; }
.hist-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.hist-table tbody tr:last-child { border-bottom: none; }
.hist-table tbody tr:hover { background: #f4f7fd; }
.hist-table tbody tr.row-in { animation: rowIn 0.26s cubic-bezier(0.22,1,0.36,1) forwards; }
.hist-table td { padding: 0.78rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.hist-table td.al-c { text-align: center; }

/* date */
.date-main { font-size: 0.82rem; font-weight: 700; color: var(--ink); }
.date-sub  { font-size: 0.68rem; color: var(--ink-mute); margin-top: 1px; }

/* project pill */
.proj-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.22rem 0.65rem; border-radius: 20px; font-size: 0.68rem; font-weight: 700;
    background: var(--cream); color: var(--ink-soft); border: 1px solid var(--border);
}

/* material name */
.mat-name { font-weight: 700; color: var(--ink); font-size: 0.875rem; }

/* qty badge */
.qty-badge {
    display: inline-flex; align-items: center; gap: 0.28rem;
    padding: 0.22rem 0.65rem; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
    background: var(--red-lt); color: var(--red); border: 1px solid #fca5a5;
}

/* remarks */
.remarks-text { font-size: 0.8rem; color: var(--ink-mute); max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* user avatar */
.user-cell { display: flex; align-items: center; gap: 0.45rem; }
.user-av {
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--accent-lt); color: var(--accent); border: 1px solid var(--accent-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem; font-weight: 800; flex-shrink: 0;
}
.user-name { font-size: 0.8rem; font-weight: 600; color: var(--ink-soft); }

/* empty */
.empty-state { text-align: center; padding: 3.5rem 1.5rem; }
.empty-state .es-icon { font-size: 2.25rem; display: block; margin-bottom: 0.65rem; color: var(--accent); opacity: 0.18; }
.empty-state h4 { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink-soft); margin: 0 0 0.3rem; }
.empty-state p  { font-size: 0.8rem; color: var(--ink-mute); margin: 0; }
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Inventory &rsaquo; Usage</div>
            <h1>Record <em>Usage</em></h1>
        </div>
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock
        </a>
    </div>

    <div class="layout">

        <!-- ── Left: Record Form ──────── -->
        <div class="card c1">
            <div class="card-head">
                <div class="ch-ic orange"><i class="fas fa-minus-circle"></i></div>
                <div>
                    <h2>Record Consumption</h2>
                    <p>Book material usage against a project</p>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="usageForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="record_usage">

                    <div class="sec"><i class="fas fa-calendar-day"></i> When</div>

                    <div class="mf">
                        <label>Usage Date <span class="req">*</span></label>
                        <input type="date" name="usage_date" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="sec"><i class="fas fa-building"></i> Where</div>

                    <div class="mf">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" required>
                            <option value="">Select project…</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sec"><i class="fas fa-cube"></i> What</div>

                    <div class="mf">
                        <label>Material <span class="req">*</span></label>
                        <select name="material_id" id="material_select" required>
                            <option value="">Select material…</option>
                            <?php foreach ($materials as $m): ?>
                                <option value="<?= $m['id'] ?>"
                                        data-stock="<?= $m['current_stock'] ?>"
                                        data-unit="<?= htmlspecialchars($m['unit']) ?>">
                                    <?= htmlspecialchars($m['material_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="stock-hint" id="stock_hint">
                            <i class="fas fa-layer-group" style="font-size:0.7rem;"></i>
                            <span id="stock_hint_text">Available: —</span>
                        </div>
                    </div>

                    <div class="mf">
                        <label>Quantity <span class="req">*</span></label>
                        <div class="mf-row">
                            <input type="number" name="quantity" id="qty_input"
                                   step="0.01" min="0.01" required placeholder="0.00">
                            <div class="unit-badge" id="unit_badge">—</div>
                        </div>
                    </div>

                    <div class="mf">
                        <label>Remarks</label>
                        <textarea name="remarks" placeholder="e.g. Block A foundation pour…"></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Submit Consumption
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Right: History ─────────── -->
        <div class="card c2">
            <div class="card-head">
                <div class="ch-ic blue"><i class="fas fa-history"></i></div>
                <h2>Usage History
                    <span class="count-tag" style="margin-left:0.3rem;"><?= count($usage_history) ?></span>
                </h2>
            </div>

            <div style="overflow-x:auto;">
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Material</th>
                            <th class="al-c">Quantity</th>
                            <th>Remarks</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usage_history)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <span class="es-icon"><i class="fas fa-clipboard-list"></i></span>
                                        <h4>No usage records yet</h4>
                                        <p>Submitted entries will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            foreach ($usage_history as $i => $r):
                                $d = date_create($r['usage_date']);
                                $initial = strtoupper(substr(trim($r['used_by_name'] ?: 'U'), 0, 1));
                        ?>
                            <tr class="row-in" style="animation-delay:<?= $i * 25 ?>ms;">
                                <td>
                                    <div class="date-main"><?= date_format($d, 'd M Y') ?></div>
                                    <div class="date-sub"><?= date_format($d, 'D') ?></div>
                                </td>
                                <td>
                                    <span class="proj-pill">
                                        <i class="fas fa-building" style="font-size:0.58rem;"></i>
                                        <?= htmlspecialchars($r['project_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="mat-name"><?= htmlspecialchars($r['material_name']) ?></span>
                                </td>
                                <td class="al-c">
                                    <span class="qty-badge">
                                        <i class="fas fa-arrow-up" style="font-size:0.55rem;"></i>
                                        <?= number_format($r['quantity'], 2) ?> <?= htmlspecialchars($r['unit']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="remarks-text" title="<?= htmlspecialchars($r['remarks']) ?>">
                                        <?= $r['remarks'] ? htmlspecialchars($r['remarks']) : '<span style="color:var(--ink-mute)">—</span>' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-av"><?= $initial ?></div>
                                        <span class="user-name"><?= htmlspecialchars($r['used_by_name'] ?: 'Unknown') ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const matSelect = document.getElementById('material_select');
const unitBadge = document.getElementById('unit_badge');
const stockHint = document.getElementById('stock_hint');
const stockText = document.getElementById('stock_hint_text');
const qtyInput  = document.getElementById('qty_input');

matSelect.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) {
        unitBadge.textContent = '—';
        stockHint.classList.remove('show', 'low');
        return;
    }
    const unit  = opt.getAttribute('data-unit');
    const stock = parseFloat(opt.getAttribute('data-stock'));
    unitBadge.textContent = unit;
    stockText.textContent = `Available: ${stock.toFixed(2)} ${unit}`;
    stockHint.classList.add('show');
    stockHint.classList.toggle('low', stock < 10);
    qtyInput.max = stock;
});

qtyInput.addEventListener('input', function () {
    const opt = matSelect.options[matSelect.selectedIndex];
    if (!opt.value) return;
    const stock = parseFloat(opt.getAttribute('data-stock'));
    const qty   = parseFloat(this.value) || 0;
    stockHint.classList.toggle('low', qty > stock || stock < 10);
    stockText.textContent = qty > stock
        ? `⚠ Exceeds available stock (${stock.toFixed(2)} ${opt.getAttribute('data-unit')})`
        : `Available: ${stock.toFixed(2)} ${opt.getAttribute('data-unit')}`;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>