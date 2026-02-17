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

$db            = Database::getInstance();
$masterService = new MasterService();
$page_title    = 'Create Vendor Bill';
$current_page  = 'vendors';
$vendors       = $masterService->getAllParties(['type' => 'vendor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        header("Location: create_bill.php"); exit;
    }
    try {
        $db->beginTransaction();
        $vendor_id      = intval($_POST['vendor_id']);
        $bill_no        = sanitize($_POST['bill_no']);
        $bill_date      = $_POST['bill_date'];
        $amount         = floatval($_POST['amount']);
        $tax_amount     = floatval($_POST['tax_amount']     ?? 0);
        $taxable_amount = floatval($_POST['taxable_amount'] ?? 0);

        if ($vendor_id <= 0)   throw new Exception("Please select a vendor.");
        if (empty($bill_no))   throw new Exception("Bill Number is required.");
        if (empty($bill_date)) throw new Exception("Bill Date is required.");
        if ($amount <= 0)      throw new Exception("Grand Total must be greater than zero.");

        $dup = $db->query("SELECT id FROM bills WHERE bill_no = ? AND party_id = ?", [$bill_no, $vendor_id])->fetch();
        if ($dup) throw new Exception("Bill Number '$bill_no' already exists for this vendor.");

        $file_path = null;
        if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/bills/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext      = pathinfo($_FILES['bill_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'bill_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['bill_file']['tmp_name'], $uploadDir . $fileName))
                $file_path = 'uploads/bills/' . $fileName;
        }

        $bill_data = [
            'bill_no'        => $bill_no,
            'bill_date'      => $bill_date,
            'party_id'       => $vendor_id,
            'amount'         => $amount,
            'tax_amount'     => $tax_amount,
            'taxable_amount' => $taxable_amount,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'file_path'      => $file_path,
            'created_by'     => $_SESSION['user_id'],
        ];

        $bill_id = $db->insert('bills', $bill_data);
        if (!$bill_id) throw new Exception("Failed to save bill. Please try again.");

        // Process Itemized Bill Entries
        $materials_json = $_POST['materials_json'] ?? '[]';
        $bill_items = json_decode($materials_json, true);

        if (empty($bill_items)) {
            throw new Exception("Please add at least one material item to the bill.");
        }

        $linked_challan_ids = [];

        foreach ($bill_items as $item) {
            $cid = intval($item['challan_id']);
            $mid = intval($item['material_id']);
            $rate = floatval($item['rate']);
            
            if ($cid <= 0 || $mid <= 0 || $rate <= 0) continue;

            $db->query(
                "UPDATE challan_items SET rate = ? WHERE challan_id = ? AND material_id = ?", 
                [$rate, $cid, $mid]
            );

            $linked_challan_ids[] = $cid;
        }

        $linked_challan_ids = array_unique($linked_challan_ids);
        foreach ($linked_challan_ids as $cid) {
            $db->update('challans', ['bill_id' => $bill_id], 'id = ?', [$cid]);
        }

        logAudit('create', 'bills', $bill_id, null, $bill_data);
        
        // ── Notification Trigger ──
        require_once __DIR__ . '/../../includes/NotificationService.php';
        $ns = new NotificationService();
        $notifTitle = "New Vendor Bill Created";
        $notifMsg   = "Bill #{$bill_no} for Vendor ID {$vendor_id} has been created.";
        $notifLink  = BASE_URL . "modules/vendors/view_bill.php?id=" . $bill_id;
        
        // Notify current user (or Admin user ID 1)
        $ns->create($_SESSION['user_id'], $notifTitle, $notifMsg, 'success', $notifLink);
        if ($_SESSION['user_id'] != 1) {
             $ns->create(1, $notifTitle, $notifMsg . " (Created by " . $_SESSION['username'] . ")", 'info', $notifLink);
        }

        $db->commit();
        setFlashMessage('success', "Vendor Bill {$bill_no} created successfully.");
        header("Location: view_bill.php?id=$bill_id"); exit;

    } catch (Exception $e) {
        $db->rollback();
        $formError = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

<style>
/* ── Reset & root ─────────────────────────────── */
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
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw { max-width: 1000px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

/* ── Animations ───────────────────────────────── */
@keyframes hdrIn  { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
@keyframes cardIn { from { opacity:0; transform:translateY(18px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-8px);  } to { opacity:1; transform:translateX(0); } }

/* ── Page header ──────────────────────────────── */
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

/* ── Error banner ─────────────────────────────── */
.err-banner {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.9rem 1.2rem; margin-bottom: 1.5rem;
    background: #fee2e2; border: 1.5px solid #fecaca;
    border-radius: 9px; color: #991b1b; font-size: 0.875rem;
}

/* ── Cards ────────────────────────────────────── */
.card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden; margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0; animation: cardIn 0.42s cubic-bezier(0.22,1,0.36,1) both;
}
.card.c1 { animation-delay: 0.10s; }
.card.c2 { animation-delay: 0.19s; }
.card.c3 { animation-delay: 0.28s; }

.card-head {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.ch-ic {
    width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.78rem;
}
.ch-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.ch-ic.green  { background: var(--green-lt);  color: var(--green); }
.ch-ic.orange { background: #fdf8f3; color: #b5622a; border: 1px solid #e0c9b5; }
.ch-ic.purple { background: #ede9fe; color: #7c3aed; }
.card-head h2 {
    font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
    color: var(--ink); margin: 0; flex: 1; display: flex; align-items: center; gap: 0.5rem;
}
.step-tag {
    font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream); border: 1px solid var(--border);
    padding: 0.18rem 0.6rem; border-radius: 20px;
}
/* item count badge inside h2 */
.item-count {
    display: none;
    font-size: 0.62rem; font-weight: 800; padding: 0.15rem 0.55rem;
    border-radius: 20px; background: var(--accent); color: white;
    font-family: 'DM Sans', sans-serif; letter-spacing: 0.04em;
}
.item-count.show { display: inline-block; }

.card-body { padding: 1.5rem; }

/* ── Section labels ──────────────────────────── */
.sec {
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.13em; text-transform: uppercase;
    color: var(--ink-mute); margin: 1.35rem 0 0.8rem;
    padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.38rem;
}
.sec:first-child { margin-top: 0; }
.sec .opt { font-size: 0.6rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--ink-mute); margin-left: 0.35rem; }

/* ── Grids ────────────────────────────────────── */
.g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
@media (max-width: 640px) { .g2, .g3 { grid-template-columns: 1fr; } }

/* ── Fields ───────────────────────────────────── */
.f { display: flex; flex-direction: column; gap: 0.35rem;}
.f label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.f label .req { color: #dc2626; margin-left: 2px; }
.f input, .f select {
    width: 100%; height: 40px; padding: 0 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    -webkit-appearance: none; appearance: none;
}
.f select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.85rem center; padding-right: 2.2rem;
}
.f input:focus, .f select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.11); background: white; }

/* challan note */
.challan-note {
    margin-top: 0.6rem; padding: 0.6rem 0.9rem; width: 50%;
    background: var(--accent-lt); border: 1.5px solid var(--accent-md); border-radius: 7px;
    font-size: 0.75rem; color: var(--accent-dk);
    display: flex; align-items: flex-start; gap: 0.5rem; line-height: 1.5;
}
.challan-note i { margin-top: 2px; flex-shrink: 0; }

/* ── Select2 ─────────────────────────────────── */
.select2-container { width: 100% !important; }
.select2-container--default .select2-selection--single,
.select2-container--default .select2-selection--multiple {
    height: 40px !important; border: 1.5px solid var(--border) !important;
    border-radius: 8px !important; background: #fdfcfa !important;
    display: flex; align-items: center; font-family: 'DM Sans', sans-serif;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.select2-container--default 
.select2-selection--multiple 
.select2-selection__choice__remove {
    display: none !important;
    margin: 0 !important;
}
.select2-container--default 
.select2-selection--multiple 
.select2-selection__choice {
    padding-left: 8px !important; 
    display: inline-flex !important;
    align-items: center;
    flex-direction: row-reverse;  
    gap: 6px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px !important; padding-left: 0.85rem !important; font-size: 0.875rem; color: var(--ink); }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; right: 6px !important; }
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: var(--accent) !important; box-shadow: 0 0 0 3px rgba(42,88,181,0.11) !important; background: white !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__rendered { padding: 3px 8px; display: flex; flex-wrap: wrap; gap: 3px; align-items: center; min-height: 36px; }
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: var(--accent-lt) !important; border: 1px solid var(--accent-md) !important;
    color: var(--accent-dk) !important; border-radius: 5px !important;
    padding: 1px 6px !important; font-size: 0.75rem !important; font-weight: 600 !important;
}
.select2-dropdown { border: 1.5px solid var(--border) !important; border-radius: 10px !important; box-shadow: 0 12px 30px rgba(26,23,20,0.1) !important; overflow: hidden; }
.select2-results__option { font-family: 'DM Sans', sans-serif; font-size: 0.875rem; padding: 0.55rem 0.85rem; }
.select2-results__option--highlighted[aria-selected] { background: var(--accent-bg) !important; color: var(--accent) !important; }
.select2-search--dropdown .select2-search__field { border: 1.5px solid var(--border) !important; border-radius: 7px !important; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; padding: 0.4rem 0.75rem !important; outline: none; }

