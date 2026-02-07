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
$page_title = 'Delivery Challans';
$current_page = 'material_challan';

// Handle operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/challans/material.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_challan' && $_SESSION['user_role'] === 'admin') {
        $challan_id = intval($_POST['challan_id']);
        
        $challan = $db->query("SELECT * FROM challans WHERE id = ?", [$challan_id])->fetch();
        if ($challan && $challan['status'] === 'pending') {
            try {
                $db->beginTransaction();
                
                // 1. Handle Vendor Creation if deferred
                if (empty($challan['party_id']) && !empty($challan['temp_vendor_data'])) {
                    $tv = json_decode($challan['temp_vendor_data'], true);
                    
                    // Double check overlap to avoid duplicates if same name created elsewhere
                    $existing = $db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='vendor'", [trim($tv['name'])])->fetch();
                    if ($existing) {
                        $party_id = $existing['id'];
                    } else {
                        $vendor_data = [
                            'party_type' => 'vendor',
                            'name' => $tv['name'],
                            'mobile' => $tv['mobile'],
                            'address' => $tv['address'],
                            'gst_number' => $tv['gst_number'],
                            'gst_status' => !empty($tv['gst_number']) ? 'registered' : 'unregistered',
                            'email' => $tv['email'],
                            'status' => 'active' // Active immediately upon approval
                        ];
                        $party_id = $db->insert('parties', $vendor_data);
                    }
                    
                    // Link to challan
                    $db->update('challans', ['party_id' => $party_id, 'temp_vendor_data' => null], 'id=?', ['id' => $challan_id]);
                }

                // 2. Update Status
                $update_data = [
                    'status' => 'approved',
                    'approved_by' => $_SESSION['user_id'],
                    'approved_at' => date('Y-m-d H:i:s')
                ];
                $db->update('challans', $update_data, 'id = ?', ['id' => $challan_id]);
                
                // 3. Update Stock
                $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$challan_id])->fetchAll();
                foreach ($items as $item) {
                    updateMaterialStock($item['material_id'], $item['quantity'], true);
                }

                // 4. Activate Vendor if it was pending (legacy case)
                if (!empty($challan['party_id'])) {
                     $db->update('parties', ['status' => 'active'], "id = ? AND status = 'pending'", ['id' => $challan['party_id']]);
                }
                
                logAudit('approve', 'challans', $challan_id);
                $db->commit();
                setFlashMessage('success', 'Challan approved successfully');

            } catch (Exception $e) {
                $db->rollback();
                setFlashMessage('error', 'Approval failed: ' . $e->getMessage());
            }
        } else {
            setFlashMessage('warning', 'Challan already approved or invalid.');
        }
        redirect('modules/challans/material.php');
    }

    if ($action === 'delete_challan' && $_SESSION['user_role'] === 'admin') {
        try {
            $challan_id = intval($_POST['challan_id']);
            $db->beginTransaction();

            // 1. Revert Stock (Subtract what was added)
            $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$challan_id])->fetchAll();
            foreach ($items as $item) {
                // updateMaterialStock($id, $qty, $add=true/false). We added when created. So now we subtract.
                updateMaterialStock($item['material_id'], $item['quantity'], false);
            }

            // 2. Delete Items
            $db->delete('challan_items', 'challan_id = ?', [$challan_id]);

            // 3. Delete Challan
            $db->delete('challans', 'id = ?', [$challan_id]);

            logAudit('delete', 'challans', $challan_id);
            $db->commit();
            setFlashMessage('success', 'Challan deleted successfully');

        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Error deleting challan: ' . $e->getMessage());
        }
        redirect('modules/challans/material.php');
    }

    if ($action === 'bulk_delete_challans' && $_SESSION['user_role'] === 'admin') {
        try {
            $ids = json_decode($_POST['ids'], true);
            if (empty($ids)) {
                throw new Exception("No challans selected");
            }

            $db->beginTransaction();
            $count = 0;

            $deleted_ids = [];

            foreach ($ids as $id) {
                // Verify status is pending before deleting
                $challan = $db->query("SELECT status FROM challans WHERE id = ?", [$id])->fetch();
                if ($challan && $challan['status'] === 'pending') {
                    // 1. Revert Stock
                    $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$id])->fetchAll();
                    foreach ($items as $item) {
                        updateMaterialStock($item['material_id'], $item['quantity'], false);
                    }
                    
                    // 2. Delete Items & Challan
                    $db->delete('challan_items', 'challan_id = ?', [$id]);
                    $db->delete('challans', 'id = ?', [$id]);
                    
                    $deleted_ids[] = $id;
                    $count++;
                }
            }
            
            if (!empty($deleted_ids)) {
                logAudit('bulk_delete', 'challans', 0, null, ['deleted_ids' => $deleted_ids]);
            }

            $db->commit();
            setFlashMessage('success', "$count challans deleted successfully");

        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Error deleting challans: ' . $e->getMessage());
        }
        redirect('modules/challans/material.php');
    }
}

