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
requireAuth();

$masterService = new MasterService();
$page_title = 'Parties';
$current_page = 'parties';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $data = [
                'party_type' => $_POST['party_type'],
                'name' => sanitize($_POST['name']),
                'contact_person' => sanitize($_POST['contact_person']),
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'gst_number' => sanitize($_POST['gst_number'])
            ];
            $masterService->createParty($data);
            setFlashMessage('success', 'Party created successfully');
            
            if (!empty($_POST['return_url'])) {
                header('Location: ' . $_POST['return_url']);
                exit;
            }
            
        } elseif ($action === 'update') {
            $data = [
                'party_type' => $_POST['party_type'],
                'name' => sanitize($_POST['name']),
                'contact_person' => sanitize($_POST['contact_person']),
                'mobile' => sanitize($_POST['mobile']),
                'email' => sanitize($_POST['email']),
                'address' => sanitize($_POST['address']),
                'gst_number' => sanitize($_POST['gst_number'])
            ];
            $masterService->updateParty(intval($_POST['id']), $data);
            setFlashMessage('success', 'Party updated successfully');
            
        } elseif ($action === 'delete') {
            $masterService->deleteParty(intval($_POST['id']));
            setFlashMessage('success', 'Party deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/masters/parties.php');
}

// Fetch all parties
$filters = [
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];
$parties = $masterService->getAllParties($filters);

