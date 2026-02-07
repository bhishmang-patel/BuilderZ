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
$page_title = 'Material Usage';
$current_page = 'usage';

// Handle Usage Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/inventory/usage.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'record_usage') {
        $project_id = intval($_POST['project_id']);
        $material_id = intval($_POST['material_id']);
        $quantity = floatval($_POST['quantity']);
        $usage_date = $_POST['usage_date'];
        $remarks = sanitize($_POST['remarks'] ?? '');
        
        // Check current stock (Dynamic Calculation)
        $stock_sql = "SELECT m.material_name, m.unit,
                        (
                            (SELECT COALESCE(SUM(ci.quantity), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status = 'approved') 
                            - 
                            (SELECT COALESCE(SUM(mu.quantity), 0) FROM material_usage mu WHERE mu.material_id = m.id)
                        ) as current_stock
                      FROM materials m WHERE m.id = ?";
        $material = $db->query($stock_sql, [$material_id])->fetch();
        
        if ($quantity <= 0) {
            setFlashMessage('error', 'Usage quantity must be greater than zero.');
        } elseif ($material['current_stock'] < $quantity) {
            setFlashMessage(
                'error',
                "Insufficient stock for {$material['material_name']}. Available: {$material['current_stock']} {$material['unit']}"
            );
        } else {
            $db->beginTransaction();
            try {
                $data = [
                    'project_id' => $project_id,
                    'material_id' => $material_id,
                    'quantity' => $quantity,
                    'usage_date' => $usage_date,
                    'remarks' => $remarks,
                    'created_by' => $_SESSION['user_id']
                ];
                
                $id = $db->insert('material_usage', $data);
                
                logAudit('create', 'material_usage', $id, null, $data);
                $db->commit();
                
                setFlashMessage('success', 'Material usage recorded successfully');
                redirect('modules/inventory/usage.php');
                
            } catch (Exception $e) {
                $db->rollback();
                setFlashMessage('error', 'Error recording usage: ' . $e->getMessage());
            }
        }
    }
}

// Fetch usage history
$sql = "SELECT mu.*, p.project_name, m.material_name, m.unit,
               COALESCE(NULLIF(u.full_name, ''), u.username, 'Unknown') AS used_by_name
        FROM material_usage mu
        JOIN projects p ON mu.project_id = p.id
        JOIN materials m ON mu.material_id = m.id
        LEFT JOIN users u ON mu.created_by = u.id
        ORDER BY mu.usage_date DESC, mu.created_at DESC";

$usage_history = $db->query($sql)->fetchAll();

// Fetch projects and materials for form (Dynamic Stock)
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$materials_sql = "SELECT m.id, m.material_name, m.unit,
        (
            (SELECT COALESCE(SUM(ci.quantity), 0)
            FROM challan_items ci
            JOIN challans c ON ci.challan_id = c.id
            WHERE ci.material_id = m.id AND c.status = 'approved')
            -
            (SELECT COALESCE(SUM(mu.quantity), 0)
            FROM material_usage mu
            WHERE mu.material_id = m.id)
        ) AS current_stock
    FROM materials m
    HAVING current_stock > 0
    ORDER BY m.material_name";

$materials = $db->query($materials_sql)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Page Specific Styles */
.chart-card-custom {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 25px;
    height: 100%; /* Match height */
}

.chart-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
}

.chart-title-group h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-subtitle {
    font-size: 13px;
    color: #64748b;
    margin-left: 12px;
}

