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

$db = Database::getInstance();
$page_title = 'Flats';
$current_page = 'flats';

// Handle CRUD Operations
$masterService = new MasterService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            // Concatenate prefix if provided (Multi-tower logic)
            $flatNo = sanitize($_POST['flat_no']);
            if (!empty($_POST['flat_prefix_single'])) {
                $flatNo = sanitize($_POST['flat_prefix_single']) . '-' . $flatNo;
            }

            $data = [
                'project_id' => intval($_POST['project_id']),
                'flat_no' => $flatNo,
                'floor' => intval($_POST['floor']),
                'unit_type' => $_POST['unit_type'] ?? 'flat',
                'bhk' => $_POST['bhk'],
                'rera_area' => floatval($_POST['rera_area']),
                'sellable_area' => floatval($_POST['sellable_area']),
                'usable_area' => floatval($_POST['usable_area']),
                'area_sqft' => floatval($_POST['sellable_area']), // Mapping sellable to main area
                'total_value' => floatval($_POST['flat_value']),
                'rate_per_sqft' => (floatval($_POST['sellable_area']) > 0) ? (floatval($_POST['flat_value']) / floatval($_POST['sellable_area'])) : 0,
                'status' => $_POST['status']
            ];
            $masterService->createFlat($data);
            setFlashMessage('success', 'Unit created successfully');
            
            
            } elseif ($action === 'bulk_create') {

                $projectId  = intval($_POST['project_id']);
                $floorCount = intval($_POST['floor_count']);
                $prefix     = sanitize($_POST['flat_prefix']);
                $flatMix    = json_decode($_POST['flat_mix'], true);

                if (!$projectId || !$floorCount || empty($flatMix)) {
                    throw new Exception('Invalid bulk configuration');
                }

                // 5. Typical Floor Safety Check
                if ($floorCount >= 2) {
                    if (empty($flatMix['typical_floor']['units'])) {
                        throw new Exception("Typical floor configuration is required for projects with 2+ floors.");
                    }
                    // Validate Strict Flat Only
                    foreach ($flatMix['typical_floor']['units'] as $u) {
                        if ($u['unit_type'] !== 'flat') throw new Exception("Typical floors create FLATS only.");
                    }
                }

                $db->beginTransaction();
                $count = 0;

                try {
                    // Helper to create unit
                    $createUnit = function($floor, $unitData, $index) use ($masterService, $projectId, $prefix) {
                        
                        // 4. Area & Value Validation
                        $sellableArea = floatval($unitData['sellable_area']);
                        $flatValue    = floatval($unitData['flat_value']);
                        $bhk          = $unitData['bhk'] ?? '';
                        $unitType     = $unitData['unit_type'];

                        if ($sellableArea <= 0) throw new Exception("Sellable Area must be > 0 (Row $index)");
                        if ($flatValue <= 0)    throw new Exception("Flat Value must be > 0 (Row $index)");
                        
                        // 4. Usable Area Validation
                        if (!empty($unitData['usable_area']) && $unitData['usable_area'] > $sellableArea) {
                             throw new Exception("Usable area cannot exceed sellable area (Row $index)");
                        }

                        // 3. BHK Enforcement
                        if ($unitType === 'flat' && empty($bhk)) {
                            throw new Exception("BHK is required for Flats (Floor $floor, Row $index)");
                        }
                        if ($unitType === 'shop' && !empty($bhk)) {
                            throw new Exception("Shops cannot have a BHK value (Floor $floor, Row $index)");
                        }
                        
                        $rate = $flatValue / $sellableArea;

                        // 2. Flat Numbering Edge Cases
                        // Standardize format: {prefix}{floor}{2-digit-sequence}
                        // Floor 0 -> A-001, Floor 1 -> A-101
                        $seqStr = str_pad($index, 2, '0', STR_PAD_LEFT);
                        $flatNo = $prefix . $floor . $seqStr;

                        $masterService->createFlat([
                            'project_id'    => $projectId,
                            'flat_no'       => $flatNo,
                            'floor'         => $floor,
                            'unit_type'     => $unitType,
                            'bhk'           => ($unitType === 'shop') ? null : $bhk,
                            'rera_area'     => floatval($unitData['rera_area']),
                            'sellable_area' => $sellableArea,
                            'usable_area'   => floatval($unitData['usable_area']),
                            'area_sqft'     => $sellableArea,
                            'total_value'   => $flatValue,
                            'rate_per_sqft' => $rate,
                            'status'        => 'available'
                        ]);
                    };

                    // 1. Ground Floor
                    if (!empty($flatMix['ground_floor'])) {
                        foreach ($flatMix['ground_floor'] as $idx => $unit) {
                            $createUnit(0, $unit, $idx + 1);
                            $count++;
                        }
                    }

                    // 2. First Floor
                    if (!empty($flatMix['first_floor'])) {
                        foreach ($flatMix['first_floor'] as $idx => $unit) {
                            $createUnit(1, $unit, $idx + 1);
                            $count++;
                        }
                    }

                    // 3. Typical Floors
                    if (!empty($flatMix['typical_floor']['units']) && $floorCount >= 2) {
                        $startFloor = max(2, intval($flatMix['typical_floor']['start_floor']));
                        
                        for ($f = $startFloor; $f <= $floorCount; $f++) {
                            foreach ($flatMix['typical_floor']['units'] as $idx => $unit) {
                                $createUnit($f, $unit, $idx + 1);
                                $count++;
                            }
                        }
                    }
                    
                    $db->commit();
                    setFlashMessage('success', "$count units created successfully");

                } catch (Exception $ex) {
                    $db->rollBack();
                    throw $ex;
                }
            } elseif ($action === 'update') {
            $data = [
                'flat_no' => sanitize($_POST['flat_no']),
                'floor' => intval($_POST['floor']),
                'unit_type' => $_POST['unit_type'],
                'bhk' => $_POST['bhk'],
                'rera_area' => floatval($_POST['rera_area']),
                'sellable_area' => floatval($_POST['sellable_area']),
                'usable_area' => floatval($_POST['usable_area']),
                'area_sqft' => floatval($_POST['sellable_area']),
                'total_value' => floatval($_POST['flat_value']),
                'rate_per_sqft' => (floatval($_POST['sellable_area']) > 0) ? (floatval($_POST['flat_value']) / floatval($_POST['sellable_area'])) : 0,
                'status' => $_POST['status']
            ];
            $masterService->updateFlat(intval($_POST['id']), $data);
            setFlashMessage('success', 'Unit updated successfully');
            
        } elseif ($action === 'delete') {
            $masterService->deleteFlat(intval($_POST['id']));
            setFlashMessage('success', 'Flat deleted successfully');

        } elseif ($action === 'bulk_delete') {
            $ids = json_decode($_POST['ids'], true);
            $count = $masterService->bulkDeleteFlats($ids);
            setFlashMessage('success', "$count flats deleted successfully");
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/masters/flats.php');
}

