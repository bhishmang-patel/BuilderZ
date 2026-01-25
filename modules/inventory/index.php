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
$page_title = 'Stock Status';
$current_page = 'stock';

// Fetch stock summary
$sql = "SELECT m.*, 
        (SELECT COALESCE(SUM(ci.quantity), 0) FROM challan_items ci JOIN challans c ON ci.challan_id = c.id WHERE ci.material_id = m.id AND c.status != 'cancelled') as total_in,
        (SELECT COALESCE(SUM(mu.quantity), 0) FROM material_usage mu WHERE mu.material_id = m.id) as total_out
        FROM materials m 
        ORDER BY m.material_name";
$stock_data = $db->query($sql)->fetchAll();

// Calculate Stats
$total_items = count($stock_data);
$total_value = 0;
$total_usage = 0;
$low_stock_count = 0;

foreach ($stock_data as $item) {
    $current_value = $item['current_stock'] * $item['default_rate'];
    $total_value += $current_value;
    $total_usage += $item['total_out'];
    
    if ($item['current_stock'] < 10) { // Assuming 10 is a low stock threshold
        $low_stock_count++;
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

.avatar-square {
    width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #fff; margin-right: 12px; flex-shrink: 0;
}

.badge-soft {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
}
.badge-soft.green { background: #ecfdf5; color: #059669; }
.badge-soft.red { background: #fef2f2; color: #ef4444; }
.badge-soft.gray { background: #f1f5f9; color: #64748b; }

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

.action-btn { 
    width: 32px; height: 32px; 
    border-radius: 8px; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    color: #64748b; 
    background: transparent; 
    border: 1px solid transparent; 
    transition: all 0.2s; 
}
.action-btn:hover { background: #f1f5f9; color: #3b82f6; }

</style>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern">
        <div class="stat-icon bg-violet-light">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="stat-info">
            <h4>Total Items</h4>
            <div class="value"><?= $total_items ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-emerald-light">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h4>Total Value</h4>
            <div class="value"><?= formatCurrencyShort($total_value) ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-orange-light">
             <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h4>Low Stock</h4>
            <div class="value"><?= $low_stock_count ?></div>
        </div>
    </div>
    <div class="stat-card-modern">
        <div class="stat-icon bg-blue-light">
            <i class="fas fa-dolly"></i>
        </div>
        <div class="stat-info">
            <h4>Total Usage</h4>
            <div class="value"><?= number_format($total_usage) ?></div>
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
                        <div class="chart-icon-box blue"><i class="fas fa-boxes"></i></div>
                        Stock Status Report
                    </h3>
                    <div class="chart-subtitle">Real-time inventory levels and valuation</div>
                </div>
                
                <div>
                    <a href="<?= BASE_URL ?>modules/inventory/usage.php" class="modern-btn" style="background: #f59e0b;">
                        <i class="fas fa-minus-circle"></i> Record Usage
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Material Name</th>
                            <th>In (Purchased)</th>
                            <th>Out (Used)</th>
                            <th>Current Stock</th>
                            <th>Valuation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $colors = ['#10b981', '#3b82f6', '#a855f7', '#f59e0b'];
                        $i = 0;
                        foreach ($stock_data as $item): 
                            $color = $colors[$i % 4];
                            $initial = strtoupper(substr($item['material_name'], 0, 1));
                            $i++;
                            
                            $valuation = $item['current_stock'] * $item['default_rate'];
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center;">
                                    <div class="avatar-square" style="background: <?= $color ?>;"><?= $initial ?></div>
                                    <div>
                                        <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($item['material_name']) ?></div>
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                            Unit: <?= strtolower($item['unit']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-soft green">
                                    + <?= number_format($item['total_in'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-soft red">
                                    - <?= number_format($item['total_out'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 15px; font-weight: 700; color: #1e293b;">
                                    <?= number_format($item['current_stock'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #475569;">
                                    <?= formatCurrency($valuation) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>modules/inventory/ledger.php?id=<?= $item['id'] ?>" class="action-btn" title="View Ledger">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
