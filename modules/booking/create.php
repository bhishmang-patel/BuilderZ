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
$page_title = 'Create Booking';
$current_page = 'booking';

// Fetch data
$customers = $db->query("SELECT id, name, mobile, email, address FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$available_flats = $db->query("SELECT f.id, f.flat_no, f.area_sqft, f.total_value, p.project_name, p.id as project_id
                                FROM flats f
                                JOIN projects p ON f.project_id = p.id
                                WHERE f.status = 'available'
                                ORDER BY p.project_name, f.flat_no")->fetchAll();

require_once __DIR__ . '/../../includes/BookingService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/booking/create.php');
    }


    $bookingService = new BookingService();
    $result = $bookingService->createBooking($_POST, $_SESSION['user_id']);

    if ($result['success']) {
        setFlashMessage('success', $result['message']);
        redirect('modules/booking/view.php?id=' . $result['booking_id']);
    } else {
        setFlashMessage('error', $result['message']);
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
    /* Styling for Sticky Summary */
    .sticky-summary {
        position: sticky;
        top: 20px;
    }
    
    .summary-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #e1e7ef;
        overflow: hidden;
    }

    .summary-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        padding: 1.5rem;
        color: white;
    }

    .summary-project-name {
        font-size: 0.9rem;
        opacity: 0.8;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .summary-flat-no {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
    }
    
    .summary-body {
        padding: 1.5rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    .summary-row.total-row {
        border-top: 2px dashed #e2e8f0;
        margin-top: 1rem;
        padding-top: 1rem;
        font-weight: 700;
        font-size: 1.1rem;
        color: #11998e;
    }

    .summary-label {
        color: #64748b;
    }

    .summary-value {
        font-weight: 600;
        color: #1e293b;
    }
    
    .calculation-input {
        background: transparent;
        border: none;
        text-align: right;
        width: 120px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .calculation-input:focus {
        outline: none;
    }
</style>

<div class="create-booking-container">
    <form method="POST" id="bookingForm" onsubmit="return validateForm()">
        <?= csrf_field() ?>
        
        <div class="row">
            <!-- LEFT COLUMN: Forms -->
            <div class="col-lg-8">
                
                <!-- Customer Information Card -->
                <div class="info-card">
                    <div class="card-header-custom">
                        <div class="card-icon icon-customer">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h2 class="card-title-custom">Customer Information</h2>
                    </div>
                    <div class="card-body-custom">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Customer Name *</label>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" name="customer_name" id="customer_name" class="form-control-custom" placeholder="Search or enter customer" autocomplete="off" required>
                                        <ul id="customer_suggestions" class="autocomplete-list"></ul>
                                    </div>
                                    <input type="hidden" name="customer_id" id="customer_id">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Mobile Number *</label>
                                    <input type="text" name="mobile" id="customer_mobile" class="form-control-custom" placeholder="Enter mobile" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Email Address</label>
                                    <input type="email" name="email" id="customer_email" class="form-control-custom" placeholder="Enter email">
                                </div>
                            </div>
                             <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Referred By</label>
                                    <input type="text" name="referred_by" id="referred_by" class="form-control-custom" placeholder="Enter referrer name (Optional)">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Address</label>
                                    <input type="text" name="address" id="customer_address" class="form-control-custom" placeholder="Enter complete address">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Details Card -->
                <div class="info-card">
                    <div class="card-header-custom">
                        <div class="card-icon icon-flat">
                            <i class="fas fa-building"></i>
                        </div>
                        <h2 class="card-title-custom">Booking Details</h2>
                    </div>
                    <div class="card-body-custom">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Select Project *</label>
                                    <select name="project_id" id="project_select" class="form-control-custom" required onchange="filterFlats()">
                                        <option value="">Choose Project First</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Select Flat *</label>
                                    <select name="flat_id" id="flat_select" class="form-control-custom" required onchange="updateFlatDetails()" disabled>
                                        <option value="">Select project first</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                             <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Booking Date *</label>
                                    <input type="date" name="booking_date" class="form-control-custom" required value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Agreement Value (₹) *</label>
                                    <input type="number" name="agreement_value" id="agreement_value" class="form-control-custom" placeholder="0.00" step="0.01" required oninput="calculateFinancials()">
                                    <small class="text-muted">Enter the base agreement value.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Deduction Card -->
                <div class="info-card">
                    <div class="card-header-custom">
                        <div class="card-icon icon-flat" style="background: rgba(245, 87, 108, 0.1); color: #f5576c;">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <h2 class="card-title-custom">Deduction</h2>
                    </div>
                    <div class="card-body-custom">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Development Charge</label>
                                    <input type="number" name="development_charge" id="development_charge" class="form-control-custom" placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Parking Charge</label>
                                    <input type="number" name="parking_charge" id="parking_charge" class="form-control-custom" placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-custom mb-0">
                                    <label class="form-label-custom">Society Charge</label>
                                    <input type="number" name="society_charge" id="society_charge" class="form-control-custom" placeholder="0.00" step="0.01" oninput="calculateFinancials()">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-left mt-4 mb-5">
                    <a href="index.php" class="btn-secondary-custom" style="margin-right: 1rem;">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>
            </div>

            <!-- RIGHT COLUMN: Summary (Sticky) -->
            <div class="col-lg-4">
                <div class="sticky-summary">
                    <div class="summary-card">
                        <div class="summary-header">
                            <div class="summary-project-name" id="display_project_name">Select Project</div>
                            <h3 class="summary-flat-no" id="display_flat_no">---</h3>
                        </div>
                        <div class="summary-body">
                            <div class="summary-row">
                                <span class="summary-label">Area</span>
                                <span class="summary-value" id="display_area">- sqft</span>
                            </div>
                            <hr style="border-top: 1px solid #f1f5f9;">
                            
                            <div class="summary-row">
                                <span class="summary-label">Rate (₹/sqft)</span>
                                <input type="number" name="rate" id="booking_rate" class="calculation-input" placeholder="0.00" readonly tabindex="-1">
                            </div>
                            
                            <div class="summary-row">
                                <span class="summary-label">Agreement Value</span>
                                <span class="summary-value" id="display_agreement_value">₹ 0.00</span>
                            </div>
                            
                            <div class="summary-row">
                                <span class="summary-label">Stamp Duty</span>
                                <input type="number" name="stamp_duty_registration" id="stamp_duty_registration" class="calculation-input" placeholder="0.00" readonly tabindex="-1">
                            </div>
                            
                            <div class="summary-row">
                                <span class="summary-label">Registration</span>
                                <input type="number" name="registration_amount" id="registration_amount" class="calculation-input" placeholder="0.00" readonly tabindex="-1">
                            </div>

                            <div class="summary-row">
                                <span class="summary-label">GST</span>
                                <input type="number" name="gst_amount" id="gst_amount" class="calculation-input" placeholder="0.00" readonly tabindex="-1">
                            </div>

                            <div class="summary-row total-row">
                                <span>Est. Total Cost</span>
                                <span id="display_total_cost">₹ 0.00</span>
                            </div>

                            <button type="submit" class="btn-primary-custom" style="width: 100%; margin-top: 1.5rem; justify-content: center; display: flex;">
                                <i class="fas fa-check-circle mr-2"></i> Confirm Booking
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Toast Notification Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Toast Notification System
function showToast(message, type = 'error', title = '') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    // ... (Keep existing toast icons) ...
    const icons = {
        error: '<svg class="toast-icon" fill="currentColor" style="color: #f5576c;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
        success: '<svg class="toast-icon" fill="currentColor" style="color: #38ef7d;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        warning: '<svg class="toast-icon" fill="currentColor" style="color: #ffd89b;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
    };
    
    // ... (Keep existing toast logic) ...
    const defaultTitles = { error: 'Error', success: 'Success', warning: 'Warning' };
    
    toast.innerHTML = `
        ${icons[type] || icons.error}
        <div class="toast-content">
            <div class="toast-title">${title || defaultTitles[type]}</div>
            <p class="toast-message">${message}</p>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

const customers = <?= json_encode($customers) ?>;
const allFlats = <?= json_encode($available_flats) ?>;

// ... (Keep autocomplete function) ...
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
    document.getElementById('customer_mobile').value = customer.mobile || '';
    document.getElementById('customer_email').value = customer.email || '';
    document.getElementById('customer_address').value = customer.address || '';
});

document.getElementById('customer_name').addEventListener('input', function() {
    document.getElementById('customer_id').value = '';
});

function filterFlats() {
    const projectId = document.getElementById('project_select').value;
    const flatSelect = document.getElementById('flat_select');
    
    // Reset Summary
    document.getElementById('display_project_name').textContent = projectId ? document.getElementById('project_select').options[document.getElementById('project_select').selectedIndex].text : 'Select Project';
    document.getElementById('display_flat_no').textContent = '---';
    document.getElementById('display_area').textContent = '- sqft';
    
    flatSelect.innerHTML = '<option value="">Select Flat</option>';
    
    if (!projectId) {
        flatSelect.disabled = true;
        flatSelect.innerHTML = '<option value="">Select project first</option>';
        return;
    }
    
    // Sort and Filter flats
    const projectFlats = allFlats.filter(flat => flat.project_id == projectId)
        .sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, {numeric: true, sensitivity: 'base'}));
    
    if (projectFlats.length === 0) {
        flatSelect.disabled = true;
        flatSelect.innerHTML = '<option value="">No available flats</option>';
        showToast('No available flats found in this project', 'warning', 'No Flats Available');
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
}

function updateFlatDetails() {
    const select = document.getElementById('flat_select');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const area = option.getAttribute('data-area');
        const value = option.getAttribute('data-value');
        const flatNo = option.getAttribute('data-flat-no');
        
        // Update Summary Card
        document.getElementById('display_flat_no').textContent = flatNo;
        document.getElementById('display_area').textContent = parseFloat(area).toFixed(2) + ' sqft';
        
        // Auto-fill Value
        document.getElementById('agreement_value').value = value;
        calculateFinancials();
    } else {
        document.getElementById('display_flat_no').textContent = '---';
        document.getElementById('display_area').textContent = '- sqft';
        document.getElementById('agreement_value').value = '';
        calculateFinancials();
    }
}

function calculateFinancials() {
    const agreementValue = parseFloat(document.getElementById('agreement_value').value) || 0;
    const flatOption = document.getElementById('flat_select').selectedOptions[0];
    const area = flatOption && flatOption.value ? parseFloat(flatOption.getAttribute('data-area')) || 0 : 0;
    
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
    // Total Cost = Agreement Value - Charges - Stamp - Reg - GST
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

function validateForm() {
    const projectId = document.getElementById('project_select').value;
    const flatId = document.getElementById('flat_select').value;
    
    if (!projectId) {
        showToast('Please select a project', 'warning');
        return false;
    }
    if (!flatId) {
        showToast('Please select a flat', 'warning');
        return false;
    }
    return true;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