/* ── Financials ──────────────────────────────── */
.fin-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
@media (max-width: 640px) { .fin-grid { grid-template-columns: 1fr; } }
.fi-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.35rem; display: block; }
.fi-label .req { color: #dc2626; margin-left: 2px; }
.fin-input-wrap input {
    width: 100%; height: 40px; padding: 0 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none; transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
}
.fin-input-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.11); background: white; }
.fin-input-wrap.total-wrap input { font-weight: 700; font-family: 'Fraunces', serif; font-size: 1rem; }
.summary-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;
    padding: 1.1rem 1.4rem; background: var(--cream); border: 1.5px solid var(--border); border-radius: 11px;
}
.sb-items { display: flex; align-items: center; gap: 1.4rem; flex-wrap: wrap; }
.sb-item .si-lbl { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 3px; }
.sb-item .si-val { font-family: 'Fraunces', serif; font-weight: 600; font-size: 0.9rem; color: var(--ink); }
.sb-item .si-val.plus { color: var(--green); }
.sb-divider { width: 1px; height: 32px; background: var(--border); flex-shrink: 0; }
.sb-grand { text-align: right; }
.sb-grand .sg-lbl { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 4px; }
.sb-grand .sg-val { font-family: 'Fraunces', serif; font-size: 1.85rem; font-weight: 700; color: var(--ink); line-height: 1; }

