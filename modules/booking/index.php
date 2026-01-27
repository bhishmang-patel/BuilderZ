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
$page_title = 'Bookings';
$current_page = 'booking';

require_once __DIR__ . '/../../includes/BookingService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

// Handle booking creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_booking') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            setFlashMessage('error', 'Security token expired. Please try again.');
            redirect('modules/booking/index.php');
        }

        $bookingService = new BookingService();
        $result = $bookingService->createBooking($_POST, $_SESSION['user_id']);

        if ($result['success']) {
            setFlashMessage('success', $result['message']);
            redirect('modules/booking/view.php?id=' . $result['booking_id']);
        } else {
            setFlashMessage('error', 'Failed to create booking: ' . $result['message']);
            redirect('modules/booking/index.php');
        }
    }
}

// Fetch all bookings
$status_filter = $_GET['status'] ?? '';
$project_filter = $_GET['project'] ?? '';
$referrer_filter = $_GET['referrer'] ?? '';

$where = '1=1';
$params = [];

if ($status_filter) {
    $where .= ' AND b.status = ?';
    $params[] = $status_filter;
}

if ($project_filter) {
    $where .= ' AND b.project_id = ?';
    $params[] = $project_filter;
}

if ($referrer_filter) {
    $where .= ' AND b.referred_by = ?';
    $params[] = $referrer_filter;
}

$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft,
               p.name as customer_name,
               p.mobile as customer_mobile,
               pr.project_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        WHERE $where
        ORDER BY b.created_at DESC";

$stmt = $db->query($sql, $params);
$bookings = $stmt->fetchAll();


// Calculate Totals for Footer
$total_bookings_count = count($bookings);
$total_bookings_value = 0;
foreach($bookings as $b) {
    $total_bookings_value += $b['agreement_value'];
}

// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

// Get unique referred_by values for filter
$referrers = $db->query("SELECT DISTINCT referred_by FROM bookings WHERE referred_by IS NOT NULL AND referred_by != '' ORDER BY referred_by")->fetchAll(PDO::FETCH_COLUMN);