// Fetch challans with filters
$vendor_filter = $_GET['vendor'] ?? '';
$project_filter = $_GET['project'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "c.challan_type = 'material'";
$params = [];

if ($vendor_filter) {
    $where .= ' AND c.party_id = ?';
    $params[] = $vendor_filter;
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
               p.name as vendor_name,
               pr.project_name,
               u.full_name as created_by_name,
               (SELECT GROUP_CONCAT(DISTINCT m.material_name SEPARATOR ', ') 
                FROM challan_items ci 
                JOIN materials m ON ci.material_id = m.id 
                WHERE ci.challan_id = c.id) as material_names,
               (SELECT COALESCE(SUM(quantity), 0) FROM challan_items ci WHERE ci.challan_id = c.id) as total_quantity,
               p.address as vendor_address
        FROM challans c
        LEFT JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE $where
        ORDER BY c.created_at DESC";

$stmt = $db->query($sql, $params);
$challans = $stmt->fetchAll();

// Get vendors for filter
$vendors = $db->query("SELECT id, name FROM parties WHERE party_type = 'vendor' ORDER BY name")->fetchAll();
// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS for Modern Design -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
    /* Local Overrides for Challans */
    .icon-challan { background: linear-gradient(135deg, #a855f7 0%, #d8b4fe 100%); color: white; }
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
                        <div class="chart-icon-box purple"><i class="fas fa-file-invoice"></i></div>
                        Delivery Challans
                    </h3>
                    <div class="chart-subtitle">Track material procurement and payments</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <form id="bulkDeleteChallanForm" method="POST" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="bulk_delete_challans">
                        <input type="hidden" name="ids" id="bulkDeleteChallanIds">
                    </form>
                    <button id="bulkDeleteChallanBtn" onclick="confirmBulkDeleteChallans()" class="modern-btn" style="background:#fee2e2; color:#ef4444; display:none;">
                        <i class="fas fa-trash-alt"></i> Bulk Delete (<span id="selectedChallanCount">0</span>)
                    </button>
                    <button onclick="document.getElementById('filterSection').style.display = document.getElementById('filterSection').style.display === 'none' ? 'block' : 'none'" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <a href="create.php" class="modern-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Challan
                    </a>
                </div>
            </div>

            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($vendor_filter || $project_filter || $status_filter) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <select name="vendor" class="modern-select" style="flex:1;">
                            <option value="">All Vendors</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['name']) ?>
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
                        <a href="material.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><input type="checkbox" id="selectAllChallans" class="premium-checkbox" onclick="toggleAllChallans(this)"></th>
                            <th>CHALLAN NO</th>
                            <th>DATE</th>
                            <th>VENDOR</th>
                            <th>MATERIALS</th>
                            <th>PROJECT</th>
                            <th>QUANTITY</th>
                            <th>ADDRESS</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($challans)): ?>
                            <tr><td colspan="9" class="text-center" style="padding:40px; color: #94a3b8;">No challans found.</td></tr>
                        <?php else: 
                            foreach ($challans as $challan): 
                                $statusClass = 'orange'; // default pending
                                if($challan['status'] === 'approved') $statusClass = 'blue';
                                if($challan['status'] === 'paid') $statusClass = 'green';
                                
                                $vendorName = $challan['vendor_name'];
                                if (empty($vendorName) && !empty($challan['temp_vendor_data'])) {
                                    $tv = json_decode($challan['temp_vendor_data'], true);
                                    $vendorName = $tv['name'] . ' (Draft)';
                                }
                                
                                $vendorInitial = strtoupper(substr($vendorName ?? '?', 0, 1));
                        ?>
                        <tr>
                            <td>
                                <?php if ($challan['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                                    <input type="checkbox" class="premium-checkbox challan-checkbox" value="<?= $challan['id'] ?>" onclick="updateBulkChallanState()">
                                <?php else: ?>
                                    <i class="fas fa-lock" style="color: #cbd5e1; font-size: 14px; margin-left: 2px;" title="Cannot delete"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight:700; color:#475569;"><?= htmlspecialchars($challan['challan_no']) ?></span>
                            </td>
                            <td><span style="font-size:13px; color:#64748b;"><?= formatDate($challan['challan_date']) ?></span></td>
                            <td>
                                <div style="display:flex; align-items:left; justify-content:left;">
                                    <?php 
                                        $vendorColor = ColorHelper::getCustomerColor($vendorName);
                                    ?>
                                    <div class="avatar-circle" style="background: <?= $vendorColor ?>; color: #fff; width:24px; height:24px; font-size:10px; margin-right:8px;"><?= $vendorInitial ?></div>
                                    <span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($vendorName) ?></span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:12px; color:#64748b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($challan['material_names']) ?>">
                                    <?= htmlspecialchars($challan['material_names']) ?: '—' ?>
                                </span>
                            </td>
                            <td>
                                <?php $projColor = ColorHelper::getProjectColor($challan['project_id']); ?>
                                <span class="badge-pill" style="background: <?= $projColor ?>; color: #fff;"><?= htmlspecialchars($challan['project_name']) ?></span>
                            </td>
                            <td><span style="font-weight:700; color:#1e293b;"><?= number_format($challan['total_quantity'], 2) ?></span></td>
                            <td><span style="font-size:13px; color:#64748b;"><?= htmlspecialchars($challan['vendor_address'] ?: 'N/A') ?></span></td>
                            <td><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($challan['status']) ?></span></td>
                            <td>
                                <button class="action-btn" onclick="viewChallanDetails(<?= $challan['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                                <a href="edit.php?id=<?= $challan['id'] ?>" class="action-btn" title="Edit" style="color:#3b82f6;"><i class="fas fa-edit"></i></a>
                                <?php if ($challan['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                                    <button onclick="openApproveModal(<?= $challan['id'] ?>, '<?= htmlspecialchars($challan['challan_no']) ?>')" class="action-btn" title="Approve" style="color:#10b981;">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button onclick="openDeleteModal(<?= $challan['id'] ?>, '<?= htmlspecialchars($challan['challan_no']) ?>')" class="action-btn" title="Delete" style="color:#ef4444;">
                                        <i class="fas fa-trash-alt"></i>
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



<!-- Challan Details Modal -->
<div id="challanDetailsModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 700px;">
        <div class="modal-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:20px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#1e293b;">Challan Details</h3>
            <button onclick="document.getElementById('challanDetailsModal').style.display='none'" style="border:none; background:none; font-size:24px; color:#94a3b8; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" id="challan_details_content" style="padding:25px;">
            <div style="text-align:center; padding:20px; color:#94a3b8;">Loading details...</div>
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
                Are you sure you want to approve Challan <strong id="approve_challan_no" style="color: #0f172a;"></strong>? 
                <br>This action will verify the challan and mark it as approved.
            </p>
            
            <form method="POST" id="approveForm">
                <?= csrf_field() ?>
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

<style>
/* Modal Styles from Projects/Booking */
.custom-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); padding-top: 50px; }
.custom-modal-content { background-color: #fefefe; margin: auto; border: 1px solid #888; width: 90%; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideIn 0.3s ease-out; }
@keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
</style>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px; text-align: center; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); padding: 30px 20px 20px; border-bottom: 1px solid #fee2e2;">
            <div style="width: 60px; height: 60px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <i class="fas fa-trash-alt" style="font-size: 24px; color: #ef4444;"></i>
            </div>
            <h3 style="margin: 0; color: #991b1b; font-size: 20px; font-weight: 700;">Delete Challan?</h3>
        </div>
        
        <div style="padding: 30px 25px;">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                Are you sure you want to delete Challan <strong id="delete_challan_no" style="color: #0f172a;"></strong>? 
                <br>This will remove the record and <strong>revert (subtract)</strong> the added stock.
            </p>
            
            <form method="POST" id="deleteForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_challan">
                <input type="hidden" name="challan_id" id="delete_challan_id">
                
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" 
                            style="padding: 10px 24px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Cancel
                    </button>
                    <button type="submit" 
                            style="padding: 10px 24px; border: none; background: #ef4444; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2); transition: all 0.2s;">
                        Confirm Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewChallanDetails(challanId) {
    document.getElementById('challanDetailsModal').style.display = 'block';
    fetch('<?= BASE_URL ?>modules/challans/get_challan_details.php?id=' + challanId + '&t=' + new Date().getTime())
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

function openDeleteModal(id, challanNo) {
    document.getElementById('delete_challan_id').value = id;
    document.getElementById('delete_challan_no').innerText = challanNo;
    document.getElementById('deleteModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
    }
}

// Bulk Delete Logic
function toggleAllChallans(source) {
    const checkboxes = document.querySelectorAll('.challan-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkChallanState();
}

function updateBulkChallanState() {
    const checkboxes = document.querySelectorAll('.challan-checkbox:checked');
    const bar = document.getElementById('bulkDeleteChallanBtn');
    const count = document.getElementById('selectedChallanCount');
    
    if (checkboxes.length > 0) {
        bar.style.display = 'inline-flex';
        count.textContent = checkboxes.length;
    } else {
        bar.style.display = 'none';
        document.getElementById('selectAllChallans').checked = false;
    }
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.challan-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAllChallans').checked = false;
    updateBulkChallanState();
}

function confirmBulkDeleteChallans() {
    const checkboxes = document.querySelectorAll('.challan-checkbox:checked');
    if (checkboxes.length === 0) return;

    Swal.fire({
        title: `Delete ${checkboxes.length} Challans?`,
        html: "This will <strong>PERMANENTLY</strong> delete the records.<br>Stock will be <strong>SUBTRACTED</strong> (Reverted).",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#fff',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'premium-swal-popup',
            title: 'premium-swal-title',
            content: 'premium-swal-content',
            confirmButton: 'premium-swal-confirm',
            cancelButton: 'premium-swal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const ids = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('bulkDeleteChallanIds').value = JSON.stringify(ids);
            document.getElementById('bulkDeleteChallanForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
