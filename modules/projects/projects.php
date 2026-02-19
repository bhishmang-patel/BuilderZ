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
$masterService = new MasterService();
$page_title = 'Projects';
$current_page = 'projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF Token verification failed');
    }
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
    redirect('modules/projects/projects.php');
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'location' => $_GET['location'] ?? ''
];
$projects = $masterService->getAllProjects($filters);
$all_locations = $masterService->getDistinctLocations();

$sow_sql = "SELECT id, name FROM stage_of_work WHERE status = 'active' ORDER BY name";
$stage_templates = $db->query($sow_sql)->fetchAll();

include __DIR__ . '/../../includes/header.php';
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
    --accent:    #2a58b5;
    --accent-bg: #fdf8f3;
}

body {
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
}

.page-wrap {
    max-width: 1200px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ════════════════════════════════════════
   ENTRANCE ANIMATIONS
   ════════════════════════════════════════ */
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes rowSlide {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
    opacity: 0;
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

.filter-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.12s forwards;
}

.main-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.20s forwards;
}

.data-table tbody tr.row-anim {
    opacity: 0;
    transform: translateX(-10px);
    animation: rowSlide 0.34s cubic-bezier(0.22,1,0.36,1) forwards;
}

/* ── Page Header ─────────────────────── */
.eyebrow {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.15em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem; font-weight: 700;
    line-height: 1.1; color: var(--ink); margin: 0;
}
.page-header h1 em { color: var(--accent); font-style: italic; }

.header-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

.btn-filter-toggle {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.55rem 1rem;
    font-size: 0.82rem; font-weight: 500;
    color: var(--ink-soft);
    border: 1.5px solid var(--border);
    border-radius: 6px; background: white;
    cursor: pointer; transition: all 0.18s ease;
    font-family: 'DM Sans', sans-serif;
}
.btn-filter-toggle:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.btn-add {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.62rem 1.4rem;
    background: var(--accent); color: white;
    border: none; border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s ease;
    white-space: nowrap;
}
.btn-add:hover { background: #9e521f; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.32); }
.btn-add:active { transform: translateY(0); }

/* ── Filter Card ─────────────────────── */
.filter-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.9rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.filter-head-icon {
    width: 26px; height: 26px; border-radius: 5px;
    background: #ede9fe; color: #7c3aed;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; flex-shrink: 0;
}
.filter-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.9rem; font-weight: 600; color: var(--ink); margin: 0;
}
.filter-body { padding: 1.1rem 1.5rem; }

.filter-row {
    display: grid;
    grid-template-columns: minmax(220px, 320px) auto auto auto auto;
    gap: 0.6rem;
    align-items: center;
}
@media (max-width: 700px) { .filter-row { grid-template-columns: 1fr; } }

.search-wrap { position: relative; flex: 1; }
.search-wrap .icon {
    position: absolute; left: 0.75rem; top: 50%;
    transform: translateY(-50%); 
    color: var(--ink-mute); font-size: 0.72rem; pointer-events: none;
}

.f-ctrl {
    height: 38px;
    padding: 0 0.85rem;
    border: 1.5px solid var(--border);
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s;
    -webkit-appearance: none; appearance: none;
}
.f-ctrl:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); background: white; }
.search-wrap input.f-ctrl { padding-left: 2.1rem; width: 100%; }
select.f-ctrl {
    padding-right: 2rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.7rem center;
}

.btn-apply {
    height: 38px;
    padding: 0 1.25rem;
    background: var(--accent); color: white; border: none;
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: background 0.18s;
    white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 0.35rem;
}
.btn-apply:hover { background: #9e521f; }

.btn-reset {
    height: 38px;
    padding: 0 1.25rem;
    background: white; color: var(--ink-soft);
    border: 1.5px solid var(--border);
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem; font-weight: 500;
    text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 0.35rem;
    transition: all 0.18s;
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Card head ───────────────────────── */
.card-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.1rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa;
}
.card-icon {
    width: 30px; height: 30px; border-radius: 7px;
    background: #fdf8f3; color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
    border: 1px solid #e0c9b5;
}
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0;
}
.count-tag {
    margin-left: auto;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.65rem; border-radius: 20px;
}

