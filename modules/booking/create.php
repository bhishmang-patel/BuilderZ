<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/BookingService.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();
checkPermission(['admin', 'project_manager']);

$db           = Database::getInstance();
$page_title   = 'Create Booking';
$current_page = 'booking';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/booking/create.php');
    }
    $bookingService = new BookingService();
    $result = $bookingService->createBooking($_POST, $_SESSION['user_id']);
    if ($result['success']) {
        require_once __DIR__ . '/../../includes/NotificationService.php';
        $ns = new NotificationService();
        $notifTitle = "New Booking Created";
        $notifMsg   = "Booking for Flat ID {$_POST['flat_id']} has been created successfully.";
        $notifLink  = BASE_URL . "modules/booking/view.php?id=" . $result['booking_id'];
        // Notify Admins + Sales team
        $ns->notifyUsersWithPermission('sales', $notifTitle, $notifMsg . " (Created by " . $_SESSION['username'] . ")", 'info', $notifLink);
        setFlashMessage('success', $result['message']);
        redirect('modules/booking/view.php?id=' . $result['booking_id']);
    } else {
        setFlashMessage('error', $result['message']);
    }
}

$customers      = $db->query("SELECT id, name, mobile, email, address FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();
$projects       = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$stage_of_works = $db->query("SELECT * FROM stage_of_work WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$available_flats= $db->query("SELECT f.id, f.flat_no, f.area_sqft, f.total_value, f.unit_type, p.project_name, p.id as project_id
                               FROM flats f JOIN projects p ON f.project_id = p.id
                               WHERE f.status = 'available'
                               ORDER BY p.project_name, f.flat_no")->fetchAll();

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/CrmService.php';

$lead = null;
if (isset($_GET['lead_id'])) {
    $crm  = CrmService::getInstance();
    $lead = $crm->getLead($_GET['lead_id']);
}
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
        --accent-bg: #f0f5ff;
        --accent-lt: #eff4ff;
    }

    /* ── Page Wrapper ────────────────────────── */
    .be-wrap { max-width: 1100px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .be-header {
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
    .be-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .be-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    /* ── Lead Banner ─────────────────────────── */
    .lead-banner {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.75rem 1.1rem; margin-bottom: 1.5rem;
        background: var(--accent-lt); border: 1.5px solid #c7d9f9;
        border-radius: 10px; font-size: 0.82rem; color: var(--accent);
    }
    .lead-banner strong { font-weight: 800; }

    /* ── Layout ──────────────────────────────── */
    .be-grid { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; }
    @media (max-width: 1024px) { .be-grid { grid-template-columns: 1fr; } }

    /* ── Cards ───────────────────────────────── */
    .be-card {
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
        font-family: 'DM Sans', sans-serif;
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,0.1);
    }
    .field input[readonly], .field select:disabled {
        background: #f0ece5; color: var(--ink-mute); cursor: not-allowed;
    }
    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    @media (max-width: 640px) { .field-row { grid-template-columns: 1fr; } }

    /* ── Autocomplete ────────────────────────── */
    .ac-wrap { position: relative; }
    .ac-list {
        position: absolute; z-index: 100; top: 100%; left: 0; right: 0;
        background: white; border: 1.5px solid var(--border);
        border-radius: 8px; margin-top: 0.25rem;
        max-height: 240px; overflow-y: auto;
        box-shadow: 0 10px 25px rgba(26,23,20,0.1); display: none;
    }
    .ac-list.show { display: block; }
    .ac-item {
        padding: 0.65rem 0.85rem; cursor: pointer;
        transition: background 0.12s; border-bottom: 1px solid var(--border-lt);
    }
    .ac-item:last-child { border-bottom: none; }
    .ac-item:hover, .ac-item.active { background: var(--accent-bg); }
    .ac-item strong { display: block; color: var(--ink); }
    .ac-item small  { color: var(--ink-mute); font-size: 0.72rem; }

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
    .sum-head .sp {
        font-size: 0.7rem; text-transform: uppercase;
        letter-spacing: 0.1em; opacity: 0.7; margin-bottom: 0.3rem;
    }
    .sum-head .sn { font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700; }
    .sum-head .sa { font-size: 0.72rem; opacity: 0.45; margin-top: 0.2rem; }

    .sum-body { padding: 1.5rem; }
    .sum-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.6rem 0; border-bottom: 1px solid var(--border-lt);
    }
    .sum-row:last-child { border-bottom: none; }
    .sum-label { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }
    .sum-val   { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

    .sum-row input[type="number"] {
        width: 110px; padding: 0.35rem 0.6rem; border: 1.5px solid var(--border);
        border-radius: 6px; text-align: right; font-size: 0.8rem;
        background: #f0ece5; color: var(--ink-mute); outline: none;
        cursor: not-allowed; font-family: 'DM Sans', sans-serif;
    }

    .sum-total {
        background: #ecfdf5; border: 1.5px dashed #10b981;
        border-radius: 10px; padding: 1rem;
        text-align: center; margin-top: 1rem;
    }
    .sum-total .st-lbl {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: #065f46; margin-bottom: 0.3rem;
    }
    .sum-total .st-val {
        font-family: 'Fraunces', serif; font-size: 1.4rem;
        font-weight: 700; color: #065f46;
    }

    /* ── Buttons ─────────────────────────────── */
    .btn-row {
        display: flex; justify-content: flex-end; gap: 0.75rem;
        margin-top: 1.5rem; padding-top: 1.5rem;
        border-top: 1.5px solid var(--border-lt);
    }
    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px; font-size: 0.875rem;
        font-weight: 600; cursor: pointer; transition: all 0.18s;
        display: inline-flex; align-items: center; gap: 0.5rem;
        text-decoration: none; font-family: 'DM Sans', sans-serif;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
    .btn-primary { background: var(--ink); color: white; border: 1.5px solid var(--ink); }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }

    .btn-submit {
        width: 100%; margin-top: 1.25rem; padding: 0.85rem;
        background: var(--ink); color: white; border: none;
        border-radius: 8px; font-size: 0.875rem; font-weight: 700;
        cursor: pointer; transition: all 0.18s; font-family: 'DM Sans', sans-serif;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-submit:hover { background: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,0.3); transform: translateY(-1px); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="be-wrap">

    <!-- ── Header ──────────────────────────────── -->
    <div class="be-header">
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

    <?php if ($lead): ?>
    <div class="lead-banner">
        <i class="fas fa-link"></i>
        Converting lead &mdash;
        <strong><?= htmlspecialchars($lead['full_name']) ?></strong>
        (<?= htmlspecialchars($lead['mobile']) ?>)
    </div>
    <?php endif; ?>

    <form method="POST" id="bookingForm" action="create.php">
        <?= csrf_field() ?>

        <div class="be-grid">

            <!-- ── Left Column ─────────────────────── -->
            <div>

                <!-- Customer Card -->
                <div class="be-card" style="margin-bottom:1.5rem; animation-delay:0s;">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Customer Name *</label>
                                <div class="ac-wrap">
                                    <input type="text" name="customer_name" id="customer_name"
                                           placeholder="Search or type name" autocomplete="off" required
                                           value="<?= $lead ? htmlspecialchars($lead['full_name']) : '' ?>">
                                    <ul id="customer_suggestions" class="ac-list"></ul>
                                </div>
                                <input type="hidden" name="customer_id" id="customer_id">
                                <input type="hidden" name="lead_id" value="<?= $lead ? $lead['id'] : '' ?>">
                            </div>
                            <div class="field">
                                <label>Referred By</label>
                                <input type="text" name="referred_by" placeholder="Optional"
                                       value="<?= $lead ? htmlspecialchars($lead['source']) : '' ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Mobile Number *</label>
                                <input type="text" name="mobile" id="cust_mobile"
                                       placeholder="10-digit number" required
                                       pattern="\d{10}" maxlength="10" minlength="10"
                                       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                       value="<?= $lead ? htmlspecialchars($lead['mobile']) : '' ?>">
                            </div>
                            <div class="field">
                                <label>Email Address</label>
                                <input type="email" name="email" id="cust_email"
                                       placeholder="optional@email.com"
                                       value="<?= $lead ? htmlspecialchars($lead['email'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <div class="field">
                            <label>Address</label>
                            <input type="text" name="address" id="cust_address"
                                   placeholder="City, Area"
                                   value="<?= $lead ? htmlspecialchars($lead['address'] ?? '') : '' ?>">
                        </div>

                    </div>
                </div>

                <!-- Property Card -->
                <div class="be-card" style="margin-bottom:1.5rem; animation-delay:0.08s;">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Property &amp; Booking Details</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Select Project *</label>
                                <select name="project_id" id="project_select" required onchange="filterFlats()">
                                    <option value="">Choose Project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>">
                                            <?= htmlspecialchars($project['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Select Unit *</label>
                                <select name="flat_id" id="flat_select" required onchange="updateFlatDetails()" disabled>
                                    <option value="">Select project first</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label>Payment Plan (Stage of Work)</label>
                            <select name="stage_of_work_id">
                                <option value="">— No Plan Selected —</option>
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
                <div class="be-card" style="animation-delay:0.16s;">
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

            <!-- ── Summary Sidebar ──────────────────── -->
            <div>
                <div class="sum-card">
                    <div class="sum-head">
                        <div class="sp" id="display_project_name">SELECT PROJECT</div>
                        <div class="sn" id="display_flat_no">— — —</div>
                        <div class="sa" id="display_area_sub">— sqft</div>
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
const allFlats  = <?= json_encode($available_flats) ?>;

/* ── Autocomplete ─────────────────── */
function setupAutocomplete(inputId, listId, data, onSelect) {
    const input = document.getElementById(inputId);
    const list  = document.getElementById(listId);
    let focus   = -1;

    function addActive(items) {
        if (!items || !items.length) return;
        Array.from(items).forEach(i => i.classList.remove('active'));
        if (focus >= items.length) focus = 0;
        if (focus < 0) focus = items.length - 1;
        items[focus].classList.add('active');
        items[focus].scrollIntoView({ block: 'nearest' });
    }
    function render(matches) {
        list.innerHTML = '';
        if (!matches.length) { list.classList.remove('show'); return; }
        matches.forEach(item => {
            const li = document.createElement('li');
            li.className = 'ac-item';
            li.innerHTML = `<strong>${item.name}</strong><small>${item.mobile || ''}</small>`;
            li.onclick = () => { onSelect(item); list.classList.remove('show'); };
            list.appendChild(li);
        });
        list.classList.add('show');
    }
    function filter(val) {
        focus = -1;
        const matches = val
            ? data.filter(d => d.name.toLowerCase().includes(val.toLowerCase()) || (d.mobile && d.mobile.includes(val)))
            : data.slice(0, 15);
        render(matches);
    }

    input.addEventListener('input',   () => filter(input.value));
    input.addEventListener('focus',   () => filter(input.value));
    input.addEventListener('keydown', e => {
        const items = list.getElementsByClassName('ac-item');
        if (!list.classList.contains('show')) return;
        if (e.keyCode === 40) { focus++; addActive(items); e.preventDefault(); }
        else if (e.keyCode === 38) { focus--; addActive(items); e.preventDefault(); }
        else if (e.keyCode === 13) {
            e.preventDefault();
            if (focus > -1 && items[focus]) items[focus].click();
            else if (items.length === 1) items[0].click();
        }
    });
    document.addEventListener('click', e => { if (e.target !== input) list.classList.remove('show'); });
}

setupAutocomplete('customer_name', 'customer_suggestions', customers, function(c) {
    document.getElementById('customer_name').value  = c.name;
    document.getElementById('customer_id').value    = c.id;
    document.getElementById('cust_mobile').value    = c.mobile  || '';
    document.getElementById('cust_email').value     = c.email   || '';
    document.getElementById('cust_address').value   = c.address || '';
});
document.getElementById('customer_name').addEventListener('input', () => {
    document.getElementById('customer_id').value = '';
});

/* ── Flat filter ──────────────────── */
function filterFlats() {
    const projSel = document.getElementById('project_select');
    const projId  = projSel.value;
    const projTxt = projId ? projSel.options[projSel.selectedIndex].text : 'SELECT PROJECT';
    const fs      = document.getElementById('flat_select');

    document.getElementById('display_project_name').textContent = projTxt;
    document.getElementById('display_flat_no').textContent      = '— — —';
    document.getElementById('display_area_sub').textContent     = '— sqft';
    document.getElementById('display_area').textContent         = '— sqft';
    document.getElementById('display_area').setAttribute('data-area', 0);

    fs.innerHTML = '<option value="">Select Unit</option>';

    if (!projId) {
        fs.disabled = true;
        fs.innerHTML = '<option value="">Select project first</option>';
        calculateFinancials();
        return;
    }

    const projectFlats = allFlats
        .filter(f => f.project_id == projId)
        .sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, { numeric: true, sensitivity: 'base' }));

    if (!projectFlats.length) {
        fs.disabled = true;
        fs.innerHTML = '<option value="">No available units</option>';
        return;
    }

    fs.disabled = false;
    projectFlats.forEach(f => {
        const o = document.createElement('option');
        o.value = f.id;
        o.setAttribute('data-area',    f.area_sqft);
        o.setAttribute('data-value',   f.total_value);
        o.setAttribute('data-flat-no', f.flat_no);
        o.textContent = `${f.flat_no}${f.unit_type ? ' (' + f.unit_type + ')' : ''} — ${parseFloat(f.area_sqft).toFixed(0)} sqft`;
        fs.appendChild(o);
    });
    calculateFinancials();
}

function updateFlatDetails() {
    const fs = document.getElementById('flat_select');
    const o  = fs.options[fs.selectedIndex];
    if (o.value) {
        const area  = o.getAttribute('data-area');
        const value = o.getAttribute('data-value');
        const no    = o.getAttribute('data-flat-no');
        document.getElementById('display_flat_no').textContent        = no;
        document.getElementById('display_area_sub').textContent       = parseFloat(area).toFixed(2) + ' sqft';
        document.getElementById('display_area').textContent           = parseFloat(area).toFixed(2) + ' sqft';
        document.getElementById('display_area').setAttribute('data-area', area);
        document.getElementById('agreement_value').value              = value;
    } else {
        document.getElementById('display_flat_no').textContent        = '— — —';
        document.getElementById('display_area_sub').textContent       = '— sqft';
        document.getElementById('display_area').textContent           = '— sqft';
        document.getElementById('display_area').setAttribute('data-area', 0);
        document.getElementById('agreement_value').value              = '';
    }
    calculateFinancials();
}

/* ── Financials ───────────────────── */
function calculateFinancials() {
    const ag   = parseFloat(document.getElementById('agreement_value').value) || 0;
    const area = parseFloat(document.getElementById('display_area').getAttribute('data-area')) || 0;
    const sd   = Math.round(ag * 0.06);
    const reg  = ag >= 3000000 ? 30000 : Math.round(ag * 0.01);
    const gst  = Math.round(ag * 0.01);
    const dev  = parseFloat(document.getElementById('development_charge').value) || 0;
    const park = parseFloat(document.getElementById('parking_charge').value)     || 0;
    const soc  = parseFloat(document.getElementById('society_charge').value)     || 0;
    const total= ag - (dev + park + soc) - sd - reg - gst;

    document.getElementById('display_agreement_value').textContent = '₹ ' + ag.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('stamp_duty_registration').value       = sd.toFixed(2);
    document.getElementById('registration_amount').value           = reg.toFixed(2);
    document.getElementById('gst_amount').value                    = gst.toFixed(2);
    document.getElementById('display_total_cost').textContent      = '₹ ' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('booking_rate').value = area > 0 ? (total / area).toFixed(2) : '';
}

/* ── URL params ───────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const p      = new URLSearchParams(window.location.search);
    const projId = p.get('project_id');
    const flatId = p.get('flat_id');
    if (projId) {
        document.getElementById('project_select').value = projId;
        filterFlats();
        if (flatId) setTimeout(() => {
            document.getElementById('flat_select').value = flatId;
            updateFlatDetails();
        }, 100);
    }
    calculateFinancials();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>