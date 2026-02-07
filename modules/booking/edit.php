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
    // CSRF Check
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

        // Validation
        if ($agreement_value <= 0) {
            throw new Exception("Agreement value must be greater than 0");
        }

        if (empty($customer_id)) {
            throw new Exception("Customer is required");
        }

        // Update Booking
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

        // Audit Log
        logAudit('update', 'bookings', $booking_id, $booking, $update_data);

        setFlashMessage('success', 'Booking updated successfully');
        redirect('modules/booking/view.php?id=' . $booking_id);

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

// Get all customers for autocomplete
$customers = $db->query("SELECT id, name, mobile, email, address FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();

// Get active Stage of Work Templates
$stage_of_works = $db->query("SELECT * FROM stage_of_work ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
    /* Premium Page Styles */
    .edit-booking-container {
        padding: 40px;
        max-width: 1600px;
        margin: 0 auto;
        background: #f8fafc;
        min-height: 100vh;
        align-items: center;
        justify-content: center;
    }

    .page-header-premium {
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title h1 {
        font-size: 24px;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
        letter-spacing: -0.5px;
    }

    .page-title p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 14px;
    }

    .content-wrapper {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    /* Form Styles */
    .form-section-title {
        font-size: 12px;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #f1f5f9;
        margin-top: 2px;
    }

    .input-group-modern {
        margin-bottom: 24px;
    }

    .input-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
    }

    .modern-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
        transition: all 0.2s ease;
        background: #f8fafc;
        outline: none;
    }

    .modern-input:focus {
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    }
    
    .modern-input[readonly] {
        background: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
        border-color: #cbd5e1;
    }

    .form-grid-premium {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    /* Summary Card */
    .summary-card-premium {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        position: sticky;
        top: 20px;
    }

    .summary-header-premium {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 30px;
        color: white;
        text-align: center;
    }

    .summary-details-premium {
        padding: 30px;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
        align-items: center;
        font-size: 14px;
    }
    
    .summary-label { color: #64748b; font-weight: 500; }
    .summary-val { color: #1e293b; font-weight: 700; }
    
    .modern-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
    }
    .btn-primary-glow {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        width: 100%;
        margin-top: 20px;
        height: 48px;
    }
    .btn-primary-glow:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
    }
    
    .btn-secondary-flat {
        background: transparent;
        color: #64748b;
        border: 1px solid #e2e8f0;
        width: 100%;
        margin-top: 12px;
        height: 48px;
    }
    .btn-secondary-flat:hover {
        background: #f8fafc;
        color: #334155;
        border-color: #cbd5e1;
    }
</style>

<div class="edit-booking-container">
    <div class="page-header-premium">
        <div class="page-title">
            <h1>Edit Booking Details</h1>
            <p>Update customer info, financials, and charges for Flat <?= htmlspecialchars($booking['flat_no']) ?></p>
        </div>
        <a href="index.php" class="modern-btn btn-secondary-flat" style="width: auto; height: 40px; margin: 0; background: #fff;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <form method="POST" id="bookingForm">
        <?= csrf_field() ?>
        
        <div class="row">
            <!-- LEFT COLUMN: Forms -->
            <div class="col-lg-6" style="padding-right: 40px; border-right: 1px solid #f1f5f9;">
                <div class="content-wrapper" style="padding: 40px;">
                    
                    <!-- Customer Section -->
                    <div class="form-section-title"><i class="fas fa-user-circle"></i> Customer Information</div>
                    <div class="form-grid-premium">
                        <div class="input-group-modern">
                            <label class="input-label">Customer Name</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" id="customer_name" class="modern-input" placeholder="Search customer" autocomplete="off" required value="<?= htmlspecialchars($booking['customer_name']) ?>">
                                <ul id="customer_suggestions" class="autocomplete-list"></ul>
                            </div>
                            <input type="hidden" name="customer_id" id="customer_id" value="<?= $booking['current_customer_id'] ?>">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Referred By</label>
                            <input type="text" name="referred_by" class="modern-input" placeholder="Referrer Name" value="<?= htmlspecialchars($booking['referred_by'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid-premium">
                        <div class="input-group-modern">
                            <label class="input-label">Mobile Number</label>
                            <input type="text" id="cust_mobile" class="modern-input" readonly value="<?= htmlspecialchars($booking['customer_mobile']) ?>">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Email Address</label>
                            <input type="text" id="cust_email" class="modern-input" readonly value="<?= htmlspecialchars($booking['customer_email']) ?>">
                        </div>
                    </div>

                    <!-- Booking Section -->
                    <div class="form-section-title" style="margin-top: 40px;"><i class="fas fa-building"></i> Property Details</div>
                    <div class="form-grid-premium">
                        <div class="input-group-modern">
                            <label class="input-label">Project</label>
                            <input type="text" class="modern-input" value="<?= htmlspecialchars($booking['project_name']) ?>" readonly>
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Flat No</label>
                            <input type="text" class="modern-input" value="<?= htmlspecialchars($booking['flat_no']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="input-group-modern">
                        <label class="input-label" style="color: #4f46e5; font-weight: 700;">Payment Plan (Stage of Work)</label>
                        <select name="stage_of_work_id" class="modern-select" style="border-color: #4f46e5; background: #eef2ff;">
                            <option value="">-- No Plan Selected --</option>
                            <?php foreach ($stage_of_works as $plan): ?>
                                <option value="<?= $plan['id'] ?>" <?= $booking['stage_of_work_id'] == $plan['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plan['name']) ?> (<?= $plan['total_stages'] ?> Stages)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid-premium">
                        <div class="input-group-modern">
                            <label class="input-label">Booking Date</label>
                            <input type="date" name="booking_date" class="modern-input" required value="<?= $booking['booking_date'] ?>">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Agreement Value (₹)</label>
                            <input type="number" name="agreement_value" id="agreement_value" class="modern-input" style="font-weight: 700;" step="0.01" required value="<?= $booking['agreement_value'] ?>" oninput="calculateFinancials()">
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div class="form-section-title" style="margin-top: 40px;"><i class="fas fa-minus-circle"></i> Additional Charges</div>
                    <div class="form-grid-premium">
                        <div class="input-group-modern">
                            <label class="input-label">Development Charge</label>
                            <input type="number" name="development_charge" id="development_charge" class="modern-input" placeholder="0.00" step="0.01" value="<?= $booking['development_charge'] ?? '' ?>" oninput="calculateFinancials()">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Parking Charge</label>
                            <input type="number" name="parking_charge" id="parking_charge" class="modern-input" placeholder="0.00" step="0.01" value="<?= $booking['parking_charge'] ?? '' ?>" oninput="calculateFinancials()">
                        </div>
                    </div>
                    <div class="input-group-modern" style="max-width: 48%;">
                        <label class="input-label">Society Charge</label>
                        <input type="number" name="society_charge" id="society_charge" class="modern-input" placeholder="0.00" step="0.01" value="<?= $booking['society_charge'] ?? '' ?>" oninput="calculateFinancials()">
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: Summary -->
            <div class="col-lg-8">
                <div class="summary-card-premium" style="min-width: 500px;">
                    <div class="summary-header-premium">
                        <div style="font-size: 11px; text-transform: uppercase; opacity: 0.7; letter-spacing: 1px; font-weight: 700;"><?= htmlspecialchars($booking['project_name']) ?></div>
                        <div style="font-size: 32px; font-weight: 800; margin-top: 5px; line-height: 1;"><?= htmlspecialchars($booking['flat_no']) ?></div>
                    </div>
                    <div class="summary-details-premium">
                        <div class="summary-row">
                            <span class="summary-label">Area (sqft)</span>
                            <span class="summary-val" id="display_area" data-area="<?= $booking['area_sqft'] ?>"><?= number_format($booking['area_sqft'], 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Rate (₹/sqft)</span>
                            <input type="number" name="rate" id="booking_rate" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly value="<?= $booking['rate'] ?? '' ?>">
                        </div>
                        <div style="height: 1px; background: #f1f5f9; margin: 16px 0;"></div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Agreement Value</span>
                            <span class="summary-val" id="display_agreement_value">₹ 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Stamp Duty (6%)</span>
                            <input type="number" name="stamp_duty_registration" id="stamp_duty_registration" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly value="<?= $booking['stamp_duty_registration'] ?? '' ?>">
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Registration (1%)</span>
                            <input type="number" name="registration_amount" id="registration_amount" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly value="<?= $booking['registration_amount'] ?? '' ?>">
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">GST (1%)</span>
                            <input type="number" name="gst_amount" id="gst_amount" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly value="<?= $booking['gst_amount'] ?? '' ?>">
                        </div>

                        <div style="background: #f0fdf4; border: 1px dashed #22c55e; border-radius: 12px; padding: 16px; text-align: center; margin-top: 20px;">
                            <div style="font-size: 11px; color: #15803d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Est. Total Cost</div>
                            <div id="display_total_cost" style="font-size: 20px; font-weight: 800; color: #166534;">₹ 0.00</div>
                        </div>

                        <button type="submit" class="modern-btn btn-primary-glow">
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
            li.className = 'autocomplete-item';
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
        let items = list.getElementsByClassName('autocomplete-item');
        if (!list.classList.contains('show')) return;
        if (e.keyCode == 40) { currentFocus++; addActive(items); e.preventDefault(); }
        else if (e.keyCode == 38) { currentFocus--; addActive(items); e.preventDefault(); }
        else if (e.keyCode == 13) { e.preventDefault(); if (currentFocus > -1 && items[currentFocus]) items[currentFocus].click(); else if (items.length === 1) items[0].click(); }
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
    
    // Update Agreement Value Display
    document.getElementById('display_agreement_value').textContent = '₹ ' + agreementValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Calculate Stamp Duty (6%) - Rounded
    const stampDuty = Math.round(agreementValue * 0.06);
    document.getElementById('stamp_duty_registration').value = stampDuty.toFixed(2);
    
    // Calculate Registration (1% with Cap) - Rounded
    let registration = Math.round(agreementValue * 0.01);
    if (agreementValue >= 3000000) {
        registration = 30000;
    }
    document.getElementById('registration_amount').value = registration.toFixed(2);

    // Calculate GST (1%) - Rounded
    const gst = Math.round(agreementValue * 0.01);
    document.getElementById('gst_amount').value = gst.toFixed(2);
    
    // Get Charges
    const devCharge = parseFloat(document.getElementById('development_charge').value) || 0;
    const parkingCharge = parseFloat(document.getElementById('parking_charge').value) || 0;
    const societyCharge = parseFloat(document.getElementById('society_charge').value) || 0;
    const totalCharges = devCharge + parkingCharge + societyCharge;

    // Calculate Total Cost (Net)
    // Total Cost = Agreement Value - Charges - Stamp - Reg - GST (Using formula from index.php)
    const totalCost = agreementValue - totalCharges - stampDuty - registration - gst;
    document.getElementById('display_total_cost').textContent = '₹ ' + totalCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Calculate Rate = Total Cost / Area
    if (area > 0) {
        const rate = totalCost / area;
        document.getElementById('booking_rate').value = rate.toFixed(2);
    } else {
        document.getElementById('booking_rate').value = '';
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    calculateFinancials();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
