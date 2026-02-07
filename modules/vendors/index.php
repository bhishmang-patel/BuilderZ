<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$masterService = new MasterService();
$db = Database::getInstance();
$page_title = 'Vendors';
$current_page = 'vendors';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF Token verification failed');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $data = [
                'party_type' => 'vendor',
                'name' => sanitize($_POST['name']),
                'vendor_type' => sanitize($_POST['vendor_type']),
                'contact_person' => '', // Deprecated/Removed from Schema but good to be explicit if array strictness
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'city' => sanitize($_POST['city']),
                'gst_number' => sanitize($_POST['gst_number']),
                'gst_status' => sanitize($_POST['gst_status']),
                'opening_balance' => floatval($_POST['opening_balance'] ?? 0),
                'status' => 'active' // Default active on create
            ];
            // Remove contact_person key since it's gone from DB
            unset($data['contact_person']);
            
            $newVendorId = $masterService->createParty($data);
            
            // Link Challan if selected
            if (!empty($_POST['link_challan_id'])) {
                $challanId = intval($_POST['link_challan_id']);
                $db = Database::getInstance();
                $db->update('challans', ['party_id' => $newVendorId], 'id = ?', ['id' => $challanId]);
            }

            setFlashMessage('success', 'Vendor created successfully');
            
            if (!empty($_POST['return_url'])) {
                header('Location: ' . $_POST['return_url']);
                exit;
            }
            
        } elseif ($action === 'update') {
            $data = [
                'party_type' => 'vendor',
                'name' => sanitize($_POST['name']),
                'vendor_type' => sanitize($_POST['vendor_type']),
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'city' => sanitize($_POST['city']),
                'gst_number' => sanitize($_POST['gst_number']),
                'gst_status' => sanitize($_POST['gst_status']),
                'opening_balance' => floatval($_POST['opening_balance'] ?? 0),
                'status' => sanitize($_POST['status'])
            ];
            $masterService->updateParty(intval($_POST['id']), $data);
            setFlashMessage('success', 'Vendor updated successfully');
            
        } elseif ($action === 'delete') {
            $masterService->deleteParty(intval($_POST['id']));
            setFlashMessage('success', 'Vendor deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/vendors/index.php');
}

// Fetch all parties but filter for vendors
$filters = [
    'type' => 'vendor',
    'search' => $_GET['search'] ?? '',
    'vendor_type' => $_GET['vendor_type'] ?? '',
    'city' => $_GET['city'] ?? '',
    'gst_status' => $_GET['gst_status'] ?? '',
    'status' => $_GET['status'] ?? '',
    'material' => $_GET['material'] ?? ''
];
$parties = [];
try {
    $parties = $masterService->getAllParties($filters);
} catch (Exception $e) {
    // Check if it's a column not found error
    if (strpos($e->getMessage(), 'Column not found') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
        $errorMsg = "Database columns missing! Please run the migration script: <code>migrations/add_vendor_columns.sql</code>";
    } else {
        $errorMsg = "Error loading vendors: " . $e->getMessage();
    }
    // Set a variable to display this error in the UI
    $fatalError = $errorMsg;
}

// Statistics (Mocking similar structure but only for vendors doesn't make much sense to break down further, so maybe just total count)
$totalVendors = count($parties);

include __DIR__ . '/../../includes/header.php';
?>

<!-- Modern Dashboard Style -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Reusing Projects Page Styles */
.chart-card-custom {
    background: #fff;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 100%;
    border: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
}
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
    margin: 4% auto;
    border: none;
    width: 90%; 
    max-width: 650px;
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
.chart-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
}
.chart-title-group h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.chart-subtitle {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 4px;
    font-weight: 500;
    padding-left: 42px; 
}
.modern-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: #0f172a;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none;
}
.modern-btn:hover { opacity: 0.9; }

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
    margin-top: -10px;
}
.modern-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    padding: 12px 15px;
    letter-spacing: 0.5px;
    border: none;
    text-align: center;
    vertical-align: middle !important;
}
.modern-table tbody tr { background: #fff; }
.modern-table td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    text-align: center;
    vertical-align: middle !important;
}
.modern-table tr:last-child td { border-bottom: none; }

