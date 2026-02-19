<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Delivery Challans';
$current_page = 'material_challan';

// Handle operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/vendors/challans/material.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_challan' && $_SESSION['user_role'] === 'admin') {
        $challan_id = intval($_POST['challan_id']);
        $challan = $db->query("SELECT * FROM challans WHERE id = ?", [$challan_id])->fetch();
        if ($challan && $challan['status'] === 'pending') {
            try {
                $db->beginTransaction();
                if (empty($challan['party_id']) && !empty($challan['temp_vendor_data'])) {
                    $tv = json_decode($challan['temp_vendor_data'], true);
                    $existing = $db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='vendor'", [trim($tv['name'])])->fetch();
                    if ($existing) {
                        $party_id = $existing['id'];
                    } else {
                        // Check if GST number is already taken by another vendor
                        if (!empty($tv['gst_number'])) {
                            $gstOwner = $db->query("SELECT id, name FROM parties WHERE gst_number = ? AND party_type='vendor'", [$tv['gst_number']])->fetch();
                            if ($gstOwner) {
                                throw new Exception("Cannot approve: GST '{$tv['gst_number']}' is already used by vendor '{$gstOwner['name']}'. Please edit the challan and selecting the existing vendor.");
                            }
                        }

                        $vendor_data = [
                            'party_type' => 'vendor', 'name' => $tv['name'], 'mobile' => $tv['mobile'],
                            'address' => $tv['address'], 'gst_number' => $tv['gst_number'],
                            'gst_status' => !empty($tv['gst_number']) ? 'registered' : 'unregistered',
                            'email' => $tv['email'], 'status' => 'active'
                        ];
                        $party_id = $db->insert('parties', $vendor_data);
                    }
                    $db->update('challans', ['party_id' => $party_id, 'temp_vendor_data' => null], 'id=?', ['id' => $challan_id]);
                }
                $update_data = ['status' => 'approved', 'approved_by' => $_SESSION['user_id'], 'approved_at' => date('Y-m-d H:i:s')];
                $db->update('challans', $update_data, 'id = ?', ['id' => $challan_id]);
                $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$challan_id])->fetchAll();
                foreach ($items as $item) { updateMaterialStock($item['material_id'], $item['quantity'], true); }
                if (!empty($challan['party_id'])) {
                    $db->update('parties', ['status' => 'active'], "id = ? AND status = 'pending'", ['id' => $challan['party_id']]);
                }
                logAudit('approve', 'challans', $challan_id);
                $db->commit();
                setFlashMessage('success', 'Challan approved successfully');
            } catch (Exception $e) {
                $db->rollback();
                setFlashMessage('error', 'Approval failed: ' . $e->getMessage());
            }
        } else {
            setFlashMessage('warning', 'Challan already approved or invalid.');
        }
        redirect('modules/vendors/challans/material.php');
    }

    if ($action === 'delete_challan' && $_SESSION['user_role'] === 'admin') {
        try {
            $challan_id = intval($_POST['challan_id']);
            $db->beginTransaction();
            $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$challan_id])->fetchAll();
            foreach ($items as $item) { updateMaterialStock($item['material_id'], $item['quantity'], false); }
            $db->delete('challan_items', 'challan_id = ?', [$challan_id]);
            $db->delete('challans', 'id = ?', [$challan_id]);
            logAudit('delete', 'challans', $challan_id);
            $db->commit();
            setFlashMessage('success', 'Challan deleted successfully');
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Error deleting challan: ' . $e->getMessage());
        }
        redirect('modules/vendors/challans/material.php');
    }

    if ($action === 'bulk_delete_challans' && $_SESSION['user_role'] === 'admin') {
        try {
            $ids = json_decode($_POST['ids'], true);
            if (empty($ids)) throw new Exception("No challans selected");
            $db->beginTransaction();
            $count = 0; $deleted_ids = [];
            foreach ($ids as $id) {
                $challan = $db->query("SELECT status FROM challans WHERE id = ?", [$id])->fetch();
                if ($challan && $challan['status'] === 'pending') {
                    $items = $db->query("SELECT * FROM challan_items WHERE challan_id = ?", [$id])->fetchAll();
                    foreach ($items as $item) { updateMaterialStock($item['material_id'], $item['quantity'], false); }
                    $db->delete('challan_items', 'challan_id = ?', [$id]);
                    $db->delete('challans', 'id = ?', [$id]);
                    $deleted_ids[] = $id; $count++;
                }
            }
            if (!empty($deleted_ids)) logAudit('bulk_delete', 'challans', 0, null, ['deleted_ids' => $deleted_ids]);
            $db->commit();
            setFlashMessage('success', "$count challans deleted successfully");
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Error deleting challans: ' . $e->getMessage());
        }
        redirect('modules/vendors/challans/material.php');
    }
}

