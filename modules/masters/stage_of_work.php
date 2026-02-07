<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

// Database Connection
$db = Database::getInstance();
$page_title = 'Stage of Work Templates';
$current_page = 'stage_of_work';

// Handle Actions (Create/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/masters/stage_of_work.php');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create' || $action === 'update') {
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            $stages = $_POST['stages'] ?? [];

            if (empty($name)) throw new Exception("Template name is required");
            if (empty($stages)) throw new Exception("At least one stage is required");

            // Validate Total 100%
            $total = 0;
            foreach ($stages as $s) $total += floatval($s['percentage']);
            if (abs($total - 100) > 0.01) throw new Exception("Total percentage must be exactly 100%. Current: $total%");

            $db->beginTransaction();

            if ($action === 'create') {
                $db->query("INSERT INTO stage_of_work (name, description, total_stages, status) VALUES (?, ?, ?, 'active')", 
                    [$name, $desc, count($stages)]
                );
                $planId = $db->getConnection()->lastInsertId();
                $msg = "Template created successfully";
            } else {
                $planId = intval($_POST['id']);
                $db->query("UPDATE stage_of_work SET name = ?, description = ?, total_stages = ? WHERE id = ?", 
                    [$name, $desc, count($stages), $planId]
                );
                // Clear existing items to re-insert
                $db->query("DELETE FROM stage_of_work_items WHERE stage_of_work_id = ?", [$planId]);
                $msg = "Template updated successfully";
            }

            // Insert Stages
            $order = 1;
            foreach ($stages as $stage) {
                $db->query("INSERT INTO stage_of_work_items (stage_of_work_id, stage_name, percentage, stage_order, stage_type) VALUES (?, ?, ?, ?, ?)", [
                    $planId,
                    trim($stage['name']),
                    floatval($stage['percentage']),
                    $order++,
                    $stage['type']
                ]);
            }

            $db->commit();
            setFlashMessage('success', $msg);
            
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            $db->delete('stage_of_work', 'id = ?', [$id]);
            setFlashMessage('success', 'Template deleted successfully');
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/masters/stage_of_work.php');
}

// Fetch Plans
$plans = $db->query("SELECT * FROM stage_of_work ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS for standard styles -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Replicating Styles from Projects.php */

.chart-card-custom {
    background: #fff;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 100%;
    border: 1px solid #f1f5f9;
}

.chart-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.chart-title-group h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.chart-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: #f1f5f9;
    color: #64748b;
}

.chart-icon-box.purple {
    background: #f3e8ff;
    color: #9333ea;
}

.chart-subtitle {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 4px;
    font-weight: 500;
    padding-left: 42px; 
}

/* Modern Button */
.modern-btn {
    background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modern-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }

/* Table Styles */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
}
.modern-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    padding: 12px 15px;
    letter-spacing: 0.5px;
    border: none;
    text-align: left;
}
.modern-table tbody tr { background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: transform 0.2s; }
.modern-table tbody tr:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
.modern-table td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.modern-table td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
.modern-table td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

