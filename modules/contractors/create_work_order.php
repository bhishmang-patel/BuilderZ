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

$page_title = 'Create Work Order';
$current_page = 'work_orders';

// Fetch initial data
$projects = $masterService->getAllProjects();
$contractors = $db->query("SELECT id, name, mobile, address, gst_number, pan_number, contractor_type FROM parties WHERE party_type = 'contractor' AND status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/contractors/create_work_order.php');
    }

    try {
        $data = [
            'project_id' => intval($_POST['project_id']),
            'contractor_id' => intval($_POST['contractor_id']),
            'work_order_no' => sanitize($_POST['work_order_no'] ?? ''),
            'title' => sanitize($_POST['title']),
            'contract_amount' => floatval($_POST['contract_amount']),
            'gst_rate' => floatval($_POST['gst_rate']),
            'tds_percentage' => floatval($_POST['tds_percentage']),
            'status' => 'active',
            'quotation_text' => sanitize($_POST['quotation_text'] ?? '')
        ];

        // Handle Quotation File Upload
        if (!empty($_FILES['quotation_file']['name'])) {
            $upload_dir = __DIR__ . '/../../assets/documents/quotations/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES['quotation_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception("Invalid quotation file type. Allowed: PDF, JPG, PNG, DOC/DOCX.");
            }
            
            $file_name = 'WO_Quote_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
            if (move_uploaded_file($_FILES['quotation_file']['tmp_name'], $upload_dir . $file_name)) {
                $data['quotation_file'] = 'assets/documents/quotations/' . $file_name;
            }
        }
        
        // Enforce Quotation Method constraint
        $q_method = $_POST['quotation_method'] ?? 'manual';
        if ($q_method === 'manual') {
            $data['quotation_file'] = null; // Clear file if they explicitly chose manual
        } else {
            $data['quotation_text'] = '';   // Clear text if they chose upload
        }

        // Auto-Contractor Creation Logic
        $contractor_name = trim($_POST['contractor_name'] ?? '');
        if (empty($data['contractor_id']) && !empty($contractor_name)) {
            // Check if exists
            $existing = $db->query("SELECT id FROM parties WHERE name = ? AND party_type='contractor'", [$contractor_name])->fetch();
            if ($existing) {
                $data['contractor_id'] = $existing['id'];
            } else {
                // Create new via MasterService to handle validation and text formatting
                $newParty = [
                    'party_type' => 'contractor',
                    'name' => $contractor_name,
                    'mobile' => sanitize($_POST['mobile'] ?? ''),
                    'address' => sanitize($_POST['address'] ?? ''),
                    'gst_number' => empty(trim($_POST['gst_number'] ?? '')) ? null : sanitize(trim($_POST['gst_number'])),
                    'pan_number' => sanitize($_POST['pan_number'] ?? ''),
                    'contractor_type' => sanitize($_POST['contractor_type'] ?? 'General'),
                    'status' => 'active'
                ];
                $data['contractor_id'] = $masterService->createParty($newParty);
            }
        }

        if (empty($data['contractor_id'])) {
            throw new Exception("Contractor is required.");
        }

        $masterService->createWorkOrder($data);
        setFlashMessage('success', 'Work Order created successfully');
        redirect('modules/contractors/work_orders.php');

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
    }

    /* ── Page Wrapper ────────────────────────── */
    .wo-wrap { max-width: 1020px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .wo-header {
        display: flex; align-items: flex-end; justify-content: space-between;
        margin-bottom: 2.5rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        flex-wrap: wrap; gap: 1rem;
    }

    .wo-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .wo-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    .back-link {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: white; color: var(--ink-soft);
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s; border: 1.5px solid var(--border);
    }
    .back-link:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Cards ───────────────────────────────── */
    .wo-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden; margin-bottom: 1.75rem;
        animation: fadeUp 0.4s ease both;
    }
    .wo-card:nth-child(1) { animation-delay: .05s; }
    .wo-card:nth-child(2) { animation-delay: .1s; }

    .card-head {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 1.25rem 1.75rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .card-icon {
        width: 32px; height: 32px; background: #6366f1; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem; flex-shrink: 0;
    }

    .card-head h2 {
        font-family: 'Fraunces', serif; font-size: 1.05rem;
        font-weight: 600; color: var(--ink); margin: 0;
    }

    .card-body { padding: 1.75rem; }

    /* ── Section Label ───────────────────────── */
    .sec-label {
        font-size: 0.68rem; font-weight: 800; color: var(--ink-mute);
        text-transform: uppercase; letter-spacing: 0.1em;
        margin: 0 0 1rem; padding-bottom: 0.5rem;
        border-bottom: 1.5px solid var(--border-lt);
    }
    .sec-gap { margin-top: 2rem; }

    /* ── Form Grid ───────────────────────────── */
    .form-row-2 {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
    .form-row-3 {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
    @media (max-width: 768px) {
        .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
    }

    /* ── Field ───────────────────────────────── */
    .field {
        display: flex; flex-direction: column; gap: 0.45rem;
        position: relative;
    }

    .field label {
        font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft);
    }

    .field label .req {
        color: var(--accent); margin-left: 2px;
    }

    .field input,
    .field select,
    .field textarea {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
    }
    
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,0.1);
    }

    .field textarea {
        resize: vertical; min-height: 60px; font-family: 'DM Sans', sans-serif;
    }

    /* ── Autocomplete ────────────────────────── */
    .autocomplete-list {
        position: absolute; top: 100%; left: 0; right: 0;
        background: white; border: 1.5px solid var(--border);
        border-radius: 8px; max-height: 200px; overflow-y: auto;
        z-index: 200; display: none; margin-top: 0.25rem;
        box-shadow: 0 10px 25px rgba(26,23,20,0.15);
        list-style: none; padding: 0.3rem; margin-left: 0;
    }
    .autocomplete-list.show { display: block; }
    
    .autocomplete-item {
        padding: 0.65rem 0.85rem; cursor: pointer;
        font-size: 0.82rem; color: var(--ink);
        transition: background 0.13s; border-bottom: 1px solid var(--border-lt);
        border-radius: 6px; margin-bottom: 0.15rem;
    }
    .autocomplete-item:last-child { border-bottom: none; margin-bottom: 0; }
    .autocomplete-item:hover,
    .autocomplete-item.active { background: #fdfcfa; color: var(--accent); }

    /* ── Action Bar ──────────────────────────── */
    .action-bar {
        display: flex; align-items: center; justify-content: flex-end;
        gap: 0.65rem; padding: 1.25rem 1.75rem;
        background: #fdfcfa; border-top: 1.5px solid var(--border-lt);
    }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary {
        background: var(--ink); color: white; border: 1.5px solid var(--ink);
    }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,0.3); transform: translateY(-1px); }

    /* Animations */
    /* ── Upload Zone ── */
    .upload-zone {
        position: relative; border: 2px dashed var(--border); border-radius: 10px;
        background: #fdfcfa; padding: 2rem 1.5rem; text-align: center; cursor: pointer;
        transition: border-color 0.22s, background 0.22s, box-shadow 0.22s, transform 0.22s;
    }
    .upload-zone:hover {
        border-color: var(--accent); background: var(--accent-bg);
        transform: translateY(-1px); box-shadow: 0 4px 16px rgba(42,88,181,0.08);
    }
    .upload-zone.drag-over {
        border-color: var(--accent); background: var(--accent-bg);
        transform: scale(1.015); box-shadow: 0 0 0 4px rgba(42,88,181,0.12);
    }
    .upload-zone.has-file {
        border-style: solid; border-color: #10b981; background: #f0fdf4;
        padding: 1.25rem 1.5rem; transform: none; box-shadow: none;
    }
    .upload-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer;
        width: 100%; height: 100%; z-index: 1;
    }

    /* ── Default idle state ── */
    .uz-default { transition: opacity 0.18s; }
    .uz-icon {
        width: 48px; height: 48px; border-radius: 12px; background: var(--accent-bg);
        border: 1.5px solid #c7d6f5; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1rem; font-size: 1.15rem; color: var(--accent);
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), background 0.2s, box-shadow 0.2s;
    }
    .upload-zone:hover .uz-icon, .upload-zone.drag-over .uz-icon {
        transform: translateY(-4px) scale(1.1); background: #dce8fb; box-shadow: 0 6px 16px rgba(42,88,181,0.18);
    }
    .uz-title { font-size: 0.875rem; font-weight: 600; color: var(--ink); margin-bottom: 0.25rem; }
    .uz-title span { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
    .uz-sub { font-size: 0.72rem; color: var(--ink-mute); line-height: 1.6; }
    .uz-types { display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap; margin-top: 0.85rem; }
    .uz-type-badge {
        font-size: 0.6rem; font-weight: 700; letter-spacing: 0.06em; padding: 0.2rem 0.55rem;
        border-radius: 20px; background: white; border: 1.5px solid var(--border);
        color: var(--ink-mute); text-transform: uppercase;
    }

    /* ── File chosen state ── */
    .uz-file { display: none; align-items: center; gap: 0.85rem; text-align: left; position: relative; z-index: 2; }
    .upload-zone.has-file .uz-default { display: none; }
    .upload-zone.has-file .uz-file { display: flex; animation: fileIn 0.35s cubic-bezier(0.16,1,0.3,1) both; }
    @keyframes fileIn {
        from { opacity: 0; transform: translateY(8px) scale(0.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .uz-file-icon {
        width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .uz-file-icon.pdf   { background: #fef2f2; border: 1.5px solid #fca5a5; color: #ef4444; }
    .uz-file-icon.img   { background: #eff6ff; border: 1.5px solid #bfdbfe; color: #3b82f6; }
    .uz-file-icon.doc   { background: #eff6ff; border: 1.5px solid #bfdbfe; color: #2563eb; }
    .uz-file-icon.other { background: #ecfdf5; border: 1.5px solid #a7f3d0; color: #10b981; }

    .uz-file-info { flex: 1; min-width: 0; }
    .uz-file-name {
        font-size: 0.85rem; font-weight: 600; color: var(--ink);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .uz-file-meta { font-size: 0.7rem; color: var(--ink-mute); margin-top: 3px; display: flex; align-items: center; gap: 0.4rem; }
    .uz-file-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: #a7f3d0; }

    .uz-file-remove {
        width: 30px; height: 30px; border-radius: 7px; flex-shrink: 0; border: 1.5px solid #fca5a5;
        background: #fef2f2; color: #ef4444; font-size: 0.72rem; display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.18s; position: relative; z-index: 3;
    }
    .uz-file-remove:hover { background: #ef4444; border-color: #ef4444; color: white; transform: scale(1.1); }

    /* ── Progress bar ── */
    .uz-progress { height: 3px; background: #bbf7d0; border-radius: 2px; margin-top: 0.9rem; overflow: hidden; display: none; }
    .upload-zone.has-file .uz-progress { display: block; }
    .uz-progress-bar { height: 100%; background: #10b981; border-radius: 2px; width: 0%; transition: width 0.55s cubic-bezier(0.16,1,0.3,1); }

    /* ── Quotation Card ── */
    .qt-method-label {
        font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.6rem;
    }
    .qt-toggle {
        display: inline-flex; background: linear-gradient(135deg, #f5f3ef, #f0ece5);
        padding: 4px; border-radius: 10px; border: 1.5px solid var(--border);
        margin-bottom: 1.75rem; position: relative;
    }
    .qt-toggle label { cursor: pointer; margin: 0; }
    .qt-toggle input[type="radio"] { display: none; }
    .qt-tab {
        padding: 0.55rem 1.4rem; font-size: 0.78rem; border-radius: 7px;
        transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
        display: flex; align-items: center; gap: 0.45rem; white-space: nowrap;
        font-weight: 500; color: var(--ink-mute); background: transparent;
    }
    .qt-tab.active {
        font-weight: 600; color: var(--ink); background: white;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.02);
    }
    .qt-tab i { font-size: 0.65rem; }
    .qt-tab.active i { color: var(--accent); }

    .qt-field-label {
        display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.1rem;
    }
    .qt-field-label i {
        font-size: 0.55rem; color: var(--accent); opacity: 0.65;
    }
    .qt-textarea {
        width: 100%; padding: 1rem 1.1rem; border: 1.5px solid var(--border);
        border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.875rem;
        color: var(--ink); background: #fdfcfa; outline: none; resize: vertical;
        line-height: 1.7;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .qt-textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,0.08);
    }
    .qt-hint {
        display: flex; align-items: center; gap: 0.35rem;
        font-size: 0.68rem; color: var(--ink-mute); margin-top: 0.15rem;
    }
    .qt-hint i { font-size: 0.55rem; opacity: 0.45; }
</style>

<div class="wo-wrap">

    <!-- Header -->
    <div class="wo-header">
        <div>
            <div class="eyebrow">Contract Management</div>
            <h1>Create Work Order</h1>
        </div>
        <a href="work_orders.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <!-- Form -->
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <!-- Card 1: Project & Contractor -->
        <div class="wo-card">
            <div class="card-head">
                <div class="card-icon"><i class="fas fa-file-contract"></i></div>
                <h2>Project & Contractor</h2>
            </div>
            <div class="card-body">
                <div class="sec-label">Primary Information</div>
                <div class="form-row-2">
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

                <div class="sec-gap"></div>
                <div class="sec-label">Contractor Details</div>
                
                <div class="form-row-3">
                    <div class="field">
                        <label>Contractor Name <span class="req">*</span></label>
                        <input type="text" name="contractor_name" id="contractor_name" placeholder="Search or add..." autocomplete="off" required>
                        <ul id="contractor_suggestions" class="autocomplete-list"></ul>
                        <input type="hidden" name="contractor_id" id="contractor_id">
                    </div>
                    <div class="field">
                        <label>Mobile</label>
                        <input type="text" name="mobile" id="mobile" placeholder="Mobile Number">
                    </div>
                    <div class="field">
                        <label>Trade / Type</label>
                        <select name="contractor_type" id="contractor_type">
                            <option value="General">General</option>
                            <option value="Civil">Civil</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="Fabrication">Fabrication</option>
                            <option value="Tiles">Tiles</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row-3" style="margin-top:1.25rem">
                    <div class="field">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" id="gst_number" placeholder="Optional">
                    </div>
                    <div class="field">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" id="pan_number" placeholder="Required for TDS">
                    </div>
                    <div class="field">
                        <label>Address</label>
                        <input type="text" name="address" id="address" placeholder="Location/City">
                    </div>
                </div>


            </div>
        </div>

        <!-- Card 3: Quotation & Scope -->
        <div class="wo-card">
            <div class="card-head">
                <div class="card-icon" style="background:var(--accent-lt); color:var(--accent)"><i class="fas fa-file-signature"></i></div>
                <h2>Quotation & Scope of Work</h2>
            </div>
            <div class="card-body">
                
                <!-- Method selector label -->
                <div class="qt-method-label">Choose Input Method</div>

                <!-- Segmented Toggle -->
                <div class="qt-toggle">
                    <label>
                        <input type="radio" name="quotation_method" value="manual" checked onchange="toggleQuoteMethod(this.value)">
                        <div class="qt-tab active" id="tab_manual">
                            <i class="fas fa-pen-nib"></i> Manual Entry
                        </div>
                    </label>
                    <label>
                        <input type="radio" name="quotation_method" value="upload" onchange="toggleQuoteMethod(this.value)">
                        <div class="qt-tab" id="tab_upload">
                            <i class="fas fa-cloud-arrow-up"></i> Upload File
                        </div>
                    </label>
                </div>

                <!-- Manual Entry Section -->
                <div id="quote_manual_sec" class="field" style="margin-bottom: 0;">
                    <label class="qt-field-label">
                        <i class="fas fa-align-left"></i>
                        Scope of Work / Quotation Text
                    </label>
                    <textarea name="quotation_text" rows="5" class="qt-textarea" placeholder="Describe the scope of work, terms & conditions, material specifications, or paste the full quotation text here..."></textarea>
                    <div class="qt-hint">
                        <i class="fas fa-info-circle"></i>
                        This will be included in the Work Order and can be referenced later.
                    </div>
                </div>
                
                <!-- File Upload Section -->
                <div id="quote_upload_sec" class="field" style="display: none; margin-bottom: 0;">
                    <label class="qt-field-label">
                        <i class="fas fa-paperclip"></i>
                        Quotation Document
                    </label>
                    
                    <div class="upload-zone" id="uploadZone"
                         ondragover="handleDragOver(event,'uploadZone')"
                         ondragleave="handleDragLeave(event,'uploadZone')"
                         ondrop="handleDrop(event,'uploadZone','fileInput')">

                        <input type="file" id="fileInput" name="quotation_file"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                               onchange="handleFileSelect(this,'uploadZone')">

                        <!-- Default state -->
                        <div class="uz-default">
                          <div class="uz-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                          <div class="uz-title"><span>Click to upload</span> or drag &amp; drop</div>
                          <div class="uz-sub">Max file size ~5 MB</div>
                          <div class="uz-types">
                            <span class="uz-type-badge">PDF</span>
                            <span class="uz-type-badge">JPG</span>
                            <span class="uz-type-badge">PNG</span>
                            <span class="uz-type-badge">DOC</span>
                          </div>
                        </div>

                        <!-- File chosen state -->
                        <div class="uz-file">
                          <div class="uz-file-icon other" id="uzFileIcon"><i class="fas fa-file"></i></div>
                          <div class="uz-file-info">
                            <div class="uz-file-name" id="uzFileName">—</div>
                            <div class="uz-file-meta">
                              <span id="uzFileSize">—</span>
                              <span class="dot"></span>
                              <span id="uzFileType">—</span>
                            </div>
                          </div>
                          <!-- Use a button with specific z-index/click events to block bubble to input file -->
                          <button type="button" class="uz-file-remove" onclick="removeFile(event, 'uploadZone', 'fileInput')" title="Remove file">
                            <i class="fas fa-times"></i>
                          </button>
                        </div>

                        <div class="uz-progress"><div class="uz-progress-bar" id="uzProgressBar"></div></div>
                    </div>
                </div>

                <script>
                function toggleQuoteMethod(method) {
                    const tabManual = document.getElementById('tab_manual');
                    const tabUpload = document.getElementById('tab_upload');
                    const secManual = document.getElementById('quote_manual_sec');
                    const secUpload = document.getElementById('quote_upload_sec');

                    if (method === 'manual') {
                        tabManual.classList.add('active');
                        tabUpload.classList.remove('active');
                        secManual.style.display = 'flex';
                        secUpload.style.display = 'none';
                        // clear file input
                        document.querySelector('input[name="quotation_file"]').value = '';
                    } else {
                        tabUpload.classList.add('active');
                        tabManual.classList.remove('active');
                        secUpload.style.display = 'flex';
                        secManual.style.display = 'none';
                        // clear text area
                        document.querySelector('textarea[name="quotation_text"]').value = '';
                    }
                }

                function handleFileSelect(input, zoneId) {
                    if (input.files && input.files[0]) showFile(input.files[0], zoneId);
                }

                function handleDragOver(e, zoneId) {
                    e.preventDefault();
                    document.getElementById(zoneId).classList.add('drag-over');
                }

                function handleDragLeave(e, zoneId) {
                    document.getElementById(zoneId).classList.remove('drag-over');
                }

                function handleDrop(e, zoneId, inputId) {
                    e.preventDefault();
                    const zone = document.getElementById(zoneId);
                    zone.classList.remove('drag-over');
                    const file = e.dataTransfer.files[0];
                    if (file) {
                      const dt = new DataTransfer();
                      dt.items.add(file);
                      document.getElementById(inputId).files = dt.files;
                      showFile(file, zoneId);
                    }
                }

                function showFile(file, zoneId) {
                    const zone = document.getElementById(zoneId);
                    zone.querySelector('#uzFileName').textContent = file.name;
                    const size = file.size < 1024 * 1024
                      ? (file.size / 1024).toFixed(1) + ' KB'
                      : (file.size / (1024*1024)).toFixed(2) + ' MB';
                    zone.querySelector('#uzFileSize').textContent = size;
                    const ext = file.name.split('.').pop().toLowerCase();
                    zone.querySelector('#uzFileType').textContent = ext.toUpperCase();

                    const iconEl = zone.querySelector('#uzFileIcon');
                    iconEl.className = 'uz-file-icon';
                    if (ext === 'pdf') {
                      iconEl.classList.add('pdf'); iconEl.innerHTML = '<i class="fas fa-file-pdf"></i>';
                    } else if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                      iconEl.classList.add('img'); iconEl.innerHTML = '<i class="fas fa-file-image"></i>';
                    } else if (['doc','docx'].includes(ext)) {
                      iconEl.classList.add('doc'); iconEl.innerHTML = '<i class="fas fa-file-word"></i>';
                    } else {
                      iconEl.classList.add('other'); iconEl.innerHTML = '<i class="fas fa-file"></i>';
                    }

                    zone.classList.add('has-file');
                    // Hide file input covering to allow remove button click securely
                    document.getElementById('fileInput').style.display = 'none';

                    const bar = zone.querySelector('#uzProgressBar');
                    bar.style.width = '0%';
                    setTimeout(() => { bar.style.width = '100%'; }, 60);
                }

                function removeFile(e, zoneId, inputId) {
                    e.preventDefault(); e.stopPropagation();
                    const zone = document.getElementById(zoneId);
                    zone.classList.remove('has-file');
                    document.getElementById('fileInput').style.display = 'block';
                    document.getElementById(inputId).value = '';
                    zone.querySelector('#uzProgressBar').style.width = '0%';
                }
                </script>
            </div>
        </div>
        
        <!-- Card 2: Contract Details -->
        <div class="wo-card">
            <div class="card-head">
                <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
                <h2>Contract Details</h2>
            </div>
            <div class="card-body">
                <div class="form-row-2">
                    <div class="field">
                        <label>Work Order Title <span class="req">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. Civil Work Phase 1 - Foundation">
                    </div>
                    <div class="field">
                        <label>Work Order No.</label>
                        <input type="text" name="work_order_no" placeholder="Auto-generated if empty">
                    </div>
                </div>

                <div class="sec-gap"></div>
                <div class="sec-label">Financials</div>
                <div class="form-row-3">
                    <div class="field">
                        <label>Contract Value (₹) <span class="req">*</span></label>
                        <input type="number" name="contract_amount" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="field">
                        <label>GST Rate (%)</label>
                        <select name="gst_rate">
                            <option value="0">0%</option>
                            <option value="6">6%</option>
                            <option value="12">12%</option>
                            <option value="18" selected>18%</option>
                            <option value="28">28%</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>TDS Rate (%)</label>
                        <select name="tds_percentage">
                            <option value="0">0%</option>
                            <option value="1" selected>1% (Individual/HUF)</option>
                            <option value="2">2% (Company/Firm)</option>
                            <option value="5">5%</option>
                            <option value="10">10%</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="action-bar">
                <a href="work_orders.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Create Work Order
                </button>
            </div>
        </div>

    </form>

</div>

<script>
const contractors = <?= json_encode($contractors) ?>;

// Autocomplete Setup
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

        if (e.key === 'Escape') closeList();
    });

    function updateActive(items) {
        items.forEach(item => item.classList.remove('active'));
        if (items[currentIndex]) items[currentIndex].classList.add('active');
    }

    document.addEventListener('click', function (e) {
        if (!list.contains(e.target) && e.target !== input) closeList();
    });
}

// Initialize
setupAutocomplete('contractor_name', 'contractor_suggestions', 'contractor_id', contractors, 'name', (item) => {
    document.getElementById('mobile').value  = item.mobile || '';
    document.getElementById('gst_number').value = item.gst_number || '';
    document.getElementById('address').value = item.address || '';
    if(item.pan_number) document.getElementById('pan_number').value = item.pan_number;
    if(item.contractor_type) document.getElementById('contractor_type').value = item.contractor_type;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>