.avatar-square {
    width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #fff; margin-right: 12px; flex-shrink: 0;
}
.badge-pill {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-pill.vendor { background: #eff6ff; color: #3b82f6; }

.action-btn { color: #94a3b8; cursor: pointer; transition: 0.2s; margin: 0 4px; border:none; background:transparent;}
.action-btn:hover { color: #64748b; }
.action-btn.delete:hover { color: #ef4444; }

.filter-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}
.filter-row { display: flex; gap: 10px; }
.modern-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #64748b;
    outline: none;
}
.modern-input {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #64748b;
    outline: none;
    width: 100%;
}

/* Modal Styles */
.modal-header-premium {
    background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
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

.input-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #334155;
    font-size: 13px;
}
.modern-input:focus, .modern-select:focus {
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.modal-footer-premium {
    padding: 24px 32px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.btn-save {
    background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }
.btn-ghost {
    background: transparent;
    color: #64748b;
    border: 2px solid #e2e8f0;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-ghost:hover { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.btn-danger {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 24px;
    font-weight: 600;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
}
.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
}

.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }

.stats-container {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.stat-card-modern {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    width: 280px;
    flex: 0 0 auto;
    gap: 15px;
    transition: transform 0.2s;
}
.stat-card-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.stat-info h4 {
    margin: 0;
    font-size: 14px;
    color: #64748b;
    font-weight: 600;
}
.stat-info .value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
    margin-top: 5px;
    position: relative;
    cursor: default;
}
/* Hover Effect for Short/Full Numbers */
.stat-card-modern .full-value {
    display: none;
    font-size: 20px;
}
.stat-card-modern:hover .short-value {
    display: none;
}
.stat-card-modern:hover .full-value {
    display: inline-block;
    animation: fadeIn 0.2s ease-in;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(2px); }
    to { opacity: 1; transform: translateY(0); }
}

.bg-blue-light { background: #eff6ff; color: #3b82f6; }
</style>

<!-- Calculate Total Pending Payables -->
<?php
$stmt = $db->query("SELECT SUM(amount - paid_amount) as total_pending FROM bills WHERE status IN ('pending', 'partial')");
$totalPending = $stmt->fetch()['total_pending'] ?? 0;
?>

<!-- Error Alert -->
<?php if (isset($fatalError)): ?>
<div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px;">
    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
    <div>
        <strong>System Error:</strong> <?= $fatalError ?>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h4>Total Vendors</h4>
            <div class="value"><?= $totalVendors ?></div>
        </div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon" style="background: #fff1f2; color: #ef4444;">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <h4>Total Payables</h4>
            <div class="value stat-value" style="color: #ef4444;">
                <span class="short-value"><?= formatCurrencyShort($totalPending) ?></span>
                <span class="full-value"><?= formatCurrency($totalPending) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-truck"></i></div>
                        Vendors
                    </h3>
                    <div class="chart-subtitle">Manage suppliers and vendors</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="toggleFilter()" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <button class="modern-btn" onclick="openPartyModal('addPartyModal')" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Vendor
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($filters['search']) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row" style="flex-wrap: wrap;">
                        <div style="flex: 2; min-width: 200px; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                            <input type="text" name="search" class="modern-input" placeholder="Search by name, mobile..." value="<?= htmlspecialchars($filters['search']) ?>" style="padding-left: 32px;">
                        </div>
                         <div style="flex: 1; min-width: 150px;">
                            <select name="vendor_type" class="modern-select" style="width: 100%;">
                                <option value="">All Types</option>
                                <option value="supplier" <?= $filters['vendor_type'] == 'supplier' ? 'selected' : '' ?>>Supplier</option>
                                <option value="contractor" <?= $filters['vendor_type'] == 'contractor' ? 'selected' : '' ?>>Contractor</option>
                                <option value="service_provider" <?= $filters['vendor_type'] == 'service_provider' ? 'selected' : '' ?>>Service Provider</option>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <input type="text" name="city" class="modern-input" placeholder="City" value="<?= htmlspecialchars($filters['city']) ?>">
                        </div>
                         <div style="flex: 1; min-width: 150px;">
                            <select name="gst_status" class="modern-select" style="width: 100%;">
                                <option value="">GST Status</option>
                                <option value="registered" <?= $filters['gst_status'] == 'registered' ? 'selected' : '' ?>>Registered</option>
                                <option value="unregistered" <?= $filters['gst_status'] == 'unregistered' ? 'selected' : '' ?>>Unregistered</option>
                                <option value="composition" <?= $filters['gst_status'] == 'composition' ? 'selected' : '' ?>>Composition</option>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 100px;">
                            <select name="status" class="modern-select" style="width: 100%;">
                                <option value="">Status</option>
                                <option value="active" <?= $filters['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $filters['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div style="flex: 2; min-width: 200px; position: relative;">
                            <i class="fas fa-box-open" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                            <input type="text" name="material" class="modern-input" placeholder="Filter by Material..." value="<?= htmlspecialchars($filters['material']) ?>" style="padding-left: 32px;">
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="modern-btn">Apply</button>
                            <a href="index.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding-left: 20px;">Vendor</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>GST Status</th>
                            <th style="width: 200px;">Material</th>
                            <th>Quantity</th>
                            <th style="text-align: right; padding-right: 20px;">Outstanding (₹)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parties)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-folder-open" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No vendors found.
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($parties as $party): 
                                $color = ColorHelper::getCustomerColor($party['name']);
                                $initial = ColorHelper::getInitial($party['name']);
                        ?>
                        <tr>
                            <td style="text-align: left; padding-left: 20px;">
                                <div style="display:inline-flex; align-items:center; text-align: left;">
                                    <div class="avatar-square" style="background: <?= $color ?>; margin-right: 12px; flex-shrink: 0;"><?= $initial ?></div>
                                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">
                                        <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($party['name'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($party['vendor_type']): ?>
                                    <span class="badge-pill" style="background: #f1f5f9; color: #475569; font-weight:600; text-transform: capitalize;">
                                        <?= str_replace('_', ' ', $party['vendor_type']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #475569; font-size: 13px;">
                                    <?= htmlspecialchars($party['city'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px; align-items: center;">
                                    <span style="font-size: 12px; font-weight: 700; color: #334155; text-transform: capitalize;">
                                        <?= htmlspecialchars($party['gst_status'] ?? 'Unregistered') ?>
                                    </span>
                                    <?php if(!empty($party['gst_number'])): ?>
                                        <span style="font-size: 11px; color: #64748b; font-family: monospace;">
                                            <?= htmlspecialchars($party['gst_number']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12px; color: #475569; max-width: 200px; white-space: normal; line-height: 1.4;">
                                    <?= !empty($party['supplied_materials']) ? htmlspecialchars($party['supplied_materials']) : '<span style="color:#cbd5e1">-</span>' ?>
                                </div>
                            </td>
                            <td style="color: #475569;">
                                <?= !empty($party['total_quantity']) ? number_format($party['total_quantity'], 2) : '-' ?>
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                                    <?php if(($party['outstanding_balance'] ?? 0) > 0): ?>
                                        <span style="color: #ef4444; font-weight: 700;">
                                            <?= formatCurrency($party['outstanding_balance']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #10b981; font-weight: 700;">
                                            <?= formatCurrency(0) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if(($party['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge-pill stock-in">Active</span>
                                <?php else: ?>
                                    <span class="badge-pill" style="background: #f1f5f9; color: #94a3b8;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="action-btn" onclick="viewPartyDetails(<?= htmlspecialchars(json_encode($party)) ?>)" title="View Details">
                                    <i class="far fa-eye"></i>
                                </button>
                                <button class="action-btn" onclick="editParty(<?= htmlspecialchars(json_encode($party)) ?>)" title="Edit">
                                    <i class="far fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="openDeleteModal(<?= $party['id'] ?>)" title="Delete">
                                    <i class="far fa-trash-alt"></i>
                                </button>
                                <button class="action-btn" onclick="openManageBills(<?= $party['id'] ?>, '<?= htmlspecialchars(addslashes($party['name'])) ?>')" title="Manage Bills" style="color:#0ea5e9;">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewPartyModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px;">
        <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h5 class="modal-title" style="font-weight: 800; color: #1e293b; margin: 0; font-size: 18px;" id="view_party_title"></h5>
            <button class="modal-close" onclick="closePartyModal('viewPartyModal')" style="font-size: 24px; color: #94a3b8; border:none; background:transparent; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 25px;">
            <div style="margin-bottom: 25px;">
                 <span class="badge-pill vendor" style="font-size: 12px; padding: 6px 16px;">Vendor</span>
            </div>
            
            <div style="background: #f8fafc; border-radius: 12px; padding: 10px;">
                 <div style="display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600; font-size: 13px;">Mobile</span>
                    <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_mobile"></span>
                </div>
                 <div style="display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600; font-size: 13px;">Email</span>
                    <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_email"></span>
                </div>
                 <div style="display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600; font-size: 13px;">GST Number</span>
                    <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_gst"></span>
                </div>
                 <div style="display: flex; justify-content: space-between; padding: 12px;">
                    <span style="color: #64748b; font-weight: 600; font-size: 13px;">Address</span>
                    <span style="color: #1e293b; font-weight: 600; font-size: 13px; text-align:right; max-width:60%;" id="view_address"></span>
                </div>
            </div>
        </div>
         <div class="modal-footer" style="padding: 15px 25px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
             <button type="button" class="modern-btn" style="background:#f1f5f9; color:#64748b;" onclick="closePartyModal('viewPartyModal')">Close</button>
        </div>
    </div>
</div>

<!-- Manage Bills Modal -->
<div id="manageBillsModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 800px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-file-invoice-dollar"></i> Vendor Bills</h3>
                <p id="manage_bills_vendor_name">Vendor Name</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('manageBillsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-premium" style="background:#f8fafc;">
             <div style="text-align: right; margin-bottom: 20px;">
                 <button onclick="openAddBillModal()" class="modern-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); width: auto; font-size: 13px;">
                    <i class="fas fa-plus"></i> Add New Bill
                </button>
             </div>
             
             <!-- Bills List Container -->
             <div id="bills_list_container">
                 <div style="text-align:center; padding:40px; color:#94a3b8;">
                     <i class="fas fa-spinner fa-spin"></i> Loading bills...
                 </div>
             </div>
        </div>
    </div>
</div>

<!-- Add Bill Modal -->
<div id="addBillModal" class="custom-modal" style="z-index: 10001;">
    <div class="custom-modal-content" style="max-width: 550px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-plus-circle"></i> Add Bill</h3>
                <p>Record a new bill for <span id="add_bill_vendor_name"></span></p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('addBillModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="../bills/create_bill_handler.php" method="POST" enctype="multipart/form-data">
             <?= csrf_field() ?>
             <input type="hidden" name="vendor_id" id="bill_vendor_id">
             
             <div class="modal-body-premium">
                 
                 <div class="form-section-title"><i class="fas fa-link"></i> Select Challan (Optional)</div>
                 <div style="margin-bottom: 20px;">
                    <select name="challan_id" id="bill_challan_select" class="modern-select" onchange="onChallanSelect(this)">
                        <option value="">-- Direct Bill (No Challan) --</option>
                    </select>
                 </div>

                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Bill Details</div>
                 <div class="form-grid-premium">
                    <div>
                        <label class="input-label">Bill Number *</label>
                        <input type="text" name="bill_no" id="bill_no" required class="modern-input" placeholder="e.g. INV-2024-001">
                    </div>
                    <div>
                         <label class="input-label">Bill Date *</label>
                         <input type="date" name="bill_date" id="bill_date" required class="modern-input" value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- New Qty/Rate Fields for Calculator -->
                    <div class="full-width" style="margin-top: 10px; margin-bottom: 5px; border-top: 1px dashed #e2e8f0; padding-top: 10px;">
                        <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Item Calculator (Optional)</span>
                    </div>
                    <div>
                         <label class="input-label">Quantity</label>
                         <input type="number" id="calc_qty" class="modern-input" placeholder="Qty" oninput="calculateBillAmount()">
                    </div>
                    <div>
                         <label class="input-label">Rate (₹)</label>
                         <input type="number" id="calc_rate" class="modern-input" placeholder="Rate" step="0.01" oninput="calculateBillAmount()">
                    </div>
                    
                     <div>
                         <label class="input-label">Amount (₹) *</label>
                         <input type="number" name="amount" id="bill_amount" required class="modern-input" step="0.01" placeholder="0.00">
                    </div>
                     <div>
                         <label class="input-label">Attach File</label>
                         <input type="file" name="bill_file" class="modern-input" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                
                <div id="challan_preview" style="margin-top: 15px; background: #f0f9ff; padding: 10px; border-radius: 8px; border: 1px solid #bae6fd; font-size: 13px; color: #0369a1; display: none;">
                    <strong><i class="fas fa-shopping-cart"></i> Items:</strong> <span id="challan_items_display"></span>
                </div>

             </div>

             <div class="modal-footer-premium">
                 <button type="button" class="btn-ghost" onclick="closePartyModal('addBillModal')">Cancel</button>
                 <button type="submit" class="btn-save">
                     <i class="fas fa-check"></i> Save Bill
                 </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Bill Modal -->
<div id="editBillModal" class="custom-modal" style="z-index: 10001;">
    <div class="custom-modal-content" style="max-width: 550px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Bill</h3>
                <p>Update bill details</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('editBillModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="../bills/update_bill_handler.php" method="POST" enctype="multipart/form-data">
             <?= csrf_field() ?>
             <input type="hidden" name="id" id="edit_bill_id">
             
             <div class="modal-body-premium">
                 
                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Bill Details</div>
                 <div class="form-grid-premium">
                    <div>
                        <label class="input-label">Bill Number *</label>
                        <input type="text" name="bill_no" id="edit_bill_no" required class="modern-input">
                    </div>
                    <div>
                         <label class="input-label">Bill Date *</label>
                         <input type="date" name="bill_date" id="edit_bill_date" required class="modern-input">
                    </div>

                    <!-- Calculator for Edit -->
                    <div class="full-width" style="margin-top: 10px; margin-bottom: 5px; border-top: 1px dashed #e2e8f0; padding-top: 10px;">
                        <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Item Calculator (Optional)</span>
                    </div>
                    <div>
                         <label class="input-label">Quantity</label>
                         <input type="number" id="edit_calc_qty" class="modern-input" placeholder="Qty" oninput="calculateEditBillAmount()">
                    </div>
                    <div>
                         <label class="input-label">Rate (₹)</label>
                         <input type="number" id="edit_calc_rate" class="modern-input" placeholder="Rate" step="0.01" oninput="calculateEditBillAmount()">
                    </div>

                     <div>
                         <label class="input-label">Amount (₹) *</label>
                         <input type="number" name="amount" id="edit_bill_amount" required class="modern-input" step="0.01">
                    </div>
                     <div>
                         <label class="input-label">Update File (Optional)</label>
                         <input type="file" name="bill_file" class="modern-input" accept=".pdf,.jpg,.jpeg,.png">
                         <small style="color:#64748b" id="edit_file_status"></small>
                    </div>
                </div>
             </div>

             <div class="modal-footer-premium">
                 <button type="button" class="btn-ghost" onclick="closePartyModal('editBillModal')">Cancel</button>
                 <button type="submit" class="btn-save">
                     <i class="fas fa-save"></i> Update Changes
                 </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Party Modal -->
<div id="addPartyModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-plus-circle"></i> Add New Vendor</h3>
                <p>Enter vendor details</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('addPartyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
             <?= csrf_field() ?>
             <input type="hidden" name="action" value="create">
             <input type="hidden" name="return_url" id="add_return_url">

             <div class="modal-body-premium">

                 <!-- Link Challan Section -->
                 <div style="background: #f0f9ff; padding: 15px; border-radius: 12px; border: 1px solid #bae6fd; margin-bottom: 20px;">
                    <label class="input-label" style="color: #0369a1;"><i class="fas fa-link"></i> Link Existing Challan (Optional)</label>
                    <div style="position: relative;">
                        <input type="text" id="link_challan_search" class="modern-input" placeholder="Search by Challan No... (e.g. 1001)" autocomplete="off" style="border-color: #bae6fd;">
                        <input type="hidden" name="link_challan_id" id="link_challan_id">
                        <ul id="challan_suggestions" class="autocomplete-list"></ul>
                    </div>
                    <small style="color: #0369a1; font-size: 11px; margin-top: 4px; display: block;">Select a challan to auto-fill vendor details and link it.</small>
                 </div>

                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Basic Info</div>
                 
                 <div class="form-grid-premium">
                    <div class="full-width">
                        <label class="input-label">Vendor Name *</label>
                        <input type="text" name="name" id="add_name" required class="modern-input" placeholder="Enter business or person name">
                    </div>

                    <div>
                        <label class="input-label">Vendor Type *</label>
                        <select name="vendor_type" id="add_vendor_type" required class="modern-select" style="width: 100%;">
                            <option value="">Select Type</option>
                            <option value="supplier">Supplier</option>
                            <option value="contractor">Contractor</option>
                            <option value="service_provider">Service Provider</option>
                        </select>
                    </div>
                     <div>
                        <label class="input-label">Opening Balance (₹)</label>
                        <input type="number" name="opening_balance" id="add_opening_balance" class="modern-input" placeholder="0.00" step="0.01">
                    </div>

                     <div>
                        <label class="input-label">GST Status</label>
                         <select name="gst_status" id="add_gst_status" class="modern-select" style="width: 100%;" onchange="toggleGstInput('add')">
                            <option value="unregistered">Unregistered</option>
                            <option value="registered">Registered</option>
                            <option value="composition">Composition</option>
                        </select>
                    </div>
                     <div>
                        <label class="input-label">GST Number</label>
                        <input type="text" name="gst_number" id="add_gst_number" class="modern-input" placeholder="GSTIN (Optional)" disabled style="background: #f1f5f9;">
                    </div>
                </div>

                <div class="form-section-title" style="margin-top: 24px;"><i class="fas fa-address-card"></i> Contact Details</div>
                
                 <div class="form-grid-premium">
                    <div>
                         <label class="input-label">Mobile Number</label>
                         <input type="text" name="mobile" id="add_mobile" class="modern-input" placeholder="Enter 10-digit mobile" pattern="\d{10}" maxlength="10" minlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Please enter exactly 10 digits">
                    </div>
                     <div>
                         <label class="input-label">Email Address</label>
                         <input type="email" name="email" id="add_email" class="modern-input" placeholder="Email address">
                    </div>
                     <div class="full-width">
                        <label class="input-label">City / Location</label>
                        <input type="text" name="city" id="add_city" class="modern-input" placeholder="City">
                    </div>
                     <div class="full-width">
                        <label class="input-label">Address</label>
                        <textarea name="address" id="add_address" class="modern-input" rows="3" placeholder="Full billing address"></textarea>
                    </div>
                 </div>
             </div>

             <div class="modal-footer-premium">
                 <button type="button" class="btn-ghost" onclick="closePartyModal('addPartyModal')">Cancel</button>
                 <button type="submit" class="btn-save">
                     <i class="fas fa-save"></i> Save Vendor
                 </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Party Modal -->
<div id="editPartyModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Vendor</h3>
                <p>Update vendor information</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('editPartyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
             <?= csrf_field() ?>
             <input type="hidden" name="action" value="update">
             <input type="hidden" name="id" id="edit_id">

             <div class="modal-body-premium">
                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Basic Info</div>
                 
                 <div class="form-grid-premium">
                    <div class="full-width">
                        <label class="input-label">Vendor Name *</label>
                        <input type="text" name="name" id="edit_name" required class="modern-input" placeholder="Enter business or person name">
                    </div>

                    <div>
                        <label class="input-label">Vendor Type *</label>
                        <select name="vendor_type" id="edit_vendor_type" required class="modern-select" style="width: 100%;">
                            <option value="supplier">Supplier</option>
                            <option value="contractor">Contractor</option>
                            <option value="service_provider">Service Provider</option>
                        </select>
                    </div>
                     <div>
                        <label class="input-label">Opening Balance (₹)</label>
                        <input type="number" name="opening_balance" id="edit_opening_balance" class="modern-input" placeholder="0.00" step="0.01">
                    </div>
                    
                     <div>
                        <label class="input-label">Status</label>
                        <select name="status" id="edit_status" class="modern-select" style="width: 100%;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                     <div>
                         <!-- Spacer -->
                    </div>

                     <div>
                        <label class="input-label">GST Status</label>
                         <select name="gst_status" id="edit_gst_status" class="modern-select" style="width: 100%;" onchange="toggleGstInput('edit')">
                            <option value="unregistered">Unregistered</option>
                            <option value="registered">Registered</option>
                            <option value="composition">Composition</option>
                        </select>
                    </div>
                     <div>
                        <label class="input-label">GST Number</label>
                        <input type="text" name="gst_number" id="edit_gst_number" class="modern-input" placeholder="GSTIN (Optional)">
                    </div>
                </div>

                <div class="form-section-title" style="margin-top: 24px;"><i class="fas fa-address-card"></i> Contact Details</div>
                
                 <div class="form-grid-premium">
                    <div>
                         <label class="input-label">Mobile Number</label>
                         <input type="text" name="mobile" id="edit_mobile" class="modern-input" placeholder="Enter 10-digit mobile" pattern="\d{10}" maxlength="10" minlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Please enter exactly 10 digits">
                    </div>
                     <div>
                         <label class="input-label">Email Address</label>
                         <input type="email" name="email" id="edit_email" class="modern-input" placeholder="Email address">
                    </div>
                     <div class="full-width">
                        <label class="input-label">City / Location</label>
                        <input type="text" name="city" id="edit_city" class="modern-input" placeholder="City">
                    </div>
                      <div class="full-width">
                        <label class="input-label">Address</label>
                        <textarea name="address" id="edit_address" class="modern-input" rows="3" placeholder="Full billing address"></textarea>
                    </div>
                 </div>
             </div>

             <div class="modal-footer-premium">
                 <button type="button" class="btn-ghost" onclick="closePartyModal('editPartyModal')">Cancel</button>
                 <button type="submit" class="btn-save">
                     <i class="fas fa-save"></i> Update Changes
                 </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deletePartyModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 400px; border-radius: 16px;">
        <div class="modal-body" style="text-align: center; padding: 40px 30px;">
            <div style="width: 72px; height: 72px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto;">
                <i class="fas fa-trash-alt" style="font-size: 32px; color: #ef4444;"></i>
            </div>
            
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #1e293b;">Delete Vendor?</h3>
            <p style="margin: 0 0 32px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                Are you sure you want to delete this vendor?<br>
                <span style="color: #ef4444; font-weight: 600;">This action cannot be undone.</span>
            </p>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="modern-btn btn-cancel" onclick="closePartyModal('deletePartyModal')">
                        Cancel
                    </button>
                    <button type="submit" class="modern-btn btn-danger">
                        Yes, Delete It
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Autocomplete Styles */
.autocomplete-list {
    position: absolute;
    top: 100%; left: 0; right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    border-radius: 8px;
    z-index: 50;
    margin-top: 4px;
    max-height: 250px;
    overflow-y: auto;
    display: none;
    list-style: none;
    padding: 0;
}
.autocomplete-list.show { display: block; }
.autocomplete-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f8fafc;
    font-size: 13px;
    color: #334155;
}
.autocomplete-item:hover { background: #f0f9ff; color: #0284c7; }
</style>

<script>
function openPartyModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closePartyModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function toggleFilter() {
    const section = document.getElementById('filterSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

function openDeleteModal(id) {
    document.getElementById('delete_id').value = id;
    openPartyModal('deletePartyModal');
}

function toggleGstInput(prefix) {
    const status = document.getElementById(prefix + '_gst_status').value;
    const input = document.getElementById(prefix + '_gst_number');
    
    if (status === 'unregistered') {
        input.disabled = true;
        input.style.background = '#f1f5f9';
        input.value = '';
    } else {
        input.disabled = false;
        input.style.background = '#fff';
    }
}

function editParty(party) {
    document.getElementById('edit_id').value = party.id;
    document.getElementById('edit_name').value = party.name;
    document.getElementById('edit_vendor_type').value = party.vendor_type || 'supplier';
    document.getElementById('edit_opening_balance').value = party.opening_balance || 0;
    
    document.getElementById('edit_mobile').value = party.mobile;
    document.getElementById('edit_email').value = party.email;
    
    document.getElementById('edit_gst_status').value = party.gst_status || (party.gst_number ? 'registered' : 'unregistered');
    toggleGstInput('edit'); // Set initial state
    document.getElementById('edit_gst_number').value = party.gst_number;
    
    document.getElementById('edit_city').value = party.city || '';
    document.getElementById('edit_address').value = party.address;
    document.getElementById('edit_status').value = party.status || 'active';
    
    openPartyModal('editPartyModal');
}

function viewPartyDetails(party) {
    document.getElementById('view_party_title').innerText = party.name;
    document.getElementById('view_mobile').innerText = party.mobile || 'N/A';
    document.getElementById('view_email').innerText = party.email || 'N/A';
    document.getElementById('view_gst').innerText = party.gst_number || 'N/A';
    document.getElementById('view_address').innerText = party.address || 'N/A';

    // Type is always vendor here
    openPartyModal('viewPartyModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
        document.body.style.overflow = 'auto';
    }
}

// Manage Bills Logic
let currentVendorId = null;
let currentVendorName = '';
let unbilledChallans = [];

function openManageBills(vendorId, vendorName) {
    currentVendorId = vendorId;
    currentVendorName = vendorName;
    document.getElementById('manage_bills_vendor_name').innerText = vendorName;
    
    openPartyModal('manageBillsModal');
    loadBills(vendorId);
}

function loadBills(vendorId) {
    const container = document.getElementById('bills_list_container');
    container.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('../bills/get_bills.php?vendor_id=' + vendorId)
    .then(response => {
        if (!response.ok) return []; 
        return response.json();
    })
    .catch(err => [])
    .then(bills => {
        if (!bills || bills.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px; border: 2px dashed #cbd5e1; border-radius: 12px;">
                    <i class="fas fa-file-invoice" style="font-size: 32px; color: #cbd5e1; margin-bottom: 10px;"></i>
                    <div style="color: #64748b; font-weight: 500;">No bills recorded yet.</div>
                    <div style="font-size: 13px; color: #94a3b8;">Click "Add New Bill" to start.</div>
                </div>`;
            return;
        }
        
        // Render Bills Table
        let html = `
        <table class="modern-table" style="margin-top:0;">
            <thead>
                <tr>
                    <th style="text-align:left;">Bill No</th>
                    <th>Date</th>
                    <th>Challan No</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Status</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>`;
            
        bills.forEach(bill => {
            const statusColor = bill.status === 'paid' ? 'green' : (bill.status === 'partial' ? 'orange' : 'red');
            const amount = parseFloat(bill.amount).toFixed(2);
            // Prepare bill object for edit
            const billJson = JSON.stringify(bill).replace(/"/g, '&quot;');
            
            let fileBtn = '';
            if (bill.file_path) {
                fileBtn = `
                <a href="<?= BASE_URL ?>${bill.file_path}" target="_blank" class="action-btn" title="View Bill File" style="color:#64748b; margin-right:4px;">
                    <i class="fas fa-eye" style="font-size: 11px;"></i>
                </a>`;
            }

            html += `
            <tr>
                <td style="text-align:left; font-weight:600;">${bill.bill_no}</td>
                <td>${bill.bill_date}</td>
                <td>${bill.challan_no ? '<span class="badge-pill blue">'+bill.challan_no+'</span>' : '<span style="color:#cbd5e1">-</span>'}</td>
                <td style="text-align:right; font-weight:700;">₹ ${amount}</td>
                <td><span class="badge-pill ${statusColor}">${bill.status.toUpperCase()}</span></td>
                <td>
                    <div style="display:flex; justify-content:center;">
                        ${fileBtn}
                        <button class="action-btn" onclick="openEditBillModal(${billJson})" title="Edit Bill">
                            <i class="fas fa-edit" style="font-size: 11px;"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        });
        
        html += `</tbody></table>`;
        container.innerHTML = html;
    });
}

function openEditBillModal(bill) {
    // Keep manage bills open in background
    document.getElementById('edit_bill_id').value = bill.id;
    document.getElementById('edit_bill_no').value = bill.bill_no;
    document.getElementById('edit_bill_date').value = bill.bill_date;
    document.getElementById('edit_bill_amount').value = bill.amount;
    
    if(bill.file_path) {
        document.getElementById('edit_file_status').innerText = 'Current file: ' + bill.file_path.split('/').pop();
    } else {
        document.getElementById('edit_file_status').innerText = 'No file attached.';
    }
    
    // Reset calculator fields
    document.getElementById('edit_calc_qty').value = '';
    document.getElementById('edit_calc_rate').value = '';

    openPartyModal('editBillModal');
}

function calculateEditBillAmount() {
    const qty = parseFloat(document.getElementById('edit_calc_qty').value) || 0;
    const rate = parseFloat(document.getElementById('edit_calc_rate').value) || 0;
    
    if (qty > 0 && rate > 0) {
        const total = qty * rate;
        document.getElementById('edit_bill_amount').value = total.toFixed(2);
    }
}

function openAddBillModal() {
    closePartyModal('manageBillsModal'); 
    
    document.getElementById('add_bill_vendor_name').innerText = currentVendorName;
    document.getElementById('bill_vendor_id').value = currentVendorId;
    
    // Fetch Unbilled Challans
    const select = document.getElementById('bill_challan_select');
    select.innerHTML = '<option value="">Loading...</option>';
    
    fetch('../bills/get_unbilled_challans.php?vendor_id=' + currentVendorId)
        .then(res => res.json())
        .then(data => {
            unbilledChallans = data;
            select.innerHTML = '<option value="">-- Direct Bill (No Challan) --</option>';
            if (data.length > 0) {
                data.forEach(c => {
                    select.innerHTML += `<option value="${c.id}">Challan #${c.challan_no} (${c.challan_date}) - ₹${c.total_amount}</option>`;
                });
            }
        });
        
    openPartyModal('addBillModal');
}

function onChallanSelect(select) {
    const id = select.value;
    const preview = document.getElementById('challan_preview');
    const itemsDisplay = document.getElementById('challan_items_display');
    const amountInput = document.getElementById('bill_amount');
    const dateInput = document.getElementById('bill_date');
    
    if (!id) {
        preview.style.display = 'none';
        amountInput.value = '';
        return;
    }
    
    const challan = unbilledChallans.find(c => c.id == id);
    if (challan) {
        itemsDisplay.innerText = challan.materials || 'No items';
        amountInput.value = challan.total_amount;
        if (challan.challan_date) dateInput.value = challan.challan_date;
        
        // Auto-fetch quantity
        if (challan.total_quantity) {
            document.getElementById('calc_qty').value = challan.total_quantity;
        }
        
        preview.style.display = 'block';
    }
}

function calculateBillAmount() {
    const qty = parseFloat(document.getElementById('calc_qty').value) || 0;
    const rate = parseFloat(document.getElementById('calc_rate').value) || 0;
    
    if (qty > 0 && rate > 0) {
        const total = qty * rate;
        document.getElementById('bill_amount').value = total.toFixed(2);
    }
}

// Autocomplete Logic for Challan Search
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('open_add_modal')) {
        openPartyModal('addPartyModal');
        if (urlParams.has('return_url')) {
            document.getElementById('add_return_url').value = urlParams.get('return_url');
        }
    }

    const searchInput = document.getElementById('link_challan_search');
    const suggestionsList = document.getElementById('challan_suggestions');
    const hiddenIdInput = document.getElementById('link_challan_id');

    searchInput.addEventListener('input', function() {
        const val = this.value;
        if (!val || val.length < 1) {
            suggestionsList.classList.remove('show');
            return;
        }

        fetch('<?= BASE_URL ?>modules/challans/get_challan_data.php?search=' + encodeURIComponent(val))
            .then(res => res.json())
            .then(data => {
                suggestionsList.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(challan => {
                        const li = document.createElement('li');
                        li.className = 'autocomplete-item';
                        li.innerHTML = `<strong>${challan.challan_no}</strong> - <span style="color:#64748b">${challan.vendor_name || 'No Vendor Name'}</span>`;
                        li.onclick = () => {
                            // Link ID
                            hiddenIdInput.value = challan.id;
                            searchInput.value = challan.challan_no;
                            suggestionsList.classList.remove('show');

                            // Open Custom Confirm Modal
                            window.confirmAutoFill(challan);
                        };
                        suggestionsList.appendChild(li);
                    });
                    suggestionsList.classList.add('show');
                } else {
                    suggestionsList.classList.remove('show');
                }
            });
    });

    // Close list if clicked outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput) {
            suggestionsList.classList.remove('show');
        }
    });

    // Auto-fill Logic with Custom Modal
    let pendingChallanData = null;

    window.confirmAutoFill = function(challan) {
        pendingChallanData = challan;
        document.getElementById('confirm_challan_no').innerText = challan.challan_no;
        openPartyModal('autoFillConfirmModal');
    };

    window.handleAutoFillChoice = function(shouldFill) {
        closePartyModal('autoFillConfirmModal');
        
        if (shouldFill && pendingChallanData) {
            const c = pendingChallanData;
            // Highlight fields being filled for a moment to give feedback
            const fillField = (id, val) => {
                const el = document.getElementById(id);
                if (val) {
                    el.value = val;
                    el.style.backgroundColor = '#f0f9ff';
                    setTimeout(() => el.style.backgroundColor = '#f8fafc', 1000);
                }
            };

            if (c.vendor_name) fillField('add_name', c.vendor_name);
            if (c.mobile) fillField('add_mobile', c.mobile);
            if (c.email) fillField('add_email', c.email);
            if (c.address) fillField('add_address', c.address);
            if (c.gst_number) fillField('add_gst_number', c.gst_number);
        }
        pendingChallanData = null;
    };
});
</script>

<!-- Auto-Fill Confirmation Modal -->
<div id="autoFillConfirmModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 450px; border-radius: 16px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #334155 100%); padding: 25px; text-align: center;">
             <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-magic" style="font-size: 24px; color: #38bdf8;"></i>
            </div>
            <h3 style="margin: 0; color: white; font-size: 18px; font-weight: 700;">Import Details?</h3>
        </div>
        
        <div style="padding: 30px 25px; text-align: center;">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                Found vendor details in Challan <strong id="confirm_challan_no" style="color: #0369a1;"></strong>. 
                <br>Do you want to auto-fill the form with this information?
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" onclick="handleAutoFillChoice(false)" 
                        style="padding: 10px 24px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    No, keep empty
                </button>
                <button type="button" onclick="handleAutoFillChoice(true)" 
                        style="padding: 10px 24px; border: none; background: #0ea5e9; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3); transition: all 0.2s;">
                    Yes, Auto-fill
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
