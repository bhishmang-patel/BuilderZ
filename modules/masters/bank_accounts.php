<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

$masterService = new MasterService();
$success_msg   = '';
$error_msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF token verification failed.";
    } else {
        try {
            if (isset($_POST['action'])) {
                // Server-side Validation
                $acc_num = $_POST['account_number'] ?? '';
                $ifsc    = strtoupper(trim($_POST['ifsc_code'] ?? ''));

                if (strlen($acc_num) < 9 || strlen($acc_num) > 18) {
                    throw new Exception("Account Number must be between 9 and 18 digits.");
                }
                if (!empty($ifsc)) {
                    if (strlen($ifsc) !== 11 || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
                        throw new Exception("Invalid IFSC Code format. Must be 4 letters, '0', then 6 alphanumeric characters. Example: SBIN0001234");
                    }
                }

                if ($_POST['action'] === 'create') {
                    $data = [
                        'bank_name'       => $_POST['bank_name'],
                        'account_name'    => $_POST['account_name'],
                        'account_number'  => $acc_num,
                        'account_type'    => $_POST['account_type'] ?? 'current',
                        'ifsc_code'       => $ifsc,
                        'branch'          => $_POST['branch'] ?? null,
                        'opening_balance' => floatval($_POST['opening_balance'] ?? 0),
                        'current_balance' => floatval($_POST['opening_balance'] ?? 0),
                        'status'          => $_POST['status'] ?? 'active',
                    ];
                    $masterService->createBankAccount($data);
                    $success_msg = "Bank account added successfully.";
                } elseif ($_POST['action'] === 'edit') {
                    $id   = intval($_POST['id']);
                    $data = [
                        'bank_name'      => $_POST['bank_name'],
                        'account_name'   => $_POST['account_name'],
                        'account_number' => $acc_num,
                        'account_type'   => $_POST['account_type'] ?? 'current',
                        'ifsc_code'      => $ifsc,
                        'branch'         => $_POST['branch'] ?? null,
                        'status'         => $_POST['status'] ?? 'active',
                    ];
                    $masterService->updateBankAccount($id, $data);
                    $success_msg = "Bank account updated successfully.";
                } elseif ($_POST['action'] === 'delete') {
                    $id = intval($_POST['id']);
                    $masterService->deleteBankAccount($id);
                    $success_msg = "Bank account deleted successfully.";
                }
            }
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}

$accounts     = $masterService->getAllBankAccounts();
$page_title   = "Company Bank Accounts";
$current_page = "masters";

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
}
body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1240px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

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
.page-header p { font-size:.85rem; color:var(--ink-mute); margin:.35rem 0 0; }
.btn-add { display:inline-flex; align-items:center; gap:.45rem; padding:.6rem 1.3rem; background:var(--ink); color:white; border:none; border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .18s; }
.btn-add:hover { background:var(--accent); transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); }