/* ── Drop zone ───────────────────────────────── */
.drop-zone {
    position: relative; border: 2px dashed var(--border); border-radius: 10px;
    padding: 2rem 1.5rem; text-align: center; cursor: pointer;
    transition: border-color 0.2s, background 0.2s; background: #fdfcfa;
}
.drop-zone:hover, .drop-zone.active { border-color: var(--accent); background: var(--accent-lt); }
.drop-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.dz-icon { font-size: 1.75rem; color: var(--ink-mute); margin-bottom: 0.65rem; display: block; transition: color 0.2s; }
.drop-zone:hover .dz-icon, .drop-zone.active .dz-icon { color: var(--accent); }
.dz-title { font-size: 0.9rem; font-weight: 600; color: var(--ink-soft); margin-bottom: 0.22rem; }
.dz-sub   { font-size: 0.73rem; color: var(--ink-mute); }
.dz-file  { display: none; margin-top: 0.75rem; padding: 0.45rem 0.9rem; background: white; border: 1.5px solid var(--accent-md); border-radius: 6px; font-size: 0.78rem; font-weight: 600; color: var(--accent); align-items: center; gap: 0.4rem; width: fit-content; margin-inline: auto; }

/* ══════════════════════════════════════
   MATERIAL ITEMS CARD
══════════════════════════════════════ */

/* ── iOS Toggle ──────────────────────── */
.manual-toggle {
    display: inline-flex; align-items: center; gap: 0.75rem;
    margin-bottom: 1.1rem; padding: 0.5rem 0.75rem;
    background: var(--cream); border: 1.5px solid var(--border); border-radius: 7px;
    cursor: pointer; transition: border-color 0.18s, background 0.18s;
}
.manual-toggle:hover { border-color: var(--accent); background: var(--accent-lt); }
.manual-toggle > label:not(.toggle-switch) { 
    font-size: 0.75rem; font-weight: 500; color: var(--ink-soft); 
    cursor: pointer; user-select: none; white-space: nowrap; 
    line-height: 1; margin: 0;
    display: flex; align-items: center; height: 24px; /* Force match switch height */
}
.manual-toggle:has(input:checked) { border-color: var(--accent); background: var(--accent-lt); }
.manual-toggle:has(input:checked) > label:not(.toggle-switch) { color: var(--accent); font-weight: 600; }
.toggle-switch { position: relative; display: flex; align-items: center; justify-content: center; width: 44px; height: 24px; flex-shrink: 0; margin: 0; padding: 0; line-height: normal; cursor: pointer; }
.toggle-switch input { position: absolute; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.slider {
    position: absolute; top: 50%; left: 0; width: 100%; height: 24px; transform: translateY(-50%);
    background-color: #e5e5e5; border: 1.5px solid #d1d1d1; border-radius: 24px; transition: .3s;
}
.slider:before {
    position: absolute; content: ""; height: 18px; width: 18px; top: 50%; left: 2px;
    transform: translateY(-50%); background-color: white; transition: .3s; border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input:checked + .slider { background-color: var(--accent); border-color: var(--accent); }
input:checked + .slider:before { transform: translate(19px, -50%); }


/* ── Add strip ───────────────────────── */
.add-strip {
    display: grid;
    grid-template-columns: 1.1fr 1.8fr 0.9fr 0.9fr 0.9fr auto;
    gap: 0.75rem; align-items: end;
    padding: 1.1rem 1.25rem;
    background: #fafbff;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    margin-bottom: 1.5rem;
}
@media (max-width: 860px) {
    .add-strip { grid-template-columns: 1fr 1fr; }
    .btn-add { grid-column: 1 / -1; }
}

/* strip fields — same height/style as .f but scoped to strip */
.sf { display: flex; flex-direction: column; gap: 0.32rem; }
.sf label { font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.sf input, .sf select {
    width: 100%; height: 38px; padding: 0 0.75rem;
    border: 1.5px solid var(--border); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    -webkit-appearance: none; appearance: none;
}
.sf select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.7rem center; padding-right: 2rem;
}
.sf input:focus, .sf select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.11); background: white; }
.sf input[readonly] { background: var(--cream); color: var(--ink-mute); cursor: not-allowed; border-style: dashed; }
.sf input[readonly]:focus { border-color: var(--border); box-shadow: none; }