// Fetch challans with filters
$vendor_filter  = $_GET['vendor']  ?? '';
$project_filter = $_GET['project'] ?? '';
$status_filter  = $_GET['status']  ?? '';

$where = "c.challan_type = 'material'";
$params = [];
if ($vendor_filter)  { $where .= ' AND c.party_id = ?';    $params[] = $vendor_filter; }
if ($project_filter) { $where .= ' AND c.project_id = ?';  $params[] = $project_filter; }
if ($status_filter)  { $where .= ' AND c.status = ?';      $params[] = $status_filter; }

$sql = "SELECT c.*, 
           p.name as vendor_name, pr.project_name,
           u.full_name as created_by_name,
           (SELECT GROUP_CONCAT(DISTINCT m.material_name SEPARATOR ', ') 
            FROM challan_items ci JOIN materials m ON ci.material_id = m.id 
            WHERE ci.challan_id = c.id) as material_names,
           (SELECT COALESCE(SUM(quantity), 0) FROM challan_items ci WHERE ci.challan_id = c.id) as total_quantity,
           p.address as vendor_address
        FROM challans c
        LEFT JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE $where ORDER BY c.created_at DESC";

$challans = $db->query($sql, $params)->fetchAll();

// Compute stats
$total = count($challans);
$pending_count  = count(array_filter($challans, fn($c) => $c['status'] === 'pending'));
$approved_count = count(array_filter($challans, fn($c) => $c['status'] === 'approved'));
$paid_count     = count(array_filter($challans, fn($c) => $c['status'] === 'paid'));

