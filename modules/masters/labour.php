<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Labour Pay';
$current_page = 'labour_pay';

// Handle challan operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_challan') {
        $labour_id = intval($_POST['labour_id']);
        $project_id = intval($_POST['project_id']);
        $challan_date = $_POST['challan_date'];
        $work_description = sanitize($_POST['work_description']);
        $work_from_date = $_POST['work_from_date'];
        $work_to_date = $_POST['work_to_date'];
        $total_amount = floatval($_POST['total_amount']);
        
        $db->beginTransaction();
        try {
            // Generate challan number
            $challan_no = generateChallanNo('labour', $db);
            
            // Create challan
            $challan_data = [
                'challan_no' => $challan_no,
                'challan_type' => 'labour',
                'party_id' => $labour_id,
                'project_id' => $project_id,
                'challan_date' => $challan_date,
                'work_description' => $work_description,
                'work_from_date' => $work_from_date,
                'work_to_date' => $work_to_date,
                'total_amount' => $total_amount,
                'status' => 'pending',
                'created_by' => $_SESSION['user_id']
            ];
            
            $challan_id = $db->insert('challans', $challan_data);
            
            logAudit('create', 'challans', $challan_id, null, $challan_data);
            $db->commit();
            
            setFlashMessage('success', "Labour pay $challan_no created successfully");
            redirect('modules/masters/labour.php');
            
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to create pay: ' . $e->getMessage());
            redirect('modules/masters/labour.php');
        }
        
    } elseif ($action === 'approve_challan' && $_SESSION['user_role'] === 'admin') {
        $challan_id = intval($_POST['challan_id']);
        
        $update_data = [
            'status' => 'approved',
            'approved_by' => $_SESSION['user_id'],
            'approved_at' => date('Y-m-d H:i:s')
        ];
        
        $db->update('challans', $update_data, 'id = ?', ['id' => $challan_id]);
        logAudit('approve', 'challans', $challan_id);
        
        setFlashMessage('success', 'Pay approved successfully');
        redirect('modules/masters/labour.php');
    }
}

