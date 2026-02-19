<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Stage of Work Templates';
$current_page = 'stage_of_work';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/projects/stage_of_work.php');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $name   = trim($_POST['name']);
            $desc   = trim($_POST['description']);
            $stages = $_POST['stages'] ?? [];

            if (empty($name))   throw new Exception("Template name is required");
            if (empty($stages)) throw new Exception("At least one stage is required");

            $total = 0;
            foreach ($stages as $s) $total += floatval($s['percentage']);
            if (abs($total - 100) > 0.01) throw new Exception("Total percentage must equal 100%. Current: {$total}%");

            $db->beginTransaction();

            if ($action === 'create') {
                $db->query("INSERT INTO stage_of_work (name, description, total_stages, status) VALUES (?, ?, ?, 'active')",
                    [$name, $desc, count($stages)]);
                $planId = $db->getConnection()->lastInsertId();
                $msg = "Template created successfully";
            } else {
                $planId = intval($_POST['id']);
                $db->query("UPDATE stage_of_work SET name = ?, description = ?, total_stages = ? WHERE id = ?",
                    [$name, $desc, count($stages), $planId]);
                $db->query("DELETE FROM stage_of_work_items WHERE stage_of_work_id = ?", [$planId]);
                $msg = "Template updated successfully";
            }

            $order = 1;
            foreach ($stages as $stage) {
                $db->query("INSERT INTO stage_of_work_items (stage_of_work_id, stage_name, percentage, stage_order, stage_type) VALUES (?, ?, ?, ?, ?)", [
                    $planId, trim($stage['name']), floatval($stage['percentage']), $order++, $stage['type']
                ]);
            }

            $db->commit();
            setFlashMessage('success', $msg);

        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            $db->delete('stage_of_work', 'id = ?', [$id]);
            setFlashMessage('success', 'Template deleted successfully');
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlashMessage('error', $e->getMessage());
    }

    redirect('modules/projects/stage_of_work.php');
}

$plans = $db->query("SELECT * FROM stage_of_work ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; }

:root {
    --ink:        #1a1714;
    --ink-soft:   #6b6560;
    --ink-mute:   #9e9690;
    --cream:      #f5f3ef;
    --surface:    #ffffff;
    --border:     #e8e3db;
    --border-lt:  #f0ece5;
    --accent:     #2a58b5;
    --accent-lt:  #eff4ff;
    --accent-md:  #c7d9f9;
    --accent-bg:  #f0f5ff;
    --accent-dk:  #1e429f;
}

body {
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
}

/* ─────────────────────────────────────────
   WRAPPER
───────────────────────────────────────── */
.page-wrap {
    max-width: 1080px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}

/* ─────────────────────────────────────────
   ENTRANCE ANIMATIONS
───────────────────────────────────────── */
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
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
    opacity: 0;
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

.main-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.14s forwards;
}

.data-table tbody tr.row-anim {
    opacity: 0;
    transform: translateX(-10px);
    animation: rowSlide 0.34s cubic-bezier(0.22,1,0.36,1) forwards;
}

/* ─────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────── */
.eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--ink);
    margin: 0;
}
.page-header h1 em {
    color: var(--accent);
    font-style: italic;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 1.4rem;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
    text-decoration: none;
}
.btn-primary:hover {
    background: var(--accent-dk);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(42,88,181,0.32);
    color: white;
}
.btn-primary:active { transform: translateY(0); }

/* ─────────────────────────────────────────
   CARD
───────────────────────────────────────── */
.card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.1rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.card-icon {
    width: 30px; height: 30px;
    border-radius: 7px;
    background: var(--accent-lt);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
}
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 1rem; font-weight: 600;
    color: var(--ink); margin: 0;
}
.count-tag {
    margin-left: auto;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute);
    background: var(--cream);
    border: 1px solid var(--border);
    padding: 0.18rem 0.65rem;
    border-radius: 20px;
}