/* Icon Box */
.chart-icon-box {
    width: 42px;
    height: 42px;
    background: #f1f5f9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #475569;
}
.chart-icon-box.orange { background: #fff7ed; color: #f59e0b; }
.chart-icon-box.blue { background: #eff6ff; color: #3b82f6; }

/* Form Elements */
.form-group { margin-bottom: 20px; }
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}
.modern-input, .modern-select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    color: #1e293b;
    background: #fff;
    transition: all 0.2s;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
.modern-input:focus, .modern-select:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
textarea.modern-input { resize: vertical; min-height: 100px; height: auto; }

/* Modern Table */
.modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.modern-table th {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.modern-table td {
    padding: 16px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #1e293b;
}
.modern-table tr:last-child td { border-bottom: none; }
.modern-table tbody tr { transition: background 0.1s; }
.modern-table tbody tr:hover { background: #f8fafc; }

.badge-soft {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 12px;
}
.badge-soft.green { background: #ecfdf5; color: #059669; }
.badge-soft.red { background: #fef2f2; color: #ef4444; }

.modern-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    background: #0f172a;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    width: 100%;
}
.modern-btn:hover { background: #1e293b; transform: translateY(-1px); color: white; }
.modern-btn.secondary { background: #f1f5f9; color: #475569; width: auto; }
.modern-btn.secondary:hover { background: #e2e8f0; color: #1e293b; }
.modern-btn.warning { background: #f59e0b; color: white; }
.modern-btn.warning:hover { background: #d97706; }

</style>

<div class="row">
    <!-- Record Consumption Form -->
    <div class="col-md-5 mb-4" style="width: 35%;">
        <div class="chart-card-custom">
            
            <div class="chart-header-custom" style="margin-bottom: 20px;">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box orange"><i class="fas fa-minus-circle"></i></div>
                        Record Usage
                    </h3>
                    <div class="chart-subtitle" style="margin-left: 12px; margin-top: -2px;">Book material consumption</div>
                </div>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_usage">
                
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="usage_date" class="modern-input" required value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <div style="position: relative;">
                        <select name="project_id" class="modern-select" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>">
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 12px; margin-top: 2px;"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Material *</label>
                    <div style="position: relative;">
                        <select name="material_id" class="modern-select" required id="material_select">
                            <option value="">Select Material</option>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?= $material['id'] ?>" data-stock="<?= $material['current_stock'] ?>" data-unit="<?= $material['unit'] ?>">
                                    <?= htmlspecialchars($material['material_name']) ?> (Avail: <?= $material['current_stock'] ?> <?= $material['unit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 12px; margin-top: 2px;"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" name="quantity" step="0.01" class="modern-input" required placeholder="0.00">
                        <span id="unit_display" style="font-weight: 600; color: #64748b; background: #f1f5f9; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; min-width: 60px; text-align: center;">-</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="modern-input" rows="3" placeholder="Description of usage (e.g. Block A Foundation)"></textarea>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="modern-btn warning">
                        <i class="fas fa-check-circle"></i> Submit Consumption
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Usage History Table -->
    <div class="col-md-7 mb-4" style="width: 65%;">
        <div class="chart-card-custom">
             <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-history"></i></div>
                        Usage History
                    </h3>
                    <div class="chart-subtitle">Recent material consumption log</div>
                </div>
                <div>
                     <a href="<?= BASE_URL ?>modules/inventory/index.php" class="modern-btn secondary" style="padding: 10px 15px;">
                        <i class="fas fa-arrow-left"></i> Stock
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Material</th>
                            <th>Quantity</th>
                            <th>Remarks</th>
                            <th>Entered By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usage_history)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-clipboard-list" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No usage records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usage_history as $record): ?>
                            <tr>
                                <td><span style="font-weight:600; color:#475569;"><?= formatDate($record['usage_date']) ?></span></td>
                                <td>
                                    <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($record['project_name']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($record['material_name']) ?></td>
                                <td>
                                    <span class="badge-soft red">
                                        - <?= $record['quantity'] ?> <?= $record['unit'] ?>
                                    </span>
                                </td>
                                <td><span style="font-size:13px; color:#64748b;"><?= htmlspecialchars($record['remarks']) ?></span></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <div style="width:24px; height:24px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#64748b;">
                                            <?= strtoupper(substr(trim($record['used_by_name'] ?: 'U'), 0, 1)) ?>
                                        </div>
                                        <span style="font-size:13px;"><?= htmlspecialchars($record['used_by_name'] ?: 'Unknown') ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('material_select').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        document.getElementById('unit_display').textContent = option.getAttribute('data-unit');
    } else {
        document.getElementById('unit_display').textContent = '-';
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
