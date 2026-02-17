<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/ProcurementService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Create Purchase Order';
$current_page = 'procurement';

// Fetch initial data
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$vendors = $db->query("SELECT id, name, mobile, address, gst_number FROM parties WHERE party_type = 'vendor' AND status = 'active' ORDER BY name")->fetchAll();
$materials = $db->query("SELECT id, material_name, unit, default_rate, tax_rate FROM materials ORDER BY material_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/vendors/procurement/create.php');
    }

    try {
        $service = new ProcurementService();
        
        $poData = [
            'project_id' => $_POST['project_id'],
            'vendor_id' => $_POST['vendor_id'],
            'order_date' => $_POST['order_date'],
            'expected_date' => $_POST['expected_date'] ?: null,
            'notes' => sanitize($_POST['notes'])
        ];

        // ── 1. Handle Vendor (Create or Update) ─────────────────
        file_put_contents(__DIR__ . '/debug_po_post.txt', print_r($_POST, true)); // DEBUG LOG

        $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
        $vendor_name = trim($_POST['vendor_name']);
        
        $vendor_data = [
            'mobile' => sanitize($_POST['mobile'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'gst_number' => sanitize($_POST['gst_number'] ?? ''),
            // Default fields for new vendor
            'vendor_type' => 'supplier', // Default for PO
            'city' => !empty($_POST['address']) ? explode(',', $_POST['address'])[0] : '', // Use first part of address as City
            'gst_status' => !empty($_POST['gst_number']) ? 'registered' : 'unregistered'
        ];

        // Check for duplicate GST Number
        if (!empty($vendor_data['gst_number'])) {
            $gstCheckSql = "SELECT id, name FROM parties WHERE gst_number = ? AND party_type='vendor'";
            $gstParams = [$vendor_data['gst_number']];
            
            if ($vendor_id) {
                $gstCheckSql .= " AND id != ?";
                $gstParams[] = $vendor_id;
            }
            
            $existingGst = $db->query($gstCheckSql, $gstParams)->fetch();
            if ($existingGst) {
                throw new Exception("GST Number '{$vendor_data['gst_number']}' is already used by vendor '{$existingGst['name']}' (ID: {$existingGst['id']}).");
            }
        }

        if ($vendor_id) {
            // Update existing vendor details
            $db->update('parties', $vendor_data, 'id=?', [$vendor_id]);
        } else {
            // Check if vendor name exists (case-insensitive)
            $existing = $db->query("SELECT id FROM parties WHERE name = ? AND party_type='vendor'", [$vendor_name])->fetch();
            if ($existing) {
                $vendor_id = $existing['id'];
                $db->update('parties', $vendor_data, 'id=?', [$vendor_id]);
            } else {
                // Create new vendor
                $vendor_data['name'] = $vendor_name;
                $vendor_data['party_type'] = 'vendor';
                $vendor_data['status'] = 'active';
                $vendor_id = $db->insert('parties', $vendor_data);
            }
        }
        $poData['vendor_id'] = $vendor_id; // Update PO with final vendor ID

        $items = json_decode($_POST['items_json'], true);
        if (empty($items)) {
            throw new Exception("Please add at least one item.");
        }

        // ── 2. Handle New Materials ─────────────────────────────
        foreach ($items as &$item) {
            if (empty($item['material_id']) && !empty($item['material_name'])) {
                $matName = trim($item['material_name']);
                
                // Check if exists
                $existingMat = $db->query("SELECT id FROM materials WHERE material_name = ?", [$matName])->fetch();
                
                if ($existingMat) {
                    $item['material_id'] = $existingMat['id'];
                } else {
                    // Create new material
                    $newMatData = [
                        'material_name' => $matName,
                        'unit'          => $item['unit'],
                        'default_rate'  => $item['rate'],
                        'tax_rate'      => $item['tax_rate'] ?? 0
                    ];
                    $item['material_id'] = $db->insert('materials', $newMatData);
                }
            }
        }
        unset($item); // Break reference

        $po_id = $service->createPO($poData, $items);
        
        setFlashMessage('success', "Purchase Order created successfully.");
        redirect("modules/vendors/procurement/view.php?id=$po_id");

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    /* ── Reset & Base ────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }

    body {
        background: #f5f3ef;
        font-family: 'DM Sans', sans-serif;
        color: #1a1714;
        min-height: 100vh;
    }

    /* ── Page Layout ─────────────────────────────────── */
    .po-wrapper {
        max-width: 1020px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────────────── */
    .po-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid #ddd9d1;
    }

    .po-page-header .title-block {}
    .po-page-header .eyebrow {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #b5622a;
        margin-bottom: 0.35rem;
    }
    .po-page-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        color: #1a1714;
        margin: 0;
    }
    .po-page-header h1 span {
        color: #b5622a;
        font-style: italic;
    }

    .po-page-header .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.82rem;
        font-weight: 500;
        color: #6b6560;
        text-decoration: none;
        padding: 0.45rem 1rem;
        border: 1.5px solid #ddd9d1;
        border-radius: 6px;
        transition: all 0.2s ease;
        background: white;
    }
    .po-page-header .back-link:hover {
        border-color: #b5622a;
        color: #b5622a;
        background: #fdf8f3;
    }

    /* ── Cards ───────────────────────────────────────── */
    .po-card {
        background: #ffffff;
        border: 1.5px solid #e8e3db;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    }

    .po-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.25rem 1.75rem;
        border-bottom: 1.5px solid #f0ece5;
        background: #fdfcfa;
    }

    .po-card-header .card-icon {
        width: 32px;
        height: 32px;
        background: #b5622a;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    .po-card-header h2 {
        font-family: 'Fraunces', serif;
        font-size: 1.05rem;
        font-weight: 600;
        color: #1a1714;
        margin: 0;
    }

    .po-card-header .step-badge {
        margin-left: auto;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #9e9690;
        background: #f0ece5;
        padding: 0.2rem 0.65rem;
        border-radius: 20px;
    }

    .po-card-body { 
        padding: 1.6rem; 
    }

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
    
    /* ── Form Grid ───────────────────────────────────── */
    .form-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .form-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
    }

    @media (max-width: 768px) {
        .form-grid-2, .form-grid-4 { grid-template-columns: 1fr; }
    }
    @media (min-width: 769px) and (max-width: 960px) {
        .form-grid-4 { grid-template-columns: 1fr 1fr; }
    }

    .form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
    @media (max-width: 900px) {
        .form-grid-3 { grid-template-columns: 1fr; }
    }

    .sec-gap { margin-top: 2rem; }

    /* ── Field ───────────────────────────────────────── */
    .field {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .field label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6b6560;
    }

    .field label .req {
        color: #b5622a;
        margin-left: 2px;
    }

    .field input,
    .field select,
    .field textarea {
        width: 100%;
        padding: 0.65rem 0.9rem;
        border: 1.5px solid #e0dbd3;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.9rem;
        font-weight: 400;
        color: #1a1714;
        background: #fdfcfa;
        outline: none;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        -webkit-appearance: none;
        appearance: none;
    }

    .field select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.85rem center;
        padding-right: 2.5rem;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        border-color: #b5622a;
        box-shadow: 0 0 0 3px rgba(181, 98, 42, 0.1);
        background: #ffffff;
    }

    .field input[readonly] {
        background: #f5f3ef;
        color: #9e9690;
        cursor: default;
    }

    .field textarea {
        resize: vertical;
        min-height: 80px;
        line-height: 1.55;
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


    /* ── Autocomplete ────────────────────────────────── */
    .autocomplete-wrapper {
        position: relative;
    }

    .autocomplete-list {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1.5px solid #e0dbd3;
        border-radius: 8px;
        list-style: none;
        padding: 0.3rem;
        margin: 0;
        z-index: 200;
        display: none;
        max-height: 210px;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(26,23,20,0.1);
    }

    .autocomplete-list.show { display: block; }

    .autocomplete-item {
        padding: 0.6rem 0.75rem;
        cursor: pointer;
        border-radius: 5px;
        font-size: 0.875rem;
        color: #2a2520;
        transition: background 0.15s ease;
    }

    .autocomplete-item.active {
        background: #f5f1eb;
        color: #b5622a;
    }
    .autocomplete-item:hover { background: #f5f1eb; color: #b5622a; }

    /* ── Add Item Strip ──────────────────────────────── */
    .add-item-strip {
        display: grid;
        grid-template-columns: 2fr 0.8fr 0.9fr 0.8fr 1fr auto;
        gap: 1rem;
        align-items: flex-end;
        padding: 1.25rem 1.5rem;
        background: #fdf8f3;
        border: 1.5px dashed #e0c9b5;
        border-radius: 10px;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .add-item-strip {
            grid-template-columns: 1fr 1fr;
        }
        .add-item-strip .btn-add-item {
            grid-column: span 2;
        }
    }

    .btn-add-item {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.65rem 1.25rem;
        background: #b5622a;
        color: white;
        border: none;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.18s ease, transform 0.1s ease;
        height: 42px;
    }
    .btn-add-item:hover { background: #9e521f; transform: translateY(-1px); }
    .btn-add-item:active { transform: translateY(0); }

    /* ── Items Table ─────────────────────────────────── */
    .items-table-wrap {
        border: 1.5px solid #e8e3db;
        border-radius: 10px;
        overflow: hidden;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .items-table thead tr {
        background: #f5f1eb;
    }

    .items-table thead th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #6b6560;
        border-bottom: 1.5px solid #e8e3db;
        white-space: nowrap;
    }

    .items-table thead th:last-child { text-align: center; }
    .items-table thead th.text-right { text-align: right; }

    .items-table tbody tr {
        border-bottom: 1px solid #f0ece5;
        transition: background 0.15s ease;
    }
    .items-table tbody tr:last-child { border-bottom: none; }
    .items-table tbody tr:hover { background: #fdfcfa; }

    .items-table tbody td {
        padding: 0.8rem 1rem;
        color: #2a2520;
        vertical-align: middle;
    }

    .items-table .td-material { font-weight: 500; color: #1a1714; }
    .items-table .td-num { font-variant-numeric: tabular-nums; text-align: right; }
    .items-table .td-unit {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #9e9690;
    }
    .items-table .td-total { font-weight: 600; color: #1a1714; text-align: right; }
    .items-table .td-action { text-align: center; }

    .empty-row td {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #9e9690;
        font-size: 0.875rem;
    }

    .empty-row td .empty-icon {
        display: block;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        opacity: 0.4;
    }

    /* ── Grand Total Row ─────────────────────────────── */
    .grand-total-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-top: 1.5px solid #e8e3db;
        background: #fdfcfa;
    }

    .grand-total-row .label {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b6560;
    }

    .grand-total-row .amount {
        font-family: 'Fraunces', serif;
        font-size: 1.4rem;
        font-weight: 700;
        color: #b5622a;
        font-variant-numeric: tabular-nums;
    }

    /* ── Remove Btn ──────────────────────────────────── */
    .btn-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border: 1.5px solid #f0c0b0;
        background: #fff5f0;
        color: #c0522a;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.18s ease;
    }
    .btn-remove:hover {
        background: #c0522a;
        border-color: #c0522a;
        color: white;
        transform: scale(1.05);
    }

    /* ── Action Bar ──────────────────────────────────── */
    .po-action-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1.25rem 1.75rem;
        background: #fdfcfa;
        border-top: 1.5px solid #f0ece5;
    }

    .btn-cancel {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.7rem 1.5rem;
        border: 1.5px solid #ddd9d1;
        background: white;
        color: #6b6560;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.18s ease;
    }
    .btn-cancel:hover {
        border-color: #b5622a;
        color: #b5622a;
        background: #fdf8f3;
    }

    .btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.75rem;
        background: #1a1714;
        color: white;
        border: 1.5px solid #1a1714;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s ease;
        letter-spacing: 0.02em;
    }
    .btn-submit:hover {
        background: #b5622a;
        border-color: #b5622a;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(181, 98, 42, 0.3);
    }
    .btn-submit:active { transform: translateY(0); }

    /* ── Item count badge ────────────────────────────── */
    .item-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 0.4rem;
        background: #b5622a;
        color: white;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        margin-left: 0.5rem;
        transition: all 0.2s ease;
    }

    /* ── Toast ───────────────────────────────────────── */
    .toast-container {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
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
    .po-card {
        animation: fadeUp 0.45s ease both;
    }

    .po-card:nth-of-type(1) {
        animation-delay: 0.05s;
    }

    .po-card:nth-of-type(2) {
        animation-delay: 0.15s;
    }

</style>

<div class="po-wrapper">
    <div class="po-page-header">
        <div class="title-block">
            <div class="eyebrow">Procurement</div>
            <h1>New Purchase <span>Order</span></h1>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <form method="POST" onsubmit="return validateForm()">
        <?= csrf_field() ?>

        <!-- ── Card 1: Order Details ── -->
        <div class="po-card">
            <div class="po-card-header">
                <div class="card-icon"><i class="fas fa-file-alt"></i></div>
                <h2>Order Details</h2>
                <span class="step-badge">Step 1 of 2</span>
            </div>
            <div class="po-card-body">
                <div class="sec-gap">
                    <div class="sec-label">Project Details</div>
                    <div class="form-grid-2" style="margin-bottom: 1.25rem;">
                        <div class="field">
                            <label>Project <span class="req">*</span></label>
                            <select name="project_id" required>
                                <option value="">— Select Project —</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                </div>

                <div class="sec-gap">
                    <div class="sec-label">Vendor Details</div>
                    <div class="grid-3" style="margin-bottom:1.1rem">
                        <div class="field">
                            <label>Vendor Name <span class="req">*</span></label>
                            <div class="autocomplete-wrapper">
                                <input type="text" name="vendor_name" id="vendor_name" placeholder="Add vendor…" autocomplete="off" required>
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

                <div class="sec-gap">
                    <div class="sec-label">Order Info</div>
                    <div class="form-grid-2" style="margin-bottom: 2rem;">
                        
                        <div class="field">
                            <label>Order Date <span class="req">*</span></label>
                            <input type="date" name="order_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="field">
                            <label>Expected Delivery</label>
                            <input type="date" name="expected_date">
                        </div>
                    </div>
                </div>
                    <div class="field" style="margin-bottom: 1.25rem;">
                        <label>Notes / Terms &amp; Conditions</label>
                        <textarea name="notes" rows="3" placeholder="Delivery instructions, payment terms, or special requirements…"></textarea>
                    </div>
            </div>
        </div>

        <!-- ── Card 2: Order Items ── -->
        <div class="po-card">
            <div class="po-card-header">
                <div class="card-icon"><i class="fas fa-boxes"></i></div>
                <h2>Order Items <span class="item-count-badge" id="item_count_badge" style="display:none">0</span></h2>
                <span class="step-badge">Step 2 of 2</span>
            </div>
            <div class="po-card-body">

                <!-- Add item strip -->
                <div class="add-item-strip">
                    <div class="field">
                        <label>Material Name <span class="req">*</span></label>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="material_search" placeholder="Search or add new material…" autocomplete="off">
                            <ul id="material_suggestions" class="autocomplete-list"></ul>
                        </div>
                        <input type="hidden" id="material_id">
                    </div>
                    <div class="field">
                        <label>Unit</label>
                        <select id="material_unit" class="input-modern">
                            <option value="">Select</option>
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
                        <input type="number" id="material_qty" step="0.01" placeholder="0.00" min="0">
                    </div>
                    <div class="field">
                        <label>Rate (₹)</label>
                        <input type="number" id="material_rate" step="0.01" placeholder="0.00" min="0">
                    </div>
                    <div class="field">
                        <label>GST (%)</label>
                        <input type="number" id="material_tax" step="0.01" placeholder="0.00" min="0">
                    </div>
                    <button type="button" class="btn-add-item" onclick="addItem()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>

                <!-- Items table -->
                <div class="items-table-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Rate</th>
                                <th class="text-right">GST</th>
                                <th class="text-right">Total</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody id="items_tbody">
                            <tr class="empty-row">
                                <td colspan="7">
                                    <span class="empty-icon"><i class="fas fa-shopping-basket"></i></span>
                                    No items added yet. Use the form above to add materials.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="grand-total-row">
                        <span class="label">Total Amount</span>
                        <span class="amount" id="grand_total">₹ 0.00</span>
                    </div>
                </div>

                <input type="hidden" name="items_json" id="items_json">
            </div>

            <div class="po-action-bar">
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Create Purchase Order
                </button>
            </div>
        </div>

    </form>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Data (from PHP) ────────────────────────────────────────
const materials = <?= json_encode($materials) ?>;
const vendors   = <?= json_encode($vendors) ?>;
let items = [];

// Initialize Material Autocomplete
setupAutocomplete('material_search', 'material_suggestions', 'material_id', materials, 'material_name', function(item) {
    document.getElementById('material_unit').value = item.unit || '';
    document.getElementById('material_rate').value = item.default_rate || '';
    document.getElementById('material_tax').value  = item.tax_rate || '';
});


// ── Autocomplete ───────────────────────────────────────────
function setupAutocomplete(inputId, listId, hiddenId, data, displayKey, onSelect) {
    const input  = document.getElementById(inputId);
    const list   = document.getElementById(listId);
    const hidden = document.getElementById(hiddenId);

    let currentIndex = -1;

    function closeList() {
        list.classList.remove('show');
        currentIndex = -1;
    }

    function renderList(matches) {
        list.innerHTML = '';
        if (matches.length === 0) {
            closeList();
            return;
        }

        matches.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.textContent = item[displayKey];

            li.addEventListener('click', () => {
                selectItem(item);
            });

            list.appendChild(li);
        });

        list.classList.add('show');
    }

    function selectItem(item) {
        input.value  = item[displayKey];
        hidden.value = item.id;
        closeList();
        if (onSelect) onSelect(item);
    }

    input.addEventListener('input', function () {
        const val = this.value.toLowerCase();
        // Clear hidden ID if user changes text
        hidden.value = '';
        
        if (!val) {
            closeList();
            return;
        }

        const matches = data.filter(item =>
            item[displayKey].toLowerCase().includes(val)
        );

        renderList(matches);
    });

    input.addEventListener('keydown', function (e) {
        const items = list.querySelectorAll('.autocomplete-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex = (currentIndex + 1) % items.length;
            updateActive(items);
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            updateActive(items);
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            if (currentIndex >= 0 && items[currentIndex]) {
                items[currentIndex].click();
            }
        }

        if (e.key === 'Escape') {
            closeList();
        }
    });

    function updateActive(items) {
        items.forEach(item => item.classList.remove('active'));
        if (items[currentIndex]) {
            items[currentIndex].classList.add('active');
        }
    }

    document.addEventListener('click', function (e) {
        if (!list.contains(e.target) && e.target !== input) {
            closeList();
        }
    });
}


    // Clear vendor ID if name changes
    document.getElementById('vendor_name').addEventListener('input', function() {
        document.getElementById('vendor_id').value = '';
    });

    setupAutocomplete('vendor_name', 'vendor_suggestions', 'vendor_id', vendors, 'name', (item) => {
        document.getElementById('vendor_mobile').value  = item.mobile || '';
        document.getElementById('vendor_gst').value     = item.gst_number || '';
        document.getElementById('vendor_email').value   = item.email || '';
        document.getElementById('vendor_address').value = item.address || '';
    });