/* autocomplete */
.autocomplete-wrapper { position: relative; }
.autocomplete-list {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: white; border: 1.5px solid var(--border); border-radius: 9px;
    list-style: none; padding: 0.3rem; margin: 0;
    z-index: 200; display: none; max-height: 220px; overflow-y: auto;
    box-shadow: 0 12px 28px rgba(26,23,20,0.1);
}
.autocomplete-list.show { display: block; }
.autocomplete-list li {
    display: flex; align-items: flex-start; gap: 0.55rem;
    padding: 0.52rem 0.7rem; cursor: pointer; border-radius: 6px;
    font-size: 0.83rem; color: var(--ink); transition: background 0.1s;
    line-height: 1.4;
}
.autocomplete-list li:hover { background: var(--accent-lt); color: var(--accent); }
.ac-ic {
    width: 20px; height: 20px; border-radius: 4px; flex-shrink: 0; margin-top: 1px;
    background: var(--accent-lt); color: var(--accent);
    display: flex; align-items: center; justify-content: center; font-size: 0.58rem;
}
.ac-sub { font-size: 0.68rem; color: var(--ink-mute); margin-top: 1px; }

/* add button */
.btn-add {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    height: 38px; padding: 0 1.15rem; align-self: end;
    background: var(--accent); color: white; border: none; border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; white-space: nowrap; transition: all 0.18s;
}
.btn-add:hover { background: var(--accent-dk); transform: translateY(-1px); box-shadow: 0 3px 10px rgba(42,88,181,0.3); }
.btn-add:active { transform: translateY(0); }

/* ── Materials table ─────────────────── */
.mat-table-wrap { border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; }
.mat-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
.mat-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.mat-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.mat-table thead th.al-r { text-align: right; }
.mat-table thead th.al-c { text-align: center; }
.mat-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.mat-table tbody tr:last-child { border-bottom: none; }
.mat-table tbody tr:hover { background: #f4f7fd; }
.mat-table tbody td { padding: 0.78rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.mat-table tbody td.al-r { text-align: right; }
.mat-table tbody td.al-c { text-align: center; }
.mat-table tbody tr.row-in { animation: rowIn 0.28s cubic-bezier(0.22,1,0.36,1) forwards; }

.row-num {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--cream); border: 1px solid var(--border);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 700; color: var(--ink-soft);
}
.challan-pill {
    display: inline-block; padding: 0.18rem 0.6rem; border-radius: 20px;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.04em;
    background: var(--accent-lt); color: var(--accent-dk); border: 1px solid var(--accent-md);
}
.unit-pill {
    display: inline-block; padding: 0.18rem 0.5rem; border-radius: 20px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
    background: var(--cream); color: var(--ink-soft); border: 1px solid var(--border);
}
.mat-name { font-weight: 700; color: var(--ink); font-size: 0.875rem; }
.num-cell { font-family: 'Fraunces', serif; font-weight: 700; color: var(--ink); }

.rate-input {
    max-width: 75px; height: 34px; padding: 0 0.5rem; margin-left: auto;
    border: 1.5px solid var(--border); border-radius: 6px; display: block;
    font-family: 'DM Sans', sans-serif; font-size: 0.85rem; color: var(--ink);
    background: white; outline: none; transition: border-color 0.15s; text-align: right;
}
.rate-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.11); }
/* Hide spin buttons */
.rate-input::-webkit-outer-spin-button,
.rate-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.rate-input { -moz-appearance: textfield; }

.btn-remove {
    width: 26px; height: 26px; border-radius: 5px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-mute);
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.65rem; transition: all 0.15s;
}
.btn-remove:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

.empty-row td { text-align: center; padding: 3rem 1rem; color: var(--ink-mute); font-size: 0.875rem; }
.empty-row .ei { font-size: 2rem; display: block; margin-bottom: 0.6rem; color: var(--accent); opacity: 0.2; }

/* ── Mat summary footer ──────────────── */
.mat-summary {
    display: flex; align-items: center; justify-content: flex-end; gap: 1.5rem;
    padding: 0.85rem 1.25rem; border-top: 1.5px solid var(--border-lt); background: #fafbff;
}
.ms-item { text-align: right; }
.ms-lbl { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.ms-val { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 700; color: var(--ink); margin-top: 1px; }
.ms-div { width: 1px; height: 30px; background: var(--border); }

/* ── Action bar ──────────────────────────────── */
.action-bar {
    display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;
    padding: 1rem 1.5rem; background: #fafbff; border-top: 1.5px solid var(--border-lt);
}
.btn-cancel {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.6rem 1.2rem; border: 1.5px solid var(--border);
    background: white; color: var(--ink-soft); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 500;
    text-decoration: none; transition: all 0.18s; cursor: pointer;
}
.btn-cancel:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }
.btn-save {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.6rem 1.65rem; background: var(--ink); color: white;
    border: 1.5px solid var(--ink); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
}
.btn-save:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }
.btn-save:active { transform: translateY(0); }
</style>

