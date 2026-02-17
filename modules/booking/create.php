<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/BookingService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Create Booking';
$current_page = 'booking';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/booking/create.php');
    }

    $bookingService = new BookingService();
    $result = $bookingService->createBooking($_POST, $_SESSION['user_id']);

    if ($result['success']) {
        
        // ── Notification Trigger ──
        require_once __DIR__ . '/../../includes/NotificationService.php';
        $ns = new NotificationService();
        $notifTitle = "New Booking Created";
        $notifMsg   = "Booking for Flat ID {$_POST['flat_id']} has been created successfully.";
        $notifLink  = BASE_URL . "modules/booking/view.php?id=" . $result['booking_id'];
        
        // Notify current user (or Admin user ID 1)
        $ns->create($_SESSION['user_id'], $notifTitle, $notifMsg, 'success', $notifLink);
        if ($_SESSION['user_id'] != 1) {
             $ns->create(1, $notifTitle, $notifMsg . " (Created by " . $_SESSION['username'] . ")", 'info', $notifLink);
        }

        setFlashMessage('success', $result['message']);
        redirect('modules/booking/view.php?id=' . $result['booking_id']);
    } else {
        setFlashMessage('error', $result['message']);
    }
}

$customers = $db->query("SELECT id, name, mobile, email, address FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$stage_of_works = $db->query("SELECT * FROM stage_of_work WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$available_flats = $db->query("SELECT f.id, f.flat_no, f.area_sqft, f.total_value, p.project_name, p.id as project_id
                                FROM flats f
                                JOIN projects p ON f.project_id = p.id
                                WHERE f.status = 'available'
                                ORDER BY p.project_name, f.flat_no")->fetchAll();

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
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    /* ── Page Wrapper ────────────────────────── */
    .bc-wrap { max-width: 1100px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .bc-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .header-left { display: flex; align-items: center; gap: 0.75rem; }
    .back-btn {
        width: 38px; height: 38px; border-radius: 9px;
        background: var(--surface); border: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        color: var(--ink-soft); text-decoration: none;
        transition: all 0.18s; flex-shrink: 0;
    }
    .back-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .bc-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .bc-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    /* ── Layout ──────────────────────────────── */
    .bc-grid { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; }
    @media (max-width: 1024px) { .bc-grid { grid-template-columns: 1fr; } }

    /* ── Cards ───────────────────────────────── */
    .bc-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.4s ease both;
    }

    .card-header {
        padding: 1.15rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .card-header h3 {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
        color: var(--ink); margin: 0; display: flex; align-items: center; gap: 0.6rem;
    }
    .card-header h3 i { font-size: 0.85rem; color: var(--accent); }

    .card-body { padding: 1.5rem; }

    /* ── Form Fields ─────────────────────────── */
    .field { margin-bottom: 1.1rem; }
    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select {
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
    .field input:focus, .field select:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }
    .field input[readonly] { background: #f0ece5; color: var(--ink-mute); cursor: not-allowed; }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    @media (max-width: 640px) { .field-row { grid-template-columns: 1fr; } }

    /* Autocomplete */
    .ac-wrap { position: relative; }
    .ac-list {
        position: absolute; z-index: 100; top: 100%; left: 0; right: 0;
        background: white; border: 1.5px solid var(--border);
        border-radius: 8px; margin-top: 0.25rem;
        max-height: 240px; overflow-y: auto;
        box-shadow: 0 10px 25px rgba(26,23,20,0.1);
        display: none;
    }
    .ac-list.show { display: block; }
    .ac-item {
        padding: 0.65rem 0.85rem; cursor: pointer;
        transition: background 0.12s;
        border-bottom: 1px solid var(--border-lt);
    }
    .ac-item:last-child { border-bottom: none; }
    .ac-item:hover, .ac-item.active { background: var(--accent-bg); }
    .ac-item strong { color: var(--ink); }

    /* ── Summary Card ────────────────────────── */
    .sum-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.5s 0.1s ease both;
    }
    .sum-head {
        padding: 1.5rem; background: linear-gradient(135deg, var(--ink) 0%, #3e3936 100%);
        color: white; text-align: center;
    }
    .sum-head .sp { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.7; margin-bottom: 0.3rem; }
    .sum-head .sn { font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700; }

    .sum-body { padding: 1.5rem; }
    .sum-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.6rem 0; border-bottom: 1px solid var(--border-lt);
    }
    .sum-row:last-child { border-bottom: none; }
    .sum-label { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }
    .sum-val { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

    .sum-row input[type="number"] {
        width: 100px; padding: 0.35rem 0.6rem; border: 1.5px solid var(--border);
        border-radius: 6px; text-align: right; font-size: 0.8rem;
        background: white; outline: none; transition: border-color 0.15s;
    }
    .sum-row input[type="number"]:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(181,98,42,0.1); }

    .sum-total {
        background: #ecfdf5; border: 1.5px dashed #10b981;
        border-radius: 10px; padding: 1rem;
        text-align: center; margin-top: 1rem;
    }
    .sum-total .st-lbl {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: #065f46; margin-bottom: 0.3rem;
    }
    .sum-total .st-val { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; color: #065f46; }

    /* ── Buttons ─────────────────────────────── */
    .btn-row {
        display: flex; justify-content: flex-end; gap: 0.75rem;
        margin-top: 1.5rem; padding-top: 1.5rem;
        border-top: 1.5px solid var(--border-lt);
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
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.3); }

    .btn-submit {
        width: 100%; margin-top: 1.25rem; padding: 0.85rem;
        background: var(--ink); color: white; border: none;
        border-radius: 8px; font-size: 0.875rem; font-weight: 700;
        cursor: pointer; transition: all 0.18s;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-submit:hover { background: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.3); transform: translateY(-1px); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="bc-wrap">

    <!-- Header -->
    <div class="bc-header">
        <div class="header-left">
            <a href="index.php" class="back-btn" title="Back to Bookings">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="eyebrow">New Reservation</div>
                <h1>Create Booking</h1>
            </div>
        </div>
    </div>

    <form method="POST" id="bookingForm" action="create.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_booking">

        <div class="bc-grid">

            <!-- Main Form -->
            <div>

                <!-- Customer Card -->
                <div class="bc-card" style="margin-bottom:1.5rem">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Customer Name *</label>
                                <div class="ac-wrap">
                                    <input type="text" name="customer_name" id="customer_name" 
                                           placeholder="Search or type name" autocomplete="off" required>
                                    <ul id="customer_suggestions" class="ac-list"></ul>
                                </div>
                                <input type="hidden" name="customer_id" id="customer_id">
                            </div>
                            <div class="field">
                                <label>Referred By</label>
                                <input type="text" name="referred_by" placeholder="Optional">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Mobile Number *</label>
                                <input type="text" name="mobile" id="cust_mobile" 
                                       placeholder="10-digit number" required
                                       pattern="\d{10}" maxlength="10" minlength="10"
                                       oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            </div>
                            <div class="field">
                                <label>Email Address</label>
                                <input type="email" name="email" id="cust_email" 
                                       placeholder="optional@email.com">
                            </div>
                        </div>

                        <div class="field">
                            <label>Address</label>
                            <input type="text" name="address" id="cust_address" 
                                   placeholder="City, Area">
                        </div>

                    </div>
                </div>

                <!-- Property Card -->
                <div class="bc-card" style="margin-bottom:1.5rem">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Property & Booking Details</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Select Project *</label>
                                <select name="project_id" id="project_select" required onchange="filterFlats()">
                                    <option value="">Choose Project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Select Flat *</label>
                                <select name="flat_id" id="flat_select" required onchange="updateFlatDetails()" disabled>
                                    <option value="">Select project first</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label>Payment Plan (Stage of Work)</label>
                            <select name="stage_of_work_id">
                                <option value="">Optional</option>
                                <?php foreach ($stage_of_works as $plan): ?>
                                    <option value="<?= $plan['id'] ?>">
                                        <?= htmlspecialchars($plan['name']) ?> (<?= $plan['total_stages'] ?> Stages)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Booking Date *</label>
                                <input type="date" name="booking_date" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="field">
                                <label>Agreement Value (₹) *</label>
                                <input type="number" name="agreement_value" id="agreement_value" 
                                       step="0.01" required placeholder="0.00"
                                       oninput="calculateFinancials()">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Charges Card -->
                <div class="bc-card">
                    <div class="card-header">
                        <h3><i class="fas fa-minus-circle"></i> Additional Charges</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Development Charge</label>
                                <input type="number" name="development_charge" id="development_charge" 
                                       placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                            </div>
                            <div class="field">
                                <label>Parking Charge</label>
                                <input type="number" name="parking_charge" id="parking_charge" 
                                       placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Society Charge</label>
                                <input type="number" name="society_charge" id="society_charge" 
                                       placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                            </div>
                            <div class="field"></div>
                        </div>

                        <div class="btn-row">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Create Booking
                            </button>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Summary Sidebar -->
            <div>
                <div class="sum-card">
                    <div class="sum-head">
                        <div class="sp" id="display_project_name">SELECT PROJECT</div>
                        <div class="sn" id="display_flat_no">---</div>
                    </div>
                    <div class="sum-body">
                        <div class="sum-row">
                            <span class="sum-label">Area</span>
                            <span class="sum-val" id="display_area" data-area="0">— sqft</span>
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Rate (₹/sqft)</span>
                            <input type="number" name="rate" id="booking_rate" readonly placeholder="0.00">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Agreement Value</span>
                            <span class="sum-val" id="display_agreement_value">₹ 0.00</span>
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Stamp Duty (6%)</span>
                            <input type="number" name="stamp_duty_registration" id="stamp_duty_registration" readonly placeholder="0.00">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Registration (1%)</span>
                            <input type="number" name="registration_amount" id="registration_amount" readonly placeholder="0.00">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">GST (1%)</span>
                            <input type="number" name="gst_amount" id="gst_amount" readonly placeholder="0.00">
                        </div>

                        <div class="sum-total">
                            <div class="st-lbl">Est. Total Cost</div>
                            <div class="st-val" id="display_total_cost">₹ 0.00</div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check-circle"></i> Create Booking
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </form>

</div>

<script>
const customers = <?= json_encode($customers) ?>;
const allFlats = <?= json_encode($available_flats) ?>;

// Autocomplete
function setupAutocomplete(inputId, listId, data, onSelect) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    let currentFocus = -1;
    
    function closeAllLists(elmnt) { if (elmnt !== input) list.classList.remove('show'); }
    function addActive(items) {
        if (!items || items.length === 0) return false;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        items[currentFocus].classList.add("active");
        items[currentFocus].scrollIntoView({ block: 'nearest' });
    }
    function removeActive(items) { for (let i = 0; i < items.length; i++) items[i].classList.remove("active"); }
    function renderList(matches) {
        list.innerHTML = '';
        if (matches.length === 0) { list.classList.remove('show'); return; }
        matches.forEach(item => {
            const li = document.createElement('li');
            li.className = 'ac-item';
            li.innerHTML = `<strong>${item.name}</strong>`;
            li.onclick = function() { onSelect(item); list.classList.remove('show'); };
            list.appendChild(li);
        });
        list.classList.add('show');
    }
    function filterAndShow(val) {
        let matches = [];
        if (!val) matches = data.slice(0, 15);
        else matches = data.filter(d => d.name.toLowerCase().includes(val.toLowerCase()));
        renderList(matches);
        currentFocus = -1;
    }
    
    input.addEventListener('input', function() { filterAndShow(this.value); });
    input.addEventListener('focus', function() { filterAndShow(this.value); });
    input.addEventListener('keydown', function(e) {
        let items = list.getElementsByClassName('ac-item');
        if (!list.classList.contains('show')) return;
        if (e.keyCode == 40) { currentFocus++; addActive(items); e.preventDefault(); }
        else if (e.keyCode == 38) { currentFocus--; addActive(items); e.preventDefault(); }
        else if (e.keyCode == 13) { 
            e.preventDefault(); 
            if (currentFocus > -1 && items[currentFocus]) items[currentFocus].click(); 
            else if (items.length === 1) items[0].click(); 
        }
    });
    document.addEventListener('click', function(e) { if (e.target !== input) closeAllLists(e.target); });
}

setupAutocomplete('customer_name', 'customer_suggestions', customers, function(customer) {
    document.getElementById('customer_name').value = customer.name;
    document.getElementById('customer_id').value = customer.id;
    document.getElementById('cust_mobile').value = customer.mobile || '';
    document.getElementById('cust_email').value = customer.email || '';
    document.getElementById('cust_address').value = customer.address || '';
});

document.getElementById('customer_name').addEventListener('input', function() {
    document.getElementById('customer_id').value = '';
});

// Flat Selection
function filterFlats() {
    const projectId = document.getElementById('project_select').value;
    const flatSelect = document.getElementById('flat_select');
    
    document.getElementById('display_project_name').textContent = projectId 
        ? document.getElementById('project_select').options[document.getElementById('project_select').selectedIndex].text 
        : 'SELECT PROJECT';
    document.getElementById('display_flat_no').textContent = '---';
    document.getElementById('display_area').textContent = '— sqft';
    document.getElementById('display_area').setAttribute('data-area', 0);
    
    flatSelect.innerHTML = '<option value="">Select Flat</option>';
    
    if (!projectId) {
        flatSelect.disabled = true;
        flatSelect.innerHTML = '<option value="">Select project first</option>';
        calculateFinancials();
        return;
    }
    
    const projectFlats = allFlats.filter(flat => flat.project_id == projectId)
        .sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, {numeric: true, sensitivity: 'base'}));
    
    if (projectFlats.length === 0) {
        flatSelect.disabled = true;
        flatSelect.innerHTML = '<option value="">No available flats</option>';
        return;
    }
    
    flatSelect.disabled = false;
    projectFlats.forEach(flat => {
        const option = document.createElement('option');
        option.value = flat.id;
        option.setAttribute('data-area', flat.area_sqft);
        option.setAttribute('data-value', flat.total_value);
        option.setAttribute('data-flat-no', flat.flat_no);
        option.textContent = `${flat.flat_no} - ${parseFloat(flat.area_sqft).toFixed(0)} sqft`;
        flatSelect.appendChild(option);
    });
    calculateFinancials();
}

function updateFlatDetails() {
    const select = document.getElementById('flat_select');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const area = option.getAttribute('data-area');
        const value = option.getAttribute('data-value');
        const flatNo = option.getAttribute('data-flat-no');
        
        document.getElementById('display_flat_no').textContent = flatNo;
        document.getElementById('display_area').textContent = parseFloat(area).toFixed(2) + ' sqft';
        document.getElementById('display_area').setAttribute('data-area', area);
        document.getElementById('agreement_value').value = value;
    } else {
        document.getElementById('display_flat_no').textContent = '---';
        document.getElementById('display_area').textContent = '— sqft';
        document.getElementById('display_area').setAttribute('data-area', 0);
        document.getElementById('agreement_value').value = '';
    }
    calculateFinancials();
}

