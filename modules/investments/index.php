<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/InvestmentService.php';
require_once __DIR__ . '/../../includes/MasterService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$investmentService = new InvestmentService();
$masterService = new MasterService();
$page_title = 'Investments';
$current_page = 'investments';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/investments/index.php');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $investmentService->createInvestment($_POST, $_SESSION['user_id']);
            setFlashMessage('success', 'Investment recorded successfully');
        
        } elseif ($action === 'update') {
            $investmentService->updateInvestment(intval($_POST['id']), $_POST);
            setFlashMessage('success', 'Investment updated successfully');
        
        } elseif ($action === 'delete') {
            $investmentService->deleteInvestment(intval($_POST['id']));
            setFlashMessage('success', 'Investment deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/investments/index.php');
}

// Fetch all investments
$filters = [
    'search' => $_GET['search'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'investment_type' => $_GET['investment_type'] ?? ''
];
$investments = $investmentService->getAllInvestments($filters);
$projects = $masterService->getAllProjects();

// Stats
$total_invested = 0;
foreach($investments as $inv) {
    $total_invested += $inv['amount'];
}

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Determine filename
    $filename = 'investments_' . date('Y-m-d') . '.csv';
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write Column Headers
    fputcsv($output, ['Date', 'Project', 'Investor Name', 'Type', 'Amount', 'Remarks']);
    
    // Write Data
    foreach ($investments as $row) {
        fputcsv($output, [
            date('d-M-Y', strtotime($row['investment_date'])),
            $row['project_name'],
            $row['investor_name'],
            ucfirst($row['investment_type']),
            $row['amount'],
            $row['remarks']
        ]);
    }
    
    fclose($output);
    exit();
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Modern Dashboard Style -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Local overrides */
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
    max-width: 600px;
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
    background: linear-gradient(135deg, #059669 0%, #10b981 100%); /* Emerald Gradient */
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
.modal-footer-premium {
    padding: 24px 32px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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
    border-color: #10b981;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}
.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }
.input-group-modern { margin-bottom: 20px; }

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.modern-table thead th {
    background: #f8fafc;
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    padding: 16px 24px;
    letter-spacing: 0.8px;
    border-bottom: 2px solid #e2e8f0;
    text-align: left;
    white-space: nowrap;
}
.modern-table tbody tr { 
    background: #fff;
    transition: background-color 0.2s ease;
}
.modern-table tbody tr:hover {
    background: #f8fafc;
}
.modern-table td {
    padding: 16px 24px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
}
.modern-table td:first-child,
.modern-table th:first-child {
    padding-left: 24px;
}
.modern-table td:last-child,
.modern-table th:last-child {
    padding-right: 24px;
}
/* Alignment utilities */
.text-center { text-align: center !important; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }

.badge-pill {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-pill.green { background: #ecfdf5; color: #10b981; }
.badge-pill.blue { background: #eff6ff; color: #3b82f6; }
.badge-pill.purple { background: #f3e8ff; color: #9333ea; }
.badge-pill.orange { background: #fff7ed; color: #c2410c; }
.badge-pill.gray { background: #f1f5f9; color: #64748b; }

.action-btn { color: #94a3b8; cursor: pointer; transition: 0.2s; margin: 0 4px; border:none; background:transparent;}
.action-btn:hover { color: #64748b; }
.action-btn.delete:hover { color: #ef4444; }

.btn-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); }

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

.modern-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none;
}
/* Hover Effect for Short/Full Numbers */
.stat-card-custom .full-value {
    display: none;
    font-size: 20px;
}
.stat-card-custom:hover .short-value {
    display: none;
}
.stat-card-custom:hover .full-value {
    display: inline-block;
    animation: fadeIn 0.2s ease-in;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(2px); }
    to { opacity: 1; transform: translateY(0); }
}

.modern-icon {
    width: 50px;
    height: 48px;
    border-radius: 12px;
    background: #ecfdf5;
    color: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
}

.holding-hand {
    margin-left: -2px;
    transform: translateY(12px);
    font-size: 1.5rem;
}

.inr-above {
    font-size: 0.7em;
    position: relative;
    top: -20px;   /* force ABOVE the hand */
}

</style>

<!-- Stats Row -->
<div class="row" style="margin-bottom: 25px;">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card-custom" style="background:white; padding:20px; border-radius:16px; display:flex; gap:15px; align-items:center; border:1px solid #f1f5f9;">
            <div class="modern-icon">
                <span class="fa-layers fa-fw">
                    <i class="fas fa-hand-holding holding-hand"></i>
                    <i class="fas fa-indian-rupee-sign inr-above"></i>
                </span>
            </div>
            <div>
                <div style="font-size:13px; font-weight:600; color:#64748b; text-transform:uppercase;">Total Invested</div>
                <div style="font-size:24px; font-weight:800; color:#1e293b;">
                    <span class="short-value"><?= formatCurrencyShort($total_invested) ?></span>
                    <span class="full-value"><?= formatCurrency($total_invested) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                <div class="chart-title-group">
                    <h3>
                        <div style="width:36px; height:36px; background:#ecfdf5; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#10b981; margin-right:10px;">
                            <i class="fas fa-hand-holding" style="transform: translateX(3px);"></i>
                            <i class="fas fa-indian-rupee-sign" style="font-size: 0.6rem; transform: translateY(-6px); margin-left:-11px; margin-right: 11px"></i>
                        </div>
                        Investments
                    </h3>
                    <div style="padding-left:46px; font-size:13px; color:#94a3b8; font-weight:500;">Track capital, loans, and partner contributions</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="document.getElementById('filterSection').style.display = document.getElementById('filterSection').style.display === 'none' ? 'block' : 'none'" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <a href="?export=csv&search=<?= urlencode($filters['search']) ?>&project_id=<?= urlencode($filters['project_id']) ?>&investment_type=<?= urlencode($filters['investment_type']) ?>" class="modern-btn" style="background: linear-gradient(135deg, #5b5e63ff 0%, #99a0aaff 100%); color:#fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                    
                    <button class="modern-btn" onclick="openModal('addInvestmentModal')" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Investment
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($filters['search'] || $filters['project_id'] || $filters['investment_type']) ? 'block' : 'none' ?>; margin-bottom:20px;">
                <form method="GET" style="background: #f8fafc; border-radius: 12px; padding: 15px;">
                    <div style="display:flex; gap:10px;">
                        <div style="flex: 2; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                            <input type="text" name="search" class="modern-input" placeholder="Search investor..." value="<?= htmlspecialchars($filters['search']) ?>" style="padding-left: 32px;">
                        </div>
                        <select name="project_id" class="modern-select" style="flex:1;">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $filters['project_id'] == $proj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($proj['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="investment_type" class="modern-select" style="flex:1;">
                            <option value="">All Types</option>
                            <option value="loan" <?= $filters['investment_type'] === 'loan' ? 'selected' : '' ?>>Loan</option>
                            <option value="partner" <?= $filters['investment_type'] === 'partner' ? 'selected' : '' ?>>Partner Contribution</option>
                            <option value="personal" <?= $filters['investment_type'] === 'personal' ? 'selected' : '' ?>>Personal Capital</option>
                            <option value="other" <?= $filters['investment_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <button type="submit" class="modern-btn" style="background:#0f172a;">Apply</button>
                        <a href="index.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 0%;">Date</th>
                            <th class="text-left" style="width: 0%;">Project</th>
                            <th class="text-center" style="width: 0%;">Investor</th>
                            <th class="text-center" style="width: 0%;">Type</th>
                            <th class="text-center" style="width: 0%;">Amount</th>
                            <th class="text-center" style="width: 0%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($investments)): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 40px; color: #64748b;">
                                    <i class="fas fa-folder-open" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No investments found.
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($investments as $inv): 
                                $color = ColorHelper::getProjectColor($inv['project_id']);
                                $initial = ColorHelper::getInitial($inv['project_name']);
                        ?>
                        <tr>
                            <td class="text-center">
                                <span style="font-size:13px; font-weight:600; color:#64748b;"><?= formatDate($inv['investment_date']) ?></span>
                            </td>
                            <td class="text-left">
                                <div style="display:flex; align-items:center; justify-content: flex-start">
                                    <div class="avatar-square" style="background: <?= $color ?>; width:32px; height:32px; border-radius: 8px; font-size:11px; font-weight:700; color:white; margin-right:12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);"><?= $initial ?></div>
                                    <span style="font-weight:600; font-size: 14px;"><?= htmlspecialchars($inv['project_name']) ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($inv['investor_name']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge-pill gray" style="font-weight: 600; letter-spacing: 0.3px;"><?= ucfirst($inv['investment_type']) ?></span>
                            </td>
                            <td class="text-center">
                                <span style="font-weight:700; color:#059669; font-family: 'Inter', sans-serif;"><?= formatCurrency($inv['amount']) ?></span>
                            </td>
                            <td class="text-center" style="white-space: nowrap;">
                                <button class="action-btn" onclick="viewInvestment(<?= htmlspecialchars(json_encode($inv)) ?>)" title="View Details">
                                    <i class="far fa-eye"></i>
                                </button>
                                <button class="action-btn" onclick="editInvestment(<?= htmlspecialchars(json_encode($inv)) ?>)" title="Edit">
                                    <i class="far fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="openDeleteModal(<?= $inv['id'] ?>)" title="Delete">
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

<!-- View Investment Modal -->
<div id="viewInvestmentModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-file-invoice-dollar"></i> Investment Details</h3>
                <p>View complete investment record</p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('viewInvestmentModal')">&times;</button>
        </div>
        <div class="modal-body-premium">
            <div style="background: #f0fdf4; border: 1px dashed #22c55e; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;">
                <div style="font-size: 11px; text-transform: uppercase; color: #15803d; font-weight: 700; margin-bottom: 5px;">Invested Amount</div>
                <div id="view_amount" style="font-size: 32px; font-weight: 800; color: #166534;">₹ 0.00</div>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px;">Project</span>
                <span id="view_project" style="color: #1e293b; font-weight: 700; font-size: 14px;"></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px;">Investor</span>
                <span id="view_investor" style="color: #1e293b; font-weight: 700; font-size: 14px;"></span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px;">Type</span>
                <span id="view_type" class="badge-pill" style="font-size: 12px;"></span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px;">Date</span>
                <span id="view_date" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
            </div>

            <div style="margin-top: 15px;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px; display: block; margin-bottom: 8px;">Remarks</span>
                <div id="view_remarks" style="background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 14px; color: #475569; line-height: 1.5; min-height: 40px;"></div>
            </div>
        </div>
        <div class="modal-footer-premium">
            <button type="button" class="modern-btn" style="background:#f1f5f9; color:#64748b;" onclick="closeModal('viewInvestmentModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Investment Modal -->
<div id="addInvestmentModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-hand-holding-usd"></i> New Investment</h3>
                <p>Record initial capital or loan</p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('addInvestmentModal')">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body-premium">
                <div class="form-section-title"><i class="fas fa-info-circle"></i> Investment Details</div>
                
                <div class="form-grid-premium">
                    <div class="input-group-modern full-width">
                        <label class="input-label">Project *</label>
                        <select name="project_id" required class="modern-select">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Investor Name *</label>
                        <input type="text" name="investor_name" required class="modern-input" placeholder="e.g. HDFC Bank, John Doe">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Type *</label>
                        <select name="investment_type" required class="modern-select">
                            <option value="other">Other</option>
                            <option value="loan">Loan</option>
                            <option value="partner">Partner Contribution</option>
                            <option value="personal">Personal Capital</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Amount (₹) *</label>
                        <input type="number" name="amount" required step="0.01" class="modern-input" placeholder="0.00">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Date *</label>
                        <input type="date" name="investment_date" required value="<?= date('Y-m-d') ?>" class="modern-input">
                    </div>
                </div>

                <div class="input-group-modern">
                    <label class="input-label">Remarks</label>
                    <textarea name="remarks" class="modern-input" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>

            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeModal('addInvestmentModal')">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Investment Modal -->
<div id="editInvestmentModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Investment</h3>
                <p>Update investment record</p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('editInvestmentModal')">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-body-premium">
                <div class="form-section-title"><i class="fas fa-info-circle"></i> Investment Details</div>
                
                <div class="form-grid-premium">
                    <div class="input-group-modern full-width">
                        <label class="input-label">Project *</label>
                        <select name="project_id" id="edit_project_id" required class="modern-select">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Investor Name *</label>
                        <input type="text" name="investor_name" id="edit_investor_name" required class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Type *</label>
                        <select name="investment_type" id="edit_investment_type" required class="modern-select">
                            <option value="other">Other</option>
                            <option value="loan">Loan</option>
                            <option value="partner">Partner Contribution</option>
                            <option value="personal">Personal Capital</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Amount (₹) *</label>
                        <input type="number" name="amount" id="edit_amount" required step="0.01" class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Date *</label>
                        <input type="date" name="investment_date" id="edit_investment_date" required class="modern-input">
                    </div>
                </div>

                <div class="input-group-modern">
                    <label class="input-label">Remarks</label>
                    <textarea name="remarks" id="edit_remarks" class="modern-input" rows="2"></textarea>
                </div>
            </div>

            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeModal('editInvestmentModal')">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteInvestmentModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 400px; border-radius: 16px;">
        <div class="modal-body" style="text-align: center; padding: 40px 30px;">
            <div style="width: 72px; height: 72px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto;">
                <i class="fas fa-trash-alt" style="font-size: 32px; color: #ef4444;"></i>
            </div>
            
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #1e293b;">Delete Investment?</h3>
            <p style="margin: 0 0 32px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                Are you sure you want to delete this record?<br>
                <span style="color: #ef4444; font-weight: 600;">This action cannot be undone.</span>
            </p>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="modern-btn" style="background: #f1f5f9; color: #64748b;" onclick="closeModal('deleteInvestmentModal')">
                        Cancel
                    </button>
                    <button type="submit" class="modern-btn" style="background: #ef4444; color: white;">
                        Yes, Delete It
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
        document.body.style.overflow = 'auto';
    }
}

function formatMoney(amount) {
    return '₹ ' + parseFloat(amount).toLocaleString('en-IN', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2
    });
}

function viewInvestment(inv) {
    document.getElementById('view_amount').innerText = formatMoney(inv.amount);
    document.getElementById('view_project').innerText = inv.project_name;
    document.getElementById('view_investor').innerText = inv.investor_name;
    
    // Type Badge
    const typeSpan = document.getElementById('view_type');
    typeSpan.innerText = inv.investment_type.charAt(0).toUpperCase() + inv.investment_type.slice(1);
    
    // Remove existing colors
    typeSpan.classList.remove('green', 'blue', 'orange', 'gray', 'purple');
    
    // Add correct color logic
    if (inv.investment_type === 'loan') typeSpan.style.background = '#fee2e2'; 
    else if (inv.investment_type === 'partner') typeSpan.style.background = '#eff6ff';
    else if (inv.investment_type === 'personal') typeSpan.style.background = '#f0fdf4';
    else typeSpan.style.background = '#f1f5f9';
    
    typeSpan.style.color = '#475569'; // Default
    if (inv.investment_type === 'loan') typeSpan.style.color = '#ef4444';
    else if (inv.investment_type === 'partner') typeSpan.style.color = '#3b82f6';
    else if (inv.investment_type === 'personal') typeSpan.style.color = '#15803d';

    
    document.getElementById('view_date').innerText = new Date(inv.investment_date).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
    
    document.getElementById('view_remarks').innerText = inv.remarks || 'No remarks provided.';
    
    openModal('viewInvestmentModal');
}

function editInvestment(inv) {
    document.getElementById('edit_id').value = inv.id;
    document.getElementById('edit_project_id').value = inv.project_id;
    document.getElementById('edit_investor_name').value = inv.investor_name;
    document.getElementById('edit_investment_type').value = inv.investment_type;
    document.getElementById('edit_amount').value = inv.amount;
    document.getElementById('edit_investment_date').value = inv.investment_date;
    document.getElementById('edit_remarks').value = inv.remarks || '';
    
    openModal('editInvestmentModal');
}

function openDeleteModal(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteInvestmentModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
