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
            'status' => 'active'
        ];

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
                    'gst_number' => sanitize($_POST['gst_number'] ?? ''),
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
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
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
    <form method="POST">
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
                            <option value="HVAC">HVAC</option>
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