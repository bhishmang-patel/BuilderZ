<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$page_title = 'Notifications';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/NotificationService.php';

$notificationService = new NotificationService();
$userId  = $_SESSION['user_id'];
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page - 1) * $limit;

$notifications = $notificationService->getAll($userId, $limit, $offset);
$unread_count  = count(array_filter($notifications, fn($n) => !$n['is_read']));
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }

:root {
    --ink:        #1a1714; --ink-soft:  #6b6560; --ink-mute:  #9e9690;
    --cream:      #f5f3ef; --surface:   #ffffff; --border:    #e8e3db; --border-lt: #f0ece5;
    --accent:     #2a58b5; --accent-lt: #eff4ff; --accent-md: #c7d9f9; --accent-bg: #f0f5ff;
    --green:      #059669; --green-lt:  #d1fae5;
    --orange:     #d97706; --orange-lt: #fef3c7;
    --red:        #dc2626; --red-lt:    #fee2e2;
    --purple:     #7c3aed; --purple-lt: #ede9fe;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 740px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes itemIn { from { opacity:0; transform:translateX(-8px);  } to { opacity:1; transform:translateX(0); } }

/* ── Page header ──────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    margin-bottom: 2rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    opacity: 0; animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size:0.67rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:var(--accent); margin-bottom:0.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }

.unread-tag {
    display: inline-flex; align-items: center; gap: 0.32rem;
    font-size: 0.68rem; font-weight: 800; padding: 0.22rem 0.7rem; border-radius: 20px;
    background: var(--accent); color: white; margin-left: 0.5rem; transform: translateY(-3px);
}

.btn-mark-read {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.52rem 1.1rem; border: 1.5px solid var(--border); background: white;
    color: var(--accent); border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.18s;
}
.btn-mark-read:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.btn-mark-clear {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.52rem 1.1rem; border: 1.5px solid var(--border); background: white;
    color: var(--red); border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.18s;
}
.btn-mark-clear:hover { border-color: var(--read); color: var(--red); background: var(--accent-bg); }

/* ── Panel ────────────────────────── */
.panel {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 1px 4px rgba(26,23,20,.04);
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.12s both;
}

/* ── Notification items ───────────── */
.notif-item {
    display: flex; gap: 1rem; padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--border-lt);
    transition: background 0.14s;
    animation: itemIn 0.28s cubic-bezier(0.22,1,0.36,1) both;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f4f7fd; }
.notif-item.unread { background: #fdfaf6; }
.notif-item.unread:hover { background: #f4f7fd; }

/* unread dot */
.unread-dot {
    position: absolute; top: 0; right: 0;
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--accent); border: 1.5px solid white;
}

/* icon */
.notif-ic-wrap { position: relative; flex-shrink: 0; }
.notif-ic {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.85rem;
}
.notif-ic.info    { background: var(--accent-lt); color: var(--accent); }
.notif-ic.success { background: var(--green-lt);  color: var(--green); }
.notif-ic.warning { background: var(--orange-lt); color: var(--orange); }
.notif-ic.error   { background: var(--red-lt);    color: var(--red); }
.notif-ic.default { background: var(--cream);     color: var(--ink-mute); border: 1px solid var(--border); }

/* content */
.notif-content { flex: 1; min-width: 0; }
.notif-top { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.25rem; }
.notif-title { font-size: 0.9rem; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-time  { font-size: 0.68rem; color: var(--ink-mute); white-space: nowrap; flex-shrink: 0; }
.notif-msg   { font-size: 0.82rem; color: var(--ink-soft); line-height: 1.55; margin: 0; }
.notif-link  {
    display: inline-flex; align-items: center; gap: 0.3rem;
    margin-top: 0.5rem; font-size: 0.75rem; font-weight: 700;
    color: var(--accent); text-decoration: none; transition: gap 0.15s;
}
.notif-link:hover { gap: 0.5rem; text-decoration: none; }

/* ── Empty state ──────────────────── */
.empty-state { text-align: center; padding: 4rem 1.5rem; }
.empty-state .es-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: var(--accent); opacity: 0.18; }
.empty-state h4 { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink-soft); margin: 0 0 0.35rem; }
.empty-state p  { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

/* ── Pagination ───────────────────── */
.pag-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.4rem; border-top: 1.5px solid var(--border-lt); background: #fafbff;
}
.pag-info { font-size: 0.75rem; color: var(--ink-mute); }
.pag-btns { display: flex; gap: 0.5rem; }
.pag-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.45rem 0.9rem; border: 1.5px solid var(--border);
    border-radius: 7px; background: white; color: var(--ink-soft);
    font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600;
    text-decoration: none; transition: all 0.18s;
}
.pag-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Section dividers (date groups) ─ */
.date-group { display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 1.4rem; background: #fafbff; border-bottom: 1px solid var(--border-lt); }
.date-group span { font-size: 0.62rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-mute); white-space: nowrap; }
.date-group::after { content:''; flex:1; height:1px; background:var(--border-lt); }
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Activity</div>
            <h1>
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="unread-tag"><?= $unread_count ?> new</span>
                <?php endif; ?>
            </h1>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn-mark-read" id="markAllReadBtn">
                <i class="fas fa-check-double"></i> Mark all read
            </button>
            <button class="btn-mark-clear" id="btnClearAll">
                <i class="fas fa-trash-alt"></i> Clear All
            </button>
        </div>
    </div>

    <!-- ── Notifications Panel ────── -->
    <div class="panel">

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <span class="es-icon"><i class="fas fa-bell-slash"></i></span>
                <h4>All caught up!</h4>
                <p>You have no notifications right now. Check back later.</p>
            </div>

        <?php else:
            // Group by date
            $groups = [];
            foreach ($notifications as $n) {
                $d = date('Y-m-d', strtotime($n['created_at']));
                $groups[$d][] = $n;
            }
            $today     = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $delay     = 0;

            foreach ($groups as $date => $items):
                $label = $date === $today ? 'Today' : ($date === $yesterday ? 'Yesterday' : date('M d, Y', strtotime($date)));
        ?>
            <div class="date-group"><span><?= $label ?></span></div>

            <?php foreach ($items as $n):
                $type = $n['type'] ?? 'default';
                $icon = match($type) {
                    'info'    => 'fa-info-circle',
                    'success' => 'fa-check-circle',
                    'warning' => 'fa-exclamation-triangle',
                    'error'   => 'fa-exclamation-circle',
                    default   => 'fa-bell',
                };
                $is_unread = !$n['is_read'];
                $time_str  = date('h:i A', strtotime($n['created_at']));
                $delay    += 30;
            ?>
                <div class="notif-item <?= $is_unread ? 'unread' : '' ?>" style="display:flex; cursor:pointer;" onclick="markReadAndRedirect(<?= $n['id'] ?>, '<?= $n['link'] ?>')">
                    <div class="notif-ic-wrap">
                        <div class="notif-ic <?= htmlspecialchars($type) ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <?php if ($is_unread): ?><span class="unread-dot"></span><?php endif; ?>
                    </div>
                    <div class="notif-content">
                        <div class="notif-top">
                            <span class="notif-title"><?= htmlspecialchars($n['title']) ?></span>
                            <span class="notif-time"><?= $time_str ?></span>
                        </div>
                        <p class="notif-msg"><?= htmlspecialchars($n['message']) ?></p>
                        <?php if (!empty($n['link']) && !in_array($n['link'], ['#','null'])): ?>
                            <div class="notif-link">
                                View details <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endforeach; ?>

            <!-- Pagination -->
            <?php if ($page > 1 || count($notifications) === $limit): ?>
            <div class="pag-bar">
                <span class="pag-info">Page <?= $page ?></span>
                <div class="pag-btns">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="pag-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    <?php if (count($notifications) === $limit): ?>
                        <a href="?page=<?= $page + 1 ?>" class="pag-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<!-- Clear All Modal -->