// Statistics
$stats = [
    'total' => count($parties),
    'customer' => count(array_filter($parties, fn($p) => $p['party_type'] === 'customer')),
    'vendor' => count(array_filter($parties, fn($p) => $p['party_type'] === 'vendor')),
    'labour' => count(array_filter($parties, fn($p) => $p['party_type'] === 'labour'))
];

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
    background-color: rgba(0,0,0,0.5); 
    padding-top: 50px;
    backdrop-filter: blur(4px);
}
.custom-modal-content {
    background-color: #fefefe;
    margin: auto;
    border: none;
    width: 90%; 
    max-width: 600px;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
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
/* Chart styles managed by booking.css */
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
.badge-pill.customer { background: #ecfdf5; color: #10b981; }
.badge-pill.vendor { background: #eff6ff; color: #3b82f6; }
.badge-pill.labour { background: #fff7ed; color: #f59e0b; }

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
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
.form-full {
    grid-column: 1 / -1;
}

/* Modal Button Hover Effects */
.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: none;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-cancel:hover {
    background: #e2e8f0;
    color: #475569;
}
.btn-danger {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 24px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
}
.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
}

/* Removed conflicting button variants */

/* Page Specific Styles */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-modern {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
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
}

.bg-emerald-light { background: #ecfdf5; color: #059669; }
.bg-blue-light { background: #eff6ff; color: #3b82f6; }
.bg-orange-light { background: #fff7ed; color: #f59e0b; }
.bg-violet-light { background: #f5f3ff; color: #8b5cf6; }

/* Premium Modal Styles (from Flats) */
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

/* Modern Inputs with Blue Focus */
.input-group-modern {
    margin-bottom: 20px;
    display: block; 
}

.input-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #334155;
    font-size: 13px;
}

.modern-input, .modern-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    color: #1e293b;
    transition: all 0.2s;
    outline: none;
    background: #f8fafc;
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

.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }
</style>

<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h4>Total Parties</h4>
            <div class="value"><?= $stats['total'] ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-emerald-light">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-info">
            <h4>Customers</h4>
            <div class="value"><?= $stats['customer'] ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h4>Vendors</h4>
            <div class="value"><?= $stats['vendor'] ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-orange-light">
            <i class="fas fa-hard-hat"></i>
        </div>
        <div class="stat-info">
            <h4>Labour</h4>
            <div class="value"><?= $stats['labour'] ?></div>
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
                        <div class="chart-icon-box blue"><i class="fas fa-users"></i></div>
                        Parties
                    </h3>
                    <div class="chart-subtitle">Manage customers, vendors, and contractors</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="toggleFilter()" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <button class="modern-btn" onclick="openPartyModal('addPartyModal')" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Party
                    </button>
                </div>
            </div>



            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($filters['search'] || $filters['type']) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <div style="flex: 2; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                            <input type="text" name="search" class="modern-input" placeholder="Search by name, mobile, email..." value="<?= htmlspecialchars($filters['search']) ?>" style="padding-left: 32px;">
                        </div>
                        <select name="type" class="modern-select" style="flex:1;">
                            <option value="">All Types</option>
                            <option value="customer" <?= $filters['type'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="vendor" <?= $filters['type'] === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                            <option value="labour" <?= $filters['type'] === 'labour' ? 'selected' : '' ?>>Labour</option>
                        </select>
                        <button type="submit" class="modern-btn">Apply</button>
                        <a href="parties.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Type</th>
                            <th>Contact Info</th>
                            <th>GST Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parties)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-folder-open" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No parties found.
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($parties as $party): 
                                $color = ColorHelper::getCustomerColor($party['id']);
                                $initial = ColorHelper::getInitial($party['name']);
                        ?>
                        <tr>
                            <td style="text-align: left;">
                                <div style="display:flex; align-items:center;">
                                    <div class="avatar-square" style="background: <?= $color ?>;"><?= $initial ?></div>
                                    <div>
                                        <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($party['name'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:13px; font-weight:600; color:#475569;">
                                    <?= htmlspecialchars($party['contact_person'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-pill <?= $party['party_type'] ?>">
                                    <?= ucfirst($party['party_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; align-items: center; gap:4px;">
                                    <?php if(!empty($party['mobile'])): ?>
                                    <span style="font-size: 13px; color: #64748b;"><i class="fas fa-phone-alt" style="font-size:11px; margin-right:4px;"></i> <?= htmlspecialchars($party['mobile']) ?></span>
                                    <?php endif; ?>
                                    <?php if(!empty($party['email'])): ?>
                                    <span style="font-size: 13px; color: #64748b;"><i class="fas fa-envelope" style="font-size:11px; margin-right:4px;"></i> <?= htmlspecialchars($party['email']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 13px; font-weight:600; color: #475569;">
                                    <?= htmlspecialchars($party['gst_number'] ?? '-') ?>
                                </span>
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
                 <span id="view_party_type" class="badge-pill" style="font-size: 12px; padding: 6px 16px;"></span>
            </div>
            
            <div style="background: #f8fafc; border-radius: 12px; padding: 10px;">
                <div style="display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600; font-size: 13px;">Contact Person</span>
                    <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_contact_person"></span>
                </div>
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

<!-- Add Party Modal -->
<div id="addPartyModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-plus-circle"></i> Add New Party</h3>
                <p>Enter party details</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('addPartyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
             <input type="hidden" name="action" value="create">
             <input type="hidden" name="return_url" id="add_return_url">

             <div class="modal-body-premium">
                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Basic Info</div>
                 
                 <div class="form-grid-premium">
                    <div class="full-width">
                        <label class="input-label">Party Type *</label>
                        <select name="party_type" required class="modern-select">
                            <option value="">Select Type</option>
                            <option value="customer">Customer</option>
                            <option value="vendor">Vendor</option>
                            <option value="labour">Labour/Contractor</option>
                        </select>
                    </div>

                    <div class="full-width">
                        <label class="input-label">Party Name *</label>
                        <input type="text" name="name" required class="modern-input" placeholder="Enter business or person name">
                    </div>

                    <div>
                         <label class="input-label">Contact Person</label>
                         <input type="text" name="contact_person" class="modern-input" placeholder="Point of contact">
                    </div>
                     <div>
                         <label class="input-label">GST Number</label>
                         <input type="text" name="gst_number" class="modern-input" placeholder="GSTIN (Optional)">
                    </div>
                </div>

                <div class="form-section-title" style="margin-top: 24px;"><i class="fas fa-address-card"></i> Contact Details</div>
                
                 <div class="form-grid-premium">
                    <div>
                         <label class="input-label">Mobile Number</label>
                         <input type="text" name="mobile" class="modern-input" placeholder="Enter 10-digit mobile" pattern="\d{10}" maxlength="10" minlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Please enter exactly 10 digits">
                    </div>
                     <div>
                         <label class="input-label">Email Address</label>
                         <input type="email" name="email" class="modern-input" placeholder="Email address">
                    </div>
                     <div class="full-width">
                        <label class="input-label">Address</label>
                        <textarea name="address" class="modern-input" rows="3" placeholder="Full billing address"></textarea>
                    </div>
                 </div>
             </div>

             <div class="modal-footer-premium">
                 <button type="button" class="btn-ghost" onclick="closePartyModal('addPartyModal')">Cancel</button>
                 <button type="submit" class="btn-save">
                     <i class="fas fa-save"></i> Save Party
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
                <h3><i class="fas fa-edit"></i> Edit Party</h3>
                <p>Update party information</p>
            </div>
            <button class="modal-close-btn" onclick="closePartyModal('editPartyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
             <input type="hidden" name="action" value="update">
             <input type="hidden" name="id" id="edit_id">

             <div class="modal-body-premium">
                 <div class="form-section-title"><i class="fas fa-info-circle"></i> Basic Info</div>
                 
                 <div class="form-grid-premium">
                    <div class="full-width">
                         <label class="input-label">Party Type *</label>
                        <select name="party_type" id="edit_party_type" required class="modern-select">
                            <option value="">Select Type</option>
                            <option value="customer">Customer</option>
                            <option value="vendor">Vendor</option>
                            <option value="labour">Labour/Contractor</option>
                        </select>
                    </div>

                    <div class="full-width">
                        <label class="input-label">Party Name *</label>
                        <input type="text" name="name" id="edit_name" required class="modern-input" placeholder="Enter business or person name">
                    </div>

                    <div>
                         <label class="input-label">Contact Person</label>
                         <input type="text" name="contact_person" id="edit_contact_person" class="modern-input" placeholder="Point of contact">
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
            
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #1e293b;">Delete Party?</h3>
            <p style="margin: 0 0 32px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                Are you sure you want to delete this party?<br>
                <span style="color: #ef4444; font-weight: 600;">This action cannot be undone.</span>
            </p>
            
            <form method="POST">
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

function editParty(party) {
    document.getElementById('edit_id').value = party.id;
    document.getElementById('edit_party_type').value = party.party_type;
    document.getElementById('edit_name').value = party.name;
    document.getElementById('edit_contact_person').value = party.contact_person;
    document.getElementById('edit_mobile').value = party.mobile;
    document.getElementById('edit_email').value = party.email;
    document.getElementById('edit_gst_number').value = party.gst_number;
    document.getElementById('edit_address').value = party.address;
    openPartyModal('editPartyModal');
}

function viewPartyDetails(party) {
    document.getElementById('view_party_title').innerText = party.name;
    document.getElementById('view_contact_person').innerText = party.contact_person || 'N/A';
    document.getElementById('view_mobile').innerText = party.mobile || 'N/A';
    document.getElementById('view_email').innerText = party.email || 'N/A';
    document.getElementById('view_gst').innerText = party.gst_number || 'N/A';
    document.getElementById('view_address').innerText = party.address || 'N/A';

    const typeBadge = document.getElementById('view_party_type');
    typeBadge.className = 'badge-pill ' + party.party_type;
    typeBadge.innerText = party.party_type.charAt(0).toUpperCase() + party.party_type.slice(1);

    openPartyModal('viewPartyModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('open_add_modal')) {
        openPartyModal('addPartyModal');
        
        if (urlParams.has('pre_party_type')) {
            const typeSelect = document.querySelector('#addPartyModal select[name="party_type"]');
            if (typeSelect) {
                typeSelect.value = urlParams.get('pre_party_type');
            }
        }
        
        if (urlParams.has('return_url')) {
            document.getElementById('add_return_url').value = urlParams.get('return_url');
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