<div class="pw">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Vendors &rsaquo; Bills</div>
            <h1>New Vendor <em>Bill</em></h1>
        </div>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Vendors</a>
    </div>

    <?php if (!empty($formError)): ?>
        <div class="err-banner">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($formError) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="billForm">
        <?= csrf_field() ?>

        <!-- ── Card 1: Vendor & Reference ── -->
        <div class="card c1">
            <div class="card-head">
                <div class="ch-ic blue"><i class="fas fa-truck"></i></div>
                <h2>Vendor &amp; Reference</h2>
                <span class="step-tag">Step 1 of 3</span>
            </div>
            <div class="card-body">

                <div class="sec"><i class="fas fa-user-tag"></i> Select Vendor</div>
                <div class="f" style="width:50%;">
                    <label>Vendor <span class="req">*</span></label>
                    <select name="vendor_id" id="vendor_id" required>
                        <option value="">Search vendor…</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars($v['name']) ?>
                                <?php if (!empty($v['vendor_type'])): ?>(<?= ucfirst($v['vendor_type']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sec" style="margin-top:1.5rem;"><i class="fas fa-file-alt"></i> Bill Reference</div>
                <div class="g2" style="max-width:520px;">
                    <div class="f">
                        <label>Bill Number <span class="req">*</span></label>
                        <input type="text" name="bill_no" required placeholder="e.g. INV-2025-001"
                               value="<?= htmlspecialchars($_POST['bill_no'] ?? '') ?>">
                    </div>
                    <div class="f">
                        <label>Bill Date <span class="req">*</span></label>
                        <input type="date" name="bill_date" required
                               value="<?= htmlspecialchars($_POST['bill_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Card 2: Challans & Attachment ── -->
        <div class="card c2">
            <div class="card-head">
                <div class="ch-ic orange"><i class="fas fa-link"></i></div>
                <h2>Challans &amp; Attachment</h2>
                <span class="step-tag">Step 3 of 3</span>
            </div>
            <div class="card-body">

                <div class="sec"><i class="fas fa-boxes"></i> Link Challans <span class="opt">optional</span></div>
                <div class="f" style="width:50%;">
                    <label>Challans</label>
                    <select name="challan_ids[]" id="challan_ids" multiple disabled></select>
                </div>
                <div class="challan-note">
                    <i class="fas fa-info-circle"></i>
                    Linking challans lets you trace which material deliveries are covered by this bill. Select a vendor above to load unbilled challans.
                </div>

                <div class="sec" style="margin-top:1.5rem;"><i class="fas fa-paperclip"></i> Attach Document <span class="opt">optional</span></div>
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="bill_file" id="billFile"
                           accept=".pdf,.jpg,.jpeg,.png" onchange="handleFile(this)">
                    <span class="dz-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                    <div class="dz-title">Click to upload or drag &amp; drop</div>
                    <div class="dz-sub">PDF, JPG, PNG · max 10 MB</div>
                    <div class="dz-file" id="dzFile"><i class="fas fa-file"></i> <span id="dzFileName"></span></div>
                </div>

            </div>
        </div>

        <!-- ── Card 3: Material Items ── -->
        <div class="card c3">
            <div class="card-head">
                <div class="ch-ic green"><i class="fas fa-boxes"></i></div>
                <h2>
                    Material Items
                    <span class="item-count" id="item_badge">0</span>
                </h2>
                <span class="step-tag">Step 2 of 3</span>
            </div>
            <div class="card-body">

                <div class="sec"><i class="fas fa-plus-circle"></i> Add Item</div>

                <!-- Manual toggle (iOS Style) -->
                <div class="manual-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" id="manual_mode" onchange="toggleManualMode()">
                        <span class="slider"></span>
                    </label>
                    <label for="manual_mode">Enter Manually (Unlock fields)</label>
                </div>


                <!-- Add strip -->
                <div class="add-strip">
                    <div style="grid-column:1/-1; display:flex; gap:0.5rem; margin-bottom:0.5rem; flex-wrap:wrap;">
                        <style>
                            .add-strip { grid-template-columns: 1fr 1.6fr 0.7fr 0.7fr 0.7fr 0.7fr auto !important; }
                            @media (max-width: 900px) { .add-strip { grid-template-columns: 1fr 1fr !important; } }
                        </style>
                    </div>  

                    <div class="sf">
                        <label>Challan No</label>
                        <input type="text" id="material_challan" placeholder="Auto-filled" readonly>
                        <input type="hidden" id="item_challan_id">
                    </div>
                    <div class="sf">
                        <label>Material Name</label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="material_name_input"
                                   placeholder="Search material…" autocomplete="off">
                            <ul id="material_suggestions" class="autocomplete-list"></ul>
                        </div>
                        <input type="hidden" id="material_id_hidden">
                    </div>
                    <div class="sf">
                        <label>Unit</label>
                        <select id="material_unit">
                            <option value="">—</option>
                            <option value="kg">Kg</option>
                            <option value="ton">Ton</option>
                            <option value="bag">Bag</option>
                            <option value="cft">CFT</option>
                            <option value="sqft">Sqft</option>
                            <option value="nos">Nos</option>
                            <option value="ltr">Ltr</option>
                            <option value="brass">Brass</option>
                            <option value="bundle">Bundle</option>
                        </select>
                    </div>
                    <div class="sf">
                        <label>Quantity</label>
                        <input type="number" id="material_quantity" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="sf">
                        <label>Rate (<?= CURRENCY_SYMBOL ?>)</label>
                        <input type="number" id="material_rate" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="sf">
                        <label>GST %</label>
                        <select id="material_gst">
                            <option value="0">0%</option>
                            <option value="6">6%</option>
                            <option value="12">12%</option>
                            <option value="18">18%</option>
                            <option value="28">28%</option>
                        </select>
                    </div>
                    <button type="button" class="btn-add" onclick="addMaterial()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>

                <!-- Table section label -->
                <div class="sec"><i class="fas fa-list"></i> Item List</div>

                <!-- Table -->
                <div class="mat-table-wrap">
                    <table class="mat-table">
                        <thead>
                            <tr>
                                <th class="al-c" style="width:44px;">Sr.No</th>
                                <th>Challan</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th class="al-c">GST</th>
                                <th class="al-r">Qty</th>
                                <th class="al-r">Rate (<?= CURRENCY_SYMBOL ?>)</th>
                                <th class="al-r">Amount (<?= CURRENCY_SYMBOL ?>)</th>
                                <th class="al-c" style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="materials_tbody">
                            <tr class="empty-row">
                                <td colspan="9">
                                    <span class="ei"><i class="fas fa-cubes"></i></span>
                                    No items added. Use the form above to add materials.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Summary footer -->
                    <div class="mat-summary" id="mat_summary" style="display:none;">
                        <div class="ms-item"><div class="ms-lbl">Items</div><div class="ms-val" id="sum_count">0</div></div>
                        <div class="ms-div"></div>
                        <div class="ms-item"><div class="ms-lbl">Total Qty</div><div class="ms-val" id="sum_qty">0.00</div></div>
                        <div class="ms-div"></div>
                        <div class="ms-item"><div class="ms-lbl">Base Amount</div><div class="ms-val" id="sum_taxable"><?= CURRENCY_SYMBOL ?> 0.00</div></div> <!-- Added Base Amount -->
                        <div class="ms-div"></div>
                        <div class="ms-item"><div class="ms-lbl">GST Amount</div><div class="ms-val" id="sum_tax"><?= CURRENCY_SYMBOL ?> 0.00</div></div>   <!-- Added GST Amount -->
                        <div class="ms-div"></div>
                        <div class="ms-item"><div class="ms-lbl">Total Amount</div><div class="ms-val" id="sum_amount"><?= CURRENCY_SYMBOL ?> 0.00</div></div>
                    </div>
                </div>

            </div>
            <input type="hidden" name="materials_json" id="materials_json">
            <input type="hidden" name="amount" id="amount" value="0">
            <input type="hidden" name="tax_amount" id="tax_amount" value="0">
            <input type="hidden" name="taxable_amount" id="taxable_amount" value="0">

            <div class="action-bar">
                <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Save Bill</button>
            </div>
        </div>

    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    /* ── Select2 init ─────────────────── */
    $('#vendor_id').select2({ placeholder: 'Search vendor…' }).on('select2:open', function () {
        setTimeout(function () {
            document.querySelector('.select2-container--open .select2-search__field')
                .setAttribute('placeholder', 'Type to search vendor...');
        }, 0);
    });
    $('#challan_ids').select2({ placeholder: 'Select challans', allowClear: false });

    /* ── Load challans on vendor change ── */
    $('#vendor_id').on('change', function () {
        const vid = $(this).val();
        const $ch = $('#challan_ids');

        $ch.prop('disabled', true).empty().append('<option>Loading…</option>');
        if (!vid) return;

        $.getJSON(`../bills/get_unbilled_challans.php?vendor_id=${vid}`)
            .done(function (data) {
                $ch.empty();
                if (data.length) {
                    data.forEach(c => $ch.append(
                        new Option(`Challan #${c.challan_no}  (${c.challan_date})  —  <?= CURRENCY_SYMBOL ?>${parseFloat(c.total_amount).toFixed(2)}`, c.id)
                    ));
                    $ch.prop('disabled', false);
                } else {
                    $ch.append('<option disabled>No unbilled challans for this vendor</option>');
                }
            })
            .fail(function () {
                $ch.empty().append('<option disabled>Failed to load challans</option>');
            });
    });
});