/* ── Alerts ───────────────────────── */
.alert { display:flex; align-items:center; gap:.65rem; padding:.8rem 1.1rem; border-radius:9px; font-size:.82rem; font-weight:600; margin-bottom:1.4rem; }
.alert.success { background:var(--green-lt); color:var(--green); border:1.5px solid #6ee7b7; }
.alert.error   { background:var(--red-lt);   color:var(--red);   border:1.5px solid #fca5a5; }

/* ── Card ────────────────────────── */
.card {
    background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden;
    box-shadow:0 1px 4px rgba(26,23,20,.04);
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) .1s both;
}

/* ── Table ────────────────────────── */
.ba-table { width:100%; border-collapse:collapse; font-size:.855rem; }
.ba-table thead tr { background:#eef2fb; border-bottom:1.5px solid var(--border); }
.ba-table thead th { padding:.65rem 1rem; font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft); text-align:left; white-space:nowrap; }
.ba-table thead th.al-r { text-align:right; }
.ba-table tbody tr { border-bottom:1px solid var(--border-lt); transition:background .12s; }
.ba-table tbody tr:last-child { border-bottom:none; }
.ba-table tbody tr:hover { background:#f4f7fd; }
.ba-table tbody tr.row-in { animation:rowIn .24s cubic-bezier(.22,1,.36,1) forwards; }
.ba-table td { padding:.75rem 1rem; vertical-align:middle; }
.ba-table td.al-r { text-align:right; }

/* pills */
.pill { display:inline-block; padding:.22rem .7rem; border-radius:20px; font-size:.68rem; font-weight:800; letter-spacing:.04em; }
.pill.active   { background:var(--green-lt); color:var(--green); border:1px solid #6ee7b7; }
.pill.inactive { background:var(--red-lt); color:var(--red); border:1px solid #fca5a5; }

/* type badge */
.type-badge { font-size:.7rem; color:var(--ink-mute); background:#f0ece5; padding:2px 6px; border-radius:4px; margin-left:6px; font-weight:600; text-transform:uppercase; letter-spacing:0.03em; }

/* action btns */
.act-grp { display:flex; gap:.35rem; justify-content:flex-end; }
.act-btn { width:28px; height:28px; border-radius:6px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; cursor:pointer; transition:all .15s; }
.act-btn:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.act-btn.del:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); }

/* empty */
.empty-state { text-align:center; padding:4rem 1.5rem; }
.empty-state .es-icon { font-size:2.5rem; display:block; margin-bottom:.75rem; color:var(--accent); opacity:.18; }
.empty-state h4 { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink-soft); margin:0 0 .35rem; }
.empty-state p  { font-size:.82rem; color:var(--ink-mute); margin:0; }

/* ── Modal ────────────────────────── */
.m-backdrop { display:none; position:fixed; inset:0; z-index:10000; background:rgba(26,23,20,.45); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:1rem; }
.m-backdrop.open { display:flex; }
.m-box { background:white; border-radius:14px; overflow:hidden; width:100%; max-width:620px; box-shadow:0 24px 48px rgba(26,23,20,.18); animation:mIn .28s cubic-bezier(.22,1,.36,1); max-height:92vh; display:flex; flex-direction:column; }
@keyframes mIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }

/* Custom checkbox toggle */
.tog-input { display: none; }
.tog-track {
    width: 36px; height: 20px; border-radius: 20px;
    background: var(--border); cursor: pointer;
    transition: background 0.2s; position: relative; flex-shrink: 0;
}
.tog-track::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 14px; height: 14px;
    border-radius: 50%; background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.tog-input:checked + .tog-track { background: var(--green); }
.tog-input:checked + .tog-track::after { transform: translateX(16px); }

.toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0.25rem; border-bottom: 1px solid var(--border-lt); }
.toggle-row:last-child { border-bottom: none; }
.toggle-label { font-size: 0.85rem; font-weight: 600; color: var(--ink); }

