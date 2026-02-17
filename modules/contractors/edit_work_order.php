<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$masterService = new MasterService();
$db = Database::getInstance();

$page_title = 'Edit Work Order';
$current_page = 'work_orders';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    setFlashMessage('error', 'Invalid Work Order ID.');
    redirect('modules/contractors/work_orders.php');
}

$workOrder = $masterService->getWorkOrder($id);
if (!$workOrder) {
    setFlashMessage('error', 'Work Order not found.');
    redirect('modules/contractors/work_orders.php');
}

$projects    = $masterService->getAllProjects();
$contractors = $db->query("SELECT id, name, mobile, address, gst_number, pan_number, contractor_type FROM parties WHERE party_type = 'contractor' AND status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/contractors/edit_work_order.php?id=' . $id);
    }
    try {
        $data = [
            'project_id'      => intval($_POST['project_id']),
            'contractor_id'   => intval($_POST['contractor_id']),
            'work_order_no'   => sanitize($_POST['work_order_no'] ?? $workOrder['work_order_no']),
            'title'           => sanitize($_POST['title']),
            'contract_amount' => floatval($_POST['contract_amount']),
            'gst_rate'        => floatval($_POST['gst_rate']),
            'tds_percentage'  => floatval($_POST['tds_percentage']),
            'status'          => sanitize($_POST['status'])
        ];
        $contractor_name = trim($_POST['contractor_name'] ?? '');
        if (empty($data['contractor_id']) && !empty($contractor_name)) {
            $existing = $db->query("SELECT id FROM parties WHERE name = ? AND party_type='contractor'", [$contractor_name])->fetch();
            if ($existing) {
                $data['contractor_id'] = $existing['id'];
                // Update existing contractor details
                $updateParty = [
                    'mobile'          => sanitize($_POST['mobile'] ?? ''),
                    'address'         => sanitize($_POST['address'] ?? ''),
                    'gst_number'      => sanitize($_POST['gst_number'] ?? ''),
                    'pan_number'      => sanitize($_POST['pan_number'] ?? ''),
                    'contractor_type' => sanitize($_POST['contractor_type'] ?? 'General')
                ];
                $masterService->updateParty($existing['id'], $updateParty);
            } else {
                $newParty = [
                    'party_type'      => 'contractor',
                    'name'            => $contractor_name,
                    'mobile'          => sanitize($_POST['mobile'] ?? ''),
                    'address'         => sanitize($_POST['address'] ?? ''),
                    'gst_number'      => sanitize($_POST['gst_number'] ?? ''),
                    'pan_number'      => sanitize($_POST['pan_number'] ?? ''),
                    'contractor_type' => sanitize($_POST['contractor_type'] ?? 'General'),
                    'status'          => 'active'
                ];
                $data['contractor_id'] = $masterService->createParty($newParty);
            }
        } elseif (!empty($data['contractor_id'])) {
            // Update existing contractor details if ID is provided
            $updateParty = [
                'mobile'          => sanitize($_POST['mobile'] ?? ''),
                'address'         => sanitize($_POST['address'] ?? ''),
                'gst_number'      => sanitize($_POST['gst_number'] ?? ''),
                'pan_number'      => sanitize($_POST['pan_number'] ?? ''),
                'contractor_type' => sanitize($_POST['contractor_type'] ?? 'General')
            ];
            $masterService->updateParty($data['contractor_id'], $updateParty);
        }
        if (empty($data['contractor_id'])) throw new Exception("Contractor is required.");
        $masterService->updateWorkOrder($id, $data);
        setFlashMessage('success', 'Work Order updated successfully');
        redirect('modules/contractors/work_orders.php');
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
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
    --accent:    #2a58b5;
    --accent-lt: #eff4ff;
    --accent-md: #c7d9f9;
    --accent-bg: #f0f5ff;
    --accent-dk: #1e429f;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

/* ── Wrapper ─────────────────────────── */
.page-wrap {
    max-width: 980px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ── Animations ──────────────────────── */
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
.page-header {
    display: flex; align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2.5rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem; flex-wrap: wrap;
    opacity: 0;
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.ch-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) forwards;
}
.ch-card:nth-of-type(1) { animation-delay: 0.12s; }
.ch-card:nth-of-type(2) { animation-delay: 0.24s; }

/* ── Page header ─────────────────────── */
.eyebrow {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.15em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem; font-weight: 700;
    line-height: 1.1; color: var(--ink); margin: 0;
}
.page-header h1 em { color: var(--accent); font-style: italic; }

.header-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

/* status badge */
.st-tag {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.75rem; border-radius: 20px;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.05em; text-transform: uppercase;
}
.st-tag .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: 0.7; }
.st-tag.active    { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.st-tag.completed { background: var(--accent-lt); color: var(--accent-dk); border: 1px solid var(--accent-md); }
.st-tag.cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.back-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; font-weight: 500;
    color: var(--ink-soft); text-decoration: none;
    padding: 0.45rem 1rem;
    border: 1.5px solid var(--border); border-radius: 6px;
    background: white; transition: all 0.18s ease;
}
.back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Card structure ──────────────────── */
.card-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.1rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.card-icon {
    width: 30px; height: 30px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
}
.card-icon.blue  { background: var(--accent-lt); color: var(--accent); }
.card-icon.green { background: #d1fae5; color: #059669; }
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;
}
.step-tag {
    margin-left: auto;
    font-size: 0.62rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.65rem; border-radius: 20px;
}
.card-body { padding: 1.6rem; }

