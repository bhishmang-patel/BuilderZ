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
$material_id = intval($_GET['id'] ?? 0);

if (!$material_id) {
    redirect('modules/inventory/index.php');
}

// Get material details
$stmt = $db->select('materials', 'id = ?', [$material_id]);
$material = $stmt->fetch();

if (!$material) {
    die('Material not found');
}

$page_title = 'Ledger: ' . $material['material_name'];
$current_page = 'stock';

// Fetch Purchases (Inward)
$sql_in = "SELECT c.challan_date as date, 
                  c.challan_no as reference, 
                  p.name as party_name, 
                  'purchase' as type,
                  ci.quantity,
                  ci.rate
           FROM challan_items ci
           JOIN challans c ON ci.challan_id = c.id
           JOIN parties p ON c.party_id = p.id
           WHERE ci.material_id = ? AND c.status != 'cancelled'";

// Fetch Usage (Outward)
$sql_out = "SELECT mu.usage_date as date, 
                   CONCAT('Usage - ', pr.project_name) as reference, 
                   u.full_name as party_name, 
                   'usage' as type,
                   mu.quantity,
                   0 as rate
            FROM material_usage mu
            JOIN projects pr ON mu.project_id = pr.id
            LEFT JOIN users u ON mu.created_by = u.id
            WHERE mu.material_id = ?";

// Merge and Order
$sql = "($sql_in) UNION ALL ($sql_out) ORDER BY date DESC, type ASC";
$stmt = $db->query($sql, [$material_id, $material_id]);
$transactions = $stmt->fetchAll();

// Calculate Stats for this specific ledger view
$total_in = 0;
$total_out = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'purchase') {
        $total_in += $t['quantity'];
    } else {
        $total_out += $t['quantity'];
    }
}

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
    margin-bottom: 30px;
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
.bg-red-light { background: #fef2f2; color: #ef4444; }

/* Chart/Table Card */
.chart-card-custom {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 25px;
    height: auto;
}

.chart-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
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
    margin-left: 52px;
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
.chart-icon-box.blue { background: #eff6ff; color: #3b82f6; }

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
.badge-soft.orange { background: #fff7ed; color: #f59e0b; }

.modern-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0f172a;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.modern-btn:hover { background: #1e293b; transform: translateY(-1px); color: white; }
.modern-btn.light { background: #f1f5f9; color: #475569; }
.modern-btn.light:hover { background: #e2e8f0; color: #1e293b; }

</style>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h4>Current Stock</h4>
            <div class="value"><?= number_format($material['current_stock'], 2) ?> <span style="font-size:14px; font-weight:500; color:#94a3b8;"><?= strtolower($material['unit']) ?></span></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-violet-light">
            <i class="fas fa-tag"></i>
        </div>
        <div class="stat-info">
            <h4>Default Rate</h4>
            <div class="value"><?= formatCurrency($material['default_rate']) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-emerald-light">
             <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-info">
            <h4>Total Purchased</h4>
            <div class="value"><?= number_format($total_in, 2) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-red-light">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-info">
            <h4>Total Used</h4>
            <div class="value"><?= number_format($total_out, 2) ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="chart-card-custom">
            <!-- Header -->
            <div class="chart-header-custom">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box blue"><i class="fas fa-history"></i></div>
                        Material Ledger: <?= htmlspecialchars($material['material_name']) ?>
                    </h3>
                    <div class="chart-subtitle">Transaction history and stock movement</div>
                </div>
                
                <div>
                    <a href="<?= BASE_URL ?>modules/inventory/index.php" class="modern-btn light">
                        <i class="fas fa-arrow-left"></i> Back to Stock
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction Type</th>
                            <th>Party / Project</th>
                            <th>Reference</th>
                            <th>In (+)</th>
                            <th>Out (-)</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-folder-open" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    No transaction history found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600; color:#475569;"><?= formatDate($txn['date']) ?></span>
                                </td>
                                <td>
                                    <?php if ($txn['type'] === 'purchase'): ?>
                                        <span class="badge-soft green"><i class="fas fa-cart-plus" style="font-size:10px;"></i> Purchase</span>
                                    <?php else: ?>
                                        <span class="badge-soft orange"><i class="fas fa-hammer" style="font-size:10px;"></i> Usage</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($txn['party_name'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span style="color:#64748b; font-family:monospace; font-size:13px;"><?= htmlspecialchars($txn['reference']) ?></span>
                                </td>
                                
                                <td style="color: #059669; font-weight:700;">
                                    <?= $txn['type'] === 'purchase' ? '+ '.number_format($txn['quantity'], 2) : '-' ?>
                                </td>
                                <td style="color: #ef4444; font-weight:700;">
                                    <?= $txn['type'] === 'usage' ? '- '.number_format($txn['quantity'], 2) : '-' ?>
                                </td>
                                
                                <td>
                                    <?= $txn['rate'] > 0 ? formatCurrency($txn['rate']) : '-' ?>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