.m-head { display:flex; align-items:center; justify-content:space-between; padding:1.1rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff; flex-shrink:0; }
.m-head-l { display:flex; align-items:center; gap:.6rem; }
.m-hic { width:28px; height:28px; border-radius:7px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.72rem; background:var(--accent-lt); color:var(--accent); }
.m-head h3 { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin:0; }
.m-close { width:26px; height:26px; border-radius:5px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.85rem; transition:all .15s; }
.m-close:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); }
.m-body { padding:1.4rem 1.5rem; overflow-y:auto; flex:1; }
.m-foot { display:flex; justify-content:flex-end; gap:.65rem; padding:1rem 1.5rem; border-top:1.5px solid var(--border-lt); background:#fafbff; flex-shrink:0; }

/* fields */
.mf { display:flex; flex-direction:column; gap:.28rem; margin-bottom:.9rem; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf label .req { color:var(--red); margin-left:2px; }
.mf input,.mf select { width:100%; height:40px; padding:0 .85rem; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; color:var(--ink); background:#fdfcfa; outline:none; transition:border-color .18s,box-shadow .18s; -webkit-appearance:none; appearance:none; }
.mf input:focus,.mf select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white; }
.mf input:disabled { background:var(--cream); color:var(--ink-mute); cursor:not-allowed; }
.mf input.invalid { border-color:var(--red); background:#fff5f5; }
.mf-err { color:var(--red); font-size:0.7rem; margin-top:2px; display:none; }
.mf-row2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:500px) { .mf-row2{grid-template-columns:1fr;} }

/* radio */
.radio-grp { display:flex; gap:1.5rem; }
.radio-lbl { display:inline-flex; align-items:center; gap:.4rem; font-size:.82rem; font-weight:600; color:var(--ink-soft); cursor:pointer; }
.radio-lbl input[type="radio"] { width:16px; height:16px; margin:0; cursor:pointer; }

/* modal buttons */
.btn-modal { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.3rem; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; border:1.5px solid transparent; transition:all .18s; }
.btn-modal-ghost  { background:white; border-color:var(--border); color:var(--ink-soft); }
.btn-modal-ghost:hover  { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.btn-modal-submit { background:var(--ink); color:white; border-color:var(--ink); }
.btn-modal-submit:hover { background:var(--accent); border-color:var(--accent); transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,88,181,.3); }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Masters &rsaquo; Finance</div>
            <h1>Company <em>Bank Accounts</em></h1>
            <p>Manage your company's bank accounts for payments and receipts.</p>
        </div>
        <button class="btn-add" onclick="openModal('add')">
            <i class="fas fa-plus"></i> Add Account
        </button>
    </div>

    <!-- ── Alerts ──────────────────── -->
    <?php if ($success_msg): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- ── Table ───────────────────── -->
    <div class="card">
        <div style="overflow-x:auto;">
            <table class="ba-table">
                <thead>
                    <tr>
                        <th>Bank Name</th>
                        <th>Account</th>
                        <th>Account Details</th>
                        <th>IFSC / Branch</th>
                        <th class="al-r">Current Balance</th>
                        <th>Status</th>
                        <th class="al-r"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accounts)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <span class="es-icon"><i class="fas fa-university"></i></span>
                                <h4>No bank accounts yet</h4>
                                <p>Add your first company bank account to get started.</p>
                            </div>
                        </td></tr>
                    <?php else:
                        foreach ($accounts as $i => $acc):
                    ?>
                        <tr class="row-in" style="animation-delay:<?= $i*20 ?>ms;">
                            <td><strong><?= htmlspecialchars($acc['bank_name']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($acc['account_name']) ?>
                                <?php if(!empty($acc['account_type'])): ?>
                                    <span class="type-badge"><?= strtoupper($acc['account_type']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-family:monospace;font-size:.85rem;color:var(--ink-soft)"><?= htmlspecialchars($acc['account_number']) ?></code></td>
                            <td>
                                <div style="font-size:.82rem;">
                                    <strong><?= htmlspecialchars($acc['ifsc_code']) ?></strong><br>
                                    <span style="color:var(--ink-mute)"><?= htmlspecialchars($acc['branch']) ?></span>
                                </div>
                            </td>
                            <td class="al-r">
                                <strong style="font-family:'Fraunces',serif;font-weight:700;color:var(--ink)">
                                    ₹ <?= number_format($acc['current_balance'], 2) ?>
                                </strong>
                            </td>
                            <td><span class="pill <?= $acc['status'] ?>"><?= ucfirst($acc['status']) ?></span></td>
                            <td class="al-r">
                                <div class="act-grp">
                                    <button class="act-btn" onclick='openModal("edit",<?= json_encode($acc) ?>)' title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="act-btn del" onclick="confirmDelete(<?= $acc['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="accountModal" class="m-backdrop">
    <div class="m-box">
        <form method="POST" id="accountForm" onsubmit="return validateForm()">
            <div class="m-head">
                <div class="m-head-l">
                    <div class="m-hic"><i class="fas fa-university"></i></div>
                    <h3 id="modalTitle">Add Bank Account</h3>
                </div>
                <button type="button" class="m-close" onclick="closeModal()">×</button>
            </div>
            <div class="m-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="accountId">

                <div class="mf-row2">
                    <div class="mf">
                        <label>Bank Name <span class="req">*</span></label>
                        <input type="text" id="bank_name" name="bank_name" required placeholder="e.g. HDFC Bank">
                    </div>
                    <div class="mf">
                        <label>Account Name <span class="req">*</span></label>
                        <input type="text" id="account_name" name="account_name" required placeholder="e.g. Primary Current">
                    </div>
                </div>

                    <label>Account Type <span class="req">*</span></label>
                    <div style="border:1.5px solid var(--border-lt); border-radius:8px; padding:0.5rem 1rem; margin-bottom:0.5rem;">
                        <div class="toggle-row">
                            <span class="toggle-label">Current Account</span>
                            <input class="tog-input" type="radio" name="account_type" id="at_current" value="current" checked>
                            <label class="tog-track" for="at_current"></label>
                        </div>
                        <div class="toggle-row">
                            <span class="toggle-label">Savings Account</span>
                            <input class="tog-input" type="radio" name="account_type" id="at_savings" value="savings">
                            <label class="tog-track" for="at_savings"></label>
                        </div>
                        <div class="toggle-row">
                            <span class="toggle-label">Overdraft / CC</span>
                            <input class="tog-input" type="radio" name="account_type" id="at_od" value="od">
                            <label class="tog-track" for="at_od"></label>
                        </div>
                    </div>

                <div class="mf-row2">
                    <div class="mf">
                        <label>Account Number <span class="req">*</span></label>
                        <input type="text" id="account_number" name="account_number" required placeholder="9-18 digits" minlength="9" maxlength="18">
                        <div class="mf-err" id="err_account_number">Must be 9-18 digits</div>
                    </div>
                    <div class="mf">
                        <label>IFSC Code <span class="req">*</span></label>
                        <input type="text" id="ifsc_code" name="ifsc_code" required placeholder="Exactly 11 chars" maxlength="11" oninput="this.value = this.value.toUpperCase()">
                        <div class="mf-err" id="err_ifsc_code">Must be exactly 11 alphanumeric chars</div>
                    </div>
                </div>

                <div class="mf-row2">
                    <div class="mf">
                        <label>Branch Name</label>
                        <input type="text" id="branch" name="branch" placeholder="e.g. Andheri East">
                    </div>
                    <div class="mf" id="openingBalanceGroup">
                        <label>Opening Balance</label>
                        <input type="number" step="0.01" id="opening_balance" name="opening_balance" value="0.00">
                    </div>
                </div>

                <div class="mf">
                    <label>Status</label>
                    <div class="toggle-row">
                        <span class="toggle-label">Active</span>
                        <input class="tog-input" type="checkbox" name="status" id="status_toggle" value="active" checked>
                        <label class="tog-track" for="status_toggle"></label>
                    </div>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn-modal btn-modal-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-submit"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
const modal        = document.getElementById('accountModal');
const form         = document.getElementById('accountForm');
const modalTitle   = document.getElementById('modalTitle');
const formAction   = document.getElementById('formAction');
const accountId    = document.getElementById('accountId');
const openingBalGrp= document.getElementById('openingBalanceGroup');

function openModal(mode, data = null) {
    modal.classList.add('open');
    clearErrors();
    if (mode === 'edit' && data) {
        modalTitle.textContent = 'Edit Bank Account';
        formAction.value = 'edit';
        accountId.value = data.id;
        document.getElementById('bank_name').value = data.bank_name;
        document.getElementById('account_name').value = data.account_name;
        document.getElementById('account_number').value = data.account_number;
        document.getElementById('ifsc_code').value = data.ifsc_code || '';
        document.getElementById('branch').value = data.branch || '';
        document.getElementById('opening_balance').value = data.opening_balance;
        document.getElementById('opening_balance').disabled = true;
        
        // Status Toggle
        const statusToggle = document.getElementById('status_toggle');
        statusToggle.checked = (data.status === 'active');
        
        // Type radios
        const radiosType = document.getElementsByName('account_type');
        const typeVal = data.account_type || 'current';
        for(let r of radiosType) { if(r.value === typeVal) r.checked = true; }

    } else {
        modalTitle.textContent = 'Add Bank Account';
        formAction.value = 'create';
        accountId.value = '';
        form.reset();
        document.getElementById('opening_balance').disabled = false;
        // Default Status
        document.getElementById('status_toggle').checked = true;
        document.querySelector('input[name="account_type"][value="current"]').checked = true;
    }
}

function closeModal() { modal.classList.remove('open'); }

function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this bank account?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// ── Validation Logic ───────────────────
function showError(id, msg) {
    const el = document.getElementById(id);
    const err = document.getElementById('err_' + id);
    if(el) el.classList.add('invalid');
    if(err) { err.style.display = 'block'; err.textContent = msg; }
}

function clearErrors() {
    const inputs = document.querySelectorAll('.mf input');
    inputs.forEach(i => i.classList.remove('invalid'));
    const errs = document.querySelectorAll('.mf-err');
    errs.forEach(e => e.style.display = 'none');
}

function validateForm() {
    clearErrors();
    let valid = true;

    // 1. Account Number: 9-18 digits
    const accNum = document.getElementById('account_number').value.trim();
    if (accNum.length < 9 || accNum.length > 18 || !/^\d+$/.test(accNum)) {
        showError('account_number', 'Must be 9-18 digits');
        valid = false;
    }

    // 2. IFSC Code: Exactly 11 alphanumeric (Strict Format)
    const ifsc = document.getElementById('ifsc_code').value.trim().toUpperCase();
    if (ifsc.length > 0) { // If entered
        const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
        if (!ifscRegex.test(ifsc)) {
            showError('ifsc_code', "Invalid Format. Must start with 4 letters, then '0', then 6 alphanumeric. e.g. SBIN0001234");
            valid = false;
        }
    } else {
        // Required field check is handled by browser 'required' attribute, but let's be safe
        showError('ifsc_code', 'IFSC Code is required');
        valid = false;
    }

    return valid;
}

modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>