// Fetch challans with filters
$labour_filter = $_GET['labour'] ?? '';
$project_filter = $_GET['project'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "c.challan_type = 'labour'";
$params = [];

if ($labour_filter) {
    $where .= ' AND c.party_id = ?';
    $params[] = $labour_filter;
}

if ($project_filter) {
    $where .= ' AND c.project_id = ?';
    $params[] = $project_filter;
}

if ($status_filter) {
    $where .= ' AND c.status = ?';
    $params[] = $status_filter;
}

$sql = "SELECT c.*, 
               p.name as labour_name,
               pr.project_name,
               u.full_name as created_by_name
        FROM challans c
        JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE $where
        ORDER BY c.created_at DESC";

$stmt = $db->query($sql, $params);
$challans = $stmt->fetchAll();

// Get labour/contractors
$labours = $db->query("SELECT id, name, mobile, contact_person FROM parties WHERE party_type = 'labour' ORDER BY name")->fetchAll();

// Get projects
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS for Modern Design -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
    /* Local Overrides for Labour */
    .icon-labour { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: white; }
    .status-pending { color: #f59e0b; background: #fffbeb; }
    .status-approved { color: #10b981; background: #ecfdf5; }
    .status-paid { color: #3b82f6; background: #eff6ff; }
</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;"> 
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box orange"><i class="fas fa-hard-hat"></i></div>
                        Labour Pay
                    </h3>
                    <div class="chart-subtitle">Manage labour payments and contractors</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="document.getElementById('filterSection').style.display = document.getElementById('filterSection').style.display === 'none' ? 'block' : 'none'" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <a href="labour_create.php" class="modern-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Labour Pay
                    </a>
                </div>
            </div>

            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($labour_filter || $project_filter || $status_filter) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <select name="labour" class="modern-select" style="flex:1;">
                            <option value="">All Labour/Contractors</option>
                            <?php foreach ($labours as $labour): ?>
                                <option value="<?= $labour['id'] ?>" <?= $labour_filter == $labour['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($labour['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="project" class="modern-select" style="flex:1;">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="modern-select" style="flex:1;">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial Paid</option>
                            <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                        
                        <button type="submit" class="modern-btn">Apply</button>
                        <a href="modules/masters/labour.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>PAY NO</th>
                            <th>DATE</th>
                            <th>LABOUR/CONTRACTOR</th>
                            <th>PROJECT</th>
                            <th>WORK PERIOD</th>
                            <th>TOTAL</th>
                            <th>PAID</th>
                            <th>PENDING</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($challans)): ?>
                            <tr><td colspan="10" class="text-center" style="padding:40px; color: #94a3b8;">No labour payments found.</td></tr>
                        <?php else: 
                            foreach ($challans as $challan): 
                                $statusClass = 'orange'; // default pending
                                if($challan['status'] === 'approved') $statusClass = 'blue';
                                if($challan['status'] === 'paid') $statusClass = 'green';
                                
                                $labourInitial = strtoupper(substr($challan['labour_name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <span style="font-weight:700; color:#475569;"><?= htmlspecialchars($challan['challan_no']) ?></span>
                            </td>
                            <td><span style="font-size:13px; color:#64748b;"><?= formatDate($challan['challan_date']) ?></span></td>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <?php $labourColor = ColorHelper::getCustomerColor($challan['party_id']); ?>
                                    <div class="avatar-circle" style="background: <?= $labourColor ?>; color: #fff; width:28px; height:28px; font-size:11px; margin-right:8px;"><?= $labourInitial ?></div>
                                    <span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($challan['labour_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge-pill gray"><?= htmlspecialchars($challan['project_name']) ?></span></td>
                            <td>
                                <div style="font-size:12px; color:#64748b;">
                                    <?= formatDate($challan['work_from_date']) ?> <i class="fas fa-arrow-right" style="font-size:10px; margin:0 2px;"></i> <?= formatDate($challan['work_to_date']) ?>
                                </div>
                            </td>
                            <td><span style="font-weight:700; color:#1e293b;"><?= formatCurrency($challan['total_amount']) ?></span></td>
                            <td><span style="color:#10b981; font-weight:600; font-size:13px;"><?= formatCurrency($challan['paid_amount']) ?></span></td>
                            <td>
                                <?php if ($challan['pending_amount'] > 0): ?>
                                    <span style="color:#f59e0b; font-weight:600; font-size:13px;"><?= formatCurrency($challan['pending_amount']) ?></span>
                                <?php else: ?>
                                    <span class="badge-pill green">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($challan['status']) ?></span></td>
                            <td>
                                <button class="action-btn" onclick="viewChallanDetails(<?= $challan['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                                <?php if ($challan['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                                    <button onclick="openApproveModal(<?= $challan['id'] ?>, '<?= htmlspecialchars($challan['challan_no']) ?>')" class="action-btn" title="Approve" style="color:#10b981;">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
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

<!-- Approval Confirmation Modal -->
<div id="approveModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px; text-align: center; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding: 30px 20px 20px; border-bottom: 1px solid #d1fae5;">
            <div style="width: 60px; height: 60px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <i class="fas fa-check" style="font-size: 24px; color: #059669;"></i>
            </div>
            <h3 style="margin: 0; color: #065f46; font-size: 20px; font-weight: 700;">Confirm Approval</h3>
        </div>
        
        <div style="padding: 30px 25px;">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                Are you sure you want to approve Labour Pay <strong id="approve_challan_no" style="color: #0f172a;"></strong>? 
                <br>This action will verify the work and mark it as approved.
            </p>
            
            <form method="POST" id="approveForm">
                <input type="hidden" name="action" value="approve_challan">
                <input type="hidden" name="challan_id" id="approve_challan_id">
                
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="button" onclick="document.getElementById('approveModal').style.display='none'" 
                            style="padding: 10px 24px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Cancel
                    </button>
                    <button type="submit" 
                            style="padding: 10px 24px; border: none; background: #10b981; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                        Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="challanDetailsModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 700px;">
        <div class="modal-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:20px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#1e293b;">Labour Pay Details</h3>
            <button onclick="document.getElementById('challanDetailsModal').style.display='none'" style="border:none; background:none; font-size:24px; color:#94a3b8; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" id="challan_details_content" style="padding:25px;">
            <div style="text-align:center; padding:20px; color:#94a3b8;">Loading details...</div>
        </div>
    </div>
</div>

<style>
/* Modal Styles copied from material.php */
.custom-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); padding-top: 50px; }
.custom-modal-content { background-color: #fefefe; margin: auto; border: 1px solid #888; width: 90%; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideIn 0.3s ease-out; }
@keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
</style>

<script>
function viewChallanDetails(challanId) {
    document.getElementById('challanDetailsModal').style.display = 'block';
    fetch('<?= BASE_URL ?>modules/challans/get_challan_details.php?id=' + challanId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('challan_details_content').innerHTML = html;
        });
}

function openApproveModal(id, challanNo) {
    document.getElementById('approve_challan_id').value = id;
    document.getElementById('approve_challan_no').innerText = challanNo;
    document.getElementById('approveModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