// Get available flats for booking
$available_flats = $db->query("SELECT f.id, f.flat_no, f.area_sqft, f.total_value, p.project_name, p.id as project_id
                                FROM flats f
                                JOIN projects p ON f.project_id = p.id
                                WHERE f.status = 'available'
                                ORDER BY p.project_name, f.flat_no")->fetchAll();

// Get customers
$customers = $db->query("SELECT id, name, mobile FROM parties WHERE party_type = 'customer' ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Premium Modal Styles (Ported from Projects) */
.custom-modal {
    display: none; 
    position: fixed; 
    z-index: 10000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(15, 23, 42, 0.6); 
    backdrop-filter: blur(8px);
    transition: all 0.3s;
}

.custom-modal-content {
    background-color: #ffffff;
    margin: 2% auto;
    border: none;
    width: 80%; 
    max-width: 1200px; /* Wide for split layout */
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

@keyframes modalSlideUp {
    from { transform: translateY(30px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-header-premium {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%); /* Dark Blue/Slate Gradient */
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-title-group h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-title-group p {
    margin: 6px 0 0 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.modal-close-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}
.modal-close-btn:hover { background: rgba(255, 255, 255, 0.25); transform: rotate(90deg); }

.modal-body-premium {
    padding: 32px;
    background: #ffffff;
}

.form-section-title {
    font-size: 11px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #f1f5f9;
}

.input-group-modern {
    margin-bottom: 20px;
}

.input-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}

.modern-input, .modern-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    color: #1e293b;
    font-weight: 500;
    transition: all 0.2s ease;
    background: #f8fafc;
    outline: none;
}

.modern-input:focus, .modern-select:focus {
    border-color: #3b82f6; /* Blue Focus */
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }

/* Summary Card Styles */
.summary-card-premium {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    position: sticky;
    top: 0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.summary-header-premium {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 24px;
    color: white;
}

.summary-details-premium {
    padding: 24px;
}

/* New Stats Row Styles */
.stat-card-premium {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s;
    height: 100%;
}
.stat-card-premium:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }

.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; flex-shrink: 0;
}
.stat-icon.purple { background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%); }
.stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

.stat-content { flex: 1; }
.stat-label { font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
/* CSS Grid Approach for Hover Reveal without Overlap/Jumps */
.stat-value.hover-reveal {
    display: grid;
    grid-template-columns: 1fr;
    align-items: center;
    min-height: 29px;
}

.hover-reveal .short-val,
.hover-reveal .full-val {
    grid-row: 1;
    grid-column: 1;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    white-space: nowrap;
}

/* Default State */
.hover-reveal .short-val {
    opacity: 1;
    visibility: visible;
}

.hover-reveal .full-val {
    opacity: 0;
    visibility: hidden;
    font-size: 20px; /* Slightly smaller to fit large numbers */
}

/* Hover State */
.stat-card-premium:hover .hover-reveal .short-val {
    opacity: 0;
    visibility: hidden;
}

.stat-card-premium:hover .hover-reveal .full-val {
    opacity: 1;
    visibility: visible;
}
</style>

<?php
$total_area_sold = 0;
$total_received_sum = 0;
$total_pending_sum = 0;

foreach($bookings as $b) {
    if(!empty($b['area_sqft'])) $total_area_sold += $b['area_sqft'];
    $total_received_sum += $b['total_received'];
    $total_pending_sum += $b['total_pending'];
}
$average_rate = ($total_area_sold > 0) ? ($total_bookings_value / $total_area_sold) : 0;
?>

<!-- Stats Row -->
<div class="row" style="margin-bottom: 25px;">
    <!-- Row 1 -->
    <div class="col-lg-4 col-md-6" style="margin-bottom: 20px;">
        <div class="stat-card-premium">
            <div class="stat-icon blue"><i class="fas fa-file-signature"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?= $total_bookings_count ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6" style="margin-bottom: 20px;">
        <div class="stat-card-premium">
            <div class="stat-icon green"><i class="fas fa-chart-area"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Area Sold</div>
                <div class="stat-value"><?= number_format($total_area_sold, 0) ?> <span class="unit">sqft</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6" style="margin-bottom: 20px;">
        <div class="stat-card-premium">
            <div class="stat-icon purple"><i class="fas fa-percent"></i></div>
            <div class="stat-content">
                <div class="stat-label">Average Rate</div>
                <div class="stat-value">₹ <?= number_format($average_rate, 0) ?> <span class="unit">/sqft</span></div>
            </div>
        </div>
    </div>

    <!-- Row 2 -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card-premium" style="height: 95px">
            <div class="stat-icon orange"><i class="fas fa-wallet"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Sold Value</div>
                <div class="stat-value hover-reveal">
                    <span class="short-val"><?= formatCurrencyShort($total_bookings_value) ?></span>
                    <span class="full-val"><?= formatCurrency($total_bookings_value) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="stat-card-premium"  style="height: 95px">
            <div class="stat-icon green"><i class="fas fa-hand-holding-usd"></i></div> <!-- Icon changed for variety -->
            <div class="stat-content">
                <div class="stat-label">Total Received</div>
                <div class="stat-value hover-reveal">
                    <span class="short-val"><?= formatCurrencyShort($total_received_sum) ?></span>
                    <span class="full-val"><?= formatCurrency($total_received_sum) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6"  style="height: 95px">
        <div class="stat-card-premium">
            <div class="stat-icon orange" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Pending</div>
                <div class="stat-value hover-reveal">
                    <span class="short-val"><?= formatCurrencyShort($total_pending_sum) ?></span>
                    <span class="full-val"><?= formatCurrency($total_pending_sum) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Layout -->
<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;"> <!-- Main Card -->
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-file-contract"></i></div>
                        All Bookings
                    </h3>
                    <div class="chart-subtitle">Manage and track all customer bookings</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="document.getElementById('filterSection').style.display = document.getElementById('filterSection').style.display === 'none' ? 'block' : 'none'" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <a href="cancelled.php" class="modern-btn" style="background:#fef2f2; color:#ef4444; border:1px solid #fecaca; text-decoration: none;">
                        <i class="fas fa-ban"></i> Cancelled
                    </a>
                    <a href="export.php" class="modern-btn" style="background:#fff; color:#10b981; border:1px solid #10b981; text-decoration: none;">
                        <i class="fas fa-file-excel"></i> Export
                    </a>
                    <button type="button" onclick="openBookingModal('createBookingModal')" class="modern-btn" style="background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Booking
                    </button>
                </div>
            </div>

            <!-- Filter Section (Hidden by default unless filtered) -->
            <div id="filterSection" style="display: <?= ($status_filter || $project_filter || $referrer_filter) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <select name="project" class="modern-select" style="flex:1;">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="referrer" class="modern-select" style="flex:1;">
                            <option value="">All Referrers</option>
                            <?php foreach ($referrers as $ref): ?>
                                <option value="<?= htmlspecialchars($ref) ?>" <?= $referrer_filter === $ref ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ref) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="modern-select" style="flex:1;">
                            <option value="">All Status</option>
                            <?php 
                            $statuses = ['active', 'completed', 'cancelled'];
                            foreach ($statuses as $st): ?>
                                <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="modern-btn">Apply</button>
                        <a href="index.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>PROJECT</th>
                            <th>FLAT</th>
                            <th>AREA</th>
                            <th>CUSTOMER</th>
                            <th>REFERRED BY</th>
                            <th>RATE</th>
                            <th>AG. AMOUNT</th>
                            <th>RECEIVED</th>
                            <th>PENDING</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(empty($bookings)): ?>
                            <tr><td colspan="12" class="text-center" style="padding:40px;">No bookings found matching the criteria.</td></tr>
                        <?php else: 
                            foreach ($bookings as $booking): 
                                $color = ColorHelper::getProjectColor($booking['project_id']);
                                $initial = ColorHelper::getInitial($booking['project_name']);
                                
                                $custColor = ColorHelper::getCustomerColor($booking['customer_id'] ?? 0);
                                $custInitial = ColorHelper::getInitial($booking['customer_name']);
                                
                                // Status styling
                                $statusClass = 'green';
                                if($booking['status'] === 'active') $statusClass = 'blue';
                                if($booking['status'] === 'cancelled') $statusClass = 'red';
                                if($booking['status'] === 'completed') $statusClass = 'green';
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                    <span style="font-weight:700;"><?= htmlspecialchars($booking['project_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge-pill blue"><?= htmlspecialchars($booking['flat_no']) ?></span></td>
                            <td><span style="font-weight:600; color:#64748b; font-size:13px;"><?= number_format($booking['area_sqft'], 2) ?> sqft</span></td>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <div class="avatar-circle" style="background: <?= $custColor ?>; color: #fff;"><?= $custInitial ?></div>
                                    <div style="display:flex; flex-direction:column;">
                                        <span style="line-height:1.2;"><?= htmlspecialchars($booking['customer_name']) ?></span>
                                        <span style="font-size:10px; color:#94a3b8;"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if(!empty($booking['referred_by'])): ?>
                                    <span style="color:#475569; font-size:13px; font-weight:500;"><i class="fas fa-user-tag" style="color:#94a3b8; margin-right:4px;"></i> <?= htmlspecialchars($booking['referred_by']) ?></span>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($booking['rate'])): ?>
                                    <span style="font-weight:600; color:#475569;" title="<?= formatCurrency($booking['rate']) ?>"><?= formatCurrencyShort($booking['rate']) ?></span>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-pill green" title="<?= formatCurrency($booking['agreement_value']) ?>"><?= formatCurrencyShort($booking['agreement_value']) ?></span></td>
                            <td><span style="font-size:13px; font-weight:600; color:#10b981;" title="<?= formatCurrency($booking['total_received']) ?>"><?= formatCurrencyShort($booking['total_received']) ?></span></td>
                            <td>
                                <?php if($booking['total_pending'] > 0): ?>
                                    <span style="font-size:13px; font-weight:600; color:#f59e0b;" title="<?= formatCurrency($booking['total_pending']) ?>"><?= formatCurrencyShort($booking['total_pending']) ?></span>
                                <?php else: ?>
                                    <span class="badge-pill green">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($booking['status']) ?></span></td>
                            <td><span style="font-size:12px; font-weight:600; color:#64748b;"><?= date('d M Y', strtotime($booking['booking_date'])) ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>modules/booking/view.php?id=<?= $booking['id'] ?>" class="action-btn" title="View"><i class="fas fa-eye"></i></a>
                                <?php if ($booking['status'] !== 'cancelled'): ?>
                                    <a href="<?= BASE_URL ?>modules/booking/edit.php?id=<?= $booking['id'] ?>" class="action-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="action-btn" title="Cancel" onclick="openCancelModal(<?= $booking['id'] ?>, '<?= htmlspecialchars(addslashes($booking['project_name'])) ?>', '<?= htmlspecialchars($booking['flat_no']) ?>')"><i class="fas fa-times-circle" style="color: #ef4444;"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>  
        </div>
    </div>
</div>

<!-- Cancel Booking Confirmation Modal -->
<div id="confirmCancelModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; border-radius: 16px; border:none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 30px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="modal-title" style="margin: 0; font-size:18px; font-weight:800; color:#ef4444; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i> Confirm Cancellation
            </h3>
            <button class="modal-close" onclick="closeBookingModal('confirmCancelModal')" style="color:#94a3b8; font-size: 24px; background: transparent; border: none; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 30px; text-align: center;">
            <div style="width: 60px; height: 60px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-ban" style="font-size: 30px; color: #ef4444;"></i>
            </div>
            <h4 style="margin: 0 0 10px; color: #1e293b; font-weight: 700;">Are you sure?</h4>
            <p style="color: #64748b; margin-bottom: 20px; line-height: 1.5;">
                You are about to initiate the cancellation process for<br>
                <strong id="cancel_project_name" style="color: #334155;"></strong> - Flat <strong id="cancel_flat_no" style="color: #334155;"></strong>.
            </p>
            <p style="font-size: 13px; color: #94a3b8; background: #f8fafc; padding: 10px; border-radius: 8px;">
                This will take you to the cancellation page where you can process refunds and deductions.
            </p>
        </div>
        <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 20px; background: #fff; border-radius: 0 0 16px 16px; display: flex; justify-content: center; gap: 10px;">
            <button type="button" class="modern-btn" style="background: #fff; color: #64748b; border: 1px solid #e2e8f0;" onclick="closeBookingModal('confirmCancelModal')">Go Back</button>
            <a href="#" id="confirm_cancel_btn" class="modern-btn" style="background: #ef4444; color: white; border: none; text-decoration: none;">Proceed to Cancel</a>
        </div>
    </div>
</div>

<!-- Premium Create Booking Modal -->
<div id="createBookingModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-file-signature"></i> Create New Booking</h3>
                <p>Register a new booking for a customer</p>
            </div>
            <button class="modal-close-btn" onclick="closeBookingModal('createBookingModal')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create_booking">
            <?= csrf_field() ?>

            <div class="modal-body-premium">
                <div class="row">
                    <!-- LEFT COLUMN: Forms -->
                    <div class="col-lg-8" style="padding-right: 40px; border-right: 1px solid #f1f5f9;">
                        
                        <!-- Customer Section -->
                        <div class="form-section-title"><i class="fas fa-user-circle"></i> Customer Information</div>
                        <div class="form-grid-premium">
                            <div class="input-group-modern">
                                <label class="input-label">Full Name *</label>
                                <input type="text" name="customer_name" required class="modern-input" placeholder="Enter customer name">
                            </div>
                            <div class="input-group-modern">
                                <label class="input-label">Mobile Number *</label>
                                <input type="text" name="mobile" required class="modern-input" placeholder="Enter 10-digit mobile number" pattern="\d{10}" maxlength="10" minlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Please enter exactly 10 digits">
                            </div>
                        </div>
                        <div class="form-grid-premium">
                            <div class="input-group-modern">
                                <label class="input-label">Email Address</label>
                                <input type="email" name="email" class="modern-input" placeholder="optional@email.com">
                            </div>
                            <div class="input-group-modern">
                                <label class="input-label">Referred By</label>
                                <input type="text" name="referred_by" class="modern-input" placeholder="Optional">
                            </div>
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Address</label>
                            <input type="text" name="address" class="modern-input" placeholder="City, Area">
                        </div>

                        <!-- Booking Details Section -->
                        <div class="form-section-title" style="margin-top: 30px;"><i class="fas fa-building"></i> Property & Booking Details</div>
                        <div class="form-grid-premium">
                            <div class="input-group-modern">
                                <label class="input-label">Select Project *</label>
                                <select name="project_id" id="project_select" class="modern-select" required onchange="filterFlats()">
                                    <option value="">Choose Project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group-modern">
                                <label class="input-label">Select Flat *</label>
                                <select name="flat_id" id="flat_select" class="modern-select" required onchange="updateFlatDetails()" disabled>
                                    <option value="">Select project first</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid-premium">
                            <div class="input-group-modern">
                                <label class="input-label">Booking Date *</label>
                                <input type="date" name="booking_date" required value="<?= date('Y-m-d') ?>" class="modern-input">
                            </div>
                            <div class="input-group-modern">
                                <label class="input-label">Agreement Value (₹) *</label>
                                <input type="number" name="agreement_value" id="agreement_value" step="0.01" required class="modern-input" style="font-weight: 700;" oninput="calculateModalFinancials()" placeholder="0.00">
                            </div>
                        </div>
                        
                        <!-- Deduction Fields -->
                        <div class="form-section-title" style="margin-top: 20px;"><i class="fas fa-minus-circle"></i> Deductions/Charges</div>
                        <div class="form-grid-premium">
                            <div class="input-group-modern">
                                <label class="input-label">Development Charge</label>
                                <input type="number" name="development_charge" id="modal_development_charge" step="0.01" class="modern-input" placeholder="0.00" oninput="calculateModalFinancials()">
                            </div>
                            <div class="input-group-modern">
                                <label class="input-label">Parking Charge</label>
                                <input type="number" name="parking_charge" id="modal_parking_charge" step="0.01" class="modern-input" placeholder="0.00" oninput="calculateModalFinancials()">
                            </div>
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Society Charge</label>
                            <input type="number" name="society_charge" id="modal_society_charge" step="0.01" class="modern-input" placeholder="0.00" oninput="calculateModalFinancials()">
                        </div>

                    </div>

                    <!-- RIGHT COLUMN: Summary (Sticky) -->
                    <div class="col-lg-4" style="width: 50%;">
                        <div class="summary-card-premium">
                            <div class="summary-header-premium">
                                <div id="display_project_name" style="font-size: 11px; text-transform: uppercase; opacity: 0.7; letter-spacing: 1px; font-weight: 700;">SELECT PROJECT</div>
                                <div id="display_flat_no" style="font-size: 32px; font-weight: 800; margin-top: 5px; line-height: 1;">---</div>
                            </div>
                            <div class="summary-details-premium">
                                <!-- Area -->
                                <div style="display: flex; justify-content: space-between; margin-bottom: 16px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 600;">Area/sqft</span>
                                    <span style="color: #1e293b; font-weight: 700;" id="display_area">- sqft</span>
                                </div>
                                
                                <div style="height: 1px; background: #f1f5f9; margin: 16px 0;"></div>

                                <!-- Financials -->
                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 500;">Rate (₹/sqft)</span>
                                    <input type="number" name="rate" id="modal_rate" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly placeholder="0.00">
                                </div>

                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 500;">Agreement Value</span>
                                    <span style="color: #1e293b; font-weight: 700;" id="display_agreement_value">₹ 0.00</span>
                                </div>

                                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 500;">Stamp Duty (6%)</span>
                                    <input type="number" name="stamp_duty_registration" id="modal_stamp_duty" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly placeholder="0.00">
                                </div>

                                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 500;">Registration (1%)</span>
                                    <input type="number" name="registration_amount" id="modal_registration_amount" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly placeholder="0.00">
                                </div>

                                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                                    <span style="color: #64748b; font-size: 13px; font-weight: 500;">GST (1%)</span>
                                    <input type="number" name="gst_amount" id="modal_gst_amount" class="modern-input" style="width: 100px; padding: 6px 10px; font-size: 13px; text-align: right;" readonly placeholder="0.00">
                                </div>

                                <!-- Total -->
                                <div style="background: #f0fdf4; border: 1px dashed #22c55e; border-radius: 12px; padding: 16px; text-align: center;">
                                    <div style="font-size: 11px; color: #15803d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Est. Total Cost</div>
                                    <div id="display_total_cost" style="font-size: 20px; font-weight: 800; color: #166534;">₹ 0.00</div>
                                </div>

                                <button type="submit" class="modern-btn" style="width: 100%; justify-content: center; margin-top: 24px; padding: 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 100%;">
                                    <i class="fas fa-check-circle"></i> Confirm Booking
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const allFlats = <?= json_encode($available_flats) ?>;

function openBookingModal(modalId) {
    document.getElementById(modalId).style.display = "block";
}

function closeBookingModal(modalId) {
    document.getElementById(modalId).style.display = "none";
}

// Close modal when clicking outside
// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
    }
}

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
        document.getElementById('agreement_value').value = '';
        document.getElementById('modal_rate').value = '';
        document.getElementById('modal_stamp_duty').value = '';
        document.getElementById('display_agreement_value').textContent = '₹ 0.00';
        document.getElementById('display_total_cost').textContent = '₹ 0.00';
        return;
    }
    
    // Filter flats by selected project
    const projectFlats = allFlats.filter(flat => flat.project_id == projectId);
    
    // Sort logic for natural order (A-1, A-2, ... A-10)
    projectFlats.sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, {numeric: true, sensitivity: 'base'}));
    
    if (projectFlats.length === 0) {
        flatSelect.disabled = true;
        flatSelect.innerHTML = '<option value="">No available flats in this project</option>';
        return;
    }
    
    // Populate flat dropdown
    flatSelect.disabled = false;
    projectFlats.forEach(flat => {
        const option = document.createElement('option');
        option.value = flat.id;
        option.setAttribute('data-area', flat.area_sqft);
        option.setAttribute('data-value', flat.total_value);
        option.textContent = `${flat.flat_no} - ${parseFloat(flat.area_sqft).toFixed(0)} sqft - ₹ ${parseFloat(flat.total_value).toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
        flatSelect.appendChild(option);
    });
}

function updateFlatDetails() {
    const select = document.getElementById('flat_select');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const area = option.getAttribute('data-area');
        const value = option.getAttribute('data-value');
        const flatNo = option.textContent.split(' - ')[0]; // Extract flat no
        
        document.getElementById('display_flat_no').textContent = flatNo;
        document.getElementById('display_area').textContent = parseFloat(area).toFixed(2) + ' sqft';
        
        document.getElementById('agreement_value').value = value;
        calculateModalFinancials();
    } else {
        document.getElementById('display_flat_no').textContent = '---';
        document.getElementById('display_area').textContent = '- sqft';
        document.getElementById('agreement_value').value = '';
        document.getElementById('display_agreement_value').textContent = '₹ 0.00';
        document.getElementById('display_total_cost').textContent = '₹ 0.00';
        calculateModalFinancials();
    }
}

function calculateModalFinancials() {
    const agreementValue = parseFloat(document.getElementById('agreement_value').value) || 0;
    const flatOption = document.getElementById('flat_select').selectedOptions[0];
    const area = flatOption && flatOption.value ? parseFloat(flatOption.getAttribute('data-area')) || 0 : 0;
    
    // Update Agreement Value Display
    document.getElementById('display_agreement_value').textContent = '₹ ' + agreementValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Calculate Stamp Duty (6%) - Rounded
    const stampDuty = Math.round(agreementValue * 0.06);
    document.getElementById('modal_stamp_duty').value = stampDuty.toFixed(2);
    
    // Calculate Registration (1% with Cap) - Rounded
    let registration = Math.round(agreementValue * 0.01);
    if (agreementValue >= 3000000) {
        registration = 30000;
    }
    document.getElementById('modal_registration_amount').value = registration.toFixed(2);

    // Calculate GST (1%) - Rounded
    const gst = Math.round(agreementValue * 0.01);
    document.getElementById('modal_gst_amount').value = gst.toFixed(2);
    
    // Get Charges
    const devCharge = parseFloat(document.getElementById('modal_development_charge').value) || 0;
    const parkingCharge = parseFloat(document.getElementById('modal_parking_charge').value) || 0;
    const societyCharge = parseFloat(document.getElementById('modal_society_charge').value) || 0;
    const totalCharges = devCharge + parkingCharge + societyCharge;

    // Calculate Total Cost (Net)
    // Total Cost = Agreement Value - Charges - Stamp - Reg - GST
    const totalCost = agreementValue - totalCharges - stampDuty - registration - gst;
    document.getElementById('display_total_cost').textContent = '₹ ' + totalCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Calculate Rate = Total Cost / Area
    if (area > 0) {
        const rate = totalCost / area;
        document.getElementById('modal_rate').value = rate.toFixed(2);
    } else {
        document.getElementById('modal_rate').value = '';
    }
}
</script>

<script>
function openCancelModal(bookingId, projectName, flatNo) {
    document.getElementById('cancel_project_name').textContent = projectName;
    document.getElementById('cancel_flat_no').textContent = flatNo;
    document.getElementById('confirm_cancel_btn').href = '<?= BASE_URL ?>modules/booking/cancel.php?id=' + bookingId;
    document.getElementById('confirmCancelModal').style.display = "block";
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