/* ── Live financial summary ───────────── */
function fmt(n) { return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }



/* ── File drop zone ───────────────────── */
function handleFile(input) {
    const fEl = document.getElementById('dzFile');
    const fn  = document.getElementById('dzFileName');
    const dz  = document.getElementById('dropZone');
    if (input.files && input.files[0]) {
        fn.textContent    = input.files[0].name;
        fEl.style.display = 'flex';
        dz.style.borderColor = 'var(--accent)';
        dz.style.background  = 'var(--accent-lt)';
    } else {
        fEl.style.display = 'none';
        dz.style.borderColor = '';
        dz.style.background  = '';
    }
}
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('active'); });
dz.addEventListener('dragleave', () => dz.classList.remove('active'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('active');
    if (e.dataTransfer.files.length) {
        document.getElementById('billFile').files = e.dataTransfer.files;
        handleFile(document.getElementById('billFile'));
    }
});

/* ══════════════════════════════════════
   MATERIAL ITEM LOGIC  (unchanged)
══════════════════════════════════════ */
let materialsData = [];
let vendorItems   = [];

/* 1. Fetch items when vendor selected */
$('#vendor_id').on('change', function () {
    const vid = $(this).val();
    vendorItems = [];
    clearItemFields();
    if (!vid) return;

    $.getJSON(`get_unbilled_items.php?vendor_id=${vid}`)
        .done(function (data) { vendorItems = data; })
        .fail(function () { showToast('Failed to load unbilled items.', 'error'); });
});