/* ─────────────────────────────────────────
   TABLE
───────────────────────────────────────── */
.table-wrap { overflow-x: auto; }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table thead tr {
    background: #f0f4fb;
    border-bottom: 1.5px solid var(--border);
}
.data-table thead th {
    padding: 0.75rem 1.1rem;
    text-align: left;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.data-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.12s ease;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f7f9ff; }
.data-table td {
    padding: 1rem 1.1rem;
    vertical-align: middle;
    color: var(--ink-soft);
}

.tpl-name { font-weight: 700; color: var(--ink); font-size: 0.9rem; line-height: 1.3; }
.tpl-desc { font-size: 0.78rem; color: var(--ink-mute); margin-top: 2px; }

.stages-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem; font-weight: 700;
    color: var(--accent);
    background: var(--accent-lt);
    border: 1px solid var(--accent-md);
    padding: 0.25rem 0.7rem;
    border-radius: 20px;
    white-space: nowrap;
}

.date-text {
    font-size: 0.78rem;
    color: var(--ink-mute);
}

/* action buttons */
.act-wrap { display: flex; align-items: center; justify-content: center; gap: 0.35rem; }
.act-btn {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 0.72rem;
    border: 1.5px solid var(--border);
    background: white; color: var(--ink-mute);
    cursor: pointer; transition: all 0.16s ease;
}
.act-btn.view:hover  { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
.act-btn.edit:hover  { border-color: #059669; color: #059669; background: #f0fdf4; }
.act-btn.del:hover   { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

/* empty state */
.empty-state { text-align: center; padding: 5rem 2rem; color: var(--ink-mute); }
.empty-state .ei { font-size: 2.8rem; opacity: 0.2; margin-bottom: 1rem; display: block; color: var(--accent); }
.empty-state .et { font-family: 'Fraunces', serif; font-size: 1.1rem; color: var(--ink-soft); margin-bottom: 0.4rem; }
.empty-state .es { font-size: 0.875rem; }

/* ─────────────────────────────────────────
   MODALS
───────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(26,23,20,0.5);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 9000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}
.modal-overlay.open {
    opacity: 1;
    pointer-events: all;
}

.modal-box {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(26,23,20,0.22);
    width: 100%;
    max-width: 660px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: scale(0.95) translateY(12px);
    transition: transform 0.32s cubic-bezier(0.22,1,0.36,1);
}
.modal-overlay.open .modal-box {
    transform: scale(1) translateY(0);
}
.modal-box.sm { max-width: 440px; }

/* Modal head */
.modal-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.3rem 1.6rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
    flex-shrink: 0;
}
.modal-head-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: var(--accent-lt);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; flex-shrink: 0;
}
.modal-head-icon.green { background: #d1fae5; color: #059669; }
.modal-head-icon.red   { background: #fee2e2; color: #dc2626; }

.modal-title {
    font-family: 'Fraunces', serif;
    font-size: 1.05rem; font-weight: 700; color: var(--ink);
    margin: 0; flex: 1;
}
.modal-subtitle { font-size: 0.72rem; color: var(--ink-mute); margin-top: 2px; }

.modal-close {
    width: 28px; height: 28px;
    border-radius: 6px;
    border: 1.5px solid var(--border);
    background: white; color: var(--ink-soft);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.75rem;
    transition: all 0.16s ease; flex-shrink: 0;
}
.modal-close:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

/* Modal body */
.modal-body {
    padding: 1.6rem;
    overflow-y: auto;
    flex: 1;
}

#createForm, #editForm {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

/* Form fields */
.f-group { margin-bottom: 1.1rem; }
.f-group:last-child { margin-bottom: 0; }
.f-label {
    display: block;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 0.4rem;
}
.f-input {
    width: 100%;
    height: 40px;
    padding: 0 0.85rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    color: var(--ink);
    background: #fdfcfa;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.f-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(42,88,181,0.11);
    background: white;
}

/* Stages section */
.stages-label {
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-mute);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-lt);
    margin-bottom: 0.75rem;
}

.stages-box {
    background: var(--accent-lt);
    border: 1.5px solid var(--accent-md);
    border-radius: 10px;
    padding: 0.75rem;
    min-height: 52px;
    margin-bottom: 0.65rem;
}

.stage-row {
    display: grid;
    grid-template-columns: 26px 1fr 86px 26px;
    gap: 0.5rem;
    align-items: flex-start;
    background: white;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 0.6rem;
    margin-bottom: 0.5rem;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.stage-row:last-child { margin-bottom: 0; }
.stage-row:hover { border-color: var(--accent-md); box-shadow: 0 2px 8px rgba(42,88,181,0.07); }

.drag-handle {
    color: var(--border);
    cursor: grab;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem;
    height: 34px; /* Align with input height */
    transition: color 0.15s;
}
.drag-handle:hover { color: var(--ink-mute); }
.drag-handle:active { cursor: grabbing; }

.s-name-input,
.s-pct-input {
    height: 34px;
    padding: 0 0.6rem;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem;
    color: var(--ink);
    background: #fdfcfa;
    outline: none;
    width: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s;
}
.s-name-input:focus,
.s-pct-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(42,88,181,0.1);
    background: white;
}
.s-pct-input { text-align: center; }

.s-type-hint {
    font-size: 0.63rem; color: var(--ink-mute);
    margin-top: 2px; display: flex; align-items: center; gap: 3px;
    padding-left: 1px; /* Slight alignment tweak */
}

.stage-del {
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 5px; cursor: pointer; font-size: 0.7rem;
    border: 1.5px solid #fecaca;
    color: #dc2626; background: #fff5f5;
    transition: all 0.15s ease;
    margin-top: 4px; /* Center relative to 34px input */
}
.stage-del:hover { background: #dc2626; border-color: #dc2626; color: white; }

.btn-add-stage {
    width: 100%;
    height: 36px;
    border: 1.5px dashed var(--accent-md);
    border-radius: 8px;
    background: white;
    color: var(--accent);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.8rem; font-weight: 600;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
    transition: all 0.18s ease;
}
.btn-add-stage:hover { background: var(--accent-lt); border-color: var(--accent); }

/* Total bar */
.total-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.8rem;
    padding: 0.75rem 1rem;
    background: var(--cream);
    border: 1.5px solid var(--border);
    border-radius: 8px;
}
.total-bar-label {
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--ink-mute);
}
.total-bar-right { display: flex; align-items: center; gap: 0.6rem; }
.total-pct {
    font-family: 'Fraunces', serif;
    font-size: 1.15rem; font-weight: 700; color: var(--ink);
}
.validity-tag {
    font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em;
    padding: 0.2rem 0.6rem; border-radius: 20px;
}
.validity-tag.valid   { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.validity-tag.invalid { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* Modal footer */
.modal-foot {
    padding: 1.1rem 1.6rem;
    border-top: 1.5px solid var(--border-lt);
    background: #fafbff;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-shrink: 0;
}

.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.62rem 1.25rem;
    background: white; color: var(--ink-soft);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 500;
    cursor: pointer; transition: all 0.18s ease;
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.btn-submit {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.62rem 1.5rem;
    background: var(--accent);
    color: white;
    border: 1.5px solid var(--accent);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s ease;
}
.btn-submit:hover { background: var(--accent-dk); border-color: var(--accent-dk); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: 0.42; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
.btn-submit.danger { background: #dc2626; border-color: #dc2626; }
.btn-submit.danger:hover { background: #b91c1c; border-color: #b91c1c; box-shadow: 0 4px 14px rgba(220,38,38,0.3); }

/* View modal list */
.view-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.8rem 1rem;
    border-bottom: 1px solid var(--border-lt);
    gap: 1rem;
}
.view-row:last-child { border-bottom: none; }
.view-row-order {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--accent-lt);
    color: var(--accent);
    font-size: 0.68rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.view-row-name { font-weight: 600; color: var(--ink); font-size: 0.875rem; }
.view-row-type { font-size: 0.67rem; color: var(--ink-mute); margin-top: 2px; display: flex; align-items: center; gap: 3px; }
.view-row-pct {
    font-family: 'Fraunces', serif;
    font-size: 1.05rem; font-weight: 700; color: var(--accent);
    white-space: nowrap;
}

/* Delete confirm */
.del-confirm { text-align: center; padding: 0.5rem 0; }
.del-icon {
    width: 58px; height: 58px;
    background: #fee2e2; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.1rem;
    font-size: 1.4rem; color: #dc2626;
}
.del-title { font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 700; color: var(--ink); margin-bottom: 0.4rem; }
.del-sub   { font-size: 0.875rem; color: var(--ink-soft); line-height: 1.55; }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Masters</div>
            <h1>Stage of Work <em>Templates</em></h1>
        </div>
        <button class="btn-primary" id="openCreateBtn">
            <i class="fas fa-plus"></i> Create Template
        </button>
    </div>

    <!-- ── Main Card ─────────────────── -->
    <div class="main-card">
        <div class="card-head">
            <div class="card-icon"><i class="fas fa-layer-group"></i></div>
            <h2>All Templates</h2>
            <span class="count-tag"><?= count($plans) ?> template<?= count($plans) !== 1 ? 's' : '' ?></span>
        </div>

        <div class="table-wrap">
            <?php if (empty($plans)): ?>
                <div class="empty-state">
                    <span class="ei"><i class="fas fa-layer-group"></i></span>
                    <div class="et">No Templates Yet</div>
                    <div class="es">Create a template to define reusable payment stage structures.</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Description</th>
                            <th>Stages</th>
                            <th>Last Updated</th>
                            <th style="width:100px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $i => $plan): $delay = 40 + $i * 45; ?>
                        <tr class="row-anim" style="animation-delay:<?= $delay ?>ms">
                            <td>
                                <div class="tpl-name"><?= htmlspecialchars($plan['name']) ?></div>
                            </td>
                            <td>
                                <div class="tpl-desc">
                                    <?= htmlspecialchars(mb_substr($plan['description'], 0, 60)) . (mb_strlen($plan['description']) > 60 ? '…' : '') ?>
                                </div>
                            </td>
                            <td>
                                <span class="stages-pill">
                                    <i class="fas fa-layer-group" style="font-size:0.58rem;"></i>
                                    <?= $plan['total_stages'] ?> Stage<?= $plan['total_stages'] != 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-text"><?= formatDate($plan['updated_at'] ?: $plan['created_at']) ?></span>
                            </td>
                            <td>
                                <div class="act-wrap">
                                    <button class="act-btn view" onclick="openViewModal(<?= $plan['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="act-btn edit" onclick="openEditModal(<?= $plan['id'] ?>)" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="act-btn del"  onclick="openDeleteModal(<?= $plan['id'] ?>)" title="Delete"><i class="fas fa-times"></i></button>
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
     CREATE MODAL
══════════════════════════════════════ -->
<div id="createModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-icon"><i class="fas fa-plus"></i></div>
            <div style="flex:1;">
                <div class="modal-title">Create New Template</div>
                <div class="modal-subtitle">Configure stages — percentages must total 100%</div>
            </div>
            <button class="modal-close" onclick="closeModal('createModal')"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="createForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-body">
                <div class="f-group">
                    <label class="f-label">Template Name <span style="color:#dc2626">*</span></label>
                    <input type="text" class="f-input" name="name" required placeholder="e.g. Standard Construction Plan">
                </div>
                <div class="f-group">
                    <label class="f-label">Description</label>
                    <input type="text" class="f-input" name="description" placeholder="Brief details…">
                </div>

                <div class="stages-label">Stages Breakdown</div>
                <div class="stages-box" id="createBox"></div>

                <button type="button" class="btn-add-stage" onclick="addStage('createBox','create')">
                    <i class="fas fa-plus"></i> Add Stage
                </button>

                <div class="total-bar">
                    <span class="total-bar-label">Total</span>
                    <div class="total-bar-right">
                        <span class="total-pct" id="createPct">0%</span>
                        <span class="validity-tag invalid" id="createValidity">Invalid</span>
                    </div>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-submit" id="createBtn" disabled>
                    <i class="fas fa-check"></i> Save Template
                </button>
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
                <div class="modal-title">Edit Template</div>
                <div class="modal-subtitle">Modify stages — percentages must total 100%</div>
            </div>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">

            <div class="modal-body">
                <div class="f-group">
                    <label class="f-label">Template Name <span style="color:#dc2626">*</span></label>
                    <input type="text" class="f-input" name="name" id="editName" required>
                </div>
                <div class="f-group">
                    <label class="f-label">Description</label>
                    <input type="text" class="f-input" name="description" id="editDesc">
                </div>

                <div class="stages-label">Stages Breakdown</div>
                <div class="stages-box" id="editBox"></div>

                <button type="button" class="btn-add-stage" onclick="addStage('editBox','edit')">
                    <i class="fas fa-plus"></i> Add Stage
                </button>

                <div class="total-bar">
                    <span class="total-bar-label">Total</span>
                    <div class="total-bar-right">
                        <span class="total-pct" id="editPct">0%</span>
                        <span class="validity-tag invalid" id="editValidity">Invalid</span>
                    </div>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-submit" id="editBtn">
                    <i class="fas fa-check"></i> Update Template
                </button>
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
            <div class="modal-head-icon"><i class="fas fa-eye"></i></div>
            <div style="flex:1;">
                <div class="modal-title" id="viewTitle">Template Details</div>
                <div class="modal-subtitle" id="viewSubtitle">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewBody" style="padding:0; min-height:120px;">
            <div style="text-align:center;padding:3rem;color:var(--ink-mute);"><i class="fas fa-spinner fa-spin"></i></div>
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
                <div class="del-title">Delete Template?</div>
                <div class="del-sub">This action cannot be undone. All stage configurations in this template will be permanently removed.</div>
            </div>
        </div>
        <div class="modal-foot">
            <form method="POST" style="display:contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="button" class="btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-submit danger">
                    <i class="fas fa-trash-alt"></i> Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
/* ── Modal helpers ────────────────────── */
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

/* ── Stage counter ────────────────────── */
let uid = 0;

function addStage(boxId, scope, name='', pct='', type='construction_linked') {
    const box = document.getElementById(boxId);
    const id  = ++uid;

    const typeLabel = type === 'booking'
        ? '<i class="far fa-clock"></i> Time Based'
        : '<i class="fas fa-hammer"></i> Construction Linked';

    const row = document.createElement('div');
    row.className = 'stage-row';
    row.innerHTML = `
        <div class="drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
        <div>
            <input class="s-name-input" type="text"
                   name="stages[${id}][name]" value="${esc(name)}"
                   placeholder="Stage name" required>
            <input type="hidden" name="stages[${id}][type]" value="${esc(type)}">
            <div class="s-type-hint">${typeLabel}</div>
        </div>
        <input class="s-pct-input pct-field" type="number"
               name="stages[${id}][percentage]" value="${esc(pct)}"
               placeholder="%" step="0.01" min="0" max="100" required>
        <div class="stage-del" title="Remove" onclick="this.closest('.stage-row').remove(); recalc('${boxId}','${scope}')">
            <i class="fas fa-times"></i>
        </div>
    `;

    row.querySelector('.pct-field').addEventListener('input', () => recalc(boxId, scope));
    box.appendChild(row);
    recalc(boxId, scope);
    refreshSortable(boxId, scope);
}

function removeStage(btn, boxId, scope) {
    btn.closest('.stage-row').remove();
    recalc(boxId, scope);
}

function recalc(boxId, scope) {
    const pctId = scope === 'create' ? 'createPct'      : 'editPct';
    const valId = scope === 'create' ? 'createValidity' : 'editValidity';
    const btnId = scope === 'create' ? 'createBtn'      : 'editBtn';

    let total = 0;
    document.querySelectorAll(`#${boxId} .pct-field`).forEach(f => {
        total += parseFloat(f.value) || 0;
    });

    document.getElementById(pctId).textContent = total.toFixed(2) + '%';

    const ok = Math.abs(total - 100) < 0.01;
    const vEl = document.getElementById(valId);
    vEl.textContent = ok ? 'Valid' : 'Invalid';
    vEl.className   = 'validity-tag ' + (ok ? 'valid' : 'invalid');
    document.getElementById(btnId).disabled = !ok;
}

function refreshSortable(boxId, scope) {
    const el = document.getElementById(boxId);
    if (el._sortable) el._sortable.destroy();
    el._sortable = Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        onEnd: () => recalc(boxId, scope)
    });
}

function esc(v) {
    return String(v || '').replace(/"/g, '&quot;');
}

/* ── Create button ────────────────────── */
document.getElementById('openCreateBtn').addEventListener('click', () => {
    openModal('createModal');
    const box = document.getElementById('createBox');
    if (box.children.length === 0) {
        addStage('createBox', 'create', 'Booking Token',      10,  'booking');
        addStage('createBox', 'create', 'Registration',       20,  'booking');
        addStage('createBox', 'create', 'Plinth Completion',  15,  'construction_linked');
    }
});

/* ── Edit modal ───────────────────────── */
function openEditModal(id) {
    openModal('editModal');
    const box = document.getElementById('editBox');
    box.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--ink-mute);"><i class="fas fa-spinner fa-spin"></i></div>';

    fetch(`get_stage_details.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            document.getElementById('editId').value   = data.plan.id;
            document.getElementById('editName').value = data.plan.name;
            document.getElementById('editDesc').value = data.plan.description;
            box.innerHTML = '';
            data.items.forEach(item =>
                addStage('editBox', 'edit', item.stage_name, item.percentage, item.stage_type)
            );
        });
}

/* ── View modal ───────────────────────── */
function openViewModal(id) {
    openModal('viewModal');
    document.getElementById('viewTitle').textContent    = 'Loading…';
    document.getElementById('viewSubtitle').textContent = '';
    document.getElementById('viewBody').innerHTML =
        '<div style="text-align:center;padding:3rem;color:var(--ink-mute);"><i class="fas fa-spinner fa-spin"></i></div>';

    fetch(`get_stage_details.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            document.getElementById('viewTitle').textContent    = data.plan.name;
            document.getElementById('viewSubtitle').textContent = data.plan.description || '—';

            let html = '';
            data.items.forEach((item, i) => {
                const typeLabel = item.stage_type === 'booking'
                    ? '<i class="far fa-clock"></i> Time Based'
                    : '<i class="fas fa-hammer"></i> Construction Linked';
                html += `
                    <div class="view-row">
                        <div class="view-row-order">${i + 1}</div>
                        <div style="flex:1;">
                            <div class="view-row-name">${item.stage_name}</div>
                            <div class="view-row-type">${typeLabel}</div>
                        </div>
                        <div class="view-row-pct">${parseFloat(item.percentage)}%</div>
                    </div>`;
            });
            document.getElementById('viewBody').innerHTML = html;
        });
}

/* ── Delete modal ─────────────────────── */
function openDeleteModal(id) {
    document.getElementById('deleteId').value = id;
    openModal('deleteModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>