/* ── Table ───────────────────────────── */
.table-wrap { overflow-x: auto; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table thead tr { background: #f5f1eb; border-bottom: 1.5px solid var(--border); }
.data-table thead th {
    padding: 0.75rem 1.1rem; text-align: left;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.data-table thead th.center { text-align: center; }
.data-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #fdf8f3; }
.data-table td { padding: 0.95rem 1.1rem; vertical-align: middle; color: var(--ink-soft); }
.data-table td.center { text-align: center; }

/* ── Project name cell ───────────────── */
.proj-name-cell { display: flex; align-items: center; gap: 0.75rem; }
.av-sq {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 800; color: white; flex-shrink: 0;
    text-transform: uppercase;
}
.proj-name { font-weight: 700; color: var(--ink); font-size: 0.9rem; }

/* ── Location cell ───────────────────── */
.loc-cell { font-size: 0.82rem; color: var(--ink-soft); display: flex; align-items: center; gap: 0.35rem; }
.loc-cell i { font-size: 0.65rem; color: var(--ink-mute); }

/* ── Timeline cell ───────────────────── */
.timeline-cell { font-size: 0.78rem; color: var(--ink-soft); line-height: 1.8; }
.timeline-cell strong { color: var(--ink); }

/* ── Pills ───────────────────────────── */
.pill {
    display: inline-block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.04em;
    padding: 0.22rem 0.65rem; border-radius: 20px;
}
.pill.purple { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
.pill.gray   { background: var(--cream); color: var(--ink-soft); border: 1px solid var(--border); }
.pill.active    { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.pill.completed { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.pill.on_hold   { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* ── Action buttons ──────────────────── */
.act-wrap { display: flex; align-items: center; justify-content: center; gap: 0.35rem; }
.act-btn {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 0.72rem;
    border: 1.5px solid var(--border);
    background: white; color: var(--ink-mute);
    cursor: pointer; transition: all 0.16s;
}
.act-btn.view:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
.act-btn.edit:hover { border-color: #059669; color: #059669; background: #f0fdf4; }
.act-btn.del:hover  { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

/* ── Empty state ─────────────────────── */
.empty-state { text-align: center; padding: 5rem 2rem; color: var(--ink-mute); }
.empty-state .ei { font-size: 2.8rem; opacity: 0.2; margin-bottom: 1rem; display: block; color: var(--accent); }
.empty-state .et { font-family: 'Fraunces', serif; font-size: 1.1rem; color: var(--ink-soft); margin-bottom: 0.4rem; }
.empty-state .es { font-size: 0.875rem; }

/* ════════════════════════════════════════
   MODALS
   ════════════════════════════════════════ */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(26,23,20,0.5);
    backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
    z-index: 9000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    opacity: 0; pointer-events: none;
    transition: opacity 0.25s ease;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal-box {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(26,23,20,0.2);
    width: 100%; max-width: 640px;
    max-height: 92vh; display: flex; flex-direction: column; overflow: hidden;
    transform: scale(0.95) translateY(12px);
    transition: transform 0.32s cubic-bezier(0.22,1,0.36,1);
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-box.sm { max-width: 460px; }

.modal-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.3rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fdfcfa; flex-shrink: 0;
}
.modal-head-icon {
    width: 36px; height: 36px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; flex-shrink: 0;
}
.modal-head-icon.orange { background: #fdf8f3; color: var(--accent); border: 1px solid #e0c9b5; }
.modal-head-icon.green  { background: #d1fae5; color: #059669; }
.modal-head-icon.blue   { background: #dbeafe; color: #2563eb; }
.modal-head-icon.red    { background: #fee2e2; color: #dc2626; }

.modal-title { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 700; color: var(--ink); margin: 0; flex: 1; }
.modal-subtitle { font-size: 0.72rem; color: var(--ink-mute); margin-top: 2px; }

.modal-close {
    width: 28px; height: 28px; border-radius: 6px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-soft);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem; transition: all 0.16s; flex-shrink: 0;
}
.modal-close:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

.modal-body { padding: 1.6rem; overflow-y: auto; flex: 1; }

.modal-box form {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

/* Form section */
.fsec {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.85rem;
    padding-bottom: 0.45rem; border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.4rem;
}

.fg { margin-bottom: 1rem; }
.fg:last-child { margin-bottom: 0; }
.fg label {
    display: block; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.4rem;
}

.f-input {
    width: 100%; height: 40px; padding: 0 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem;
    color: var(--ink); background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.f-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); background: white; }
.f-input.is-select {
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.8rem center;
    padding-right: 2.2rem;
}

.f-hint { font-size: 0.7rem; color: var(--ink-mute); margin-top: 0.3rem; }

.fgrid   { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.fgrid3  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem; }
.fspan   { grid-column: 1 / -1; }

/* Toggle row */
.toggle-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #fdfcfa;
    border: 1.5px solid var(--border-lt);
    border-radius: 8px; margin-bottom: 1rem;
}
.toggle-label { font-weight: 600; color: var(--ink); font-size: 0.875rem; flex: 1; }
.toggle-sub   { font-size: 0.72rem; color: var(--ink-mute); }

/* Custom checkbox toggle */
.tog-input { display: none; }
.tog-track {
    width: 36px; height: 20px; border-radius: 20px;
    background: var(--border); cursor: pointer;
    transition: background 0.2s; position: relative; flex-shrink: 0;
}
.tog-track::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 14px; height: 14px;
    border-radius: 50%; background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.tog-input:checked + .tog-track { background: var(--accent); }
.tog-input:checked + .tog-track::after { transform: translateX(16px); }

/* Modal footer */
.modal-foot {
    padding: 1.1rem 1.6rem;
    border-top: 1.5px solid var(--border-lt);
    background: #fdfcfa;
    display: flex; justify-content: flex-end; gap: 0.75rem;
    flex-shrink: 0;
}
.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.62rem 1.25rem;
    background: white; color: var(--ink-soft);
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 500;
    cursor: pointer; transition: all 0.18s;
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.btn-submit {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.62rem 1.5rem;
    background: var(--accent); color: white;
    border: 1.5px solid var(--accent); border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
}
.btn-submit:hover { background: #9e521f; border-color: #9e521f; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.3); }
.btn-submit:active { transform: translateY(0); }
.btn-submit.danger { background: #dc2626; border-color: #dc2626; }
.btn-submit.danger:hover { background: #b91c1c; border-color: #b91c1c; box-shadow: 0 4px 14px rgba(220,38,38,0.3); }

/* View modal content */
.view-stat-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 1px; background: var(--border);
    border: 1.5px solid var(--border); border-radius: 10px;
    overflow: hidden; margin-bottom: 1.25rem;
}
.view-stat {
    background: #fdfcfa; padding: 1rem 1.25rem; text-align: center;
}
.view-stat-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.35rem; }
.view-stat-val { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); }
.view-stat-val.red { color: #dc2626; }

.view-info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 0; border-bottom: 1px solid var(--border-lt);
    gap: 1rem;
}
.view-info-row:last-child { border-bottom: none; }
.view-info-label { font-size: 0.78rem; color: var(--ink-soft); }
.view-info-value { font-size: 0.875rem; font-weight: 600; color: var(--ink); }

/* Delete confirm */
.del-confirm { text-align: center; padding: 0.5rem 0; }
.del-icon { width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.1rem; font-size: 1.5rem; color: #dc2626; }
.del-title { font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 700; color: var(--ink); margin-bottom: 0.4rem; }
.del-sub   { font-size: 0.875rem; color: var(--ink-soft); line-height: 1.55; }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Masters</div>
            <h1>Construction <em>Projects</em></h1>
        </div>
        <div class="header-right">
            <button class="btn-filter-toggle" id="toggleFilter">
                <i class="fas fa-sliders-h"></i> Filters
                <?php if ($filters['search'] || $filters['status'] || $filters['location']): ?>
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--accent);display:inline-block;margin-left:2px;"></span>
                <?php endif; ?>
            </button>
            <button class="btn-add" id="openAddBtn">
                <i class="fas fa-plus"></i> New Project
            </button>
        </div>
    </div>

    <!-- ── Filters ──────────────────── -->
    <div class="filter-card" id="filterSection" style="display:<?= ($filters['search'] || $filters['status'] || $filters['location']) ? 'block' : 'none' ?>">
        <div class="filter-head">
            <div class="filter-head-icon"><i class="fas fa-filter"></i></div>
            <h2>Filter Projects</h2>
        </div>
        <div class="filter-body">
            <form method="GET">
                <div class="filter-row">
                    <div class="search-wrap">
                        <i class="fas fa-search icon"></i>
                        <input type="text" name="search" class="f-ctrl" placeholder="Search projects…" value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <select name="status" class="f-ctrl">
                        <option value="">All Status</option>
                        <option value="active"    <?= $filters['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="on_hold"   <?= $filters['status'] === 'on_hold'   ? 'selected' : '' ?>>On Hold</option>
                    </select>
                    <select name="location" class="f-ctrl">
                        <option value="">All Locations</option>
                        <?php foreach ($all_locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= $filters['location'] === $loc ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
                    <a href="projects.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Main Card ─────────────────── -->
    <div class="main-card">
        <div class="card-head">
            <div class="card-icon"><i class="fas fa-building"></i></div>
            <h2>All Projects</h2>
            <span class="count-tag"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?></span>
        </div>

        <div class="table-wrap">
            <?php if (empty($projects)): ?>
                <div class="empty-state">
                    <span class="ei"><i class="fas fa-building"></i></span>
                    <div class="et">No Projects Found</div>
                    <div class="es">Create your first project to get started.</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Location</th>
                            <th>Timeline</th>
                            <th class="center">Units</th>
                            <th class="center">Status</th>
                            <th class="center" style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $i => $proj):
                            $color   = ColorHelper::getProjectColor($proj['id']);
                            $initial = ColorHelper::getInitial($proj['project_name']);
                            $delay   = 40 + $i * 42;
                        ?>
                        <tr class="row-anim" style="animation-delay:<?= $delay ?>ms">
                            <td style="text-align: left; padding-left: 24px;">
                                <?= renderProjectBadge($proj['project_name'], $proj['id']) ?>
                            </td>
                            <td>
                                <div class="loc-cell">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($proj['location']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="timeline-cell">
                                    <div>Start: <strong><?= formatDate($proj['start_date']) ?></strong></div>
                                    <?php if ($proj['expected_completion']): ?>
                                    <div>End: <strong><?= formatDate($proj['expected_completion']) ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="center">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                    <?php if ($proj['total_flats'] > 0): ?>
                                        <span class="pill purple"><?= $proj['total_flats'] ?> Flats</span>
                                    <?php endif; ?>
                                    <?php if ($proj['total_shops'] > 0): ?>
                                        <span class="pill purple" style="background:#f3e8ff;color:#6b21a8;border-color:#e9d5ff;"><?= $proj['total_shops'] ?> Shops</span>
                                    <?php endif; ?>
                                    <?php if ($proj['total_offices'] > 0): ?>
                                        <span class="pill purple" style="background:#fae8ff;color:#86198f;border-color:#f5d0fe;"><?= $proj['total_offices'] ?> Offices</span>
                                    <?php endif; ?>
                                    <?php if ($proj['total_flats'] == 0 && $proj['total_shops'] == 0 && $proj['total_offices'] == 0): ?>
                                        <span class="pill gray">0 Units</span>
                                    <?php endif; ?>

                                    <?php if ($proj['has_multiple_towers']): ?>
                                        <span class="pill gray" style="font-size:0.62rem;">
                                            <i class="fas fa-building" style="font-size:0.58rem;"></i>
                                            <?= $proj['tower_count'] ?> Towers
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="center">
                                <span class="pill <?= $proj['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $proj['status'])) ?>
                                </span>
                            </td>
                            <td class="center">
                                <div class="act-wrap">
                                    <button class="act-btn view" onclick='openViewModal(<?= htmlspecialchars(json_encode($proj)) ?>)' title="View"><i class="fas fa-eye"></i></button>
                                    <button class="act-btn edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($proj)) ?>)' title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="act-btn del"  onclick="openDeleteModal(<?= $proj['id'] ?>)" title="Delete"><i class="fas fa-times"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════
     ADD MODAL
══════════════════════════════════════ -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-icon orange"><i class="fas fa-plus"></i></div>
            <div style="flex:1;">
                <div class="modal-title">New Project</div>
                <div class="modal-subtitle">Launch a new construction project</div>
            </div>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">

                <div class="fsec"><i class="fas fa-info-circle"></i> Project Essentials</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Project Name <span style="color:#dc2626">*</span></label>
                        <input type="text" name="project_name" class="f-input" required placeholder="e.g. Skyline Towers Phase I">
                    </div>
                    <div class="fg">
                        <label>Location <span style="color:#dc2626">*</span></label>
                        <input type="text" name="location" class="f-input" required placeholder="e.g. Sector 45, Downtown">
                    </div>
                </div>

                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Multi-Tower Project</div>
                        <div class="toggle-sub">Enable if this project has multiple towers</div>
                    </div>
                    <input class="tog-input" type="checkbox" name="has_multiple_towers" id="add_towers">
                    <label class="tog-track" for="add_towers"></label>
                </div>

                <div class="fsec"><i class="far fa-calendar-alt"></i> Timeline</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Start Date <span style="color:#dc2626">*</span></label>
                        <input type="date" name="start_date" class="f-input" required>
                    </div>
                    <div class="fg">
                        <label>Target Completion</label>
                        <input type="date" name="expected_completion" class="f-input">
                    </div>
                </div>

                <div class="fsec"><i class="fas fa-ruler-combined"></i> Scope & Status</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Floors</label>
                        <input type="number" name="total_floors" class="f-input" min="0" value="0">
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status" class="f-input is-select">
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="fgrid3" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Total Flats</label>
                        <input type="number" name="total_flats" class="f-input" min="0" value="0">
                    </div>
                    <div class="fg">
                        <label>Total Shops</label>
                        <input type="number" name="total_shops" class="f-input" min="0" value="0">
                    </div>
                    <div class="fg">
                        <label>Total Offices</label>
                        <input type="number" name="total_offices" class="f-input" min="0" value="0">
                    </div>
                </div>

                <div class="fg">
                    <label>Default Payment Plan</label>
                    <select name="default_stage_of_work_id" class="f-input is-select">
                        <option value="">— None (Manual) —</option>
                        <?php foreach ($stage_templates as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="f-hint">Used for tracking project progress independently of bookings.</div>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-submit"><i class="fas fa-rocket"></i> Launch Project</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════ -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-icon green"><i class="fas fa-pencil-alt"></i></div>
            <div style="flex:1;">
                <div class="modal-title">Edit Project</div>
                <div class="modal-subtitle">Modify project details and configuration</div>
            </div>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">

                <div class="fsec"><i class="fas fa-info-circle"></i> Project Essentials</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg fspan">
                        <label>Project Name <span style="color:#dc2626">*</span></label>
                        <input type="text" name="project_name" id="e_name" class="f-input" required>
                    </div>
                    <div class="fg fspan">
                        <label>Location <span style="color:#dc2626">*</span></label>
                        <input type="text" name="location" id="e_loc" class="f-input" required>
                    </div>
                </div>

                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Multi-Tower Project</div>
                        <div class="toggle-sub">Enable if this project has multiple towers</div>
                    </div>
                    <input class="tog-input" type="checkbox" name="has_multiple_towers" id="e_towers">
                    <label class="tog-track" for="e_towers"></label>
                </div>

                <div class="fsec"><i class="far fa-calendar-alt"></i> Timeline</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Start Date <span style="color:#dc2626">*</span></label>
                        <input type="date" name="start_date" id="e_start" class="f-input" required>
                    </div>
                    <div class="fg">
                        <label>Target Completion</label>
                        <input type="date" name="expected_completion" id="e_end" class="f-input">
                    </div>
                </div>

                <div class="fsec"><i class="fas fa-sliders-h"></i> Configuration</div>
                <div class="fgrid" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Floors</label>
                        <input type="number" name="total_floors" id="e_floors" class="f-input" min="0">
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status" id="e_status" class="f-input is-select">
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="fgrid3" style="margin-bottom:1rem;">
                    <div class="fg">
                        <label>Total Flats</label>
                        <input type="number" name="total_flats" id="e_flats" class="f-input" min="0">
                    </div>
                    <div class="fg">
                        <label>Total Shops</label>
                        <input type="number" name="total_shops" id="e_shops" class="f-input" min="0">
                    </div>
                    <div class="fg">
                        <label>Total Offices</label>
                        <input type="number" name="total_offices" id="e_offices" class="f-input" min="0">
                    </div>
                </div>

                <div class="fg">
                    <label>Default Payment Plan</label>
                    <select name="default_stage_of_work_id" id="e_sow" class="f-input is-select">
                        <option value="">— None (Manual) —</option>
                        <?php foreach ($stage_templates as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('editModal')">Discard</button>
                <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Update Project</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     VIEW MODAL
══════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-box sm">
        <div class="modal-head">
            <div class="modal-head-icon blue"><i class="fas fa-eye"></i></div>
            <div style="flex:1;">
                <div class="modal-title" id="v_name">Project Details</div>
                <div class="modal-subtitle" id="v_loc">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;">
                <span class="pill" id="v_status_pill">—</span>
            </div>
            <div class="view-stat-row">
                <div class="view-stat">
                    <div class="view-stat-label">Total Units</div>
                    <div class="view-stat-val" id="v_total">0</div>
                </div>
                <div class="view-stat">
                    <div class="view-stat-label">Units Left</div>
                    <div class="view-stat-val red" id="v_left">0</div>
                </div>
            </div>
            <div id="v_tower_row" class="view-info-row" style="display:none;">
                <span class="view-info-label">Towers</span>
                <span class="view-info-value" id="v_towers">—</span>
            </div>
            <div class="view-info-row">
                <span class="view-info-label">Total Floors</span>
                <span class="view-info-value" id="v_floors">—</span>
            </div>
            <div class="view-info-row">
                <span class="view-info-label">Start Date</span>
                <span class="view-info-value" id="v_start">—</span>
            </div>
            <div class="view-info-row">
                <span class="view-info-label">Target Completion</span>
                <span class="view-info-value" id="v_end">—</span>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-ghost" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     DELETE MODAL
══════════════════════════════════════ -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box sm">
        <div class="modal-body">
            <div class="del-confirm">
                <div class="del-icon"><i class="fas fa-trash-alt"></i></div>
                <div class="del-title">Delete Project?</div>
                <div class="del-sub">This action cannot be undone. All project data will be permanently removed.</div>
            </div>
        </div>
        <div class="modal-foot">
            <form method="POST" style="display:contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="del_id">
                <button type="button" class="btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-submit danger"><i class="fas fa-trash-alt"></i> Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
/* ── Modal helpers ──────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.open').forEach(o => closeModal(o.id));
});

/* ── Filter toggle ──────────────────── */
document.getElementById('toggleFilter').addEventListener('click', () => {
    const fs = document.getElementById('filterSection');
    fs.style.display = fs.style.display === 'none' ? 'block' : 'none';
});

/* ── Add button ─────────────────────── */
document.getElementById('openAddBtn').addEventListener('click', () => openModal('addModal'));

/* ── Edit modal ─────────────────────── */
function openEditModal(p) {
    document.getElementById('edit_id').value  = p.id;
    document.getElementById('e_name').value   = p.project_name;
    document.getElementById('e_loc').value    = p.location;
    document.getElementById('e_start').value  = p.start_date;
    document.getElementById('e_end').value    = p.expected_completion || '';
    document.getElementById('e_floors').value = p.total_floors;
    document.getElementById('e_flats').value   = p.total_flats;
    document.getElementById('e_shops').value   = p.total_shops || 0;
    document.getElementById('e_offices').value = p.total_offices || 0;
    document.getElementById('e_status').value  = p.status;
    document.getElementById('e_towers').checked = parseInt(p.has_multiple_towers) === 1;
    const sow = document.getElementById('e_sow');
    if (sow) sow.value = p.default_stage_of_work_id || '';
    openModal('editModal');
}

/* ── View modal ─────────────────────── */
function openViewModal(p) {
    document.getElementById('v_name').textContent   = p.project_name;
    document.getElementById('v_loc').textContent    = p.location;
    document.getElementById('v_total').textContent  = (parseInt(p.total_flats)||0) + (parseInt(p.total_shops)||0) + (parseInt(p.total_offices)||0);
    document.getElementById('v_left').textContent   = (parseInt(p.total_flats) - (parseInt(p.booked_count) || 0)); // Booking logic typically for flats only? Assuming yes for now.

    // Detailed breakdown if mixed use
    let details = [];
    if(p.total_flats > 0) details.push(p.total_flats + ' Flats');
    if(p.total_shops > 0) details.push(p.total_shops + ' Shops');
    if(p.total_offices > 0) details.push(p.total_offices + ' Offices');
    if(details.length > 0) {
        document.getElementById('v_loc').innerHTML = p.location + '<br><span style="font-size:0.7em;color:var(--accent)">' + details.join(' • ') + '</span>';
    }

    document.getElementById('v_floors').textContent = p.total_floors;
    document.getElementById('v_start').textContent  = p.start_date;
    document.getElementById('v_end').textContent    = p.expected_completion || '—';

    const tRow = document.getElementById('v_tower_row');
    if (parseInt(p.has_multiple_towers) === 1) {
        document.getElementById('v_towers').textContent = p.tower_count;
        tRow.style.display = 'flex';
    } else {
        tRow.style.display = 'none';
    }

    const sp = document.getElementById('v_status_pill');
    sp.className   = 'pill ' + p.status;
    sp.textContent = p.status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());

    openModal('viewModal');
}

/* ── Delete modal ───────────────────── */
function openDeleteModal(id) {
    document.getElementById('del_id').value = id;
    openModal('deleteModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>