/* ── Autocomplete ────────────────────── */
const matInput = document.getElementById('material_name_input');
const matList  = document.getElementById('material_suggestions');

matInput.addEventListener('input', function () {
    const val = this.value.toLowerCase();
    matList.innerHTML = '';

    if (!val || !vendorItems.length) { matList.classList.remove('show'); return; }

    const matches = vendorItems.filter(item =>
        item.material_name.toLowerCase().includes(val) ||
        item.challan_no.toLowerCase().includes(val)
    ).slice(0, 10);

    if (!matches.length) { matList.classList.remove('show'); return; }

    matches.forEach(item => {
        const li = document.createElement('li');
        li.innerHTML = `
            <span class="ac-ic"><i class="fas fa-cube"></i></span>
            <span>
                ${esc(item.material_name)}
                <div class="ac-sub">Challan #${esc(item.challan_no)} &middot; Qty: ${item.quantity} ${item.unit}</div>
            </span>`;
        li.onclick = () => selectItem(item);
        matList.appendChild(li);
    });
    matList.classList.add('show');
});

document.addEventListener('click', e => {
    if (!matInput.contains(e.target) && !matList.contains(e.target))
        matList.classList.remove('show');
});

function selectItem(item) {
    document.getElementById('material_name_input').value = item.material_name;
    document.getElementById('material_id_hidden').value  = item.material_id;
    document.getElementById('material_challan').value    = item.challan_no;
    document.getElementById('item_challan_id').value     = item.challan_id;
    document.getElementById('material_unit').value       = item.unit.toLowerCase();
    document.getElementById('material_quantity').value   = item.quantity;
    matList.classList.remove('show');
    document.getElementById('material_rate').value = '';
    document.getElementById('material_rate').focus();
}

function clearItemFields() {
    ['material_name_input','material_challan','material_quantity','material_rate'].forEach(id =>
        document.getElementById(id).value = '');
    ['material_id_hidden','item_challan_id'].forEach(id =>
        document.getElementById(id).value = '');
    document.getElementById('material_unit').value = '';
    document.getElementById('material_gst').value  = '0';
}

/* ── Add material ────────────────────── */
function addMaterial() {
    const isManual = document.getElementById('manual_mode').checked;
    const cid  = document.getElementById('item_challan_id').value;
    const cno  = document.getElementById('material_challan').value;
    const mid  = document.getElementById('material_id_hidden').value;
    const name = document.getElementById('material_name_input').value;
    const unit = document.getElementById('material_unit').value;
    const qty  = parseFloat(document.getElementById('material_quantity').value);
    const rate = parseFloat(document.getElementById('material_rate').value);
    const gst  = parseFloat(document.getElementById('material_gst').value) || 0;

    if (!isManual && (!cid || !mid)) { showToast('Please search and select a material from the list.', 'error'); return; }
    if (!rate || rate <= 0)          { flashField('material_rate');     return; }
    if (cid && mid && materialsData.find(m => m.challan_id == cid && m.material_id == mid)) {
        showToast('This item is already added.', 'warning'); return;
    }

    materialsData.push({ 
        challan_id: cid, challan_no: cno, material_id: mid, material_name: name, unit, 
        quantity: qty, rate, gst_percent: gst, 
        total: (qty * rate) + ((qty * rate * gst) / 100) 
    });
    renderMaterials();
    clearItemFields();
    matInput.focus();
}