$vendors  = $db->query("SELECT id, name FROM parties WHERE party_type = 'vendor' ORDER BY name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

    /* ── Wrapper ─────────────────────────────── */
    .ch-index-wrap { max-width: 1200px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Page Header ─────────────────────────── */
    .ch-page-header {
        display: flex; align-items: flex-end; justify-content: space-between;
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        gap: 1rem; flex-wrap: wrap;
    }
    .ch-page-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .ch-page-header h1 {
        font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .ch-page-header h1 em { color: var(--accent); font-style: italic; }

    .header-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }

    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s ease, transform 0.15s ease, box-shadow 0.18s ease;
        white-space: nowrap; border: 1.5px solid var(--ink);
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.28); color: white; }

    .btn-filter-toggle {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1rem; border: 1.5px solid var(--border); background: white;
        color: var(--ink-soft); border-radius: 8px; font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.18s ease;
    }
    .btn-filter-toggle:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .btn-filter-toggle.active { background: var(--accent-lt); border-color: var(--accent); color: var(--accent); }

    .btn-bulk-del {
        display: none; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1rem; background: #fee2e2; border: 1.5px solid #fca5a5;
        color: #b91c1c; border-radius: 8px; font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: all 0.18s ease;
    }
    .btn-bulk-del:hover { background: #ef4444; border-color: #ef4444; color: white; }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.1rem 1.3rem;
        display: flex; align-items: center; gap: 0.9rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        animation: fadeUp 0.4s ease both;
    }
    .stat-card:nth-child(1) { animation-delay:.05s }
    .stat-card:nth-child(2) { animation-delay:.1s }
    .stat-card:nth-child(3) { animation-delay:.15s }
    .stat-card:nth-child(4) { animation-delay:.2s }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-pip {
        width: 42px; height: 42px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .pip-total    { background: #eef2ff; color: #4f63d2; }
    .pip-pending  { background: var(--accent-lt); color: var(--accent); }
    .pip-approved { background: #ecfdf5; color: #059669; }
    .pip-paid     { background: #eff6ff; color: #1d4ed8; }

    .stat-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.15rem; }
    .stat-value { font-family: 'Fraunces', serif; font-size: 1.55rem; font-weight: 700; color: var(--ink); line-height: 1; text-align: center; }

    /* ── Main Panel ──────────────────────────── */
    .ch-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    /* ── Toolbar ─────────────────────────────── */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa; overflow: hidden;
    }
    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: var(--accent); border-radius: 7px;
        display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-div  { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    .filter-form { display: flex; align-items: center; justify-content: right; gap: 0.5rem; flex: 1; min-width: 0; flex-wrap: nowrap; }

    .f-select {
        height: 34px; padding: 0 1.75rem 0 0.75rem; border: 1.5px solid var(--border);
        border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 0.8rem;
        color: var(--ink); background: var(--surface); outline: none;
        transition: border-color 0.15s; -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center;
        flex: 0 0 140px; min-width: 0;
    }
    .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); }

    .btn-go, .btn-clear {
        width: 34px; height: 34px; border: none; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 0.75rem; transition: all 0.18s;
        flex-shrink: 0; text-decoration: none;
    }
    .btn-go    { background: var(--ink); color: white; }
    .btn-go:hover { background: var(--accent); }
    .btn-clear { background: #fee2e2; color: #b91c1c; }
    .btn-clear:hover { background: #fca5a5; }

    @media (max-width: 820px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div   { display: none; }
        .filter-form   { width: 100%; flex-wrap: wrap; }
        .f-select      { flex: 1 1 130px; }
    }

    /* ── Table ───────────────────────────────── */
    .ch-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

    .ch-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .ch-table thead th {
        padding: 0.75rem 1rem; text-align: left;
        font-size: 0.66rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .ch-table thead th.th-c { text-align: center; }
    .ch-table thead th.th-r { text-align: right; }
    .ch-table thead th.th-check { width: 44px; text-align: center; }

    .ch-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .ch-table tbody tr:last-child { border-bottom: none; }
    .ch-table tbody tr:hover { background: #fdfcfa; }

    .ch-table td { padding: 0.85rem 1rem; vertical-align: middle; }
    .ch-table td.td-c { text-align: center; }
    .ch-table td.td-r { text-align: right; }

    /* Challan no */
    .ch-num { font-weight: 700; color: var(--ink); font-size: 0.875rem; }

    /* Date */
    .ch-date { font-size: 0.8rem; color: var(--ink-soft); }

    /* Vendor cell */
    .vendor-cell { display: flex; align-items: center; gap: 0.55rem; }
    .v-avatar {
        width: 26px; height: 26px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.65rem; font-weight: 700; color: white; flex-shrink: 0;
    }
    .v-name { font-weight: 600; font-size: 0.85rem; color: var(--ink); }

    /* Materials */
    .mat-snippet {
        font-size: 0.78rem; color: var(--ink-soft);
        display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
        max-width: 200px;
    }

    /* Project pill */
    .proj-pill {
        display: inline-flex; align-items: center;
        padding: 0.22rem 0.65rem; border-radius: 20px;
        font-size: 0.72rem; font-weight: 700; color: white;
        white-space: nowrap;
    }

    /* Quantity */
    .ch-qty { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

    /* Status pills */
    .status-pill {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.26rem 0.7rem; border-radius: 20px;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; white-space: nowrap;
    }
    .status-pill::before {
        content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0;
    }
    .pill-pending  { background: var(--accent-lt); color: #a04d1e; }
    .pill-pending::before  { background: var(--accent); }
    .pill-approved { background: #ecfdf5; color: #065f46; }
    .pill-approved::before { background: #10b981; }
    .pill-paid     { background: #eff6ff; color: #1e40af; }
    .pill-paid::before     { background: #3b82f6; }
    .pill-partial  { background: #fefce8; color: #854d0e; }
    .pill-partial::before  { background: #eab308; }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.edit:hover   { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }
    .act-btn.approve:hover{ border-color: #059669; color: #059669; background: #ecfdf5; }
    .act-btn.del:hover    { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

    /* Lock icon */
    .lock-icon { color: var(--border); font-size: 0.75rem; }

    /* Checkbox */
    .ch-checkbox {
        width: 15px; height: 15px; cursor: pointer; accent-color: var(--accent);
    }

    /* Empty state */
    .empty-state { padding: 4rem 1rem; text-align: center; }
    .empty-state .ei { font-size: 2.5rem; color: var(--border); margin-bottom: 0.75rem; display: block; }
    .empty-state p { color: var(--ink-mute); font-size: 0.875rem; margin: 0 0 0.75rem; }
    .empty-state a { font-size: 0.82rem; font-weight: 600; color: var(--accent); text-decoration: none; border-bottom: 1.5px solid var(--accent); }

    /* ── Modals ──────────────────────────────── */
    .po-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .po-modal-backdrop.open { display: flex; }

    .po-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.2rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.05rem;
        font-weight: 600; color: var(--ink); margin: 0;
    }
    .modal-close {
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        border: none; background: none; font-size: 1.2rem; color: var(--ink-mute);
        cursor: pointer; border-radius: 6px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--cream); color: var(--ink); }

    .modal-icon-ring {
        width: 56px; height: 56px; border-radius: 50%; border: 2px solid;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1rem; font-size: 1.3rem;
    }
    .ring-green { border-color: #10b981; background: #ecfdf5; color: #059669; }
    .ring-red   { border-color: #ef4444; background: #fef2f2; color: #dc2626; }

    .modal-body { padding: 2rem 1.5rem; text-align: center; }
    .modal-body p { font-size: 0.875rem; color: var(--ink-soft); line-height: 1.65; margin: 0 0 1.5rem; }

    .modal-footer {
        display: flex; gap: 0.75rem; justify-content: center;
        padding: 0 1.5rem 1.5rem;
    }

    .mbtn {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1.4rem; border-radius: 8px;
        font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
        cursor: pointer; border: 1.5px solid; transition: all 0.18s;
    }
    .mbtn-cancel { background: white; border-color: var(--border); color: var(--ink-soft); }
    .mbtn-cancel:hover { border-color: var(--ink); color: var(--ink); }
    .mbtn-approve{ background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
    .mbtn-approve:hover { background: #10b981; border-color: #10b981; color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }
    .mbtn-delete { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .mbtn-delete:hover { background: #ef4444; border-color: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }

    /* Details modal */
    .details-modal-body { padding: 1.5rem; min-height: 120px; }

    /* ── Animations ──────────────────────────── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="ch-index-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="ch-page-header">
        <div>
            <div class="eyebrow">Challans Module</div>
            <h1>Delivery <em>Challans</em></h1>
        </div>
        <div class="header-actions">
            <!-- Bulk delete button (shown when items selected) -->
            <button class="btn-bulk-del" id="bulkDeleteBtn" onclick="confirmBulkDeleteChallans()">
                <i class="fas fa-trash-alt"></i> Delete (<span id="selectedCount">0</span>)
            </button>
            <a href="create.php" class="btn-new"><i class="fas fa-plus"></i> New Challan</a>
        </div>
    </div>

    <!-- ── Stats ────────────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-pip pip-total"><i class="fas fa-file-invoice"></i></div>
            <div><div class="stat-label">Total</div><div class="stat-value"><?= $total ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-pending"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-label">Pending</div><div class="stat-value"><?= $pending_count ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-approved"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-label">Approved</div><div class="stat-value"><?= $approved_count ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-pip pip-paid"><i class="fas fa-receipt"></i></div>
            <div><div class="stat-label">Paid</div><div class="stat-value"><?= $paid_count ?></div></div>
        </div>
    </div>

    <!-- ── Main Panel ────────────────────────── -->
    <div class="ch-panel">

        <!-- Toolbar with inline filters -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-truck"></i></div>
                <div class="toolbar-title">All Challans</div>
            </div>
            <div class="toolbar-div"></div>

            <form method="GET" class="filter-form">
                <select name="vendor" class="f-select">
                    <option value="">All Vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $vendor_filter == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="project" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $project_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="f-select" style="flex:0 0 120px">
                    <option value="">All Status</option>
                    <option value="pending"  <?= $status_filter === 'pending'  ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="partial"  <?= $status_filter === 'partial'  ? 'selected' : '' ?>>Partial</option>
                    <option value="paid"     <?= $status_filter === 'paid'     ? 'selected' : '' ?>>Paid</option>
                </select>

                <button type="submit" class="btn-go" title="Apply filters"><i class="fas fa-search"></i></button>

                <?php if ($vendor_filter || $project_filter || $status_filter): ?>
                    <a href="material.php" class="btn-clear" title="Clear filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="ch-table">
                <thead>
                    <tr>
                        <th class="th-check">
                            <input type="checkbox" class="ch-checkbox" id="selectAll" onclick="toggleAll(this)">
                        </th>
                        <th>Challan No</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Materials</th>
                        <th>Project</th>
                        <th class="th-r">Qty</th>
                        <th class="th-c">Status</th>
                        <th class="th-r">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($challans)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <span class="ei"><i class="fas fa-folder-open"></i></span>
                                    <p>No challans found<?= ($vendor_filter || $project_filter || $status_filter) ? ' matching your filters' : '' ?>.</p>
                                    <?php if ($vendor_filter || $project_filter || $status_filter): ?>
                                        <a href="material.php">Clear filters</a>
                                    <?php else: ?>
                                        <a href="create.php">Create your first challan</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($challans as $challan):
                            $vendorName = $challan['vendor_name'];
                            if (empty($vendorName) && !empty($challan['temp_vendor_data'])) {
                                $tv = json_decode($challan['temp_vendor_data'], true);
                                $vendorName = ($tv['name'] ?? '?') . ' (Draft)';
                            }
                            $initial = strtoupper(substr($vendorName ?? '?', 0, 1));
                            $vendorColor = ColorHelper::getCustomerColor($vendorName);
                            $projColor   = ColorHelper::getProjectColor($challan['project_id']);

                            $pillClass = match($challan['status']) {
                                'pending'  => 'pill-pending',
                                'approved' => 'pill-approved',
                                'paid'     => 'pill-paid',
                                'partial'  => 'pill-partial',
                                default    => 'pill-pending'
                            };
                        ?>
                        <tr>
                            <td class="td-c">
                                <?php if ($challan['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                                    <input type="checkbox" class="ch-checkbox row-check" value="<?= $challan['id'] ?>" onchange="updateBulkState()">
                                <?php else: ?>
                                    <i class="fas fa-lock lock-icon"></i>
                                <?php endif; ?>
                            </td>
                            <td><span class="ch-num"><?= htmlspecialchars($challan['challan_no']) ?></span></td>
                            <td><span class="ch-date"><?= formatDate($challan['challan_date']) ?></span></td>
                            <td>
                                <div class="vendor-cell">
                                    <span class="v-name"><?= htmlspecialchars($vendorName) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="mat-snippet" title="<?= htmlspecialchars($challan['material_names']) ?>">
                                    <?= htmlspecialchars($challan['material_names'] ?: '—') ?>
                                </span>
                            </td>
                            <td>
                                <?= renderProjectBadge($challan['project_name'], $challan['project_id']) ?>
                            </td>
                            <td class="td-r">
                                <span class="ch-qty"><?= number_format($challan['total_quantity'], 2) ?></span>
                            </td>
                            <td class="td-c">
                                <span class="status-pill <?= $pillClass ?>"><?= ucfirst($challan['status']) ?></span>
                            </td>
                            <td class="td-r">
                                <div class="act-group">
                                    <button class="act-btn" onclick="viewDetails(<?= $challan['id'] ?>)" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="edit.php?id=<?= $challan['id'] ?>" class="act-btn edit" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <?php if ($challan['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                                        <button class="act-btn approve"
                                            onclick="openApprove(<?= $challan['id'] ?>, '<?= htmlspecialchars($challan['challan_no']) ?>')"
                                            title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="act-btn del"
                                            onclick="openDelete(<?= $challan['id'] ?>, '<?= htmlspecialchars($challan['challan_no']) ?>')"
                                            title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /.ch-panel -->
</div><!-- /.ch-index-wrap -->


<!-- ══════════ MODALS ══════════ -->

<!-- Details Modal -->
<div class="po-modal-backdrop" id="detailsModal">
    <div class="po-modal" style="max-width:680px">
        <div class="modal-head">
            <h3>Challan Details</h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">×</button>
        </div>
        <div class="details-modal-body" id="details_content">
            <div style="text-align:center;padding:2rem;color:var(--ink-mute)">
                <i class="fas fa-spinner fa-spin"></i> Loading…
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="po-modal-backdrop" id="approveModal">
    <div class="po-modal" style="max-width:460px">
        <div class="modal-head">
            <h3>Confirm Approval</h3>
            <button class="modal-close" onclick="closeModal('approveModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon-ring ring-green"><i class="fas fa-check"></i></div>
            <p>
                Approve challan <strong id="approve_no" style="color:var(--ink)"></strong>?<br>
                This will mark it as approved and update stock accordingly.
            </p>
        </div>
        <form method="POST" id="approveForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve_challan">
            <input type="hidden" name="challan_id" id="approve_id">
            <div class="modal-footer">
                <button type="button" class="mbtn mbtn-cancel" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="mbtn mbtn-approve"><i class="fas fa-check"></i> Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="po-modal-backdrop" id="deleteModal">
    <div class="po-modal" style="max-width:460px">
        <div class="modal-head">
            <h3>Delete Challan</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon-ring ring-red"><i class="fas fa-trash-alt"></i></div>
            <p>
                Delete challan <strong id="delete_no" style="color:var(--ink)"></strong>?<br>
                This will permanently remove the record and <strong>revert stock</strong>.
            </p>
        </div>
        <form method="POST" id="deleteForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_challan">
            <input type="hidden" name="challan_id" id="delete_id">
            <div class="modal-footer">
                <button type="button" class="mbtn mbtn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="mbtn mbtn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk delete hidden form -->
<form method="POST" id="bulkForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete_challans">
    <input type="hidden" name="ids" id="bulkIds">
</form>

<script>
/* ── Modal helpers ────────────────────────────────── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.po-modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});

/* ── View Details ─────────────────────────────────── */
function viewDetails(id) {
    document.getElementById('details_content').innerHTML =
        '<div style="text-align:center;padding:2rem;color:var(--ink-mute)"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    openModal('detailsModal');
    fetch('<?= BASE_URL ?>modules/vendors/challans/get_challan_details.php?id=' + id + '&t=' + Date.now())
        .then(r => r.text())
        .then(html => { document.getElementById('details_content').innerHTML = html; });
}

/* ── Approve / Delete ─────────────────────────────── */
function openApprove(id, no) {
    document.getElementById('approve_id').value = id;
    document.getElementById('approve_no').textContent = no;
    openModal('approveModal');
}

function openDelete(id, no) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_no').textContent = no;
    openModal('deleteModal');
}

/* ── Bulk select ──────────────────────────────────── */
function toggleAll(src) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = src.checked);
    updateBulkState();
}

function updateBulkState() {
    const checked = document.querySelectorAll('.row-check:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    document.getElementById('selectedCount').textContent = checked.length;
    btn.style.display = checked.length > 0 ? 'inline-flex' : 'none';
    document.getElementById('selectAll').indeterminate =
        checked.length > 0 && checked.length < document.querySelectorAll('.row-check').length;
}

function confirmBulkDeleteChallans() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (!checked.length) return;
    const ids = Array.from(checked).map(cb => cb.value);
    if (confirm(`Delete ${ids.length} selected challan(s)? Stock will be reverted.`)) {
        document.getElementById('bulkIds').value = JSON.stringify(ids);
        document.getElementById('bulkForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>