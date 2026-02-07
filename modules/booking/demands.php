<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Payment Demands';
$current_page = 'demands';

// Fetch all projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

$project_id = $_GET['project_id'] ?? '';
$where_clause = "";
$params = [];

if (!empty($project_id)) {
    $where_clause = "WHERE b.project_id = ?";
    $params[] = $project_id;
}

// Fetch Demands
$sql = "SELECT bd.*, b.customer_id, p.name as customer_name, f.flat_no, pr.project_name
        FROM booking_demands bd
        JOIN bookings b ON bd.booking_id = b.id
        JOIN parties p ON b.customer_id = p.id
        JOIN flats f ON b.flat_id = f.id
        JOIN projects pr ON b.project_id = pr.id
        $where_clause
        AND b.status != 'cancelled'
        AND bd.status != 'paid'
        ORDER BY bd.generated_date DESC";

$demands = $db->query($sql, $params)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<div class="booking-details-container">
    <div class="row">
        <div class="col-12">
            <div class="chart-card-custom" style="height: auto;">
                <div class="chart-header-custom" style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="chart-title-group">
                        <h3>
                            <div class="chart-icon-box orange"><i class="fas fa-file-invoice-dollar"></i></div>
                            Payment Demands
                        </h3>
                        <div class="chart-subtitle">Direct requests for payment sent to customers</div>
                    </div>
                    
                    <form method="GET" class="filter-group-pro">
                        <label for="project_filter" class="filter-label"><i class="fas fa-filter"></i> Filter:</label>
                        <select name="project_id" id="project_filter" class="modern-select-pro" onchange="this.form.submit()">
                            <option value="">All Projects</option>
                            <?php foreach($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <style>
                    .filter-group-pro {
                        display: flex;
                        align-items: center;
                        background: #f8fafc;
                        padding: 6px 12px;
                        border-radius: 8px;
                        border: 1px solid #e2e8f0;
                        transition: all 0.2s;
                    }
                    .filter-group-pro:hover {
                        border-color: #cbd5e1;
                        background: #fff;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                    }
                    .filter-label {
                        margin-right: 10px;
                        font-size: 13px;
                        font-weight: 600;
                        color: #64748b;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        white-space: nowrap;
                        margin-bottom: 0;
                    }
                    .modern-select-pro {
                        border: none;
                        background: transparent;
                        padding: 6px 24px 6px 8px;
                        font-size: 14px;
                        font-weight: 600;
                        color: #334155;
                        cursor: pointer;
                        outline: none;
                        min-width: 150px;
                        appearance: none;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: right center;
                        background-size: 14px;
                    }
                    .modern-select-pro:focus {
                        color: #0f172a;
                    }
                </style>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer / Flat</th>
                                <th>Stage Name</th>
                                <th>Amt Demanded</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($demands)): ?>
                                <tr><td colspan="8" class="text-center" style="padding: 40px;">No demands generated yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($demands as $d): 
                                    $balance = $d['demand_amount'] - $d['paid_amount'];
                                ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($d['generated_date'])) ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($d['customer_name']) ?></div>
                                        <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($d['project_name']) ?> - <?= htmlspecialchars($d['flat_no']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge-pill blue"><?= htmlspecialchars($d['stage_name']) ?></span>
                                    </td>
                                    <td><strong style="color: #1e293b;"><?= formatCurrency($d['demand_amount']) ?></strong></td>
                                    <td><span style="color: #10b981;"><?= formatCurrency($d['paid_amount']) ?></span></td>
                                    <td>
                                        <?php if ($balance > 0): ?>
                                            <span style="color: #ef4444; font-weight: 600;"><?= formatCurrency($balance) ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] == 'pending'): ?>
                                            <span class="badge-pill red">Pending</span>
                                        <?php elseif ($d['status'] == 'partial'): ?>
                                            <span class="badge-pill orange">Partial</span>
                                        <?php else: ?>
                                            <span class="badge-pill green">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>modules/booking/print_demand.php?id=<?= $d['id'] ?>" target="_blank" class="action-btn" title="View Demand Letter"><i class="fas fa-print"></i></a>
                                        <a href="<?= BASE_URL ?>modules/booking/view.php?id=<?= $d['booking_id'] ?>" class="action-btn" title="View Booking"><i class="fas fa-eye"></i></a>
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
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
