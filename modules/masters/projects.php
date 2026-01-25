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
checkPermission(['admin', 'project_manager']);

$masterService = new MasterService();
$page_title = 'Projects';
$current_page = 'projects';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $masterService->createProject($_POST, $_SESSION['user_id']);
            setFlashMessage('success', 'Project created successfully');
        
        } elseif ($action === 'update') {
            $masterService->updateProject(intval($_POST['id']), $_POST);
            setFlashMessage('success', 'Project updated successfully');
        
        } elseif ($action === 'delete') {
            $masterService->deleteProject(intval($_POST['id']));
            setFlashMessage('success', 'Project deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/masters/projects.php');
}

// Fetch all projects
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? ''
];
$projects = $masterService->getAllProjects($filters);

include __DIR__ . '/../../includes/header.php';
?>

<!-- Modern Dashboard Style -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Local overrides for Projects page to match Dashboard */
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
/* Premium Modal Styles */
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
    margin: 4% auto; /* Centered with top margin */
    border: none;
    width: 90%; 
    max-width: 650px; /* Slightly wider */
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden; /* For header radius */
}

@keyframes modalSlideUp {
    from { transform: translateY(30px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.modal-header-premium {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); /* Purple Gradient */
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

/* Modern Inputs with Purple Focus */
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
    border-color: #a855f7; /* Purple */
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(168, 85, 247, 0.1);
}

.modern-input::placeholder { color: #cbd5e1; }

.modal-footer-premium {
    padding: 24px 32px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Button Variants */
.btn-save {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(124, 58, 237, 0.4); }

.modern-btn {
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
    display: flex;
    align-items: center;
    gap: 8px;
}
.modern-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }

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
.three-cols { grid-template-columns: 1fr 1fr 1fr; }

/* View Modal Specifics */
.view-stat-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}
.view-stat-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
.view-stat-val { font-size: 20px; font-weight: 800; color: #1e293b; }

/* Table Styles from Dashboard */
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

/* Avatars & Badges */
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
.badge-pill.blue { background: #eff6ff; color: #3b82f6; }
.badge-pill.green { background: #ecfdf5; color: #10b981; }
.badge-pill.purple { background: #f3e8ff; color: #9333ea; }
.badge-pill.orange { background: #fff7ed; color: #c2410c; }
.badge-pill.gray { background: #f1f5f9; color: #64748b; }

.status-active { background: #ecfdf5; color: #10b981; }
.status-completed { background: #eff6ff; color: #3b82f6; }
.status-on_hold { background: #fff7ed; color: #f59e0b; }

.action-btn { color: #94a3b8; cursor: pointer; transition: 0.2s; margin: 0 4px; border:none; background:transparent;}
.action-btn:hover { color: #64748b; }
.action-btn.delete:hover { color: #ef4444; }

/* Filter Section */
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
    width: 100%;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
.form-grid.three-comp {
    grid-template-columns: repeat(3, 1fr);
}
.form-full {
    grid-column: 1 / -1;
}
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
</style>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom" style="height: auto;">
            
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box purple"><i class="fas fa-building"></i></div>
                        Projects
                    </h3>
                    <div class="chart-subtitle">Manage construction projects and timelines</div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="document.getElementById('filterSection').style.display = document.getElementById('filterSection').style.display === 'none' ? 'block' : 'none'" class="modern-btn" style="background:#f1f5f9; color:#475569;">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    <button class="modern-btn" onclick="openProjectModal('addProjectModal')" style="background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> New Project
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div id="filterSection" style="display: <?= ($filters['search'] || $filters['status']) ? 'block' : 'none' ?>;">
                <form method="GET" class="filter-card">
                    <div class="filter-row">
                        <div style="flex: 2; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                            <input type="text" name="search" class="modern-input" placeholder="Search projects..." value="<?= htmlspecialchars($filters['search']) ?>" style="padding-left: 32px;">
                        </div>
                        <select name="status" class="modern-select" style="flex:1;">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="on_hold" <?= $filters['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        </select>
                        <button type="submit" class="modern-btn">Apply</button>
                        <a href="projects.php" class="modern-btn" style="background:#94a3b8;">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive" style="overflow-y: visible;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding-left: 24px;">PROJECT NAME</th>
                            <th style="text-align: left;">LOCATION</th>
                            <th class="text-center">TIMELINE</th>
                            <th class="text-center">UNITS</th>
                            <th class="text-center">STATUS</th>
                            <th class="text-center">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-folder-open" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No projects found.
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($projects as $project): 
                                $color = ColorHelper::getProjectColor($project['id']);
                                $initial = ColorHelper::getInitial($project['project_name']);
                        ?>
                        <tr>
                            <td style="text-align: left; padding-left: 24px;">
                                <div style="display:flex; align-items:center;">
                                    <div class="avatar-square" style="background: <?= $color ?>;"><?= $initial ?></div>
                                    <span style="font-weight:700;"><?= htmlspecialchars($project['project_name']) ?></span>
                                </div>
                            </td>
                            <td style="text-align: left;">
                                <span style="color: #64748b; font-size: 13px;">
                                    <i class="fas fa-map-marker-alt" style="margin-right: 4px;"></i> <?= htmlspecialchars($project['location']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; flex-direction: column; align-items: center;">
                                    <span style="font-size: 12px; color: #64748b;">Start: <strong style="color:#1e293b;"><?= formatDate($project['start_date']) ?></strong></span>
                                    <?php if($project['expected_completion']): ?>
                                    <span style="font-size: 12px; color: #64748b;">End: <strong style="color:#1e293b;"><?= formatDate($project['expected_completion']) ?></strong></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge-pill purple"><?= $project['total_flats'] ?> Flats</span>
                            </td>
                            <td class="text-center">
                                <span class="badge-pill status-<?= $project['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="action-btn" onclick="viewProjectDetails(<?= htmlspecialchars(json_encode($project)) ?>)" title="View Details">
                                    <i class="far fa-eye"></i>
                                </button>
                                <button class="action-btn" onclick="editProject(<?= htmlspecialchars(json_encode($project)) ?>)" title="Edit">
                                    <i class="far fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="openDeleteModal(<?= $project['id'] ?>)" title="Delete">
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
<div id="viewProjectModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px; border-radius: 12px; padding: 0; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px;">
            <h5 class="modal-title" style="font-weight: 800; color: #1e293b; margin: 0; font-size: 18px;" id="view_project_title">Project Name</h5>
            <button class="modal-close" onclick="closeProjectModal('viewProjectModal')" style="font-size: 24px; color: #94a3b8; border:none; background:transparent; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 25px;">
            <!-- Status Badge -->
            <div class="text-center mb-4">
                <span id="view_status_badge" class="badge-pill" style="font-size: 12px; padding: 6px 16px;"></span>
            </div>

            <!-- Stats Grid -->
            <div class="row mb-4" style="background: #f8fafc; border-radius: 12px; padding: 15px; margin: 0;">
                 <div class="col-6 text-center" style="border-right: 1px solid #e2e8f0;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Total Units</div>
                    <div style="font-size: 24px; font-weight: 800; color: #1e293b;" id="view_total_units">0</div>
                </div>
                <div class="col-6 text-center">
                    <div style="font-size: 11px; text-transform: uppercase; color: #ef4444; font-weight: 700; letter-spacing: 0.5px;">Units Left</div>
                    <div style="font-size: 24px; font-weight: 800; color: #ef4444;" id="view_units_left">0</div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 500; font-size: 13px;">Location</span>
                <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_location"></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 500; font-size: 13px;">Total Floors</span>
                <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_floors"></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b; font-weight: 500; font-size: 13px;">Start Date</span>
                <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_start_date"></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0;">
                <span style="color: #64748b; font-weight: 500; font-size: 13px;">Completion Date</span>
                <span style="color: #1e293b; font-weight: 600; font-size: 13px;" id="view_end_date"></span>
            </div>
        </div>
        <div class="modal-footer" style="padding: 15px 25px; background: #f8fafc; border-top: 1px solid #f1f5f9; border-radius: 0 0 12px 12px;">
             <button type="button" class="modern-btn" style="background: #fff; color: #64748b; border: 1px solid #e2e8f0;" onclick="closeProjectModal('viewProjectModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div id="addProjectModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-layer-group"></i> New Project</h3>
                <p>Launch a new construction project and track its progress</p>
            </div>
            <button class="modal-close-btn" onclick="closeProjectModal('addProjectModal')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="modal-body-premium">
                
                <!-- Basic Info -->
                <div class="form-section-title"><i class="fas fa-info-circle"></i> Project Essentials</div>
                <div class="form-grid-premium">
                    <div class="input-group-modern full-width">
                        <label class="input-label">Project Name *</label>
                        <input type="text" name="project_name" required class="modern-input" placeholder="e.g. Skyline Towers Phase I">
                    </div>
                    <div class="input-group-modern full-width">
                        <label class="input-label">Location *</label>
                        <input type="text" name="location" required class="modern-input" placeholder="e.g. Sector 45, Downtown">
                    </div>
                </div>

                <!-- Timeline -->
                <div class="form-section-title"><i class="far fa-calendar-alt"></i> Production Timeline</div>
                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Start Date *</label>
                        <input type="date" name="start_date" required class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Target Completion</label>
                        <input type="date" name="expected_completion" class="modern-input">
                    </div>
                </div>

                <!-- Specs -->
                <div class="form-section-title"><i class="fas fa-ruler-combined"></i> Scope & Status</div>
                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Floors</label>
                        <input type="number" name="total_floors" min="0" value="0" class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Total Flats</label>
                        <input type="number" name="total_flats" min="0" value="0" class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Initial Status</label>
                        <select name="status" class="modern-select">
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeProjectModal('addProjectModal')">Cancel</button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-rocket"></i> Launch Project
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Edit Project Modal -->
<div id="editProjectModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Project</h3>
                <p>Modify project details and configuration</p>
            </div>
            <button class="modal-close-btn" onclick="closeProjectModal('editProjectModal')">&times;</button>
        </div>

        <form method="POST" id="editProjectForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-body-premium">
                
                <!-- Basic Info -->
                <div class="form-section-title"><i class="fas fa-info-circle"></i> Project Essentials</div>
                <div class="form-grid-premium">
                    <div class="input-group-modern full-width">
                        <label class="input-label">Project Name *</label>
                        <input type="text" name="project_name" id="edit_project_name" required class="modern-input">
                    </div>
                    <div class="input-group-modern full-width">
                        <label class="input-label">Location *</label>
                        <input type="text" name="location" id="edit_location" required class="modern-input">
                    </div>
                </div>

                <!-- Timeline -->
                <div class="form-section-title"><i class="far fa-calendar-alt"></i> Production Timeline</div>
                <div class="form-grid-premium">
                    <div class="input-group-modern">
                        <label class="input-label">Start Date *</label>
                        <input type="date" name="start_date" id="edit_start_date" required class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Target Completion</label>
                        <input type="date" name="expected_completion" id="edit_expected_completion" class="modern-input">
                    </div>
                </div>

                <!-- Specs -->
                <div class="form-section-title"><i class="fas fa-sliders-h"></i> Configuration</div>
                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Floors</label>
                        <input type="number" name="total_floors" id="edit_total_floors" min="0" class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Total Flats</label>
                        <input type="number" name="total_flats" id="edit_total_flats" min="0" class="modern-input">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Status</label>
                        <select name="status" id="edit_status" class="modern-select">
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="closeProjectModal('editProjectModal')">Discard Changes</button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-check"></i> Update Project
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteProjectModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 400px; border-radius: 16px;">
        <div class="modal-body" style="text-align: center; padding: 40px 30px;">
            <div style="width: 72px; height: 72px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto;">
                <i class="fas fa-trash-alt" style="font-size: 32px; color: #ef4444;"></i>
            </div>
            
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #1e293b;">Delete Project?</h3>
            <p style="margin: 0 0 32px 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                Are you sure you want to delete this project?<br>
                <span style="color: #ef4444; font-weight: 600;">This action cannot be undone.</span>
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="modern-btn btn-cancel" onclick="closeProjectModal('deleteProjectModal')">
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
function openProjectModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
}

function closeProjectModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = "none";
        document.body.style.overflow = 'auto';
    }
}

function editProject(project) {
    document.getElementById('edit_id').value = project.id;
    document.getElementById('edit_project_name').value = project.project_name;
    document.getElementById('edit_location').value = project.location;
    document.getElementById('edit_start_date').value = project.start_date;
    document.getElementById('edit_expected_completion').value = project.expected_completion || '';
    document.getElementById('edit_total_floors').value = project.total_floors;
    document.getElementById('edit_total_flats').value = project.total_flats;
    document.getElementById('edit_status').value = project.status;
    openProjectModal('editProjectModal');
}

function viewProjectDetails(project) {
    document.getElementById('view_project_title').innerText = project.project_name;
    document.getElementById('view_location').innerText = project.location;
    document.getElementById('view_start_date').innerText = project.start_date;
    document.getElementById('view_end_date').innerText = project.expected_completion || 'N/A';
    document.getElementById('view_floors').innerText = project.total_floors;
    
    // Units Calculation
    const totalUnits = parseInt(project.total_flats) || 0;
    const bookedCount = parseInt(project.booked_count) || 0; // fetched from service
    const unitsLeft = totalUnits - bookedCount;

    document.getElementById('view_total_units').innerText = totalUnits;
    document.getElementById('view_units_left').innerText = unitsLeft;

    
    // Status Logic
    const badgeSpan = document.getElementById('view_status_badge');
    badgeSpan.className = 'badge-pill status-' + project.status;
    badgeSpan.innerText = project.status.replace('_', ' ').charAt(0).toUpperCase() + project.status.slice(1).replace('_', ' ');
    
    openProjectModal('viewProjectModal');
}

function openDeleteModal(projectId) {
    document.getElementById('delete_id').value = projectId;
    openProjectModal('deleteProjectModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
