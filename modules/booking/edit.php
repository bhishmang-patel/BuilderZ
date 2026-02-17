<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();
$page_title = 'Edit Booking';
$current_page = 'booking';

$booking_id = intval($_GET['id'] ?? 0);

// Fetch booking details
$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft,
               p.name as customer_name,
               p.mobile as customer_mobile,
               p.email as customer_email,
               p.address as customer_address,
               p.id as current_customer_id,
               pr.project_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        WHERE b.id = ?";

$stmt = $db->query($sql, [$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlashMessage('error', 'Booking not found');
    redirect('modules/booking/index.php');
}

if ($booking['status'] === 'cancelled') {
    setFlashMessage('warning', 'Cancelled bookings cannot be edited.');
    redirect('modules/booking/view.php?id=' . $booking_id);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/booking/edit.php?id=' . $booking_id);
    }

    try {
        $customer_id = intval($_POST['customer_id']);
        $agreement_value = floatval($_POST['agreement_value']);
        $booking_date = $_POST['booking_date'];
        $referred_by = sanitize($_POST['referred_by']);
        $rate = floatval($_POST['rate']);
        $stamp_duty_registration = floatval($_POST['stamp_duty_registration']);
        $registration_amount = floatval($_POST['registration_amount']);
        $gst_amount = floatval($_POST['gst_amount']);
        $development_charge = floatval($_POST['development_charge']);
        $parking_charge = floatval($_POST['parking_charge']);
        $society_charge = floatval($_POST['society_charge']);

        if ($agreement_value <= 0) {
            throw new Exception("Agreement value must be greater than 0");
        }

        if (empty($customer_id)) {
            throw new Exception("Customer is required");
        }

        $update_data = [
            'customer_id' => $customer_id,
            'agreement_value' => $agreement_value,
            'booking_date' => $booking_date,
            'referred_by' => $referred_by,
            'rate' => $rate,
            'stamp_duty_registration' => $stamp_duty_registration,
            'registration_amount' => $registration_amount,
            'gst_amount' => $gst_amount,
            'development_charge' => $development_charge,
            'parking_charge' => $parking_charge,
            'society_charge' => $society_charge,
            'stage_of_work_id' => !empty($_POST['stage_of_work_id']) ? intval($_POST['stage_of_work_id']) : null
        ];

        $db->update('bookings', $update_data, 'id = ?', ['id' => $booking_id]);

        logAudit('update', 'bookings', $booking_id, $booking, $update_data);

        setFlashMessage('success', 'Booking updated successfully');
        redirect('modules/booking/view.php?id=' . $booking_id);

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

$customers = $db->query("SELECT id, name, mobile, email, address FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();
$stage_of_works = $db->query("SELECT * FROM stage_of_work ORDER BY name ASC")->fetchAll();

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

    /* ── Form Sections ───────────────────────── */
    .sec-title {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        margin-bottom: 1rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .sec-title i { font-size: 0.75rem; color: var(--accent); }

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

<div class="be-wrap">

    <!-- Header -->
    <div class="be-header">
        <div class="header-left">
            <a href="view.php?id=<?= $booking_id ?>" class="back-btn" title="Back to Booking">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="eyebrow">Booking #<?= $booking_id ?></div>
                <h1>Edit Booking Details</h1>
            </div>
        </div>
    </div>

    <form method="POST" id="bookingForm">
        <?= csrf_field() ?>

        <div class="be-grid">

            <!-- Main Form -->
            <div>

                <!-- Customer Card -->
                <div class="be-card" style="margin-bottom:1.5rem">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Customer Name *</label>
                                <div class="ac-wrap">
                                    <input type="text" id="customer_name" placeholder="Search customer" 
                                           autocomplete="off" required value="<?= htmlspecialchars($booking['customer_name']) ?>">
                                    <ul id="customer_suggestions" class="ac-list"></ul>
                                </div>
                                <input type="hidden" name="customer_id" id="customer_id" value="<?= $booking['current_customer_id'] ?>">
                            </div>
                            <div class="field">
                                <label>Referred By</label>
                                <input type="text" name="referred_by" placeholder="Referrer Name" 
                                       value="<?= htmlspecialchars($booking['referred_by'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Mobile Number</label>
                                <input type="text" id="cust_mobile" readonly value="<?= htmlspecialchars($booking['customer_mobile']) ?>">
                            </div>
                            <div class="field">
                                <label>Email Address</label>
                                <input type="text" id="cust_email" readonly value="<?= htmlspecialchars($booking['customer_email']) ?>">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Property Card -->
                <div class="be-card" style="margin-bottom:1.5rem">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Property & Booking Details</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Project</label>
                                <input type="text" value="<?= htmlspecialchars($booking['project_name']) ?>" readonly>
                            </div>
                            <div class="field">
                                <label>Flat No</label>
                                <input type="text" value="<?= htmlspecialchars($booking['flat_no']) ?>" readonly>
                            </div>
                        </div>

                        <div class="field">
                            <label>Payment Plan (Stage of Work)</label>
                            <select name="stage_of_work_id">
                                <option value="">— No Plan Selected —</option>
                                <?php foreach ($stage_of_works as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" <?= $booking['stage_of_work_id'] == $plan['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plan['name']) ?> (<?= $plan['total_stages'] ?> Stages)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Booking Date *</label>
                                <input type="date" name="booking_date" required value="<?= $booking['booking_date'] ?>">
                            </div>
                            <div class="field">
                                <label>Agreement Value (₹) *</label>
                                <input type="number" name="agreement_value" id="agreement_value" 
                                       step="0.01" required value="<?= $booking['agreement_value'] ?>" 
                                       oninput="calculateFinancials()">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Charges Card -->
                <div class="be-card">
                    <div class="card-header">
                        <h3><i class="fas fa-minus-circle"></i> Additional Charges</h3>
                    </div>
                    <div class="card-body">

                        <div class="field-row">
                            <div class="field">
                                <label>Development Charge</label>
                                <input type="number" name="development_charge" id="development_charge" 
                                       placeholder="0.00" step="0.01" value="<?= $booking['development_charge'] ?? '' ?>" 
                                       oninput="calculateFinancials()">
                            </div>
                            <div class="field">
                                <label>Parking Charge</label>
                                <input type="number" name="parking_charge" id="parking_charge" 
                                       placeholder="0.00" step="0.01" value="<?= $booking['parking_charge'] ?? '' ?>" 
                                       oninput="calculateFinancials()">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Society Charge</label>
                                <input type="number" name="society_charge" id="society_charge" 
                                       placeholder="0.00" step="0.01" value="<?= $booking['society_charge'] ?? '' ?>" 
                                       oninput="calculateFinancials()">
                            </div>
                            <div class="field"></div>
                        </div>

                        <div class="btn-row">
                            <a href="view.php?id=<?= $booking_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Save Changes
                            </button>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Summary Sidebar -->
            <div>
                <div class="sum-card">
                    <div class="sum-head">
                        <div class="sp"><?= htmlspecialchars($booking['project_name']) ?></div>
                        <div class="sn"><?= htmlspecialchars($booking['flat_no']) ?></div>
                    </div>
                    <div class="sum-body">
                        <div class="sum-row">
                            <span class="sum-label">Area</span>
                            <span class="sum-val" id="display_area" data-area="<?= $booking['area_sqft'] ?>">
                                <?= number_format($booking['area_sqft'], 2) ?> sqft
                            </span>
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Rate (₹/sqft)</span>
                            <input type="number" name="rate" id="booking_rate" readonly value="<?= $booking['rate'] ?? '' ?>">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Agreement Value</span>
                            <span class="sum-val" id="display_agreement_value">₹ 0.00</span>
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Stamp Duty (6%)</span>
                            <input type="number" name="stamp_duty_registration" id="stamp_duty_registration" readonly value="<?= $booking['stamp_duty_registration'] ?? '' ?>">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">Registration (1%)</span>
                            <input type="number" name="registration_amount" id="registration_amount" readonly value="<?= $booking['registration_amount'] ?? '' ?>">
                        </div>
                        <div class="sum-row">
                            <span class="sum-label">GST (1%)</span>
                            <input type="number" name="gst_amount" id="gst_amount" readonly value="<?= $booking['gst_amount'] ?? '' ?>">
                        </div>

                        <div class="sum-total">
                            <div class="st-lbl">Est. Total Cost</div>
                            <div class="st-val" id="display_total_cost">₹ 0.00</div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check-circle"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </form>

</div>

<script>
const customers = <?= json_encode($customers) ?>;

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
});

document.getElementById('customer_name').addEventListener('input', function() {
    document.getElementById('customer_id').value = '';
});

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

document.addEventListener('DOMContentLoaded', function() {
    calculateFinancials();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>