/* ── Section labels ──────────────────── */
.sec-label {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 1rem;
    padding-bottom: 0.45rem; border-bottom: 1px solid var(--border-lt);
}
.sec-gap { margin-top: 1.75rem; }

/* ── Form grids ──────────────────────── */
.fg2 { display: grid; grid-template-columns: 1fr 1fr;       gap: 1.1rem; }
.fg3 { display: grid; grid-template-columns: 1fr 1fr 1fr;   gap: 1.1rem; }
@media (max-width: 720px) { .fg2, .fg3 { grid-template-columns: 1fr; } }

/* ── Fields ──────────────────────────── */
.field { display: flex; flex-direction: column; gap: 0.38rem; }
.field label {
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute);
}
.field label .req { color: #dc2626; margin-left: 2px; }
.field input,
.field select {
    width: 100%; height: 40px; padding: 0 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.field select {
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.8rem center; padding-right: 2.2rem;
}
.field input:focus, .field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(42,88,181,0.11);
    background: white;
}

/* ── Autocomplete ────────────────────── */
.ac-wrap { position: relative; }
.ac-list {
    position: absolute; top: calc(100% + 5px); left: 0; right: 0;
    background: white; border: 1.5px solid var(--border);
    border-radius: 9px; list-style: none; padding: 0.35rem;
    margin: 0; z-index: 300; display: none;
    max-height: 220px; overflow-y: auto;
    box-shadow: 0 10px 28px rgba(26,23,20,0.1);
}
.ac-list.show { display: block; }
.ac-item {
    padding: 0.55rem 0.75rem; cursor: pointer;
    border-radius: 5px; font-size: 0.875rem; color: var(--ink);
    display: flex; align-items: center; gap: 0.5rem;
    transition: background 0.12s ease;
}
.ac-item:hover, .ac-item.active { background: var(--accent-lt); color: var(--accent); }
.ac-item .ac-ic {
    width: 22px; height: 22px; border-radius: 5px;
    background: var(--accent-md); color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem; flex-shrink: 0;
}

/* ── Action bar ──────────────────────── */
.action-bar {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 0.75rem; padding: 1.1rem 1.6rem;
    background: #fafbff; border-top: 1.5px solid var(--border-lt);
}
.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.62rem 1.25rem;
    border: 1.5px solid var(--border); background: white;
    color: var(--ink-soft); border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 500;
    text-decoration: none; cursor: pointer; transition: all 0.18s ease;
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }
.btn-submit {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.62rem 1.6rem;
    background: var(--ink); color: white;
    border: 1.5px solid var(--ink); border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s ease; letter-spacing: 0.01em;
}
.btn-submit:hover {
    background: var(--accent); border-color: var(--accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(42,88,181,0.3);
}
.btn-submit:active { transform: translateY(0); }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Contracts &rsaquo; Work Orders</div>
            <h1>Edit Work <em>Order</em></h1>
        </div>
        <div class="header-right">
            <?php
                $stCls   = $workOrder['status'];
                $stLabel = ucfirst(str_replace('_', ' ', $workOrder['status']));
            ?>
            <span class="st-tag <?= $stCls ?>">
                <span class="dot"></span><?= $stLabel ?>
            </span>
            <a href="work_orders.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <form method="POST">
        <?= csrf_field() ?>

        <!-- ── Card 1: Project & Contractor ─── -->
        <div class="ch-card">
            <div class="card-head">
                <div class="card-icon blue"><i class="fas fa-file-contract"></i></div>
                <h2>Project &amp; Contractor</h2>
                <span class="step-tag">Step 1 of 2</span>
            </div>
            <div class="card-body">

                <div class="sec-label">Primary Details</div>
                <div class="fg2">
                    <div class="field">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" required>
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['id'] == $workOrder['project_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Status <span class="req">*</span></label>
                        <select name="status" required>
                            <option value="active"    <?= $workOrder['status'] == 'active'    ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $workOrder['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $workOrder['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="sec-gap"></div>
                <div class="sec-label">Contractor Details</div>

                <div class="fg3">
                    <div class="field">
                        <label>Contractor Name <span class="req">*</span></label>
                        <div class="ac-wrap">
                            <input type="text" name="contractor_name" id="contractor_name"
                                   value="<?= htmlspecialchars($workOrder['contractor_name']) ?>"
                                   placeholder="Search or add…" autocomplete="off" required>
                            <ul id="ac_list" class="ac-list"></ul>
                        </div>
                        <input type="hidden" name="contractor_id" id="contractor_id" value="<?= $workOrder['contractor_id'] ?>">
                    </div>
                    <div class="field">
                        <label>Mobile</label>
                        <input type="text" name="mobile" id="mobile" placeholder="Mobile number" 
                               value="<?= htmlspecialchars($workOrder['contractor_mobile'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Trade / Type</label>
                        <select name="contractor_type" id="contractor_type">
                            <option value="General"     <?= ($workOrder['contractor_type'] ?? '') == 'General'     ? 'selected' : '' ?>>General</option>
                            <option value="Civil"       <?= ($workOrder['contractor_type'] ?? '') == 'Civil'       ? 'selected' : '' ?>>Civil</option>
                            <option value="Electrical"  <?= ($workOrder['contractor_type'] ?? '') == 'Electrical'  ? 'selected' : '' ?>>Electrical</option>
                            <option value="Plumbing"    <?= ($workOrder['contractor_type'] ?? '') == 'Plumbing'    ? 'selected' : '' ?>>Plumbing</option>
                            <option value="Carpentry"   <?= ($workOrder['contractor_type'] ?? '') == 'Carpentry'   ? 'selected' : '' ?>>Carpentry</option>
                            <option value="Painting"    <?= ($workOrder['contractor_type'] ?? '') == 'Painting'    ? 'selected' : '' ?>>Painting</option>
                            <option value="Fabrication" <?= ($workOrder['contractor_type'] ?? '') == 'Fabrication' ? 'selected' : '' ?>>Fabrication</option>
                            <option value="HVAC"        <?= ($workOrder['contractor_type'] ?? '') == 'HVAC'        ? 'selected' : '' ?>>HVAC</option>
                            <option value="Other"       <?= ($workOrder['contractor_type'] ?? '') == 'Other'       ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="fg3" style="margin-top:1.1rem;">
                    <div class="field">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" id="gst_number" placeholder="Optional"
                               value="<?= htmlspecialchars($workOrder['contractor_gst'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" id="pan_number" placeholder="Required for TDS"
                               value="<?= htmlspecialchars($workOrder['contractor_pan'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Address</label>
                        <input type="text" name="address" id="address" placeholder="Location / City"
                               value="<?= htmlspecialchars($workOrder['contractor_address'] ?? '') ?>">
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Card 2: Contract Details ─────── -->
        <div class="ch-card">
            <div class="card-head">
                <div class="card-icon green"><i class="fas fa-clipboard-list"></i></div>
                <h2>Contract Details</h2>
                <span class="step-tag">Step 2 of 2</span>
            </div>
            <div class="card-body">

                <div class="fg2">
                    <div class="field">
                        <label>Work Order Title <span class="req">*</span></label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($workOrder['title']) ?>">
                    </div>
                    <div class="field">
                        <label>Work Order No.</label>
                        <input type="text" name="work_order_no" value="<?= htmlspecialchars($workOrder['work_order_no']) ?>">
                    </div>
                </div>

                <div class="sec-gap"></div>
                <div class="sec-label">Financials</div>

                <div class="fg3">
                    <div class="field">
                        <label>Contract Value (₹) <span class="req">*</span></label>
                        <input type="number" name="contract_amount" step="0.01" required
                               value="<?= $workOrder['contract_amount'] ?>">
                    </div>
                    <div class="field">
                        <label>GST Rate (%)</label>
                        <select name="gst_rate">
                            <option value="0"  <?= $workOrder['gst_rate'] ==  0 ? 'selected' : '' ?>>0%</option>
                            <option value="5"  <?= $workOrder['gst_rate'] ==  5 ? 'selected' : '' ?>>5%</option>
                            <option value="12" <?= $workOrder['gst_rate'] == 12 ? 'selected' : '' ?>>12%</option>
                            <option value="18" <?= $workOrder['gst_rate'] == 18 ? 'selected' : '' ?>>18%</option>
                            <option value="28" <?= $workOrder['gst_rate'] == 28 ? 'selected' : '' ?>>28%</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>TDS Rate (%)</label>
                        <select name="tds_percentage">
                            <option value="0"  <?= $workOrder['tds_percentage'] ==  0 ? 'selected' : '' ?>>0%</option>
                            <option value="1"  <?= $workOrder['tds_percentage'] ==  1 ? 'selected' : '' ?>>1% — Individual / HUF</option>
                            <option value="2"  <?= $workOrder['tds_percentage'] ==  2 ? 'selected' : '' ?>>2% — Company / Firm</option>
                            <option value="5"  <?= $workOrder['tds_percentage'] ==  5 ? 'selected' : '' ?>>5%</option>
                            <option value="10" <?= $workOrder['tds_percentage'] == 10 ? 'selected' : '' ?>>10%</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="action-bar">
                <a href="work_orders.php" class="btn-ghost">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Update Work Order
                </button>
            </div>
        </div>

    </form>
</div>

<script>
(function () {
    const contractors = <?= json_encode($contractors) ?>;
    const input  = document.getElementById('contractor_name');
    const list   = document.getElementById('ac_list');
    const hidden = document.getElementById('contractor_id');
    let idx = -1;

    function close() { list.classList.remove('show'); idx = -1; }

    function render(matches) {
        list.innerHTML = '';
        if (!matches.length) { close(); return; }
        matches.forEach(item => {
            const li = document.createElement('li');
            li.className = 'ac-item';
            li.innerHTML = `<span class="ac-ic"><i class="fas fa-hard-hat"></i></span>${item.name}`;
            li.addEventListener('mousedown', e => { e.preventDefault(); pick(item); });
            list.appendChild(li);
        });
        list.classList.add('show');
    }

    function pick(item) {
        input.value  = item.name;
        hidden.value = item.id;
        document.getElementById('mobile').value          = item.mobile          || '';
        document.getElementById('gst_number').value      = item.gst_number      || '';
        document.getElementById('pan_number').value      = item.pan_number      || '';
        document.getElementById('address').value         = item.address         || '';
        document.getElementById('contractor_type').value = item.contractor_type || 'General';
        close();
    }

    input.addEventListener('input', function () {
        hidden.value = '';
        const val = this.value.toLowerCase().trim();
        if (!val) { close(); return; }
        render(contractors.filter(c => c.name.toLowerCase().includes(val)));
    });

    input.addEventListener('keydown', function (e) {
        const items = list.querySelectorAll('.ac-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % items.length; }
        else if (e.key === 'ArrowUp')  { e.preventDefault(); idx = (idx - 1 + items.length) % items.length; }
        else if (e.key === 'Enter')    { e.preventDefault(); if (idx >= 0) items[idx].dispatchEvent(new MouseEvent('mousedown')); }
        else if (e.key === 'Escape')   { close(); return; }
        items.forEach((li, i) => li.classList.toggle('active', i === idx));
    });

    document.addEventListener('click', e => {
        if (!list.contains(e.target) && e.target !== input) close();
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>