<div class="custom-modal" id="idxClearModal">
<div class="modal-box">
    <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="modal-title">Clear All Notifications?</div>
    <p class="modal-text">Are you sure you want to remove all notifications?<br>This action cannot be undone.</p>
    <div class="modal-actions">
        <button class="btn-modal-cancel" id="idxCancelClear">Cancel</button>
        <button class="btn-modal-confirm" id="idxConfirmClear">Yes, Clear All</button>
    </div>
</div>
</div>

<!-- Mark Read Modal -->
<div class="custom-modal" id="idxReadModal">
<div class="modal-box">
    <div class="modal-icon" style="color:var(--accent); background:var(--accent-lt);"><i class="fas fa-check-double"></i></div>
    <div class="modal-title">Mark All as Read?</div>
    <p class="modal-text">This will mark all your unread notifications as read.</p>
    <div class="modal-actions">
        <button class="btn-modal-cancel" id="idxCancelRead">Cancel</button>
        <button class="btn-modal-confirm" id="idxConfirmRead" style="background:var(--accent); border-color:var(--accent);">Yes, Mark Read</button>
    </div>
</div>
</div>

<script>
function markReadAndRedirect(id, link) {
    fetch('<?= BASE_URL ?>modules/api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(() => {
        if(link && link !== 'null' && link !== '') window.location.href = link;
        else location.reload();
    });
}

// Clear All Logic
const clearModal = document.getElementById('idxClearModal');
if(clearModal) {
    document.getElementById('btnClearAll').addEventListener('click', () => {
        clearModal.classList.add('active');
    });
    document.getElementById('idxCancelClear').addEventListener('click', () => {
        clearModal.classList.remove('active');
    });
    document.getElementById('idxConfirmClear').addEventListener('click', () => {
        fetch('<?= BASE_URL ?>modules/api/notifications.php?action=clear_all', { method: 'POST' })
        .then(() => location.reload());
    });
}

// Mark Read Logic
const readModal = document.getElementById('idxReadModal');
if(readModal) {
    document.getElementById('markAllReadBtn').addEventListener('click', () => {
        readModal.classList.add('active');
    });
    document.getElementById('idxCancelRead').addEventListener('click', () => {
        readModal.classList.remove('active');
    });
    document.getElementById('idxConfirmRead').addEventListener('click', () => {
        fetch('<?= BASE_URL ?>modules/api/notifications.php?action=mark_all_read', { method: 'POST' })
        .then(() => location.reload());
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>