// ── Add Item ───────────────────────────────────────────────
function addItem() {
    const id   = document.getElementById('material_id').value;
    const name = document.getElementById('material_search').value.trim();
    const unit = document.getElementById('material_unit').value;
    const qty  = parseFloat(document.getElementById('material_qty').value);
    const rate = parseFloat(document.getElementById('material_rate').value);
    const taxRate = parseFloat(document.getElementById('material_tax').value) || 0;

    if (!name || !unit || isNaN(qty) || qty <= 0 || isNaN(rate)) {
        alert('Please enter valid material details (Name, Unit, Qty, Rate).');
        return;
    }

    const taxable = qty * rate;
    const taxAmount = taxable * (taxRate / 100);
    const total = taxable + taxAmount;

    items.push({ 
        material_id: id, 
        material_name: name, 
        unit, 
        quantity: qty, 
        rate, 
        tax_rate: taxRate,
        tax_amount: taxAmount,
        total 
    });
    renderItems();

    // Reset add-item inputs
    document.getElementById('material_search').value = '';
    document.getElementById('material_id').value     = '';
    document.getElementById('material_unit').value   = '';
    document.getElementById('material_qty').value    = '';
    document.getElementById('material_rate').value   = '';
    document.getElementById('material_tax').value    = '';
}