/* Badge Pills */
.badge-pill {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-pill.blue { background: #eff6ff; color: #3b82f6; }
.badge-pill.gray { background: #f1f5f9; color: #64748b; }

/* Action Buttons */
.action-btn { color: #94a3b8; cursor: pointer; transition: 0.2s; margin: 0 4px; border:none; background:transparent;}
.action-btn:hover { color: #64748b; }
.action-btn.view:hover { color: #3b82f6; }
.action-btn.edit:hover { color: #10b981; }
.action-btn.delete:hover { color: #ef4444; }

/* Modal Styles */
.custom-modal {
    display: none; 
    position: fixed; 
    z-index: 10000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: hidden;
    background-color: rgba(15, 23, 42, 0.6); 
    backdrop-filter: blur(8px);
    align-items: center; /* Vertical Center */
    justify-content: center; /* Horizontal Center */
}

.custom-modal-content {
    background-color: #ffffff;
    margin: 0; /* Centered by flex parent */
    border: none;
    width: 90%; 
    max-width: 700px;
    border-radius: 20px;
    position: relative;
    animation: modalSlideUp 0.3s ease-out;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    max-height: 85vh; /* Limit height */
}

@keyframes modalSlideUp {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-header-premium {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}
.modal-header-premium h3 { margin: 0; font-size: 20px; font-weight: 800; display:flex; align-items:center; gap:10px; }
.modal-header-premium p { margin: 5px 0 0 0; font-size: 13px; opacity: 0.8; }

.modal-close-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; cursor: pointer; transition: 0.2s;
}
.modal-close-btn:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }

.modal-body-premium { padding: 32px; max-height: 70vh; overflow-y: auto; }

/* Form Elements */
.modern-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    color: #1e293b;
    outline: none;
    background: #f8fafc;
    transition: 0.2s;
}
.modern-input:focus { border-color: #a855f7; background: #fff; }
.input-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
.input-group-modern { margin-bottom: 20px; }

/* Stage Builder Styles */
.stages-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px;
    margin-top: 10px;
}
/* Stage Row Refined Alignment */
.stage-row {
    display: flex; /* Switch to Flex for better vertical alignment */
    gap: 12px;
    align-items: flex-start; /* Align triggers to top */
    background: white;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
}
.stage-row:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

.drag-handle { 
    color: #cbd5e1; 
    cursor: grab; 
    display: flex;
    align-items: center; /* Center icon in box */
    justify-content: center;
    width: 32px;
    height: 42px; /* Match input height roughly */
}
.drag-handle:hover { color: #64748b; }

.remove-btn { 
    color: #ef4444; 
    cursor: pointer; 
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 42px; /* Match input height roughly */
    transition: 0.2s;
}
.remove-btn:hover { background: #fee2e2; border-radius: 6px; }

/* Input Wrappers */
.stage-name-col { flex: 3; }
.stage-pct-col { flex: 1; max-width: 120px; }


.btn-add-stage {
    width: 100%;
    padding: 12px;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    background: white;
    color: #64748b;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
}
.btn-add-stage:hover { border-color: #a855f7; color: #a855f7; background: #faf5ff; }

.total-bar {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 15px; padding: 15px; background: #f1f5f9; border-radius: 10px; font-weight: 700;
}
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
.status-valid { background: #dcfce7; color: #166534; }
.status-invalid { background: #fee2e2; color: #991b1b; }

.modal-footer-premium {
    padding: 24px 32px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex; justify-content: flex-end; gap: 12px;
}

.btn-save {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    color: white; border: none; padding: 12px 28px; border-radius: 10px;
    font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}
.btn-ghost {
    background: transparent; color: #64748b; border: 2px solid #e2e8f0;
    padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer;
    transition: all 0.2s;
}
.btn-ghost:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom">
            
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box purple"><i class="fas fa-layer-group"></i></div>
                        Stage of Work Templates
                    </h3>
                    <div class="chart-subtitle">Define reusable payment structures for your projects</div>
                </div>
                
                <button class="modern-btn" onclick="openModal('createModal')">
                    <i class="fas fa-plus"></i> Create Template
                </button>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Description</th>
                            <th>Total Stages</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($plans)): ?>
                            <tr>
                                <td colspan="5" class="text-center" style="padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-clipboard-list" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                    No templates found. Create one to get started.
                                </td>
                            </tr>
                        <?php else: foreach($plans as $plan): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($plan['name']) ?></div>
                                </td>
                                <td>
                                    <span style="color: #64748b;"><?= htmlspecialchars(substr($plan['description'], 0, 50)) . (strlen($plan['description']) > 50 ? '...' : '') ?></span>
                                </td>
                                <td>
                                    <span class="badge-pill blue"><?= $plan['total_stages'] ?> Stages</span>
                                </td>
                                <td>
                                    <span style="color: #64748b; font-family: monospace;"><?= formatDate($plan['updated_at'] ?: $plan['created_at']) ?></span>
                                </td>
                                <td class="text-right">
                                    <button class="action-btn view" onclick="openViewModal(<?= $plan['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" onclick="openEditModal(<?= $plan['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="openDeleteModal(<?= $plan['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
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

<!-- CREATE MODAL -->
<div id="createModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-magic"></i> Create New Template</h3>
                <p>Configure stages and percentages</p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" id="createForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body-premium">
                <div class="input-group-modern">
                    <label class="input-label">Template Name</label>
                    <input type="text" name="name" class="modern-input" required placeholder="e.g. Standard Construction Plan">
                </div>
                <div class="input-group-modern">
                    <label class="input-label">Description</label>
                    <input type="text" name="description" class="modern-input" placeholder="Brief details...">
                </div>

                <div class="input-label">Stages Breakdown</div>
                <div class="stages-wrapper" id="createStagesWrapper">
                    <!-- Stages injected here -->
                </div>
                <button type="button" class="btn-add-stage" onclick="addStage('createStagesWrapper')">
                    <i class="fas fa-plus margin-right-5"></i> Add Stage
                </button>

                <div class="total-bar">
                    <span>Total Progress</span>
                    <div>
                        <span id="createTotalDisplay" style="margin-right: 10px;">0%</span>
                        <span id="createStatusBadge" class="status-badge status-invalid">Invalid</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-save" id="createSubmitBtn" disabled>Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Template</h3>
                <p>Modify stages and percentages</p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body-premium">
                <div class="input-group-modern">
                    <label class="input-label">Template Name</label>
                    <input type="text" name="name" id="editName" class="modern-input" required>
                </div>
                <div class="input-group-modern">
                    <label class="input-label">Description</label>
                    <input type="text" name="description" id="editDesc" class="modern-input">
                </div>

                <div class="input-label">Stages Breakdown</div>
                <div class="stages-wrapper" id="editStagesWrapper">
                    <!-- Stages injected here -->
                </div>
                <button type="button" class="btn-add-stage" onclick="addStage('editStagesWrapper')">
                    <i class="fas fa-plus margin-right-5"></i> Add Stage
                </button>

                <div class="total-bar">
                    <span>Total Progress</span>
                    <div>
                        <span id="editTotalDisplay" style="margin-right: 10px;">0%</span>
                        <span id="editStatusBadge" class="status-badge status-invalid">Invalid</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-save" id="editSubmitBtn">Update Template</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div id="viewModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-eye"></i> Template Details</h3>
            </div>
            <button class="modal-close-btn" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body-premium" id="viewModalContent">
            Loading...
        </div>
        <div class="modal-footer-premium">
            <button type="button" class="btn-ghost" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div id="deleteModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-body-premium">
            <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-trash-alt" style="font-size: 24px; color: #ef4444;"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 10px;">Delete Template?</h3>
            <p style="color: #64748b; margin-bottom: 20px;">Are you sure you want to delete this template? This cannot be undone.</p>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn-save" style="background: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
// Shared Logic
function addStage(containerId, name='', pct='', type='construction_linked') {
    const wrapper = document.getElementById(containerId);
    const div = document.createElement('div');
    const uniqueId = Date.now() + Math.floor(Math.random() * 1000);
    div.className = 'stage-row';
    div.innerHTML = `
        <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>
        <div class="stage-name-col">
            <input type="text" name="stages[${uniqueId}][name]" class="modern-input" value="${name}" placeholder="Stage Name" required>
            <input type="hidden" name="stages[${uniqueId}][type]" value="${type}">
             <div style="font-size:11px; color:#94a3b8; margin-top:4px; font-weight:500;">
                ${type === 'booking' ? '<i class="far fa-clock"></i> Time Based' : '<i class="fas fa-hammer"></i> Construction Linked'}
             </div>
        </div>
        <div class="stage-pct-col">
            <input type="number" name="stages[${uniqueId}][percentage]" class="modern-input pct-input" value="${pct}" placeholder="%" step="0.01" oninput="calcTotal('${containerId}')" required style="text-align:center;">
        </div>
        <div class="remove-btn" onclick="this.parentElement.remove(); calcTotal('${containerId}');" title="Remove Stage">
            <i class="fas fa-times"></i>
        </div>
    `;
    wrapper.appendChild(div);
    calcTotal(containerId);
}

function calcTotal(containerId) {
    const isCreate = containerId === 'createStagesWrapper';
    const totalDisplay = document.getElementById(isCreate ? 'createTotalDisplay' : 'editTotalDisplay');
    const badge = document.getElementById(isCreate ? 'createStatusBadge' : 'editStatusBadge');
    const btn = document.getElementById(isCreate ? 'createSubmitBtn' : 'editSubmitBtn');
    
    let total = 0;
    document.querySelectorAll(`#${containerId} .pct-input`).forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });

    totalDisplay.textContent = total.toFixed(2) + '%';
    
    if (Math.abs(total - 100) < 0.01) {
        badge.className = 'status-badge status-valid';
        badge.textContent = 'Valid';
        btn.disabled = false;
        btn.style.opacity = '1';
    } else {
        badge.className = 'status-badge status-invalid';
        badge.textContent = 'Invalid';
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }
}

// Modal Functions
function openModal(id) {
    document.getElementById(id).style.display = 'flex'; /* Flex for centering */
    if(id === 'createModal') {
        const wrapper = document.getElementById('createStagesWrapper');
        if(wrapper.children.length === 0) {
            addStage('createStagesWrapper', 'Booking Token', 10, 'booking');
            addStage('createStagesWrapper', 'Registration', 20, 'booking');
            addStage('createStagesWrapper', 'Plinth Completion', 15, 'construction_linked');
        }
    }
    initSortable('createStagesWrapper');
    initSortable('editStagesWrapper');
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function initSortable(id) {
    new Sortable(document.getElementById(id), {
        animation: 150,
        handle: '.drag-handle'
    });
}

function openDeleteModal(id) {
    document.getElementById('deleteId').value = id;
    openModal('deleteModal');
}

function openEditModal(id) {
    openModal('editModal');
    
    // Fetch Data
    fetch(`get_stage_details.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                document.getElementById('editId').value = data.plan.id;
                document.getElementById('editName').value = data.plan.name;
                document.getElementById('editDesc').value = data.plan.description;
                
                const wrapper = document.getElementById('editStagesWrapper');
                wrapper.innerHTML = '';
                data.items.forEach(item => {
                    addStage('editStagesWrapper', item.stage_name, item.percentage, item.stage_type);
                });
            }
        });
}

function openViewModal(id) {
    openModal('viewModal');
    const content = document.getElementById('viewModalContent');
    content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch(`get_stage_details.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                let html = `
                    <h4 style="margin:0 0 5px 0; color:#1e293b;">${data.plan.name}</h4>
                    <p style="margin:0 0 20px 0; font-size:13px; color:#64748b;">${data.plan.description}</p>
                    <table class="modern-table">
                        <thead><tr><th>Stage</th><th class="text-right">%</th></tr></thead>
                        <tbody>
                `;
                data.items.forEach(item => {
                    html += `<tr><td>${item.stage_name}</td><td class="text-right font-bold">${parseFloat(item.percentage)}%</td></tr>`;
                });
                html += `</tbody></table>`;
                content.innerHTML = html;
            }
        });
}

// Close on click outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
