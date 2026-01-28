<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin']);

$db = Database::getInstance();
$page_title = 'Audit Trail';
$current_page = 'audit';

/* ================= FILTERS ================= */
$user_filter   = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$action_filter = trim($_GET['action'] ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to'] ?? '');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 100;
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];

if ($user_filter > 0) {
    $where[]  = 'a.user_id = ?';
    $params[] = $user_filter;
}

if ($action_filter !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $action_filter;
}

if ($date_from !== '') {
    $where[]  = 'a.created_at >= ?';
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to !== '') {
    $where[]  = 'a.created_at <= ?';
    $params[] = $date_to . ' 23:59:59';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ================= TOTAL COUNT ================= */
$countStmt = $db->query(
    "SELECT COUNT(*) FROM audit_trail a $where_sql",
    $params
);
$total_rows  = (int)$countStmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

/* ================= FETCH LOGS ================= */
$sql = "
SELECT 
    a.id,
    a.created_at,
    COALESCE(NULLIF(a.action, ''), 'unknown') AS action,
    a.table_name,
    a.record_id,
    a.new_values,
    a.ip_address,
    COALESCE(u.username, 'System') AS username
FROM audit_trail a
LEFT JOIN users u ON u.id = a.user_id
$where_sql
ORDER BY a.created_at DESC, a.id DESC
LIMIT $limit OFFSET $offset
";

$stmt = $db->query($sql, $params);
$logs = $stmt->fetchAll();

/* ================= USERS ================= */
$users = $db->query(
    "SELECT id, full_name FROM users ORDER BY full_name"
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* UI UNCHANGED */
.filter-bar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
    display: block;
    text-transform: uppercase;
}
.json-view {
    background: #1e293b;
    color: #e2e8f0;
    padding: 16px;
    border-radius: 8px;
    font-family: Consolas, monospace;
    font-size: 13px;
    max-height: 400px;
    overflow: auto;
}
/* Center align specific columns (Action, Module, Record ID, Details, IP) */
.modern-table th:nth-child(3), .modern-table td:nth-child(3),
.modern-table th:nth-child(4), .modern-table td:nth-child(4),
.modern-table th:nth-child(5), .modern-table td:nth-child(5),
.modern-table th:nth-child(6), .modern-table td:nth-child(6),
.modern-table th:nth-child(7), .modern-table td:nth-child(7) {
    text-align: center !important;
}

/* Reduce gap by limiting width of Action column */
.modern-table th:nth-child(3), .modern-table td:nth-child(3) {
    width: 120px;
}

</style>

<div class="chart-card-custom" style="height: fit-content;">
    <div class="chart-header-custom" style="padding: 24px;">
        <div class="chart-title-group" style="display:flex;align-items:center;gap:16px;">
            <div class="chart-icon-box purple" style="width:48px;height:48px;">
                <i class="fas fa-history"></i>
            </div>
            <div>
                <h3 style="margin:0;">Audit Trail</h3>
                <div class="chart-subtitle" style="padding-left: 0;">Track system activities and changes</div>
            </div>
        </div>
    </div>

    <div class="card-body" style="padding:0 24px 24px 24px;">

        <!-- STATS GRID -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
            <div class="stat-card-modern" style="padding: 15px;">
                <div class="stat-label-modern">Total Logs</div>
                <div class="stat-value-modern" style="color: #64748b; font-size: 24px;"><?= number_format($total_rows) ?></div>
                <div class="stat-subtext">All time activities</div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-label-modern">Activities Today</div>
                <div class="stat-value-modern" style="color: #3b82f6; font-size: 24px;">
                    <?php 
                        $today_count = $db->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                        echo number_format($today_count);
                    ?>
                </div>
                <div class="stat-subtext">Recorded today</div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-label-modern">Active Users</div>
                <div class="stat-value-modern" style="color: #10b981; font-size: 24px;">
                    <?php 
                        $active_users = $db->query("SELECT COUNT(DISTINCT user_id) FROM audit_trail WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
                        echo number_format($active_users);
                    ?>
                </div>
                <div class="stat-subtext">Last 7 days</div>
            </div>
        </div>

        <!-- FILTERS (RESTORED, UI UNCHANGED) -->
        <form method="GET" class="filter-bar">
            <div class="row" style="align-items:flex-end;">
                <div class="col-3">
                    <label class="filter-label">User</label>
                    <select name="user" class="modern-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-2">
                    <label class="filter-label">Action</label>
                    <select name="action" class="modern-select">
                        <option value="">All</option>
                        <?php foreach (['create','update','delete','bulk_delete','login','logout','unknown'] as $a): ?>
                            <option value="<?= $a ?>" <?= $action_filter === $a ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $a)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-2">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="date_from" class="modern-input" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="col-2">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="date_to" class="modern-input" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="col-3">
                    <button class="modern-btn blue" style="width:70%; height: 42px; align-items: center; justify-content: center">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <!-- TABLE (UNCHANGED UI) -->
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module / Table</th>
                    <th>Record ID</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
                </thead>
                <tbody>

                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding:40px;">
                            No audit logs found
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($logs as $log): ?>
                    <?php
                        $action = $log['action'] ?: 'unknown';
                        $badgeClass =
                            $action === 'login'  ? 'purple' :
                            ($action === 'create' ? 'green' :
                            ($action === 'delete' || $action === 'bulk_delete' ? 'red' :
                            ($action === 'update' ? 'blue' : 'gray')));
                    ?>
                    <tr>
                        <td><?= formatDate($log['created_at'], DATETIME_FORMAT) ?></td>
                        <td><?= htmlspecialchars($log['username']) ?></td>
                        <td>
                            <span class="badge-pill <?= $badgeClass ?>" style="text-transform: capitalize;">
                                <?= htmlspecialchars(str_replace('_', ' ', $action)) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['table_name']) ?></td>
                        <td>
                            <?php if ((int)$log['record_id'] === 0): ?>
                                <span class="badge-pill gray" style="font-size: 11px;">Multiple</span>
                            <?php else: ?>
                                #<?= (int)$log['record_id'] ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['new_values']): ?>
                                <button class="modern-btn small gray"
                                    onclick='showDetails(<?= json_encode(json_decode($log["new_values"], true)) ?>)'>
                                    Payload
                                </button>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <div style="font-size: 13px; color: #64748b;">
                Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $limit, $total_rows) ?></strong> of <strong><?= number_format($total_rows) ?></strong> entries
            </div>
            <div style="display: flex; gap: 8px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&user=<?= $user_filter ?>&action=<?= htmlspecialchars($action_filter) ?>&date_from=<?= htmlspecialchars($date_from) ?>&date_to=<?= htmlspecialchars($date_to) ?>" class="modern-btn gray small">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <button class="modern-btn gray small" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                <?php endif; ?>

                <span style="display: flex; align-items: center; padding: 0 10px; font-weight: 600; color: #475569; font-size: 13px;">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&user=<?= $user_filter ?>&action=<?= htmlspecialchars($action_filter) ?>&date_from=<?= htmlspecialchars($date_from) ?>&date_to=<?= htmlspecialchars($date_to) ?>" class="modern-btn gray small">
                         Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="modern-btn gray small" disabled style="opacity: 0.5; cursor: not-allowed;">
                         Next <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- MODAL (UNCHANGED UI) -->
<div id="detailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 600px; padding: 0; overflow: hidden;">
        <div class="modal-header" style="background: #f8fafc; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Change Details</h2>
            <button onclick="closeModalById('detailsModal')" style="background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <pre id="details_content" class="json-view" style="margin: 0;"></pre>
        </div>
        <div class="modal-footer" style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
            <button class="modern-btn gray" onclick="closeModalById('detailsModal')" style="padding: 10px 24px;">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function showDetails(data){
    const el = document.getElementById('details_content');
    try {
        el.textContent = JSON.stringify(data, null, 2);
    } catch {
        el.textContent = data;
    }
    document.getElementById('detailsModal').style.display = 'flex';
}
function closeModalById(id){
    document.getElementById(id).style.display = 'none';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
