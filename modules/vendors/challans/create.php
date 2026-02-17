<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Create Delivery Challan';
$current_page = 'material_challan';

// Fetch initial data
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$vendors = $db->query("SELECT id, name, mobile, email, address, gst_number FROM parties WHERE party_type = 'vendor' AND status = 'active' ORDER BY name")->fetchAll();
$materials = $db->query("SELECT id, material_name, unit, default_rate FROM materials ORDER BY material_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please reload and try again.');
         redirect('modules/vendors/challans/create.php');
    }

    try {
        $db->beginTransaction();

        $vendor_id = $_POST['vendor_id'];
        $vendor_name = trim($_POST['vendor_name']);

        // GST Validation
        if (!empty($_POST['gst_number'])) {
            $_POST['gst_number'] = strtoupper(trim($_POST['gst_number']));
            if (!preg_match("/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/", $_POST['gst_number'])) {
                throw new Exception("Invalid GST Number. Must be exactly 15 characters (e.g. 22AAAAA0000A1Z5).");
            }
            
            // Check if GST already exists in parties table
            $existingGst = $db->query("SELECT id, name FROM parties WHERE gst_number = ? AND party_type='vendor'", [$_POST['gst_number']])->fetch();
            if ($existingGst) {
                 // If selecting an existing vendor, ensure the GST matches that vendor (logic handled below by ID), 
                 // but if creating a NEW vendor (vendor_id empty), this is a blocker.
                 if (empty($vendor_id) || $vendor_id != $existingGst['id']) {
                     throw new Exception("GST Number '{$_POST['gst_number']}' is already registered to '{$existingGst['name']}'. Please select that vendor instead.");
                 }
            }
        }
        
        if (empty($vendor_id)) {
            $existing_vendor = $db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='vendor'", [$vendor_name])->fetch();
            
            if ($existing_vendor) {
                $vendor_id = $existing_vendor['id'];
            } else {
                $temp_vendor = [
                    'name' => $vendor_name,
                    'mobile' => sanitize($_POST['mobile']),
                    'address' => sanitize($_POST['address']),
                    'gst_number' => sanitize($_POST['gst_number']),
                    'email' => sanitize($_POST['email'])
                ];
                $temp_vendor_json = json_encode($temp_vendor);
                $vendor_id = null;
            }
        }

        $project_id = intval($_POST['project_id']);
        $po_id = !empty($_POST['po_id']) ? intval($_POST['po_id']) : null;
        $challan_date = $_POST['challan_date'];
        $vehicle_no = sanitize($_POST['vehicle_no'] ?? '');
        $challan_no = sanitize($_POST['challan_no'] ?? '');
        
        if (empty($challan_no)) {
            throw new Exception("Challan Number is required");
        }
        
        $dup_check = $db->query("SELECT id FROM challans WHERE challan_no = ?", [$challan_no])->fetch();
        if ($dup_check) {
            throw new Exception("Challan Number '$challan_no' already exists. Please use a unique number.");
        }

        $total_amount = 0; // Challans have no value now
        
        $materials_list = json_decode($_POST['materials_json'], true);
        if (empty($materials_list)) {
            throw new Exception("Please add at least one material");
        }

        // Removed total_amount calculation loop

        $challan_data = [
            'challan_no' => $challan_no,
            'challan_type' => 'material',
            'party_id' => $vendor_id,
            'project_id' => $project_id,
            'po_id' => $po_id,
            'challan_date' => $challan_date,
            'vehicle_no' => $vehicle_no,
            'total_amount' => 0, // No value on challan
            'status' => 'pending',
            'created_by' => $_SESSION['user_id'],
            'temp_vendor_data' => $temp_vendor_json ?? null
        ];
        
        $challan_id = $db->insert('challans', $challan_data);

        foreach ($materials_list as $item) {
            $material_id = isset($item['material_id']) && $item['material_id'] ? intval($item['material_id']) : null;
            $material_name = trim($item['material_name']);
            $unit = $item['unit'];
            
            if (!$material_id) {
                $existing = $db->query("SELECT id FROM materials WHERE LOWER(material_name) = LOWER(?)", [$material_name])->fetch();
                if ($existing) {
                    $material_id = $existing['id'];
                } else {
                    $new_material_data = [
                        'material_name' => $material_name,
                        'unit' => $unit,
                        'default_rate' => 0,
                        'current_stock' => 0
                    ];
                    $material_id = $db->insert('materials', $new_material_data);
                }
            }

            $item_data = [
                'challan_id' => $challan_id,
                'material_id' => $material_id,
                'quantity' => floatval($item['quantity']),
                'rate' => null,
                'tax_rate' => null,
                'tax_amount' => null
            ];
            
            $db->insert('challan_items', $item_data);
            
            if ($po_id && $material_id) {
                 $db->query("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE po_id = ? AND material_id = ?", 
                     [$item['quantity'], $po_id, $material_id]);
            }
        }
        
        if ($po_id) {
        require_once __DIR__ . '/../../../includes/ProcurementService.php';
            $procService = new ProcurementService();
            $procService->updatePOStatus($po_id);
        }
        
        logAudit('create', 'challans', $challan_id, null, $challan_data);

        // ── Notification Trigger ──
        require_once __DIR__ . '/../../../includes/NotificationService.php';
        $ns = new NotificationService();
        $notifTitle = "New Material Challan";
        $notifMsg   = "Challan #{$challan_no} created for Vendor: {$vendor_name}";
        $notifLink  = BASE_URL . "modules/vendors/challans/material.php";

        // Notify Admin (User 1)
        $ns->create(1, $notifTitle, $notifMsg, 'info', $notifLink);
        
        // Also notify current user if not admin
        if ($_SESSION['user_id'] != 1) {
             $ns->create($_SESSION['user_id'], $notifTitle, "You created: " . $notifMsg, 'success', $notifLink);
        }

        $db->commit();
        setFlashMessage('success', "Delivery Challan $challan_no created successfully");
        redirect('modules/vendors/challans/material.php');

    } catch (Exception $e) {
        $db->rollback();
        setFlashMessage('error', $e->getMessage());
    }
}