// ── Render Table ───────────────────────────────────────────
function renderItems() {
    const tbody = document.getElementById('items_tbody');
    const badge = document.getElementById('item_count_badge');
    let html  = '';
    let grandTotal = 0;
    let totalTax = 0;

    if (items.length === 0) {
        html = `<tr class="empty-row">
                    <td colspan="8">
                        <span class="empty-icon"><i class="fas fa-shopping-basket"></i></span>
                        No items added yet. Use the form above to add materials.
                    </td>
                </tr>`;
        badge.style.display = 'none';
    } else {
        items.forEach((item, index) => {
            grandTotal += item.total;
            totalTax += item.tax_amount;
            
            html += `<tr>
                <td style="color:#9e9690;font-size:.75rem;padding-left:1rem">${index + 1}</td>
                <td class="td-material">${item.material_name}</td>
                <td class="td-unit">${item.unit}</td>
                <td class="td-num">${item.quantity.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                <td class="td-num">₹ ${item.rate.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                <td class="td-num">${item.tax_rate}% <br><small class="text-muted">₹${item.tax_amount.toFixed(2)}</small></td>
                <td class="td-total">₹ ${item.total.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                <td class="td-action">
                    <button type="button" class="btn-remove" onclick="removeItem(${index})" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`;
        });
        badge.textContent   = items.length;
        badge.style.display = 'inline-flex';
    }

    tbody.innerHTML = html;
    document.getElementById('grand_total').innerHTML = 
        `<small style="display:block;font-size:0.9rem;color:#6b6560;font-weight:500">Tax: ₹${totalTax.toLocaleString('en-IN', {minimumFractionDigits: 2})}</small>` +
        '₹ ' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

// ── Remove Item ────────────────────────────────────────────
function removeItem(index) {
    items.splice(index, 1);
    renderItems();
}

// ── Form Validation ────────────────────────────────────────
function validateForm() {
    if (items.length === 0) {
        alert('Please add at least one item before creating the order.');
        return false;
    }
    document.getElementById('items_json').value = JSON.stringify(items);
    return true;
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>