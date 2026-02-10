<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$page_title = 'All Expenses';

// --- Handling Deletion ---
if (isset($_POST['delete_id'])) {
    $del_id = $_POST['delete_id'];
    try {
        $db->query("DELETE FROM expenses WHERE id = ?", [$del_id]);
        $_SESSION['success'] = "Expense record deleted successfully.";
        header("Location: list.php"); // PRG pattern
        exit;
    } catch (Exception $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// --- Filters ---
$date_from  = $_GET['date_from']  ?? date('Y-m-01');
$date_to    = $_GET['date_to']    ?? date('Y-m-d');
$project_id = $_GET['project_id'] ?? '';
$category_id= $_GET['category_id']?? '';
$payment_method = $_GET['payment_method'] ?? '';

// --- Fetch Data Options ---
$projects   = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$categories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();

// --- Build Query ---
$params = [$date_from, $date_to];
$where = "WHERE e.date BETWEEN ? AND ?";

if (!empty($project_id)) {
    $where .= " AND e.project_id = ?";
    $params[] = $project_id;
}
if (!empty($category_id)) {
    $where .= " AND e.category_id = ?";
    $params[] = $category_id;
}
if (!empty($payment_method)) {
    $where .= " AND e.payment_method = ?";
    $params[] = $payment_method;
}

$sql = "SELECT e.*, ec.name as category_name, p.project_name 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id 
        LEFT JOIN projects p ON e.project_id = p.id 
        $where 
        ORDER BY e.date DESC, e.id DESC";

$expenses = $db->query($sql, $params)->fetchAll();

// Calculate Total
$total_amount = 0;
foreach ($expenses as $exp) {
    $total_amount += $exp['amount'];
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounts.css">
<style>
/* Additional styles for List View */
.filter-bar {
    background: #fff;
    border: 1px solid #e3e6f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

/* Controlled Grid Layout */
.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

/* Labels */
.filter-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #858796;
    margin-bottom: 6px;
    letter-spacing: 0.05em;
}

/* Inputs & Select */
.filter-control {
    width: 100%;
    padding: 10px 12px;
    font-size: 13px;
    border: 1px solid #d1d3e2;
    border-radius: 6px;
    background: #f8f9fc;
    color: #495057;
    height: 42px;
}

.filter-control:focus {
    outline: none;
    border-color: #4e73df;
    background: #fff;
}

/* Date Range Wrapper */
.date-range-wrapper {
    display: flex;
    gap: 8px;
}

.date-range-wrapper .filter-control {
    flex: 1;
}

/* Button */
.filter-btn {
    width: 100%;
    height: 42px;
    padding: 0 20px;
    background: #4e73df;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: #2e59d9;
    transform: translateY(-1px);
}


.total-badge {
    background: #eef2ff;
    color: #4e73df;
    padding: 8px 16px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 14px;
    border: 1px solid #d1d3e2;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}


</style>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800" style="font-weight: 900;">Expense Register</h1>
            <p class="mb-0 text-muted" style="font-weight: 700; font-size: 12px; margin-top: 20px">Detailed list of all expenses</p>
        </div>
        <div>
            <div class="total-badge">
                <span>Total:</span>
                <span style="font-size: 1.1em;"><?= formatCurrency($total_amount) ?></span>
            </div>
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="modern-btn" style="background: #fff; color: #64748b; border: 1px solid #e2e8f0; margin-left: 10px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>modules/accounts/add.php" class="modern-btn" style="margin-left: 10px;">
                <i class="fas fa-plus"></i> New Expense
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <div class="filter-grid">
            <div>
                <label class="filter-label">Date Range</label>
                <div class="date-range-wrapper">
                    <input type="date" name="date_from" class="filter-control" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="date" name="date_to" class="filter-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
            </div>
            <div>
                <label class="filter-label">Project / Site</label>
                <select name="project_id" class="filter-control">
                    <option value="">All Projects</option>
                    <?php foreach($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="filter-label">Category</label>
                <select name="category_id" class="filter-control">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="filter-label">Payment Mode</label>
                <select name="payment_method" class="filter-control">
                    <option value="">All Modes</option>
                    <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="upi"  <?= $payment_method === 'upi' ? 'selected' : '' ?>>UPI</option>
                    <option value="cheque" <?= $payment_method === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                    <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>Card</option>
                </select>
            </div>
            <div>
                <button type="submit" class="filter-btn w-100">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="chart-card-custom">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th><div class="th-inner left">Date</div></th>
                        <th><div class="th-inner left">Project</div></th>
                        <th><div class="th-inner left">Description</div></th>
                        <th><div class="th-inner center">Category</div></th>
                        <th><div class="th-inner right">Amount</div></th>
                        <th><div class="th-inner center">Mode</div></th>
                        <th width="50"><div class="th-inner center">Action</div></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-2x mb-3" style="color:#d1d3e2;"></i>
                                <p>No expenses found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $row): 
                            $cat_initial = strtoupper(substr($row['category_name'] ?: '?', 0, 1));
                            // Assign a stable random color based on category ID
                            $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6610f2', '#fd7e14'];
                            $bg_color = $colors[($row['category_id'] ?? 0) % count($colors)];
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:#4e73df; font-size:13px;">
                                    <?= date('d M Y', strtotime($row['date'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['project_name'])): ?>
                                    <span class="badge-soft blue" style="font-size:11px;">
                                        <i class="fas fa-building" style="margin-right:4px;"></i> <?= htmlspecialchars($row['project_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-soft gray" style="font-size:11px;">Head Office</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:13.5px; color:#1e293b; font-weight:500;">
                                    <?= htmlspecialchars($row['description'] ?: '—') ?>
                                </div>
                                <?php if($row['gst_included']): ?>
                                    <div style="font-size:11px; color:#1cc88a;">
                                        <i class="fas fa-check-circle"></i> GST Paid: <?= formatCurrencyShort($row['gst_amount']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="badge-soft gray">
                                    <?= htmlspecialchars($row['category_name']) ?>
                                </div>
                            </td>
                            <td class="text-right">
                                <span style="font-weight:700; color:#1e293b;">
                                    <?= formatCurrency($row['amount']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span style="font-size:12px; text-transform:uppercase; color:#858796; font-weight:600;">
                                    <?= $row['payment_method'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center" style="gap: 5px;">
                                    <a href="add.php?id=<?= $row['id'] ?>" class="action-btn" title="Edit Expense">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirmAction(event, 'Are you sure you want to delete this expense?', 'Yes, Delete It');" style="margin:0;">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="action-btn delete-btn" title="Delete Expense">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
