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
$page_title = 'Project Progress';
$current_page = 'project_progress';

// Fetch all projects for the dropdown
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

$selected_project_id = $_GET['project_id'] ?? null;
$milestones = [];

// Fetch milestones if a project is selected
if ($selected_project_id) {
    // 1. Fetch Project Info (to get default plan)
    $project = $db->select('projects', 'id = ?', [$selected_project_id])->fetch();
    
    // 2. Determine Source of Truth for Stages
    if (!empty($project['default_stage_of_work_id'])) {
        // Use Master Template assigned to Project
        $sql = "SELECT swi.stage_name, swi.percentage, swi.stage_order, sw.name as plan_name
                FROM stage_of_work_items swi
                JOIN stage_of_work sw ON swi.stage_of_work_id = sw.id
                WHERE sw.id = ?
                ORDER BY swi.stage_order ASC";
        $milestones = $db->query($sql, [$project['default_stage_of_work_id']])->fetchAll();
    } else {
        // FALLBACK: Infer from active bookings (Legacy behavior)
        $sql = "SELECT DISTINCT swi.stage_name, swi.percentage, swi.stage_order, sw.name as plan_name
                FROM bookings b
                JOIN stage_of_work sw ON b.stage_of_work_id = sw.id
                JOIN stage_of_work_items swi ON sw.id = swi.stage_of_work_id
                WHERE b.project_id = ? AND b.status = 'active'
                GROUP BY swi.stage_name
                ORDER BY swi.stage_order ASC";
        $milestones = $db->query($sql, [$selected_project_id])->fetchAll();
    }

    // 3. Fetch Completed Stages
    $completed_sql = "SELECT stage_name FROM project_completed_stages WHERE project_id = ?
                      UNION
                      SELECT DISTINCT bd.stage_name 
                      FROM booking_demands bd 
                      JOIN bookings b ON bd.booking_id = b.id 
                      WHERE b.project_id = ?";
    $completed_stages = $db->query($completed_sql, [$selected_project_id, $selected_project_id])->fetchAll(PDO::FETCH_COLUMN);
}

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
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

    /* ── Page Wrapper ────────────────────────── */
    .pp-wrap { max-width: 1080px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .pp-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
    }

    .pp-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .pp-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .pp-header h1 em { color: var(--accent); font-style: italic; }

    /* ── Selector Card ───────────────────────── */
    .selector-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; padding: 1.5rem; margin-bottom: 1.75rem;
        animation: fadeUp 0.4s ease both;
    }

    .selector-card label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.6rem;
    }

    .selector-card select {
        width: 100%; max-width: 420px; padding: 0.7rem 0.9rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.5rem;
    }
    .selector-card select:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }

    /* ── Progress Card ───────────────────────── */
    .prog-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.5s 0.1s ease both;
    }

    .prog-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa; flex-wrap: wrap; gap: 1rem;
    }
    .prog-header h3 {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600;
        color: var(--ink); margin: 0; display: flex; align-items: center; gap: 0.6rem;
    }
    .prog-header h3 i { font-size: 0.85rem; color: var(--accent); }
    .prog-header p {
        font-size: 0.78rem; color: var(--ink-mute); margin: 0.25rem 0 0;
    }

    .stage-badge {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.4rem 0.9rem; border-radius: 20px;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; background: var(--accent-bg);
        color: var(--accent); border: 1px solid #e0c9b5;
    }

    /* ── Milestone Rows ──────────────────────── */
    .ml-list {}
    .ml-row {
        display: flex; align-items: center; gap: 1.25rem;
        padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-lt);
        transition: background 0.15s;
    }
    .ml-row:last-child { border-bottom: none; }
    .ml-row:hover { background: #fdfcfa; }

    .ml-icon {
        width: 44px; height: 44px; border-radius: 10px;
        background: var(--accent-bg); display: flex;
        align-items: center; justify-content: center;
        color: var(--accent); font-size: 0.9rem; flex-shrink: 0;
    }

    .ml-info { flex: 1; }
    .ml-name {
        font-size: 0.925rem; font-weight: 700; color: var(--ink);
        margin-bottom: 0.25rem;
    }
    .ml-meta {
        font-size: 0.78rem; color: var(--ink-mute);
    }
    .ml-meta strong { color: var(--ink-soft); }

    .ml-action { flex-shrink: 0; }

    .btn-complete {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.6rem 1.2rem; background: var(--ink); color: white;
        border: 1.5px solid var(--ink); border-radius: 8px;
        font-size: 0.8rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; text-decoration: none;
    }
    .btn-complete:hover {
        background: var(--accent); border-color: var(--accent);
        box-shadow: 0 4px 14px rgba(181,98,42,0.28);
        transform: translateY(-1px); color: white;
    }

    .btn-completed {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.6rem 1.2rem; background: #ecfdf5; color: #065f46;
        border: 1.5px solid #a7f3d0; border-radius: 8px;
        font-size: 0.8rem; font-weight: 600; cursor: default;
    }

    /* Empty State */
    .empty-state {
        padding: 4rem 1rem; text-align: center;
    }
    .empty-state i {
        font-size: 2.5rem; color: var(--border);
        margin-bottom: 0.75rem; display: block;
    }
    .empty-state h3 {
        font-size: 1.1rem; font-weight: 700; color: var(--ink-soft);
        margin: 0 0 0.4rem;
    }
    .empty-state p {
        font-size: 0.875rem; color: var(--ink-mute); margin: 0;
    }

    /* ── Modal ───────────────────────────────── */
    .pp-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .pp-modal-backdrop.open { display: flex; }

    .pp-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 480px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: var(--ink); margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-head h3 i { color: var(--accent); }
    .modal-head p { font-size: 0.75rem; color: var(--ink-mute); margin: 0.25rem 0 0; }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem; text-align: center; }

    .modal-icon {
        width: 64px; height: 64px; border-radius: 50%;
        background: var(--accent-bg); display: flex;
        align-items: center; justify-content: center;
        margin: 0 auto 1.25rem; color: var(--accent); font-size: 1.5rem;
    }

    .modal-body h4 {
        font-size: 1.05rem; font-weight: 700; color: var(--ink);
        margin: 0 0 0.6rem;
    }
    .modal-body p {
        font-size: 0.85rem; color: var(--ink-soft); line-height: 1.6;
        margin: 0 0 1.5rem;
    }

    .modal-footer {
        display: flex; justify-content: center; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary {
        background: var(--ink); color: white; border: 1.5px solid var(--ink);
        width: 100%; justify-content: center;
    }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.3); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="pp-wrap">

    <!-- Header -->
    <div class="pp-header">
        <div class="eyebrow">Construction Tracking</div>
        <h1>Project <em>Progress</em></h1>
    </div>

    <!-- Project Selector -->
    <div class="selector-card">
        <form method="GET">
            <label>Select Project to Manage Progress</label>
            <select name="project_id" onchange="this.form.submit()">
                <option value="">— Choose Project —</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $selected_project_id == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_project_id): ?>
        <?php if (empty($milestones)): ?>
            <div class="prog-card">
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Linked Payment Plans Found</h3>
                    <p>This project doesn't have any bookings with construction-linked payment plans yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="prog-card">
                <div class="prog-header">
                    <div>
                        <h3><i class="fas fa-layer-group"></i> Construction Milestones</h3>
                        <p>Mark stages as complete to trigger demand generation</p>
                    </div>
                    <span class="stage-badge">
                        <i class="fas fa-list-check"></i> <?= count($milestones) ?> Stages
                    </span>
                </div>
                
                <div class="ml-list">
                    <?php foreach ($milestones as $stage): ?>
                        <div class="ml-row">
                            <div class="ml-icon">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                            <div class="ml-info">
                                <div class="ml-name"><?= htmlspecialchars($stage['stage_name']) ?></div>
                                <div class="ml-meta">
                                    Usually linked to <strong><?= floatval($stage['percentage']) ?>%</strong> demand
                                </div>
                            </div>
                            <div class="ml-action">
                                <?php if (in_array($stage['stage_name'], $completed_stages ?? [])): ?>
                                    <span class="btn-completed">
                                        <i class="fas fa-check-double"></i> Completed
                                    </span>
                                <?php else: ?>
                                    <button class="btn-complete" onclick="confirmMilestone('<?= htmlspecialchars($stage['stage_name']) ?>')">
                                        <i class="fas fa-check-circle"></i> Mark Complete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<!-- Trigger Demand Modal -->
<div class="pp-modal-backdrop" id="triggerModal">
    <div class="pp-modal">
        <div class="modal-head">
            <div>
                <h3><i class="fas fa-bell"></i> Trigger Demands</h3>
                <p>Generating payment demands for customers</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h4>Confirm "<span id="modal_stage_name"></span>"?</h4>
            <p>
                This will automatically generate payment demands for all eligible customers who are pending this stage.
            </p>
            <form method="POST" action="trigger_demand.php">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                <input type="hidden" name="stage_name" id="input_stage_name">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Yes, Generate Demands
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmMilestone(stageName) {
    document.getElementById('modal_stage_name').textContent = stageName;
    document.getElementById('input_stage_name').value = stageName;
    openModal();
}

function openModal() { document.getElementById('triggerModal').classList.add('open'); }
function closeModal() { document.getElementById('triggerModal').classList.remove('open'); }

document.getElementById('triggerModal').addEventListener('click', e => {
    if (e.target.id === 'triggerModal') closeModal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>