/* ── Render table ────────────────────── */
function renderMaterials() {
    const tbody = document.getElementById('materials_tbody');
    const badge = document.getElementById('item_badge');

    if (!materialsData.length) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="9"><span class="ei"><i class="fas fa-cubes"></i></span>No items added. Use the form above to add materials.</td></tr>`;
        badge.classList.remove('show');
        document.getElementById('mat_summary').style.display = 'none';
        document.getElementById('materials_json').value = '[]';
        return;
    }

    badge.textContent = materialsData.length;
    badge.classList.add('show');

    tbody.innerHTML = materialsData.map((m, i) => `
        <tr class="row-in">
            <td class="al-c"><span class="row-num">${i + 1}</span></td>
            <td>${m.challan_no ? `<span class="challan-pill">${esc(m.challan_no)}</span>` : '<span style="color:var(--ink-mute)">—</span>'}</td>
            <td><span class="mat-name">${esc(m.material_name)}</span></td>
            <td><span class="unit-pill">${esc(m.unit)}</span></td>
            <td class="al-c">
                <select class="rate-input" style="width:65px;padding:0 0.3rem; text-align:center" onchange="updateItemGST(${i}, this.value)">
                    <option value="0"  ${m.gst_percent == 0  ? 'selected' : ''}>0%</option>
                    <option value="6"  ${m.gst_percent == 6  ? 'selected' : ''}>6%</option>
                    <option value="12" ${m.gst_percent == 12 ? 'selected' : ''}>12%</option>
                    <option value="18" ${m.gst_percent == 18 ? 'selected' : ''}>18%</option>
                    <option value="28" ${m.gst_percent == 28 ? 'selected' : ''}>28%</option>
                </select>
            </td>
            <td class="al-r num-cell">${fmt(m.quantity)}</td>
            <td class="al-r"><input type="number" class="rate-input" value="${m.rate}" onchange="updateItemRate(${i}, this.value)" placeholder="Rate" step="0.01"></td>
            <td class="al-r num-cell">${fmt(m.total)}</td>
            <td class="al-c"><button type="button" class="btn-remove" onclick="removeMaterial(${i})" title="Remove"><i class="fas fa-times"></i></button></td>
        </tr>`).join('');

    const totalQty = materialsData.reduce((s, m) => s + m.quantity, 0);
    const totalAmt = materialsData.reduce((s, m) => s + m.total, 0);
    document.getElementById('sum_count').textContent  = materialsData.length;
    document.getElementById('sum_qty').textContent    = fmt(totalQty);
    
    // Call updateSummary to set initial values correctly including tax
    updateSummary(); 
    
    document.getElementById('mat_summary').style.display = 'flex';
    document.getElementById('materials_json').value = JSON.stringify(materialsData);
}

function removeMaterial(idx) {
    materialsData.splice(idx, 1);
    renderMaterials();
}
function updateItemRate(idx, val) {
    const rate = parseFloat(val) || 0;
    const gst  = materialsData[idx].gst_percent || 0;
    materialsData[idx].rate  = rate;
    const basic = materialsData[idx].quantity * rate;
    materialsData[idx].total = basic + ((basic * gst) / 100);
    
    // refresh amount column & summary without full re-render
    const rows = document.querySelectorAll('#materials_tbody tr.row-in');
    if (rows[idx]) {
        // Amount cell is index 7
        rows[idx].querySelectorAll('td')[7].textContent = fmt(materialsData[idx].total);
    }
    updateSummary();
}
function updateItemGST(idx, val) {
    const gst = parseFloat(val) || 0;
    const rate = materialsData[idx].rate || 0;
    materialsData[idx].gst_percent = gst;
    const basic = materialsData[idx].quantity * rate;
    materialsData[idx].total = basic + ((basic * gst) / 100);

    const rows = document.querySelectorAll('#materials_tbody tr.row-in');
    if (rows[idx]) {
        // Amount cell is index 7
        rows[idx].querySelectorAll('td')[7].textContent = fmt(materialsData[idx].total);
    }
    updateSummary();
}
function updateSummary() {
    // Calculate totals
    const totalAmt = materialsData.reduce((s, m) => s + m.total, 0);
    const taxable  = materialsData.reduce((s, m) => s + (m.quantity * m.rate), 0);
    const tax      = totalAmt - taxable;

    // Update display text
    document.getElementById('sum_taxable').textContent = '<?= CURRENCY_SYMBOL ?> ' + fmt(taxable); // New
    document.getElementById('sum_tax').textContent     = '<?= CURRENCY_SYMBOL ?> ' + fmt(tax);     // New
    document.getElementById('sum_amount').textContent  = '<?= CURRENCY_SYMBOL ?> ' + fmt(totalAmt);
    
    // Update hidden inputs so form can submit
    document.getElementById('materials_json').value = JSON.stringify(materialsData);
    document.getElementById('amount').value         = totalAmt.toFixed(2);
    document.getElementById('taxable_amount').value = taxable.toFixed(2);
    document.getElementById('tax_amount').value     = tax.toFixed(2);
}

/* ── Challan selection auto-populate ── */
$(function () {
    $('#challan_ids').on('change', function () {
        const selectedIds = $(this).val() || [];
        selectedIds.forEach(id => {
            if (materialsData.some(m => m.challan_id == id)) return;
            fetch(`get_challan_items.php?challan_id=${id}`)
                .then(r => r.json())
                .then(items => {
                    let added = 0;
                    items.forEach(item => {
                        if (!materialsData.find(m => m.challan_id == id && m.material_id == item.material_id)) {
                            materialsData.push({
                                challan_id:    item.challan_id,
                                challan_no:    item.challan_no || id,
                                material_id:   item.material_id,
                                material_name: item.material_name,
                                unit:          item.unit.toLowerCase(),
                                quantity:      parseFloat(item.quantity) || 0,
                                rate:          parseFloat(item.rate) || 0,
                                total:         (parseFloat(item.rate) || 0) * (parseFloat(item.quantity) || 0),
                            });
                            added++;
                        }
                    });
                    if (added) renderMaterials();
                });
        });
    });
});

/* ── Manual mode ─────────────────────── */
function toggleManualMode() {
    const isManual = document.getElementById('manual_mode').checked;
    const challanEl = document.getElementById('material_challan');
    if (isManual) {
        challanEl.removeAttribute('readonly');
        challanEl.placeholder = 'Enter challan no';
    } else {
        challanEl.setAttribute('readonly', true);
        challanEl.placeholder = 'Auto-filled';
    }
}

/* ── Helpers ─────────────────────────── */
function flashField(id) {
    const el = document.getElementById(id);
    el.style.borderColor = '#dc2626';
    el.style.boxShadow   = '0 0 0 3px rgba(220,38,38,0.12)';
    el.focus();
    setTimeout(() => { el.style.borderColor = ''; el.style.boxShadow = ''; }, 1800);
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Enter key in strip → add */
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.closest('.add-strip') && e.target.tagName !== 'BUTTON')
        addMaterial();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>