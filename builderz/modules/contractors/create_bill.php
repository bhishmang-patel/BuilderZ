<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$masterService = new MasterService();

$page_title = 'Create Contractor Bill';
$current_page = 'contractor_pay';

$contractors = $masterService->getAllParties(['type' => 'contractor']);
$projects    = $masterService->getAllProjects();
$workOrders  = $masterService->getAllWorkOrders();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        header("Location: create_bill.php");
        exit;
    }
    try {
        $db->beginTransaction();

        $contractor_id  = intval($_POST['contractor_id']);
        $project_id     = intval($_POST['project_id']);
        $work_order_id  = !empty($_POST['work_order_id']) ? intval($_POST['work_order_id']) : null;
        $challan_no     = sanitize($_POST['challan_no']);

        if ($contractor_id <= 0) throw new Exception("Invalid Contractor ID.");
        if ($project_id <= 0) throw new Exception("Invalid Project ID.");
        if (empty($challan_no)) throw new Exception("Bill Number is required.");

        // Check for duplicate Bill No for this contractor
        $stmt = $db->query(
            "SELECT id FROM contractor_bills WHERE bill_no = ? AND contractor_id = ?", 
            [$challan_no, $contractor_id]
        );
        if ($stmt->fetch()) {
            throw new Exception("Bill Number '$challan_no' already exists for this contractor.");
        }

        $bill_data = [
            'bill_no'              => $challan_no,
            'contractor_id'        => $contractor_id,
            'project_id'           => $project_id,
            'work_order_id'        => $work_order_id,
            'bill_date'            => $_POST['challan_date'],
            'work_description'     => sanitize($_POST['work_description']),
            'work_from_date'       => $_POST['work_from_date'],
            'work_to_date'         => $_POST['work_to_date'],
            'basic_amount'         => floatval($_POST['bill_amount']),
            'gst_amount'           => floatval($_POST['gst_amount']),
            'tds_amount'           => floatval($_POST['tds_amount']),
            'total_payable'        => floatval($_POST['final_payable_amount']),
            'is_rcm'               => isset($_POST['is_rcm']) ? 1 : 0,
            'paid_amount'          => 0,
            'pending_amount'       => floatval($_POST['final_payable_amount']),
            'status'               => 'pending',
            'payment_status'       => 'pending',
            'created_by'           => $_SESSION['user_id']
        ];

        $bill_id = $db->insert('contractor_bills', $bill_data);
        if (!$bill_id) {
             throw new Exception("Bill created but ID not returned.");
        }
        logAudit('create', 'contractor_bills', $bill_id, null, $bill_data);
        
        // ── Notification Trigger ──
        require_once __DIR__ . '/../../includes/NotificationService.php';
        $ns = new NotificationService();
        $notifTitle = "New Contractor Bill Created";
        $notifMsg   = "Bill #{$challan_no} for Contractor ID {$contractor_id} has been created.";
        $notifLink  = BASE_URL . "modules/contractors/view_bill.php?id=" . $bill_id;
        
        // Notify Admins + Contractor Managers
        $ns->notifyUsersWithPermission('contractors', $notifTitle, $notifMsg . " (Created by " . $_SESSION['username'] . ")", 'info', $notifLink);

        $db->commit();

        setFlashMessage('success', "Contractor Bill {$challan_no} created successfully");
        header("Location: view_bill.php?id=$bill_id");
        exit;
        
    } catch (Throwable $e) { // Catch both Error and Exception
        $db->rollback();
        error_log("Bill Creation Error: " . $e->getMessage());
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
    --accent:    #2a58b5ff;
    --accent-bg: #fdf8f3;
    --accent-dk: #9e521f;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

/* ── Wrapper ─────────────────────────── */
.page-wrap {
    max-width: 1020px;
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
.ch-card:nth-of-type(1) { animation-delay: 0.10s; }
.ch-card:nth-of-type(2) { animation-delay: 0.18s; }
.ch-card:nth-of-type(3) { animation-delay: 0.26s; }
.ch-card:nth-of-type(4) { animation-delay: 0.34s; }

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

.back-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; font-weight: 500; color: var(--ink-soft);
    text-decoration: none; padding: 0.45rem 1rem;
    border: 1.5px solid var(--border); border-radius: 6px;
    background: white; transition: all 0.18s ease; white-space: nowrap;
}
.back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Card structure ──────────────────── */
.card-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.1rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.card-icon {
    width: 30px; height: 30px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
}
.card-icon.orange { background: var(--accent-bg); color: var(--accent); border: 1px solid #e0c9b5; }
.card-icon.blue   { background: #eff4ff; color: #2a58b5; }
.card-icon.green  { background: #d1fae5; color: #059669; }
.card-icon.purple { background: #ede9fe; color: #7c3aed; }

.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;
}
.card-body { padding: 1.6rem; }

/* ── Section labels ──────────────────── */
.sec-label {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 1rem;
    padding-bottom: 0.45rem; border-bottom: 1px solid var(--border-lt);
}

/* ── Form grids ──────────────────────── */
.fg2 { display: grid; grid-template-columns: 1fr 1fr;         gap: 1.1rem; }
.fg3 { display: grid; grid-template-columns: 1fr 1fr 1fr;     gap: 1.1rem; }
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
.field select,
.field textarea {
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
.field textarea {
    height: auto; padding: 0.7rem 0.85rem;
    resize: vertical; min-height: 80px;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    background: white;
}

/* ── RCM toggle row ──────────────────── */
.rcm-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.8rem 1rem;
    background: #fff5f5; border: 1.5px solid #fecaca;
    border-radius: 8px; cursor: pointer;
    transition: background 0.18s;
}
.rcm-row:hover { background: #fee2e2; }
.rcm-row input[type="checkbox"] { display: none; }
.rcm-track {
    width: 34px; height: 18px; border-radius: 20px;
    background: #fecaca; transition: background 0.2s;
    position: relative; flex-shrink: 0;
}
.rcm-track::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 14px; height: 14px; border-radius: 50%;
    background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.rcm-input:checked ~ .rcm-track { background: #dc2626; }
.rcm-input:checked ~ .rcm-track::after { transform: translateX(16px); }
.rcm-label { font-size: 0.82rem; font-weight: 600; color: #991b1b; flex: 1; }
.rcm-hint  { font-size: 0.72rem; color: #dc2626; opacity: 0.8; }

/* ── Summary bar ─────────────────────── */
.summary-bar {
    display: flex; align-items: center;
    justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    padding: 1.25rem 1.6rem;
    background: var(--cream);
    border: 1.5px solid var(--border);
    border-radius: 12px; margin-top: 1.1rem;
}

.summary-items { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }

.sum-item { text-align: center; }
.sum-item .sum-lbl {
    font-size: 0.62rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 2px;
}
.sum-item .sum-val {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 700; color: var(--ink);
}
.sum-item .sum-val.green { color: #059669; }
.sum-item .sum-val.red   { color: #dc2626; }

.sum-divider { width: 1px; height: 36px; background: var(--border); flex-shrink: 0; }

.payable-block { text-align: right; }
.payable-block .p-lbl {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 4px;
}
.payable-block .p-val {
    font-family: 'Fraunces', serif;
    font-size: 1.75rem; font-weight: 700; color: var(--ink);
    line-height: 1;
}
.payable-block .p-formula {
    font-size: 0.7rem; color: var(--ink-mute); margin-top: 4px;
}

/* ── Action bar ──────────────────────── */
.action-bar {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 0.75rem; padding: 1.1rem 1.6rem;
    background: #fdfcfa; border-top: 1.5px solid var(--border-lt);
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
    cursor: pointer; transition: all 0.18s ease;
}
.btn-submit:hover {
    background: var(--accent); border-color: var(--accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(181,98,42,0.3);
}
.btn-submit:active { transform: translateY(0); }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Contractors &rsaquo; Payments</div>
            <h1>New Contractor <em>Bill</em></h1>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <form method="POST" id="contractorForm">
        <?= csrf_field() ?>

        <!-- ── Card 1: Contract & Project ─── -->
        <div class="ch-card">
            <div class="card-head">
                <div class="card-icon blue"><i class="fas fa-file-contract"></i></div>
                <h2>Contract &amp; Project</h2>
            </div>
            <div class="card-body">
                <div class="fg3">
                    <div class="field">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" id="project_id" required onchange="filterWorkOrders()">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Work Order <span class="req">*</span></label>
                        <select name="work_order_id" id="work_order_id" required onchange="loadWorkOrderDetails()">
                            <option value="">— Select Work Order —</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Contractor <span class="req">*</span></label>
                        <select name="contractor_id" id="contractor_id" required>
                            <option value="">— Select Contractor —</option>
                            <?php foreach ($contractors as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <?php if (!empty($c['contractor_type'])): ?>
                                        (<?= htmlspecialchars($c['contractor_type']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Card 2: Work Details ──────── -->
        <div class="ch-card">
            <div class="card-head">
                <div class="card-icon orange"><i class="fas fa-hard-hat"></i></div>
                <h2>Work Description</h2>
            </div>
            <div class="card-body">
                <div class="fg3" style="margin-bottom:1.1rem;">
                    <div class="field">
                        <label>Bill No <span class="req">*</span></label>
                        <input type="text" name="challan_no" required placeholder="Enter Bill No">
                    </div>
                    <div class="field">
                        <label>Work From <span class="req">*</span></label>
                        <input type="date" name="work_from_date" required>
                    </div>
                    <div class="field">
                        <label>Work To <span class="req">*</span></label>
                        <input type="date" name="work_to_date" required>
                    </div>
                    <div class="field">
                        <label>Bill Date <span class="req">*</span></label>
                        <input type="date" name="challan_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Description <span class="req">*</span></label>
                    <textarea name="work_description" rows="3"
                              placeholder="e.g. Completed foundation work for Block A…" required></textarea>
                </div>
            </div>
        </div>

        <!-- ── Card 3: Bill & Taxes ──────── -->
        <div class="ch-card">
            <div class="card-head">
                <div class="card-icon green"><i class="fas fa-rupee-sign"></i></div>
                <h2>Bill Amount &amp; Taxes</h2>
            </div>
            <div class="card-body">

                <div class="fg3" style="margin-bottom:1.25rem;">
                    <div class="field">
                        <label>Basic Bill Amount (<?= CURRENCY_SYMBOL ?>) <span class="req">*</span></label>
                        <input type="number" name="bill_amount" id="bill_amount"
                               placeholder="0.00" step="0.01" required oninput="calculateFinal()">
                    </div>
                    <div class="field">
                        <label>GST (%)</label>
                        <input type="number" id="gst_percentage"
                               placeholder="18.00" step="0.01" value="18.00" oninput="calculateFinal()">
                        <input type="hidden" name="gst_amount" id="gst_amount">
                    </div>
                    <div class="field">
                        <label>TDS (%)</label>
                        <input type="number" id="tds_percentage"
                               placeholder="0.00" step="0.01" value="0.00" oninput="calculateFinal()">
                        <input type="hidden" name="tds_amount" id="tds_amount">
                    </div>
                </div>

                <!-- RCM toggle -->
                <label class="rcm-row" for="is_rcm">
                    <input class="rcm-input" type="checkbox" name="is_rcm" id="is_rcm" onchange="calculateFinal()">
                    <span class="rcm-track"></span>
                    <span class="rcm-label">Reverse Charge Mechanism (RCM)</span>
                    <span class="rcm-hint">Builder pays GST directly to Govt. Contractor receives Basic − TDS only.</span>
                </label>

                <!-- Live summary -->
                <div class="summary-bar">
                    <div class="summary-items">
                        <div class="sum-item">
                            <div class="sum-lbl">Basic</div>
                            <div class="sum-val"><?= CURRENCY_SYMBOL ?> <span id="s_basic">0.00</span></div>
                        </div>
                        <div class="sum-divider"></div>
                        <div class="sum-item">
                            <div class="sum-lbl">GST Added</div>
                            <div class="sum-val green">+ <?= CURRENCY_SYMBOL ?> <span id="s_gst">0.00</span></div>
                        </div>
                        <div class="sum-divider"></div>
                        <div class="sum-item">
                            <div class="sum-lbl">TDS Deducted</div>
                            <div class="sum-val red">− <?= CURRENCY_SYMBOL ?> <span id="s_tds">0.00</span></div>
                        </div>
                    </div>
                    <div class="payable-block">
                        <div class="p-lbl">Net Payable</div>
                        <div class="p-val"><?= CURRENCY_SYMBOL ?> <span id="final_display">0.00</span></div>
                        <div class="p-formula" id="formula_hint">Basic + GST − TDS</div>
                    </div>
                    <input type="hidden" name="final_payable_amount" id="final_payable_amount">
                </div>

            </div>

            <div class="action-bar">
                <a href="index.php" class="btn-ghost">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Save Bill
                </button>
            </div>
        </div>

    </form>
</div>

<script>
const workOrders = <?= json_encode($workOrders) ?>;

/* ── Work order filtering ─────────────── */
function filterWorkOrders() {
    const pid    = document.getElementById('project_id').value;
    const sel    = document.getElementById('work_order_id');
    sel.innerHTML = '<option value="">— Select Work Order —</option>';
    if (!pid) return;
    workOrders
        .filter(wo => wo.project_id == pid && wo.status === 'active')
        .forEach(wo => {
            const opt = document.createElement('option');
            opt.value = wo.id;
            opt.text  = `${wo.work_order_no} – ${wo.title}`;
            sel.appendChild(opt);
        });
}

function loadWorkOrderDetails() {
    const woId = document.getElementById('work_order_id').value;
    if (!woId) return;
    const wo = workOrders.find(w => w.id == woId);
    if (wo) {
        document.getElementById('contractor_id').value  = wo.contractor_id;
        document.getElementById('tds_percentage').value = wo.tds_percentage || 0;
        calculateFinal();
    }
}

/* ── Live calculation ─────────────────── */
function calculateFinal() {
    const basic  = parseFloat(document.getElementById('bill_amount').value)    || 0;
    const gstPct = parseFloat(document.getElementById('gst_percentage').value) || 0;
    const tdsPct = parseFloat(document.getElementById('tds_percentage').value) || 0;
    const isRcm  = document.getElementById('is_rcm').checked;

    const gstAmt = (basic * gstPct) / 100;
    const tdsAmt = (basic * tdsPct) / 100;
    const final  = isRcm ? (basic - tdsAmt) : (basic + gstAmt - tdsAmt);

    document.getElementById('gst_amount').value          = gstAmt.toFixed(2);
    document.getElementById('tds_amount').value          = tdsAmt.toFixed(2);
    document.getElementById('final_payable_amount').value = final.toFixed(2);

    document.getElementById('s_basic').textContent  = basic.toFixed(2);
    document.getElementById('s_gst').textContent    = gstAmt.toFixed(2);
    document.getElementById('s_tds').textContent    = tdsAmt.toFixed(2);
    document.getElementById('final_display').textContent = final.toFixed(2);
    document.getElementById('formula_hint').textContent  = isRcm
        ? 'Basic − TDS (RCM: Builder pays GST)'
        : 'Basic + GST − TDS';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>