include __DIR__ . '/../../../includes/header.php';
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
        --accent-lt: #fef3ea;
    }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── Wrapper ─────────────────────────────── */
    .challan-wrap {
        max-width: 1020px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .challan-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        gap: 1rem;
        flex-wrap: wrap;
    }

    .challan-page-header .eyebrow {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 0.3rem;
    }

    .challan-page-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--ink);
        margin: 0;
    }

    .challan-page-header h1 em { color: var(--accent); font-style: italic; }

    .back-link {
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
    }
    .back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Cards ───────────────────────────────── */
    .ch-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    }

    .ch-card-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.1rem 1.6rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .ch-card-icon {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: white;
        background: var(--accent);
        flex-shrink: 0;
    }

    .ch-card-icon.blue   { background: #4f63d2; }
    .ch-card-icon.green  { background: #059669; }

    .ch-card-head h2 {
        font-family: 'Fraunces', serif;
        font-size: 1rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0;
    }

    .ch-card-head .step-tag {
        margin-left: auto;
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ink-mute);
        background: var(--cream);
        border: 1px solid var(--border);
        padding: 0.18rem 0.6rem;
        border-radius: 20px;
    }

    .ch-card-body { padding: 1.6rem; }

    /* ── Section label ───────────────────────── */
    .sec-label {
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--ink-mute);
        margin: 0 0 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
    }

    /* ── Field ───────────────────────────────── */
    .field {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .field label {
        font-size: 0.73rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--ink-soft);
    }

    .field label .req { color: var(--accent); margin-left: 2px; }

    .field input,
    .field select,
    .field textarea {
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
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
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

    .field .hint {
        font-size: 0.7rem;
        color: var(--ink-mute);
        margin-top: -0.1rem;
    }

    /* ── Grids ───────────────────────────────── */
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.1rem; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.1rem; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.1rem; }

    @media (max-width: 780px) {
        .grid-3, .grid-4 { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 520px) {
        .grid-3, .grid-4, .grid-2 { grid-template-columns: 1fr; }
    }

    /* ── Divider between sub-sections ───────── */
    .sec-gap { margin-top: 1.4rem; padding-top: 1.4rem; border-top: 1px solid var(--border-lt); }

    /* ── Autocomplete ────────────────────────── */
    .autocomplete-wrapper { position: relative; }

    .autocomplete-list {
        position: absolute;
        top: calc(100% + 3px);
        left: 0; right: 0;
        background: white;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        list-style: none;
        padding: 0.3rem;
        margin: 0;
        z-index: 300;
        display: none;
        max-height: 210px;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(26,23,20,0.1);
    }
    .autocomplete-list.show { display: block; }

    .autocomplete-item {
        padding: 0.55rem 0.75rem;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--ink);
        transition: background 0.12s ease;
    }
    .autocomplete-item:hover,
    .autocomplete-item.active { background: #f5f1eb; color: var(--accent); }

    .autocomplete-item .ac-sub { font-size: 0.72rem; color: var(--ink-mute); margin-top: 1px; }

    /* ── Add Material Strip ──────────────────── */
    .add-strip {
        display: grid;
        grid-template-columns: 2.2fr 0.9fr 0.9fr 0.9fr auto;
        gap: 0.85rem;
        align-items: flex-end;
        padding: 1.2rem 1.4rem;
        background: #fdf8f3;
        border: 1.5px dashed #e0c9b5;
        border-radius: 10px;
        margin-bottom: 1.4rem;
    }

    @media (max-width: 760px) {
        .add-strip { grid-template-columns: 1fr 1fr; }
        .add-strip .btn-add { grid-column: span 2; }
    }

    .btn-add {
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0 1.2rem;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.18s ease, transform 0.1s ease;
    }
    .btn-add:hover { background: #9e521f; transform: translateY(-1px); }
    .btn-add:active { transform: translateY(0); }

    /* ── Materials Table ─────────────────────── */
    .mat-table-wrap {
        border: 1.5px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }

    .mat-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .mat-table thead tr { background: #f5f1eb; border-bottom: 1.5px solid var(--border); }

    .mat-table thead th {
        padding: 0.7rem 1rem;
        text-align: left;
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-soft);
        white-space: nowrap;
    }

    .mat-table tbody tr {
        border-bottom: 1px solid var(--border-lt);
        transition: background 0.12s ease;
    }
    .mat-table tbody tr:last-child { border-bottom: none; }
    .mat-table tbody tr:hover { background: #fdfcfa; }

    .mat-table td { padding: 0.8rem 1rem; vertical-align: middle; }

    .mat-name { font-weight: 600; color: var(--ink); }

    .mat-table td input.qty-input {
        width: 100px;
        height: 30px;
        text-align: left;
    }

    .mat-unit {
        display: inline-block;
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--ink-mute);
        background: var(--cream);
        border: 1px solid var(--border);
        padding: 0.15rem 0.45rem;
        border-radius: 4px;
    }

    .qty-input {
        width: 88px;
        height: 34px;
        padding: 0 0.6rem;
        border: 1.5px solid var(--border);
        border-radius: 6px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        color: var(--ink);
        background: var(--surface);
        outline: none;
        transition: border-color 0.15s ease;
    }
    .qty-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(181,98,42,0.12); }

    .btn-remove {
        width: 28px; height: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1.5px solid #f0c0b0;
        background: #fff5f0;
        color: #c0522a;
        border-radius: 6px;
        font-size: 0.68rem;
        cursor: pointer;
        transition: all 0.16s ease;
    }
    .btn-remove:hover { background: #c0522a; border-color: #c0522a; color: white; }

    .empty-row td {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--ink-mute);
        font-size: 0.875rem;
    }
    .empty-row .ei { display: block; font-size: 2rem; opacity: 0.3; margin-bottom: 0.5rem; }

    /* ── Action Bar ──────────────────────────── */
    .ch-action-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1.2rem 1.6rem;
        background: #fdfcfa;
        border-top: 1.5px solid var(--border-lt);
    }

    .btn-cancel {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.65rem 1.4rem;
        border: 1.5px solid var(--border);
        background: white;
        color: var(--ink-soft);
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.18s ease;
    }
    .btn-cancel:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .btn-save {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.65rem 1.75rem;
        background: var(--ink);
        color: white;
        border: 1.5px solid var(--ink);
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s ease;
        letter-spacing: 0.02em;
    }
    .btn-save:hover {
        background: var(--accent);
        border-color: var(--accent);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(181,98,42,0.28);
    }
    .btn-save:active { transform: translateY(0); }

    /* ── Toast ───────────────────────────────── */
    .toast-wrap {
        position: fixed;
        top: 1.5rem; right: 1.5rem;
        z-index: 9999;
        display: flex; flex-direction: column; gap: 0.5rem;
    }

    .toast {
        display: flex; align-items: flex-start; gap: 0.75rem;
        padding: 0.9rem 1.1rem;
        background: white;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        min-width: 280px;
        max-width: 360px;
        box-shadow: 0 8px 24px rgba(26,23,20,0.12);
        animation: toastIn 0.25s ease;
    }
    .toast.t-success { border-left: 4px solid #10b981; }
    .toast.t-warning { border-left: 4px solid #f59e0b; }
    .toast.t-error   { border-left: 4px solid #ef4444; }

    .toast-icon { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
    .t-success .toast-icon { color: #10b981; }
    .t-warning .toast-icon { color: #f59e0b; }
    .t-error   .toast-icon { color: #ef4444; }

    .toast-title { font-size: 0.8rem; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
    .toast-msg   { font-size: 0.78rem; color: var(--ink-soft); margin: 0; line-height: 1.4; }

    .toast-close {
        margin-left: auto; background: none; border: none;
        font-size: 1rem; color: var(--ink-mute); cursor: pointer; flex-shrink: 0; line-height: 1;
    }
    .toast-close:hover { color: var(--ink); }

    @keyframes toastIn {
        from { opacity: 0; transform: translateX(20px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    /* ── item count badge ────────────────────── */
    .item-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 0.4rem;
        background: var(--accent); color: white;
        border-radius: 20px; font-size: 0.65rem; font-weight: 700;
        margin-left: 0.5rem;
    }

    /* ── Fade Animation ───────────────────────── */

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }


    /* Apply animation to cards */
    .ch-card {
        animation: fadeUp 0.45s ease both;
    }

    .ch-card:nth-of-type(1) {
        animation-delay: 0.05s;
    }

    .ch-card:nth-of-type(2) {
        animation-delay: 0.15s;
    }
</style>

<div class="challan-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="challan-page-header">
        <div>
            <div class="eyebrow">Material Challans</div>
            <h1>Create Delivery <em>Challan</em></h1>
        </div>
        <a href="material.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <form method="POST" id="challanForm">
        <?= csrf_field() ?>

        <!-- ── Card 1: Challan Info ──────────── -->
        <div class="ch-card">
            <div class="ch-card-head">
                <div class="ch-card-icon blue"><i class="fas fa-file-invoice"></i></div>
                <h2>Challan Information</h2>
                <span class="step-tag">Step 1 of 2</span>
            </div>
            <div class="ch-card-body">

                <!-- Row 1: Core identifiers -->
                <div class="sec-label">Core Details</div>
                <div class="grid-3" style="margin-bottom:1.1rem">
                    <div class="field">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" id="project_id" required onchange="fetchPOs()">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Challan No <span class="req">*</span></label>
                        <input type="text" name="challan_no" placeholder="e.g. 1001" required>
                    </div>
                    <div class="field">
                        <label>Challan Date <span class="req">*</span></label>
                        <input type="date" name="challan_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="grid-3">
                    <div class="field">
                        <label>Link to PO</label>
                        <select name="po_id" id="po_id" onchange="loadPOItems()">
                            <option value="">— None —</option>
                        </select>
                        <span class="hint">Select Project &amp; Vendor first</span>
                    </div>
                    <div class="field">
                        <label>Vehicle No</label>
                        <input type="text" name="vehicle_no" placeholder="e.g. MH-12-AB-1234">
                    </div>
                    <div class="field" style="visibility:hidden"></div>
                </div>

                <!-- Row 2: Vendor -->
                <div class="sec-gap">
                    <div class="sec-label">Vendor Details</div>
                    <div class="grid-3" style="margin-bottom:1.1rem">
                        <div class="field">
                            <label>Vendor Name <span class="req">*</span></label>
                            <div class="autocomplete-wrapper">
                                <input type="text" name="vendor_name" id="vendor_name" placeholder="Search vendor…" autocomplete="off" required>
                                <ul id="vendor_suggestions" class="autocomplete-list"></ul>
                            </div>
                            <input type="hidden" name="vendor_id" id="vendor_id">
                        </div>
                        <div class="field">
                            <label>Mobile</label>
                            <input type="text" name="mobile" id="vendor_mobile" placeholder="10-digit number"
                                   pattern="\d{10}" maxlength="10" minlength="10"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                        <div class="field">
                            <label>GST Number</label>
                            <input type="text" name="gst_number" id="vendor_gst" placeholder="22AAAAA0000A1Z5"
                                   maxlength="15"
                                   pattern="^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$"
                                   oninput="this.value=this.value.toUpperCase()">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label>Email Address</label>
                            <input type="email" name="email" id="vendor_email" placeholder="vendor@example.com">
                        </div>
                        <div class="field">
                            <label>Vendor Address</label>
                            <input type="text" name="address" id="vendor_address" placeholder="Full address">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Card 2: Materials ─────────────── -->
        <div class="ch-card">
            <div class="ch-card-head">
                <div class="ch-card-icon green"><i class="fas fa-boxes"></i></div>
                <h2>Material Items <span class="item-badge" id="item_badge" style="display:none">0</span></h2>
                <span class="step-tag">Step 2 of 2</span>
            </div>
            <div class="ch-card-body">

                <!-- Add strip -->
                <div class="add-strip">
                    <div class="field">
                        <label>Material Name</label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="material_name_input" placeholder="Search material…" autocomplete="off">
                            <ul id="material_suggestions" class="autocomplete-list"></ul>
                        </div>
                        <input type="hidden" id="material_id_hidden">
                    </div>
                    <div class="field">
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
                    <div class="field">
                        <label>Quantity</label>
                        <input type="number" id="material_quantity" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <button type="button" class="btn-add" onclick="addMaterial()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>

                <!-- Table -->
                <div class="mat-table-wrap">
                    <table class="mat-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="materials_tbody">
                            <tr class="empty-row">
                                <td colspan="5">
                                    <span class="ei"><i class="fas fa-cubes"></i></span>
                                    No items added. Use the form above to add materials.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="materials_json" id="materials_json">
            </div>

            <div class="ch-action-bar">
                <a href="material.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                <button type="button" id="btn-save-challan" class="btn-save"><i class="fas fa-check"></i> Save Challan</button>
            </div>
        </div>

    </form>
</div>

<!-- Toast Container -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Challan Script Loaded');

    /* ── Data ─────────────────────────────────────────── */
    const vendors          = <?= json_encode($vendors) ?>;
    const existingMaterials = <?= json_encode($materials) ?>;
    window.materialsData = [];

    /* ── Toast ────────────────────────────────────────── */
    window.showToast = function(message, type = 'error', title = '') {
        const wrap = document.getElementById('toastWrap');
        if(!wrap) return;
        
        const icons = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error:   'fa-times-circle'
        };
        const titles = { success: 'Success', warning: 'Warning', error: 'Error' };
        const t = document.createElement('div');
        t.className = `toast t-${type}`;
        t.innerHTML = `
            <i class="fas ${icons[type] || icons.error} toast-icon"></i>
            <div style="flex:1">
                <div class="toast-title">${title || titles[type]}</div>
                <p class="toast-msg">${message}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
        wrap.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; setTimeout(() => t.remove(), 300); }, 4000);
    };

    /* ── Autocomplete ─────────────────────────────────── */
    function setupAutocomplete(inputId, listId, data, displayKey, onSelect) {
        const input  = document.getElementById(inputId);
        const list   = document.getElementById(listId);
        if(!input || !list) return;

        let focus = -1;

        function render(matches) {
            list.innerHTML = '';
            if (!matches.length) { list.classList.remove('show'); return; }
            list.classList.add('show');
            matches.forEach(item => {
                const li = document.createElement('li');
                li.className = 'autocomplete-item';
                if (displayKey === 'name') {
                    li.innerHTML = `<strong>${item.name}</strong>`;
                } else {
                    li.innerHTML = `<strong>${item.material_name}</strong><div class="ac-sub">${item.unit}</div>`;
                }
                li.onclick = () => { onSelect(item); list.classList.remove('show'); focus = -1; };
                list.appendChild(li);
            });
        }

        function filter(val) {
            const v = val.toLowerCase();
            const matches = !v
                ? data.slice(0, 12)
                : data.filter(d => (d[displayKey] || '').toLowerCase().includes(v));
            render(matches);
            focus = -1;
        }

        input.addEventListener('input',  () => filter(input.value));
        input.addEventListener('focus',  () => filter(input.value));

        input.addEventListener('keydown', e => {
            const items = list.querySelectorAll('.autocomplete-item');
            if (!list.classList.contains('show') || !items.length) return;
            if (e.key === 'ArrowDown') { focus = Math.min(focus + 1, items.length - 1); }
            else if (e.key === 'ArrowUp') { focus = Math.max(focus - 1, 0); }
            else if (e.key === 'Enter') { e.preventDefault(); if (focus >= 0) items[focus].click(); else if (items.length === 1) items[0].click(); return; }
            else return;
            items.forEach((el, i) => el.classList.toggle('active', i === focus));
            items[focus]?.scrollIntoView({ block: 'nearest' });
        });

        document.addEventListener('click', e => { if (e.target !== input) list.classList.remove('show'); });
    }

    // Initialize Autocompletes
    setupAutocomplete('vendor_name', 'vendor_suggestions', vendors, 'name', vendor => {
        document.getElementById('vendor_name').value    = vendor.name;
        document.getElementById('vendor_id').value      = vendor.id;
        document.getElementById('vendor_mobile').value  = vendor.mobile || '';
        document.getElementById('vendor_email').value   = vendor.email || '';
        document.getElementById('vendor_address').value = vendor.address || '';
        document.getElementById('vendor_gst').value     = vendor.gst_number || '';
        fetchPOs();
    });

    const vNameInput = document.getElementById('vendor_name');
    if(vNameInput) {
        vNameInput.addEventListener('input', () => {
            document.getElementById('vendor_id').value = '';
            document.getElementById('po_id').innerHTML = '<option value="">— None —</option>';
        });
    }

    setupAutocomplete('material_name_input', 'material_suggestions', existingMaterials, 'material_name', material => {
        document.getElementById('material_name_input').value = material.material_name;
        document.getElementById('material_id_hidden').value  = material.id;
        document.getElementById('material_unit').value       = material.unit;
    });

    const mNameInput = document.getElementById('material_name_input');
    if(mNameInput) {
        mNameInput.addEventListener('input', () => {
            document.getElementById('material_id_hidden').value = '';
        });
    }

    // Expose functions globally for inline onclicks in HTML (like onchange="fetchPOs()")
    // Note: It's better to attach listeners in JS, but for now we expose them to window to keep HTML compatible
    window.fetchPOs = function() {
        const projectId = document.getElementById('project_id').value;
        const poSelect  = document.getElementById('po_id');

        if (!projectId) {
            poSelect.innerHTML = '<option value="">— Select Project First —</option>';
            return;
        }

        poSelect.innerHTML = `<option value="">Loading POs...</option>`;

        fetch(`../../api/get_project_pos.php?project_id=${projectId}`)
            .then(r => r.json())
            .then(data => {
                let opts = '';
                if (data.length > 0) {
                    opts = `<option value="">— Select PO —</option>`;
                    data.forEach(po => {
                        const date = new Date(po.order_date).toLocaleDateString('en-GB'); 
                        opts += `<option value="${po.id}">#${po.po_number} (₹${parseFloat(po.total_amount).toLocaleString('en-IN')}) - ${date}</option>`;
                    });
                } else {
                    opts = `<option value="">No Approved POs found</option>`;
                }
                poSelect.innerHTML = opts;
            })
            .catch(() => {
                poSelect.innerHTML = '<option value="">Failed to load POs</option>';
            });
    };

    window.loadPOItems = function() {
        const poId = document.getElementById('po_id').value;
        if (!poId) {
            toggleVendorFields(false);
            return;
        } // ... rest of loadPOItems logic is large, let's keep it simple for this fix or re-implement if needed. 
          // Re-implementing simplified version to ensure it works
        
        if (window.materialsData.length > 0 && !confirm('This will replace current items and vendor details. Continue?')) {
            document.getElementById('po_id').value = '';
            return;
        }

        fetch(`../../api/get_po_details.php?po_id=${poId}`)
            .then(r => r.json())
            .then(data => {
                const po = data.po;
                const items = data.items;

                if (po) {
                    document.getElementById('vendor_id').value      = po.vendor_id;
                    document.getElementById('vendor_name').value    = po.vendor_name;
                    document.getElementById('vendor_mobile').value  = po.vendor_mobile || '';
                    document.getElementById('vendor_email').value   = po.vendor_email || '';
                    document.getElementById('vendor_address').value = po.vendor_address || '';
                    document.getElementById('vendor_gst').value     = po.vendor_gst || '';
                    toggleVendorFields(true);
                    showToast('Vendor details auto-filled from PO.', 'success');
                }

                window.materialsData = [];
                if (items && items.length > 0) {
                    items.forEach(item => {
                        const pendingQty = parseFloat(item.quantity) - parseFloat(item.received_qty);
                        if (pendingQty > 0) {
                            window.materialsData.push({
                                material_id:   item.material_id,
                                material_name: item.material_name,
                                unit:          item.unit,
                                quantity:      pendingQty, 
                                rate:          parseFloat(item.rate),
                                total:         pendingQty * parseFloat(item.rate)
                            });
                        }
                    });
                    renderMaterials();
                } else {
                    showToast('No items found in this PO', 'warning');
                }
            })
            .catch((e) => {
                console.error(e);
                showToast('Failed to load PO items.', 'error');
            });
    };

    window.toggleVendorFields = function(readonly) {
        const fields = ['vendor_name', 'vendor_mobile', 'vendor_gst', 'vendor_email', 'vendor_address'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if(el) {
                el.readOnly = readonly;
                if(readonly) {
                    el.style.backgroundColor = '#f9fafb';
                    el.style.cursor = 'not-allowed';
                } else {
                    el.style.backgroundColor = '';
                    el.style.cursor = '';
                }
            }
        });
    };

    window.addMaterial = function() {
        const name = document.getElementById('material_name_input').value.trim();
        const id   = document.getElementById('material_id_hidden').value;
        const unit = document.getElementById('material_unit').value;
        const qty  = parseFloat(document.getElementById('material_quantity').value);

        if (!name || !unit || isNaN(qty) || qty <= 0) {
            showToast('Please fill Material Name, Unit and Quantity.', 'warning', 'Incomplete');
            return;
        }

        if (window.materialsData.some(m => m.material_name.toLowerCase() === name.toLowerCase())) {
            showToast('This material has already been added.', 'warning', 'Duplicate');
            return;
        }

        window.materialsData.push({ material_id: id, material_name: name, unit, quantity: qty, rate: 0, total: 0 });
        renderMaterials();

        document.getElementById('material_name_input').value = '';
        document.getElementById('material_id_hidden').value  = '';
        document.getElementById('material_unit').value       = '';
        document.getElementById('material_quantity').value   = '';
        document.getElementById('material_name_input').focus();
    };

    window.renderMaterials = function() {
        const tbody = document.getElementById('materials_tbody');
        const badge = document.getElementById('item_badge');

        if (!window.materialsData.length) {
            tbody.innerHTML = `<tr class="empty-row"><td colspan="5">
                <span class="ei"><i class="fas fa-cubes"></i></span>
                No items added. Use the form above to add materials.
            </td></tr>`;
            badge.style.display = 'none';
            return;
        }

        badge.textContent    = window.materialsData.length;
        badge.style.display  = 'inline-flex';

        tbody.innerHTML = window.materialsData.map((m, i) => `
            <tr>
                <td style="color:var(--ink-mute);font-size:.75rem;width:36px">${i + 1}</td>
                <td><span class="mat-name">${m.material_name}</span></td>
                <td><span class="mat-unit">${m.unit.toUpperCase()}</span></td>
                <td>
                    <input class="qty-input" type="number" value="${m.quantity}"
                           onchange="updateQty(${i}, this.value)" step="0.01" min="0">
                </td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeMaterial(${i})" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`).join('');
    };

    window.updateQty = function(idx, val) {
        const q = parseFloat(val);
        if (isNaN(q) || q < 0) { showToast('Invalid quantity.', 'error'); renderMaterials(); return; }
        window.materialsData[idx].quantity = q;
    };

    window.removeMaterial = function(idx) {
        window.materialsData.splice(idx, 1);
        renderMaterials();
    };

    /* ── Validate & Submit ────────────────────────────── */
    const saveBtn = document.getElementById('btn-save-challan');
    if(saveBtn) {
        console.log("Save button found, attaching listener");
        // Visual indicator that script ran
        saveBtn.style.border = "2px solid #2a58b5"; 
        
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const btn = this;
            const form = document.getElementById('challanForm');

            console.log('Save button clicked');

            if (!form) {
                showToast('Form element not found!', 'error');
                return;
            }

            // Basic HTML5 validation
            if (!form.checkValidity()) {
                console.warn('HTML5 Validation failed');
                form.reportValidity();
                return;
            }

            // Custom Validation
            if (!window.materialsData.length) {
                showToast('Please add at least one material item.', 'error', 'No Materials');
                return;
            }

            // Prepare Data
            document.getElementById('materials_json').value = JSON.stringify(window.materialsData);
            console.log('Submitting form...');

            // Disable button to prevent double submit
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            // Submit using prototype to avoid shadowing issues
            try {
                HTMLFormElement.prototype.submit.call(form);
            } catch (err) {
                console.error('Submit Error:', err);
                showToast('Error: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Save Challan';
            }
        });
    } else {
        console.error("Save button NOT found");
        alert("Critical Error: Save button not found. Please refresh.");
    }

});
</script>


<?php include __DIR__ . '/../../../includes/footer.php'; ?>