// Fetch flats
$filters = [
    'project_id' => $_GET['project'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$flats = $masterService->getAllFlats($filters);
$projects = $masterService->getAllProjects(); // Reusing getAllProjects for dropdown
$stats = $masterService->getFlatStats();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
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
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.custom-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #3b82f6;
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
    background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); /* Blue -> Cyan Gradient */
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
    display: block; /* Reset from flex in old css */
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
    border-color: #3b82f6; /* Blue */
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
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
.btn-ghost.delete:hover { background: #fee2e2; color: #ef4444; border-color: #fecaca; }

.btn-danger-premium {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.form-grid-premium {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }
.three-cols { grid-template-columns: 1fr 1fr 1fr; }

/* Filter specifics */
.filter-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}
/* Helper Box */
.info-box {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    color: #1e40af;
    font-size: 13px;
    line-height: 1.5;
}
.info-box i { font-size: 16px; margin-top: 2px; }

/* Fix select alignment in flat mix table */
#flatMixTable .modern-select {
    height: 45px;
    padding: 0 14px;
    display: flex;
    align-items: center;
    line-height: 45px;
}

/* Align inputs in same row */
#flatMixTable .modern-input {
    height: 45px;
}

#flatMixTable td {
    vertical-align: middle;
}

/* Custom Checkbox */
.custom-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    appearance: none;
    background: white;
    transition: all 0.2s;
}
.custom-checkbox:checked {
    background: #3b82f6;
    border-color: #3b82f6;
}
.custom-checkbox:checked::after {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    color: white;
    font-size: 12px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

</style>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon bg-emerald-light">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h4>Available Flats</h4>
            <div class="value"><?= $stats['available'] ?? 0 ?></div>
        </div>
    </div>

    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
        <div class="stat-info">
            <h4>Sold Flats</h4>
            <div class="value"><?= ($stats['sold'] ?? 0) + ($stats['booked'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-violet-light">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-info">
            <h4>Total Flats</h4>
            <div class="value"><?= array_sum($stats) ?></div>
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
                        <div class="chart-icon-box" style="background: #0f172a; color: white;"><i class="fas fa-layer-group"></i></div>
                        Flats Management
                    </h3>
                    <div class="chart-subtitle">Manage project inventory, status, and pricing</div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button id="bulkDeleteBtn" class="modern-btn" onclick="confirmBulkDelete()" style="background: #fee2e2; color: #ef4444; display: none; margin-right: 12px;">
                        <i class="fas fa-trash-alt"></i> Bulk Delete (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn-save" onclick="showFlatModal('bulkCreateModal')" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-magic"></i> Bulk Create
                    </button>
                    <button class="btn-save" onclick="showFlatModal('addFlatModal')" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
                        <i class="fas fa-plus"></i> Add Flat
                    </button>
                </div>
            </div>

            <div style="padding: 20px;">
                <!-- Filters -->
                <form method="GET" class="filter-card mb-4">
                    <div class="filter-row">
                        <div class="input-group-modern" style="flex: 2;">
                            <label class="input-label" style="margin-bottom: 5px;">Project</label>
                            <select name="project" class="modern-select">
                                <option value="">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>" <?= $filters['project_id'] == $project['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group-modern" style="flex: 1;">
                            <label class="input-label" style="margin-bottom: 5px;">Status</label>
                            <select name="status" class="modern-select">
                                <option value="">All Status</option>
                                <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="booked" <?= $filters['status'] === 'booked' ? 'selected' : '' ?>>Booked</option>
                                <option value="sold" <?= $filters['status'] === 'sold' ? 'selected' : '' ?>>Sold</option>
                            </select>
                        </div>
                        <div class="input-group-modern" style="flex: 2;">
                            <label class="input-label" style="margin-bottom: 5px;">Search</label>
                            <input type="text" name="search" class="modern-input" placeholder="Search flat number..." 
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label" style="visibility: hidden; margin-bottom: 5px;">Action</label>
                            <button type="submit" class="modern-btn filter-btn">Filter</button>
                        </div>
                    </div>
                </form>

                <style>
                    /* Filter Row Specifics */
                    .filter-row .modern-input, 
                    .filter-row .modern-select,
                    .filter-btn {
                        height: 45px;
                        box-sizing: border-box;
                    }

                    .filter-row .input-group-modern {
                        margin-bottom: 0; /* Remove default margin in filter row */
                    }
                    
                    .modern-btn {
                        background: #3b82f6; 
                        color: white; 
                        border: none; 
                        border-radius: 10px; 
                        font-weight: 600; 
                        font-size: 14px;
                        cursor: pointer;
                        transition: all 0.2s;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding: 0 24px;
                    }
                    .modern-btn:hover { background: #2563eb; transform: translateY(-1px); }
                </style>

                <!-- Flats Table -->
                <div class="table-responsive">
                    <form id="bulkDeleteForm" method="POST">
                        <input type="hidden" name="action" value="bulk_delete">
                        <input type="hidden" name="ids" id="bulkDeleteIds">
                        
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="selectAll" class="custom-checkbox" onclick="toggleAll(this)"></th>
                                    <th>PROJECT</th>
                                    <th>FLAT NO</th>
                                    <th>FLOOR</th>
                                    <th>BHK</th>
                                    <th>AREA (SQFT)</th>
                                    <th>RATE/SQFT</th>
                                    <th>AG. AMOUNT</th>
                                    <th>STATUS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $colors = ['av-green', 'av-blue', 'av-purple', 'av-orange'];
                                $idx = 0;
                                if (empty($flats)): 
                                ?>
                                    <tr>
                                        <td colspan="10" class="text-center" style="padding: 40px; color: #94a3b8;">
                                            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                            No flats found
                                        </td>
                                    </tr>
                                <?php else: 
                                    foreach ($flats as $flat): 
                                        $color = ColorHelper::getProjectColor($flat['project_id']);
                                        $initial = ColorHelper::getInitial($flat['project_name']);
                                        
                                        // Status colors matching design system
                                        $statusClass = 'gray';
                                        if($flat['status'] === 'available') $statusClass = 'green';
                                        if($flat['status'] === 'booked') $statusClass = 'orange';
                                        if($flat['status'] === 'sold') $statusClass = 'blue';
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($flat['status'] === 'available'): ?>
                                                <input type="checkbox" class="custom-checkbox flat-checkbox" value="<?= $flat['id'] ?>" onclick="updateBulkState()">
                                            <?php else: ?>
                                                <i class="fas fa-lock" style="color: #cbd5e1; font-size: 14px; margin-left: 2px;" title="Cannot delete booked/sold flat"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                                <span style="font-weight:700; color: #1e293b;"><?= htmlspecialchars($flat['project_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-pill blue" style="font-size: 13px;"><?= htmlspecialchars($flat['flat_no']) ?></span>
                                        </td>
                                        <td><span style="font-weight: 500; color: #64748b;"><?= $flat['floor'] ?></span></td>
                                        <td><span class="badge-pill purple"><?= htmlspecialchars($flat['bhk'] ?? '—') ?></span></td>       
                                        <td><span style="font-weight: 600; color: #475569;"><?= number_format($flat['area_sqft'], 2) ?></span></td>
                                        <td>
                                            <?php if(!empty($flat['booked_rate'])): ?>
                                                <span style="color: #64748b; font-family: monospace;" title="Booked Rate"><?= formatCurrency($flat['booked_rate']) ?></span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-family: monospace;"><?= formatCurrency($flat['rate_per_sqft']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($flat['booked_amount'])): ?>
                                                <span style="font-weight: 700; color: #059669; font-size: 14px;" title="Agreement Amount"><?= formatCurrency($flat['booked_amount']) ?></span>
                                            <?php else: ?>
                                                <span style="font-weight: 700; color: #9ca3af; font-size: 14px;"><?= formatCurrency($flat['total_value']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-pill <?= $statusClass ?>"><?= ucfirst($flat['status']) ?></span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="action-btn" onclick="viewFlatDetails(<?= $flat['id'] ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="action-btn" onclick="editFlat(<?= htmlspecialchars(json_encode($flat)) ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($flat['status'] === 'available'): ?>
                                                <button type="button" class="action-btn delete" onclick="openDeleteModal(<?= $flat['id'] ?>, '<?= htmlspecialchars($flat['flat_no']) ?>')" title="Delete">
                                                    <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>

            </div>
        </div>
    </div>
</div>

<!-- Bulk Create Modal -->
<div id="bulkCreateModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 1100px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-layer-group"></i> Bulk Create Flats</h3>
                <p>Configure Ground, First, and Typical floors</p>
            </div>
            <button class="modal-close-btn" onclick="hideFlatModal('bulkCreateModal')">&times;</button>
        </div>

        <form method="POST" id="bulkCreateForm">
            <div class="modal-body-premium">
                <input type="hidden" name="action" value="bulk_create">
                <input type="hidden" name="flat_mix" id="flat_mix">

                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Project *</label>
                        <select name="project_id" class="modern-select" required>
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        data-multi-tower="<?= $project['has_multiple_towers'] ?? 0 ?>"
                                        data-total-floors="<?= $project['total_floors'] ?? 0 ?>">
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Total Floors *</label>
                        <input type="number" name="floor_count" id="floor_count" class="modern-input" min="1" required placeholder="e.g. 10">
                    </div>
                    <div class="input-group-modern" id="prefix_container" style="display:none;">
                        <label class="input-label">Flat Prefix</label>
                        <input type="text" name="flat_prefix" class="modern-input" value="A-" placeholder="e.g. A-">
                        <small style="color:#64748b; font-size:11px;">Ex: "A-" generates A-101, A-102</small>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs-container" style="margin: 20px 0;">
                    <button type="button" class="tab-btn active" onclick="showTab('ground')">Ground Floor (G)</button>
                    <button type="button" class="tab-btn" onclick="showTab('first')">First Floor (1)</button>
                    <button type="button" class="tab-btn" onclick="showTab('typical')">Typical Floors (2+)</button>
                </div>

                <style>
                    .tabs-container { display: flex; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 2px; }
                    .tab-btn {
                        padding: 10px 20px; border: none; background: none; font-weight: 600; color: #64748b; cursor: pointer;
                        border-bottom: 2px solid transparent; transition: all 0.2s;
                    }
                    .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
                    .tab-content { display: none; }
                    .tab-content.active { display: block; animation: fadeIn 0.3s; }
                    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                    
                    /* Table inputs */
                    .mix-table .modern-input, .mix-table .modern-select { height: 40px; padding: 5px 10px; font-size: 13px; }
                    .mix-table th { font-size: 12px; color: #94a3b8; text-transform: uppercase; padding-bottom: 10px; }
                    .mix-table th:first-child { width: 120px; } /* Increased width for Type column */
                    .rate-display { background: #f1f5f9; color: #64748b; font-weight: 600; border: 1px solid #e2e8f0; }
                </style>

                <!-- Ground Floor -->
                <div id="tab-ground" class="tab-content active">
                    <div class="info-box"><i class="fas fa-store"></i> Define Shops/Offices/Flats for Ground Floor.</div>
                    <table class="modern-table mix-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>RERA (Sqft)</th>
                                <th>Sellable (Sqft)</th>
                                <th>Usable (Sqft)</th>
                                <th>Flat Value</th>
                                <th>Rate/Sqft</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ground"></tbody>
                    </table>
                    <button type="button" class="btn-ghost" onclick="addItemRow('ground')"><i class="fas fa-plus"></i> Add Unit</button>
                </div>

                <!-- First Floor -->
                <div id="tab-first" class="tab-content">
                    <div class="info-box"><i class="fas fa-layer-group"></i> Define Mixed Units (Shops/Offices/Flats) for First Floor.</div>
                    <table class="modern-table mix-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>BHK (Opt)</th>
                                <th>RERA</th>
                                <th>Sellable</th>
                                <th>Usable</th>
                                <th>Flat Value</th>
                                <th>Rate/Sqft</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-first"></tbody>
                    </table>
                    <button type="button" class="btn-ghost" onclick="addItemRow('first')"><i class="fas fa-plus"></i> Add Unit</button>
                </div>

                <!-- Typical Floors -->
                <div id="tab-typical" class="tab-content">
                    <div class="info-box" style="background: #eff6ff; color: #1e40af;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Configuration from Floor 2 will repeat for all above floors.</strong>
                        Only 'Flat' type allowed.
                    </div>
                    <table class="modern-table mix-table">
                        <thead>
                            <tr>
                                <th>TYPE</th>
                                <th>BHK</th>
                                <th>RERA</th>
                                <th>Sellable</th>
                                <th>Usable</th>
                                <th>Flat Value</th>
                                <th>Rate/Sqft</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-typical"></tbody>
                    </table>
                    <button type="button" class="btn-ghost" onclick="addItemRow('typical')"><i class="fas fa-plus"></i> Add Flat</button>
                </div>

            </div>

            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="hideFlatModal('bulkCreateModal')">Cancel</button>
                <button type="submit" class="btn-save" onclick="return submitBulkForm()">
                    <i class="fas fa-bolt"></i> Generate Flats
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Flat Details Modal -->
<div id="flatDetailsModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 800px;">
        <div class="modal-header-premium">
             <div class="modal-title-group">
                <h3><i class="fas fa-home"></i> Flat Details</h3>
            </div>
            <button class="modal-close-btn" onclick="hideFlatModal('flatDetailsModal')">&times;</button>
        </div>
        <div class="modal-body-premium" id="flat_details_content" style="background: #f8fafc; min-height: 200px;">
            <div style="text-align:center; padding:50px; color:#94a3b8;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 15px;"></i>
                <div>Loading details...</div>
            </div>
        </div>
    </div>
</div>

<!-- Add Single Flat Modal -->
<div id="addFlatModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 800px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-plus-circle"></i> Add Single Unit</h3>
            </div>
            <button class="modal-close-btn" onclick="hideFlatModal('addFlatModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body-premium">
                <input type="hidden" name="action" value="create">
                
                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Project *</label>
                        <select name="project_id" class="modern-select" required onchange="toggleSinglePrefix(this)">
                            <option value="">-- Select Project --</option> 
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        data-multi-tower="<?= $project['has_multiple_towers'] ?>"
                                        data-total-floors="<?= $project['total_floors'] ?? 0 ?>">
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="input-group-modern" id="add_single_prefix_container" style="display:none;">
                        <label class="input-label">Tower *</label>
                        <input type="text" name="flat_prefix_single" id="add_flat_prefix_single" class="modern-input" placeholder="e.g. A">
                    </div>

                    <div class="input-group-modern">
                        <label class="input-label">Flat/Unit No *</label>
                        <input type="text" name="flat_no" class="modern-input" required placeholder="e.g. 101">
                    </div>
                     <div class="input-group-modern">
                        <label class="input-label">Floor *</label>
                        <input type="number" name="floor" class="modern-input" required>
                    </div>
                </div>

                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Type *</label>
                        <select name="unit_type" class="modern-select" required>
                            <option value="flat">Flat</option>
                            <option value="shop">Shop</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">BHK</label>
                         <select name="bhk" class="modern-select">
                            <option value="">--</option>
                            <option value="1BHK">1 BHK</option>
                            <option value="2BHK">2 BHK</option>
                            <option value="3BHK">3 BHK</option>
                        </select>
                    </div>
                     <div class="input-group-modern">
                        <label class="input-label">Status</label>
                        <select name="status" class="modern-select">
                            <option value="available">Available</option>
                            <option value="booked">Booked</option>
                            <option value="sold">Sold</option>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">Areas & Pricing</div>
                <div class="form-grid-premium three-cols">
                   <div class="input-group-modern">
                        <label class="input-label">RERA Area</label>
                        <input type="number" name="rera_area" class="modern-input" step="0.01">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Usable Area</label>
                        <input type="number" name="usable_area" class="modern-input" step="0.01">
                    </div> 
                    <div class="input-group-modern">
                        <label class="input-label">Sellable Area *</label>
                        <input type="number" name="sellable_area" id="add_sellable_area" class="modern-input" step="0.01" required oninput="calcSingleRate('add')">
                    </div>
                </div>

                <div class="form-grid-premium">
                     <div class="input-group-modern">
                        <label class="input-label">Total Flat Value *</label>
                        <input type="number" name="flat_value" id="add_flat_value" class="modern-input" step="0.01" required oninput="calcSingleRate('add')">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Rate / Sqft (Auto)</label>
                        <input type="text" id="add_rate_display" class="modern-input" style="background: #f1f5f9;" readonly>
                    </div>
                </div>

            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="hideFlatModal('addFlatModal')">Cancel</button>
                <button type="submit" class="btn-save">Save Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Flat Modal -->
<div id="editFlatModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 800px;">
        <div class="modal-header-premium">
            <div class="modal-title-group">
                <h3><i class="fas fa-edit"></i> Edit Unit Details</h3>
            </div>
            <button class="modal-close-btn" onclick="hideFlatModal('editFlatModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body-premium">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Flat/Unit No *</label>
                        <input type="text" name="flat_no" id="edit_flat_no" class="modern-input" required>
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Floor *</label>
                        <input type="number" name="floor" id="edit_floor" class="modern-input" required>
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Status</label>
                        <select name="status" id="edit_status" class="modern-select">
                            <option value="available">Available</option>
                            <option value="booked">Booked</option>
                            <option value="sold">Sold</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid-premium three-cols">
                    <div class="input-group-modern">
                        <label class="input-label">Type *</label>
                         <select name="unit_type" id="edit_unit_type" class="modern-select" required>
                            <option value="flat">Flat</option>
                            <option value="shop">Shop</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">BHK</label>
                         <select name="bhk" id="edit_bhk" class="modern-select">
                            <option value="">--</option>
                            <option value="1BHK">1 BHK</option>
                            <option value="2BHK">2 BHK</option>
                            <option value="3BHK">3 BHK</option>
                        </select>
                    </div>
                     <div class="input-group-modern">
                        <label class="input-label">Rate / Sqft (Auto)</label>
                        <input type="text" id="edit_rate_display" class="modern-input" style="background: #f1f5f9;" readonly>
                    </div>
                </div>

                <div class="form-section-title">Areas & Pricing</div>
                <div class="form-grid-premium three-cols">
                   <div class="input-group-modern">
                        <label class="input-label">RERA Area</label>
                        <input type="number" name="rera_area" id="edit_rera_area" class="modern-input" step="0.01">
                    </div>
                    <div class="input-group-modern">
                        <label class="input-label">Usable Area</label>
                        <input type="number" name="usable_area" id="edit_usable_area" class="modern-input" step="0.01">
                    </div> 
                    <div class="input-group-modern">
                        <label class="input-label">Sellable Area *</label>
                        <input type="number" name="sellable_area" id="edit_sellable_area" class="modern-input" step="0.01" required oninput="calcSingleRate('edit')">
                    </div>
                </div>
                
                <div class="input-group-modern">
                    <label class="input-label">Total Flat Value *</label>
                    <input type="number" name="flat_value" id="edit_flat_value" class="modern-input" step="0.01" required oninput="calcSingleRate('edit')">
                </div>
            </div>
            <div class="modal-footer-premium">
                <button type="button" class="btn-ghost" onclick="hideFlatModal('editFlatModal')">Cancel</button>
                <button type="submit" class="btn-save">Update Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 450px;">
        <div class="modal-body-premium" style="text-align: center; padding: 40px 32px;">
            <div style="width: 80px; height: 80px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i class="fas fa-trash-alt" style="font-size: 32px; color: #ef4444;"></i>
            </div>
            
            <h3 style="margin: 0 0 12px; color: #1e293b; font-weight: 800; font-size: 20px;">Delete Flat <span id="delete_flat_no"></span>?</h3>
            <p style="color: #64748b; margin-bottom: 32px; line-height: 1.6; font-size: 14px;">
                Are you sure you want to remove this flat from inventory?<br>
                <span style="color: #ef4444; font-weight: 600;">This action cannot be undone.</span>
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="btn-ghost" onclick="hideFlatModal('confirmDeleteModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn-danger-premium">
                        Yes, Delete It
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function viewFlatDetails(flatId) {
    showFlatModal('flatDetailsModal');
    // Load content via AJAX
    fetch('<?= BASE_URL ?>modules/masters/get_flat_details.php?id=' + flatId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('flat_details_content').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('flat_details_content').innerHTML = '<div style="text-align:center; padding:20px; color:#ef4444;">Failed to load details.</div>';
        });
}

function showFlatModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'block';
    
    // Init tabs if bulk modal
    if (id === 'bulkCreateModal' && document.querySelectorAll('#tbody-ground tr').length === 0) {
        showTab('ground');
    }
}

function hideFlatModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.style.display = 'none';
    }
}

// Single Flat Calc
function calcSingleRate(prefix) {
    const sellable = parseFloat(document.getElementById(prefix + '_sellable_area').value) || 0;
    const value = parseFloat(document.getElementById(prefix + '_flat_value').value) || 0;
    const rateDisplay = document.getElementById(prefix + '_rate_display');
    
    if (sellable > 0 && value > 0) {
        const rate = value / sellable;
        rateDisplay.value = rate.toFixed(2);
    } else {
        rateDisplay.value = '0.00';
    }
}

function editFlat(flat) {
    document.getElementById('edit_id').value = flat.id;
    document.getElementById('edit_flat_no').value = flat.flat_no;
    document.getElementById('edit_floor').value = flat.floor;
    document.getElementById('edit_unit_type').value = flat.unit_type || 'flat';
    document.getElementById('edit_bhk').value = flat.bhk;
    document.getElementById('edit_status').value = flat.status;
    
    // Areas
    document.getElementById('edit_sellable_area').value = flat.sellable_area || flat.area_sqft; // Fallback
    document.getElementById('edit_rera_area').value = flat.rera_area || '';
    document.getElementById('edit_usable_area').value = flat.usable_area || '';
    
    // Value & Rate
    document.getElementById('edit_flat_value').value = flat.total_value || 0;
    calcSingleRate('edit'); // Update display
    
    showFlatModal('editFlatModal');
}

function openDeleteModal(id, flatNo) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_flat_no').innerText = flatNo;
    showFlatModal('confirmDeleteModal');
}

// Bulk Delete
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.flat-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkState();
}

function updateBulkState() {
    const checkboxes = document.querySelectorAll('.flat-checkbox:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    const count = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        btn.style.display = 'inline-flex';
        count.textContent = checkboxes.length;
    } else {
        btn.style.display = 'none';
    }
}

function confirmBulkDelete() {
    const checkboxes = document.querySelectorAll('.flat-checkbox:checked');
    if (checkboxes.length === 0) return;
    document.getElementById('bulk_delete_count').innerText = checkboxes.length;
    document.getElementById('bulkDeleteModal').style.display = 'block';
}

function submitBulkDelete() {
    const checkboxes = document.querySelectorAll('.flat-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    document.getElementById('bulkDeleteIds').value = JSON.stringify(ids);
    document.getElementById('bulkDeleteForm').submit();
}

// --- BULK CREATE LOGIC ---
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('tab-' + tabName).classList.add('active');
    const buttons = document.querySelectorAll('.tab-btn');
    if(tabName === 'ground') buttons[0].classList.add('active');
    if(tabName === 'first') buttons[1].classList.add('active');
    if(tabName === 'typical') buttons[2].classList.add('active');
}

function addItemRow(section) {
    const tbody = document.getElementById('tbody-' + section);
    const row = document.createElement('tr');
    
    let typeOptions = `
        <select class="modern-select unit-type" onchange="toggleRowBhk(this)">
            <option value="shop">Shop</option>
            <option value="office">Office</option>
            <option value="flat">Flat</option>
        </select>`;
        
    // Typical floor check
    if (section === 'typical') {
        typeOptions = `<input type="hidden" class="unit-type" value="flat"><span class="badge-pill blue">FLAT</span>`;
    }

    let bhkCell = '';
    // Only add BHK cell if NOT ground floor
    if (section !== 'ground') {
        bhkCell = `
        <td>
             <select class="modern-select bhk" disabled style="background:#f1f5f9; cursor:not-allowed;">
                <option value="">--</option>
                <option value="1BHK">1 BHK</option>
                <option value="2BHK">2 BHK</option>
                <option value="3BHK">3 BHK</option>
            </select>
        </td>`;
    }

    row.innerHTML = `
        <td>${typeOptions}</td>
        ${bhkCell}
        <td><input type="number" class="modern-input rera" placeholder="RERA" step="0.01"></td>
        <td><input type="number" class="modern-input sellable" placeholder="Sellable" step="0.01" oninput="updateRowRate(this)"></td>
        <td><input type="number" class="modern-input usable" placeholder="Usable" step="0.01"></td>
        <td><input type="number" class="modern-input fval" placeholder="Total Value" step="0.01" oninput="updateRowRate(this)"></td>
        <td><input type="text" class="modern-input rate-display" readonly></td>
        <td><button type="button" class="btn-ghost" onclick="this.closest('tr').remove()"><i class="fas fa-times" style="color:#ef4444;"></i></button></td>
    `;
    tbody.appendChild(row);
    
    // Auto-init BHK state
    const select = row.querySelector('.unit-type');
    if(select && select.type !== 'hidden') toggleRowBhk(select);
    else if(section === 'typical') {
         // Typical is always flat, enable BHK
         row.querySelector('.bhk').disabled = false;
         row.querySelector('.bhk').style.background = '#fff';
         row.querySelector('.bhk').style.cursor = 'default';
    }
}

function toggleRowBhk(select) {
    const row = select.closest('tr');
    const bhk = row.querySelector('.bhk');
    // Guard clause if BHK column doesn't exist (Ground Floor)
    if (!bhk) return; 
    
    if (select.value === 'flat') {
        bhk.disabled = false;
        bhk.style.background = '#fff';
        bhk.style.cursor = 'default';
    } else if (select.value === 'office') {
        bhk.disabled = false; // "BHK Optional" per requirement
        bhk.style.background = '#fff';
    } else { // Shop
        bhk.disabled = true;
        bhk.value = '';
        bhk.style.background = '#f1f5f9';
        bhk.style.cursor = 'not-allowed';
    }
}

function updateRowRate(input) {
    const row = input.closest('tr');
    const sellable = parseFloat(row.querySelector('.sellable').value) || 0;
    const val = parseFloat(row.querySelector('.fval').value) || 0;
    const rateBox = row.querySelector('.rate-display');
    
    if (sellable > 0 && val > 0) {
        rateBox.value = (val / sellable).toFixed(2);
    } else {
        rateBox.value = '';
    }
}

function submitBulkForm() {
    // 6. Confirmation & 4. Frontend Validation
    const groundRows = getRows('ground');
    const firstRows  = getRows('first');
    const typicalRows = getRows('typical');
    const totalFloors = parseInt(document.getElementById('floor_count').value) || 0;
    
    // Validation
    const allRows = [...groundRows, ...firstRows, ...typicalRows];
    for (let i = 0; i < allRows.length; i++) {
        const r = allRows[i];
        if (parseFloat(r.sellable_area) <= 0 || isNaN(parseFloat(r.sellable_area))) {
            alert('Error: Sellable Area must be greater than 0 for all units.');
            return false;
        }
        if (parseFloat(r.flat_value) <= 0 || isNaN(parseFloat(r.flat_value))) {
            alert('Error: Flat Value must be greater than 0 for all units.');
            return false;
        }
        if (r.unit_type === 'flat' && !r.bhk) {
            alert('Error: BHK is required for all Flats.');
            return false;
        }
    }
    
    // 5. Typical Floor Safety
    if (totalFloors >= 2 && typicalRows.length === 0) {
        alert('Warning: You have ' + totalFloors + ' floors but no Typical Floor configuration. Please add units to Typical Floors.');
        return false;
    }

    // Calculate Total Units
    let totalUnits = groundRows.length + firstRows.length;
    let typicalCount = 0;
    if (totalFloors >= 2) {
        typicalCount = typicalRows.length * (totalFloors - 1);
        totalUnits += typicalCount;
    }
    
    // Populate Confirmation Modal
    document.getElementById('confirm_ground_count').textContent = groundRows.length;
    document.getElementById('confirm_first_count').textContent = firstRows.length;
    document.getElementById('confirm_typical_total').textContent = typicalCount;
    document.getElementById('confirm_typical_per_floor').textContent = typicalRows.length;
    document.getElementById('confirm_typical_floors').textContent = totalFloors >= 2 ? (totalFloors - 1) : 0;
    document.getElementById('confirm_total_final').textContent = totalUnits;

    // Construct Payload
    const payload = {
        ground_floor: groundRows,
        first_floor: firstRows,
        typical_floor: {
            start_floor: 2,
            units: typicalRows
        }
    };
    
    document.getElementById('flat_mix').value = JSON.stringify(payload);
    
    // Show Modal
    showFlatModal('bulkConfirmModal');
    return false; // Prevent immediate submission
}

function finalSubmitBulk() {
    // Find the form within the Bulk Create Modal and submit it
    // Assuming the button 'Generate Flats' is inside the form, we can find the form by ID or context
    // But since the new button is outside, we need to target the form explicitly.
    // The form starts at line ~550 in flats.php? No, it's lines 582 or 840+.
    // Let's rely on finding standard form or adding ID.
    // Actually, let's find the form that contains input name="action" value="bulk_create"
    const form = document.querySelector('input[name="action"][value="bulk_create"]').form;
    if(form) form.submit();
}


function getRows(section) {
    const rows = [];
    document.querySelectorAll('#tbody-' + section + ' tr').forEach(tr => {
        const bhkInput = tr.querySelector('.bhk');
        rows.push({
            unit_type: tr.querySelector('.unit-type').value,
            bhk: bhkInput ? bhkInput.value : null, // Handle missing BHK column
            rera_area: tr.querySelector('.rera').value,
            sellable_area: tr.querySelector('.sellable').value,
            usable_area: tr.querySelector('.usable').value,
            flat_value: tr.querySelector('.fval').value
        });
    });
    return rows;
}
</script>
<script>
// Multi-Tower Logic (Bulk)
// Multi-Tower Logic (Bulk) & Auto-Fill Floors
document.querySelector('select[name="project_id"]').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const isMultiTower = parseInt(selected.getAttribute('data-multi-tower')) === 1;
    const totalFloors = selected.getAttribute('data-total-floors');
    
    // Auto-fill Floor Count
    const floorInput = document.getElementById('floor_count');
    if(totalFloors && floorInput) {
        floorInput.value = totalFloors;
    }

    const prefixContainer = document.getElementById('prefix_container');
    
    if (isMultiTower) {
        prefixContainer.style.display = 'block';
    } else {
        prefixContainer.style.display = 'none';
         document.querySelector('input[name="flat_prefix"]').value = '';
    }
});

// Multi-Tower Logic (Single)
function toggleSinglePrefix(select) {
    const selected = select.options[select.selectedIndex];
    const isMultiTower = parseInt(selected.getAttribute('data-multi-tower')) === 1;
    const totalFloors = selected.getAttribute('data-total-floors');
    
    // Set Max Floor for Single Flat
    const floorInput = document.querySelector('#addFlatModal input[name="floor"]');
    if(floorInput && totalFloors) {
        floorInput.max = totalFloors;
        floorInput.placeholder = "Max " + totalFloors;
    }

    const container = document.getElementById('add_single_prefix_container');
    const input = document.getElementById('add_flat_prefix_single');

    if (isMultiTower) {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

// Init Single Modal on load (in case first project is multi-tower)
document.addEventListener('DOMContentLoaded', function() {
    const singleSelect = document.querySelector('#addFlatModal select[name="project_id"]');
    if(singleSelect) toggleSinglePrefix(singleSelect);
});
</script>


<!-- Bulk Confirmation Modal (Professional) -->
<div id="bulkConfirmModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px; border-radius: 16px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 30px; text-align: center; color: white;">
            <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);">
                <i class="fas fa-clipboard-check" style="font-size: 28px; color: #60a5fa;"></i>
            </div>
            <h3 style="margin: 0 0 8px; font-size: 22px; font-weight: 700;">Confirm Generation</h3>
            <p style="margin: 0; color: #94a3b8; font-size: 14px;">Review the inventory breakdown below</p>
        </div>
        
        <div style="padding: 30px;">
            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <span style="display: block; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Ground Floor</span>
                    <strong style="font-size: 20px; color: #1e293b;" id="confirm_ground_count">0</strong>
                </div>
                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <span style="display: block; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">First Floor</span>
                    <strong style="font-size: 20px; color: #1e293b;" id="confirm_first_count">0</strong>
                </div>
                <div style="background: #eff6ff; padding: 15px; border-radius: 12px; border: 1px solid #dbeafe; text-align: center; grid-column: span 2;">
                    <span style="display: block; font-size: 11px; text-transform: uppercase; color: #3b82f6; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Typical Floors</span>
                    <strong style="font-size: 24px; color: #1e40af;" id="confirm_typical_total">0</strong>
                     <div style="font-size: 12px; color: #60a5fa; margin-top: 2px;">
                        (<span id="confirm_typical_per_floor">0</span> units × <span id="confirm_typical_floors">0</span> floors)
                    </div>
                </div>
            </div>

            <div style="background: #1e293b; color: white; padding: 16px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-layer-group" style="font-size: 16px;"></i>
                    </div>
                    <span style="font-weight: 600;">Total New Units</span>
                </div>
                <span style="font-size: 24px; font-weight: 800; color: #4ade80;" id="confirm_total_final">0</span>
            </div>

            <div style="display: flex; gap: 15px;">
                <button type="button" class="btn-ghost" onclick="hideFlatModal('bulkConfirmModal')" style="flex: 1;">Back to Edit</button>
                <button type="button" class="btn-save" onclick="finalSubmitBulk()" style="flex: 1; justify-content: center;">
                    <i class="fas fa-bolt"></i> Yes, Generate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width: 500px; text-align: center; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); padding: 30px 20px 20px; border-bottom: 1px solid #fee2e2;">
            <div style="width: 60px; height: 60px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <i class="fas fa-layer-group" style="font-size: 24px; color: #ef4444;"></i>
            </div>
            <h3 style="margin: 0; color: #991b1b; font-size: 20px; font-weight: 700;">Bulk Delete Flats?</h3>
        </div>
        
        <div style="padding: 30px 25px;">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                You are about to delete <strong id="bulk_delete_count" style="color: #0f172a; font-size: 1.1em;">0</strong> selected flat(s).
                <br>
                <span style="display: inline-block; background: #fff1f2; color: #be123c; padding: 4px 10px; border-radius: 6px; margin-top: 8px; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle"></i> Warning: Irreversible Action
                </span>
                <br><br>
                This action contains <strong>no undo</strong>. Only available flats can be deleted.
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" onclick="document.getElementById('bulkDeleteModal').style.display='none'" 
                        style="padding: 12px 24px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="button" onclick="submitBulkDelete()" 
                        style="padding: 12px 24px; border: none; background: #ef4444; color: white; border-radius: 10px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25); transition: all 0.2s;">
                    Yes, Delete All
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
