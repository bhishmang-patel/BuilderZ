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
$page_title = 'Project Progress';
$current_page = 'project_progress';

// Fetch all projects for the dropdown
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

$selected_project_id = $_GET['project_id'] ?? null;
$milestones = [];

// Fetch milestones if a project is selected
if ($selected_project_id) {
    // 1. Fetch Project Info (to get default plan)
    $project = $db->select('projects', 'id = ?', [$selected_project_id])->fetch();
    
    // 2. Determine Source of Truth for Stages
    if (!empty($project['default_stage_of_work_id'])) {
        // Use Master Template assigned to Project
        $sql = "SELECT swi.stage_name, swi.percentage, swi.stage_order, sw.name as plan_name
                FROM stage_of_work_items swi
                JOIN stage_of_work sw ON swi.stage_of_work_id = sw.id
                WHERE sw.id = ?
                ORDER BY swi.stage_order ASC";
        $milestones = $db->query($sql, [$project['default_stage_of_work_id']])->fetchAll();
    } else {
        // FALLBACK: Infer from active bookings (Legacy behavior)
        $sql = "SELECT DISTINCT swi.stage_name, swi.percentage, swi.stage_order, sw.name as plan_name
                FROM bookings b
                JOIN stage_of_work sw ON b.stage_of_work_id = sw.id
                JOIN stage_of_work_items swi ON sw.id = swi.stage_of_work_id
                WHERE b.project_id = ? AND b.status = 'active'
                GROUP BY swi.stage_name
                ORDER BY swi.stage_order ASC";
        $milestones = $db->query($sql, [$selected_project_id])->fetchAll();
    }

    // 3. Fetch Completed Stages (Independent Table + Legacy Demands check)
    // We check both the new 'project_completed_stages' table AND existing demands for backward compatibility
    $completed_sql = "SELECT stage_name FROM project_completed_stages WHERE project_id = ?
                      UNION
                      SELECT DISTINCT bd.stage_name 
                      FROM booking_demands bd 
                      JOIN bookings b ON bd.booking_id = b.id 
                      WHERE b.project_id = ?";
    $completed_stages = $db->query($completed_sql, [$selected_project_id, $selected_project_id])->fetchAll(PDO::FETCH_COLUMN);
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Reusing Project/Booking Styles */
.progress-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    overflow: hidden;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}

.progress-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.milestone-row {
    display: flex;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
}

.milestone-row:last-child {
    border-bottom: none;
}

.milestone-row:hover {
    background: #f8fafc;
}

.milestone-info {
    flex: 1;
}

.milestone-name {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.milestone-meta {
    font-size: 13px;
    color: #64748b;
}

.status-badge-outline {
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    background: white;
}

.status-badge-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.btn-mark-complete {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-mark-complete:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
}

.btn-disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
    box-shadow: none;
}
/* CSS for Modal Overlay */
.custom-modal {
    display: none; 
    position: fixed; 
    z-index: 99999; /* High z-index to cover sidebar */
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(15, 23, 42, 0.75); /* Darker backdrop */
    backdrop-filter: blur(8px);
    align-items: center; /* Center vertically */
    justify-content: center; /* Center horizontally */
    display: none; /* Keep hidden by default, JS toggles flex */
}

/* Important: JS sets this to 'flex' so we need !important or strict rule if JS uses 'block' by default for modals */
.custom-modal[style*="display: block"] {
    display: flex !important;
}

.custom-modal-content {
    background-color: #ffffff;
    margin: auto; /* Center in flex container */
    border: none;
    width: 90%; 
    max-width: 500px;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    position: relative;
    animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

@keyframes modalPop {
    from { transform: scale(0.95) translateY(20px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

/* Header & Body styling reused from Project Styles */
.modal-header-premium {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-title-group h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title-group p {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.7);
}

.modal-close-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}
.modal-close-btn:hover { background: rgba(255, 255, 255, 0.25); }

.modal-body-premium {
    padding: 30px;
    background: #ffffff;
}
</style>

<div class="booking-details-container"> <!-- Reuse container for padding -->
    <div class="row">
        <div class="col-12">
            <div class="info-card-modern" style="margin-bottom: 30px;">
                <div class="card-body-modern" style="padding: 24px;">
                    <form method="GET" style="display: flex; align-items: flex-end; gap: 20px;">
                        <div style="flex: 1; max-width: 400px;">
                            <label class="input-label" style="margin-bottom: 8px; display: block;">Select Project to Manage Progress</label>
                            <select name="project_id" class="modern-select" onchange="this.form.submit()">
                                <option value="">-- Choose Project --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $selected_project_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_project_id): ?>
        <?php if (empty($milestones)): ?>
            <div class="empty-state">
                <i class="fas fa-search" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                <h3 style="color: #64748b;">No Linked Payment Plans Found</h3>
                <p style="color: #94a3b8;">This project doesn't have any bookings with construction-linked payment plans yet.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="progress-card">
                        <div class="progress-header">
                            <div>
                                <h3 style="margin: 0; font-size: 18px; font-weight: 700;">Construction Milestones</h3>
                                <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.8;">Mark stages as complete to trigger demand generation</p>
                            </div>
                            <div class="status-badge-outline" style="color: white; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.1);">
                                <?= count($milestones) ?> Stages Identified
                            </div>
                        </div>
                        
                        <div class="milestones-list">
                            <?php foreach ($milestones as $stage): ?>
                                <div class="milestone-row">
                                    <div class="milestone-icon" style="width: 48px; height: 48px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; color: #64748b;">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-name">
                                            <?= htmlspecialchars($stage['stage_name']) ?>
                                        </div>
                                        <div class="milestone-meta">
                                            Usually linked to <strong><?= floatval($stage['percentage']) ?>%</strong> demand
                                        </div>
                                    </div>
                                    <div class="milestone-actions">
                                        <?php if (in_array($stage['stage_name'], $completed_stages ?? [])): ?>
                                            <button class="modern-btn btn-ghost" style="color: #10b981; border-color: #10b981; cursor: default; background: #ecfdf5;">
                                                <i class="fas fa-check-double"></i> Completed
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-mark-complete" onclick="confirmMilestone('<?= htmlspecialchars($stage['stage_name']) ?>')">
                                                <i class="fas fa-check-circle"></i> Mark Complete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Trigger Demand Modal -->
<div id="triggerModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-bell"></i> Trigger Demands</h3>
                <p>Generating payment demands for customers</p>
            </div>
            <button class="modal-close-btn" onclick="document.getElementById('triggerModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body-premium" style="text-align: center;">
            <div style="width: 72px; height: 72px; background: #e0f2fe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto;">
                <i class="fas fa-paper-plane" style="font-size: 32px; color: #0284c7;"></i>
            </div>
            <h3 style="margin-bottom: 12px; font-weight: 800; color: #0f172a;">Confirm "<span id="modal_stage_name"></span>"?</h3>
            <p style="color: #64748b; margin-bottom: 30px;">
                This will automatically generate payment demands for all eligible customers who are pending this stage.
            </p>
            <form method="POST" action="trigger_demand.php">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                <input type="hidden" name="stage_name" id="input_stage_name">
                <button type="submit" class="modern-btn" style="width: 100%; justify-content: center; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                    Yes, Generate Demands
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmMilestone(stageName) {
    document.getElementById('modal_stage_name').textContent = stageName;
    document.getElementById('input_stage_name').value = stageName;
    document.getElementById('triggerModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