// Financial Calculations
function calculateFinancials() {
    const agreementValue = parseFloat(document.getElementById('agreement_value').value) || 0;
    const area = parseFloat(document.getElementById('display_area').getAttribute('data-area')) || 0;
    
    document.getElementById('display_agreement_value').textContent = '₹ ' + agreementValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const stampDuty = Math.round(agreementValue * 0.06);
    document.getElementById('stamp_duty_registration').value = stampDuty.toFixed(2);
    
    let registration = Math.round(agreementValue * 0.01);
    if (agreementValue >= 3000000) registration = 30000;
    document.getElementById('registration_amount').value = registration.toFixed(2);

    const gst = Math.round(agreementValue * 0.01);
    document.getElementById('gst_amount').value = gst.toFixed(2);
    
    const devCharge = parseFloat(document.getElementById('development_charge').value) || 0;
    const parkingCharge = parseFloat(document.getElementById('parking_charge').value) || 0;
    const societyCharge = parseFloat(document.getElementById('society_charge').value) || 0;
    const totalCharges = devCharge + parkingCharge + societyCharge;

    const totalCost = agreementValue - totalCharges - stampDuty - registration - gst;
    document.getElementById('display_total_cost').textContent = '₹ ' + totalCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    if (area > 0) {
        const rate = totalCost / area;
        document.getElementById('booking_rate').value = rate.toFixed(2);
    } else {
        document.getElementById('booking_rate').value = '';
    }
}

// Check for query params
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    const flatId = urlParams.get('flat_id');
    
    if (projectId) {
        const projectSelect = document.getElementById('project_select');
        projectSelect.value = projectId;
        filterFlats();
        
        if (flatId) {
            setTimeout(() => {
                document.getElementById('flat_select').value = flatId;
                updateFlatDetails();
            }, 100);
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>